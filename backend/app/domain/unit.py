"""
BTEC (Units) Domain Model (Pydantic)

Unit represents a course unit/module.
Structure:
{
  "id": "unit_123",
  "Unit_Code": "UNIT001",
  "Unit_Name": "Introduction to Business",
  "Description": "...",
  "Credit_Hours": 30,
  "Level": "L3",
  "Status": "Active" | "Inactive"
}
"""

from pydantic import BaseModel, field_validator
from typing import Optional


class CanonicalUnit(BaseModel):
    """Canonical representation of a Zoho unit/course module."""
    
    zoho_id: str
    unit_code: str  # e.g., "UNIT001"
    unit_name: str
    
    description: Optional[str] = None
    credit_hours: Optional[float] = None
    level: Optional[str] = None  # e.g., "L3", "L4"
    status: str  # Active, Inactive, etc.
    
    @field_validator("zoho_id")
    @classmethod
    def validate_zoho_id(cls, v: str) -> str:
        v = (v or "").strip()
        if not v:
            raise ValueError("zoho_id is required")
        return v

    @field_validator("unit_code")
    @classmethod
    def validate_code(cls, v: str) -> str:
        v = (v or "").strip()
        if not v:
            raise ValueError("unit_code is required")
        return v

    @field_validator("unit_name")
    @classmethod
    def validate_name(cls, v: str) -> str:
        v = (v or "").strip()
        if not v:
            raise ValueError("unit_name is required")
        return v

    @field_validator("status")
    @classmethod
    def validate_status(cls, v: str) -> str:
        v = (v or "").strip()
        if not v:
            raise ValueError("status is required")
        return v
