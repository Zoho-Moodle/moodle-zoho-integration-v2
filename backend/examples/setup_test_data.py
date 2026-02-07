"""
Setup Test Data in Zoho CRM

This script creates minimal test data for integration testing:
1. BTEC Unit with grading template (P1-P5, M1-M3, D1-D2)
2. BTEC Student
3. BTEC Class
4. Optional: BTEC Program

Run this once to prepare Zoho for integration tests.
"""

import asyncio
from datetime import datetime
from dotenv import load_dotenv

from app.infra.zoho import create_zoho_client


async def create_test_unit(zoho):
    """Create a BTEC unit with grading template."""
    
    print("\n[1/3] Creating test BTEC unit with grading template...")
    
    unit_data = {
        "Name": "Test Unit - Programming Fundamentals",
        "Unit_Code": "TEST001",
        "Credit_Value": "10",
        "Level": "Level 3",
        
        # Pass criteria (P1-P5)
        "P1_description": "Explain the main components of a computer system and how they work together",
        "P2_description": "Describe the software development lifecycle and its key phases",
        "P3_description": "Demonstrate basic programming constructs including variables, loops, and conditionals",
        "P4_description": "Create simple programs that solve defined problems using appropriate algorithms",
        "P5_description": "Test and debug programs using systematic approaches",
        
        # Merit criteria (M1-M3)
        "M1_description": "Analyze the impact of emerging technologies on computer systems architecture",
        "M2_description": "Compare different software development methodologies and justify selection criteria",
        "M3_description": "Implement efficient algorithms demonstrating understanding of complexity",
        
        # Distinction criteria (D1-D2)
        "D1_description": "Evaluate the effectiveness of different programming paradigms for solving complex problems",
        "D2_description": "Justify design decisions made during program development with reference to best practices"
    }
    
    try:
        result = await zoho.create_record('BTEC', unit_data)
        
        if result.get('code') == 'SUCCESS':
            unit_id = result['details']['id']
            print(f"✅ Unit created successfully!")
            print(f"   ID: {unit_id}")
            print(f"   Name: {unit_data['Name']}")
            print(f"   Code: {unit_data['Unit_Code']}")
            print(f"   Grading: 5 Pass + 3 Merit + 2 Distinction criteria")
            return unit_id
        else:
            print(f"❌ Failed to create unit: {result}")
            return None
            
    except Exception as e:
        print(f"❌ Error creating unit: {e}")
        return None


async def create_test_student(zoho):
    """Create a test BTEC student."""
    
    print("\n[2/3] Creating test BTEC student...")
    
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    
    student_data = {
        "Name": f"Test Student {timestamp}",
        "First_Name": "Test",
        "Last_Name": f"Student_{timestamp}",
        "Academic_Email": f"test.student.{timestamp}@example.com",
        "Student_ID": f"STU{timestamp}",
        "Status": "Active",
        "Date_of_Birth": "2005-01-15",
        "Gender": "Prefer not to say"
    }
    
    try:
        result = await zoho.create_record('BTEC_Students', student_data)
        
        if result.get('code') == 'SUCCESS':
            student_id = result['details']['id']
            print(f"✅ Student created successfully!")
            print(f"   ID: {student_id}")
            print(f"   Name: {student_data['Name']}")
            print(f"   Email: {student_data['Academic_Email']}")
            return student_id
        else:
            print(f"❌ Failed to create student: {result}")
            return None
            
    except Exception as e:
        print(f"❌ Error creating student: {e}")
        return None


async def create_test_class(zoho):
    """Create a test BTEC class."""
    
    print("\n[3/3] Creating test BTEC class...")
    
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    
    class_data = {
        "Name": f"Test Class {timestamp}",
        "Class_Code": f"CLS{timestamp}",
        "Academic_Year": "2025-2026",
        "Start_Date": "2025-09-01",
        "End_Date": "2026-06-30",
        "Status": "Active"
    }
    
    try:
        result = await zoho.create_record('BTEC_Classes', class_data)
        
        if result.get('code') == 'SUCCESS':
            class_id = result['details']['id']
            print(f"✅ Class created successfully!")
            print(f"   ID: {class_id}")
            print(f"   Name: {class_data['Name']}")
            print(f"   Code: {class_data['Class_Code']}")
            return class_id
        else:
            print(f"❌ Failed to create class: {result}")
            return None
            
    except Exception as e:
        print(f"❌ Error creating class: {e}")
        return None


