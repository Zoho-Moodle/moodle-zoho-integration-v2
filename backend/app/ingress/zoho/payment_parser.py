"""
BTEC_Payments Parser

Strict parser for Zoho payment payloads.
Expected Zoho format:
{
  "id": "payment_id",
  "Registration": {"id": "reg_123", "name": "..."},
  "Amount": 500.00,
  "Payment_Date": "2026-01-20",
  "Payment_Method": "Credit Card",
  "Payment_Status": "Completed",
  "Description": "Course fees"
}
"""

from typing import Dict, Any, Optional
from datetime import datetime
from app.domain.payment import CanonicalPayment, LookupField


def parse_payment(raw_zoho: Dict[str, Any]) -> CanonicalPayment:
    """
    Parse a raw Zoho payment payload into canonical format.
    
    Raises ValueError if required fields are missing or invalid.
    """
    
    # Required field: id
    zoho_id = (raw_zoho.get("id") or "").strip()
    if not zoho_id:
        raise ValueError("Payment 'id' is required")

    # Required field: Registration (lookup)
    registration_raw = raw_zoho.get("Registration")
    if not registration_raw:
        raise ValueError("Payment 'Registration' lookup is required")
    
    if isinstance(registration_raw, dict):
        reg_id = (registration_raw.get("id") or "").strip()
        reg_name = (registration_raw.get("name") or "").strip()
    elif isinstance(registration_raw, str):
        reg_id = registration_raw.strip()
        reg_name = None
    else:
        raise ValueError(f"Registration must be dict or string, got {type(registration_raw)}")
    
    if not reg_id:
        raise ValueError("Payment Registration.id is required")
    
    registration = LookupField(id=reg_id, name=reg_name or None)

    # Required field: Amount
    amount = raw_zoho.get("Amount")
    if amount is None:
        raise ValueError("Payment 'Amount' is required")
    
    try:
        amount = float(amount)
    except (ValueError, TypeError):
        raise ValueError(f"Payment 'Amount' must be numeric, got {amount}")
    
    if amount < 0:
        raise ValueError(f"Payment 'Amount' cannot be negative")

    # Required field: Payment_Status
    payment_status = (raw_zoho.get("Payment_Status") or "").strip()
    if not payment_status:
        raise ValueError("Payment 'Payment_Status' is required")

    # Optional fields
    payment_date_str = (raw_zoho.get("Payment_Date") or "").strip()
    payment_date = _parse_date(payment_date_str) if payment_date_str else None

    payment_method = (raw_zoho.get("Payment_Method") or "").strip()
    payment_method = payment_method or None

    description = (raw_zoho.get("Description") or "").strip()
    description = description or None

    return CanonicalPayment(
        zoho_id=zoho_id,
        registration=registration,
        amount=amount,
        payment_date=payment_date,
        payment_method=payment_method,
        payment_status=payment_status,
        description=description,
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
