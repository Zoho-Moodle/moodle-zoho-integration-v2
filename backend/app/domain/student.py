from pydantic import BaseModel, field_validator
from typing import Optional
from datetime import date


class CanonicalStudent(BaseModel):
    zoho_id: str
    userid: Optional[int] = None  # Moodle user id (nullable)

    display_name: Optional[str] = None
    birth_date: Optional[date] = None

    academic_email: str  # Moodle username
    phone: Optional[str] = None
    address: Optional[str] = None
    country: Optional[str] = None
    city: Optional[str] = None
    record_image: Optional[str] = None

    status: Optional[str] = None
    last_sync: Optional[int] = None  # unix timestamp

    @field_validator("zoho_id")
    @classmethod
    def validate_zoho_id(cls, v: str) -> str:
        v = (v or "").strip()
        if not v:
            raise ValueError("zoho_id is required")
        return v

    @field_validator("academic_email")
    @classmethod
    def validate_email(cls, v: str) -> str:
        v = (v or "").strip().lower()
        if not v:
            raise ValueError("academic_email is required")
        # فحص بسيط
        if "@" not in v or "." not in v.split("@")[-1]:
            raise ValueError("academic_email is not valid")
        return v
