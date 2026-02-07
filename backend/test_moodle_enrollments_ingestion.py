"""
Test Moodle Enrollments Ingestion Endpoint

This script:
1. Creates test courses (if needed)
2. Sends enrollment data to the backend
3. Verifies the results
"""

import httpx
from datetime import datetime
import json

# Configuration
BASE_URL = "http://localhost:8001"
ENDPOINT = f"{BASE_URL}/api/v1/moodle/enrollments"

# Test data
test_payload = {
    "enrollments": [
        {
            "id": 1001,  # Moodle enrollment ID
            "userid": 101,  # John Doe (created in previous test)
            "courseid": 201,  # Will create this course
            "roleid": 5,  # Student role
            "status": 0,  # Active
            "timestart": 1640000000,  # Jan 2022
            "timeend": 1700000000,  # Nov 2023
            "timecreated": 1640000000,
            "timemodified": 1640000000
        },
        {
            "id": 1002,
            "userid": 102,  # Jane Smith
            "courseid": 201,  # Same course
            "roleid": 5,
            "status": 0,  # Active
            "timestart": 1640000000,
            "timeend": 1700000000,
            "timecreated": 1640000000,
            "timemodified": 1640000000
        },
        {
            "id": 1003,
            "userid": 101,  # John Doe
            "courseid": 202,  # Different course
            "roleid": 5,
            "status": 1,  # Suspended
            "timestart": 1640000000,
            "timeend": 1700000000,
            "timecreated": 1640000000,
            "timemodified": 1640000000
        },
        {
            "id": 1004,
            "userid": 999,  # Non-existent user
            "courseid": 201,
            "roleid": 5,
            "status": 0,
            "timestart": 1640000000,
            "timeend": 1700000000,
            "timecreated": 1640000000,
            "timemodified": 1640000000
        }
    ],
    "timestamp": datetime.now().isoformat()
}


def create_test_courses():
    """Create test courses in the database"""
    print("\n🎓 Creating test courses...")
    import psycopg2
    from uuid import uuid4
    
    conn = psycopg2.connect(
        dbname='moodle_zoho_v2',
        user='postgres',
        password='NewStrongPassword',
        host='localhost',
        port=5432
    )
    
    cur = conn.cursor()
    
    courses = [
        {
            "id": str(uuid4()),
            "tenant_id": "default",
            "source": "moodle",
            "zoho_id": None,
            "moodle_class_id": "201",
            "name": "Introduction to Programming",
            "status": "active"
        },
        {
            "id": str(uuid4()),
            "tenant_id": "default",
            "source": "moodle",
            "zoho_id": None,
            "moodle_class_id": "202",
            "name": "Advanced Database Systems",
            "status": "active"
        }
    ]
    
    for course in courses:
        # Check if course exists
        cur.execute(
            "SELECT id FROM classes WHERE moodle_class_id = %s AND tenant_id = %s",
            (course["moodle_class_id"], course["tenant_id"])
        )
        if cur.fetchone():
            print(f"  ℹ️  Course {course['moodle_class_id']} already exists")
            continue
        
        # Insert course
        cur.execute("""
            INSERT INTO classes (
                id, tenant_id, source, zoho_id, moodle_class_id, 
                name, status, created_at, updated_at
            ) VALUES (%s, %s, %s, %s, %s, %s, %s, NOW(), NOW())
        """, (
            course["id"],
            course["tenant_id"],
            course["source"],
            course["zoho_id"],
            course["moodle_class_id"],
            course["name"],
            course["status"]
        ))
        print(f"  ✅ Created course: {course['name']} (Moodle ID: {course['moodle_class_id']})")
    
    conn.commit()
    cur.close()
    conn.close()
    print("✅ Test courses ready")


def test_enrollments_endpoint():
    """Test the enrollments ingestion endpoint"""
    print("\n" + "="*70)
    print("🧪 Testing Moodle Enrollments Ingestion Endpoint")
    print("="*70)
    
    print(f"\n📤 Sending {len(test_payload['enrollments'])} enrollments to {ENDPOINT}")
    print("📋 Payload preview:")
    print(json.dumps(test_payload, indent=2)[:500] + "\n...")
    
    try:
        response = httpx.post(
            ENDPOINT,
            json=test_payload,
            timeout=30.0
        )
        
        print(f"\n📥 Response Status: {response.status_code}")
        
        if response.status_code == 200:
            print("\n✅ SUCCESS!\n")
            data = response.json()
            
            # Print summary
            print("📊 Summary:")
            summary = data.get("summary", {})
            print(f"   Received: {summary.get('received', 0)}")
            print(f"   Created:  {summary.get('created', 0)}")
            print(f"   Updated:  {summary.get('updated', 0)}")
            print(f"   Skipped:  {summary.get('skipped', 0)}")
            print(f"   Errors:   {summary.get('errors', 0)}")
            
            # Print individual results
            print("\n📝 Individual Results:")
            for result in data.get("results", []):
                status = result.get("status", "unknown")
                enrollment_id = result.get("moodle_enrollment_id")
                user_id = result.get("moodle_user_id")
                course_id = result.get("moodle_course_id")
                message = result.get("message", "")
                db_id = result.get("db_id")
                
                if status == "created":
                    icon = "✅"
                elif status == "updated":
                    icon = "🔄"
                elif status == "skipped":
                    icon = "⏭️"
                else:
                    icon = "❌"
                
                print(f"   {icon} Enrollment {enrollment_id} (User: {user_id}, Course: {course_id}) - {status}")
                if message:
                    print(f"      └─ {message}")
                if db_id:
                    print(f"      └─ DB ID: {db_id}")
            
            print("\n" + "="*70)
            print("🎉 Test completed successfully!")
            print("="*70)
            
        else:
            print(f"\n❌ ERROR: {response.status_code}")
            print(response.text)
            
    except Exception as e:
        print(f"\n❌ Exception occurred: {str(e)}")


if __name__ == "__main__":
    # Step 1: Create test courses
    create_test_courses()
    
    # Step 2: Test enrollments endpoint
    test_enrollments_endpoint()
