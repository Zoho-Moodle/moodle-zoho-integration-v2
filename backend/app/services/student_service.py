import hashlib
import time
from typing import Any, Dict, Optional
from sqlalchemy.orm import Session
from app.domain.student import CanonicalStudent
from app.infra.db.models.student import Student


def compute_fingerprint(s: CanonicalStudent) -> str:
    """
    Compute fingerprint for change detection.
    Only includes fields that matter for sync decisions.
    """
    parts = [
        s.academic_email or "",
        s.display_name or "",
        s.phone or "",
        s.status or "",
    ]
    raw = "|".join([p.strip().lower() for p in parts])
    return hashlib.sha256(raw.encode("utf-8")).hexdigest()


class StudentService:
    def __init__(self, db: Session):
        self.db = db

    def sync_student(self, student: CanonicalStudent) -> Dict[str, Any]:
        """
        Sync a single student. Returns decision about what happened.
        
        States:
        - NEW: Student created
        - UNCHANGED: Student already exists with same data
        - UPDATED: Student data changed and updated
        - INVALID: Student data failed validation
        """
        
        # Validation should have been done by Pydantic already
        if not student.zoho_id or not student.academic_email:
            return {
                "zoho_student_id": student.zoho_id or "unknown",
                "status": "INVALID",
                "message": "missing zoho_id or academic_email"
            }

        fp = compute_fingerprint(student)
        
        # Query for existing student
        existing: Optional[Student] = (
            self.db.query(Student)
            .filter(Student.zoho_id == student.zoho_id)
            .first()
        )

        # ============ NEW STUDENT ============
        if existing is None:
            row = Student(
                tenant_id="default",  # Required field
                source="zoho",  # Track source
                zoho_id=student.zoho_id,
                academic_email=student.academic_email,
                username=student.academic_email,  # Use email as username
                display_name=student.display_name,
                phone=student.phone,
                status=student.status,
                moodle_userid=student.userid,
                fingerprint=fp,
                sync_status="synced",
                last_sync=None,
            )
            self.db.add(row)
            self.db.commit()
            
            return {
                "zoho_student_id": student.zoho_id,
                "status": "NEW",
                "message": "Student created"
            }

        # ============ EXISTING STUDENT ============
        # Check if unchanged
        if existing.fingerprint == fp and existing.academic_email == student.academic_email:
            return {
                "zoho_student_id": student.zoho_id,
                "status": "UNCHANGED",
                "message": "No changes detected"
            }

        # ============ UPDATED STUDENT ============
        changed = {}

        if existing.academic_email != student.academic_email:
            changed["academic_email"] = (existing.academic_email, student.academic_email)
            existing.academic_email = student.academic_email
            existing.username = student.academic_email  # Keep username in sync

        if existing.display_name != student.display_name:
            changed["display_name"] = (existing.display_name, student.display_name)
            existing.display_name = student.display_name

        if existing.phone != student.phone:
            changed["phone"] = (existing.phone, student.phone)
            existing.phone = student.phone

        if existing.status != student.status:
            changed["status"] = (existing.status, student.status)
            existing.status = student.status

        if existing.moodle_userid != student.userid:
            changed["moodle_userid"] = (existing.moodle_userid, student.userid)
            existing.moodle_userid = student.userid

        existing.fingerprint = fp
        existing.last_sync = int(time.time())
        
        self.db.commit()
        
        return {
            "zoho_student_id": student.zoho_id,
            "status": "UPDATED",
            "message": "Student data updated",
            "changed": changed
        }

    def get_student(self, zoho_id: str) -> Optional[Student]:
        """Get a student by zoho_id"""
        return self.db.query(Student).filter(Student.zoho_id == zoho_id).first()

    def mark_synced_to_moodle(self, zoho_id: str, moodle_userid: int) -> None:
        """Mark student as synced to Moodle and update moodle_userid"""
        student = self.get_student(zoho_id)
        if not student:
            return
        
        student.moodle_userid = moodle_userid
        student.last_sync = int(time.time())
        self.db.commit()
