"""
Zoho CRM Workflow Rules Service

Two-step setup process:
  1. Create Zoho Webhook entities (POST /settings/automation/webhooks)
  2. Create Workflow Rules that reference those webhook entities by ID
     (POST /settings/automation/workflow_rules)

Why Workflow Rules instead of Notification Channels:
  - Work reliably with Custom Modules (BTEC_*)
  - Never expire — no renewal scheduler needed
  - Fully managed from the backend (one-time setup / idempotent)

Required OAuth scopes:
  ZohoCRM.settings.workflow_rules.ALL   → create/list/delete workflow rules
  ZohoCRM.settings.automation_actions.ALL → create/list/delete webhook entities

Zoho API (v8):
  POST   /crm/v8/settings/automation/webhooks            → create webhook entity
  GET    /crm/v8/settings/automation/webhooks            → list webhook entities
  DELETE /crm/v8/settings/automation/webhooks/{id}       → delete webhook entity
  POST   /crm/v8/settings/automation/workflow_rules      → create rule
  GET    /crm/v8/settings/automation/workflow_rules      → list rules
  DELETE /crm/v8/settings/automation/workflow_rules/{id} → delete rule

State persistence:
  Saved IDs (webhook + rule) are stored in backend/zoho_rules_state.json
  so that re-setup can clean up even after a server restart.
"""

import json
import logging
import os
from datetime import datetime, timezone
from typing import Any, Dict, List, Optional

import httpx

from app.core.config import settings
from app.infra.zoho.auth import ZohoAuthClient

logger = logging.getLogger(__name__)

ZOHO_WORKFLOW_URL = "https://www.zohoapis.com/crm/v8/settings/automation/workflow_rules"
ZOHO_WEBHOOKS_URL = "https://www.zohoapis.com/crm/v8/settings/automation/webhooks"
ZOHO_MODULES_URL = "https://www.zohoapis.com/crm/v8/settings/modules"

# Name prefix used to identify all MZI-managed objects in Zoho
MZI_PREFIX = "MZI - "

# Known module IDs for this Zoho CRM organisation.
# Fetched 2026-02-23 via GET /crm/v8/settings/modules/<api_name>
# If any module is missing, _fetch_module_ids() will try the API at runtime.
KNOWN_MODULE_IDS: Dict[str, str] = {
    "BTEC_Students":         "5398830000032385830",
    "BTEC_Registrations":    "5398830000037633007",
    "BTEC_Payments":         "5398830000037635065",
    "BTEC_Classes":          "5398830000089417071",
    "BTEC_Enrollments":      "5398830000089418539",
    "BTEC_Grades":           "5398830000087309028",
    "BTEC_Student_Requests": "5398830000125921010",
    # BTEC Units module — api_name is "BTEC" (NOT "BTEC_Units")
    # Fetched 2026-02-23 via GET /crm/v8/settings/modules/BTEC
    "BTEC":                  "5398830000033020716",
}

# State file — written to backend/ directory (CWD when server runs)
_STATE_FILE = os.path.normpath(
    os.path.join(os.path.dirname(__file__), "..", "..", "zoho_rules_state.json")
)

# ---------------------------------------------------------------------------
# Module configuration
# Each module gets TWO webhook entities + TWO workflow rules:
#   1. create + edit  → "upsert" endpoint
#   2. delete         → "delete" endpoint
# Total: 8 modules × 2 = 16 webhooks + 16 rules
# ---------------------------------------------------------------------------
WORKFLOW_MODULES: List[Dict[str, Any]] = [
    {
        "module":          "BTEC_Students",
        "endpoint_upsert": "student_updated",
        "endpoint_delete": "student_deleted",
    },
    {
        "module":          "BTEC_Registrations",
        "endpoint_upsert": "registration_created",
        "endpoint_delete": "registration_deleted",
    },
    {
        "module":          "BTEC_Payments",
        "endpoint_upsert": "payment_recorded",
        "endpoint_delete": "payment_deleted",
    },
    {
        "module":          "BTEC_Classes",
        "endpoint_upsert": "class_created",
        "endpoint_delete": "class_deleted",
    },
    {
        "module":          "BTEC_Enrollments",
        "endpoint_upsert": "enrollment_updated",
        "endpoint_delete": "enrollment_deleted",
    },
    {
        "module":          "BTEC_Grades",
        "endpoint_upsert": "grade_submitted",
        "endpoint_delete": "grade_deleted",
    },
    {
        "module":          "BTEC_Student_Requests",
        "endpoint_upsert": "request_status_changed",
        "endpoint_delete": "request_deleted",
    },
    {
        # BTEC Units — api_name is "BTEC" (NOT "BTEC_Units") per Zoho org
        "module":          "BTEC",
        "endpoint_upsert": "btec_definition_updated",
        "endpoint_delete": "btec_definition_deleted",
    },
]


