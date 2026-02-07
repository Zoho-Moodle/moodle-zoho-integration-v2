"""
Initial Sync: Import all students from Zoho to local database

This script fetches all students from Zoho BTEC_Students module
and stores them in the local database for webhook processing.
"""

import asyncio
import logging
from sqlalchemy import text

from app.infra.zoho.client import ZohoClient
from app.infra.db.base import engine
from app.core.config import get_settings

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)


async def sync_students_from_zoho():
    """Fetch all students from Zoho and store in database."""
    
    settings = get_settings()
    
    # Initialize Zoho client
    logger.info("Initializing Zoho client...")
    zoho_client = ZohoClient(
        client_id=settings.ZOHO_CLIENT_ID,
        client_secret=settings.ZOHO_CLIENT_SECRET,
        refresh_token=settings.ZOHO_REFRESH_TOKEN
    )
    await zoho_client.initialize()
    
    try:
        # Fetch all students from Zoho
        logger.info("Fetching students from Zoho BTEC_Students module...")
        students = await zoho_client.get_records(
            module="BTEC_Students",
            max_results=1000  # Adjust as needed
        )
        
        logger.info(f"Found {len(students)} students in Zoho")
        
        if not students:
            logger.warning("No students found in Zoho!")
            return
        
        # Store in database
        with engine.connect() as conn:
            inserted = 0
            updated = 0
            
            for student in students:
                student_id = student.get('id')
                full_name = student.get('Full_Name', 'Unknown')
                first_name = student.get('First_Name', '')
                last_name = student.get('Last_Name', '')
                email = student.get('Academic_Email', '')
                student_id_number = student.get('Student_ID_Number', '')
                
                # Check if student exists
                check_query = text("""
                    SELECT id FROM students 
                    WHERE zoho_student_id = :zoho_id
                """)
                existing = conn.execute(
                    check_query,
                    {"zoho_id": student_id}
                ).fetchone()
                
                if existing:
                    # Update existing
                    update_query = text("""
                        UPDATE students 
                        SET first_name = :first_name,
                            last_name = :last_name,
                            email = :email,
                            student_id_number = :student_id_number,
                            updated_at = CURRENT_TIMESTAMP
                        WHERE zoho_student_id = :zoho_id
                    """)
                    conn.execute(update_query, {
                        "first_name": first_name,
                        "last_name": last_name,
                        "email": email,
                        "student_id_number": student_id_number,
                        "zoho_id": student_id
                    })
                    updated += 1
                    logger.info(f"  ✓ Updated: {full_name} ({student_id})")
                else:
                    # Insert new
                    insert_query = text("""
                        INSERT INTO students (
                            zoho_student_id, first_name, last_name, 
                            email, student_id_number, created_at, updated_at
                        ) VALUES (
                            :zoho_id, :first_name, :last_name,
                            :email, :student_id_number, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                        )
                    """)
                    conn.execute(insert_query, {
                        "zoho_id": student_id,
                        "first_name": first_name,
                        "last_name": last_name,
                        "email": email,
                        "student_id_number": student_id_number
                    })
                    inserted += 1
                    logger.info(f"  + Inserted: {full_name} ({student_id})")
            
            conn.commit()
            
            logger.info(f"\n{'='*60}")
            logger.info(f"✅ Sync Complete!")
            logger.info(f"   Inserted: {inserted} new students")
            logger.info(f"   Updated:  {updated} existing students")
            logger.info(f"   Total:    {len(students)} students")
            logger.info(f"{'='*60}\n")
    
    finally:
        await zoho_client.close()


if __name__ == "__main__":
    asyncio.run(sync_students_from_zoho())
