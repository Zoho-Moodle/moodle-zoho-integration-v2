"""
Create Event Log Table Migration

Creates integration_events_log table for webhook event tracking.
"""

import psycopg2
from app.core.config import settings

CREATE_EVENT_LOG_TABLE_SQL = """
-- Create integration_events_log table
CREATE TABLE IF NOT EXISTS integration_events_log (
    id SERIAL PRIMARY KEY,
    event_id VARCHAR(255) NOT NULL UNIQUE,
    source VARCHAR(50) NOT NULL,
    module VARCHAR(100) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    record_id VARCHAR(255) NOT NULL,
    payload JSONB NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    result JSONB,
    error_message TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    processed_at TIMESTAMP WITH TIME ZONE
);

-- Create indexes
CREATE INDEX IF NOT EXISTS idx_events_event_id ON integration_events_log(event_id);
CREATE INDEX IF NOT EXISTS idx_events_source ON integration_events_log(source);
CREATE INDEX IF NOT EXISTS idx_events_module ON integration_events_log(module);
CREATE INDEX IF NOT EXISTS idx_events_event_type ON integration_events_log(event_type);
CREATE INDEX IF NOT EXISTS idx_events_record_id ON integration_events_log(record_id);
CREATE INDEX IF NOT EXISTS idx_events_status ON integration_events_log(status);
CREATE INDEX IF NOT EXISTS idx_events_source_module ON integration_events_log(source, module);
CREATE INDEX IF NOT EXISTS idx_events_status_created ON integration_events_log(status, created_at);
CREATE INDEX IF NOT EXISTS idx_events_record ON integration_events_log(source, record_id);

-- Grant permissions (if needed)
-- GRANT ALL PRIVILEGES ON integration_events_log TO your_user;
"""


def create_event_log_table():
    """Create integration_events_log table."""
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
        
        print("Creating integration_events_log table...")
        cursor.execute(CREATE_EVENT_LOG_TABLE_SQL)
        conn.commit()
        
        print("✅ Table created successfully")
        
        # Verify table exists
        cursor.execute("""
            SELECT table_name 
            FROM information_schema.tables 
            WHERE table_name = 'integration_events_log'
        """)
        
        result = cursor.fetchone()
        if result:
            print(f"✅ Verified: Table '{result[0]}' exists")
            
            # Get column count
            cursor.execute("""
                SELECT COUNT(*) 
                FROM information_schema.columns 
                WHERE table_name = 'integration_events_log'
            """)
            col_count = cursor.fetchone()[0]
            print(f"✅ Columns: {col_count}")
        else:
            print("❌ Warning: Table not found after creation")
        
        cursor.close()
        conn.close()
        
        print("\n✅ Migration completed successfully")
        
    except Exception as e:
        print(f"❌ Error creating table: {e}")
        raise


if __name__ == "__main__":
    from dotenv import load_dotenv
    load_dotenv()
    
    create_event_log_table()
