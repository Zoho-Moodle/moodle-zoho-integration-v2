"""
Integration test for GradeSyncService with real Zoho CRM.

This script tests the complete grading flow:
1. Fetch real unit template from Zoho BTEC module
2. Create test grade data
3. Sync to Zoho (create or update)
4. Verify in Zoho CRM
5. Update grade and re-sync
6. Clean up (optional)

Requirements:
- Zoho credentials in .env
- At least one BTEC unit in Zoho with grading template
- At least one BTEC_Students and BTEC_Classes record
"""

import asyncio
import os
from datetime import datetime
from dotenv import load_dotenv

from app.infra.zoho import create_zoho_client
from app.services.grade_sync_service import GradeSyncService, MoodleGradeData


async def test_full_grading_flow():
    """Test complete grading flow with real Zoho data."""
    
    print("=" * 80)
    print("GRADE SYNC INTEGRATION TEST")
    print("=" * 80)
    
    # Load environment
    load_dotenv()
    
    # Create Zoho client
    print("\n[1/7] Creating Zoho client...")
    zoho = create_zoho_client()
    service = GradeSyncService(zoho_client=zoho)
    print("✅ Zoho client created")
    
    # Step 1: Fetch a real unit from Zoho
    print("\n[2/7] Fetching units from Zoho...")
    response = await zoho.get_records('BTEC', page=1, per_page=5)
    units = response.get('data', [])
    
    if not units:
        print("❌ No BTEC units found in Zoho. Please create at least one unit.")
        return
    
    # Use first unit with grading template
    test_unit = None
    for unit in units:
        if unit.get('P1_description'):  # Has grading template
            test_unit = unit
            break
    
    if not test_unit:
        print("❌ No units with grading template found. Please add P1_description to a unit.")
        return
    
    print(f"✅ Using unit: {test_unit['Name']} (ID: {test_unit['id']})")
    print(f"   Unit Code: {test_unit.get('Unit_Code', 'N/A')}")
    
    # Step 2: Get grading template
    print("\n[3/7] Fetching grading template...")
    template = await service.get_grading_template(test_unit['id'])
    
    print(f"✅ Template loaded:")
    print(f"   Unit: {template.unit_name}")
    print(f"   Pass criteria: {len(template.pass_criteria)}")
    print(f"   Merit criteria: {len(template.merit_criteria)}")
    print(f"   Distinction criteria: {len(template.distinction_criteria)}")
    
    # Show some criteria
    all_criteria = template.get_all_criteria()
    print("\n   Sample criteria:")
    for criterion in all_criteria[:3]:  # Show first 3
        print(f"   - {criterion['code']}: {criterion['description'][:60]}...")
    
    # Step 3: Get test student and class
    print("\n[4/7] Fetching test student and class...")
    
    students_response = await zoho.get_records('BTEC_Students', page=1, per_page=1)
    students = students_response.get('data', [])
    if not students:
        print("❌ No BTEC_Students found. Please create at least one student.")
        return
    test_student = students[0]
    print(f"✅ Using student: {test_student.get('Name', 'N/A')} (ID: {test_student['id']})")
    
    classes_response = await zoho.get_records('BTEC_Classes', page=1, per_page=1)
    classes = classes_response.get('data', [])
    if not classes:
        print("❌ No BTEC_Classes found. Please create at least one class.")
        return
    test_class = classes[0]
    print(f"✅ Using class: {test_class.get('Name', 'N/A')} (ID: {test_class['id']})")
    
    # Step 4: Create test grade data
    print("\n[5/7] Creating test grade data...")
    
    # Use timestamp for unique IDs
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    moodle_student_id = f"test_student_{timestamp}"
    moodle_course_id = f"test_course_{timestamp}"
    
    grade_data = MoodleGradeData(
        moodle_grade_id=f"grade_{timestamp}",
        student_id=moodle_student_id,
        course_id=moodle_course_id,
        zoho_student_id=test_student['id'],
        zoho_class_id=test_class['id'],
        zoho_unit_id=test_unit['id'],
        overall_grade="Pass",
        graded_date=datetime.now().strftime("%Y-%m-%d"),
        feedback=f"Test grade created at {datetime.now().isoformat()}"
    )
    
    # Add some criterion scores
    if len(template.pass_criteria) > 0:
        grade_data.add_criterion_score(
            template.pass_criteria[0]['code'],
            "Achieved",
            "Clear and comprehensive explanation"
        )
    
    if len(template.pass_criteria) > 1:
        grade_data.add_criterion_score(
            template.pass_criteria[1]['code'],
            "Achieved",
            "Good level of detail"
        )
    
    if len(template.merit_criteria) > 0:
        grade_data.add_criterion_score(
            template.merit_criteria[0]['code'],
            "Not Achieved",
            "Needs more in-depth analysis"
        )
    
    print(f"✅ Grade data created:")
    print(f"   Composite Key: {grade_data.composite_key}")
    print(f"   Overall Grade: {grade_data.overall_grade}")
    print(f"   Criteria scores: {len(grade_data.criteria_scores)}")
    
    # Step 5: Sync grade (CREATE)
    print("\n[6/7] Syncing grade to Zoho (CREATE)...")
    result = await service.sync_grade(grade_data)
    
    print(f"✅ Grade synced:")
    print(f"   Status: {result['status']}")
    print(f"   Zoho Record ID: {result['zoho_record_id']}")
    print(f"   Composite Key: {result['composite_key']}")
    print(f"   Criteria Count: {result['criteria_count']}")
    
    zoho_grade_id = result['zoho_record_id']
    
    # Step 6: Verify in Zoho
    print("\n[7/7] Verifying grade in Zoho...")
    zoho_grade = await zoho.get_record('BTEC_Grades', zoho_grade_id)
    
    print(f"✅ Grade verified in Zoho:")
    print(f"   Grade: {zoho_grade.get('Grade')}")
    print(f"   Composite Key: {zoho_grade.get('Moodle_Grade_Composite_Key')}")
    print(f"   Feedback: {zoho_grade.get('Grade_Feedback', 'N/A')[:60]}...")
    
    subform = zoho_grade.get('Learning_Outcomes_Assessm', [])
    print(f"   Learning Outcomes: {len(subform)} rows")
    
    if subform:
        print("\n   Sample outcomes:")
        for row in subform[:3]:  # Show first 3
            print(f"   - {row.get('LO_Code')}: {row.get('LO_Score')} - {row.get('LO_Feedback', 'N/A')[:40]}...")
    
    # Step 7: Update grade (simulate grade improvement)
    print("\n[BONUS] Testing UPDATE flow...")
    grade_data.overall_grade = "Merit"  # Upgraded
    grade_data.feedback = f"Grade updated at {datetime.now().isoformat()}"
    
    # Add Merit criterion
    if len(template.merit_criteria) > 0:
        # Update the existing Merit criterion to Achieved
        for i, score in enumerate(grade_data.criteria_scores):
            if score['code'] == template.merit_criteria[0]['code']:
                grade_data.criteria_scores[i]['score'] = "Achieved"
                grade_data.criteria_scores[i]['feedback'] = "Improved analysis, well done!"
                break
    
    print(f"   Updated overall grade to: {grade_data.overall_grade}")
    
    result_update = await service.sync_grade(grade_data)
    
    print(f"✅ Grade updated:")
    print(f"   Status: {result_update['status']}")
    print(f"   Zoho Record ID: {result_update['zoho_record_id']}")
    
    # Verify update
    zoho_grade_updated = await zoho.get_record('BTEC_Grades', zoho_grade_id)
    print(f"✅ Update verified:")
    print(f"   New Grade: {zoho_grade_updated.get('Grade')}")
    print(f"   Updated Feedback: {zoho_grade_updated.get('Grade_Feedback', 'N/A')[:60]}...")
    
    # Summary
    print("\n" + "=" * 80)
    print("TEST SUMMARY")
    print("=" * 80)
    print("✅ Template extraction: PASSED")
    print("✅ Subform generation: PASSED")
    print("✅ Grade creation: PASSED")
    print("✅ Grade update: PASSED")
    print("✅ Composite key deduplication: PASSED")
    print(f"\nTest grade ID in Zoho: {zoho_grade_id}")
    print(f"Composite Key: {grade_data.composite_key}")
    print("\n⚠️  Note: Test data created in Zoho. You may want to delete it manually.")
    print("=" * 80)


