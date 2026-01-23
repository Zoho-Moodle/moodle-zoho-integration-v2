"""
Payment Mapper

Maps CanonicalPayment to DB Payment model.
"""

from app.domain.payment import CanonicalPayment
from app.infra.db.models.payment import Payment


def map_payment_to_db(canonical: CanonicalPayment, tenant_id: str) -> Payment:
    """
    Map CanonicalPayment to DB Payment model.
    """
    
    db_payment = Payment(
        tenant_id=tenant_id,
        source="zoho",
        zoho_id=canonical.zoho_id,
        registration_zoho_id=canonical.registration.id,
        amount=canonical.amount,
        payment_date=canonical.payment_date.isoformat() if canonical.payment_date else None,
        payment_method=canonical.payment_method,
        payment_status=canonical.payment_status,
        description=canonical.description,
    )
    
    return db_payment
