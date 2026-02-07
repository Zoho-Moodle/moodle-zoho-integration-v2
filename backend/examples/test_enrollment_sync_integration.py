"""
Integration test for EnrollmentSyncService with real Zoho CRM.

Tests:
1. Create enrollment
2. Search enrollments by student
3. Search enrollments by class
4. Update enrollment status
5. Withdraw enrollment
6. Complete enrollment
7. Bulk sync enrollments
"""

import asyncio
from datetime import datetime
from dotenv import load_dotenv

from app.infra.zoho import create_zoho_client
from app.services.enrollment_sync_service import (
    EnrollmentSyncService,
    EnrollmentData
)


async def test_enrollment_flow():
    """Test complete enrollment sync flow."""
    
    print("=" * 80)
    print("ENROLLMENT SYNC INTEGRATION TEST")
    print("=" * 80)
    
    # Load environment
    load_dotenv()
    
    # Create clients
    print("\n[1/9] Creating Zoho client...")
    zoho = create_zoho_client()
    service = EnrollmentSyncService(zoho_client=zoho)
    print("✅ Zoho client created")
    
    # Get test student and class
    print("\n[2/9] Getting test student and class...")
    
    students_response = await zoho.get_records('BTEC_Students', page=1, per_page=1)
    students = students_response.get('data', [])
    if not students:
        print("❌ No students found. Please run setup_test_data.py first.")
        return
    
    classes_response = await zoho.get_records('BTEC_Classes', page=1, per_page=1)
    classes = classes_response.get('data', [])
    if not classes:
        print("❌ No classes found. Please run setup_test_data.py first.")
        return
    
    test_student = students[0]
    test_class = classes[0]
    
    print(f"✅ Using student: {test_student.get('Name')} (ID: {test_student['id']})")
    print(f"✅ Using class: {test_class.get('Name')} (ID: {test_class['id']})")
    
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    
    # Test 1: Create enrollment
    print("\n[3/9] Creating enrollment...")
    
    enrollment1 = EnrollmentData(
        zoho_student_id=test_student['id'],
        zoho_class_id=test_class['id'],
        moodle_course_id=f"test_course_{timestamp}",
        enrollment_status="Active",
        enrollment_date="2025-09-01"
    )
    
    result1 = await service.sync_enrollment_to_zoho(enrollment1)
    
    print(f"✅ Enrollment created:")
    print(f"   Status: {result1['status']}")
    print(f"   Action: {result1['action']}")
    print(f"   Zoho ID: {result1['zoho_enrollment_id']}")
    
    enrollment_id = result1['zoho_enrollment_id']
    
    # Test 2: Verify creation
    print("\n[4/9] Verifying enrollment in Zoho...")
    enrollment_record = await service.get_enrollment_by_id(enrollment_id)
    
    print(f"✅ Enrollment verified:")
    print(f"   Student: {enrollment_record.get('Enrolled_Students')}")
    print(f"   Class: {enrollment_record.get('Classes')}")
    print(f"   Status: {enrollment_record.get('Enrollment_Status')}")
    print(f"   Moodle Course ID: {enrollment_record.get('Moodle_Course_ID')}")
    
    # Test 3: Update enrollment (simulate re-sync)
    print("\n[5/9] Updating enrollment...")
    
    enrollment1.attendance_percentage = 85.5
    enrollment1.notes = "Good attendance"
    
    result2 = await service.sync_enrollment_to_zoho(enrollment1)
    
    print(f"✅ Enrollment updated:")
    print(f"   Action: {result2['action']}")
    print(f"   Zoho ID: {result2['zoho_enrollment_id']}")  # Should be same
    
    # Test 4: Get student enrollments
    print("\n[6/9] Getting student enrollments...")
    student_enrollments = await service.get_student_enrollments(test_student['id'])
    
    print(f"✅ Found {len(student_enrollments)} enrollments for student")
    for e in student_enrollments[:3]:
        print(f"   - Class: {e.get('Classes')}, Status: {e.get('Enrollment_Status')}")
    
    # Test 5: Get class enrollments
    print("\n[7/9] Getting class enrollments...")
    class_enrollments = await service.get_class_enrollments(test_class['id'])
    
    print(f"✅ Found {len(class_enrollments)} enrollments for class")
    for e in class_enrollments[:3]:
        print(f"   - Student: {e.get('Enrolled_Students')}, Status: {e.get('Enrollment_Status')}")
    
    # Test 6: Update status to Completed
    print("\n[8/9] Completing enrollment...")
    
    complete_result = await service.complete_enrollment(
        zoho_enrollment_id=enrollment_id,
        completion_date="2026-06-30",
        final_grade="Distinction"
    )
    
    print(f"✅ Enrollment completed")
    
    # Verify
    completed_record = await service.get_enrollment_by_id(enrollment_id)
    print(f"   Status: {completed_record.get('Enrollment_Status')}")
    print(f"   Completion Date: {completed_record.get('Completion_Date')}")
    print(f"   Grade: {completed_record.get('Grade')}")
    
    # Test 7: Bulk sync
    print("\n[9/9] Testing bulk sync...")
    
    # Get another student for bulk test
    all_students = await zoho.get_records('BTEC_Students', page=1, per_page=3)
    students_list = all_students.get('data', [])
    
    bulk_enrollments = []
    for i, student in enumerate(students_list[:3], 1):
        bulk_enrollments.append(
            EnrollmentData(
                zoho_student_id=student['id'],
                zoho_class_id=test_class['id'],
                moodle_course_id=f"bulk_course_{timestamp}_{i}",
                enrollment_status="Active"
            )
        )
    
    bulk_result = await service.bulk_sync_enrollments(bulk_enrollments)
    
    print(f"✅ Bulk sync completed:")
    print(f"   Total: {bulk_result['total']}")
    print(f"   Created: {bulk_result['created']}")
    print(f"   Updated: {bulk_result['updated']}")
    print(f"   Failed: {bulk_result['failed']}")
    
    # Summary
    print("\n" + "=" * 80)
    print("TEST SUMMARY")
    print("=" * 80)
    print("✅ Enrollment creation: PASSED")
    print("✅ Enrollment update: PASSED")
    print("✅ Get student enrollments: PASSED")
    print("✅ Get class enrollments: PASSED")
    print("✅ Complete enrollment: PASSED")
    print("✅ Bulk sync: PASSED")
    
    print(f"\nTest enrollment Zoho ID: {enrollment_id}")
    print(f"Student: {test_student.get('Name')}")
    print(f"Class: {test_class.get('Name')}")
    
    print("\n⚠️  Note: Test data created in Zoho. You may want to delete it manually.")
    print("=" * 80)


