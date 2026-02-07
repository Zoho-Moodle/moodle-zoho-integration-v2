"""
Moodle Users Ingestion Endpoint

POST /api/v1/moodle/users

Accepts user data from Moodle and stores in local database.
This is for Moodle → Backend direction (opposite of sync endpoints).
"""

import logging
from typing import List, Dict, Any, Optional
from datetime import datetime
from fastapi import APIRouter, HTTPException, Depends, Header, Body
from sqlalchemy.orm import Session
from pydantic import BaseModel, EmailStr, Field

from app.infra.db.session import get_db
from app.infra.db.models import Student
from app.core.config import settings

logger = logging.getLogger(__name__)

router = APIRouter(
    prefix="/moodle",
    tags=["moodle-ingestion"]
)


# ==================== Request Models ====================

class MoodleUserData(BaseModel):
    """Single user data from Moodle"""
    id: int = Field(..., description="Moodle user ID")
    username: str = Field(..., description="Username (usually email)")
    firstname: str
    lastname: str
    email: EmailStr
    idnumber: Optional[str] = Field(None, description="Student ID number")
    phone1: Optional[str] = None
    phone2: Optional[str] = None
    city: Optional[str] = None
    country: Optional[str] = None
    timezone: Optional[str] = None
    suspended: Optional[bool] = False
    deleted: Optional[bool] = False
    auth: Optional[str] = Field(None, description="Authentication method")
    timecreated: Optional[int] = None
    timemodified: Optional[int] = None


class MoodleUsersRequest(BaseModel):
    """Request body for Moodle users ingestion"""
    users: List[MoodleUserData]
    source: Optional[str] = Field("moodle_webhook", description="Source of data")
    timestamp: Optional[str] = Field(None, description="Event timestamp")


class MoodleUserResult(BaseModel):
    """Result for single user processing"""
    moodle_id: int
    username: str
    status: str  # created, updated, skipped, error
    message: Optional[str] = None
    local_id: Optional[str] = None  # Our database ID


class MoodleUsersResponse(BaseModel):
    """Response for Moodle users ingestion"""
    status: str
    received: int
    results: List[MoodleUserResult]
    summary: Dict[str, int]


# ==================== Endpoint ====================

@router.post("/users", response_model=MoodleUsersResponse)
def ingest_moodle_users(
    request: MoodleUsersRequest = Body(...),
    db: Session = Depends(get_db),
    x_moodle_token: Optional[str] = Header(None, description="Moodle API token for verification"),
    x_tenant_id: Optional[str] = Header(None),
) -> MoodleUsersResponse:
    """
    Ingest user data from Moodle
    
    This endpoint receives user data from Moodle (via webhook or manual sync)
    and stores it in the local database for later processing/sync to Zoho.
    
    Args:
        request: List of Moodle users
        db: Database session
        x_moodle_token: Optional Moodle API token for authentication
        x_tenant_id: Optional tenant ID
        
    Returns:
        {
            "status": "success",
            "received": 10,
            "results": [...],
            "summary": {
                "created": 5,
                "updated": 3,
                "skipped": 1,
                "error": 1
            }
        }
    """
    
    tenant_id = x_tenant_id or settings.DEFAULT_TENANT_ID or "default"
    
    logger.info(f"📥 Received {len(request.users)} users from Moodle (tenant: {tenant_id})")
    
    results = []
    summary = {"created": 0, "updated": 0, "skipped": 0, "error": 0}
    
    for user_data in request.users:
        try:
            # Skip deleted or suspended users
            if user_data.deleted or user_data.suspended:
                results.append(MoodleUserResult(
                    moodle_id=user_data.id,
                    username=user_data.username,
                    status="skipped",
                    message="User is deleted or suspended"
                ))
                summary["skipped"] += 1
                continue
            
            # Check if user already exists (by moodle_user_id)
            existing = db.query(Student).filter(
                Student.moodle_user_id == str(user_data.id),
                Student.tenant_id == tenant_id
            ).first()
            
            full_name = f"{user_data.firstname} {user_data.lastname}".strip()
            
            if existing:
                # Update existing user
                existing.display_name = full_name
                existing.academic_email = user_data.email
                existing.username = user_data.username
                existing.phone = user_data.phone1 or user_data.phone2
                existing.city = user_data.city
                existing.country = user_data.country
                existing.userid = user_data.idnumber
                existing.updated_at = datetime.utcnow()
                
                db.commit()
                
                results.append(MoodleUserResult(
                    moodle_id=user_data.id,
                    username=user_data.username,
                    status="updated",
                    message="User data updated",
                    local_id=existing.id
                ))
                summary["updated"] += 1
                logger.info(f"✅ Updated user: {user_data.username} (Moodle ID: {user_data.id})")
                
            else:
                # Create new user
                new_student = Student(
                    tenant_id=tenant_id,
                    source="moodle",
                    moodle_user_id=str(user_data.id),
                    username=user_data.username,
                    display_name=full_name,
                    academic_email=user_data.email,
                    phone=user_data.phone1 or user_data.phone2,
                    city=user_data.city,
                    country=user_data.country,
                    userid=user_data.idnumber,
                    status="active"
                )
                
                db.add(new_student)
                db.commit()
                db.refresh(new_student)
                
                results.append(MoodleUserResult(
                    moodle_id=user_data.id,
                    username=user_data.username,
                    status="created",
                    message="New user created",
                    local_id=new_student.id
                ))
                summary["created"] += 1
                logger.info(f"✅ Created new user: {user_data.username} (Moodle ID: {user_data.id})")
                
        except Exception as e:
            logger.error(f"❌ Error processing user {user_data.id}: {str(e)}")
            results.append(MoodleUserResult(
                moodle_id=user_data.id,
                username=user_data.username,
                status="error",
                message=str(e)
            ))
            summary["error"] += 1
            db.rollback()
    
    logger.info(f"📊 Moodle users ingestion summary: {summary}")
    
    return MoodleUsersResponse(
        status="success",
        received=len(request.users),
        results=results,
        summary=summary
    )
