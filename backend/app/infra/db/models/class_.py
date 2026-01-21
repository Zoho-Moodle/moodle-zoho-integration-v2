from sqlalchemy import Column, String, Date, DateTime, Index
from uuid import uuid4
from datetime import datetime
from app.infra.db.base import Base


class Class(Base):
    __tablename__ = "classes"

    # Primary key (UUID)
    id = Column(String, primary_key=True, default=lambda: str(uuid4()), index=True)
    
    # Tenant/Source info
    tenant_id = Column(String, default="default", nullable=False)
    source = Column(String, default="zoho", nullable=True)

    # Class identifiers
    zoho_id = Column(String, nullable=False, index=True)
    
    # Class information
    name = Column(String, nullable=False)
    short_name = Column(String, nullable=True)
    status = Column(String, nullable=True)
    start_date = Column(Date, nullable=True)
    end_date = Column(Date, nullable=True)
    moodle_class_id = Column(String, nullable=True, index=True)
    ms_teams_id = Column(String, nullable=True)

    # Foreign key references (stored as zoho_id strings for now)
    teacher_zoho_id = Column(String, nullable=True, index=True)
    unit_zoho_id = Column(String, nullable=True)
    program_zoho_id = Column(String, nullable=True, index=True)

    # Sync tracking
    fingerprint = Column(String, nullable=True)
    last_sync = Column(DateTime, nullable=True)

    # Metadata
    created_at = Column(DateTime, default=datetime.utcnow, nullable=False)
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow, nullable=False)

    # Unique constraint: tenant + zoho_id
    __table_args__ = (
        Index('idx_class_tenant_zoho_id', 'tenant_id', 'zoho_id', unique=True),
        Index('idx_class_program_zoho_id', 'tenant_id', 'program_zoho_id'),
    )
