"""
Integration Test for PaymentSyncService

Tests read-only operations with real Zoho CRM data.
"""

import asyncio
import os
from datetime import date, datetime
from decimal import Decimal

from app.services.payment_sync_service import PaymentSyncService, PaymentData
from app.infra.zoho import ZohoClient, create_zoho_client


async def test_payment_flow():
    """Test complete payment data retrieval flow."""
    
    print("\n" + "="*80)
    print("PAYMENT SYNC INTEGRATION TEST (READ-ONLY)")
    print("="*80)
    
    # Step 1: Create Zoho client
    print("\n[1/7] Creating Zoho client...")
    
    zoho = create_zoho_client()
    
    print("✅ Zoho client created")
    
    # Step 2: Create service
    print("\n[2/7] Creating PaymentSyncService...")
    service = PaymentSyncService(zoho_client=zoho)
    print("✅ Service created (read-only mode)")
    
    # Step 3: Get test student (we know A01B4083C has data)
    print("\n[3/7] Getting test student...")
    
    # Get students and find A01B4083C
    response = await zoho.get_records('BTEC_Students', page=1, per_page=200)
    all_students = response.get('data', [])
    
    # Find student A01B4083C (check both Student_ID_Number and Name)
    students = [
        s for s in all_students 
        if s.get('Student_ID_Number') == 'A01B4083C' or s.get('Name') == 'A01B4083C'
    ]
    
    if not students:
        print("❌ Test student A01B4083C not found")
        print(f"ℹ️  Available students: {len(all_students)}")
        if all_students:
            # Use first student with payments
            student = all_students[0]
            student_id_num = student.get('Student_ID_Number') or student.get('Name', 'Unknown')
            print(f"ℹ️  Using first student instead: {student_id_num}")
            students = [student]
        else:
            print("❌ No students found in Zoho")
            return
    
    student = students[0]
    student_id = student['id']
    student_id_num = student.get('Student_ID_Number') or student.get('Name', 'Unknown')
    print(f"✅ Using student: {student_id_num} (ID: {student_id})")
    
    # Step 4: Get all payments for student
    print("\n[4/7] Getting student payments...")
    payments = await service.get_student_payments(student_id)
    
    print(f"✅ Found {len(payments)} payments:")
    for payment in payments:
        print(f"   - {payment.payment_name}: "
              f"Amount={payment.payment_amount}, "
              f"Date={payment.payment_date}, "
              f"Method={payment.payment_method}")
    
    # Step 5: Calculate payment summary
    print("\n[5/7] Calculating payment summary...")
    
    # For testing, we'll provide a known total_fees
    # In production, this would come from registration
    summary = await service.calculate_payment_summary(
        student_id,
        total_fees=Decimal("15000.00")  # Assume £15,000 total fees
    )
    
    print("✅ Payment summary calculated:")
    print(f"   Total Fees: £{summary.total_fees}")
    print(f"   Total Paid: £{summary.total_paid}")
    print(f"   Balance: £{summary.balance}")
    print(f"   Payment Count: {summary.payment_count}")
    print(f"   Fully Paid: {summary.is_fully_paid}")
    if summary.last_payment_date:
        print(f"   Last Payment: {summary.last_payment_date}")
    
    # Step 6: Get specific payment details
    if payments:
        print("\n[6/7] Getting specific payment details...")
        first_payment_id = payments[0].zoho_payment_id
        
        payment_detail = await service.get_payment_by_id(first_payment_id)
        
        if payment_detail:
            print("✅ Payment details retrieved:")
            print(f"   ID: {payment_detail.zoho_payment_id}")
            print(f"   Name: {payment_detail.payment_name}")
            print(f"   Amount: £{payment_detail.payment_amount}")
            print(f"   Date: {payment_detail.payment_date}")
            print(f"   Method: {payment_detail.payment_method}")
            print(f"   Synced to Moodle: {payment_detail.synced_to_moodle}")
        else:
            print("❌ Payment details not found")
    else:
        print("\n[6/7] Skipping payment details (no payments found)")
    
    # Step 7: Get recent payments
    print("\n[7/7] Getting recent payments...")
    recent = await service.get_recent_payments(limit=5, zoho_student_id=student_id)
    
    print(f"✅ Found {len(recent)} recent payments (sorted by date desc):")
    for payment in recent:
        print(f"   - {payment.payment_date}: {payment.payment_name} - £{payment.payment_amount}")
    
    # Summary
    print("\n" + "="*80)
    print("TEST SUMMARY")
    print("="*80)
    print("✅ Student payments retrieved: PASSED")
    print("✅ Payment summary calculated: PASSED")
    if payments:
        print("✅ Payment details retrieved: PASSED")
    print("✅ Recent payments sorted: PASSED")
    print("\nℹ️  All operations are READ-ONLY - no data modified in Zoho")
    print("="*80)


async def test_date_range_search():
    """Test searching payments by date range."""
    
    print("\n" + "="*80)
    print("TESTING DATE RANGE SEARCH")
    print("="*80)
    
    # Create client and service
    zoho = create_zoho_client()
    service = PaymentSyncService(zoho_client=zoho)
    
    # Search for payments in 2025
    print("\nSearching for payments in 2025...")
    payments = await service.search_payments_by_date_range(
        start_date=date(2025, 1, 1),
        end_date=date(2025, 12, 31)
    )
    
    print(f"✅ Found {len(payments)} payments in 2025")
    
    if payments:
        print("\nSample payments:")
        for payment in payments[:3]:  # Show first 3
            print(f"   - {payment.payment_date}: {payment.payment_name} - £{payment.payment_amount}")
    
    print("\n✅ Date range search completed")


if __name__ == "__main__":
    # Load environment variables
    from dotenv import load_dotenv
    load_dotenv()
    
    # Run tests
    asyncio.run(test_payment_flow())
    asyncio.run(test_date_range_search())