class ZohoWorkflowService:
    """
    Manages Zoho CRM Workflow Rules and their associated Webhook entities.

    Setup is two phases:
      Phase 1 — create Zoho Webhook entities (one per module/trigger combination)
      Phase 2 — create Workflow Rules that reference those webhooks by ID
    """

    def __init__(self) -> None:
        if not (
            settings.ZOHO_CLIENT_ID
            and settings.ZOHO_CLIENT_SECRET
            and settings.ZOHO_REFRESH_TOKEN
        ):
            raise ValueError(
                "Zoho credentials missing in .env: "
                "ZOHO_CLIENT_ID, ZOHO_CLIENT_SECRET, ZOHO_REFRESH_TOKEN are all required."
            )
        self.auth = ZohoAuthClient(
            client_id=settings.ZOHO_CLIENT_ID,
            client_secret=settings.ZOHO_CLIENT_SECRET,
            refresh_token=settings.ZOHO_REFRESH_TOKEN,
            region=settings.ZOHO_REGION,
        )

    # ------------------------------------------------------------------
    # Internal helpers
    # ------------------------------------------------------------------

    async def _headers(self) -> Dict[str, str]:
        token = await self.auth.get_access_token()
        return {
            "Authorization": f"Zoho-oauthtoken {token}",
            "Content-Type": "application/json",
        }

    @staticmethod
    def _notify_url(base_url: str, endpoint: str) -> str:
        # Clean base URL — no ${id} or query params.
        # The record ID is embedded in the raw JSON body using ${!Module.Id} syntax,
        # which Zoho substitutes at send time (POST webhooks do NOT support URL params).
        return f"{base_url.rstrip('/')}/api/v1/webhooks/student-dashboard/{endpoint}"

    async def _fetch_module_ids(
        self,
        client: httpx.AsyncClient,
        headers: Dict[str, str],
        modules: List[str],
    ) -> Dict[str, str]:
        """
        Resolve Zoho internal IDs for a list of module api_names.

        Uses cached KNOWN_MODULE_IDS first.  For any missing entries,
        calls GET /settings/modules/<api_name> (requires
        ZohoCRM.settings.modules.ALL scope).  Updates KNOWN_MODULE_IDS
        in-place as a session cache so each ID is fetched at most once.
        """
        result: Dict[str, str] = {}
        missing = [m for m in modules if m not in KNOWN_MODULE_IDS]

        for m in missing:
            try:
                r = await client.get(
                    f"{ZOHO_MODULES_URL}/{m}", headers=headers
                )
                if r.status_code == 200:
                    mods = r.json().get("modules", [])
                    if mods:
                        mid = str(mods[0].get("id", ""))
                        if mid:
                            KNOWN_MODULE_IDS[m] = mid
                            logger.info(f"  Resolved module ID: {m} = {mid}")
                        else:
                            logger.warning(f"  No id in modules response for {m}")
                    else:
                        logger.warning(f"  Empty modules list for {m}: {r.text[:100]}")
                else:
                    logger.warning(
                        f"  Could not fetch module ID for {m}: "
                        f"HTTP {r.status_code} {r.text[:150]}\n"
                        f"  → Add ZohoCRM.settings.modules.ALL scope to your OAuth token."
                    )
            except Exception as exc:
                logger.warning(f"  Exception fetching module ID for {m}: {exc}")

        for m in modules:
            mid = KNOWN_MODULE_IDS.get(m, "")
            if mid:
                result[m] = mid
            else:
                logger.error(
                    f"  Module ID for '{m}' is unknown — webhook creation will fail. "
                    f"Re-generate OAuth token with ZohoCRM.settings.modules.ALL scope."
                )
        return result

    @staticmethod
    def _build_zoho_webhook(
        name: str, notify_url: str, module: str, module_id: str
    ) -> Dict[str, Any]:
        """
        Build a Zoho Webhook entity payload.

        Zoho webhook parameters docs:
          module must be {api_name, id} — plain string is rejected
          http_method must be uppercase
          body.type = "raw", format = "JSON"
        """
        return {
            "name": name,
            "module": {"api_name": module, "id": module_id},
            "http_method": "POST",
            "url": notify_url,
            "authentication": {"type": "general"},
            "body": {
                "type": "raw",
                "format": "JSON",
                # Zoho DOES substitute ${!ModuleName.Id} inside raw_data_content
                # (confirmed in official Zoho Webhooks API docs — POST body type raw).
                # This is the only reliable way to get the record ID in the body.
                "raw_data_content": json.dumps(
                    {"zoho_id": f"${{!{module}.Id}}", "module": module}
                ),
            },
        }

    @staticmethod
    def _build_rule(
        rule_name: str,
        module: str,
        triggers: List[str],
        webhook_id: str,
    ) -> Dict[str, Any]:
        """
        Build a Zoho v8 Workflow Rule payload that references a pre-created
        Webhook entity by its Zoho ID.

        execute_when.type values (v8 API):
          "create_or_edit"  → fires on create + edit
          "delete"          → fires on delete

        conditions.criteria "id is_not_empty" matches ALL records
        (every CRM record has an id), acting as an unconditional trigger.
        """
        execute_when_type = "delete" if "delete" in triggers else "create_or_edit"
        # "repeat" is only valid for create_or_edit triggers; delete triggers reject it
        execute_when_details: Dict[str, Any] = {"type": "all_records"}
        if execute_when_type == "create_or_edit":
            execute_when_details["repeat"] = True
        return {
            "name": rule_name,
            "description": "MZI auto-sync - managed by backend, do not edit manually",
            "module": {"api_name": module},
            "active": True,
            "execute_when": {
                "type": execute_when_type,
                "details": execute_when_details,
            },
            "conditions": [
                {
                    "sequence_number": 1,
                    "criteria_pattern": "1",
                    "criteria": [
                        {
                            "criteria_group_id": 1,
                            "comparator": "is_not_empty",
                            "field": {"api_name": "id"},
                            "value": "",
                        }
                    ],
                    "instant_actions": {
                        "actions": [
                            {
                                "type": "webhooks",
                                "id": webhook_id,
                            }
                        ],
                    },
                }
            ],
        }

    # ------------------------------------------------------------------
    # State file helpers
    # ------------------------------------------------------------------

    def _load_state(self) -> Dict[str, Any]:
        try:
            if os.path.exists(_STATE_FILE):
                with open(_STATE_FILE, "r", encoding="utf-8") as f:
                    return json.load(f)
        except Exception as e:
            logger.warning(f"Could not load rules state: {e}")
        return {"rules": [], "webhooks": [], "webhook_base_url": ""}

    def _save_state(self, state: Dict[str, Any]) -> None:
        try:
            with open(_STATE_FILE, "w", encoding="utf-8") as f:
                json.dump(state, f, indent=2, ensure_ascii=False)
            logger.info(f"Saved state: {len(state.get('rules', []))} rules, "
                        f"{len(state.get('webhooks', []))} webhooks → {_STATE_FILE}")
        except Exception as e:
            logger.warning(f"Could not save rules state: {e}")

    # ------------------------------------------------------------------
    # Zoho Webhook entity CRUD
    # ------------------------------------------------------------------

    async def _create_zoho_webhook(
        self,
        client: httpx.AsyncClient,
        headers: Dict[str, str],
        name: str,
        notify_url: str,
        module: str,
        module_id: str,
    ) -> Optional[str]:
        """
        Create one Zoho Webhook entity and return its Zoho-assigned ID.
        Returns None on failure.
        """
        payload = self._build_zoho_webhook(name, notify_url, module, module_id)
        try:
            resp = await client.post(
                ZOHO_WEBHOOKS_URL,
                headers=headers,
                json={"webhooks": [payload]},
            )
            logger.info(f"  [Webhook '{name}'] HTTP {resp.status_code}")
            if resp.status_code not in (200, 201):
                logger.warning(f"  Webhook creation failed: {resp.text[:400]}")
                return None
            data = resp.json()
            items = data.get("webhooks", [])
            if not items:
                logger.warning(f"  Empty webhooks response: {resp.text[:200]}")
                return None
            item = items[0]
            details = item.get("details", item)
            webhook_id = str(details.get("id", ""))
            if webhook_id:
                logger.info(f"  Webhook '{name}' created  id={webhook_id}")
            else:
                logger.warning(f"  Webhook created but no id returned: {item}")
            return webhook_id or None
        except Exception as e:
            logger.warning(f"  Exception creating webhook '{name}': {e}")
            return None

    async def _delete_zoho_webhooks(
        self,
        client: httpx.AsyncClient,
        headers: Dict[str, str],
        webhook_ids: List[str],
    ) -> int:
        """Delete Zoho Webhook entities by ID list. Returns count deleted."""
        deleted = 0
        for wh_id in webhook_ids:
            try:
                resp = await client.delete(
                    f"{ZOHO_WEBHOOKS_URL}/{wh_id}",
                    headers=headers,
                )
                if resp.status_code in (200, 204):
                    deleted += 1
                    logger.info(f"  Deleted webhook {wh_id}")
                else:
                    logger.warning(
                        f"  Failed to delete webhook {wh_id}: "
                        f"HTTP {resp.status_code} {resp.text[:150]}"
                    )
            except Exception as e:
                logger.warning(f"  Error deleting webhook {wh_id}: {e}")
        return deleted

    # ------------------------------------------------------------------
    # Public API
    # ------------------------------------------------------------------

    async def setup_all_rules(self, webhook_base_url: str) -> Dict[str, Any]:
        """
        Create all 14 Webhook entities + 14 Workflow Rules in Zoho CRM.
        Deletes any existing MZI objects first (idempotent — safe to call multiple times).

        Args:
            webhook_base_url: Public base URL of this backend.
                e.g. "https://polyphyletically-unnagged-amare.ngrok-free.app"
        """
        # ---------------------------------------------------------------
        # Phase 0: Clean up existing MZI rules and webhooks
        # ---------------------------------------------------------------
        delete_result = await self.delete_all_rules()
        logger.info(f"Pre-cleanup: deleted {delete_result.get('deleted_rules', 0)} rules, "
                    f"{delete_result.get('deleted_webhooks', 0)} webhooks.")

        # ---------------------------------------------------------------
        # Phase 1: Create Zoho Webhook entities (one per trigger type per module)
        # ---------------------------------------------------------------
        headers = await self._headers()
        created_webhooks: List[Dict] = []
        errors: List[Any] = []

        # Map from (module, trigger_type) → webhook_id
        webhook_id_map: Dict[str, str] = {}

        # ---------------------------------------------------------------
        # Phase 0b: Resolve module IDs (needed by Zoho webhook creation API)
        # ---------------------------------------------------------------
        module_names = [m["module"] for m in WORKFLOW_MODULES]
        async with httpx.AsyncClient(timeout=30.0) as client:
            module_id_map = await self._fetch_module_ids(client, headers, module_names)

        async with httpx.AsyncClient(timeout=30.0) as client:
            logger.info(f"Phase 1: Creating {len(WORKFLOW_MODULES) * 2} Webhook entities...")
            for m in WORKFLOW_MODULES:
                module = m["module"]
                module_id = module_id_map.get(module, "")
                if not module_id:
                    err_msg = (
                        f"Unknown module ID for {module} — "
                        "re-generate OAuth token with ZohoCRM.settings.modules.ALL scope."
                    )
                    errors.append({"name": f"{MZI_PREFIX}{module}", "error": err_msg})
                    logger.error(f"  {err_msg}")
                    continue

                # Upsert webhook
                upsert_name = f"{MZI_PREFIX}{module} - create edit"
                upsert_url = self._notify_url(webhook_base_url, m["endpoint_upsert"])
                upsert_id = await self._create_zoho_webhook(
                    client, headers, upsert_name, upsert_url, module, module_id
                )
                if upsert_id:
                    webhook_id_map[f"{module}:upsert"] = upsert_id
                    created_webhooks.append({"webhook_id": upsert_id, "name": upsert_name})
                else:
                    errors.append({"name": upsert_name, "error": "Webhook entity creation failed"})

                # Delete webhook
                delete_name = f"{MZI_PREFIX}{module} - delete"
                delete_url = self._notify_url(webhook_base_url, m["endpoint_delete"])
                delete_id = await self._create_zoho_webhook(
                    client, headers, delete_name, delete_url, module, module_id
                )
                if delete_id:
                    webhook_id_map[f"{module}:delete"] = delete_id
                    created_webhooks.append({"webhook_id": delete_id, "name": delete_name})
                else:
                    errors.append({"name": delete_name, "error": "Webhook entity creation failed"})

        # ---------------------------------------------------------------
        # Phase 2: Create Workflow Rules referencing webhook IDs
        # ---------------------------------------------------------------
        created_rules: List[Dict] = []

        async with httpx.AsyncClient(timeout=30.0) as client:
            logger.info(f"Phase 2: Creating {len(WORKFLOW_MODULES) * 2} Workflow Rules...")
            for m in WORKFLOW_MODULES:
                module = m["module"]

                for trigger_type, key_suffix, rule_suffix, triggers in [
                    ("upsert", "upsert", "create edit", ["create", "edit"]),
                    ("delete", "delete", "delete",       ["delete"]),
                ]:
                    rule_name = f"{MZI_PREFIX}{module} - {rule_suffix}"
                    wh_id = webhook_id_map.get(f"{module}:{key_suffix}")

                    if not wh_id:
                        errors.append({"name": rule_name, "error": "No webhook ID available — skipped"})
                        continue

                    rule_payload = self._build_rule(
                        rule_name=rule_name,
                        module=module,
                        triggers=triggers,
                        webhook_id=wh_id,
                    )
                    try:
                        resp = await client.post(
                            ZOHO_WORKFLOW_URL,
                            headers=headers,
                            json={"workflow_rules": [rule_payload]},
                        )
                        logger.info(f"  [{rule_name}] HTTP {resp.status_code}")

                        if resp.status_code not in (200, 201):
                            body = resp.text[:300]
                            hint = (" (check OAuth scope: ZohoCRM.settings.workflow_rules.ALL)"
                                    if resp.status_code == 401 else "")
                            errors.append({"name": rule_name, "error": f"HTTP {resp.status_code}: {body}{hint}"})
                            logger.warning(f"  [{rule_name}] Failed: {body}")
                            continue

                        data = resp.json()
                        items = data.get("workflow_rules", [])
                        item = items[0] if items else {}
                        details = item.get("details", item)
                        rule_id = str(details.get("id", ""))

                        if item.get("status") == "error" or not rule_id:
                            errors.append({"name": rule_name, "error": item.get("message", str(item))})
                            logger.warning(f"  [{rule_name}] Zoho error: {item}")
                        else:
                            created_rules.append({
                                "rule_id": rule_id,
                                "name": rule_name,
                                "webhook_id": wh_id,
                                "status": "created",
                            })
                            logger.info(f"  [{rule_name}] Created (rule_id={rule_id})")

                    except Exception as e:
                        errors.append({"name": rule_name, "error": str(e)})
                        logger.warning(f"  [{rule_name}] Exception: {e}")

        # ---------------------------------------------------------------
        # Phase 3: Persist state
        # ---------------------------------------------------------------
        self._save_state({
            "webhook_base_url": webhook_base_url,
            "created_at": datetime.now(timezone.utc).isoformat(),
            "rules": created_rules,
            "webhooks": created_webhooks,
        })

        total = len(WORKFLOW_MODULES) * 2
        success_count = len(created_rules)
        logger.info(
            f"Setup done: {success_count}/{total} rules created, "
            f"{len(created_webhooks)} webhooks created, {len(errors)} errors."
        )

        return {
            "success": success_count == total and len(errors) == 0,
            "created": success_count,
            "total": total,
            "errors": errors,
            "webhook_base_url": webhook_base_url,
            "rules": created_rules,
            "webhooks_created": len(created_webhooks),
            "deleted_old": delete_result,
        }

    async def list_rules(self) -> Dict[str, Any]:
        """
        Return saved rule state + live count from Zoho API.
        """
        state = self._load_state()

        try:
            headers = await self._headers()
            async with httpx.AsyncClient(timeout=30.0) as client:
                resp = await client.get(ZOHO_WORKFLOW_URL, headers=headers)

            if resp.status_code == 200:
                all_rules = resp.json().get("workflow_rules", [])
                mzi_rules = [r for r in all_rules if r.get("name", "").startswith(MZI_PREFIX)]
                return {
                    "saved_rules":       state.get("rules", []),
                    "saved_base_url":    state.get("webhook_base_url", ""),
                    "zoho_live_count":   len(mzi_rules),
                    "zoho_live_rules":   [
                        {"id": r.get("id"), "name": r.get("name"), "active": r.get("active")}
                        for r in mzi_rules
                    ],
                }
            elif resp.status_code == 204:
                return {
                    "saved_rules":     state.get("rules", []),
                    "zoho_live_count": 0,
                    "zoho_live_rules": [],
                }
            else:
                logger.warning(f"Zoho list returned {resp.status_code}")
        except Exception as e:
            logger.warning(f"Could not fetch live rules from Zoho: {e}")

        return {
            "saved_rules":     state.get("rules", []),
            "saved_base_url":  state.get("webhook_base_url", ""),
            "zoho_live_rules": None,
            "note": "Could not reach Zoho API — showing saved state only.",
        }

    async def delete_all_rules(self) -> Dict[str, Any]:
        """
        Delete all MZI-managed Workflow Rules AND Webhook entities from Zoho CRM.
        Uses saved state file first; falls back to searching Zoho by name prefix.
        """
        state = self._load_state()
        saved_rules = state.get("rules", [])
        saved_webhooks = state.get("webhooks", [])

        headers = await self._headers()

        # Fallback: if no saved rule IDs, search Zoho by name prefix
        if not saved_rules:
            logger.info("No saved rule IDs — searching Zoho by name prefix...")
            try:
                async with httpx.AsyncClient(timeout=30.0) as client:
                    resp = await client.get(ZOHO_WORKFLOW_URL, headers=headers)
                if resp.status_code == 200:
                    all_rules = resp.json().get("workflow_rules", [])
                    saved_rules = [
                        {"rule_id": str(r["id"]), "name": r.get("name", "")}
                        for r in all_rules
                        if r.get("name", "").startswith(MZI_PREFIX) and r.get("id")
                    ]
                    logger.info(f"Found {len(saved_rules)} MZI rules in Zoho by name.")
            except Exception as e:
                logger.warning(f"Could not fetch rules for deletion: {e}")

        # Fallback: if no saved webhook IDs, search Zoho by name prefix
        if not saved_webhooks:
            logger.info("No saved webhook IDs — searching Zoho webhooks by name prefix...")
            try:
                async with httpx.AsyncClient(timeout=30.0) as client:
                    resp = await client.get(ZOHO_WEBHOOKS_URL, headers=headers)
                if resp.status_code == 200:
                    all_wh = resp.json().get("webhooks", [])
                    saved_webhooks = [
                        {"webhook_id": str(w["id"]), "name": w.get("name", "")}
                        for w in all_wh
                        if w.get("name", "").startswith(MZI_PREFIX) and w.get("id")
                    ]
                    logger.info(f"Found {len(saved_webhooks)} MZI webhooks in Zoho by name.")
            except Exception as e:
                logger.warning(f"Could not fetch webhooks for deletion: {e}")

        deleted_rules = 0
        failed_rules = 0
        deleted_webhooks = 0

        async with httpx.AsyncClient(timeout=30.0) as client:
            # Delete workflow rules
            for rule in saved_rules:
                rule_id = rule.get("rule_id") or rule.get("id")
                if not rule_id:
                    continue
                try:
                    resp = await client.delete(
                        f"{ZOHO_WORKFLOW_URL}/{rule_id}",
                        headers=headers,
                    )
                    if resp.status_code in (200, 204):
                        deleted_rules += 1
                        logger.info(f"Deleted rule {rule_id}: {rule.get('name', '')}")
                    else:
                        failed_rules += 1
                        logger.warning(
                            f"Failed to delete rule {rule_id}: "
                            f"HTTP {resp.status_code} — {resp.text[:150]}"
                        )
                except Exception as e:
                    failed_rules += 1
                    logger.warning(f"Error deleting rule {rule_id}: {e}")

            # Delete webhook entities
            wh_ids = [w.get("webhook_id") or w.get("id") for w in saved_webhooks if w]
            deleted_webhooks = await self._delete_zoho_webhooks(
                client, headers, [wid for wid in wh_ids if wid]
            )

        # Clear state file
        self._save_state({"rules": [], "webhooks": [], "webhook_base_url": ""})

        logger.info(
            f"Deletion complete: {deleted_rules} rules deleted, "
            f"{deleted_webhooks} webhooks deleted, {failed_rules} rule failures."
        )
        return {
            "deleted_rules": deleted_rules,
            "deleted_webhooks": deleted_webhooks,
            "failed_rules": failed_rules,
            "total_rules": len(saved_rules),
            "total_webhooks": len(saved_webhooks),
        }
