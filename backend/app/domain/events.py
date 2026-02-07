"""
Event Models and Schemas

Pydantic models for webhook events from Zoho and Moodle.
"""

from pydantic import BaseModel, Field
from typing import Optional, Dict, Any, List
from datetime import datetime
from enum import Enum


class EventSource(str, Enum):
    """Event source enum."""
    ZOHO = "zoho"
    MOODLE = "moodle"


class EventType(str, Enum):
    """Event type enum."""
    STUDENT_CREATED = "student.created"
    STUDENT_UPDATED = "student.updated"
    STUDENT_DELETED = "student.deleted"
    
    ENROLLMENT_CREATED = "enrollment.created"
    ENROLLMENT_UPDATED = "enrollment.updated"
    ENROLLMENT_DELETED = "enrollment.deleted"
    
    GRADE_CREATED = "grade.created"
    GRADE_UPDATED = "grade.updated"
    GRADE_DELETED = "grade.deleted"
    
    PAYMENT_CREATED = "payment.created"
    PAYMENT_UPDATED = "payment.updated"


class EventStatus(str, Enum):
    """Event processing status."""
    PENDING = "pending"
    PROCESSING = "processing"
    COMPLETED = "completed"
    FAILED = "failed"
    DUPLICATE = "duplicate"


# ============================================================================
# Zoho Webhook Events
# ============================================================================

class ZohoWebhookData(BaseModel):
    """Zoho webhook data payload."""
    module: str = Field(..., description="Zoho module name (e.g., BTEC_Students)")
    record_id: str = Field(..., alias="id", description="Zoho record ID")
    operation: str = Field(..., description="insert, update, or delete")
    data: Dict[str, Any] = Field(default_factory=dict, description="Record data")
    
    class Config:
        populate_by_name = True


class ZohoWebhookEvent(BaseModel):
    """
    Zoho webhook event.
    
    Zoho sends webhooks when records are created/updated/deleted.
    Format: https://www.zoho.com/crm/developer/docs/api/v2/notifications.html
    """
    event_id: str = Field(..., description="Unique event ID from Zoho")
    timestamp: datetime = Field(default_factory=datetime.utcnow, description="Event timestamp")
    module: str = Field(..., description="Zoho module name")
    operation: str = Field(..., description="insert, update, or delete")
    record_id: str = Field(..., description="Zoho record ID")
    record_data: Optional[Dict[str, Any]] = Field(None, description="Full record data")
    
    # Zoho-specific fields
    zoho_user_id: Optional[str] = Field(None, description="User who triggered the event")
    zoho_org_id: Optional[str] = Field(None, description="Zoho organization ID")
    
    @classmethod
    def from_zoho_webhook(cls, payload: Dict[str, Any]) -> "ZohoWebhookEvent":
        """
        Create event from Zoho webhook payload.
        
        Args:
            payload: Raw Zoho webhook JSON
            
        Returns:
            ZohoWebhookEvent instance
        """
        # Zoho webhook format varies, extract common fields
        return cls(
            event_id=payload.get("notification_id") or payload.get("id", ""),
            timestamp=datetime.fromisoformat(payload.get("timestamp", datetime.utcnow().isoformat())),
            module=payload.get("module", ""),
            operation=payload.get("operation", ""),
            record_id=payload.get("record_id") or payload.get("id", ""),
            record_data=payload.get("data"),
            zoho_user_id=payload.get("user_id"),
            zoho_org_id=payload.get("org_id")
        )


# ============================================================================
# Moodle Webhook Events
# ============================================================================

