"""
Registration Service

Handles sync logic with state machine and fingerprinting.
"""

import hashlib
from typing import Dict, Any, Optional, List
from sqlalchemy.orm import Session
from app.domain.registration import CanonicalRegistration
from app.infra.db.models.registration import Registration
from app.infra.db.models.student import Student
from app.infra.db.models.program import Program
from app.services.registration_mapper import map_registration_to_db


def compute_fingerprint(reg: CanonicalRegistration) -> str:
    """
    Compute fingerprint for change detection.
    Only includes fields that matter for sync decisions.
    """
    parts = [
        reg.student.id or "",
        reg.program.id or "",
        reg.enrollment_status or "",
        reg.registration_date.isoformat() if reg.registration_date else "",
        reg.completion_date.isoformat() if reg.completion_date else "",
    ]
    raw = "|".join([p.strip().lower() for p in parts])
    return hashlib.sha256(raw.encode("utf-8")).hexdigest()


class RegistrationService:
    def __init__(self, db: Session):
        self.db = db

    def sync_registration(self, registration: CanonicalRegistration, tenant_id: str) -> Dict[str, Any]:
        """
        Sync a single registration. Returns decision about what happened.
        
        States:
        - NEW: Registration created
        - UNCHANGED: Registration already exists with same data
        - UPDATED: Registration data changed and updated
        - INVALID: Registration data failed validation or dependencies missing
        - SKIPPED: Registration skipped (e.g., dependencies not found)
        """
        
        # Validate dependencies exist
        student_check = self.db.query(Student).filter(
            Student.zoho_id == registration.student.id,
            Student.tenant_id == tenant_id
        ).first()
        
        if not student_check:
            return {
                "zoho_registration_id": registration.zoho_id,
                "status": "INVALID",
                "message": f"Student {registration.student.id} not found. Create student first."
            }
        
        program_check = self.db.query(Program).filter(
            Program.zoho_id == registration.program.id,
            Program.tenant_id == tenant_id
        ).first()
        
        if not program_check:
            return {
                "zoho_registration_id": registration.zoho_id,
                "status": "INVALID",
                "message": f"Program {registration.program.id} not found. Create program first."
            }

        fp = compute_fingerprint(registration)
        
        # Query for existing registration
        existing: Optional[Registration] = (
            self.db.query(Registration)
            .filter(
                Registration.zoho_id == registration.zoho_id,
                Registration.tenant_id == tenant_id
            )
            .first()
        )

        # ============ NEW REGISTRATION ============
        if existing is None:
            db_reg = map_registration_to_db(registration, tenant_id)
            db_reg.fingerprint = fp
            db_reg.sync_status = "synced"
            self.db.add(db_reg)
            self.db.commit()
            
            return {
                "zoho_registration_id": registration.zoho_id,
                "status": "NEW",
                "message": "Registration created"
            }

        # ============ EXISTING REGISTRATION ============
        # Check if unchanged
        if existing.fingerprint == fp:
            return {
                "zoho_registration_id": registration.zoho_id,
                "status": "UNCHANGED",
                "message": "No changes detected"
            }

        # ============ UPDATED REGISTRATION ============
        changed = {}

        if existing.enrollment_status != registration.enrollment_status:
            changed["enrollment_status"] = (existing.enrollment_status, registration.enrollment_status)
            existing.enrollment_status = registration.enrollment_status

        reg_date_str = registration.registration_date.isoformat() if registration.registration_date else None
        if existing.registration_date != reg_date_str:
            changed["registration_date"] = (existing.registration_date, reg_date_str)
            existing.registration_date = reg_date_str

        comp_date_str = registration.completion_date.isoformat() if registration.completion_date else None
        if existing.completion_date != comp_date_str:
            changed["completion_date"] = (existing.completion_date, comp_date_str)
            existing.completion_date = comp_date_str

        existing.fingerprint = fp
        existing.sync_status = "synced"
        self.db.commit()

        return {
            "zoho_registration_id": registration.zoho_id,
            "status": "UPDATED",
            "message": "Registration updated",
            "changed_fields": changed
        }

    def sync_batch(self, registrations: List[CanonicalRegistration], tenant_id: str) -> Dict[str, Any]:
        """
        Sync multiple registrations and return summary.
        """
        results = {
            "total": len(registrations),
            "new": 0,
            "unchanged": 0,
            "updated": 0,
            "invalid": 0,
            "records": []
        }

        for reg in registrations:
            try:
                result = self.sync_registration(reg, tenant_id)
                results["records"].append(result)
                
                status = result.get("status", "")
                if status == "NEW":
                    results["new"] += 1
                elif status == "UNCHANGED":
                    results["unchanged"] += 1
                elif status == "UPDATED":
                    results["updated"] += 1
                elif status in ("INVALID", "SKIPPED"):
                    results["invalid"] += 1
                    
            except Exception as e:
                results["records"].append({
                    "zoho_registration_id": reg.zoho_id,
                    "status": "ERROR",
                    "message": str(e)
                })
                results["invalid"] += 1

        return results
