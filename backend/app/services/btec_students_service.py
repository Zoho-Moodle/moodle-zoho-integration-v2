"""
BTEC_Students Enrichment Service

Updates existing Student records with BTEC-specific data (profile image, etc.)
"""

from typing import Dict, Any, Optional
from sqlalchemy.orm import Session
from app.infra.db.models.student import Student


class BTECStudentEnrichmentService:
    """
    Enriches base Student records with BTEC-specific data.
    Called after initial Student sync to add supplementary information.
    """
    
    def __init__(self, db: Session):
        self.db = db

    def enrich_student(self, btec_data: Dict[str, Any], tenant_id: str) -> Dict[str, Any]:
        """
        Update a student record with BTEC-specific data.
        
        btec_data expected format:
        {
            "student_zoho_id": "stud_...",
            "profile_image": "https://...",
            "department": "...",
            "student_id_number": "...",
            "status": "Active"
        }
        
        Returns: {status: "ENRICHED|NOT_FOUND|ERROR", ...}
        """
        
        student_zoho_id = btec_data.get("student_zoho_id")
        if not student_zoho_id:
            return {
                "status": "INVALID",
                "message": "student_zoho_id is required"
            }
        
        # Find student
        student: Optional[Student] = (
            self.db.query(Student)
            .filter(
                Student.zoho_id == student_zoho_id,
                Student.tenant_id == tenant_id
            )
            .first()
        )
        
        if not student:
            return {
                "status": "NOT_FOUND",
                "message": f"Student {student_zoho_id} not found. Sync base student first.",
                "student_zoho_id": student_zoho_id
            }
        
        # Check what needs updating
        changed = {}
        
        # Update profile image
        profile_image = btec_data.get("profile_image")
        if profile_image and student.record_image != profile_image:
            changed["record_image"] = (student.record_image, profile_image)
            student.record_image = profile_image
        
        # Note: We're not adding extra fields to Student model yet
        # but you could extend it with department, student_id_number, etc.
        # For now, we only update record_image (profile picture)
        
        if not changed:
            return {
                "status": "UNCHANGED",
                "message": "No new enrichment data",
                "student_zoho_id": student_zoho_id
            }
        
        # Commit changes
        student.sync_status = "enriched"
        self.db.commit()
        
        return {
            "status": "ENRICHED",
            "message": "Student record enriched with BTEC data",
            "student_zoho_id": student_zoho_id,
            "changed_fields": changed
        }
