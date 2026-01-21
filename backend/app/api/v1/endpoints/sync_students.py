from fastapi import APIRouter, Depends, Request, HTTPException
from sqlalchemy.orm import Session
import logging

from app.infra.db.session import get_db
from app.ingress.zoho.student_ingress import ingest_students
from app.core.idempotency import idempotency_store

logger = logging.getLogger(__name__)

router = APIRouter(prefix="/sync")


@router.post("/students", summary="Sync Students from Zoho")
async def sync_students(request: Request, db: Session = Depends(get_db)):
    """
    Receives Zoho webhook payload and syncs students to database.
    
    Supports both JSON and form-data payloads.
    Includes idempotency check to prevent duplicate processing.
    """
    
    try:
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
            logger.info(f"Duplicate request detected: {idem_key}")
            return {
                "status": "ignored",
                "reason": "duplicate_request",
                "idempotency_key": idem_key
            }
        
        # Mark as processed
        idempotency_store.mark_processed(idem_key)
        
        # Process students
        logger.info(f"Processing student sync request: {idem_key}")
        results = ingest_students(payload, db)
        
        return {
            "status": "success",
            "idempotency_key": idem_key,
            "results": results
        }
        
    except HTTPException as e:
        logger.error(f"HTTP error in sync_students: {e.detail}")
        raise
    except Exception as e:
        logger.exception(f"Unexpected error in sync_students: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Internal server error: {str(e)}")
