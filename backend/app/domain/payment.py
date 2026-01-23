"""
BTEC_Payments Domain Model (Pydantic)

Payment represents a transaction linked to a Registration.
Structure:
{
  "id": "pay_123",
  "Registration": {"id": "reg_456", "name": "..."},
  "Amount": 500.00,
  "Payment_Date": "2026-01-20",
  "Payment_Method": "Credit Card",
  "Payment_Status": "Completed" | "Pending" | "Failed",
  "Description": "Course fees"
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


class CanonicalPayment(BaseModel):
    """Canonical representation of a Zoho payment."""
    
    zoho_id: str
    registration: LookupField  # { "id": "reg_...", "name": "Registration ID" }
    
    amount: float
    payment_date: Optional[date] = None
    payment_method: Optional[str] = None
    payment_status: str  # Completed, Pending, Failed, etc.
    description: Optional[str] = None
    
    @field_validator("zoho_id")
    @classmethod
    def validate_zoho_id(cls, v: str) -> str:
        v = (v or "").strip()
        if not v:
            raise ValueError("zoho_id is required")
        return v

    @field_validator("amount")
    @classmethod
    def validate_amount(cls, v: float) -> float:
        if v < 0:
            raise ValueError("amount cannot be negative")
        return v

    @field_validator("payment_status")
    @classmethod
    def validate_status(cls, v: str) -> str:
        v = (v or "").strip()
        if not v:
            raise ValueError("payment_status is required")
        return v
