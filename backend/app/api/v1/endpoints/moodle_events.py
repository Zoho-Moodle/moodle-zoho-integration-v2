"""
Moodle Webhook Events Endpoint

Receives real-time events from Moodle observers via webhooks.
Events are immediately processed and stored in the local database.

Data Flow: Moodle Observer → Webhook (this endpoint) → Local DB → (later) → Zoho CRM

Supported Events:
- user_created: New user created in Moodle
- user_updated: User profile updated in Moodle
- user_enrolled: User enrolled in a course
- grade_updated: Grade submitted/updated
"""

from fastapi import APIRouter, Depends, Header, HTTPException, Request
from sqlalchemy.orm import Session
from typing import Optional, Dict, Any
from pydantic import BaseModel, Field
from datetime import datetime
from uuid import uuid4
import logging
import json

from app.infra.db.session import get_db
from app.infra.db.models import Student, Enrollment, Grade
from app.infra.db.models.class_ import Class
from app.infra.db.models.unit import Unit

logger = logging.getLogger(__name__)

router = APIRouter(prefix="/events/moodle", tags=["Moodle Webhooks"])


# ============================================================================
# Request/Response Models
# ============================================================================

class MoodleUserEvent(BaseModel):
    """Moodle user created/updated event"""
    eventname: str = Field(..., description="Event name (e.g., \\core\\event\\user_created)")
    userid: int = Field(..., description="Moodle user ID")
    username: str = Field(..., description="Username/email")
    firstname: str = Field(..., description="First name")
    lastname: str = Field(..., description="Last name")
    email: str = Field(..., description="Email address")
    idnumber: Optional[str] = Field(None, description="ID number")
    phone1: Optional[str] = Field(None, description="Phone number")
    city: Optional[str] = Field(None, description="City")
    country: Optional[str] = Field(None, description="Country code")
    suspended: Optional[bool] = Field(False, description="Is suspended")
    deleted: Optional[bool] = Field(False, description="Is deleted")
    timecreated: int = Field(..., description="Creation timestamp")
    timemodified: int = Field(..., description="Modification timestamp")


class MoodleEnrollmentEvent(BaseModel):
    """Moodle user enrolled event"""
    eventname: str = Field(..., description="Event name (e.g., \\core\\event\\user_enrolment_created)")
    enrollmentid: int = Field(..., description="Enrollment ID")
    userid: int = Field(..., description="User ID")
    courseid: int = Field(..., description="Course ID")
    roleid: int = Field(..., description="Role ID")
    status: int = Field(0, description="Status: 0=active, 1=suspended")
    timestart: int = Field(..., description="Start timestamp")
    timeend: Optional[int] = Field(None, description="End timestamp")
    timecreated: int = Field(..., description="Creation timestamp")


class MoodleGradeEvent(BaseModel):
    """Moodle grade updated event"""
    eventname: str = Field(..., description="Event name (e.g., \\core\\event\\user_graded)")
    gradeid: int = Field(..., description="Grade ID")
    userid: int = Field(..., description="User ID")
    itemid: int = Field(..., description="Grade item ID")
    itemname: Optional[str] = Field(None, description="Item name")
    finalgrade: Optional[float] = Field(None, description="Final grade")
    feedback: Optional[str] = Field(None, description="Feedback")
    grader: Optional[int] = Field(None, description="Grader user ID")
    timecreated: int = Field(..., description="Creation timestamp")
    timemodified: int = Field(..., description="Modification timestamp")


class WebhookResponse(BaseModel):
    """Standard webhook response"""
    success: bool
    message: str
    event_id: Optional[str] = None
    timestamp: str


# ============================================================================
# Helper Functions
# ============================================================================

def convert_moodle_grade(finalgrade: Optional[float]) -> str:
    """Convert Moodle numeric grade to BTEC letter grade"""
    if finalgrade is None:
        return "Not Graded"
    if finalgrade >= 70:
        return "Distinction"
    elif finalgrade >= 60:
        return "Merit"
    elif finalgrade >= 40:
        return "Pass"
    else:
        return "Refer"


# ============================================================================
# Webhook Endpoints
# ============================================================================

