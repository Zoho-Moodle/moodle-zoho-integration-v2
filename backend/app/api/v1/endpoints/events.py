"""
Event Router Endpoints

Webhook endpoints for receiving events from Zoho CRM and Moodle.
"""

from fastapi import APIRouter, Request, HTTPException, BackgroundTasks, Depends, Header
from sqlalchemy.orm import Session
from typing import Optional
import logging
import json
import time
from datetime import datetime

from app.domain.events import (
    ZohoWebhookEvent, MoodleWebhookEvent,
    WebhookResponse, EventProcessingResult
)
from app.services.event_handler_service import EventHandlerService
from app.core.security import verify_webhook_signature
from app.infra.zoho import create_zoho_client
from app.infra.db.session import get_db
from app.core.config import settings

logger = logging.getLogger(__name__)
router = APIRouter(prefix="/events", tags=["webhooks"])


# ============================================================================
# Dependency: Get Event Handler Service
# ============================================================================

def get_event_handler(db: Session = Depends(get_db)) -> EventHandlerService:
    """
    Create EventHandlerService dependency.
    
    Args:
        db: Database session
        
    Returns:
        EventHandlerService instance
    """
    zoho_client = create_zoho_client()
    
    return EventHandlerService(
        db=db,
        zoho_client=zoho_client
    )


# ============================================================================
# Zoho Webhook Endpoints
# ============================================================================

@router.post("/zoho/student", response_model=WebhookResponse)
async def handle_zoho_student_event(
    request: Request,
    background_tasks: BackgroundTasks,
    db: Session = Depends(get_db),
    x_zoho_signature: Optional[str] = Header(None, alias="X-Zoho-Signature")
):
    """
    Handle Zoho BTEC_Students webhook events.
    
    Triggered when:
    - Student record is created
    - Student record is updated
    - Student record is deleted
    
    Security: Verifies HMAC signature from Zoho
    Processing: Async background task to avoid blocking webhook response
    """
    try:
        # Get raw body for HMAC verification
        body = await request.body()
        body_str = body.decode('utf-8')
        
        # Verify HMAC signature (if configured)
        if hasattr(settings, 'ZOHO_WEBHOOK_SECRET') and settings.ZOHO_WEBHOOK_SECRET:
            if not x_zoho_signature:
                logger.warning("Missing X-Zoho-Signature header")
                raise HTTPException(status_code=401, detail="Missing signature")
            
            is_valid = verify_webhook_signature(
                source="zoho",
                payload=body_str,
                signature_header=x_zoho_signature,
                secret=settings.ZOHO_WEBHOOK_SECRET
            )
            
            if not is_valid:
                logger.warning("Invalid Zoho webhook signature")
                raise HTTPException(status_code=401, detail="Invalid signature")
        
        # Parse payload - handle both JSON and form-data
        try:
            payload = json.loads(body_str)
            logger.info(f"Received JSON webhook: {payload.get('record_id', 'NO_ID')}")
        except json.JSONDecodeError:
            # Zoho sends form-data in Default mode
            logger.info(f"Received form-data webhook, raw body: {body_str[:500]}")
            from urllib.parse import parse_qs
            parsed_data = parse_qs(body_str)
            logger.info(f"Parsed data keys: {list(parsed_data.keys())}")
            
            # Extract Zoho webhook data from form fields
            # Zoho sends data as individual fields in Default format
            payload = {
                "notification_id": parsed_data.get('id', [''])[0] or f"zoho_{int(time.time() * 1000)}",
                "timestamp": parsed_data.get('Modified_Time', [datetime.utcnow().isoformat()])[0],
                "module": "BTEC_Students",
                "operation": "update",  # Default assumes update
                "record_id": parsed_data.get('id', [''])[0],
                "data": {key: value[0] for key, value in parsed_data.items()}
            }
            logger.info(f"Parsed form-data: record_id={payload['record_id']}, all_data={payload['data']}")
        
        # Create Zoho event
        event = ZohoWebhookEvent.from_zoho_webhook(payload)
        event.module = "BTEC_Students"  # Ensure correct module
        
        logger.info(f"Received Zoho student event: {event.event_id}")
        
        # Process in background
        background_tasks.add_task(
            process_zoho_event_task,
            event=event,
            db=db
        )
        
        return WebhookResponse(
            success=True,
            message="Event accepted for processing",
            event_id=event.event_id,
            status="queued"
        )
        
    except json.JSONDecodeError as e:
        logger.error(f"Invalid JSON in webhook: {e}")
        raise HTTPException(status_code=400, detail="Invalid JSON payload")
    except Exception as e:
        logger.error(f"Error handling Zoho student webhook: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/zoho/enrollment", response_model=WebhookResponse)
