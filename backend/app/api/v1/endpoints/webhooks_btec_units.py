"""
BTEC Unit Definition Webhook Handlers — Zoho BTEC → Moodle grading templates

Handles the full BTEC unit lifecycle:
  - btec_definition_updated: Zoho BTEC record created / updated →
        extract P/M/D criteria → upsert Moodle grading definition via
        local_mzi_create_btec_definition (identified by zoho_unit_id).
  - btec_definition_deleted: Zoho BTEC record deleted →
        call local_mzi_delete_btec_definition to remove grading definition.

Zoho module api_name: "BTEC"  (NOT "BTEC_Units" — intentional per this org).

Criteria field layout in the Zoho BTEC record:
  Pass (level=1):        P1_description … P19_description
  Merit (level=2):       M1_description … M9_description
  Distinction (level=3): D1_description … D6_description

Sort orders are assigned per-level (1-N), matching the PHP
gradingform_btec_criteria table expectations.

Routes (all under prefix /webhooks/student-dashboard):
  POST /btec_definition_updated
  POST /btec_definition_deleted
"""
import logging
from typing import Dict, Any, List

from fastapi import APIRouter, HTTPException, Request

from app.api.v1.endpoints.webhooks_shared import (
    call_moodle_ws,
    fetch_zoho_full_record,
    read_zoho_body,
    resolve_zoho_payload,
)

logger = logging.getLogger(__name__)
router = APIRouter()


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def _extract_criteria(record: Dict[str, Any]) -> List[Dict[str, Any]]:
    """
    Build the criteria array expected by local_mzi_create_btec_definition.

    Iterates over P1-P19 (level=1), M1-M9 (level=2), D1-D6 (level=3).
    Only includes criteria whose description field is non-empty.

    Each entry: {shortname, description, level, sortorder}
    sortorder is 1-based and resets per level.
    """
    criteria: List[Dict[str, Any]] = []

    levels = [
        ("P", range(1, 20), 1),   # Pass: P1…P19, level=1
        ("M", range(1, 10), 2),   # Merit: M1…M9, level=2
        ("D", range(1, 7),  3),   # Distinction: D1…D6, level=3
    ]

    global_sort = 1  # continuous across all levels so Moodle orders: P1…Pn, M1…Mn, D1…Dn
    for prefix, idx_range, level in levels:
        for i in idx_range:
            field = f"{prefix}{i}_description"
            desc = record.get(field, "")
            if not desc:
                continue
            criteria.append({
                "shortname":   f"{prefix}{i}",
                "description": str(desc).strip(),
                "level":       level,
                "sortorder":   global_sort,
            })
            global_sort += 1

    return criteria


def _flatten_criteria_params(criteria: List[Dict[str, Any]]) -> Dict[str, Any]:
    """
    Flatten criteria list to Moodle REST form-encoded key format:
      criteria[0][shortname], criteria[0][description], ...

    httpx encodes a flat dict as standard form data, which Moodle REST parses
    into the expected external_multiple_structure.
    """
    flat: Dict[str, Any] = {}
    for idx, c in enumerate(criteria):
        flat[f"criteria[{idx}][shortname]"]   = c["shortname"]
        flat[f"criteria[{idx}][description]"] = c["description"]
        flat[f"criteria[{idx}][level]"]       = c["level"]
        flat[f"criteria[{idx}][sortorder]"]   = c["sortorder"]
    return flat


# ===========================================================================
# ENDPOINT: btec_definition_updated  (create + edit)
# ===========================================================================

@router.post("/btec_definition_updated")
async def handle_btec_definition_updated(request: Request):
    """
    Webhook: BTEC unit created / updated in Zoho.

    Steps:
    1. Read Zoho notification body (zoho_id injected from URL ?zoho_id param).
    2. Fetch full BTEC record from Zoho (module="BTEC").
    3. Extract unit name and P/M/D criteria descriptions.
    4. Call local_mzi_create_btec_definition to upsert the Moodle grading
       definition (idempotent — matched by zoho_unit_id).
    """
    try:
        raw = await read_zoho_body(request)
        payload = await resolve_zoho_payload(raw, "btec_units")

        zoho_unit_id = payload.get("id", "")
        if not zoho_unit_id:
            raise HTTPException(status_code=400, detail="Missing Zoho unit ID in payload")

        unit_name = payload.get("Name", "")
        if not unit_name:
            raise HTTPException(status_code=400, detail="Missing unit Name in Zoho BTEC record")

        criteria = _extract_criteria(payload)

        if not criteria:
            logger.warning(
                f"⚠️ BTEC unit {zoho_unit_id} ({unit_name!r}) has no criteria fields set. "
                f"Syncing definition with empty criteria list."
            )

        logger.info(
            f"🎓 Syncing BTEC unit: {unit_name!r} (zoho_id={zoho_unit_id}) "
            f"— {len(criteria)} criteria [{sum(1 for c in criteria if c['level'] == 1)}P "
            f"{sum(1 for c in criteria if c['level'] == 2)}M "
            f"{sum(1 for c in criteria if c['level'] == 3)}D]"
        )

        params: Dict[str, Any] = {
            "name":          unit_name,
            "description":   unit_name,   # no dedicated description field in this module
            "zoho_unit_id":  zoho_unit_id,
        }
        params.update(_flatten_criteria_params(criteria))

        result = await call_moodle_ws("local_mzi_create_btec_definition", params)

        logger.info(
            f"✅ BTEC definition synced: {unit_name!r} "
            f"definition_id={result.get('definition_id')} "
            f"criteria_count={result.get('criteria_count')}"
        )
        return {
            "status":         "success",
            "zoho_unit_id":   zoho_unit_id,
            "unit_name":      unit_name,
            "criteria_count": len(criteria),
            "moodle_response": result,
        }

    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"❌ btec_definition_updated error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))


# ===========================================================================
# ENDPOINT: btec_definition_deleted
# ===========================================================================

@router.post("/btec_definition_deleted")
async def handle_btec_definition_deleted(request: Request):
    """
    Webhook: BTEC unit deleted in Zoho.

    Calls local_mzi_delete_btec_definition to remove the Moodle grading
    definition (criteria + definition + area if empty + mapping row).
    Identified by zoho_unit_id — safe to call if unit never existed.
    """
    try:
        raw = await read_zoho_body(request)

        zoho_unit_id = raw.get("_url_zoho_id", "") or raw.get("id", "")
        if not zoho_unit_id:
            raise HTTPException(status_code=400, detail="Missing Zoho unit ID")

        logger.info(f"🗑️ Deleting BTEC definition for zoho_unit_id={zoho_unit_id}")

        result = await call_moodle_ws("local_mzi_delete_btec_definition", {
            "zoho_unit_id": zoho_unit_id,
        })

        logger.info(
            f"✅ BTEC definition deleted: {result.get('message')} "
            f"(definition_id={result.get('definition_id')})"
        )
        return {
            "status":        "success",
            "zoho_unit_id":  zoho_unit_id,
            "moodle_response": result,
        }

    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"❌ btec_definition_deleted error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))
