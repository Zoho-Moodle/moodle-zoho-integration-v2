"""
Zoho Client Usage Examples

Demonstrates how to use the Zoho CRM API client following ZOHO_API_CONTRACT.md.
"""

import asyncio
from app.infra.zoho import ZohoClient, create_zoho_client


async def example_basic_operations():
    """Basic CRUD operations."""
    
    # Create client (loads from environment variables)
    zoho = create_zoho_client()
    
    # ===== STUDENTS =====
    
    # Get student by ID
    student = await zoho.get_record('BTEC_Students', '5843017000000123456')
    print(f"Student: {student['Name']}, Email: {student['Academic_Email']}")
    
    # Search students by email
    students = await zoho.search_records(
        'BTEC_Students',
        "(Academic_Email:equals:john@example.com)"
    )
    print(f"Found {len(students)} students")
    
    # Get all students (paginated)
    response = await zoho.get_records('BTEC_Students', page=1, per_page=100)
    students = response['data']
    has_more = response.get('info', {}).get('more_records', False)
    print(f"Got {len(students)} students, more: {has_more}")
    
    # Update student with Moodle ID
    await zoho.update_record('BTEC_Students', student['id'], {
        'Student_Moodle_ID': '12345',
        'Synced_to_Moodle': True
    })
    
    # ===== PROGRAMS (Products module) =====
    
    # ⚠️ Note: Programs use 'Products' module, NOT 'BTEC_Programs'
    program = await zoho.get_record('Products', '5843017000000789012')
    print(f"Program: {program['Product_Name']}, Code: {program['Product_Code']}")
    
    # ===== UNITS (BTEC module) =====
    
    # ⚠️ Note: Units use 'BTEC' module, NOT 'BTEC_Units'
    unit = await zoho.get_record('BTEC', '5843017000000345678')
    print(f"Unit: {unit['Name']}, Code: {unit['Unit_Code']}")


async def example_grading_template():
    """Fetch grading template from BTEC (Units) module."""
    
    zoho = create_zoho_client()
    
    # Fetch unit with grading template
    unit_id = '5843017000000345678'
    unit = await zoho.get_record('BTEC', unit_id)
    
    # Extract P/M/D criteria
    template = {
        'pass': [],
        'merit': [],
        'distinction': []
    }
    
    # Pass criteria (P1-P19)
    for i in range(1, 20):
        field = f'P{i}_description'
        if unit.get(field):
            template['pass'].append({
                'code': f'P{i}',
                'description': unit[field]
            })
    
    # Merit criteria (M1-M9)
    for i in range(1, 10):
        field = f'M{i}_description'
        if unit.get(field):
            template['merit'].append({
                'code': f'M{i}',
                'description': unit[field]
            })
    
    # Distinction criteria (D1-D6)
    for i in range(1, 7):
        field = f'D{i}_description'
        if unit.get(field):
            template['distinction'].append({
                'code': f'D{i}',
                'description': unit[field]
            })
    
    print(f"Grading template for {unit['Name']}:")
    print(f"  Pass: {len(template['pass'])} criteria")
    print(f"  Merit: {len(template['merit'])} criteria")
    print(f"  Distinction: {len(template['distinction'])} criteria")
    
    return template


async def example_create_grade():
    """Create grade record with Learning_Outcomes_Assessm subform."""
    
    zoho = create_zoho_client()
    
    # Prepare grade data
    grade_data = {
        # Header fields
        "Student": "5843017000000111111",      # Lookup to BTEC_Students
        "Class": "5843017000000222222",        # Lookup to BTEC_Classes
        "BTEC_Unit": "5843017000000333333",    # Lookup to BTEC (Units)
        "Grade": "Pass",                        # Pass/Merit/Distinction
        "Grade_Status": "Submitted",
        "Attempt_Date": "2026-01-25",
        "Attempt_Number": 1,
        "Feedback": "Good work overall. P1-P5 achieved.",
        "Moodle_Grade_ID": "12345",
        "Moodle_Grade_Composite_Key": "111111_222222",  # student_id + course_id
        
        # Subform: Learning_Outcomes_Assessm (one row per criterion)
        "Learning_Outcomes_Assessm": [
            {
                "LO_Code": "P1",
                "LO_Title": "Explain the components",
                "LO_Score": "Achieved",
                "LO_Definition": "Explain the components of a computer system...",
                "LO_Feedback": "Clear explanation provided"
            },
            {
                "LO_Code": "P2",
                "LO_Title": "Describe the process",
                "LO_Score": "Achieved",
                "LO_Definition": "Describe the process of software development...",
                "LO_Feedback": "Good understanding shown"
            },
            {
                "LO_Code": "P3",
                "LO_Title": "Demonstrate knowledge",
                "LO_Score": "Not Achieved",
                "LO_Definition": "Demonstrate knowledge of...",
                "LO_Feedback": "More detail needed"
            },
            {
                "LO_Code": "M1",
                "LO_Title": "Analyze the impact",
                "LO_Score": "Not Achieved",
                "LO_Definition": "Analyze the impact of...",
                "LO_Feedback": "Analysis too shallow"
            }
        ]
    }
    
    # Create grade record
    result = await zoho.create_record('BTEC_Grades', grade_data)
    
    print(f"Grade created successfully!")
    print(f"  Record ID: {result['details']['id']}")
    print(f"  Status: {result['code']}")
    
    return result['details']['id']


