"""
Restore NOT NULL constraint on academic_email column
Since we disabled student creation from Zoho webhooks, all students must come from Moodle with an email
"""
from app.infra.db.base import engine
import sqlalchemy as sa

print("🔧 Restoring NOT NULL constraint on academic_email...")

with engine.connect() as conn:
    # First check if there are any NULL values
    result = conn.execute(sa.text('SELECT COUNT(*) FROM students WHERE academic_email IS NULL;'))
    null_count = result.scalar()
    
    if null_count > 0:
        print(f"⚠️ Found {null_count} students with NULL email. Setting default value...")
        conn.execute(sa.text("UPDATE students SET academic_email = 'pending@abchorizon.com' WHERE academic_email IS NULL;"))
        conn.commit()
    
    # Now add NOT NULL constraint
    conn.execute(sa.text('ALTER TABLE students ALTER COLUMN academic_email SET NOT NULL;'))
    conn.commit()
    
print("✅ Done! academic_email is now NOT NULL again")
