"""
Sync Registrations Endpoint

POST /v1/sync/registrations - Sync BTEC_Registrations from Zoho
"""

from fastapi import APIRouter, Depends, Request, HTTPException
from sqlalchemy.orm import Session
import logging

from app.infra.db.session import get_db
from app.ingress.zoho.registration_ingress import ingest_registrations
from app.core.idempotency import idempotency_store
from app.core.config import settings

logger = logging.getLogger(__name__)

router = APIRouter(prefix="/sync")


@router.post("/registrations", summary="Sync Registrations (BTEC) from Zoho")
async def sync_registrations(request: Request, db: Session = Depends(get_db)):
    """
    Receives Zoho webhook payload and syncs registrations to database.
    
    Registers Student ↔ Program relationships.
    
    Supports both JSON and form-data payloads.
    Includes idempotency check to prevent duplicate processing.
    
    X-Tenant-ID header can be provided to override default tenant.
    """
    
    try:
        # Get tenant ID from header or use default
        tenant_id = request.headers.get("X-Tenant-ID", settings.DEFAULT_TENANT_ID)
        
        # Parse payload based on content type
        content_type = request.headers.get("content-type", "").lower()
        
        if "application/json" in content_type:
            payload = await request.json()
        else:
            # form-data
            form = await request.form()
            payload = dict(form)
        
        if not payload:
            raise HTTPException(status_code=400, detail="Empty payload")
        
        # Idempotency check
        idem_key = idempotency_store.generate_key(payload)
        
        if idempotency_store.is_duplicate(idem_key):
            logger.info(f"Duplicate registration request detected: {idem_key}")
            return {
                "status": "ignored",
                "reason": "duplicate_request",
                "idempotency_key": idem_key
            }
        
        # Mark as processed
        idempotency_store.mark_processed(idem_key)
        
        # Process registrations
        logger.info(f"Processing registration sync request: {idem_key}")
        results = ingest_registrations(payload, db, tenant_id)
        
        return {
            "status": "success",
            "tenant_id": tenant_id,
            "idempotency_key": idem_key,
            "results": results
        }
        
    except HTTPException as e:
        logger.error(f"HTTP error in sync_registrations: {e.detail}")
        raise
    except Exception as e:
        logger.exception(f"Unexpected error in sync_registrations: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Internal server error: {str(e)}")
