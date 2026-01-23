"""
Registration Mapper

Maps CanonicalRegistration to DB Registration model.
"""

from app.domain.registration import CanonicalRegistration
from app.infra.db.models.registration import Registration


def map_registration_to_db(canonical: CanonicalRegistration, tenant_id: str) -> Registration:
    """
    Map CanonicalRegistration to DB Registration model.
    """
    
    db_registration = Registration(
        tenant_id=tenant_id,
        source="zoho",
        zoho_id=canonical.zoho_id,
        student_zoho_id=canonical.student.id,
        program_zoho_id=canonical.program.id,
        enrollment_status=canonical.enrollment_status,
        registration_date=canonical.registration_date.isoformat() if canonical.registration_date else None,
        completion_date=canonical.completion_date.isoformat() if canonical.completion_date else None,
        version=str(canonical.version) if canonical.version else None,
    )
    
    return db_registration
