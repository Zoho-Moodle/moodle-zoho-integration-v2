"""
Integration Test for Event Router

Tests webhook endpoints and event processing flow.
"""

import asyncio
import os
from datetime import datetime
import json

# Test configurations
ZOHO_STUDENT_WEBHOOK_URL = "http://localhost:8001/api/v1/events/zoho/student"
ZOHO_ENROLLMENT_WEBHOOK_URL = "http://localhost:8001/api/v1/events/zoho/enrollment"
EVENT_STATS_URL = "http://localhost:8001/api/v1/events/stats"
EVENT_HEALTH_URL = "http://localhost:8001/api/v1/events/health"


async def test_event_router_health():
    """Test event router health check."""
    import httpx
    
    print("\n" + "="*80)
    print("EVENT ROUTER HEALTH CHECK")
    print("="*80)
    
    try:
        async with httpx.AsyncClient() as client:
            response = await client.get(EVENT_HEALTH_URL)
            
            if response.status_code == 200:
                data = response.json()
                print("✅ Event router is healthy")
                print(f"\nAvailable endpoints:")
                print(f"  Zoho: {', '.join(data['endpoints']['zoho'])}")
                print(f"  Moodle: {', '.join(data['endpoints']['moodle'])}")
            else:
                print(f"❌ Health check failed: {response.status_code}")
                
    except Exception as e:
        print(f"❌ Error: {e}")


async def test_zoho_student_webhook():
    """Test Zoho student webhook endpoint."""
    import httpx
    
    print("\n" + "="*80)
    print("TESTING ZOHO STUDENT WEBHOOK")
    print("="*80)
    
    # Sample Zoho webhook payload
    payload = {
        "notification_id": f"test_student_{datetime.utcnow().timestamp()}",
        "timestamp": datetime.utcnow().isoformat(),
        "module": "BTEC_Students",
        "operation": "update",
        "record_id": "5398830000123893227",  # Test student ID
        "id": "5398830000123893227",
        "data": {
            "id": "5398830000123893227",
            "Name": "A01B4083C",
            "Academic_Email": "test.student@example.com",
            "Student_ID_Number": "A01B4083C"
        },
        "user_id": "test_user",
        "org_id": "test_org"
    }
    
    try:
        async with httpx.AsyncClient() as client:
            print(f"\n📤 Sending webhook payload...")
            print(f"   Event ID: {payload['notification_id']}")
            print(f"   Module: {payload['module']}")
            print(f"   Operation: {payload['operation']}")
            print(f"   Record ID: {payload['record_id']}")
            
            response = await client.post(
                ZOHO_STUDENT_WEBHOOK_URL,
                json=payload,
                timeout=10.0
            )
            
            if response.status_code == 200:
                result = response.json()
                print("\n✅ Webhook accepted")
                print(f"   Event ID: {result.get('event_id')}")
                print(f"   Status: {result.get('status')}")
                print(f"   Message: {result.get('message')}")
            else:
                print(f"\n❌ Webhook failed: {response.status_code}")
                print(f"   Response: {response.text}")
                
    except Exception as e:
        print(f"\n❌ Error: {e}")


async def test_zoho_enrollment_webhook():
    """Test Zoho enrollment webhook endpoint."""
    import httpx
    
    print("\n" + "="*80)
    print("TESTING ZOHO ENROLLMENT WEBHOOK")
    print("="*80)
    
    # Sample enrollment webhook payload
    payload = {
        "notification_id": f"test_enrollment_{datetime.utcnow().timestamp()}",
        "timestamp": datetime.utcnow().isoformat(),
        "module": "BTEC_Enrollments",
        "operation": "update",
        "record_id": "5398830000123739037",  # Test enrollment ID
        "id": "5398830000123739037",
        "data": {
            "id": "5398830000123739037",
            "Enrolled_Students": {
                "id": "5398830000123893227",
                "name": "A01B4083C"
            },
            "Classes": {
                "id": "5398830000123893174",
                "name": "ABCC1475"
            },
            "Enrollment_Status": "Active"
        }
    }
    
    try:
        async with httpx.AsyncClient() as client:
            print(f"\n📤 Sending webhook payload...")
            print(f"   Event ID: {payload['notification_id']}")
            print(f"   Module: {payload['module']}")
            print(f"   Operation: {payload['operation']}")
            
            response = await client.post(
                ZOHO_ENROLLMENT_WEBHOOK_URL,
                json=payload,
                timeout=10.0
            )
            
            if response.status_code == 200:
                result = response.json()
                print("\n✅ Webhook accepted")
                print(f"   Event ID: {result.get('event_id')}")
                print(f"   Status: {result.get('status')}")
                print(f"   Message: {result.get('message')}")
            else:
                print(f"\n❌ Webhook failed: {response.status_code}")
                print(f"   Response: {response.text}")
                
    except Exception as e:
        print(f"\n❌ Error: {e}")


