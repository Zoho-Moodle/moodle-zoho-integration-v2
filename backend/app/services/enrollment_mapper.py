from typing import Dict, Any, Optional
from app.domain.enrollment import CanonicalEnrollment


def map_zoho_to_canonical_enrollment(record: Dict[str, Any]) -> Optional[CanonicalEnrollment]:
    """
    Map Zoho enrollment record to CanonicalEnrollment.
    
    Args:
        record: Parsed Zoho enrollment record from parser
    
    Returns:
        CanonicalEnrollment or None if validation fails
    """
    try:
        enrollment = CanonicalEnrollment(
            zoho_id=record.get("zoho_id"),
            student_zoho_id=record.get("student_zoho_id"),
            student_name=record.get("student_name"),
            class_zoho_id=record.get("class_zoho_id"),
            class_name=record.get("class_name"),
            program_zoho_id=record.get("program_zoho_id"),
            start_date=record.get("start_date"),
            moodle_course_id=record.get("moodle_course_id"),
            status=record.get("status"),
            last_sync_date=record.get("last_sync_date")
        )
        return enrollment
    except Exception as e:
        raise ValueError(f"Enrollment mapping error: {str(e)}")