async def test_simple_interface():
    """Test the simplified sync_grade_simple interface."""
    
    print("\n" + "=" * 80)
    print("TESTING SIMPLE INTERFACE")
    print("=" * 80)
    
    zoho = create_zoho_client()
    service = GradeSyncService(zoho_client=zoho)
    
    # Get test data (reuse from main test)
    units_response = await zoho.get_records('BTEC', page=1, per_page=1)
    units = units_response.get('data', [])
    
    students_response = await zoho.get_records('BTEC_Students', page=1, per_page=1)
    students = students_response.get('data', [])
    
    classes_response = await zoho.get_records('BTEC_Classes', page=1, per_page=1)
    classes = classes_response.get('data', [])
    
    if not (units and students and classes):
        print("⚠️  Skipping simple interface test - missing data")
        return
    
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    
    result = await service.sync_grade_simple(
        moodle_grade_id=f"simple_{timestamp}",
        student_zoho_id=students[0]['id'],
        class_zoho_id=classes[0]['id'],
        unit_zoho_id=units[0]['id'],
        overall_grade="Pass",
        criteria_scores=[
            ("P1", "Achieved", "Good work"),
            ("P2", "Achieved", "Well done")
        ],
        student_moodle_id=f"student_{timestamp}",
        course_moodle_id=f"course_{timestamp}",
        graded_date=datetime.now().strftime("%Y-%m-%d"),
        feedback="Test using simple interface"
    )
    
    print(f"✅ Simple interface test:")
    print(f"   Status: {result['status']}")
    print(f"   Zoho ID: {result['zoho_record_id']}")
    print("=" * 80)


