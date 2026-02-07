"""
Test Moodle Users Ingestion Endpoint

This script tests the new /api/v1/moodle/users endpoint
"""

import httpx
import json
from datetime import datetime

# Sample Moodle users data
moodle_users_payload = {
    "users": [
        {
            "id": 101,
            "username": "john.doe@example.com",
            "firstname": "John",
            "lastname": "Doe",
            "email": "john.doe@example.com",
            "idnumber": "STU12345",
            "phone1": "+1234567890",
            "city": "London",
            "country": "GB",
            "suspended": False,
            "deleted": False,
            "timecreated": 1640000000,
            "timemodified": 1700000000
        },
        {
            "id": 102,
            "username": "jane.smith@example.com",
            "firstname": "Jane",
            "lastname": "Smith",
            "email": "jane.smith@example.com",
            "idnumber": "STU12346",
            "phone1": "+1234567891",
            "city": "Manchester",
            "country": "GB",
            "suspended": False,
            "deleted": False,
            "timecreated": 1640000100,
            "timemodified": 1700000100
        },
        {
            "id": 103,
            "username": "suspended.user@example.com",
            "firstname": "Suspended",
            "lastname": "User",
            "email": "suspended.user@example.com",
            "suspended": True,  # This should be skipped
            "deleted": False
        }
    ],
    "source": "moodle_test_script",
    "timestamp": datetime.utcnow().isoformat()
}

def test_moodle_users_endpoint():
    """Test the Moodle users ingestion endpoint"""
    
    print("=" * 70)
    print("🧪 Testing Moodle Users Ingestion Endpoint")
    print("=" * 70)
    
    url = "http://localhost:8001/api/v1/moodle/users"
    
    print(f"\n📤 Sending {len(moodle_users_payload['users'])} users to {url}")
    print(f"📋 Payload preview:")
    print(json.dumps(moodle_users_payload, indent=2)[:500] + "...")
    
    try:
        response = httpx.post(
            url,
            json=moodle_users_payload,
            headers={
                "Content-Type": "application/json",
                "X-Tenant-ID": "default"
            },
            timeout=30.0
        )
        
        print(f"\n📥 Response Status: {response.status_code}")
        
        if response.status_code == 200:
            result = response.json()
            print(f"\n✅ SUCCESS!")
            print(f"\n📊 Summary:")
            print(f"   Received: {result['received']}")
            print(f"   Created:  {result['summary']['created']}")
            print(f"   Updated:  {result['summary']['updated']}")
            print(f"   Skipped:  {result['summary']['skipped']}")
            print(f"   Errors:   {result['summary']['error']}")
            
            print(f"\n📝 Individual Results:")
            for r in result['results']:
                status_emoji = {
                    'created': '✅',
                    'updated': '🔄',
                    'skipped': '⏭️',
                    'error': '❌'
                }.get(r['status'], '❓')
                
                print(f"   {status_emoji} {r['username']} (Moodle ID: {r['moodle_id']}) - {r['status']}")
                if r.get('message'):
                    print(f"      └─ {r['message']}")
                if r.get('local_id'):
                    print(f"      └─ Local DB ID: {r['local_id']}")
            
            print(f"\n" + "=" * 70)
            print("🎉 Test completed successfully!")
            print("=" * 70)
            
        else:
            print(f"\n❌ Error Response:")
            print(response.text)
            
    except httpx.ConnectError:
        print(f"\n❌ Connection Error!")
        print(f"   Make sure the server is running: python start_server.py")
        
    except Exception as e:
        print(f"\n❌ Unexpected Error: {str(e)}")


if __name__ == "__main__":
    test_moodle_users_endpoint()
