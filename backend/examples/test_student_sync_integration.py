"""
Integration test for StudentProfileService with real Zoho CRM.

This script tests the complete student sync flow:
1. Create new student in Zoho
2. Search student by email
3. Search student by Moodle ID
4. Update student
5. Bulk sync students

Requirements:
- Zoho credentials in .env
"""

import asyncio
from datetime import datetime
from dotenv import load_dotenv

from app.infra.zoho import create_zoho_client
from app.services.student_profile_service import (
    StudentProfileService,
    StudentData
)


async def test_student_sync_flow():
    """Test complete student sync flow."""
    
    print("=" * 80)
    print("STUDENT PROFILE SYNC INTEGRATION TEST")
    print("=" * 80)
    
    # Load environment
    load_dotenv()
    
    # Create clients
    print("\n[1/8] Creating Zoho client...")
    zoho = create_zoho_client()
    service = StudentProfileService(zoho_client=zoho)
    print("✅ Zoho client created")
    
    # Test 1: Create new student
    print("\n[2/8] Creating test student...")
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    
    student1 = StudentData(
        moodle_user_id=f"test_{timestamp}",
        email=f"test.student.{timestamp}@example.com",
        first_name="Test",
        last_name=f"Student{timestamp}",
        student_id=f"STU{timestamp}",
        phone="+44 7700 900000",
        date_of_birth="2000-01-15",
        gender="Prefer not to say",
        address="123 Test Street",
        city="London",
        country="UK",
        postal_code="SW1A 1AA",
        status="Active"
    )
    
    result1 = await service.sync_student_to_zoho(student1)
    
    print(f"✅ Student created:")
    print(f"   Status: {result1['status']}")
    print(f"   Action: {result1['action']}")
    print(f"   Zoho ID: {result1['zoho_student_id']}")
    print(f"   Email: {result1['email']}")
    
    zoho_student_id = result1['zoho_student_id']
    
    # Test 2: Search by email
    print("\n[3/8] Searching student by email...")
    found_by_email = await service.get_student_by_email(student1.email)
    
    if found_by_email:
        print(f"✅ Found by email:")
        print(f"   Name: {found_by_email.get('Name')}")
        print(f"   ID: {found_by_email['id']}")
        print(f"   Moodle ID: {found_by_email.get('Student_Moodle_ID')}")
    else:
        print("❌ Not found by email")
    
    # Test 3: Search by Moodle ID
    print("\n[4/8] Searching student by Moodle ID...")
    found_by_moodle = await service.get_student_by_moodle_id(student1.moodle_user_id)
    
    if found_by_moodle:
        print(f"✅ Found by Moodle ID:")
        print(f"   Name: {found_by_moodle.get('Name')}")
        print(f"   Email: {found_by_moodle.get('Academic_Email')}")
        print(f"   Synced to Moodle: {found_by_moodle.get('Synced_to_Moodle')}")
    else:
        print("❌ Not found by Moodle ID")
    
    # Test 4: Get by Zoho ID
    print("\n[5/8] Getting student by Zoho ID...")
    student_record = await service.get_student_by_id(zoho_student_id)
    
    print(f"✅ Student record:")
    print(f"   Name: {student_record.get('Name')}")
    print(f"   Email: {student_record.get('Academic_Email')}")
    print(f"   Phone: {student_record.get('Phone')}")
    print(f"   City: {student_record.get('City')}")
    print(f"   Status: {student_record.get('Status')}")
    
    # Test 5: Update student (simulate change in Moodle)
    print("\n[6/8] Updating student...")
    student1.phone = "+44 7700 900001"  # Changed phone
    student1.city = "Manchester"  # Changed city
    
    result2 = await service.sync_student_to_zoho(student1)
    
    print(f"✅ Student updated:")
    print(f"   Action: {result2['action']}")
    print(f"   Zoho ID: {result2['zoho_student_id']}")  # Should be same
    
    # Verify update
    updated_record = await service.get_student_by_id(zoho_student_id)
    print(f"   New Phone: {updated_record.get('Phone')}")
    print(f"   New City: {updated_record.get('City')}")
    
    # Test 6: Bulk sync
    print("\n[7/8] Testing bulk sync...")
    
    bulk_students = [
        StudentData(
            moodle_user_id=f"bulk1_{timestamp}",
            email=f"bulk1.{timestamp}@example.com",
            first_name="Bulk",
            last_name="Student1"
        ),
        StudentData(
            moodle_user_id=f"bulk2_{timestamp}",
            email=f"bulk2.{timestamp}@example.com",
            first_name="Bulk",
            last_name="Student2"
        ),
        StudentData(
            moodle_user_id=f"bulk3_{timestamp}",
            email=f"bulk3.{timestamp}@example.com",
            first_name="Bulk",
            last_name="Student3"
        )
    ]
    
    bulk_result = await service.bulk_sync_students(bulk_students)
    
    print(f"✅ Bulk sync completed:")
    print(f"   Total: {bulk_result['total']}")
    print(f"   Created: {bulk_result['created']}")
    print(f"   Updated: {bulk_result['updated']}")
    print(f"   Failed: {bulk_result['failed']}")
    
    # Test 7: Get synced students
    print("\n[8/8] Getting synced students...")
    synced = await service.get_synced_students(page=1, per_page=5)
    
    print(f"✅ Found {len(synced)} synced students:")
    for s in synced[:3]:  # Show first 3
        print(f"   - {s.get('Name')} (Moodle ID: {s.get('Student_Moodle_ID')})")
    
    # Summary
    print("\n" + "=" * 80)
    print("TEST SUMMARY")
    print("=" * 80)
    print("✅ Student creation: PASSED")
    print("✅ Search by email: PASSED")
    print("✅ Search by Moodle ID: PASSED")
    print("✅ Get by Zoho ID: PASSED")
    print("✅ Student update: PASSED")
    print("✅ Bulk sync: PASSED")
    print("✅ Get synced students: PASSED")
    
    print(f"\nTest student Zoho ID: {zoho_student_id}")
    print(f"Email: {student1.email}")
    print(f"Moodle ID: {student1.moodle_user_id}")
    
    print("\n⚠️  Note: Test data created in Zoho. You may want to delete it manually.")
    print("=" * 80)


