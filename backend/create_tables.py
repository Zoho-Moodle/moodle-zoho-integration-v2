"""
Create Phase 4 tables in PostgreSQL database
Run this after updating .env with correct DATABASE_URL
"""
import psycopg2
from app.core.config import settings

def create_tables():
    # Extract connection details from DATABASE_URL
    # Format: postgresql+psycopg2://user:pass@host:port/dbname
    url = settings.DATABASE_URL.replace("postgresql+psycopg2://", "")
    
    print(f"Connecting to: {url.split('@')[1]}")
    
    # Read SQL script
    with open("db_complete_schema.sql", "r", encoding="utf-8") as f:
        sql_script = f.read()
    
    # Connect and execute
    conn = psycopg2.connect(settings.DATABASE_URL.replace("+psycopg2", ""))
    conn.autocommit = True
    cursor = conn.cursor()
    
    try:
        print("Creating tables...")
        cursor.execute(sql_script)
        print("✅ Tables created successfully!")
        
        # List tables
        cursor.execute("""
            SELECT tablename FROM pg_catalog.pg_tables 
            WHERE schemaname = 'public' 
            ORDER BY tablename;
        """)
        tables = cursor.fetchall()
        print(f"\n📊 Tables in database ({len(tables)}):")
        for table in tables:
            print(f"  - {table[0]}")
            
    except Exception as e:
        print(f"❌ Error: {e}")
    finally:
        cursor.close()
        conn.close()

if __name__ == "__main__":
    create_tables()
