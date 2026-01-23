"""
Grade Service

Handles sync logic with state machine and fingerprinting.
"""

import hashlib
from typing import Dict, Any, Optional, List
from sqlalchemy.orm import Session
from app.domain.grade import CanonicalGrade
from app.infra.db.models.grade import Grade
from app.infra.db.models.student import Student
from app.infra.db.models.unit import Unit
from app.services.grade_mapper import map_grade_to_db


def compute_fingerprint(grade: CanonicalGrade) -> str:
    """
    Compute fingerprint for change detection.
    Only includes fields that matter for sync decisions.
    """
    parts = [
        grade.student.id or "",
        grade.unit.id or "",
        grade.grade_value or "",
        str(grade.score) if grade.score is not None else "",
    ]
    raw = "|".join([p.strip().lower() for p in parts])
    return hashlib.sha256(raw.encode("utf-8")).hexdigest()


class GradeService:
    def __init__(self, db: Session):
        self.db = db

    def sync_grade(self, grade: CanonicalGrade, tenant_id: str) -> Dict[str, Any]:
        """
        Sync a single grade. Returns decision about what happened.
        
        States:
        - NEW: Grade created
        - UNCHANGED: Grade already exists with same data
        - UPDATED: Grade data changed and updated
        - INVALID: Grade data failed validation or dependencies missing
        """
        
        # Validate dependencies exist
        student_check = self.db.query(Student).filter(
            Student.zoho_id == grade.student.id,
            Student.tenant_id == tenant_id
        ).first()
        
        if not student_check:
            return {
                "zoho_grade_id": grade.zoho_id,
                "status": "INVALID",
                "message": f"Student {grade.student.id} not found. Create student first."
            }
        
        unit_check = self.db.query(Unit).filter(
            Unit.zoho_id == grade.unit.id,
            Unit.tenant_id == tenant_id
        ).first()
        
        if not unit_check:
            return {
                "zoho_grade_id": grade.zoho_id,
                "status": "INVALID",
                "message": f"Unit {grade.unit.id} not found. Create unit first."
            }

        fp = compute_fingerprint(grade)
        
        # Query for existing grade
        existing: Optional[Grade] = (
            self.db.query(Grade)
            .filter(
                Grade.zoho_id == grade.zoho_id,
                Grade.tenant_id == tenant_id
            )
            .first()
        )

        # ============ NEW GRADE ============
        if existing is None:
            db_grade = map_grade_to_db(grade, tenant_id)
            db_grade.fingerprint = fp
            db_grade.sync_status = "synced"
            self.db.add(db_grade)
            self.db.commit()
            
            return {
                "zoho_grade_id": grade.zoho_id,
                "status": "NEW",
                "message": "Grade created"
            }

        # ============ EXISTING GRADE ============
        # Check if unchanged
        if existing.fingerprint == fp:
            return {
                "zoho_grade_id": grade.zoho_id,
                "status": "UNCHANGED",
                "message": "No changes detected"
            }

        # ============ UPDATED GRADE ============
        changed = {}

        if existing.grade_value != grade.grade_value:
            changed["grade_value"] = (existing.grade_value, grade.grade_value)
            existing.grade_value = grade.grade_value

        if existing.score != grade.score:
            changed["score"] = (existing.score, grade.score)
            existing.score = grade.score

        grade_date_str = grade.grade_date.isoformat() if grade.grade_date else None
        if existing.grade_date != grade_date_str:
            changed["grade_date"] = (existing.grade_date, grade_date_str)
            existing.grade_date = grade_date_str

        if existing.comments != grade.comments:
            changed["comments"] = (existing.comments, grade.comments)
            existing.comments = grade.comments

        existing.fingerprint = fp
        existing.sync_status = "synced"
        self.db.commit()

        return {
            "zoho_grade_id": grade.zoho_id,
            "status": "UPDATED",
            "message": "Grade updated",
            "changed_fields": changed
        }

    def sync_batch(self, grades: List[CanonicalGrade], tenant_id: str) -> Dict[str, Any]:
        """
        Sync multiple grades and return summary.
        """
        results = {
            "total": len(grades),
            "new": 0,
            "unchanged": 0,
            "updated": 0,
            "invalid": 0,
            "records": []
        }

        for grade in grades:
            try:
                result = self.sync_grade(grade, tenant_id)
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
                    "zoho_grade_id": grade.zoho_id,
                    "status": "ERROR",
                    "message": str(e)
                })
                results["invalid"] += 1

        return results
