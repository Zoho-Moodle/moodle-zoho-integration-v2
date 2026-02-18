"""
Quick fix: Make academic_email nullable in students table
"""
from app.infra.db.base import engine
import sqlalchemy as sa

print("🔧 Making academic_email nullable...")

with engine.connect() as conn:
    conn.execute(sa.text('ALTER TABLE students ALTER COLUMN academic_email DROP NOT NULL;'))
    conn.commit()
    
print("✅ Done! academic_email is now nullable")
