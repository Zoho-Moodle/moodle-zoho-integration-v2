from typing import Dict, Any, Optional
from app.domain.program import CanonicalProgram


def map_zoho_to_canonical_program(record: Dict[str, Any]) -> Optional[CanonicalProgram]:
    """
    Map Zoho program record to CanonicalProgram.
    
    Args:
        record: Parsed Zoho program record from parser
    
    Returns:
        CanonicalProgram or None if validation fails
    """
    try:
        program = CanonicalProgram(
            zoho_id=record.get("zoho_id"),
            name=record.get("name"),
            price=record.get("price"),
            moodle_id=record.get("moodle_id"),
            status=record.get("status")
        )
        return program
    except Exception as e:
        raise ValueError(f"Program mapping error: {str(e)}")
