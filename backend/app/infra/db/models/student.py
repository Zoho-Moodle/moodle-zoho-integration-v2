from sqlalchemy import Column, String, Integer, DateTime, Text
from uuid import uuid4
from datetime import datetime
from app.infra.db.base import Base


class Student(Base):
    __tablename__ = "students"

    # Primary key (UUID)
    id = Column(String, primary_key=True, default=lambda: str(uuid4()), index=True)
    
    # Tenant/Source info
    tenant_id = Column(String, default="default", nullable=False)
    source = Column(String, default="zoho", nullable=True)

    # Student identifiers
    zoho_id = Column(String, unique=True, index=True, nullable=True)  # Nullable: Moodle users don't have Zoho ID initially
    moodle_user_id = Column(String, nullable=True)
    userid = Column(String, nullable=True)  # Make nullable - Zoho doesn't provide this
    username = Column(String, unique=True, index=True, nullable=True)

    # User information
    display_name = Column(String, nullable=True)
    academic_email = Column(String, nullable=False)  # Required - students must be created in Moodle first with email
    birth_date = Column(String, nullable=True)
    phone = Column(String, nullable=True)
    address = Column(String, nullable=True)
    city = Column(String, nullable=True)
    country = Column(String, nullable=True)
    record_image = Column(String, nullable=True)
    status = Column(String, nullable=True)

    # Sync tracking
    sync_status = Column(String, default="pending", nullable=True)
    last_sync = Column(Integer, nullable=True)
    data_hash = Column(String, nullable=True)
    fingerprint = Column(String, nullable=True)
    moodle_userid = Column(Integer, nullable=True, index=True)

    # Metadata
    created_at = Column(DateTime, default=datetime.utcnow, nullable=False)
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow, nullable=False)
