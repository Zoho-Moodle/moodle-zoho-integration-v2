from fastapi import APIRouter
from app.api.v1.endpoints.sync_students import router as sync_students_router
from app.api.v1.endpoints.sync_programs import router as sync_programs_router
from app.api.v1.endpoints.sync_classes import router as sync_classes_router
from app.api.v1.endpoints.sync_enrollments import router as sync_enrollments_router
from app.api.v1.endpoints.health import router as health_router
from app.api.v1.endpoints.debug import router as debug_router

router = APIRouter()

# Sync endpoints
router.include_router(sync_students_router, tags=["sync"])
router.include_router(sync_programs_router, tags=["sync"])
router.include_router(sync_classes_router, tags=["sync"])
router.include_router(sync_enrollments_router, tags=["sync"])

# Health check
router.include_router(health_router, tags=["health"])

# Debug endpoints (for Zoho format analysis)
router.include_router(debug_router, tags=["debug"])
