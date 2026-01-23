"""
Create Extension Tables

Adds 6 new tables for Zoho Sigma Extension control plane.
Run this after the main tables are created.
"""

import psycopg2
from app.core.config import settings

EXTENSION_SCHEMA_SQL = """
-- Tenant Profiles
CREATE TABLE IF NOT EXISTS tenant_profiles (
    tenant_id VARCHAR(100) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    metadata_json JSONB DEFAULT '{}'::jsonb
);

CREATE INDEX IF NOT EXISTS idx_tenant_status ON tenant_profiles(status);

-- Integration Settings
CREATE TABLE IF NOT EXISTS integration_settings (
    id VARCHAR(36) PRIMARY KEY,
    tenant_id VARCHAR(100) NOT NULL UNIQUE REFERENCES tenant_profiles(tenant_id) ON DELETE CASCADE,
    moodle_enabled BOOLEAN NOT NULL DEFAULT false,
    moodle_base_url VARCHAR(500),
    moodle_api_token VARCHAR(500),
    zoho_enabled BOOLEAN NOT NULL DEFAULT true,
    zoho_api_domain VARCHAR(255),
    zoho_org_id VARCHAR(100),
    extension_api_key VARCHAR(100) NOT NULL,
    extension_api_secret VARCHAR(500) NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_integration_tenant ON integration_settings(tenant_id);

-- Module Settings
CREATE TABLE IF NOT EXISTS module_settings (
    id VARCHAR(36) PRIMARY KEY,
    tenant_id VARCHAR(100) NOT NULL REFERENCES tenant_profiles(tenant_id) ON DELETE CASCADE,
    module_name VARCHAR(100) NOT NULL,
    enabled BOOLEAN NOT NULL DEFAULT false,
    schedule_mode VARCHAR(50) NOT NULL DEFAULT 'manual',
    schedule_cron VARCHAR(100),
    last_run_at TIMESTAMP WITH TIME ZONE,
    last_run_status VARCHAR(50),
    last_run_count INTEGER DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT uq_module_per_tenant UNIQUE (tenant_id, module_name)
);

CREATE INDEX IF NOT EXISTS idx_module_tenant_name ON module_settings(tenant_id, module_name);
CREATE INDEX IF NOT EXISTS idx_module_enabled ON module_settings(enabled);

-- Field Mappings
CREATE TABLE IF NOT EXISTS field_mappings (
    id VARCHAR(36) PRIMARY KEY,
    tenant_id VARCHAR(100) NOT NULL REFERENCES tenant_profiles(tenant_id) ON DELETE CASCADE,
    module_name VARCHAR(100) NOT NULL,
    canonical_field VARCHAR(100) NOT NULL,
    zoho_field_api_name VARCHAR(100) NOT NULL,
    required BOOLEAN NOT NULL DEFAULT false,
    default_value VARCHAR(500),
    transform_rules_json JSONB DEFAULT '{}'::jsonb,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT uq_field_per_module UNIQUE (tenant_id, module_name, canonical_field)
);

CREATE INDEX IF NOT EXISTS idx_mapping_tenant_module ON field_mappings(tenant_id, module_name);

-- Sync Runs
CREATE TABLE IF NOT EXISTS sync_runs (
    run_id VARCHAR(36) PRIMARY KEY,
    tenant_id VARCHAR(100) NOT NULL REFERENCES tenant_profiles(tenant_id) ON DELETE CASCADE,
    module_name VARCHAR(100) NOT NULL,
    trigger_source VARCHAR(50) NOT NULL,
    triggered_by VARCHAR(255),
    started_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    finished_at TIMESTAMP WITH TIME ZONE,
    status VARCHAR(50) NOT NULL DEFAULT 'running',
    counts_json JSONB DEFAULT '{}'::jsonb,
    error_summary TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_runs_tenant_module ON sync_runs(tenant_id, module_name);
CREATE INDEX IF NOT EXISTS idx_runs_started ON sync_runs(started_at DESC);
CREATE INDEX IF NOT EXISTS idx_runs_status ON sync_runs(status);

-- Sync Run Items
CREATE TABLE IF NOT EXISTS sync_run_items (
    id VARCHAR(36) PRIMARY KEY,
    run_id VARCHAR(36) NOT NULL REFERENCES sync_runs(run_id) ON DELETE CASCADE,
    tenant_id VARCHAR(100) NOT NULL REFERENCES tenant_profiles(tenant_id) ON DELETE CASCADE,
    module_name VARCHAR(100) NOT NULL,
    zoho_id VARCHAR(100) NOT NULL,
    status VARCHAR(50) NOT NULL,
    message TEXT,
    diff_json JSONB DEFAULT '{}'::jsonb,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_items_run ON sync_run_items(run_id);
CREATE INDEX IF NOT EXISTS idx_items_status ON sync_run_items(status);
CREATE INDEX IF NOT EXISTS idx_items_tenant ON sync_run_items(tenant_id);

-- Insert default tenant
INSERT INTO tenant_profiles (tenant_id, name, status)
VALUES ('default', 'Default Tenant', 'active')
ON CONFLICT (tenant_id) DO NOTHING;

-- Insert default integration settings
INSERT INTO integration_settings (
    id, tenant_id, extension_api_key, extension_api_secret, 
    moodle_enabled, zoho_enabled
)
VALUES (
    'default-settings', 'default', 'ext_key_default', 
    'ext_secret_change_me_in_production', false, true
)
ON CONFLICT (tenant_id) DO NOTHING;
"""


def create_extension_tables():
    """Create extension tables"""
    try:
        # Parse DATABASE_URL
        db_url = settings.DATABASE_URL
        # Extract connection parameters
        # Format: postgresql+psycopg2://user:password@host:port/dbname
        parts = db_url.replace("postgresql+psycopg2://", "").split("@")
        user_pass = parts[0].split(":")
        host_db = parts[1].split("/")
        host_port = host_db[0].split(":")
        
        conn = psycopg2.connect(
            host=host_port[0],
            port=host_port[1] if len(host_port) > 1 else "5432",
            database=host_db[1],
            user=user_pass[0],
            password=user_pass[1]
        )
        
        cursor = conn.cursor()
        
        print("Creating extension tables...")
        cursor.execute(EXTENSION_SCHEMA_SQL)
        conn.commit()
        
        # List created tables
        cursor.execute("""
            SELECT table_name 
            FROM information_schema.tables 
            WHERE table_schema = 'public' 
            AND table_name IN ('tenant_profiles', 'integration_settings', 'module_settings', 
                              'field_mappings', 'sync_runs', 'sync_run_items')
            ORDER BY table_name;
        """)
        
        tables = cursor.fetchall()
        print(f"\n✅ Extension tables created successfully!\n")
        print("📊 Extension Tables:")
        for table in tables:
            print(f"   - {table[0]}")
        
        cursor.close()
        conn.close()
        
    except Exception as e:
        print(f"❌ Error creating extension tables: {e}")
        raise


if __name__ == "__main__":
    create_extension_tables()
