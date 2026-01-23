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

# Health check
router.include_router(health_router, tags=["health"])

# Debug endpoints (for Zoho format analysis) - محسّن للبيانات الضخمة
router.include_router(debug_router, tags=["debug"])
