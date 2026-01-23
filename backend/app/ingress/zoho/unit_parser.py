"""
BTEC (Units) Parser

Strict parser for Zoho unit payloads.
Expected Zoho format:
{
  "id": "unit_id",
  "Unit_Code": "UNIT001",
  "Unit_Name": "Introduction to Business",
  "Description": "...",
  "Credit_Hours": 30,
  "Level": "L3",
  "Status": "Active"
}
"""

from typing import Dict, Any
from app.domain.unit import CanonicalUnit


def parse_unit(raw_zoho: Dict[str, Any]) -> CanonicalUnit:
    """
    Parse a raw Zoho unit payload into canonical format.
    
    Raises ValueError if required fields are missing or invalid.
    """
    
    # Required field: id
    zoho_id = (raw_zoho.get("id") or "").strip()
    if not zoho_id:
        raise ValueError("Unit 'id' is required")

    # Required field: Unit_Code
    unit_code = (raw_zoho.get("Unit_Code") or "").strip()
    if not unit_code:
        raise ValueError("Unit 'Unit_Code' is required")

    # Required field: Unit_Name
    unit_name = (raw_zoho.get("Unit_Name") or "").strip()
    if not unit_name:
        raise ValueError("Unit 'Unit_Name' is required")

    # Required field: Status
    status = (raw_zoho.get("Status") or "").strip()
    if not status:
        raise ValueError("Unit 'Status' is required")

    # Optional fields
    description = (raw_zoho.get("Description") or "").strip()
    description = description or None

    credit_hours = raw_zoho.get("Credit_Hours")
    if credit_hours is not None:
        try:
            credit_hours = float(credit_hours)
        except (ValueError, TypeError):
            raise ValueError(f"Unit 'Credit_Hours' must be numeric, got {credit_hours}")
    else:
        credit_hours = None

    level = (raw_zoho.get("Level") or "").strip()
    level = level or None

    return CanonicalUnit(
        zoho_id=zoho_id,
        unit_code=unit_code,
        unit_name=unit_name,
        description=description,
        credit_hours=credit_hours,
        level=level,
        status=status,
    )