async def verify_existing_data(zoho):
    """Check if we already have test data."""
    
    print("\n[INFO] Checking for existing test data...")
    
    # Check units with grading template
    units_response = await zoho.get_records('BTEC', page=1, per_page=5)
    units = units_response.get('data', [])
    
    units_with_template = [u for u in units if u.get('P1_description')]
    
    if units_with_template:
        print(f"✅ Found {len(units_with_template)} unit(s) with grading template")
        print(f"   Example: {units_with_template[0].get('Name')} (ID: {units_with_template[0]['id']})")
    else:
        print("⚠️  No units with grading template found")
    
    # Check students
    students_response = await zoho.get_records('BTEC_Students', page=1, per_page=1)
    students = students_response.get('data', [])
    
    if students:
        print(f"✅ Found {len(students)} student(s)")
        print(f"   Example: {students[0].get('Name')} (ID: {students[0]['id']})")
    else:
        print("⚠️  No students found")
    
    # Check classes
    classes_response = await zoho.get_records('BTEC_Classes', page=1, per_page=1)
    classes = classes_response.get('data', [])
    
    if classes:
        print(f"✅ Found {len(classes)} class(es)")
        print(f"   Example: {classes[0].get('Name')} (ID: {classes[0]['id']})")
    else:
        print("⚠️  No classes found")
    
    # Return whether we need to create data
    return {
        'needs_unit': len(units_with_template) == 0,
        'needs_student': len(students) == 0,
        'needs_class': len(classes) == 0
    }


async def main():
    """Main setup function."""
    
    print("=" * 80)
    print("ZOHO TEST DATA SETUP")
    print("=" * 80)
    
    # Load environment
    load_dotenv()
    
    # Create Zoho client
    print("\n[INIT] Creating Zoho client...")
    zoho = create_zoho_client()
    print("✅ Connected to Zoho CRM")
    
    # Check existing data
    needs = await verify_existing_data(zoho)
    
    # Ask user
    print("\n" + "=" * 80)
    if any(needs.values()):
        print("MISSING DATA DETECTED")
        print("=" * 80)
        if needs['needs_unit']:
            print("❌ No BTEC unit with grading template")
        if needs['needs_student']:
            print("❌ No BTEC student")
        if needs['needs_class']:
            print("❌ No BTEC class")
        
        print("\nThis script will create the missing test data.")
        response = input("\nProceed? (yes/no): ").strip().lower()
        
        if response not in ['yes', 'y']:
            print("❌ Cancelled by user")
            return
    else:
        print("ALL TEST DATA EXISTS")
        print("=" * 80)
        print("✅ Unit with grading template: EXISTS")
        print("✅ Student: EXISTS")
        print("✅ Class: EXISTS")
        
        response = input("\nCreate additional test data anyway? (yes/no): ").strip().lower()
        
        if response not in ['yes', 'y']:
            print("✅ Using existing test data")
            return
    
    # Create data
    print("\n" + "=" * 80)
    print("CREATING TEST DATA")
    print("=" * 80)
    
    results = {}
    
    if needs['needs_unit'] or True:  # Always create new unit if requested
        results['unit_id'] = await create_test_unit(zoho)
    
    if needs['needs_student'] or True:
        results['student_id'] = await create_test_student(zoho)
    
    if needs['needs_class'] or True:
        results['class_id'] = await create_test_class(zoho)
    
    # Summary
    print("\n" + "=" * 80)
    print("SETUP SUMMARY")
    print("=" * 80)
    
    if all(results.values()):
        print("✅ All test data created successfully!")
        print("\nCreated IDs:")
        for key, value in results.items():
            print(f"   {key}: {value}")
        
        print("\n✅ Ready to run integration tests!")
        print("\nNext step:")
        print("   python examples/test_grade_sync_integration.py")
    else:
        print("⚠️  Some data creation failed")
        print("   Please check errors above and try again")
    
    print("=" * 80)


if __name__ == '__main__':
    asyncio.run(main())
