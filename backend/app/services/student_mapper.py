from typing import Dict, Any, Optional
from app.domain.student import CanonicalStudent


def map_zoho_to_canonical(record: Dict[str, Any]) -> Optional[CanonicalStudent]:
    """
    Map Zoho raw student record into canonical CanonicalStudent domain model.
    Returns None if record is invalid.
    
    Pydantic validation is applied automatically.
    """
    
    if not record or not record.get("valid"):
        return None

    try:
        # Extract email, with fallback to generated one
        academic_email = record.get("academic_email")
        if not academic_email:
            name = record.get("name", "").strip()
            if name:
                academic_email = f"{name}@abchorizon.com"

        # Create and validate the model (Pydantic handles validation)
        return CanonicalStudent(
            zoho_id=record["zoho_id"],
            academic_email=academic_email,
            display_name=record.get("name"),
            phone=record.get("phone"),
            status=record.get("status"),
        )
    except ValueError as e:
        # Validation failed
        print(f"Validation error in mapper: {e}")
        return None
