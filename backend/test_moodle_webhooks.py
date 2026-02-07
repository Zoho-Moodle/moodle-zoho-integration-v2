"""
Test Moodle Webhook Events

This script tests all Moodle webhook endpoints by simulating real-time events.
"""

import httpx
from datetime import datetime
import time

BASE_URL = "http://localhost:8001"

def test_user_created():
    """Test user_created webhook"""
    print("\n" + "="*70)
    print("👤 Testing: user_created webhook")
    print("="*70)
    
    payload = {
        "eventname": "\\core\\event\\user_created",
        "userid": 103,
        "username": "alice.johnson@example.com",
        "firstname": "Alice",
        "lastname": "Johnson",
        "email": "alice.johnson@example.com",
        "idnumber": "STU12347",
        "phone1": "+1234567892",
        "city": "Birmingham",
        "country": "GB",
        "suspended": False,
        "deleted": False,
        "timecreated": int(time.time()),
        "timemodified": int(time.time())
    }
    
    try:
        response = httpx.post(
            f"{BASE_URL}/api/v1/events/moodle/user_created",
            json=payload,
            timeout=10.0
        )
        
        print(f"📥 Response: {response.status_code}")
        data = response.json()
        
        if response.status_code == 200:
            print(f"✅ {data['message']}")
            if data.get('event_id'):
                print(f"   Event ID: {data['event_id']}")
        else:
            print(f"❌ Error: {data}")
            
    except Exception as e:
        print(f"❌ Exception: {str(e)}")


def test_user_updated():
    """Test user_updated webhook"""
    print("\n" + "="*70)
    print("🔄 Testing: user_updated webhook")
    print("="*70)
    
    payload = {
        "eventname": "\\core\\event\\user_updated",
        "userid": 101,  # Update John Doe
        "username": "john.doe.updated@example.com",
        "firstname": "John",
        "lastname": "Doe",
        "email": "john.doe.updated@example.com",
        "idnumber": "STU12345",
        "phone1": "+1234567899",  # Updated phone
        "city": "Manchester",  # Updated city
        "country": "GB",
        "suspended": False,
        "deleted": False,
        "timecreated": 1640000000,
        "timemodified": int(time.time())
    }
    
    try:
        response = httpx.post(
            f"{BASE_URL}/api/v1/events/moodle/user_updated",
            json=payload,
            timeout=10.0
        )
        
        print(f"📥 Response: {response.status_code}")
        data = response.json()
        
        if response.status_code == 200:
            print(f"✅ {data['message']}")
        else:
            print(f"❌ Error: {data}")
            
    except Exception as e:
        print(f"❌ Exception: {str(e)}")


def test_user_enrolled():
    """Test user_enrolled webhook"""
    print("\n" + "="*70)
    print("📚 Testing: user_enrolled webhook")
    print("="*70)
    
    payload = {
        "eventname": "\\core\\event\\user_enrolment_created",
        "enrollmentid": 1005,
        "userid": 103,  # Alice Johnson
        "courseid": 201,  # Programming course
        "roleid": 5,  # Student
        "status": 0,  # Active
        "timestart": int(time.time()),
        "timeend": None,
        "timecreated": int(time.time())
    }
    
    try:
        response = httpx.post(
            f"{BASE_URL}/api/v1/events/moodle/user_enrolled",
            json=payload,
            timeout=10.0
        )
        
        print(f"📥 Response: {response.status_code}")
        data = response.json()
        
        if response.status_code == 200:
            print(f"✅ {data['message']}")
            if data.get('event_id'):
                print(f"   Event ID: {data['event_id']}")
        else:
            print(f"❌ Error: {data}")
            
    except Exception as e:
        print(f"❌ Exception: {str(e)}")


def test_grade_updated():
    """Test grade_updated webhook"""
    print("\n" + "="*70)
    print("📊 Testing: grade_updated webhook")
    print("="*70)
    
    payload = {
        "eventname": "\\core\\event\\user_graded",
        "gradeid": 5005,
        "userid": 103,  # Alice Johnson
        "itemid": 301,  # Assignment 1
        "itemname": "Programming Assignment 1",
        "finalgrade": 72.0,  # Distinction
        "feedback": "Excellent submission with great attention to detail!",
        "grader": 2,
        "timecreated": int(time.time()),
        "timemodified": int(time.time())
    }
    
    try:
        response = httpx.post(
            f"{BASE_URL}/api/v1/events/moodle/grade_updated",
            json=payload,
            timeout=10.0
        )
        
        print(f"📥 Response: {response.status_code}")
        data = response.json()
        
        if response.status_code == 200:
            print(f"✅ {data['message']}")
            if data.get('event_id'):
                print(f"   Event ID: {data['event_id']}")
        else:
            print(f"❌ Error: {data}")
            
    except Exception as e:
        print(f"❌ Exception: {str(e)}")


def test_health():
    """Test webhook health endpoint"""
    print("\n" + "="*70)
    print("🏥 Testing: health check")
    print("="*70)
    
    try:
        response = httpx.get(
            f"{BASE_URL}/api/v1/events/moodle/health",
            timeout=10.0
        )
        
        print(f"📥 Response: {response.status_code}")
        data = response.json()
        
        if response.status_code == 200:
            print(f"✅ Service: {data['service']}")
            print(f"   Status: {data['status']}")
            print(f"   Available endpoints:")
            for endpoint in data['endpoints']:
                print(f"     • {endpoint}")
        else:
            print(f"❌ Error: {data}")
            
    except Exception as e:
        print(f"❌ Exception: {str(e)}")


def main():
    """Run all tests"""
    print("\n" + "="*70)
    print("🧪 MOODLE WEBHOOK EVENTS TEST SUITE")
    print("="*70)
    
    # Run tests in sequence
    test_health()
    time.sleep(1)
    
    test_user_created()
    time.sleep(1)
    
    test_user_updated()
    time.sleep(1)
    
    test_user_enrolled()
    time.sleep(1)
    
    test_grade_updated()
    
    print("\n" + "="*70)
    print("🎉 All webhook tests completed!")
    print("="*70)
    
    # Show database summary
    print("\n📊 Database Summary:")
    import psycopg2
    conn = psycopg2.connect(
        dbname='moodle_zoho_v2',
        user='postgres',
        password='NewStrongPassword',
        host='localhost',
        port=5432
    )
    cur = conn.cursor()
    
    cur.execute("SELECT COUNT(*) FROM students WHERE source = 'moodle'")
    print(f"   Students (Moodle): {cur.fetchone()[0]}")
    
    cur.execute("SELECT COUNT(*) FROM enrollments WHERE source = 'moodle'")
    print(f"   Enrollments (Moodle): {cur.fetchone()[0]}")
    
    cur.execute("SELECT COUNT(*) FROM grades WHERE source = 'moodle'")
    print(f"   Grades (Moodle): {cur.fetchone()[0]}")
    
    cur.close()
    conn.close()


if __name__ == "__main__":
    main()
