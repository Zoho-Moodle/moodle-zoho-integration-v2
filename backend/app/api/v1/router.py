from fastapi import APIRouter
from app.api.v1.endpoints.sync_students import router as sync_students_router
from app.api.v1.endpoints.sync_programs import router as sync_programs_router
from app.api.v1.endpoints.sync_classes import router as sync_classes_router
from app.api.v1.endpoints.sync_enrollments import router as sync_enrollments_router
from app.api.v1.endpoints.sync_registrations import router as sync_registrations_router
from app.api.v1.endpoints.sync_payments import router as sync_payments_router
from app.api.v1.endpoints.sync_units import router as sync_units_router
from app.api.v1.endpoints.sync_grades import router as sync_grades_router
from app.api.v1.endpoints.health import router as health_router
from app.api.v1.endpoints.debug_enhanced import router as debug_router

# Extension API endpoints
from app.api.v1.endpoints.extension_tenants import router as extension_tenants_router
from app.api.v1.endpoints.extension_settings import router as extension_settings_router
from app.api.v1.endpoints.extension_mappings import router as extension_mappings_router
from app.api.v1.endpoints.extension_runs import router as extension_runs_router

# Event Router (webhooks)
from app.api.v1.endpoints.events import router as events_router
from app.api.v1.endpoints.moodle_events import router as moodle_events_router

# Webhooks - Receive events from Moodle Plugin
from app.api.v1.endpoints.webhooks import router as webhooks_router

# Student Dashboard Webhooks - Zoho → Moodle DB (Direct)
from app.api.v1.endpoints.student_dashboard_webhooks import router as student_dashboard_webhooks_router

# Moodle Ingestion endpoints (Moodle → Backend)
from app.api.v1.endpoints.moodle_users import router as moodle_users_router
from app.api.v1.endpoints.moodle_enrollments import router as moodle_enrollments_router
from app.api.v1.endpoints.moodle_grades import router as moodle_grades_router

# Course Creation (Zoho → Moodle)
from app.api.v1.endpoints.create_course import router as create_course_router

# BTEC Templates Sync (Zoho → Moodle)
from app.api.v1.endpoints.btec_templates import router as btec_templates_router

# Admin: Zoho webhook setup
from app.api.v1.endpoints.zoho_webhook_setup import router as zoho_webhook_setup_router

# Admin: Full Zoho → Moodle sync
from app.api.v1.endpoints.full_sync import router as full_sync_router
# Student Requests — Moodle → Zoho write-back
from app.api.v1.endpoints.submit_request import router as submit_request_router
router = APIRouter()

# Sync endpoints - Phase 1/2/3
router.include_router(sync_students_router, tags=["sync"])
router.include_router(sync_programs_router, tags=["sync"])
router.include_router(sync_classes_router, tags=["sync"])
router.include_router(sync_enrollments_router, tags=["sync"])

# Sync endpoints - Phase 4 (BTEC modules)
router.include_router(sync_registrations_router, tags=["sync"])
router.include_router(sync_payments_router, tags=["sync"])
router.include_router(sync_units_router, tags=["sync"])
router.include_router(sync_grades_router, tags=["sync"])

# Extension API - Configuration & Monitoring
router.include_router(extension_tenants_router)
router.include_router(extension_settings_router)
router.include_router(extension_mappings_router)
router.include_router(extension_runs_router)

# Event Router - Webhooks
router.include_router(events_router)
router.include_router(moodle_events_router)

# Webhooks - Receive events from Moodle Plugin
router.include_router(webhooks_router, tags=["webhooks"])

# Student Dashboard Webhooks - Zoho → Moodle DB
router.include_router(student_dashboard_webhooks_router, prefix="/webhooks/student-dashboard", tags=["student-dashboard"])

# Moodle Ingestion - Moodle → Backend
router.include_router(moodle_users_router)
router.include_router(moodle_enrollments_router)
router.include_router(moodle_grades_router)

# Course Creation - Zoho → Moodle
router.include_router(create_course_router, tags=["classes"])

# BTEC Templates - Zoho → Moodle
router.include_router(btec_templates_router, tags=["btec"])

# Admin - Zoho Workflow Rules setup (permanent, works with all BTEC Custom Modules)
router.include_router(zoho_webhook_setup_router)

# Admin - Full Zoho → Moodle sync
router.include_router(full_sync_router)

# Student Requests — Moodle → Zoho write-back
router.include_router(submit_request_router, tags=["requests"])

# Health check
router.include_router(health_router, tags=["health"])

# Debug endpoints (for Zoho format analysis) - محسّن للبيانات الضخمة
router.include_router(debug_router, tags=["debug"])
