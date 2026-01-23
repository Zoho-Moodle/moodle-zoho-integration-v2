"""
BTEC_Registrations Parser

Strict parser for Zoho registration payloads.
Expected Zoho format:
{
  "id": "registration_id",
  "Student": {"id": "stud_123", "name": "Student Name"},
  "Program": {"id": "prog_456", "name": "Program Name"},
  "Enrollment_Status": "Active",
  "Registration_Date": "2026-01-15",
  "Completion_Date": "2027-01-15",
  "Version": 1
}
"""

from typing import Dict, Any, Optional
from datetime import datetime
from app.domain.registration import CanonicalRegistration, LookupField


def parse_registration(raw_zoho: Dict[str, Any]) -> CanonicalRegistration:
    """
    Parse a raw Zoho registration payload into canonical format.
    
    Raises ValueError if required fields are missing or invalid.
    """
    
    # Required field: id
    zoho_id = (raw_zoho.get("id") or "").strip()
    if not zoho_id:
        raise ValueError("Registration 'id' is required")

    # Required field: Student (lookup)
    student_raw = raw_zoho.get("Student")
    if not student_raw:
        raise ValueError("Registration 'Student' lookup is required")
    
    if isinstance(student_raw, dict):
        student_id = (student_raw.get("id") or "").strip()
        student_name = (student_raw.get("name") or "").strip()
    elif isinstance(student_raw, str):
        # Fallback: if Student is just a string ID
        student_id = student_raw.strip()
        student_name = None
    else:
        raise ValueError(f"Student must be dict or string, got {type(student_raw)}")
    
    if not student_id:
        raise ValueError("Registration Student.id is required")
    
    student = LookupField(id=student_id, name=student_name or None)

    # Required field: Program (lookup)
    program_raw = raw_zoho.get("Program")
    if not program_raw:
        raise ValueError("Registration 'Program' lookup is required")
    
    if isinstance(program_raw, dict):
        program_id = (program_raw.get("id") or "").strip()
        program_name = (program_raw.get("name") or "").strip()
    elif isinstance(program_raw, str):
        program_id = program_raw.strip()
        program_name = None
    else:
        raise ValueError(f"Program must be dict or string, got {type(program_raw)}")
    
    if not program_id:
        raise ValueError("Registration Program.id is required")
    
    program = LookupField(id=program_id, name=program_name or None)

    # Required field: Enrollment_Status
    enrollment_status = (raw_zoho.get("Enrollment_Status") or "").strip()
    if not enrollment_status:
        raise ValueError("Registration 'Enrollment_Status' is required")

    # Optional fields
    registration_date_str = (raw_zoho.get("Registration_Date") or "").strip()
    registration_date = _parse_date(registration_date_str) if registration_date_str else None

    completion_date_str = (raw_zoho.get("Completion_Date") or "").strip()
    completion_date = _parse_date(completion_date_str) if completion_date_str else None

    version = raw_zoho.get("Version")

    return CanonicalRegistration(
        zoho_id=zoho_id,
        student=student,
        program=program,
        enrollment_status=enrollment_status,
        registration_date=registration_date,
        completion_date=completion_date,
        version=version,
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
