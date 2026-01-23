"""
BTEC_Registrations SQLAlchemy Model

Represents Student ↔ Program relationship with enrollment tracking.
"""

from sqlalchemy import Column, String, DateTime, ForeignKey, Index
from uuid import uuid4
from datetime import datetime
from app.infra.db.base import Base


class Registration(Base):
    __tablename__ = "registrations"

    # Primary key
    id = Column(String, primary_key=True, default=lambda: str(uuid4()), index=True)

    # Tenant/Source
    tenant_id = Column(String, default="default", nullable=False)
    source = Column(String, default="zoho", nullable=True)

    # Identifiers
    zoho_id = Column(String, nullable=False, index=True)
    
    # Foreign keys
    student_zoho_id = Column(String, ForeignKey("students.zoho_id"), nullable=False, index=True)
    program_zoho_id = Column(String, ForeignKey("programs.zoho_id"), nullable=False, index=True)

    # Registration details
    enrollment_status = Column(String, nullable=False)  # Active, Inactive, Suspended, Completed
    registration_date = Column(String, nullable=True)  # YYYY-MM-DD
    completion_date = Column(String, nullable=True)    # YYYY-MM-DD

    # Sync tracking
    sync_status = Column(String, default="pending", nullable=True)
    data_hash = Column(String, nullable=True)
    fingerprint = Column(String, nullable=True)
    version = Column(String, nullable=True)

    # Metadata
    created_at = Column(DateTime, default=datetime.utcnow, nullable=False)
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow, nullable=False)

    # Index for common queries
    __table_args__ = (
        Index("ix_registrations_tenant_student_program", "tenant_id", "student_zoho_id", "program_zoho_id"),
        Index("ix_registrations_tenant_zoho", "tenant_id", "zoho_id"),
    )
