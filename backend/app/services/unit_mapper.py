"""
Unit Mapper

Maps CanonicalUnit to DB Unit model.
"""

from app.domain.unit import CanonicalUnit
from app.infra.db.models.unit import Unit


def map_unit_to_db(canonical: CanonicalUnit, tenant_id: str) -> Unit:
    """
    Map CanonicalUnit to DB Unit model.
    """
    
    db_unit = Unit(
        tenant_id=tenant_id,
        source="zoho",
        zoho_id=canonical.zoho_id,
        unit_code=canonical.unit_code,
        unit_name=canonical.unit_name,
        description=canonical.description,
        credit_hours=canonical.credit_hours,
        level=canonical.level,
        status=canonical.status,
    )
    
    return db_unit