async def example_update_grade():
    """Update existing grade by composite key."""
    
    zoho = create_zoho_client()
    
    # Search for existing grade by composite key
    composite_key = "111111_222222"
    existing_grades = await zoho.search_records(
        'BTEC_Grades',
        f"(Moodle_Grade_Composite_Key:equals:{composite_key})"
    )
    
    if existing_grades:
        grade_id = existing_grades[0]['id']
        
        # Update grade
        await zoho.update_record('BTEC_Grades', grade_id, {
            "Grade": "Merit",  # Upgraded from Pass to Merit
            "Feedback": "Improved work. M1 now achieved.",
            "Learning_Outcomes_Assessm": [
                # Updated subform with M1 achieved
                {"LO_Code": "P1", "LO_Score": "Achieved"},
                {"LO_Code": "P2", "LO_Score": "Achieved"},
                {"LO_Code": "P3", "LO_Score": "Achieved"},
                {"LO_Code": "M1", "LO_Score": "Achieved", "LO_Feedback": "Much better!"}
            ]
        })
        
        print(f"Grade {grade_id} updated to Merit")
    else:
        print(f"No grade found with composite key {composite_key}")


async def example_upsert_grade():
    """Upsert grade (create or update based on composite key)."""
    
    zoho = create_zoho_client()
    
    grade_data = {
        "Student": "5843017000000111111",
        "Class": "5843017000000222222",
        "BTEC_Unit": "5843017000000333333",
        "Grade": "Pass",
        "Moodle_Grade_Composite_Key": "111111_222222",
        "Learning_Outcomes_Assessm": [
            {"LO_Code": "P1", "LO_Score": "Achieved"}
        ]
    }
    
    # Upsert by composite key
    result = await zoho.upsert_record(
        'BTEC_Grades',
        grade_data,
        duplicate_check_fields=['Moodle_Grade_Composite_Key']
    )
    
    print(f"Grade upserted: {result}")


async def example_pagination():
    """Fetch all records with pagination."""
    
    zoho = create_zoho_client()
    
    all_students = []
    page = 1
    per_page = 200  # Max allowed by Zoho
    
    while True:
        response = await zoho.get_records(
            'BTEC_Students',
            page=page,
            per_page=per_page
        )
        
        students = response.get('data', [])
        all_students.extend(students)
        
        print(f"Page {page}: Got {len(students)} students")
        
        # Check if more pages
        has_more = response.get('info', {}).get('more_records', False)
        if not has_more:
            break
        
        page += 1
    
    print(f"Total students: {len(all_students)}")
    return all_students


async def example_error_handling():
    """Demonstrate error handling."""
    
    from app.infra.zoho.exceptions import (
        ZohoNotFoundError,
        ZohoInvalidModuleError,
        ZohoValidationError
    )
    
    zoho = create_zoho_client()
    
    # Invalid module name
    try:
        await zoho.get_record('BTEC_Programs', '123456')  # ❌ Wrong name
    except ZohoInvalidModuleError as e:
        print(f"Module error: {e}")
        # Output: Invalid module 'BTEC_Programs'. Did you mean 'Products'?
    
    # Record not found
    try:
        await zoho.get_record('BTEC_Students', '999999999')
    except ZohoNotFoundError as e:
        print(f"Not found: {e}")
    
    # Validation error
    try:
        await zoho.create_record('BTEC_Students', {
            # Missing required fields
            'Name': 'Test'
        })
    except ZohoValidationError as e:
        print(f"Validation error: {e}")
        print(f"Response: {e.response_data}")


async def main():
    """Run all examples."""
    
    print("=" * 60)
    print("Zoho Client Examples")
    print("=" * 60)
    
    try:
        # Basic operations
        print("\n1. Basic Operations")
        await example_basic_operations()
        
        # Grading template
        print("\n2. Grading Template")
        template = await example_grading_template()
        
        # Create grade
        print("\n3. Create Grade")
        grade_id = await example_create_grade()
        
        # Update grade
        print("\n4. Update Grade")
        await example_update_grade()
        
        # Pagination
        print("\n5. Pagination")
        await example_pagination()
        
        # Error handling
        print("\n6. Error Handling")
        await example_error_handling()
        
    except Exception as e:
        print(f"\nError: {e}")
        import traceback
        traceback.print_exc()


if __name__ == '__main__':
    # Run examples
    asyncio.run(main())
