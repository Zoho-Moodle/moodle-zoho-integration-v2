"""
Programs Sync Endpoint

POST /v1/sync/programs

Accepts Zoho Programs (Products) payload and syncs to database.
Returns per-record status (NEW, UNCHANGED, UPDATED, INVALID).
Idempotency: Requests with same body within 1 hour return cached result.
"""

import logging
from typing import List, Dict, Any, Optional
from fastapi import APIRouter, HTTPException, Depends, Header
from sqlalchemy.orm import Session
from pydantic import BaseModel

from app.core.idempotency import InMemoryIdempotencyStore, compute_request_hash
from app.infra.db.session import get_db
from app.ingress.zoho.program_ingress import ingest_programs
from app.core.config import settings

logger = logging.getLogger(__name__)

router = APIRouter(
    prefix="/sync",
    tags=["sync"]
)

# Idempotency store (in-memory, 1-hour TTL)
idempotency_store = InMemoryIdempotencyStore(ttl_seconds=3600)


class ProgramSyncRequest(BaseModel):
    """Request body for program sync"""
    data: List[Dict[str, Any]]


class ProgramSyncResponse(BaseModel):
    """Response for program sync"""
    status: str
    idempotency_key: str
    results: List[Dict[str, Any]]


@router.post("/programs", response_model=ProgramSyncResponse)
def sync_programs(
    request: ProgramSyncRequest,
    db: Session = Depends(get_db),
    x_tenant_id: Optional[str] = Header(None),
) -> ProgramSyncResponse:
    """
    Sync programs from Zoho
    
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
                    "zoho_program_id": "...",
                    "status": "NEW|UNCHANGED|UPDATED|INVALID",
                    "message": "...",
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
            logger.info(f"Programs sync: Returning cached result (idempotency key: {req_hash})")
            return ProgramSyncResponse(
                status="success",
                idempotency_key=req_hash,
                results=cached
            )
        
        # Ingest programs
        logger.info(f"Programs sync: Processing {len(request.data)} records (tenant: {tenant_id})")
        results = ingest_programs({"data": request.data}, db, tenant_id)
        
        # Store in idempotency cache
        idempotency_store.set(req_hash, results)
        
        return ProgramSyncResponse(
            status="success",
            idempotency_key=req_hash,
            results=results
        )
    
    except Exception as e:
        logger.error(f"Programs sync error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))
