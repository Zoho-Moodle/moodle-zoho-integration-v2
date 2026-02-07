"""
Moodle Enrollments Ingestion Endpoint

This endpoint receives enrollment data from Moodle and stores it in the local database.
Later, this data will be synced to Zoho CRM.

Data Flow: Moodle → Backend (this endpoint) → Local DB → (later) → Zoho CRM
"""

from fastapi import APIRouter, Depends, Header, HTTPException
from sqlalchemy.orm import Session
from typing import List, Optional, Dict, Any
from pydantic import BaseModel, Field
from datetime import datetime
from uuid import uuid4
import logging

from app.infra.db.session import get_db
from app.infra.db.models import Enrollment, Student
from app.infra.db.models.class_ import Class

logger = logging.getLogger(__name__)

router = APIRouter(prefix="/moodle", tags=["Moodle Ingestion"])


# ============================================================================
# Request/Response Models
# ============================================================================

class MoodleEnrollmentData(BaseModel):
    """Single Moodle enrollment data"""
    id: int = Field(..., description="Moodle enrollment ID")
    userid: int = Field(..., description="Moodle user ID")
    courseid: int = Field(..., description="Moodle course ID")
    roleid: int = Field(..., description="Role ID (e.g., 5=student, 3=teacher)")
    status: int = Field(..., description="Status: 0=active, 1=suspended")
    timestart: int = Field(..., description="Enrollment start timestamp")
    timeend: Optional[int] = Field(None, description="Enrollment end timestamp")
    timecreated: int = Field(..., description="Creation timestamp")
    timemodified: int = Field(..., description="Last modification timestamp")


class MoodleEnrollmentsRequest(BaseModel):
    """Request body for batch enrollment ingestion"""
    enrollments: List[MoodleEnrollmentData] = Field(..., description="List of enrollments to ingest")
    timestamp: Optional[str] = Field(None, description="Request timestamp")


class MoodleEnrollmentResult(BaseModel):
    """Result for a single enrollment"""
    moodle_enrollment_id: int
    moodle_user_id: int
    moodle_course_id: int
    status: str  # created, updated, skipped, error
    message: Optional[str] = None
    db_id: Optional[str] = None  # UUID of the enrollment record


class MoodleEnrollmentsResponse(BaseModel):
    """Response for batch enrollment ingestion"""
    success: bool
    timestamp: str
    summary: Dict[str, int]
    results: List[MoodleEnrollmentResult]


# ============================================================================
# Helper Functions
# ============================================================================

def get_enrollment_status(moodle_status: int) -> str:
    """Convert Moodle status code to readable status"""
    status_map = {
        0: "active",
        1: "suspended"
    }
    return status_map.get(moodle_status, "unknown")


def find_student_by_moodle_id(db: Session, moodle_user_id: int, tenant_id: str) -> Optional[Student]:
    """Find student by Moodle user ID"""
    return db.query(Student).filter(
        Student.moodle_user_id == str(moodle_user_id),
        Student.tenant_id == tenant_id
    ).first()


def find_class_by_moodle_id(db: Session, moodle_course_id: int, tenant_id: str) -> Optional[Class]:
    """Find class by Moodle course ID"""
    return db.query(Class).filter(
        Class.moodle_class_id == str(moodle_course_id),
        Class.tenant_id == tenant_id
    ).first()


# ============================================================================
# Endpoint
# ============================================================================

