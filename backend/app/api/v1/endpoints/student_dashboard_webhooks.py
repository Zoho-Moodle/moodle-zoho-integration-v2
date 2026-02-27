"""
Student Dashboard Webhook Handlers  thin aggregator

This module composes three focused sub-modules into a single FastAPI router:

  webhooks_dashboard_sync.py    local_mzi_* (student/registration/payment/grade/request)
  webhooks_moodle_courses.py    core_course_* (class_updated / class_deleted)
  webhooks_moodle_enrol.py      enrol_manual_* (enrollment_updated / enrollment_deleted)

Shared helpers live in webhooks_shared.py.

Backward-compat re-exports (used by full_sync.py and tests):
  ZOHO_MODULE_MAP, transform_zoho_to_moodle, call_moodle_ws, fetch_zoho_full_record,
  resolve_zoho_payload, FIELD_MAPPINGS, read_zoho_body, ensure_registration_synced
"""
from fastapi import APIRouter

# ---------------------------------------------------------------------------
# Shared helpers  re-exported so existing imports in full_sync.py keep working
# ---------------------------------------------------------------------------
from app.api.v1.endpoints.webhooks_shared import (           # noqa: F401
    ZOHO_MODULE_MAP,
    FIELD_MAPPINGS,
    transform_zoho_to_moodle,
    call_moodle_ws,
    fetch_zoho_full_record,
    resolve_zoho_payload,
    read_zoho_body,
    ensure_registration_synced,
    extract_zoho_record,
)

# ---------------------------------------------------------------------------
# Sub-routers
# ---------------------------------------------------------------------------
from app.api.v1.endpoints.webhooks_dashboard_sync import router as _dashboard_router
from app.api.v1.endpoints.webhooks_moodle_courses import router as _courses_router
from app.api.v1.endpoints.webhooks_moodle_enrol import router as _enrol_router
from app.api.v1.endpoints.webhooks_btec_units import router as _btec_units_router

# ---------------------------------------------------------------------------
# Composed router  included in api/v1/router.py as-is
# ---------------------------------------------------------------------------
router = APIRouter()
router.include_router(_dashboard_router)
router.include_router(_courses_router)
router.include_router(_enrol_router)
router.include_router(_btec_units_router)
