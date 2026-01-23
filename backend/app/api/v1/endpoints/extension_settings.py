"""
Extension API Endpoints - Settings & Modules
"""

from typing import List, Optional
from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session
from pydantic import BaseModel
from app.core.auth_extension import ExtensionAuth
from app.infra.db.session import get_db
from app.services.extension_service import ExtensionService


router = APIRouter(prefix="/extension", tags=["Extension - Settings"])


# ===== Schemas =====

class IntegrationSettingsUpdate(BaseModel):
    moodle_enabled: Optional[bool] = None
    moodle_base_url: Optional[str] = None
    moodle_api_token: Optional[str] = None
    zoho_enabled: Optional[bool] = None
    zoho_api_domain: Optional[str] = None
    zoho_org_id: Optional[str] = None


class IntegrationSettingsResponse(BaseModel):
    tenant_id: str
    moodle_enabled: bool
    moodle_base_url: Optional[str]
    zoho_enabled: bool
    zoho_api_domain: Optional[str]
    zoho_org_id: Optional[str]
    extension_api_key: str
    
    class Config:
        from_attributes = True


class ModuleSettingsUpdate(BaseModel):
    enabled: Optional[bool] = None
    schedule_mode: Optional[str] = None  # manual, cron, webhook
    schedule_cron: Optional[str] = None


class ModuleSettingsResponse(BaseModel):
    module_name: str
    enabled: bool
    schedule_mode: str
    schedule_cron: Optional[str]
    last_run_at: Optional[str]
    last_run_status: Optional[str]
    last_run_count: int
    
    class Config:
        from_attributes = True


# ===== Endpoints =====

@router.get("/settings", response_model=IntegrationSettingsResponse)
async def get_settings(
    auth: dict = ExtensionAuth,
    db: Session = Depends(get_db)
):
    """Get integration settings for tenant"""
    service = ExtensionService(db)
    tenant_id = auth["tenant_id"]
    
    settings = service.get_integration_settings(tenant_id)
    if not settings:
        raise HTTPException(status_code=404, detail="Settings not found")
    
    return IntegrationSettingsResponse(
        tenant_id=settings.tenant_id,
        moodle_enabled=settings.moodle_enabled,
        moodle_base_url=settings.moodle_base_url,
        zoho_enabled=settings.zoho_enabled,
        zoho_api_domain=settings.zoho_api_domain,
        zoho_org_id=settings.zoho_org_id,
        extension_api_key=settings.extension_api_key
    )


@router.put("/settings", response_model=IntegrationSettingsResponse)
async def update_settings(
    updates: IntegrationSettingsUpdate,
    auth: dict = ExtensionAuth,
    db: Session = Depends(get_db)
):
    """Update integration settings"""
    service = ExtensionService(db)
    tenant_id = auth["tenant_id"]
    
    settings = service.update_integration_settings(
        tenant_id=tenant_id,
        updates=updates.model_dump(exclude_none=True)
    )
    
    return IntegrationSettingsResponse(
        tenant_id=settings.tenant_id,
        moodle_enabled=settings.moodle_enabled,
        moodle_base_url=settings.moodle_base_url,
        zoho_enabled=settings.zoho_enabled,
        zoho_api_domain=settings.zoho_api_domain,
        zoho_org_id=settings.zoho_org_id,
        extension_api_key=settings.extension_api_key
    )


@router.get("/modules", response_model=List[ModuleSettingsResponse])
async def get_modules(
    auth: dict = ExtensionAuth,
    db: Session = Depends(get_db)
):
    """Get all module settings"""
    service = ExtensionService(db)
    tenant_id = auth["tenant_id"]
    
    modules = service.get_module_settings(tenant_id)
    
    return [
        ModuleSettingsResponse(
            module_name=m.module_name,
            enabled=m.enabled,
            schedule_mode=m.schedule_mode,
            schedule_cron=m.schedule_cron,
            last_run_at=m.last_run_at.isoformat() if m.last_run_at else None,
            last_run_status=m.last_run_status,
            last_run_count=m.last_run_count
        )
        for m in modules
    ]


@router.put("/modules/{module_name}", response_model=ModuleSettingsResponse)
async def update_module(
    module_name: str,
    updates: ModuleSettingsUpdate,
    auth: dict = ExtensionAuth,
    db: Session = Depends(get_db)
):
    """Update module settings"""
    service = ExtensionService(db)
    tenant_id = auth["tenant_id"]
    
    # Block enabling grades module
    if module_name == "grades" and updates.enabled:
        raise HTTPException(
            status_code=400,
            detail="Grades sync direction is Moodle -> Zoho (not implemented in this phase). Cannot enable outbound sync."
        )
    
    settings = service.update_module_settings(
        tenant_id=tenant_id,
        module_name=module_name,
        updates=updates.model_dump(exclude_none=True)
    )
    
    return ModuleSettingsResponse(
        module_name=settings.module_name,
        enabled=settings.enabled,
        schedule_mode=settings.schedule_mode,
        schedule_cron=settings.schedule_cron,
        last_run_at=settings.last_run_at.isoformat() if settings.last_run_at else None,
        last_run_status=settings.last_run_status,
        last_run_count=settings.last_run_count
    )
