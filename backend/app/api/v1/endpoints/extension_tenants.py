"""
Extension API Endpoints - Tenant Management
"""

from typing import List
from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session
from pydantic import BaseModel
from app.core.auth_extension import ExtensionAuth
from app.infra.db.session import get_db
from app.services.extension_service import ExtensionService


router = APIRouter(prefix="/extension", tags=["Extension - Tenants"])


# ===== Schemas =====

class TenantCreate(BaseModel):
    tenant_id: str
    name: str
    status: str = "active"


class TenantResponse(BaseModel):
    tenant_id: str
    name: str
    status: str
    created_at: str
    updated_at: str
    
    class Config:
        from_attributes = True


# ===== Endpoints =====

@router.get("/tenants", response_model=List[TenantResponse])
async def get_tenants(
    auth: dict = ExtensionAuth,
    db: Session = Depends(get_db)
):
    """Get all tenants"""
    service = ExtensionService(db)
    tenants = service.get_tenants()
    return [
        TenantResponse(
            tenant_id=t.tenant_id,
            name=t.name,
            status=t.status,
            created_at=t.created_at.isoformat(),
            updated_at=t.updated_at.isoformat()
        )
        for t in tenants
    ]


@router.post("/tenants", response_model=TenantResponse, status_code=201)
async def create_tenant(
    tenant: TenantCreate,
    auth: dict = ExtensionAuth,
    db: Session = Depends(get_db)
):
    """Create new tenant"""
    service = ExtensionService(db)
    
    try:
        new_tenant = service.create_tenant(
            tenant_id=tenant.tenant_id,
            name=tenant.name,
            status=tenant.status
        )
        return TenantResponse(
            tenant_id=new_tenant.tenant_id,
            name=new_tenant.name,
            status=new_tenant.status,
            created_at=new_tenant.created_at.isoformat(),
            updated_at=new_tenant.updated_at.isoformat()
        )
    except Exception as e:
        raise HTTPException(status_code=400, detail=str(e))