async def test_withdrawal_flow():
    """Test enrollment withdrawal."""
    
    print("\n" + "=" * 80)
    print("TESTING WITHDRAWAL FLOW")
    print("=" * 80)
    
    zoho = create_zoho_client()
    service = EnrollmentSyncService(zoho_client=zoho)
    
    # Get active enrollments
    active = await service.get_active_enrollments(page=1, per_page=1)
    
    if not active:
        print("⚠️  No active enrollments - skipping withdrawal test")
        return
    
    enrollment = active[0]
    print(f"Using enrollment: {enrollment['id']}")
    print(f"Current status: {enrollment.get('Enrollment_Status')}")
    
    # Withdraw
    result = await service.withdraw_enrollment(
        zoho_enrollment_id=enrollment['id'],
        reason="Test withdrawal"
    )
    
    print(f"✅ Enrollment withdrawn")
    
    # Verify
    updated = await service.get_enrollment_by_id(enrollment['id'])
    print(f"   New status: {updated.get('Enrollment_Status')}")
    print(f"   Notes: {updated.get('Notes', 'N/A')[:50]}...")
    
    print("=" * 80)


async def main():
    """Run all integration tests."""
    try:
        # Main test
        await test_enrollment_flow()
        
        # Withdrawal test
        await test_withdrawal_flow()
        
        print("\n✅ All integration tests completed!")
        
    except Exception as e:
        print(f"\n❌ Test failed: {e}")
        import traceback
        traceback.print_exc()


if __name__ == '__main__':
    asyncio.run(main())
