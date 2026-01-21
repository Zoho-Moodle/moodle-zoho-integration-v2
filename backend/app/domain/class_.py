from pydantic import BaseModel, field_validator
from typing import Optional
from datetime import date


class CanonicalClass(BaseModel):
    """Canonical class model - represents Zoho BTEC_Class"""
    
    zoho_id: str  # Class record ID
    name: str  # Class_Name
    short_name: Optional[str] = None  # Class_Short_Name
    status: Optional[str] = None  # Class_Status
    start_date: Optional[date] = None  # Start_Date
    end_date: Optional[date] = None  # End_Date
    moodle_class_id: Optional[str] = None  # Moodle_Class_ID
    ms_teams_id: Optional[str] = None  # MS_Teams_ID
    teacher_zoho_id: Optional[str] = None  # Teacher lookup zoho_id
    unit_zoho_id: Optional[str] = None  # Unit lookup zoho_id
    program_zoho_id: Optional[str] = None  # BTEC_Program lookup zoho_id
    
    @field_validator('zoho_id')
    @classmethod
    def validate_zoho_id(cls, v):
        if not v or not str(v).strip():
            raise ValueError('zoho_id is required')
        return str(v).strip()
    
    @field_validator('name')
    @classmethod
    def validate_name(cls, v):
        if not v or not str(v).strip():
            raise ValueError('name is required')
        return str(v).strip()
    
    class Config:
        from_attributes = True
