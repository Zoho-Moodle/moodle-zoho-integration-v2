"""
Dynamic Field Mapping Loader
============================
Reads field mappings from the `field_mappings` DB table.
Falls back to the hardcoded FIELD_MAPPINGS dict in webhooks_shared.py
if the table is empty (i.e. wizard hasn't been run yet).

Usage
-----
    from app.infra.db.mapping_loader import get_field_mappings

    # In any endpoint or service:
    with Session(engine) as session:
        mappings = get_field_mappings(session)
        student_map = mappings.get("students", {})
        # student_map["First_Name"] == ("first_name", "value")

Tenant
------
Default tenant_id is "default".  When multi-tenant support is needed,
pass the tenant_id explicitly.
"""

from __future__ import annotations

import logging
from typing import Dict, Optional, Tuple

from sqlalchemy.orm import Session

logger = logging.getLogger(__name__)

# Type alias: { zoho_api_name -> (canonical_field, transform_type) }
FieldMap = Dict[str, Tuple[str, str]]
# Top-level: { service_key -> FieldMap }
AllMappings = Dict[str, FieldMap]


def ensure_default_tenant(session: Session) -> None:
    """Create the 'default' tenant record if it doesn't exist yet."""
    from app.infra.db.models.extension import TenantProfile
    existing = session.get(TenantProfile, "default")
    if existing is None:
        session.add(TenantProfile(
            tenant_id="default",
            name="Default Tenant",
            status="active",
        ))
        session.commit()
        logger.info("mapping_loader: created default tenant record")


def get_field_mappings(
    session: Session,
    tenant_id: str = "default",
    fallback: bool = True,
) -> AllMappings:
    """
    Load field mappings from the DB.

    Returns a dict matching the format of webhooks_shared.FIELD_MAPPINGS:
        {
          "students": {
              "First_Name": ("first_name", "value"),
              "Academic_Email": ("email", "value"),
              ...
          },
          "registrations": { ... },
          ...
        }

    If no rows exist for this tenant and `fallback=True`, returns the
    hardcoded FIELD_MAPPINGS from webhooks_shared (backward-compatible).
    """
    try:
        from app.infra.db.models.extension import FieldMapping

        rows = (
            session.query(FieldMapping)
            .filter_by(tenant_id=tenant_id)
            .all()
        )

        if not rows:
            if fallback:
                logger.debug(
                    "mapping_loader: no DB mappings for tenant=%r — using hardcoded fallback",
                    tenant_id,
                )
                return _hardcoded_fallback()
            return {}

        result: AllMappings = {}
        for row in rows:
            svc = row.module_name
            if svc not in result:
                result[svc] = {}
            transform = "value"
            if row.transform_rules_json:
                transform = row.transform_rules_json.get("type", "value")
            result[svc][row.zoho_field_api_name] = (row.canonical_field, transform)

        logger.debug(
            "mapping_loader: loaded %d field rows across %d services from DB",
            len(rows),
            len(result),
        )
        return result

    except Exception as exc:
        logger.warning("mapping_loader: DB error (%s) — using hardcoded fallback", exc)
        if fallback:
            return _hardcoded_fallback()
        return {}


def get_zoho_module_for_service(service: str, tenant_id: str = "default") -> Optional[str]:
    """
    Return the Zoho module API name configured for a service.
    Reads from the .env key ZOHO_MODULE_<SERVICE_UPPER>.
    E.g. ZOHO_MODULE_STUDENTS=BTEC_Students
    """
    import os
    key = f"ZOHO_MODULE_{service.upper()}"
    val = os.environ.get(key, "").strip()
    if val:
        return val
    # Hardcoded defaults as fallback
    _defaults = {
        "students":         "BTEC_Students",
        "registrations":    "BTEC_Registrations",
        "classes":          "BTEC_Classes",
        "enrollments":      "BTEC_Enrollments",
        "grades":           "BTEC_Grades",
        "payments":         "BTEC_Payments",
        "student_requests": "BTEC_Student_Requests",
        "teachers":         "BTEC_Teachers",
    }
    return _defaults.get(service)


def _hardcoded_fallback() -> AllMappings:
    """Return the hardcoded FIELD_MAPPINGS from webhooks_shared as fallback."""
    try:
        from app.api.v1.endpoints.webhooks_shared import FIELD_MAPPINGS
        # Convert multi-target fields (lists) – keep only first target per field
        result: AllMappings = {}
        for svc, mapping in FIELD_MAPPINGS.items():
            result[svc] = {}
            for zoho_field, target in mapping.items():
                if isinstance(target, list):
                    result[svc][zoho_field] = target[0]
                else:
                    result[svc][zoho_field] = target
        return result
    except Exception:
        return {}
