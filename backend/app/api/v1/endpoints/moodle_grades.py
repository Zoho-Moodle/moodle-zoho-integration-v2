"""
Moodle Grades Ingestion Endpoint

This endpoint receives grade data from Moodle and stores it in the local database.
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
from app.infra.db.models import Grade, Student
from app.infra.db.models.unit import Unit

logger = logging.getLogger(__name__)

router = APIRouter(prefix="/moodle", tags=["Moodle Ingestion"])


# ============================================================================
# Request/Response Models
# ============================================================================

class MoodleGradeData(BaseModel):
    """Single Moodle grade data"""
    id: int = Field(..., description="Moodle grade ID")
    userid: int = Field(..., description="Moodle user ID")
    itemid: int = Field(..., description="Moodle grade item ID (assignment/quiz)")
    itemname: Optional[str] = Field(None, description="Grade item name")
    itemmodule: Optional[str] = Field(None, description="Module type (assign, quiz, etc.)")
    finalgrade: Optional[float] = Field(None, description="Final grade (0-100)")
    rawgrade: Optional[float] = Field(None, description="Raw grade")
    feedback: Optional[str] = Field(None, description="Feedback text")
    grader: Optional[int] = Field(None, description="Grader user ID")
    timecreated: int = Field(..., description="Creation timestamp")
    timemodified: int = Field(..., description="Last modification timestamp")


class MoodleGradesRequest(BaseModel):
    """Request body for batch grade ingestion"""
    grades: List[MoodleGradeData] = Field(..., description="List of grades to ingest")
    timestamp: Optional[str] = Field(None, description="Request timestamp")


class MoodleGradeResult(BaseModel):
    """Result for a single grade"""
    moodle_grade_id: int
    moodle_user_id: int
    moodle_item_id: int
    status: str  # created, updated, skipped, error
    message: Optional[str] = None
    db_id: Optional[str] = None  # UUID of the grade record


class MoodleGradesResponse(BaseModel):
    """Response for batch grade ingestion"""
    success: bool
    timestamp: str
    summary: Dict[str, int]
    results: List[MoodleGradeResult]


# ============================================================================
# Helper Functions
# ============================================================================

def convert_moodle_grade(finalgrade: Optional[float]) -> str:
    """
    Convert Moodle numeric grade (0-100) to BTEC grade letter.
    
    BTEC Grading:
    - Distinction (D): 70-100
    - Merit (M): 60-69
    - Pass (P): 40-59
    - Refer (R): 0-39
    """
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


def find_student_by_moodle_id(db: Session, moodle_user_id: int, tenant_id: str) -> Optional[Student]:
    """Find student by Moodle user ID"""
    return db.query(Student).filter(
        Student.moodle_user_id == str(moodle_user_id),
        Student.tenant_id == tenant_id
    ).first()


def find_unit_by_moodle_item(db: Session, moodle_item_id: int, tenant_id: str) -> Optional[Unit]:
    """
    Find unit by Moodle grade item ID.
    
    Note: This is a simplified mapping. In production, you'll need to maintain
    a mapping table between Moodle grade items and Zoho units.
    """
    # For now, we'll try to match by moodle_unit_id if it exists
    return db.query(Unit).filter(
        Unit.moodle_unit_id == str(moodle_item_id),
        Unit.tenant_id == tenant_id
    ).first()


# ============================================================================
# Endpoint
# ============================================================================

@router.post("/grades", response_model=MoodleGradesResponse)
def ingest_moodle_grades(
    request: MoodleGradesRequest,
    db: Session = Depends(get_db),
    x_moodle_token: Optional[str] = Header(None),
    x_tenant_id: Optional[str] = Header(None),
):
    """
    Ingest grade data from Moodle into the local database.
    
    This endpoint:
    1. Receives grade data from Moodle
    2. Converts numeric grades to BTEC letter grades
    3. Validates student and unit existence
    4. Creates or updates grade records
    5. Returns detailed results
    
    The data will later be synced to Zoho CRM.
    
    **Headers:**
    - X-Moodle-Token: Authentication token from Moodle (optional)
    - X-Tenant-ID: Tenant identifier (optional, defaults to "default")
    
    **Request Body:**
    ```json
    {
      "grades": [
        {
          "id": 5001,
          "userid": 101,
          "itemid": 301,
          "itemname": "Assignment 1",
          "itemmodule": "assign",
          "finalgrade": 85.5,
          "feedback": "Excellent work!",
          "grader": 2,
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
    
    logger.info(f"📥 Received grade ingestion request: {len(request.grades)} grades")
    
    results = []
    summary = {
        "received": len(request.grades),
        "created": 0,
        "updated": 0,
        "skipped": 0,
        "errors": 0
    }
    
    for grade_data in request.grades:
        try:
            # Find student
            student = find_student_by_moodle_id(db, grade_data.userid, tenant_id)
            if not student:
                logger.warning(f"⚠️ Student not found for Moodle user ID: {grade_data.userid}")
                results.append(MoodleGradeResult(
                    moodle_grade_id=grade_data.id,
                    moodle_user_id=grade_data.userid,
                    moodle_item_id=grade_data.itemid,
                    status="skipped",
                    message=f"Student with Moodle ID {grade_data.userid} not found in database"
                ))
                summary["skipped"] += 1
                continue
            
            # Find unit
            unit = find_unit_by_moodle_item(db, grade_data.itemid, tenant_id)
            if not unit:
                logger.warning(f"⚠️ Unit not found for Moodle item ID: {grade_data.itemid}")
                results.append(MoodleGradeResult(
                    moodle_grade_id=grade_data.id,
                    moodle_user_id=grade_data.userid,
                    moodle_item_id=grade_data.itemid,
                    status="skipped",
                    message=f"Unit with Moodle item ID {grade_data.itemid} not found. Please map grade items to units first."
                ))
                summary["skipped"] += 1
                continue
            
            # Convert grade
            btec_grade = convert_moodle_grade(grade_data.finalgrade)
            
            # Check if grade exists (by student + unit)
            existing_grade = db.query(Grade).filter(
                Grade.student_zoho_id == student.zoho_id,
                Grade.unit_zoho_id == unit.zoho_id,
                Grade.tenant_id == tenant_id
            ).first() if student.zoho_id and unit.zoho_id else None
            
            if existing_grade:
                # Update existing grade
                existing_grade.grade_value = btec_grade
                existing_grade.score = grade_data.finalgrade
                existing_grade.comments = grade_data.feedback
                existing_grade.grade_date = datetime.fromtimestamp(grade_data.timemodified).strftime("%Y-%m-%d")
                existing_grade.updated_at = datetime.now()
                
                db.commit()
                
                logger.info(f"✅ Updated grade: User {grade_data.userid} → Item {grade_data.itemid} = {btec_grade}")
                results.append(MoodleGradeResult(
                    moodle_grade_id=grade_data.id,
                    moodle_user_id=grade_data.userid,
                    moodle_item_id=grade_data.itemid,
                    status="updated",
                    message=f"Grade updated to {btec_grade}",
                    db_id=existing_grade.id
                ))
                summary["updated"] += 1
            else:
                # Create new grade
                new_grade = Grade(
                    id=str(uuid4()),
                    tenant_id=tenant_id,
                    source="moodle",
                    zoho_id=None,  # Will be populated when synced to Zoho
                    student_zoho_id=student.zoho_id,
                    unit_zoho_id=unit.zoho_id,
                    grade_value=btec_grade,
                    score=grade_data.finalgrade,
                    comments=grade_data.feedback,
                    grade_date=datetime.fromtimestamp(grade_data.timemodified).strftime("%Y-%m-%d"),
                    sync_status="pending",
                    created_at=datetime.now(),
                    updated_at=datetime.now()
                )
                
                db.add(new_grade)
                db.commit()
                
                logger.info(f"✅ Created grade: User {grade_data.userid} → Item {grade_data.itemid} = {btec_grade}")
                results.append(MoodleGradeResult(
                    moodle_grade_id=grade_data.id,
                    moodle_user_id=grade_data.userid,
                    moodle_item_id=grade_data.itemid,
                    status="created",
                    message=f"New grade created: {btec_grade} ({grade_data.finalgrade}%)",
                    db_id=new_grade.id
                ))
                summary["created"] += 1
                
        except Exception as e:
            logger.error(f"❌ Error processing grade {grade_data.id}: {str(e)}")
            db.rollback()
            results.append(MoodleGradeResult(
                moodle_grade_id=grade_data.id,
                moodle_user_id=grade_data.userid,
                moodle_item_id=grade_data.itemid,
                status="error",
                message=str(e)
            ))
            summary["errors"] += 1
    
    return MoodleGradesResponse(
        success=summary["errors"] == 0,
        timestamp=datetime.now().isoformat(),
        summary=summary,
        results=results
    )
