"""
BTEC_Grades Domain Model (Pydantic)

Grade represents a student's grade for a unit/class.
Structure:
{
  "id": "grade_123",
  "Student": {"id": "stud_456", "name": "Ahmed Mohamed"},
  "Unit": {"id": "unit_789", "name": "Unit001"},
  "Grade_Value": "A",
  "Score": 95,
  "Grade_Date": "2026-06-15",
  "Comments": "Excellent performance"
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


class CanonicalGrade(BaseModel):
    """Canonical representation of a Zoho grade."""
    
    zoho_id: str
    student: LookupField  # { "id": "stud_...", "name": "Student Name" }
    unit: LookupField     # { "id": "unit_...", "name": "Unit Code" }
    
    grade_value: str  # A, B, C, D, F, etc.
    score: Optional[float] = None  # numeric score if available
    grade_date: Optional[date] = None
    comments: Optional[str] = None
    
    @field_validator("zoho_id")
    @classmethod
    def validate_zoho_id(cls, v: str) -> str:
        v = (v or "").strip()
        if not v:
            raise ValueError("zoho_id is required")
        return v

    @field_validator("grade_value")
    @classmethod
    def validate_grade(cls, v: str) -> str:
        v = (v or "").strip()
        if not v:
            raise ValueError("grade_value is required")
        return v

    @field_validator("score")
    @classmethod
    def validate_score(cls, v: Optional[float]) -> Optional[float]:
        if v is not None and (v < 0 or v > 100):
            raise ValueError("score must be between 0 and 100")
        return v
