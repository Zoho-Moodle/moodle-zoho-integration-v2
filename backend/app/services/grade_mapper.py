"""
Grade Mapper

Maps CanonicalGrade to DB Grade model.
"""

from app.domain.grade import CanonicalGrade
from app.infra.db.models.grade import Grade


def map_grade_to_db(canonical: CanonicalGrade, tenant_id: str) -> Grade:
    """
    Map CanonicalGrade to DB Grade model.
    """
    
    db_grade = Grade(
        tenant_id=tenant_id,
        source="zoho",
        zoho_id=canonical.zoho_id,
        student_zoho_id=canonical.student.id,
        unit_zoho_id=canonical.unit.id,
        grade_value=canonical.grade_value,
        score=canonical.score,
        grade_date=canonical.grade_date.isoformat() if canonical.grade_date else None,
        comments=canonical.comments,
    )
    
    return db_grade
