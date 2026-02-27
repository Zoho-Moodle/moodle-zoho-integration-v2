"""
Create Course Endpoint - Zoho → Moodle Course Creation

POST /v1/classes/create - Create course in Moodle and update Zoho

This endpoint provides centralized course creation from Zoho CRM.
Replaces direct Moodle API calls from Zoho Deluge functions.
"""

import logging
from typing import Optional
from fastapi import APIRouter, HTTPException, Depends
from sqlalchemy.orm import Session
from pydantic import BaseModel
from datetime import datetime, timezone, timedelta

from app.infra.db.session import get_db
from app.infra.moodle.users import MoodleClient
from app.infra.zoho import ZohoClient

logger = logging.getLogger(__name__)

router = APIRouter(
    prefix="/classes",
    tags=["classes"]
)


class CreateCourseRequest(BaseModel):
    """Request body for creating course in Moodle"""
    zoho_class_id: str
    class_name: str
    class_short_name: str
    category_id: int
    start_date: str  # Format: YYYY-MM-DD
    num_sections: int = 12
    teacher_zoho_id: Optional[str] = None
    class_major: Optional[str] = None


class CreateCourseResponse(BaseModel):
    """Response for course creation"""
    status: str
    moodle_course_id: int
    zoho_updated: bool
    teacher_enrolled: bool
    default_users_enrolled: int
    message: str


@router.post("/create", response_model=CreateCourseResponse)
async def create_course_in_moodle(
    request: CreateCourseRequest,
    db: Session = Depends(get_db),
) -> CreateCourseResponse:
    """
    Create course in Moodle and update Zoho BTEC_Classes record
    
    This endpoint is called from Zoho Function Button to:
    1. Create course in Moodle
    2. Get Moodle Course ID
    3. Update Zoho BTEC_Classes record with Moodle_Class_ID
    4. Enroll teacher if provided
    5. Enroll default users (IT Support, Student Affairs, CEO, Admin)
    
    Flow:
    ```
    Zoho Button → Backend API → Moodle → Backend → Zoho (Update)
    ```
    
    Default Users Enrolled:
    - IT Support (ID: 8157, Role: Teacher)
    - Student Affairs (ID: 8181, Role: Teacher)
    - IT Program Leader (ID: 8133, Role: Teacher) - Only if Class_Major == "IT"
    - CEO (ID: 8154, Role: Teacher)
    - Moodle Super Admin (ID: 2, Role: Manager)
    
    Args:
        request: Course creation data from Zoho
        
    Returns:
        {
            "status": "success",
            "moodle_course_id": 123,
            "zoho_updated": true,
            "teacher_enrolled": true,
            "default_users_enrolled": 5,
            "message": "Course created successfully with ID 123"
        }
        
    Raises:
        HTTPException: If course creation fails
    """
    
    try:
        logger.info(f"📚 Creating course in Moodle: {request.class_short_name}")
        
        # Initialize clients
        moodle = MoodleClient()
        zoho = ZohoClient()
        
        # Convert start_date to Unix epoch in GMT+3 (matches Zoho Deluge vStartDate.unixEpoch("GMT+3:00"))
        _tz_plus3 = timezone(timedelta(hours=3))
        start_date_obj = datetime.strptime(request.start_date, "%Y-%m-%d").replace(tzinfo=_tz_plus3)
        start_timestamp = int(start_date_obj.timestamp())
        
        # Step 1: Create course in Moodle
        logger.info(f"1️⃣ Creating course in Moodle...")
        course = moodle.create_course(
            fullname=request.class_name,
            shortname=request.class_short_name,
            category_id=request.category_id,
            start_date=start_timestamp,
            num_sections=request.num_sections
        )
        
        if not course or "id" not in course:
            raise HTTPException(status_code=500, detail="Failed to create course in Moodle")
        
        moodle_course_id = course["id"]
        logger.info(f"✅ Course created in Moodle with ID: {moodle_course_id}")
        
        # Step 2: Update Zoho record with Moodle_Class_ID
        logger.info(f"2️⃣ Updating Zoho BTEC_Classes record...")
        zoho_updated = False
        try:
            await zoho.update_record(
                module='BTEC_Classes',
                record_id=request.zoho_class_id,
                data={"Moodle_Class_ID": str(moodle_course_id)}
            )
            zoho_updated = True
            logger.info(f"✅ Zoho BTEC_Classes updated with Moodle_Class_ID: {moodle_course_id}")
        except Exception as e:
            logger.error(f"❌ Failed to update Zoho: {e}")
            # Continue even if Zoho update fails - course was created
        
        # Step 3: Enroll teacher if provided
        logger.info(f"3️⃣ Enrolling teacher...")
        teacher_enrolled = False
        if request.teacher_zoho_id:
            try:
                # Get teacher from Zoho
                teacher_records = await zoho.search_records(
                    'BTEC_Teachers',
                    f"(id:equals:{request.teacher_zoho_id})"
                )
                
                if teacher_records and len(teacher_records) > 0:
                    teacher = teacher_records[0]
                    teacher_email = teacher.get('Academic_Email', '').lower()
                    
                    # Get Moodle user ID
                    moodle_teacher = moodle.get_user_by_username(teacher_email)
                    
                    if moodle_teacher:
                        moodle.enrol_user(moodle_course_id, moodle_teacher['id'], role_id=3)
                        teacher_enrolled = True
                        logger.info(f"✅ Teacher enrolled: {teacher_email}")
                    else:
                        logger.warning(f"⚠️ Teacher not found in Moodle: {teacher_email}")
                else:
                    logger.warning(f"⚠️ Teacher not found in Zoho: {request.teacher_zoho_id}")
            except Exception as e:
                logger.error(f"❌ Failed to enroll teacher: {e}")
        else:
            logger.info("ℹ️ No teacher specified")
        
        # Step 4: Enroll default users
        logger.info(f"4️⃣ Enrolling default users...")
        default_enrollment = moodle.enrol_default_users(moodle_course_id, request.class_major)
        default_users_enrolled = default_enrollment.get('enrolled', 0)
        logger.info(f"✅ Default users enrolled: {default_users_enrolled}")
        
        # Success!
        return CreateCourseResponse(
            status="success",
            moodle_course_id=moodle_course_id,
            zoho_updated=zoho_updated,
            teacher_enrolled=teacher_enrolled,
            default_users_enrolled=default_users_enrolled,
            message=f"Course created successfully with ID {moodle_course_id}"
        )
    
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"❌ Course creation error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))
