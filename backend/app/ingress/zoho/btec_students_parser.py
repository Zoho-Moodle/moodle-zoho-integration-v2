"""
BTEC_Students Parser (Additional Student Data from Zoho)

Supplements the base Student data with BTEC-specific fields like profile image.
This is called AFTER the base Students sync, to enrich student records.

Expected Zoho format:
{
  "id": "btec_stud_123",
  "Student": {"id": "stud_456", "name": "Ahmed Mohamed"},  # Link to base student
  "Profile_Image": "https://...",
  "Department": "Business",
  "Student_ID_Number": "2026001",
  "Status": "Active"
}
"""

from typing import Dict, Any, Optional
from app.domain.student import CanonicalStudent


def parse_btec_student_additional(raw_zoho: Dict[str, Any]) -> Dict[str, Any]:
    """
    Parse BTEC_Students module to extract additional student data.
    
    Returns canonical format with additional fields.
    """
    
    # Required field: id
    btec_id = (raw_zoho.get("id") or "").strip()
    if not btec_id:
        raise ValueError("BTEC_Students 'id' is required")

    # Required field: Student lookup (link to base student)
    student_raw = raw_zoho.get("Student")
    if not student_raw:
        raise ValueError("BTEC_Students 'Student' lookup is required")
    
    if isinstance(student_raw, dict):
        student_id = (student_raw.get("id") or "").strip()
        student_name = (student_raw.get("name") or "").strip()
    elif isinstance(student_raw, str):
        student_id = student_raw.strip()
        student_name = None
    else:
        raise ValueError(f"Student must be dict or string, got {type(student_raw)}")
    
    if not student_id:
        raise ValueError("BTEC_Students Student.id is required")

    # Optional fields (enrichment data)
    profile_image = (raw_zoho.get("Profile_Image") or "").strip()
    profile_image = profile_image or None

    department = (raw_zoho.get("Department") or "").strip()
    department = department or None

    student_id_number = (raw_zoho.get("Student_ID_Number") or "").strip()
    student_id_number = student_id_number or None

    status = (raw_zoho.get("Status") or "").strip()
    status = status or None

    return {
        "btec_id": btec_id,
        "student_zoho_id": student_id,
        "student_name": student_name,
        "profile_image": profile_image,
        "department": department,
        "student_id_number": student_id_number,
        "status": status,
    }
