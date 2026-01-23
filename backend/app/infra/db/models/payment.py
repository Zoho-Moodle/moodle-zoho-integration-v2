"""
BTEC_Payments SQLAlchemy Model

Represents payment transactions linked to registrations.
"""

from sqlalchemy import Column, String, DateTime, Float, ForeignKey, Index
from uuid import uuid4
from datetime import datetime
from app.infra.db.base import Base


class Payment(Base):
    __tablename__ = "payments"

    # Primary key
    id = Column(String, primary_key=True, default=lambda: str(uuid4()), index=True)

    # Tenant/Source
    tenant_id = Column(String, default="default", nullable=False)
    source = Column(String, default="zoho", nullable=True)

    # Identifiers
    zoho_id = Column(String, nullable=False, index=True)
    
    # Foreign key
    registration_zoho_id = Column(String, ForeignKey("registrations.zoho_id"), nullable=False, index=True)

    # Payment details
    amount = Column(Float, nullable=False)  # numeric amount
    payment_date = Column(String, nullable=True)  # YYYY-MM-DD
    payment_method = Column(String, nullable=True)  # Credit Card, Bank Transfer, etc.
    payment_status = Column(String, nullable=False)  # Completed, Pending, Failed
    description = Column(String, nullable=True)

    # Sync tracking
    sync_status = Column(String, default="pending", nullable=True)
    data_hash = Column(String, nullable=True)
    fingerprint = Column(String, nullable=True)

    # Metadata
    created_at = Column(DateTime, default=datetime.utcnow, nullable=False)
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow, nullable=False)

    # Index for common queries
    __table_args__ = (
        Index("ix_payments_tenant_registration", "tenant_id", "registration_zoho_id"),
        Index("ix_payments_tenant_zoho", "tenant_id", "zoho_id"),
    )
