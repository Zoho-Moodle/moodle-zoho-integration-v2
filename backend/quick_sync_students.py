"""
Quick Initial Sync - Import students from Zoho
Simplified version without complex imports
"""

import requests
import os
import sys
import psycopg2

# Load environment
from dotenv import load_dotenv
load_dotenv()

def get_db_connection():
    """Get PostgreSQL connection from DATABASE_URL."""
    db_url = os.getenv('DATABASE_URL')
    # Format: postgresql+psycopg2://user:password@host:port/dbname
    db_url = db_url.replace("postgresql+psycopg2://", "postgresql://")
    return psycopg2.connect(db_url)


def get_zoho_token():
    """Get Zoho access token using refresh token."""
    client_id = os.getenv('ZOHO_CLIENT_ID')
    client_secret = os.getenv('ZOHO_CLIENT_SECRET')
    refresh_token = os.getenv('ZOHO_REFRESH_TOKEN')
    
    url = "https://accounts.zoho.com/oauth/v2/token"
    params = {
        'refresh_token': refresh_token,
        'client_id': client_id,
        'client_secret': client_secret,
        'grant_type': 'refresh_token'
    }
    
    response = requests.post(url, params=params)
    if response.status_code == 200:
        return response.json()['access_token']
    else:
        print(f"❌ Error getting token: {response.text}")
        return None


def fetch_students_from_zoho(access_token):
    """Fetch all students from Zoho."""
    url = "https://www.zohoapis.com/crm/v2/BTEC_Students"
    headers = {
        'Authorization': f'Zoho-oauthtoken {access_token}'
    }
    params = {
        'per_page': 200
    }
    
    response = requests.get(url, headers=headers, params=params)
    if response.status_code == 200:
        data = response.json()
        return data.get('data', [])
    else:
        print(f"❌ Error fetching students: {response.text}")
        return []


def store_students_in_db(students):
    """Store students in PostgreSQL database."""
    conn = get_db_connection()
    cursor = conn.cursor()
    
    inserted = 0
    updated = 0
    
    for student in students:
        zoho_id = student.get('id')
        first_name = student.get('First_Name', '')
        last_name = student.get('Last_Name', '')
        full_name = student.get('Full_Name', f"{first_name} {last_name}")
        email = student.get('Academic_Email', '')
        student_id_number = student.get('Student_ID_Number', '')
        phone = student.get('Phone_Number', '')
        
        # Check if exists
        cursor.execute("SELECT id FROM students WHERE zoho_id = %s", (zoho_id,))
        existing = cursor.fetchone()
        
        if existing:
            # Update
            cursor.execute("""
                UPDATE students 
                SET display_name = %s, 
                    academic_email = %s, 
                    phone = %s,
                    userid = %s,
                    updated_at = CURRENT_TIMESTAMP
                WHERE zoho_id = %s
            """, (full_name, email, phone, student_id_number, zoho_id))
            updated += 1
            print(f"  ✓ Updated: {full_name} ({zoho_id})")
        else:
            # Insert
            cursor.execute("""
                INSERT INTO students (
                    id, tenant_id, source, zoho_id, 
                    display_name, academic_email, phone, userid
                )
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
            """, (
                zoho_id,  # id = zoho_id
                'default', # tenant_id
                'zoho',    # source
                zoho_id,   # zoho_id
                full_name, # display_name
                email,     # academic_email
                phone,     # phone
                student_id_number  # userid (Student_ID_Number)
            ))
            inserted += 1
            print(f"  + Inserted: {full_name} ({zoho_id})")
    
    conn.commit()
    cursor.close()
    conn.close()
    
    return inserted, updated


if __name__ == "__main__":
    print("=" * 60)
    print("🔄 Syncing Students from Zoho to Local Database")
    print("=" * 60)
    
    # Step 1: Get access token
    print("\n1️⃣ Getting Zoho access token...")
    token = get_zoho_token()
    if not token:
        print("❌ Failed to get access token!")
        sys.exit(1)
    print("✅ Access token obtained")
    
    # Step 2: Fetch students
    print("\n2️⃣ Fetching students from Zoho...")
    students = fetch_students_from_zoho(token)
    print(f"✅ Found {len(students)} students in Zoho")
    
    if not students:
        print("❌ No students found!")
        sys.exit(1)
    
    # Step 3: Store in database
    print("\n3️⃣ Storing students in database...")
    inserted, updated = store_students_in_db(students)
    
    # Summary
    print("\n" + "=" * 60)
    print("✅ SYNC COMPLETE!")
    print(f"   📥 Inserted: {inserted} new students")
    print(f"   🔄 Updated:  {updated} existing students")
    print(f"   📊 Total:    {len(students)} students")
    print("=" * 60)
    print("\n🎉 الآن الطلاب موجودين في قاعدة البيانات!")
    print("✅ الـ webhooks بتقدر تعمل update للبيانات")
