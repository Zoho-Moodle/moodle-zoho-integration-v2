import hashlib
from typing import Any, Dict, Optional
from sqlalchemy.orm import Session
from app.domain.program import CanonicalProgram
from app.infra.db.models.program import Program


def compute_program_fingerprint(p: CanonicalProgram) -> str:
    """Compute fingerprint for change detection"""
    parts = [
        p.name or "",
        str(p.price or ""),
        p.moodle_id or "",
        p.status or "",
    ]
    raw = "|".join([part.strip().lower() for part in parts])
    return hashlib.sha256(raw.encode("utf-8")).hexdigest()


class ProgramService:
    def __init__(self, db: Session):
        self.db = db

    def sync_program(self, program: CanonicalProgram, tenant_id: str = "default") -> Dict[str, Any]:
        """
        Sync a single program. Returns decision about what happened.
        
        States:
        - NEW: Program created
        - UNCHANGED: Program already exists with same data
        - UPDATED: Program data changed and updated
        - INVALID: Program data failed validation
        """
        
        if not program.zoho_id or not program.name:
            return {
                "zoho_program_id": program.zoho_id or "unknown",
                "status": "INVALID",
                "message": "Missing zoho_id or name"
            }

        fp = compute_program_fingerprint(program)
        
        # Query for existing program
        existing: Optional[Program] = (
            self.db.query(Program)
            .filter(Program.tenant_id == tenant_id)
            .filter(Program.zoho_id == program.zoho_id)
            .first()
        )

        # ============ NEW PROGRAM ============
        if existing is None:
            row = Program(
                tenant_id=tenant_id,
                source="zoho",
                zoho_id=program.zoho_id,
                name=program.name,
                price=program.price,
                moodle_id=program.moodle_id,
                status=program.status,
                fingerprint=fp,
            )
            self.db.add(row)
            self.db.commit()
            
            return {
                "zoho_program_id": program.zoho_id,
                "status": "NEW",
                "message": "Program created"
            }

        # ============ EXISTING PROGRAM ============
        # Check if unchanged
        if existing.fingerprint == fp:
            return {
                "zoho_program_id": program.zoho_id,
                "status": "UNCHANGED",
                "message": "No changes detected"
            }

        # ============ UPDATED PROGRAM ============
        changed = {}

        if existing.name != program.name:
            changed["name"] = (existing.name, program.name)
            existing.name = program.name

        if existing.price != program.price:
            changed["price"] = (existing.price, program.price)
            existing.price = program.price

        if existing.moodle_id != program.moodle_id:
            changed["moodle_id"] = (existing.moodle_id, program.moodle_id)
            existing.moodle_id = program.moodle_id

        if existing.status != program.status:
            changed["status"] = (existing.status, program.status)
            existing.status = program.status

        if changed:
            existing.fingerprint = fp
            self.db.commit()
            
            return {
                "zoho_program_id": program.zoho_id,
                "status": "UPDATED",
                "message": "Program updated",
                "changes": changed
            }

        return {
            "zoho_program_id": program.zoho_id,
            "status": "UNCHANGED",
            "message": "No changes detected"
        }