async def handle_zoho_enrollment_event(
    request: Request,
    background_tasks: BackgroundTasks,
    db: Session = Depends(get_db),
    x_zoho_signature: Optional[str] = Header(None, alias="X-Zoho-Signature")
):
    """
    Handle Zoho BTEC_Enrollments webhook events.
    
    Triggered when:
    - Enrollment record is created
    - Enrollment record is updated (status change, etc.)
    - Enrollment record is deleted
    """
    try:
        body = await request.body()
        body_str = body.decode('utf-8')
        
        # Verify signature
        if hasattr(settings, 'ZOHO_WEBHOOK_SECRET') and settings.ZOHO_WEBHOOK_SECRET:
            if not x_zoho_signature:
                raise HTTPException(status_code=401, detail="Missing signature")
            
            if not verify_webhook_signature("zoho", body_str, x_zoho_signature, settings.ZOHO_WEBHOOK_SECRET):
                raise HTTPException(status_code=401, detail="Invalid signature")
        
        payload = json.loads(body_str)
        event = ZohoWebhookEvent.from_zoho_webhook(payload)
        event.module = "BTEC_Enrollments"
        
        logger.info(f"Received Zoho enrollment event: {event.event_id}")
        
        background_tasks.add_task(process_zoho_event_task, event=event, db=db)
        
        return WebhookResponse(
            success=True,
            message="Event accepted for processing",
            event_id=event.event_id,
            status="queued"
        )
        
    except json.JSONDecodeError:
        raise HTTPException(status_code=400, detail="Invalid JSON payload")
    except Exception as e:
        logger.error(f"Error handling Zoho enrollment webhook: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/zoho/grade", response_model=WebhookResponse)
async def handle_zoho_grade_event(
    request: Request,
    background_tasks: BackgroundTasks,
    db: Session = Depends(get_db),
    x_zoho_signature: Optional[str] = Header(None, alias="X-Zoho-Signature")
):
    """
    Handle Zoho BTEC_Grades webhook events.
    
    Triggered when:
    - Grade record is created
    - Grade record is updated
    - Learning outcomes assessment is updated
    """
    try:
        body = await request.body()
        body_str = body.decode('utf-8')
        
        # Verify signature
        if hasattr(settings, 'ZOHO_WEBHOOK_SECRET') and settings.ZOHO_WEBHOOK_SECRET:
            if not x_zoho_signature:
                raise HTTPException(status_code=401, detail="Missing signature")
            
            if not verify_webhook_signature("zoho", body_str, x_zoho_signature, settings.ZOHO_WEBHOOK_SECRET):
                raise HTTPException(status_code=401, detail="Invalid signature")
        
        payload = json.loads(body_str)
        event = ZohoWebhookEvent.from_zoho_webhook(payload)
        event.module = "BTEC_Grades"
        
        logger.info(f"Received Zoho grade event: {event.event_id}")
        
        background_tasks.add_task(process_zoho_event_task, event=event, db=db)
        
        return WebhookResponse(
            success=True,
            message="Event accepted for processing",
            event_id=event.event_id,
            status="queued"
        )
        
    except json.JSONDecodeError:
        raise HTTPException(status_code=400, detail="Invalid JSON payload")
    except Exception as e:
        logger.error(f"Error handling Zoho grade webhook: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/zoho/payment", response_model=WebhookResponse)
