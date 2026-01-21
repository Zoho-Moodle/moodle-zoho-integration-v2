import hashlib
from typing import Any, Dict, Optional
from sqlalchemy.orm import Session
from app.domain.enrollment import CanonicalEnrollment
from app.infra.db.models.enrollment import Enrollment
from app.infra.db.models.student import Student
from app.infra.db.models.class_ import Class
import logging

logger = logging.getLogger(__name__)


def compute_enrollment_fingerprint(e: CanonicalEnrollment) -> str:
    """Compute fingerprint for change detection"""
    parts = [
        e.student_zoho_id or "",
        e.class_zoho_id or "",
        e.program_zoho_id or "",
        e.status or "",
        str(e.start_date or ""),
    ]
    raw = "|".join([part.strip().lower() for part in parts])
    return hashlib.sha256(raw.encode("utf-8")).hexdigest()


class EnrollmentService:
    def __init__(self, db: Session):
        self.db = db

    def sync_enrollment(self, enrollment: CanonicalEnrollment, tenant_id: str = "default") -> Dict[str, Any]:
        """
        Sync a single enrollment with dependency checks.
        
        States:
        - SKIPPED: Missing dependencies (student or class not synced yet)
        - NEW: Enrollment created
        - UNCHANGED: Enrollment already exists with same data
        - UPDATED: Enrollment data changed and updated
        - INVALID: Enrollment data failed validation
        """
        
        # ============ VALIDATION ============
        if not enrollment.zoho_id or not enrollment.student_zoho_id or not enrollment.class_zoho_id:
            return {
                "zoho_enrollment_id": enrollment.zoho_id or "unknown",
                "status": "INVALID",
                "message": "Missing zoho_id, student_zoho_id, or class_zoho_id"
            }

        # ============ DEPENDENCY CHECK: STUDENT ============
        student: Optional[Student] = (
            self.db.query(Student)
            .filter(Student.tenant_id == tenant_id)
            .filter(Student.zoho_id == enrollment.student_zoho_id)
            .first()
        )
        
        if student is None:
            logger.warning(
                f"Enrollment {enrollment.zoho_id}: student {enrollment.student_zoho_id} not synced yet"
            )
            return {
                "zoho_enrollment_id": enrollment.zoho_id,
                "status": "SKIPPED",
                "reason": "student_not_synced_yet",
                "message": f"Student {enrollment.student_zoho_id} not synced yet"
            }

        # ============ DEPENDENCY CHECK: CLASS ============
        class_: Optional[Class] = (
            self.db.query(Class)
            .filter(Class.tenant_id == tenant_id)
            .filter(Class.zoho_id == enrollment.class_zoho_id)
            .first()
        )
        
        if class_ is None:
            logger.warning(
                f"Enrollment {enrollment.zoho_id}: class {enrollment.class_zoho_id} not synced yet"
            )
            return {
                "zoho_enrollment_id": enrollment.zoho_id,
                "status": "SKIPPED",
                "reason": "class_not_synced_yet",
                "message": f"Class {enrollment.class_zoho_id} not synced yet"
            }

        # ============ COMPUTE FINGERPRINT ============
        fp = compute_enrollment_fingerprint(enrollment)

        # ============ QUERY FOR EXISTING ============
        existing: Optional[Enrollment] = (
            self.db.query(Enrollment)
            .filter(Enrollment.tenant_id == tenant_id)
            .filter(Enrollment.zoho_id == enrollment.zoho_id)
            .first()
        )

        # ============ NEW ENROLLMENT ============
        if existing is None:
            row = Enrollment(
                tenant_id=tenant_id,
                source="zoho",
                zoho_id=enrollment.zoho_id,
                enrollment_name=f"{student.display_name or 'User'} - {class_.name}",
                student_zoho_id=enrollment.student_zoho_id,
                class_zoho_id=enrollment.class_zoho_id,
                program_zoho_id=enrollment.program_zoho_id,
                student_name=student.display_name or student.academic_email,
                class_name=class_.name,
                start_date=enrollment.start_date,
                status=enrollment.status,
                moodle_course_id=enrollment.moodle_course_id,
                moodle_user_id=enrollment.moodle_user_id,
                fingerprint=fp,
            )
            self.db.add(row)
            self.db.commit()
            
            result = {
                "zoho_enrollment_id": enrollment.zoho_id,
                "status": "NEW",
                "message": "Enrollment created",
                "student_id": student.id,
                "class_id": class_.id,
            }
            
            # TODO: Call Moodle client to enrol user if MOODLE_ENABLED
            # if settings.MOODLE_ENABLED:
            #     moodle_result = moodle_client.enrol_user(...)
            #     row.moodle_enrollment_id = moodle_result["id"]
            #     db.commit()
            #     result["moodle_enrollment_id"] = row.moodle_enrollment_id
            
            return result

        # ============ EXISTING ENROLLMENT ============
        # Check if unchanged
        if existing.fingerprint == fp:
            return {
                "zoho_enrollment_id": enrollment.zoho_id,
                "status": "UNCHANGED",
                "message": "No changes detected"
            }

        # ============ UPDATED ENROLLMENT ============
        changed = {}

        if existing.status != enrollment.status:
            changed["status"] = (existing.status, enrollment.status)
            existing.status = enrollment.status

        if existing.start_date != enrollment.start_date:
            changed["start_date"] = (str(existing.start_date), str(enrollment.start_date))
            existing.start_date = enrollment.start_date

        if changed:
            existing.fingerprint = fp
            self.db.commit()
            
            return {
                "zoho_enrollment_id": enrollment.zoho_id,
                "status": "UPDATED",
                "message": "Enrollment updated",
                "changes": changed
            }

        return {
            "zoho_enrollment_id": enrollment.zoho_id,
            "status": "UNCHANGED",
            "message": "No changes detected"
        }