async def test_event_stats():
    """Test event statistics endpoint."""
    import httpx
    
    print("\n" + "="*80)
    print("EVENT PROCESSING STATISTICS")
    print("="*80)
    
    try:
        async with httpx.AsyncClient() as client:
            response = await client.get(EVENT_STATS_URL)
            
            if response.status_code == 200:
                stats = response.json()
                print(f"\n✅ Total events: {stats['total_events']}")
                
                print(f"\n📊 By Status:")
                for status, count in stats.get('by_status', {}).items():
                    print(f"   {status}: {count}")
                
                print(f"\n📊 By Source:")
                for source, count in stats.get('by_source', {}).items():
                    print(f"   {source}: {count}")
                
                print(f"\n📋 Recent Events:")
                for event in stats.get('recent_events', [])[:5]:
                    print(f"   [{event['status']}] {event['source']}.{event['module']} - {event['event_id'][:20]}...")
            else:
                print(f"❌ Stats request failed: {response.status_code}")
                
    except Exception as e:
        print(f"❌ Error: {e}")


async def test_duplicate_prevention():
    """Test duplicate event prevention."""
    import httpx
    
    print("\n" + "="*80)
    print("TESTING DUPLICATE EVENT PREVENTION")
    print("="*80)
    
    # Same event ID - should be detected as duplicate
    event_id = f"duplicate_test_{datetime.utcnow().timestamp()}"
    
    payload = {
        "notification_id": event_id,
        "timestamp": datetime.utcnow().isoformat(),
        "module": "BTEC_Students",
        "operation": "update",
        "record_id": "test_student_123",
        "id": "test_student_123"
    }
    
    try:
        async with httpx.AsyncClient() as client:
            # Send first webhook
            print(f"\n📤 Sending first webhook (Event ID: {event_id[:30]}...)")
            response1 = await client.post(
                ZOHO_STUDENT_WEBHOOK_URL,
                json=payload,
                timeout=10.0
            )
            
            if response1.status_code == 200:
                print("✅ First webhook accepted")
            
            # Wait a moment
            await asyncio.sleep(2)
            
            # Send duplicate webhook (same event_id)
            print(f"\n📤 Sending duplicate webhook (same Event ID)")
            response2 = await client.post(
                ZOHO_STUDENT_WEBHOOK_URL,
                json=payload,
                timeout=10.0
            )
            
            if response2.status_code == 200:
                result = response2.json()
                print("✅ Duplicate webhook handled")
                print(f"   Note: Event should be marked as duplicate in database")
            
    except Exception as e:
        print(f"❌ Error: {e}")


async def run_all_tests():
    """Run all event router tests."""
    
    print("\n" + "="*80)
    print("EVENT ROUTER INTEGRATION TESTS")
    print("="*80)
    print("\n⚠️  Make sure the FastAPI server is running on http://localhost:8001")
    print("   Run: cd backend && python start_server.py\n")
    
    input("Press Enter to start tests...")
    
    # Run tests
    await test_event_router_health()
    await asyncio.sleep(1)
    
    await test_zoho_student_webhook()
    await asyncio.sleep(2)
    
    await test_zoho_enrollment_webhook()
    await asyncio.sleep(2)
    
    await test_duplicate_prevention()
    await asyncio.sleep(2)
    
    await test_event_stats()
    
    print("\n" + "="*80)
    print("TEST SUMMARY")
    print("="*80)
    print("✅ Health check: COMPLETED")
    print("✅ Student webhook: COMPLETED")
    print("✅ Enrollment webhook: COMPLETED")
    print("✅ Duplicate prevention: COMPLETED")
    print("✅ Event stats: COMPLETED")
    print("\n💡 Check event stats endpoint for processing results")
    print("="*80)


if __name__ == "__main__":
    # Note: Requires httpx for async HTTP requests
    # Install: pip install httpx
    
    try:
        import httpx
    except ImportError:
        print("❌ Error: httpx not installed")
        print("   Run: pip install httpx")
        exit(1)
    
    asyncio.run(run_all_tests())