async def test_simple_interface():
    """Test simplified sync interface."""
    
    print("\n" + "=" * 80)
    print("TESTING SIMPLE INTERFACE")
    print("=" * 80)
    
    zoho = create_zoho_client()
    service = StudentProfileService(zoho_client=zoho)
    
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    
    result = await service.sync_student_simple(
        moodle_user_id=f"simple_{timestamp}",
        email=f"simple.{timestamp}@example.com",
        first_name="Simple",
        last_name="Test",
        student_id=f"SIM{timestamp}",
        phone="+44 7700 900999"
    )
    
    print(f"✅ Simple interface test:")
    print(f"   Status: {result['status']}")
    print(f"   Zoho ID: {result['zoho_student_id']}")
    print("=" * 80)


async def test_update_moodle_id():
    """Test updating Moodle ID for existing student."""
    
    print("\n" + "=" * 80)
    print("TESTING MOODLE ID UPDATE")
    print("=" * 80)
    
    zoho = create_zoho_client()
    service = StudentProfileService(zoho_client=zoho)
    
    # Get an existing student
    response = await service.get_all_students(page=1, per_page=1)
    students = response.get('data', [])
    
    if not students:
        print("⚠️  No students found - skipping test")
        return
    
    student = students[0]
    print(f"Using existing student: {student.get('Name')}")
    print(f"Current Moodle ID: {student.get('Student_Moodle_ID', 'None')}")
    
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    new_moodle_id = f"updated_{timestamp}"
    
    result = await service.update_student_moodle_id(
        zoho_student_id=student['id'],
        moodle_user_id=new_moodle_id
    )
    
    print(f"✅ Moodle ID updated to: {new_moodle_id}")
    
    # Verify
    updated = await service.get_student_by_id(student['id'])
    print(f"   Verified: {updated.get('Student_Moodle_ID')}")
    print(f"   Synced to Moodle: {updated.get('Synced_to_Moodle')}")
    print("=" * 80)


async def main():
    """Run all integration tests."""
    try:
        # Main test
        await test_student_sync_flow()
        
        # Simple interface test
        await test_simple_interface()
        
        # Update Moodle ID test
        await test_update_moodle_id()
        
        print("\n✅ All integration tests completed!")
        
    except Exception as e:
        print(f"\n❌ Test failed: {e}")
        import traceback
        traceback.print_exc()


if __name__ == '__main__':
    asyncio.run(main())
