"""
Test Moodle Grades Ingestion Endpoint

This script:
1. Creates test units (if needed)
2. Sends grade data to the backend
3. Verifies the results
"""

import httpx
from datetime import datetime
import json

# Configuration
BASE_URL = "http://localhost:8001"
ENDPOINT = f"{BASE_URL}/api/v1/moodle/grades"

# Test data
test_payload = {
    "grades": [
        {
            "id": 5001,  # Moodle grade ID
            "userid": 101,  # John Doe
            "itemid": 301,  # Assignment 1
            "itemname": "Unit 1 - Assignment 1",
            "itemmodule": "assign",
            "finalgrade": 85.5,  # Distinction
            "rawgrade": 85.5,
            "feedback": "Excellent work! Well structured and comprehensive.",
            "grader": 2,
            "timecreated": 1640000000,
            "timemodified": 1700000000
        },
        {
            "id": 5002,
            "userid": 102,  # Jane Smith
            "itemid": 301,  # Same assignment
            "itemname": "Unit 1 - Assignment 1",
            "itemmodule": "assign",
            "finalgrade": 65.0,  # Merit
            "rawgrade": 65.0,
            "feedback": "Good work, but could improve on analysis.",
            "grader": 2,
            "timecreated": 1640000000,
            "timemodified": 1700000000
        },
        {
            "id": 5003,
            "userid": 101,  # John Doe
            "itemid": 302,  # Assignment 2
            "itemname": "Unit 1 - Assignment 2",
            "itemmodule": "assign",
            "finalgrade": 45.0,  # Pass
            "rawgrade": 45.0,
            "feedback": "Meets requirements but lacks depth.",
            "grader": 2,
            "timecreated": 1640000000,
            "timemodified": 1700000000
        },
        {
            "id": 5004,
            "userid": 999,  # Non-existent user
            "itemid": 301,
            "itemname": "Unit 1 - Assignment 1",
            "itemmodule": "assign",
            "finalgrade": 90.0,
            "rawgrade": 90.0,
            "feedback": "Great work!",
            "grader": 2,
            "timecreated": 1640000000,
            "timemodified": 1700000000
        }
    ],
    "timestamp": datetime.now().isoformat()
}


def create_test_units():
    """Create test units in the database"""
    print("\n📚 Creating test units...")
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
    
    # First, add moodle_unit_id column if it doesn't exist
    try:
        cur.execute("""
            ALTER TABLE units ADD COLUMN IF NOT EXISTS moodle_unit_id VARCHAR;
        """)
        conn.commit()
        print("  ✅ Added moodle_unit_id column to units table")
    except Exception as e:
        print(f"  ℹ️  Column might already exist: {e}")
        conn.rollback()
    
    units = [
        {
            "id": str(uuid4()),
            "tenant_id": "default",
            "source": "moodle",
            "zoho_id": None,
            "unit_code": "UNIT301",
            "unit_name": "Programming Fundamentals - Assignment 1",
            "description": "Introduction to programming concepts",
            "status": "Active",
            "moodle_unit_id": "301"
        },
        {
            "id": str(uuid4()),
            "tenant_id": "default",
            "source": "moodle",
            "zoho_id": None,
            "unit_code": "UNIT302",
            "unit_name": "Programming Fundamentals - Assignment 2",
            "description": "Advanced programming topics",
            "status": "Active",
            "moodle_unit_id": "302"
        }
    ]
    
    for unit in units:
        # Check if unit exists
        cur.execute(
            "SELECT id FROM units WHERE moodle_unit_id = %s AND tenant_id = %s",
            (unit["moodle_unit_id"], unit["tenant_id"])
        )
        if cur.fetchone():
            print(f"  ℹ️  Unit {unit['moodle_unit_id']} already exists")
            continue
        
        # Insert unit
        cur.execute("""
            INSERT INTO units (
                id, tenant_id, source, zoho_id, unit_code, 
                unit_name, description, status, moodle_unit_id, created_at, updated_at
            ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())
        """, (
            unit["id"],
            unit["tenant_id"],
            unit["source"],
            unit["zoho_id"],
            unit["unit_code"],
            unit["unit_name"],
            unit["description"],
            unit["status"],
            unit["moodle_unit_id"]
        ))
        print(f"  ✅ Created unit: {unit['unit_name']} (Moodle ID: {unit['moodle_unit_id']})")
    
    conn.commit()
    cur.close()
    conn.close()
    print("✅ Test units ready")


def test_grades_endpoint():
    """Test the grades ingestion endpoint"""
    print("\n" + "="*70)
    print("🧪 Testing Moodle Grades Ingestion Endpoint")
    print("="*70)
    
    print(f"\n📤 Sending {len(test_payload['grades'])} grades to {ENDPOINT}")
    print("📋 Payload preview:")
    print(json.dumps(test_payload, indent=2)[:600] + "\n...")
    
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
                grade_id = result.get("moodle_grade_id")
                user_id = result.get("moodle_user_id")
                item_id = result.get("moodle_item_id")
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
                
                print(f"   {icon} Grade {grade_id} (User: {user_id}, Item: {item_id}) - {status}")
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
    # Step 1: Create test units
    create_test_units()
    
    # Step 2: Test grades endpoint
    test_grades_endpoint()
