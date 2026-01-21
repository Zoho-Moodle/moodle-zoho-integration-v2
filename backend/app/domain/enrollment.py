from pydantic import BaseModel, field_validator
from typing import Optional
from datetime import datetime, date


class CanonicalEnrollment(BaseModel):
    """Canonical enrollment model - represents Zoho BTEC_Enrollment"""
    
    zoho_id: str  # Enrollment record ID (Name auto number)
    student_zoho_id: str  # Student lookup zoho_id
    student_name: Optional[str] = None  # Student_Name
    class_zoho_id: str  # Class lookup zoho_id
    class_name: Optional[str] = None  # Class_Name
    program_zoho_id: Optional[str] = None  # Enrolled_Program lookup
    start_date: Optional[date] = None  # Start_Date
    moodle_course_id: Optional[str] = None  # Moodle_Course_ID
    status: Optional[str] = None  # Enrollment status
    last_sync_date: Optional[datetime] = None  # Last_Sync_Date
    
    # Will be populated by service after Moodle integration
    moodle_user_id: Optional[int] = None
    moodle_enrollment_id: Optional[int] = None
    
    @field_validator('zoho_id')
    @classmethod
    def validate_zoho_id(cls, v):
        if not v or not str(v).strip():
            raise ValueError('zoho_id is required')
        return str(v).strip()
    
    @field_validator('student_zoho_id')
    @classmethod
    def validate_student_zoho_id(cls, v):
        if not v or not str(v).strip():
            raise ValueError('student_zoho_id is required')
        return str(v).strip()
    
    @field_validator('class_zoho_id')
    @classmethod
    def validate_class_zoho_id(cls, v):
        if not v or not str(v).strip():
            raise ValueError('class_zoho_id is required')
        return str(v).strip()
    
    class Config:
        from_attributes = True
