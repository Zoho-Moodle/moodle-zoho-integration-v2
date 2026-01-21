from sqlalchemy import Column, String, Date, DateTime, Integer, Index
from uuid import uuid4
from datetime import datetime
from app.infra.db.base import Base


class Enrollment(Base):
    __tablename__ = "enrollments"

    # Primary key (UUID)
    id = Column(String, primary_key=True, default=lambda: str(uuid4()), index=True)
    
    # Tenant/Source info
    tenant_id = Column(String, default="default", nullable=False)
    source = Column(String, default="zoho", nullable=True)

    # Enrollment identifiers
    zoho_id = Column(String, nullable=False, index=True)  # Enrollment record ID (Name auto)
    enrollment_name = Column(String, nullable=True)  # Enrollment Name field (auto number)
    
    # Foreign key references (stored as zoho_id strings)
    student_zoho_id = Column(String, nullable=False, index=True)
    student_name = Column(String, nullable=True)
    class_zoho_id = Column(String, nullable=False, index=True)
    class_name = Column(String, nullable=True)
    program_zoho_id = Column(String, nullable=True, index=True)

    # Enrollment information
    start_date = Column(Date, nullable=True)
    status = Column(String, nullable=True)
    
    # Moodle integration
    moodle_course_id = Column(String, nullable=True, index=True)
    moodle_user_id = Column(Integer, nullable=True)
    moodle_enrollment_id = Column(Integer, nullable=True)
    
    # Sync tracking
    last_sync_date = Column(DateTime, nullable=True)
    fingerprint = Column(String, nullable=True)
    
    # Metadata
    created_at = Column(DateTime, default=datetime.utcnow, nullable=False)
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow, nullable=False)

    # Unique constraint: tenant + zoho_id
    __table_args__ = (
        Index('idx_enrollment_tenant_zoho_id', 'tenant_id', 'zoho_id', unique=True),
        Index('idx_enrollment_student_class', 'tenant_id', 'student_zoho_id', 'class_zoho_id'),
        Index('idx_enrollment_student', 'tenant_id', 'student_zoho_id'),
        Index('idx_enrollment_class', 'tenant_id', 'class_zoho_id'),
    )
