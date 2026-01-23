"""
Extension Service Layer

Business logic for configuration management and sync orchestration.
"""

import uuid
from datetime import datetime
from typing import List, Optional, Dict, Any
from sqlalchemy.orm import Session
from sqlalchemy import desc
from app.infra.db.models.extension import (
    TenantProfile, IntegrationSettings, ModuleSettings,
    FieldMapping, SyncRun, SyncRunItem
)


class ExtensionService:
    """Service for extension configuration and sync management"""
    
    def __init__(self, db: Session):
        self.db = db
    
    # ===== Tenant Management =====
    
    def get_tenants(self) -> List[TenantProfile]:
        """Get all tenants"""
        return self.db.query(TenantProfile).all()
    
    def create_tenant(self, tenant_id: str, name: str, status: str = "active") -> TenantProfile:
        """Create new tenant"""
        tenant = TenantProfile(tenant_id=tenant_id, name=name, status=status)
        self.db.add(tenant)
        self.db.commit()
        self.db.refresh(tenant)
        return tenant
    
    # ===== Integration Settings =====
    
    def get_integration_settings(self, tenant_id: str) -> Optional[IntegrationSettings]:
        """Get integration settings for tenant"""
        return self.db.query(IntegrationSettings).filter(
            IntegrationSettings.tenant_id == tenant_id
        ).first()
    
    def update_integration_settings(
        self, tenant_id: str, updates: Dict[str, Any]
    ) -> IntegrationSettings:
        """Update integration settings"""
        settings = self.get_integration_settings(tenant_id)
        if not settings:
            # Create if doesn't exist
            settings = IntegrationSettings(
                id=str(uuid.uuid4()),
                tenant_id=tenant_id,
                extension_api_key=updates.get("extension_api_key", f"ext_key_{tenant_id}"),
                extension_api_secret=updates.get("extension_api_secret", str(uuid.uuid4()))
            )
            self.db.add(settings)
        
        for key, value in updates.items():
            if hasattr(settings, key) and value is not None:
                setattr(settings, key, value)
        
        self.db.commit()
        self.db.refresh(settings)
        return settings
    
    # ===== Module Settings =====
    
    def get_module_settings(self, tenant_id: str, module_name: Optional[str] = None) -> List[ModuleSettings]:
        """Get module settings"""
        query = self.db.query(ModuleSettings).filter(ModuleSettings.tenant_id == tenant_id)
        if module_name:
            query = query.filter(ModuleSettings.module_name == module_name)
        return query.all()
    
    def update_module_settings(
        self, tenant_id: str, module_name: str, updates: Dict[str, Any]
    ) -> ModuleSettings:
        """Update module settings"""
        settings = self.db.query(ModuleSettings).filter(
            ModuleSettings.tenant_id == tenant_id,
            ModuleSettings.module_name == module_name
        ).first()
        
        if not settings:
            settings = ModuleSettings(
                id=str(uuid.uuid4()),
                tenant_id=tenant_id,
                module_name=module_name
            )
            self.db.add(settings)
        
        for key, value in updates.items():
            if hasattr(settings, key) and value is not None:
                setattr(settings, key, value)
        
        self.db.commit()
        self.db.refresh(settings)
        return settings
    
    # ===== Field Mappings =====
    
    def get_field_mappings(self, tenant_id: str, module_name: str) -> List[FieldMapping]:
        """Get field mappings for module"""
        return self.db.query(FieldMapping).filter(
            FieldMapping.tenant_id == tenant_id,
            FieldMapping.module_name == module_name
        ).all()
    
    def update_field_mappings(
        self, tenant_id: str, module_name: str, mappings: List[Dict[str, Any]]
    ) -> List[FieldMapping]:
        """Replace all field mappings for module"""
        # Delete existing
        self.db.query(FieldMapping).filter(
            FieldMapping.tenant_id == tenant_id,
            FieldMapping.module_name == module_name
        ).delete()
        
        # Create new
        result = []
        for mapping in mappings:
            field_map = FieldMapping(
                id=str(uuid.uuid4()),
                tenant_id=tenant_id,
                module_name=module_name,
                canonical_field=mapping["canonical_field"],
                zoho_field_api_name=mapping["zoho_field_api_name"],
                required=mapping.get("required", False),
                default_value=mapping.get("default_value"),
                transform_rules_json=mapping.get("transform_rules", {})
            )
            self.db.add(field_map)
            result.append(field_map)
        
        self.db.commit()
        return result
    
    # ===== Sync Runs =====
    
    def create_sync_run(
        self, tenant_id: str, module_name: str, trigger_source: str, triggered_by: Optional[str] = None
    ) -> SyncRun:
        """Create new sync run"""
        run = SyncRun(
            run_id=str(uuid.uuid4()),
            tenant_id=tenant_id,
            module_name=module_name,
            trigger_source=trigger_source,
            triggered_by=triggered_by,
            status="running"
        )
        self.db.add(run)
        self.db.commit()
        self.db.refresh(run)
        return run
    
    def update_sync_run(
        self, run_id: str, status: str, counts: Dict[str, int], error_summary: Optional[str] = None
    ) -> SyncRun:
        """Update sync run status"""
        run = self.db.query(SyncRun).filter(SyncRun.run_id == run_id).first()
        if run:
            run.status = status
            run.finished_at = datetime.utcnow()
            run.counts_json = counts
            if error_summary:
                run.error_summary = error_summary
            self.db.commit()
            self.db.refresh(run)
        return run
    
    def get_sync_runs(
        self, tenant_id: str, module_name: Optional[str] = None, limit: int = 50
    ) -> List[SyncRun]:
        """Get sync run history"""
        query = self.db.query(SyncRun).filter(SyncRun.tenant_id == tenant_id)
        if module_name:
            query = query.filter(SyncRun.module_name == module_name)
        return query.order_by(desc(SyncRun.started_at)).limit(limit).all()
    
    def get_sync_run(self, run_id: str) -> Optional[SyncRun]:
        """Get specific sync run"""
        return self.db.query(SyncRun).filter(SyncRun.run_id == run_id).first()
    
    def add_sync_run_item(
        self, run_id: str, tenant_id: str, module_name: str,
        zoho_id: str, status: str, message: Optional[str] = None, diff: Optional[Dict] = None
    ) -> SyncRunItem:
        """Add sync run item"""
        item = SyncRunItem(
            id=str(uuid.uuid4()),
            run_id=run_id,
            tenant_id=tenant_id,
            module_name=module_name,
            zoho_id=zoho_id,
            status=status,
            message=message,
            diff_json=diff or {}
        )
        self.db.add(item)
        self.db.commit()
        return item
    
    def get_sync_run_items(self, run_id: str) -> List[SyncRunItem]:
        """Get items for sync run"""
        return self.db.query(SyncRunItem).filter(SyncRunItem.run_id == run_id).all()
    
    def get_failed_items(self, run_id: str) -> List[SyncRunItem]:
        """Get failed items from run"""
        return self.db.query(SyncRunItem).filter(
            SyncRunItem.run_id == run_id,
            SyncRunItem.status == "FAILED"
        ).all()
