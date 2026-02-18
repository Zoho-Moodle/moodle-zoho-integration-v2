"""
BTEC Templates Sync Endpoint

POST /v1/btec/sync-templates - Sync all BTEC templates from Zoho to Moodle
POST /v1/btec/sync-template/{unit_id} - Sync single template
GET /v1/btec/templates - List all templates from Zoho
"""

from fastapi import APIRouter, Depends, HTTPException, Query
from sqlalchemy.orm import Session
from typing import Optional, List
from pydantic import BaseModel, Field
import logging

from app.infra.db.session import get_db
from app.infra.zoho.config import create_zoho_client
from app.infra.moodle.users import MoodleClient
from app.services.btec_template_service import BtecTemplateService
from app.domain.btec_template import BtecTemplate
from app.core.config import settings

logger = logging.getLogger(__name__)

router = APIRouter(prefix="/btec", tags=["btec"])


# ============= Request/Response Models =============

class SyncTemplatesRequest(BaseModel):
    """Request to sync templates"""
    only_ready: bool = Field(
        default=True,
        description="Only sync templates with status 'Ready for use'"
    )
    force: bool = Field(
        default=False,
        description="Force sync even if already processed"
    )


class TemplateInfo(BaseModel):
    """Template summary info"""
    zoho_unit_id: str
    unit_name: str
    pass_count: int
    merit_count: int
    distinction_count: int
    total_criteria: int
    status: Optional[str] = None


class SyncResult(BaseModel):
    """Result of single template sync"""
    success: bool
    status: str
    message: str
    unit_id: str
    unit_name: Optional[str] = None
    definition_id: Optional[int] = None
    criteria_count: Optional[int] = None


class SyncSummary(BaseModel):
    """Summary of bulk sync operation"""
    total: int
    success: int
    failed: int
    skipped: int
    details: List[SyncResult]


# ============= Dependency: Get Services =============

async def get_btec_service(db: Session = Depends(get_db)) -> BtecTemplateService:
    """Dependency to get BtecTemplateService instance."""
    # Use factory function to create properly configured ZohoClient
    zoho = create_zoho_client(
        client_id=settings.ZOHO_CLIENT_ID,
        client_secret=settings.ZOHO_CLIENT_SECRET,
        refresh_token=settings.ZOHO_REFRESH_TOKEN
    )
    
    moodle = None
    if settings.MOODLE_ENABLED and settings.MOODLE_BASE_URL and settings.MOODLE_TOKEN:
        moodle = MoodleClient(
            base_url=settings.MOODLE_BASE_URL,
            token=settings.MOODLE_TOKEN
        )
    
    return BtecTemplateService(zoho, moodle, db)


# ============= Endpoints =============

@router.get("/templates", response_model=List[TemplateInfo])
async def list_templates(
    only_ready: bool = Query(True, description="Only templates with status 'Ready for use'"),
    service: BtecTemplateService = Depends(get_btec_service)
):
    """
    List all BTEC templates from Zoho.
    
    Returns summary information about each template:
    - Unit name
    - Criteria counts (Pass/Merit/Distinction)
    - Status
    
    Does NOT create anything in Moodle - just fetches from Zoho.
    """
    try:
        logger.info(f"Fetching templates list (only_ready={only_ready})")
        
        templates = await service.fetch_all_templates_from_zoho(only_ready=only_ready)
        
        result = [
            TemplateInfo(
                zoho_unit_id=t.zoho_unit_id,
                unit_name=t.unit_name,
                pass_count=len(t.pass_criteria),
                merit_count=len(t.merit_criteria),
                distinction_count=len(t.distinction_criteria),
                total_criteria=t.total_criteria_count,
                status=t.status
            )
            for t in templates
        ]
        
        logger.info(f"Found {len(result)} templates")
        return result
        
    except Exception as e:
        logger.error(f"Error listing templates: {e}")
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/sync-template/{unit_id}", response_model=SyncResult)
async def sync_single_template(
    unit_id: str,
    force: bool = Query(False, description="Force sync even if already processed"),
    service: BtecTemplateService = Depends(get_btec_service)
):
    """
    Sync single BTEC template from Zoho to Moodle.
    
    Steps:
    1. Fetch template from Zoho BTEC module
    2. Parse criteria (P1-P20, M1-M8, D1-D6)
    3. Create grading definition in Moodle
    4. Return result
    
    Requires Moodle to be configured (MOODLE_ENABLED=true).
    """
    if not service.moodle:
        raise HTTPException(
            status_code=400,
            detail="Moodle integration not configured. Set MOODLE_ENABLED=true and provide credentials."
        )
    
    try:
        logger.info(f"Syncing single template: {unit_id}")
        
        result = await service.sync_template(unit_id, force=force)
        
        return SyncResult(**result)
        
    except Exception as e:
        logger.error(f"Error syncing template {unit_id}: {e}")
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/sync-templates", response_model=SyncSummary)
async def sync_all_templates(
    only_ready: bool = Query(True, description="Only sync templates with status 'Ready for use'"),
    force: bool = Query(False, description="Force sync even if already processed"),
    service: BtecTemplateService = Depends(get_btec_service)
):
    """
    Sync all BTEC templates from Zoho to Moodle.
    
    This is a bulk operation that:
    1. Fetches all templates from Zoho BTEC module
    2. Filters based on status (if only_ready=true)
    3. Creates grading definitions in Moodle for each
    4. Returns summary with success/failed counts
    
    Requires Moodle to be configured (MOODLE_ENABLED=true).
    
    **Parameters:**
    - `only_ready`: Only sync templates with status "Ready for use" (default: true)
    - `force`: Force sync even if already processed (default: false)
    
    **Response:**
    - `total`: Total number of templates found
    - `success`: Number successfully synced
    - `failed`: Number that failed
    - `skipped`: Number skipped (already processed)
    - `details`: Array of individual results
    """
    print("=" * 80)
    print("🎯 BTEC SYNC ENDPOINT CALLED")
    print(f"Parameters: only_ready={only_ready}, force={force}")
    print(f"Service instance: {service}")
    print(f"Moodle client: {service.moodle}")
    print("=" * 80)
    
    logger.info("=" * 80)
    logger.info("🎯 BTEC SYNC ENDPOINT CALLED")
    logger.info(f"Parameters: only_ready={only_ready}, force={force}")
    logger.info(f"Service instance: {service}")
    logger.info(f"Moodle client: {service.moodle}")
    logger.info("=" * 80)
    
    if not service.moodle:
        logger.error("❌ Moodle client is None - cannot proceed")
        raise HTTPException(
            status_code=400,
            detail="Moodle integration not configured. Set MOODLE_ENABLED=true and provide credentials."
        )
    
    try:
        logger.info("📞 Calling service.sync_all_templates()...")
        
        results = await service.sync_all_templates(
            only_ready=only_ready,
            force=force
        )
        
        logger.info(f"✅ Service returned results: {results}")
        
        # Convert details to SyncResult models
        details = [SyncResult(**d) for d in results['details']]
        
        summary = SyncSummary(
            total=results['total'],
            success=results['success'],
            failed=results['failed'],
            skipped=results['skipped'],
            details=details
        )
        
        logger.info(f"📤 Returning summary: total={summary.total}, success={summary.success}")
        return summary
        
    except Exception as e:
        logger.error(f"❌ Error in bulk template sync: {e}")
        raise HTTPException(status_code=500, detail=str(e))
