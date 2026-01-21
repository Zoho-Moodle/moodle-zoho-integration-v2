"""
Enrollments Sync Endpoint

POST /v1/sync/enrollments

Accepts Zoho Enrollments (BTEC_Enrollments) payload and syncs to database.
Returns per-record status (NEW, UNCHANGED, UPDATED, INVALID, SKIPPED).
SKIPPED: When student or class not synced yet (dependency error).
Idempotency: Requests with same body within 1 hour return cached result.
"""

import logging
from typing import List, Dict, Any, Optional
from fastapi import APIRouter, HTTPException, Depends, Header
from sqlalchemy.orm import Session
from pydantic import BaseModel

from app.core.idempotency import InMemoryIdempotencyStore, compute_request_hash
from app.infra.db.session import get_db
from app.ingress.zoho.enrollment_ingress import ingest_enrollments
from app.core.config import settings

logger = logging.getLogger(__name__)

router = APIRouter(
    prefix="/sync",
    tags=["sync"]
)

# Idempotency store (in-memory, 1-hour TTL)
idempotency_store = InMemoryIdempotencyStore(ttl_seconds=3600)


class EnrollmentSyncRequest(BaseModel):
    """Request body for enrollment sync"""
    data: List[Dict[str, Any]]


class EnrollmentSyncResponse(BaseModel):
    """Response for enrollment sync"""
    status: str
    idempotency_key: str
    results: List[Dict[str, Any]]


@router.post("/enrollments", response_model=EnrollmentSyncResponse)
def sync_enrollments(
    request: EnrollmentSyncRequest,
    db: Session = Depends(get_db),
    x_tenant_id: Optional[str] = Header(None),
) -> EnrollmentSyncResponse:
    """
    Sync enrollments from Zoho
    
    Args:
        request: {"data": [...]} payload
        db: Database session
        x_tenant_id: Optional tenant ID (defaults to DEFAULT_TENANT_ID)
        
    Returns:
        {
            "status": "success",
            "idempotency_key": "...",
            "results": [
                {
                    "zoho_enrollment_id": "...",
                    "status": "NEW|UNCHANGED|UPDATED|INVALID|SKIPPED",
                    "message": "...",
                    "reason": "student_not_synced_yet|class_not_synced_yet",  # if SKIPPED
                    "changes": {...}  # if UPDATED
                }
            ]
        }
    """
    
    try:
        # Determine tenant
        tenant_id = x_tenant_id or settings.DEFAULT_TENANT_ID or "default"
        
        # Compute request hash for idempotency
        req_hash = compute_request_hash(request.dict())
        
        # Check idempotency store
        cached = idempotency_store.get(req_hash)
        if cached:
            logger.info(f"Enrollments sync: Returning cached result (idempotency key: {req_hash})")
            return EnrollmentSyncResponse(
                status="success",
                idempotency_key=req_hash,
                results=cached
            )
        
        # Ingest enrollments
        logger.info(f"Enrollments sync: Processing {len(request.data)} records (tenant: {tenant_id})")
        results = ingest_enrollments({"data": request.data}, db, tenant_id)
        
        # Log skipped enrollments
        skipped = [r for r in results if r.get("status") == "SKIPPED"]
        if skipped:
            logger.warning(f"Enrollments sync: {len(skipped)} enrollments skipped (missing dependencies)")
        
        # Store in idempotency cache
        idempotency_store.set(req_hash, results)
        
        return EnrollmentSyncResponse(
            status="success",
            idempotency_key=req_hash,
            results=results
        )
    
    except Exception as e:
        logger.error(f"Enrollments sync error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))
