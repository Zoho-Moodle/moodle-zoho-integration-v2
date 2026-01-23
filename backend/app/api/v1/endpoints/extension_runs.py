"""
Extension API Endpoints - Sync Runs & Monitoring
"""

from typing import List, Optional
from fastapi import APIRouter, Depends, HTTPException, Query
from sqlalchemy.orm import Session
from pydantic import BaseModel
from app.core.auth_extension import ExtensionAuth
from app.infra.db.session import get_db
from app.services.extension_service import ExtensionService


router = APIRouter(prefix="/extension", tags=["Extension - Sync Runs"])


# ===== Schemas =====

class SyncRunTrigger(BaseModel):
    triggered_by: Optional[str] = None


class SyncRunItemResponse(BaseModel):
    zoho_id: str
    status: str
    message: Optional[str]
    diff: dict
    created_at: str


class SyncRunResponse(BaseModel):
    run_id: str
    module_name: str
    trigger_source: str
    triggered_by: Optional[str]
    started_at: str
    finished_at: Optional[str]
    status: str
    counts: dict
    error_summary: Optional[str]


class SyncRunDetailResponse(SyncRunResponse):
    items: List[SyncRunItemResponse]


# ===== Endpoints =====

@router.post("/sync/{module_name}/run", response_model=SyncRunResponse)
async def trigger_manual_sync(
    module_name: str,
    trigger: SyncRunTrigger,
    auth: dict = ExtensionAuth,
    db: Session = Depends(get_db)
):
    """Manually trigger sync for a module"""
    service = ExtensionService(db)
    tenant_id = auth["tenant_id"]
    
    # Block grades
    if module_name == "grades":
        raise HTTPException(
            status_code=400,
            detail="Grades sync direction is Moodle -> Zoho (not implemented in this phase). Manual sync not available."
        )
    
    # Check if module exists and is enabled
    module_settings = service.get_module_settings(tenant_id, module_name)
    if not module_settings:
        raise HTTPException(status_code=404, detail=f"Module '{module_name}' not configured")
    
    module_config = module_settings[0]
    if not module_config.enabled:
        raise HTTPException(status_code=400, detail=f"Module '{module_name}' is disabled")
    
    # Create sync run
    run = service.create_sync_run(
        tenant_id=tenant_id,
        module_name=module_name,
        trigger_source="manual",
        triggered_by=trigger.triggered_by or "extension_user"
    )
    
    # TODO: Integrate with existing sync services
    # For MVP, return the created run (actual sync would happen async)
    
    return SyncRunResponse(
        run_id=run.run_id,
        module_name=run.module_name,
        trigger_source=run.trigger_source,
        triggered_by=run.triggered_by,
        started_at=run.started_at.isoformat(),
        finished_at=run.finished_at.isoformat() if run.finished_at else None,
        status=run.status,
        counts=run.counts_json,
        error_summary=run.error_summary
    )


@router.get("/runs", response_model=List[SyncRunResponse])
async def get_sync_runs(
    module: Optional[str] = Query(None),
    limit: int = Query(50, le=200),
    auth: dict = ExtensionAuth,
    db: Session = Depends(get_db)
):
    """Get sync run history"""
    service = ExtensionService(db)
    tenant_id = auth["tenant_id"]
    
    runs = service.get_sync_runs(tenant_id, module, limit)
    
    return [
        SyncRunResponse(
            run_id=r.run_id,
            module_name=r.module_name,
            trigger_source=r.trigger_source,
            triggered_by=r.triggered_by,
            started_at=r.started_at.isoformat(),
            finished_at=r.finished_at.isoformat() if r.finished_at else None,
            status=r.status,
            counts=r.counts_json,
            error_summary=r.error_summary
        )
        for r in runs
    ]


@router.get("/runs/{run_id}", response_model=SyncRunDetailResponse)
async def get_sync_run_detail(
    run_id: str,
    auth: dict = ExtensionAuth,
    db: Session = Depends(get_db)
):
    """Get detailed sync run with individual item results"""
    service = ExtensionService(db)
    
    run = service.get_sync_run(run_id)
    if not run or run.tenant_id != auth["tenant_id"]:
        raise HTTPException(status_code=404, detail="Run not found")
    
    items = service.get_sync_run_items(run_id)
    
    return SyncRunDetailResponse(
        run_id=run.run_id,
        module_name=run.module_name,
        trigger_source=run.trigger_source,
        triggered_by=run.triggered_by,
        started_at=run.started_at.isoformat(),
        finished_at=run.finished_at.isoformat() if run.finished_at else None,
        status=run.status,
        counts=run.counts_json,
        error_summary=run.error_summary,
        items=[
            SyncRunItemResponse(
                zoho_id=item.zoho_id,
                status=item.status,
                message=item.message,
                diff=item.diff_json,
                created_at=item.created_at.isoformat()
            )
            for item in items
        ]
    )


@router.post("/runs/{run_id}/retry-failed", response_model=SyncRunResponse)
async def retry_failed_items(
    run_id: str,
    auth: dict = ExtensionAuth,
    db: Session = Depends(get_db)
):
    """Retry failed items from a sync run"""
    service = ExtensionService(db)
    
    run = service.get_sync_run(run_id)
    if not run or run.tenant_id != auth["tenant_id"]:
        raise HTTPException(status_code=404, detail="Run not found")
    
    failed_items = service.get_failed_items(run_id)
    
    if not failed_items:
        raise HTTPException(status_code=400, detail="No failed items to retry")
    
    # Create new run for retry
    retry_run = service.create_sync_run(
        tenant_id=run.tenant_id,
        module_name=run.module_name,
        trigger_source="retry",
        triggered_by=f"retry_of_{run_id}"
    )
    
    # TODO: Queue failed items for retry
    
    return SyncRunResponse(
        run_id=retry_run.run_id,
        module_name=retry_run.module_name,
        trigger_source=retry_run.trigger_source,
        triggered_by=retry_run.triggered_by,
        started_at=retry_run.started_at.isoformat(),
        finished_at=retry_run.finished_at.isoformat() if retry_run.finished_at else None,
        status=retry_run.status,
        counts=retry_run.counts_json,
        error_summary=retry_run.error_summary
    )