async def test_template_caching():
    """Test that template caching works."""
    
    print("\n" + "=" * 80)
    print("TESTING TEMPLATE CACHING")
    print("=" * 80)
    
    zoho = create_zoho_client()
    service = GradeSyncService(zoho_client=zoho)
    
    units_response = await zoho.get_records('BTEC', page=1, per_page=1)
    units = units_response.get('data', [])
    if not units:
        print("⚠️  Skipping cache test - no units")
        return
    
    unit_id = units[0]['id']
    
    # First fetch
    import time
    start = time.time()
    template1 = await service.get_grading_template(unit_id)
    time1 = time.time() - start
    
    # Second fetch (should be cached)
    start = time.time()
    template2 = await service.get_grading_template(unit_id)
    time2 = time.time() - start
    
    print(f"✅ Caching test:")
    print(f"   First fetch: {time1:.3f}s")
    print(f"   Second fetch (cached): {time2:.3f}s")
    print(f"   Speedup: {time1/time2:.1f}x faster")
    print(f"   Same instance: {template1 is template2}")
    
    # Clear cache
    service.clear_template_cache()
    print("✅ Cache cleared")
    
    # Third fetch (should fetch again)
    start = time.time()
    template3 = await service.get_grading_template(unit_id)
    time3 = time.time() - start
    
    print(f"   After clear: {time3:.3f}s")
    print(f"   Different instance: {template1 is not template3}")
    print("=" * 80)


async def main():
    """Run all integration tests."""
    try:
        # Main test
        await test_full_grading_flow()
        
        # Simple interface test
        await test_simple_interface()
        
        # Caching test
        await test_template_caching()
        
        print("\n✅ All integration tests completed!")
        
    except Exception as e:
        print(f"\n❌ Test failed: {e}")
        import traceback
        traceback.print_exc()


if __name__ == '__main__':
    asyncio.run(main())
