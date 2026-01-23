"""
Unit Service

Handles sync logic with state machine and fingerprinting.
"""

import hashlib
from typing import Dict, Any, Optional, List
from sqlalchemy.orm import Session
from app.domain.unit import CanonicalUnit
from app.infra.db.models.unit import Unit
from app.services.unit_mapper import map_unit_to_db


def compute_fingerprint(unit: CanonicalUnit) -> str:
    """
    Compute fingerprint for change detection.
    Only includes fields that matter for sync decisions.
    """
    parts = [
        unit.unit_code or "",
        unit.unit_name or "",
        unit.status or "",
        str(unit.credit_hours) if unit.credit_hours else "",
    ]
    raw = "|".join([p.strip().lower() for p in parts])
    return hashlib.sha256(raw.encode("utf-8")).hexdigest()


class UnitService:
    def __init__(self, db: Session):
        self.db = db

    def sync_unit(self, unit: CanonicalUnit, tenant_id: str) -> Dict[str, Any]:
        """
        Sync a single unit. Returns decision about what happened.
        
        States:
        - NEW: Unit created
        - UNCHANGED: Unit already exists with same data
        - UPDATED: Unit data changed and updated
        - INVALID: Unit data failed validation
        """
        
        fp = compute_fingerprint(unit)
        
        # Query for existing unit
        existing: Optional[Unit] = (
            self.db.query(Unit)
            .filter(
                Unit.zoho_id == unit.zoho_id,
                Unit.tenant_id == tenant_id
            )
            .first()
        )

        # ============ NEW UNIT ============
        if existing is None:
            db_unit = map_unit_to_db(unit, tenant_id)
            db_unit.fingerprint = fp
            db_unit.sync_status = "synced"
            self.db.add(db_unit)
            self.db.commit()
            
            return {
                "zoho_unit_id": unit.zoho_id,
                "status": "NEW",
                "message": "Unit created"
            }

        # ============ EXISTING UNIT ============
        # Check if unchanged
        if existing.fingerprint == fp:
            return {
                "zoho_unit_id": unit.zoho_id,
                "status": "UNCHANGED",
                "message": "No changes detected"
            }

        # ============ UPDATED UNIT ============
        changed = {}

        if existing.unit_name != unit.unit_name:
            changed["unit_name"] = (existing.unit_name, unit.unit_name)
            existing.unit_name = unit.unit_name

        if existing.status != unit.status:
            changed["status"] = (existing.status, unit.status)
            existing.status = unit.status

        if existing.credit_hours != unit.credit_hours:
            changed["credit_hours"] = (existing.credit_hours, unit.credit_hours)
            existing.credit_hours = unit.credit_hours

        if existing.level != unit.level:
            changed["level"] = (existing.level, unit.level)
            existing.level = unit.level

        if existing.description != unit.description:
            changed["description"] = (existing.description, unit.description)
            existing.description = unit.description

        existing.fingerprint = fp
        existing.sync_status = "synced"
        self.db.commit()

        return {
            "zoho_unit_id": unit.zoho_id,
            "status": "UPDATED",
            "message": "Unit updated",
            "changed_fields": changed
        }

    def sync_batch(self, units: List[CanonicalUnit], tenant_id: str) -> Dict[str, Any]:
        """
        Sync multiple units and return summary.
        """
        results = {
            "total": len(units),
            "new": 0,
            "unchanged": 0,
            "updated": 0,
            "invalid": 0,
            "records": []
        }

        for unit in units:
            try:
                result = self.sync_unit(unit, tenant_id)
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
                    "zoho_unit_id": unit.zoho_id,
                    "status": "ERROR",
                    "message": str(e)
                })
                results["invalid"] += 1

        return results
