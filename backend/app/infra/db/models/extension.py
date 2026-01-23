"""
Extension Configuration Models

Tables for Zoho Sigma Extension control plane:
- tenant_profiles: Tenant metadata
- integration_settings: Moodle/Zoho connection config
- module_settings: Per-module sync configuration
- field_mappings: Zoho -> Canonical field mappings
- sync_runs: Manual/scheduled sync execution history
- sync_run_items: Individual record sync results
"""

from sqlalchemy import Column, String, Boolean, DateTime, Integer, Text, JSON, Index, ForeignKey, UniqueConstraint
from sqlalchemy.sql import func
from app.infra.db.base import Base


class TenantProfile(Base):
    """Tenant metadata and status"""
    __tablename__ = "tenant_profiles"
    
    tenant_id = Column(String(100), primary_key=True, index=True)
    name = Column(String(255), nullable=False)
    status = Column(String(50), default="active", nullable=False)  # active, suspended, inactive
    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    updated_at = Column(DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False)
    
    metadata_json = Column(JSON, default={})  # Additional tenant info


class IntegrationSettings(Base):
    """Global integration settings per tenant"""
    __tablename__ = "integration_settings"
    
    id = Column(String(36), primary_key=True)
    tenant_id = Column(String(100), ForeignKey("tenant_profiles.tenant_id", ondelete="CASCADE"), nullable=False, unique=True, index=True)
    
    # Moodle settings
    moodle_enabled = Column(Boolean, default=False, nullable=False)
    moodle_base_url = Column(String(500))
    moodle_api_token = Column(String(500))  # Encrypted
    
    # Zoho settings
    zoho_enabled = Column(Boolean, default=True, nullable=False)
    zoho_api_domain = Column(String(255))
    zoho_org_id = Column(String(100))
    
    # Extension auth
    extension_api_key = Column(String(100), nullable=False)  # Public key identifier
    extension_api_secret = Column(String(500), nullable=False)  # HMAC secret (encrypted)
    
    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    updated_at = Column(DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False)
    
    __table_args__ = (
        Index("idx_integration_tenant", "tenant_id"),
    )


class ModuleSettings(Base):
    """Per-module sync configuration"""
    __tablename__ = "module_settings"
    
    id = Column(String(36), primary_key=True)
    tenant_id = Column(String(100), ForeignKey("tenant_profiles.tenant_id", ondelete="CASCADE"), nullable=False, index=True)
    module_name = Column(String(100), nullable=False)  # students, programs, classes, enrollments, units, registrations, payments, grades
    
    enabled = Column(Boolean, default=False, nullable=False)
    schedule_mode = Column(String(50), default="manual", nullable=False)  # manual, cron, webhook
    schedule_cron = Column(String(100))  # Cron expression if schedule_mode=cron
    
    last_run_at = Column(DateTime(timezone=True))
    last_run_status = Column(String(50))  # success, failed, partial
    last_run_count = Column(Integer, default=0)
    
    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    updated_at = Column(DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False)
    
    __table_args__ = (
        UniqueConstraint("tenant_id", "module_name", name="uq_module_per_tenant"),
        Index("idx_module_tenant_name", "tenant_id", "module_name"),
    )


class FieldMapping(Base):
    """Zoho -> Canonical field mappings per module"""
    __tablename__ = "field_mappings"
    
    id = Column(String(36), primary_key=True)
    tenant_id = Column(String(100), ForeignKey("tenant_profiles.tenant_id", ondelete="CASCADE"), nullable=False, index=True)
    module_name = Column(String(100), nullable=False)
    
    canonical_field = Column(String(100), nullable=False)  # Field name in canonical model
    zoho_field_api_name = Column(String(100), nullable=False)  # API name in Zoho
    
    required = Column(Boolean, default=False, nullable=False)
    default_value = Column(String(500))
    transform_rules_json = Column(JSON, default={})  # {type: "before_at", params: {...}}
    
    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    updated_at = Column(DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False)
    
    __table_args__ = (
        UniqueConstraint("tenant_id", "module_name", "canonical_field", name="uq_field_per_module"),
        Index("idx_mapping_tenant_module", "tenant_id", "module_name"),
    )


class SyncRun(Base):
    """Sync execution history"""
    __tablename__ = "sync_runs"
    
    run_id = Column(String(36), primary_key=True)
    tenant_id = Column(String(100), ForeignKey("tenant_profiles.tenant_id", ondelete="CASCADE"), nullable=False, index=True)
    module_name = Column(String(100), nullable=False)
    
    trigger_source = Column(String(50), nullable=False)  # manual, scheduled, webhook
    triggered_by = Column(String(255))  # User/system identifier
    
    started_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    finished_at = Column(DateTime(timezone=True))
    status = Column(String(50), default="running", nullable=False)  # running, completed, failed, partial
    
    counts_json = Column(JSON, default={})  # {new: 5, unchanged: 10, updated: 3, failed: 2}
    error_summary = Column(Text)
    
    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    
    __table_args__ = (
        Index("idx_runs_tenant_module", "tenant_id", "module_name"),
        Index("idx_runs_started", "started_at"),
    )


class SyncRunItem(Base):
    """Individual record sync result"""
    __tablename__ = "sync_run_items"
    
    id = Column(String(36), primary_key=True)
    run_id = Column(String(36), ForeignKey("sync_runs.run_id", ondelete="CASCADE"), nullable=False, index=True)
    tenant_id = Column(String(100), ForeignKey("tenant_profiles.tenant_id", ondelete="CASCADE"), nullable=False, index=True)
    module_name = Column(String(100), nullable=False)
    
    zoho_id = Column(String(100), nullable=False)
    status = Column(String(50), nullable=False)  # NEW, UNCHANGED, UPDATED, FAILED, SKIPPED
    message = Column(Text)
    diff_json = Column(JSON, default={})  # Changes applied
    
    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    
    __table_args__ = (
        Index("idx_items_run", "run_id"),
        Index("idx_items_status", "status"),
    )