async def handle_zoho_payment_event(
    request: Request,
    background_tasks: BackgroundTasks,
    db: Session = Depends(get_db),
    x_zoho_signature: Optional[str] = Header(None, alias="X-Zoho-Signature")
):
    """
    Handle Zoho BTEC_Payments webhook events.
    
    Note: Payments are read-only from Moodle's perspective.
    This webhook is primarily for logging and cache invalidation.
    """
    try:
        body = await request.body()
        body_str = body.decode('utf-8')
        
        # Verify signature
        if hasattr(settings, 'ZOHO_WEBHOOK_SECRET') and settings.ZOHO_WEBHOOK_SECRET:
            if not x_zoho_signature:
                raise HTTPException(status_code=401, detail="Missing signature")
            
            if not verify_webhook_signature("zoho", body_str, x_zoho_signature, settings.ZOHO_WEBHOOK_SECRET):
                raise HTTPException(status_code=401, detail="Invalid signature")
        
        payload = json.loads(body_str)
        event = ZohoWebhookEvent.from_zoho_webhook(payload)
        event.module = "BTEC_Payments"
        
        logger.info(f"Received Zoho payment event: {event.event_id}")
        
        background_tasks.add_task(process_zoho_event_task, event=event, db=db)
        
        return WebhookResponse(
            success=True,
            message="Event accepted for processing",
            event_id=event.event_id,
            status="queued"
        )
        
    except json.JSONDecodeError:
        raise HTTPException(status_code=400, detail="Invalid JSON payload")
    except Exception as e:
        logger.error(f"Error handling Zoho payment webhook: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))


# ============================================================================
# Moodle Webhook Endpoints
# ============================================================================

@router.post("/moodle/enrollment", response_model=WebhookResponse)
async def handle_moodle_enrollment_event(
    request: Request,
    background_tasks: BackgroundTasks,
    db: Session = Depends(get_db),
    x_moodle_signature: Optional[str] = Header(None, alias="X-Moodle-Signature")
):
    """
    Handle Moodle enrollment events.
    
    Triggered when:
    - User enrolls in a course
    - User unenrolls from a course
    - Enrollment status changes
    
    This allows Zoho to be updated when students enroll via Moodle.
    """
    try:
        body = await request.body()
        body_str = body.decode('utf-8')
        
        # Verify signature
        if hasattr(settings, 'MOODLE_WEBHOOK_SECRET') and settings.MOODLE_WEBHOOK_SECRET:
            if not x_moodle_signature:
                raise HTTPException(status_code=401, detail="Missing signature")
            
            if not verify_webhook_signature("moodle", body_str, x_moodle_signature, settings.MOODLE_WEBHOOK_SECRET):
                raise HTTPException(status_code=401, detail="Invalid signature")
        
        payload = json.loads(body_str)
        event = MoodleWebhookEvent.from_moodle_webhook(payload)
        
        logger.info(f"Received Moodle enrollment event: {event.event_id}")
        
        background_tasks.add_task(process_moodle_event_task, event=event, db=db)
        
        return WebhookResponse(
            success=True,
            message="Event accepted for processing",
            event_id=event.event_id,
            status="queued"
        )
        
    except json.JSONDecodeError:
        raise HTTPException(status_code=400, detail="Invalid JSON payload")
    except Exception as e:
        logger.error(f"Error handling Moodle enrollment webhook: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))


# ============================================================================
# Background Task Processing Functions
# ============================================================================

async def process_zoho_event_task(event: ZohoWebhookEvent, db: Session):
    """
    Background task to process Zoho event.
    
    Args:
        event: ZohoWebhookEvent instance
        db: Database session
    """
    try:
        logger.info(f"Processing Zoho event in background: {event.event_id}")
        
        # Create event handler (not async)
        zoho_client = create_zoho_client()
        event_handler = EventHandlerService(db=db, zoho_client=zoho_client)
        
        # Process event
        result: EventProcessingResult = await event_handler.handle_zoho_event(event)
        
        logger.info(
            f"Zoho event processed: {event.event_id}, "
            f"status={result.status}, action={result.action_taken}"
        )
        
    except Exception as e:
        logger.error(f"Error in Zoho event background task: {e}", exc_info=True)


async def process_moodle_event_task(event: MoodleWebhookEvent, db: Session):
    """
    Background task to process Moodle event.
    
    Args:
        event: MoodleWebhookEvent instance
        db: Database session
    """
    try:
        logger.info(f"Processing Moodle event in background: {event.event_id}")
        
        # Create event handler (not async)
        zoho_client = create_zoho_client()
        event_handler = EventHandlerService(db=db, zoho_client=zoho_client)
        
        # Process event
        result: EventProcessingResult = await event_handler.handle_moodle_event(event)
        
        logger.info(
            f"Moodle event processed: {event.event_id}, "
            f"status={result.status}, action={result.action_taken}"
        )
        
    except Exception as e:
        logger.error(f"Error in Moodle event background task: {e}", exc_info=True)


# ============================================================================
# Health Check & Status Endpoints
# ============================================================================

@router.get("/health")
async def event_router_health():
    """
    Health check for event router.
    
    Returns:
        Health status
    """
    return {
        "status": "healthy",
        "service": "event-router",
        "endpoints": {
            "zoho": [
                "/events/zoho/student",
                "/events/zoho/enrollment",
                "/events/zoho/grade",
                "/events/zoho/payment"
            ],
            "moodle": [
                "/events/moodle/enrollment"
            ]
        }
    }


@router.get("/stats")
async def event_stats(db: Session = Depends(get_db)):
    """
    Get event processing statistics.
    
    Returns:
        Event processing stats
    """
    from app.infra.db.models.event_log import EventLog
    from sqlalchemy import func
    
    try:
        # Total events
        total = db.query(func.count(EventLog.id)).scalar()
        
        # Events by status
        by_status = db.query(
            EventLog.status,
            func.count(EventLog.id)
        ).group_by(EventLog.status).all()
        
        # Events by source
        by_source = db.query(
            EventLog.source,
            func.count(EventLog.id)
        ).group_by(EventLog.source).all()
        
        # Recent events (last 10)
        recent = db.query(EventLog).order_by(
            EventLog.created_at.desc()
        ).limit(10).all()
        
        return {
            "total_events": total,
            "by_status": {status: count for status, count in by_status},
            "by_source": {source: count for source, count in by_source},
            "recent_events": [
                {
                    "event_id": e.event_id,
                    "source": e.source,
                    "module": e.module,
                    "status": e.status,
                    "created_at": e.created_at.isoformat()
                }
                for e in recent
            ]
        }
        
    except Exception as e:
        logger.error(f"Error getting event stats: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))
