"""
Event Log Database Model

Stores webhook events for deduplication and tracking.
"""

from sqlalchemy import Column, String, Integer, DateTime, Text, JSON, Index
from sqlalchemy.sql import func
from app.infra.db.base import Base


class EventLog(Base):
    """
    Event log for webhook deduplication and tracking.
    
    Stores all incoming webhook events to:
    1. Prevent duplicate processing
    2. Track event history
    3. Enable retry mechanisms
    4. Audit trail
    """
    __tablename__ = "integration_events_log"
    
    # Primary key
    id = Column(Integer, primary_key=True, autoincrement=True)
    
    # Event identification (for deduplication)
    event_id = Column(String(255), nullable=False, unique=True, index=True)
    
    # Event metadata
    source = Column(String(50), nullable=False, index=True)  # 'zoho' or 'moodle'
    module = Column(String(100), nullable=False, index=True)  # BTEC_Students, BTEC_Enrollments, etc.
    event_type = Column(String(100), nullable=False, index=True)  # created, updated, deleted
    record_id = Column(String(255), nullable=False, index=True)  # Zoho/Moodle record ID
    
    # Event payload
    payload = Column(JSON, nullable=False)  # Full webhook payload
    
    # Processing status
    status = Column(String(50), default="pending", nullable=False, index=True)  # pending, processing, completed, failed, duplicate
    
    # Processing result
    result = Column(JSON, nullable=True)  # Processing result details
    error_message = Column(Text, nullable=True)  # Error message if failed
    
    # Timestamps
    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    processed_at = Column(DateTime(timezone=True), nullable=True)
    
    # Indexes for common queries
    __table_args__ = (
        Index("idx_events_source_module", "source", "module"),
        Index("idx_events_status_created", "status", "created_at"),
        Index("idx_events_record", "source", "record_id"),
    )
