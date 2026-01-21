import hashlib
from typing import Any, Dict, Optional
from sqlalchemy.orm import Session
from app.domain.class_ import CanonicalClass
from app.infra.db.models.class_ import Class


def compute_class_fingerprint(c: CanonicalClass) -> str:
    """Compute fingerprint for change detection"""
    parts = [
        c.name or "",
        c.short_name or "",
        str(c.start_date or ""),
        str(c.end_date or ""),
        c.status or "",
        c.program_zoho_id or "",
    ]
    raw = "|".join([part.strip().lower() for part in parts])
    return hashlib.sha256(raw.encode("utf-8")).hexdigest()


class ClassService:
    def __init__(self, db: Session):
        self.db = db

    def sync_class(self, cls: CanonicalClass, tenant_id: str = "default") -> Dict[str, Any]:
        """
        Sync a single class. Returns decision about what happened.
        
        States:
        - NEW: Class created
        - UNCHANGED: Class already exists with same data
        - UPDATED: Class data changed and updated
        - INVALID: Class data failed validation
        """
        
        if not cls.zoho_id or not cls.name:
            return {
                "zoho_class_id": cls.zoho_id or "unknown",
                "status": "INVALID",
                "message": "Missing zoho_id or name"
            }

        fp = compute_class_fingerprint(cls)
        
        # Query for existing class
        existing: Optional[Class] = (
            self.db.query(Class)
            .filter(Class.tenant_id == tenant_id)
            .filter(Class.zoho_id == cls.zoho_id)
            .first()
        )

        # ============ NEW CLASS ============
        if existing is None:
            row = Class(
                tenant_id=tenant_id,
                source="zoho",
                zoho_id=cls.zoho_id,
                name=cls.name,
                short_name=cls.short_name,
                status=cls.status,
                start_date=cls.start_date,
                end_date=cls.end_date,
                moodle_class_id=cls.moodle_class_id,
                ms_teams_id=cls.ms_teams_id,
                teacher_zoho_id=cls.teacher_zoho_id,
                unit_zoho_id=cls.unit_zoho_id,
                program_zoho_id=cls.program_zoho_id,
                fingerprint=fp,
            )
            self.db.add(row)
            self.db.commit()
            
            return {
                "zoho_class_id": cls.zoho_id,
                "status": "NEW",
                "message": "Class created"
            }

        # ============ EXISTING CLASS ============
        # Check if unchanged
        if existing.fingerprint == fp:
            return {
                "zoho_class_id": cls.zoho_id,
                "status": "UNCHANGED",
                "message": "No changes detected"
            }

        # ============ UPDATED CLASS ============
        changed = {}

        if existing.name != cls.name:
            changed["name"] = (existing.name, cls.name)
            existing.name = cls.name

        if existing.short_name != cls.short_name:
            changed["short_name"] = (existing.short_name, cls.short_name)
            existing.short_name = cls.short_name

        if existing.start_date != cls.start_date:
            changed["start_date"] = (str(existing.start_date), str(cls.start_date))
            existing.start_date = cls.start_date

        if existing.end_date != cls.end_date:
            changed["end_date"] = (str(existing.end_date), str(cls.end_date))
            existing.end_date = cls.end_date

        if existing.status != cls.status:
            changed["status"] = (existing.status, cls.status)
            existing.status = cls.status

        if existing.program_zoho_id != cls.program_zoho_id:
            changed["program_zoho_id"] = (existing.program_zoho_id, cls.program_zoho_id)
            existing.program_zoho_id = cls.program_zoho_id

        if changed:
            existing.fingerprint = fp
            self.db.commit()
            
            return {
                "zoho_class_id": cls.zoho_id,
                "status": "UPDATED",
                "message": "Class updated",
                "changes": changed
            }

        return {
            "zoho_class_id": cls.zoho_id,
            "status": "UNCHANGED",
            "message": "No changes detected"
        }