@router.post("/enrollments", response_model=MoodleEnrollmentsResponse)
def ingest_moodle_enrollments(
    request: MoodleEnrollmentsRequest,
    db: Session = Depends(get_db),
    x_moodle_token: Optional[str] = Header(None),
    x_tenant_id: Optional[str] = Header(None),
):
    """
    Ingest enrollment data from Moodle into the local database.
    
    This endpoint:
    1. Receives enrollment data from Moodle
    2. Validates student and course existence
    3. Creates or updates enrollment records
    4. Returns detailed results
    
    The data will later be synced to Zoho CRM.
    
    **Headers:**
    - X-Moodle-Token: Authentication token from Moodle (optional)
    - X-Tenant-ID: Tenant identifier (optional, defaults to "default")
    
    **Request Body:**
    ```json
    {
      "enrollments": [
        {
          "id": 1234,
          "userid": 101,
          "courseid": 202,
          "roleid": 5,
          "status": 0,
          "timestart": 1640000000,
          "timeend": 1700000000,
          "timecreated": 1640000000,
          "timemodified": 1640000000
        }
      ],
      "timestamp": "2026-01-26T00:00:00Z"
    }
    ```
    
    **Response:**
    ```json
    {
      "success": true,
      "timestamp": "2026-01-26T00:00:00Z",
      "summary": {
        "received": 1,
        "created": 1,
        "updated": 0,
        "skipped": 0,
        "errors": 0
      },
      "results": [...]
    }
    ```
    """
    tenant_id = x_tenant_id or "default"
    
    logger.info(f"📥 Received enrollment ingestion request: {len(request.enrollments)} enrollments")
    
    results = []
    summary = {
        "received": len(request.enrollments),
        "created": 0,
        "updated": 0,
        "skipped": 0,
        "errors": 0
    }
    
    for enrollment_data in request.enrollments:
        try:
            # Convert status
            status = get_enrollment_status(enrollment_data.status)
            
            # Find student
            student = find_student_by_moodle_id(db, enrollment_data.userid, tenant_id)
            if not student:
                logger.warning(f"⚠️ Student not found for Moodle user ID: {enrollment_data.userid}")
                results.append(MoodleEnrollmentResult(
                    moodle_enrollment_id=enrollment_data.id,
                    moodle_user_id=enrollment_data.userid,
                    moodle_course_id=enrollment_data.courseid,
                    status="skipped",
                    message=f"Student with Moodle ID {enrollment_data.userid} not found in database"
                ))
                summary["skipped"] += 1
                continue
            
            # Find class/course
            course_class = find_class_by_moodle_id(db, enrollment_data.courseid, tenant_id)
            if not course_class:
                logger.warning(f"⚠️ Course not found for Moodle course ID: {enrollment_data.courseid}")
                results.append(MoodleEnrollmentResult(
                    moodle_enrollment_id=enrollment_data.id,
                    moodle_user_id=enrollment_data.userid,
                    moodle_course_id=enrollment_data.courseid,
                    status="skipped",
                    message=f"Course with Moodle ID {enrollment_data.courseid} not found in database"
                ))
                summary["skipped"] += 1
                continue
            
            # Check if enrollment exists (by moodle_user_id + moodle_course_id)
            existing_enrollment = db.query(Enrollment).filter(
                Enrollment.moodle_user_id == enrollment_data.userid,
                Enrollment.moodle_course_id == str(enrollment_data.courseid),
                Enrollment.tenant_id == tenant_id
            ).first()
            
            if existing_enrollment:
                # Update existing enrollment
                existing_enrollment.status = status
                existing_enrollment.moodle_enrollment_id = enrollment_data.id
                existing_enrollment.start_date = datetime.fromtimestamp(enrollment_data.timestart).date()
                existing_enrollment.updated_at = datetime.now()
                
                # Update student/class references if they've changed
                if student.zoho_id:
                    existing_enrollment.student_zoho_id = student.zoho_id
                    existing_enrollment.student_name = student.display_name
                
                if course_class.zoho_id:
                    existing_enrollment.class_zoho_id = course_class.zoho_id
                    existing_enrollment.class_name = course_class.name
                
                db.commit()
                
                logger.info(f"✅ Updated enrollment: User {enrollment_data.userid} → Course {enrollment_data.courseid}")
                results.append(MoodleEnrollmentResult(
                    moodle_enrollment_id=enrollment_data.id,
                    moodle_user_id=enrollment_data.userid,
                    moodle_course_id=enrollment_data.courseid,
                    status="updated",
                    message="Enrollment updated",
                    db_id=existing_enrollment.id
                ))
                summary["updated"] += 1
            else:
                # Create new enrollment
                new_enrollment = Enrollment(
                    id=str(uuid4()),
                    tenant_id=tenant_id,
                    source="moodle",
                    zoho_id=None,  # Will be populated when synced to Zoho
                    moodle_enrollment_id=enrollment_data.id,
                    moodle_user_id=enrollment_data.userid,
                    moodle_course_id=str(enrollment_data.courseid),
                    student_zoho_id=student.zoho_id,
                    student_name=student.display_name,
                    class_zoho_id=course_class.zoho_id,
                    class_name=course_class.name,
                    status=status,
                    start_date=datetime.fromtimestamp(enrollment_data.timestart).date(),
                    created_at=datetime.now(),
                    updated_at=datetime.now()
                )
                
                db.add(new_enrollment)
                db.commit()
                
                logger.info(f"✅ Created enrollment: User {enrollment_data.userid} → Course {enrollment_data.courseid}")
                results.append(MoodleEnrollmentResult(
                    moodle_enrollment_id=enrollment_data.id,
                    moodle_user_id=enrollment_data.userid,
                    moodle_course_id=enrollment_data.courseid,
                    status="created",
                    message="New enrollment created",
                    db_id=new_enrollment.id
                ))
                summary["created"] += 1
                
        except Exception as e:
            logger.error(f"❌ Error processing enrollment {enrollment_data.id}: {str(e)}")
            db.rollback()
            results.append(MoodleEnrollmentResult(
                moodle_enrollment_id=enrollment_data.id,
                moodle_user_id=enrollment_data.userid,
                moodle_course_id=enrollment_data.courseid,
                status="error",
                message=str(e)
            ))
            summary["errors"] += 1
    
    return MoodleEnrollmentsResponse(
        success=summary["errors"] == 0,
        timestamp=datetime.now().isoformat(),
        summary=summary,
        results=results
    )
