"""
BTEC_Grades SQLAlchemy Model

Represents student grades for units/classes.
"""

from sqlalchemy import Column, String, DateTime, Float, ForeignKey, Index
from uuid import uuid4
from datetime import datetime
from app.infra.db.base import Base


class Grade(Base):
    __tablename__ = "grades"

    # Primary key
    id = Column(String, primary_key=True, default=lambda: str(uuid4()), index=True)

    # Tenant/Source
    tenant_id = Column(String, default="default", nullable=False)
    source = Column(String, default="zoho", nullable=True)

    # Identifiers
    zoho_id = Column(String, nullable=False, index=True)

    # Foreign keys
    student_zoho_id = Column(String, ForeignKey("students.zoho_id"), nullable=False, index=True)
    unit_zoho_id = Column(String, ForeignKey("units.zoho_id"), nullable=False, index=True)

    # Grade details
    grade_value = Column(String, nullable=False)  # A, B, C, D, F, etc.
    score = Column(Float, nullable=True)  # numeric score 0-100
    grade_date = Column(String, nullable=True)  # YYYY-MM-DD
    comments = Column(String, nullable=True)

    # Sync tracking
    sync_status = Column(String, default="pending", nullable=True)
    data_hash = Column(String, nullable=True)
    fingerprint = Column(String, nullable=True)

    # Metadata
    created_at = Column(DateTime, default=datetime.utcnow, nullable=False)
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow, nullable=False)

    # Index for common queries
    __table_args__ = (
        Index("ix_grades_tenant_student_unit", "tenant_id", "student_zoho_id", "unit_zoho_id"),
        Index("ix_grades_tenant_zoho", "tenant_id", "zoho_id"),
    )
