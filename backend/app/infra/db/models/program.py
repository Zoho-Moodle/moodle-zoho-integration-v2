from sqlalchemy import Column, String, Float, Integer, DateTime, Index
from uuid import uuid4
from datetime import datetime
from app.infra.db.base import Base


class Program(Base):
    __tablename__ = "programs"

    # Primary key (UUID)
    id = Column(String, primary_key=True, default=lambda: str(uuid4()), index=True)
    
    # Tenant/Source info
    tenant_id = Column(String, default="default", nullable=False)
    source = Column(String, default="zoho", nullable=True)

    # Program identifiers
    zoho_id = Column(String, nullable=False, index=True)
    
    # Program information
    name = Column(String, nullable=False)
    price = Column(Float, nullable=True)
    moodle_id = Column(String, nullable=True, index=True)
    status = Column(String, nullable=True)

    # Sync tracking
    fingerprint = Column(String, nullable=True)
    last_sync = Column(DateTime, nullable=True)

    # Metadata
    created_at = Column(DateTime, default=datetime.utcnow, nullable=False)
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow, nullable=False)

    # Unique constraint: tenant + zoho_id
    __table_args__ = (
        Index('idx_program_tenant_zoho_id', 'tenant_id', 'zoho_id', unique=True),
    )
