"""
BTEC_Grades Parser

Strict parser for Zoho grade payloads.
Expected Zoho format:
{
  "id": "grade_id",
  "Student": {"id": "stud_123", "name": "Student Name"},
  "Unit": {"id": "unit_456", "name": "Unit Code"},
  "Grade_Value": "A",
  "Score": 95,
  "Grade_Date": "2026-06-15",
  "Comments": "Excellent performance"
}
"""

from typing import Dict, Any, Optional
from datetime import datetime
from app.domain.grade import CanonicalGrade, LookupField


def parse_grade(raw_zoho: Dict[str, Any]) -> CanonicalGrade:
    """
    Parse a raw Zoho grade payload into canonical format.
    
    Raises ValueError if required fields are missing or invalid.
    """
    
    # Required field: id
    zoho_id = (raw_zoho.get("id") or "").strip()
    if not zoho_id:
        raise ValueError("Grade 'id' is required")

    # Required field: Student (lookup)
    student_raw = raw_zoho.get("Student")
    if not student_raw:
        raise ValueError("Grade 'Student' lookup is required")
    
    if isinstance(student_raw, dict):
        student_id = (student_raw.get("id") or "").strip()
        student_name = (student_raw.get("name") or "").strip()
    elif isinstance(student_raw, str):
        student_id = student_raw.strip()
        student_name = None
    else:
        raise ValueError(f"Student must be dict or string, got {type(student_raw)}")
    
    if not student_id:
        raise ValueError("Grade Student.id is required")
    
    student = LookupField(id=student_id, name=student_name or None)

    # Required field: Unit (lookup)
    unit_raw = raw_zoho.get("Unit")
    if not unit_raw:
        raise ValueError("Grade 'Unit' lookup is required")
    
    if isinstance(unit_raw, dict):
        unit_id = (unit_raw.get("id") or "").strip()
        unit_name = (unit_raw.get("name") or "").strip()
    elif isinstance(unit_raw, str):
        unit_id = unit_raw.strip()
        unit_name = None
    else:
        raise ValueError(f"Unit must be dict or string, got {type(unit_raw)}")
    
    if not unit_id:
        raise ValueError("Grade Unit.id is required")
    
    unit = LookupField(id=unit_id, name=unit_name or None)

    # Required field: Grade_Value
    grade_value = (raw_zoho.get("Grade_Value") or "").strip()
    if not grade_value:
        raise ValueError("Grade 'Grade_Value' is required")

    # Optional fields
    score = raw_zoho.get("Score")
    if score is not None:
        try:
            score = float(score)
            if score < 0 or score > 100:
                raise ValueError(f"Score must be between 0 and 100, got {score}")
        except (ValueError, TypeError):
            raise ValueError(f"Grade 'Score' must be numeric, got {score}")
    else:
        score = None

    grade_date_str = (raw_zoho.get("Grade_Date") or "").strip()
    grade_date = _parse_date(grade_date_str) if grade_date_str else None

    comments = (raw_zoho.get("Comments") or "").strip()
    comments = comments or None

    return CanonicalGrade(
        zoho_id=zoho_id,
        student=student,
        unit=unit,
        grade_value=grade_value,
        score=score,
        grade_date=grade_date,
        comments=comments,
    )


def _parse_date(date_str: str):
    """Parse date string safely. Accepts YYYY-MM-DD format."""
    if not date_str:
        return None
    date_str = date_str.strip()
    try:
        return datetime.strptime(date_str, "%Y-%m-%d").date()
    except ValueError:
        raise ValueError(f"Invalid date format: {date_str}. Expected YYYY-MM-DD")
