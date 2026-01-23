"""
BTEC (Units) SQLAlchemy Model

Represents course units/modules in the system.
"""

from sqlalchemy import Column, String, DateTime, Float, Index
from uuid import uuid4
from datetime import datetime
from app.infra.db.base import Base


class Unit(Base):
    __tablename__ = "units"

    # Primary key
    id = Column(String, primary_key=True, default=lambda: str(uuid4()), index=True)

    # Tenant/Source
    tenant_id = Column(String, default="default", nullable=False)
    source = Column(String, default="zoho", nullable=True)

    # Identifiers
    zoho_id = Column(String, nullable=False, index=True)
    unit_code = Column(String, nullable=False)  # e.g., "UNIT001"

    # Unit details
    unit_name = Column(String, nullable=False)
    description = Column(String, nullable=True)
    credit_hours = Column(Float, nullable=True)
    level = Column(String, nullable=True)  # L3, L4, etc.
    status = Column(String, nullable=False)  # Active, Inactive

    # Sync tracking
    sync_status = Column(String, default="pending", nullable=True)
    data_hash = Column(String, nullable=True)
    fingerprint = Column(String, nullable=True)

    # Metadata
    created_at = Column(DateTime, default=datetime.utcnow, nullable=False)
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow, nullable=False)

    # Index for common queries
    __table_args__ = (
        Index("ix_units_tenant_zoho", "tenant_id", "zoho_id"),
        Index("ix_units_tenant_code", "tenant_id", "unit_code"),
    )
