from typing import Dict, Any, Optional
from app.domain.class_ import CanonicalClass


def map_zoho_to_canonical_class(record: Dict[str, Any]) -> Optional[CanonicalClass]:
    """
    Map Zoho class record to CanonicalClass.
    
    Args:
        record: Parsed Zoho class record from parser
    
    Returns:
        CanonicalClass or None if validation fails
    """
    try:
        cls = CanonicalClass(
            zoho_id=record.get("zoho_id"),
            name=record.get("name"),
            short_name=record.get("short_name"),
            status=record.get("status"),
            start_date=record.get("start_date"),
            end_date=record.get("end_date"),
            moodle_class_id=record.get("moodle_class_id"),
            ms_teams_id=record.get("ms_teams_id"),
            teacher_zoho_id=record.get("teacher_zoho_id"),
            unit_zoho_id=record.get("unit_zoho_id"),
            program_zoho_id=record.get("program_zoho_id")
        )
        return cls
    except Exception as e:
        raise ValueError(f"Class mapping error: {str(e)}")
