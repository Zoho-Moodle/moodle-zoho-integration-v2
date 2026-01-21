from pydantic import BaseModel, field_validator
from typing import Optional
from datetime import datetime, date


class CanonicalProgram(BaseModel):
    """Canonical program model - represents Zoho Product"""
    
    zoho_id: str  # Product_ID in Zoho
    name: str  # Product_Name
    price: Optional[float] = None
    moodle_id: Optional[str] = None  # MoodleID
    status: Optional[str] = None  # Active/Inactive
    
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