class MoodleWebhookEvent(BaseModel):
    """
    Moodle webhook event.
    
    Custom webhooks from Moodle when students enroll/unenroll.
    """
    event_id: str = Field(..., description="Unique event ID")
    timestamp: datetime = Field(default_factory=datetime.utcnow, description="Event timestamp")
    event_type: str = Field(..., description="Event type (e.g., user_enrolled)")
    course_id: int = Field(..., description="Moodle course ID")
    user_id: int = Field(..., description="Moodle user ID")
    
    # Additional data
    course_shortname: Optional[str] = Field(None, description="Course short name")
    user_email: Optional[str] = Field(None, description="User email")
    enrollment_method: Optional[str] = Field(None, description="Enrollment method")
    role_id: Optional[int] = Field(None, description="Role ID")
    
    data: Optional[Dict[str, Any]] = Field(None, description="Additional event data")
    
    @classmethod
    def from_moodle_webhook(cls, payload: Dict[str, Any]) -> "MoodleWebhookEvent":
        """
        Create event from Moodle webhook payload.
        
        Args:
            payload: Raw Moodle webhook JSON
            
        Returns:
            MoodleWebhookEvent instance
        """
        return cls(
            event_id=payload.get("eventid", ""),
            timestamp=datetime.fromtimestamp(payload.get("timecreated", 0)),
            event_type=payload.get("eventname", ""),
            course_id=payload.get("courseid", 0),
            user_id=payload.get("userid", 0),
            course_shortname=payload.get("courseshortname"),
            user_email=payload.get("useremail"),
            enrollment_method=payload.get("enrolment"),
            role_id=payload.get("roleid"),
            data=payload.get("other")
        )


# ============================================================================
# Event Log (Database Model Schema)
# ============================================================================

class EventLogCreate(BaseModel):
    """Schema for creating event log."""
    event_id: str = Field(..., description="Unique event ID (for deduplication)")
    source: EventSource = Field(..., description="Event source (zoho or moodle)")
    module: str = Field(..., description="Module name (BTEC_Students, BTEC_Enrollments, etc.)")
    event_type: str = Field(..., description="Event type (created, updated, deleted)")
    record_id: str = Field(..., description="Record ID from source system")
    payload: Dict[str, Any] = Field(default_factory=dict, description="Full event payload")
    status: EventStatus = Field(EventStatus.PENDING, description="Processing status")


class EventLogUpdate(BaseModel):
    """Schema for updating event log."""
    status: Optional[EventStatus] = None
    processed_at: Optional[datetime] = None
    result: Optional[Dict[str, Any]] = None
    error_message: Optional[str] = None


class EventLogResponse(BaseModel):
    """Schema for event log response."""
    id: int
    event_id: str
    source: EventSource
    module: str
    event_type: str
    record_id: str
    status: EventStatus
    payload: Dict[str, Any]
    result: Optional[Dict[str, Any]]
    error_message: Optional[str]
    created_at: datetime
    processed_at: Optional[datetime]
    
    class Config:
        from_attributes = True


# ============================================================================
# Webhook Request/Response Models
# ============================================================================

class WebhookResponse(BaseModel):
    """Standard webhook response."""
    success: bool = Field(..., description="Whether webhook was accepted")
    message: str = Field(..., description="Response message")
    event_id: Optional[str] = Field(None, description="Event ID for tracking")
    status: Optional[str] = Field(None, description="Processing status")


class EventProcessingResult(BaseModel):
    """Result of event processing."""
    event_id: str
    status: EventStatus
    action_taken: Optional[str] = Field(None, description="Action taken (created, updated, skipped)")
    record_id: Optional[str] = Field(None, description="Affected record ID")
    error: Optional[str] = Field(None, description="Error message if failed")
    processing_time_ms: Optional[float] = Field(None, description="Processing time in milliseconds")


# ============================================================================
# HMAC Signature Models
# ============================================================================

class HMACVerificationRequest(BaseModel):
    """HMAC signature verification request."""
    signature: str = Field(..., description="HMAC signature from header")
    payload: str = Field(..., description="Raw request body as string")
    secret: str = Field(..., description="HMAC secret key")


class HMACVerificationResult(BaseModel):
    """HMAC verification result."""
    valid: bool = Field(..., description="Whether signature is valid")
    message: str = Field(..., description="Verification message")