@router.post("/user_created", response_model=WebhookResponse)
async def handle_user_created(
    event: MoodleUserEvent,
    db: Session = Depends(get_db),
    x_moodle_token: Optional[str] = Header(None),
    x_tenant_id: Optional[str] = Header(None),
):
    """
    Webhook endpoint for Moodle user_created event.
    
    Called by Moodle when a new user is created.
    
    **Moodle Observer Configuration:**
    ```php
    // In your Moodle plugin's events.php
    $observers = [
        [
            'eventname' => '\\core\\event\\user_created',
            'callback' => 'local_backend_sync_observer::user_created',
        ],
    ];
    ```
    
    **Example Payload:**
    ```json
    {
      "eventname": "\\\\core\\\\event\\\\user_created",
      "userid": 123,
      "username": "john.doe@example.com",
      "firstname": "John",
      "lastname": "Doe",
      "email": "john.doe@example.com",
      "timecreated": 1640000000,
      "timemodified": 1640000000
    }
    ```
    """
    tenant_id = x_tenant_id or "default"
    
    logger.info(f"👤 Received user_created event: User {event.userid} - {event.firstname} {event.lastname}")
    
    try:
        # Check if user already exists
        existing = db.query(Student).filter(
            Student.moodle_user_id == str(event.userid),
            Student.tenant_id == tenant_id
        ).first()
        
        if existing:
            logger.warning(f"⚠️ User {event.userid} already exists, skipping")
            return WebhookResponse(
                success=True,
                message=f"User already exists",
                event_id=existing.id,
                timestamp=datetime.now().isoformat()
            )
        
        # Skip suspended/deleted users
        if event.suspended or event.deleted:
            logger.info(f"⏭️ Skipping suspended/deleted user {event.userid}")
            return WebhookResponse(
                success=True,
                message="User is suspended or deleted, skipped",
                timestamp=datetime.now().isoformat()
            )
        
        # Create new student
        full_name = f"{event.firstname} {event.lastname}".strip()
        new_student = Student(
            id=str(uuid4()),
            tenant_id=tenant_id,
            source="moodle",
            zoho_id=None,
            moodle_user_id=str(event.userid),
            userid=event.idnumber,
            username=event.username,
            display_name=full_name,
            academic_email=event.email,
            phone=event.phone1,
            city=event.city,
            country=event.country,
            status="active",
            sync_status="pending",
            created_at=datetime.now(),
            updated_at=datetime.now()
        )
        
        db.add(new_student)
        db.commit()
        
        logger.info(f"✅ Created student from webhook: {full_name} (Moodle ID: {event.userid})")
        
        return WebhookResponse(
            success=True,
            message=f"User created successfully: {full_name}",
            event_id=new_student.id,
            timestamp=datetime.now().isoformat()
        )
        
    except Exception as e:
        logger.error(f"❌ Error processing user_created event: {str(e)}")
        db.rollback()
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/user_updated", response_model=WebhookResponse)
async def handle_user_updated(
    event: MoodleUserEvent,
    db: Session = Depends(get_db),
    x_moodle_token: Optional[str] = Header(None),
    x_tenant_id: Optional[str] = Header(None),
):
    """
    Webhook endpoint for Moodle user_updated event.
    
    Called by Moodle when a user profile is updated.
    """
    tenant_id = x_tenant_id or "default"
    
    logger.info(f"🔄 Received user_updated event: User {event.userid}")
    
    try:
        # Find existing student
        student = db.query(Student).filter(
            Student.moodle_user_id == str(event.userid),
            Student.tenant_id == tenant_id
        ).first()
        
        if not student:
            # User doesn't exist, create it
            logger.warning(f"⚠️ User {event.userid} not found, creating new record")
            return await handle_user_created(event, db, x_moodle_token, x_tenant_id)
        
        # Update student information
        full_name = f"{event.firstname} {event.lastname}".strip()
        student.display_name = full_name
        student.academic_email = event.email
        student.username = event.username
        student.userid = event.idnumber
        student.phone = event.phone1
        student.city = event.city
        student.country = event.country
        student.status = "suspended" if event.suspended else "active"
        student.updated_at = datetime.now()
        
        db.commit()
        
        logger.info(f"✅ Updated student from webhook: {full_name} (Moodle ID: {event.userid})")
        
        return WebhookResponse(
            success=True,
            message=f"User updated successfully: {full_name}",
            event_id=student.id,
            timestamp=datetime.now().isoformat()
        )
        
    except Exception as e:
        logger.error(f"❌ Error processing user_updated event: {str(e)}")
        db.rollback()
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/user_enrolled", response_model=WebhookResponse)
async def handle_user_enrolled(
    event: MoodleEnrollmentEvent,
    db: Session = Depends(get_db),
    x_moodle_token: Optional[str] = Header(None),
    x_tenant_id: Optional[str] = Header(None),
):
    """
    Webhook endpoint for Moodle user_enrolment_created event.
    
    Called by Moodle when a user is enrolled in a course.
    
    **Moodle Observer Configuration:**
    ```php
    $observers = [
        [
            'eventname' => '\\core\\event\\user_enrolment_created',
            'callback' => 'local_backend_sync_observer::user_enrolled',
        ],
    ];
    ```
    """
    tenant_id = x_tenant_id or "default"
    
    logger.info(f"📚 Received user_enrolled event: User {event.userid} → Course {event.courseid}")
    
    try:
        # Find student
        student = db.query(Student).filter(
            Student.moodle_user_id == str(event.userid),
            Student.tenant_id == tenant_id
        ).first()
        
        if not student:
            logger.warning(f"⚠️ Student not found: Moodle ID {event.userid}")
            return WebhookResponse(
                success=False,
                message=f"Student with Moodle ID {event.userid} not found",
                timestamp=datetime.now().isoformat()
            )
        
        # Find course
        course = db.query(Class).filter(
            Class.moodle_class_id == str(event.courseid),
            Class.tenant_id == tenant_id
        ).first()
        
        if not course:
            logger.warning(f"⚠️ Course not found: Moodle ID {event.courseid}")
            return WebhookResponse(
                success=False,
                message=f"Course with Moodle ID {event.courseid} not found",
                timestamp=datetime.now().isoformat()
            )
        
        # Check if enrollment already exists
        existing = db.query(Enrollment).filter(
            Enrollment.moodle_user_id == event.userid,
            Enrollment.moodle_course_id == str(event.courseid),
            Enrollment.tenant_id == tenant_id
        ).first()
        
        status = "active" if event.status == 0 else "suspended"
        
        if existing:
            # Update existing enrollment
            existing.status = status
            existing.moodle_enrollment_id = event.enrollmentid
            existing.start_date = datetime.fromtimestamp(event.timestart).date()
            existing.updated_at = datetime.now()
            db.commit()
            
            logger.info(f"✅ Updated enrollment: User {event.userid} → Course {event.courseid}")
            return WebhookResponse(
                success=True,
                message=f"Enrollment updated: {student.display_name} → {course.name}",
                event_id=existing.id,
                timestamp=datetime.now().isoformat()
            )
        
        # Create new enrollment
        new_enrollment = Enrollment(
            id=str(uuid4()),
            tenant_id=tenant_id,
            source="moodle",
            zoho_id=None,
            moodle_enrollment_id=event.enrollmentid,
            moodle_user_id=event.userid,
            moodle_course_id=str(event.courseid),
            student_zoho_id=student.zoho_id,
            student_name=student.display_name,
            class_zoho_id=course.zoho_id,
            class_name=course.name,
            status=status,
            start_date=datetime.fromtimestamp(event.timestart).date(),
            created_at=datetime.now(),
            updated_at=datetime.now()
        )
        
        db.add(new_enrollment)
        db.commit()
        
        logger.info(f"✅ Created enrollment from webhook: {student.display_name} → {course.name}")
        
        return WebhookResponse(
            success=True,
            message=f"Enrollment created: {student.display_name} → {course.name}",
            event_id=new_enrollment.id,
            timestamp=datetime.now().isoformat()
        )
        
    except Exception as e:
        logger.error(f"❌ Error processing user_enrolled event: {str(e)}")
        db.rollback()
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/grade_updated", response_model=WebhookResponse)
async def handle_grade_updated(
    event: MoodleGradeEvent,
    db: Session = Depends(get_db),
    x_moodle_token: Optional[str] = Header(None),
    x_tenant_id: Optional[str] = Header(None),
):
    """
    Webhook endpoint for Moodle user_graded event.
    
    Called by Moodle when a grade is submitted or updated.
    
    **Moodle Observer Configuration:**
    ```php
    $observers = [
        [
            'eventname' => '\\core\\event\\user_graded',
            'callback' => 'local_backend_sync_observer::grade_updated',
        ],
    ];
    ```
    """
    tenant_id = x_tenant_id or "default"
    
    logger.info(f"📊 Received grade_updated event: User {event.userid} → Item {event.itemid} = {event.finalgrade}")
    
    try:
        # Find student
        student = db.query(Student).filter(
            Student.moodle_user_id == str(event.userid),
            Student.tenant_id == tenant_id
        ).first()
        
        if not student:
            logger.warning(f"⚠️ Student not found: Moodle ID {event.userid}")
            return WebhookResponse(
                success=False,
                message=f"Student with Moodle ID {event.userid} not found",
                timestamp=datetime.now().isoformat()
            )
        
        # Find unit
        unit = db.query(Unit).filter(
            Unit.moodle_unit_id == str(event.itemid),
            Unit.tenant_id == tenant_id
        ).first()
        
        if not unit:
            logger.warning(f"⚠️ Unit not found: Moodle item ID {event.itemid}")
            return WebhookResponse(
                success=False,
                message=f"Unit with Moodle item ID {event.itemid} not found. Please map grade items first.",
                timestamp=datetime.now().isoformat()
            )
        
        # Convert grade
        btec_grade = convert_moodle_grade(event.finalgrade)
        
        # Check if grade exists
        existing = db.query(Grade).filter(
            Grade.student_zoho_id == student.zoho_id,
            Grade.unit_zoho_id == unit.zoho_id,
            Grade.tenant_id == tenant_id
        ).first() if student.zoho_id and unit.zoho_id else None
        
        if existing:
            # Update existing grade
            existing.grade_value = btec_grade
            existing.score = event.finalgrade
            existing.comments = event.feedback
            existing.grade_date = datetime.fromtimestamp(event.timemodified).strftime("%Y-%m-%d")
            existing.updated_at = datetime.now()
            db.commit()
            
            logger.info(f"✅ Updated grade: {student.display_name} → {unit.unit_name} = {btec_grade}")
            return WebhookResponse(
                success=True,
                message=f"Grade updated: {student.display_name} = {btec_grade}",
                event_id=existing.id,
                timestamp=datetime.now().isoformat()
            )
        
        # Create new grade
        new_grade = Grade(
            id=str(uuid4()),
            tenant_id=tenant_id,
            source="moodle",
            zoho_id=None,
            student_zoho_id=student.zoho_id,
            unit_zoho_id=unit.zoho_id,
            grade_value=btec_grade,
            score=event.finalgrade,
            comments=event.feedback,
            grade_date=datetime.fromtimestamp(event.timemodified).strftime("%Y-%m-%d"),
            sync_status="pending",
            created_at=datetime.now(),
            updated_at=datetime.now()
        )
        
        db.add(new_grade)
        db.commit()
        
        logger.info(f"✅ Created grade from webhook: {student.display_name} → {unit.unit_name} = {btec_grade}")
        
        return WebhookResponse(
            success=True,
            message=f"Grade created: {student.display_name} = {btec_grade} ({event.finalgrade}%)",
            event_id=new_grade.id,
            timestamp=datetime.now().isoformat()
        )
        
    except Exception as e:
        logger.error(f"❌ Error processing grade_updated event: {str(e)}")
        db.rollback()
        raise HTTPException(status_code=500, detail=str(e))


@router.get("/health")
async def health_check():
    """Health check endpoint for Moodle webhooks"""
    return {
        "status": "ok",
        "service": "Moodle Webhooks",
        "endpoints": [
            "/events/moodle/user_created",
            "/events/moodle/user_updated",
            "/events/moodle/user_enrolled",
            "/events/moodle/grade_updated"
        ],
        "timestamp": datetime.now().isoformat()
    }
