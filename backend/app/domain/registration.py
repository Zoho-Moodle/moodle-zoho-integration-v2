"""
BTEC_Registrations Domain Model (Pydantic)

Registration represents a Student ↔ Program relationship.
Structure:
{
  "id": "reg_123",
  "Student": {"id": "stud_456", "name": "Ahmed Mohamed"},
  "Program": {"id": "prog_789", "name": "Business IT"},
  "Enrollment_Status": "Active" | "Inactive" | "Suspended" | "Completed",
  "Registration_Date": "2026-01-15",
  "Completion_Date": "2027-01-15",
  "Version": 1
}
"""

from pydantic import BaseModel, field_validator
from typing import Optional
from datetime import date


class LookupField(BaseModel):
    """Represents a lookup field: { "id": "...", "name": "..." }"""
    id: str
    name: Optional[str] = None

    @field_validator("id")
    @classmethod
    def validate_id(cls, v: str) -> str:
        v = (v or "").strip()
        if not v:
            raise ValueError("lookup id is required")
        return v


class CanonicalRegistration(BaseModel):
    """Canonical (intermediate) representation of a Zoho registration."""
    
    zoho_id: str
    student: LookupField  # { "id": "stud_...", "name": "Student Name" }
    program: LookupField  # { "id": "prog_...", "name": "Program Name" }
    
    enrollment_status: str  # Active, Inactive, Suspended, Completed, etc.
    registration_date: Optional[date] = None
    completion_date: Optional[date] = None
    
    version: Optional[int] = None  # For change detection
    
    @field_validator("zoho_id")
    @classmethod
    def validate_zoho_id(cls, v: str) -> str:
        v = (v or "").strip()
        if not v:
            raise ValueError("zoho_id is required")
        return v

    @field_validator("enrollment_status")
    @classmethod
    def validate_status(cls, v: str) -> str:
        v = (v or "").strip()
        if not v:
            raise ValueError("enrollment_status is required")
        return v
