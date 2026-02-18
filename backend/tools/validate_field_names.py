"""
Validate field names used in code against Zoho API source of truth.
"""
import json
import sys

# Load the Zoho API names file
with open('backend/zoho_api_names.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

# Fields used in code vs actual Zoho fields
print("=" * 80)
print("FIELD VALIDATION REPORT")
print("=" * 80)

# Check BTEC_Students fields
students_fields = {f['api_name'] for f in data['modules']['BTEC_Students']}
print("\n1. BTEC_Students Module:")
# Updated with corrected field names (removed Mobile and $Photo_id as they don't exist)
used_fields = ["Student_Moodle_ID", "Name", "First_Name", "Last_Name", 
               "Display_Name", "Academic_Email", "Phone_Number", "Status", 
               "Synced_to_Moodle"]
for field in used_fields:
    status = "OK" if field in students_fields else "MISSING"
    print(f"   {status:8} {field}")

# Check BTEC_Registrations fields
reg_fields = {f['api_name'] for f in data['modules']['BTEC_Registrations']}
print("\n2. BTEC_Registrations Module:")
used_fields = ["Program", "Study_Mode", "Student_Status", "Registration_Date", 
               "Program_Price", "Remaining_Amount", "Payment_Schedule"]
for field in used_fields:
    status = "OK" if field in reg_fields else "MISSING"
    print(f"   {status:8} {field}")

# Check Payment_Schedule subform fields
print("\n3. Payment_Schedule Subform fields:")
payment_schedule_fields = ["Installment_No", "Installment_Title", "Due_Date",
                          "Installment_Amount", "Paid_Amount", "Installment_Status"]
print("   (Subform fields need manual verification in Zoho)")
for field in payment_schedule_fields:
    print(f"   UNKNOWN  {field}")

# Check BTEC_Payments fields
payment_fields = {f['api_name'] for f in data['modules']['BTEC_Payments']}
print("\n4. BTEC_Payments Module:")
# Updated with corrected field names
used_fields = ["Payment_Date", "Payment_Amount", "Payment_Method", "Payment_Type", "Student_ID", "Registration_ID"]
errors = []
for field in used_fields:
    status = "OK" if field in payment_fields else "MISSING"
    print(f"   {status:8} {field}")
    if field not in payment_fields:
        errors.append(field)

if errors:
    print("\n   Suggested corrections:")
    for error_field in errors:
        similar = [f['api_name'] for f in data['modules']['BTEC_Payments'] 
                   if error_field.lower().replace('_', '') in f['api_name'].lower().replace('_', '')]
        if similar:
            print(f"   '{error_field}' -> Try: {similar}")

# Check BTEC_Enrollments fields
enroll_fields = {f['api_name'] for f in data['modules']['BTEC_Enrollments']}
print("\n5. BTEC_Enrollments Module:")
# Updated with corrected field names
used_fields = ["Classes", "Class_Name", "Enrolled_Program", "Start_Date", "End_Date", "Moodle_Course_ID", "Enrolled_Students", "Student_Name"]
errors = []
for field in used_fields:
    status = "OK" if field in enroll_fields else "MISSING"
    print(f"   {status:8} {field}")
    if field not in enroll_fields:
        errors.append(field)

if errors:
    print("\n   Suggested corrections:")
    for error_field in errors:
        similar = [f['api_name'] for f in data['modules']['BTEC_Enrollments'] 
                   if error_field.lower().replace('_', '') in f['api_name'].lower().replace('_', '')]
        if similar:
            print(f"   '{error_field}' -> Try: {similar}")

# Check BTEC_Student_Requests fields
req_fields = {f['api_name'] for f in data['modules']['BTEC_Student_Requests']}
print("\n6. BTEC_Student_Requests Module:")
# Updated with corrected field names (removed Created_Time as it doesn't exist, use Last_Activity_Time instead)
used_fields = ["Student", "Request_Type", "Reason", "Status", "Request_Date",
               "Payment_Receipt", "Requested_Classes", "Academic_Email", "Fees_Amount", "Change_Information", "Moodle_User_ID", "Last_Activity_Time"]
for field in used_fields:
    status = "OK" if field in req_fields else "MISSING"
    print(f"   {status:8} {field}")

print("\n" + "=" * 80)
print("ACTUAL FIELD NAMES FROM ZOHO")
print("=" * 80)

# Print actual Payment fields for correction
print("\n7. BTEC_Payments - Amount/Reference fields:")
payment_amount_fields = [f for f in data['modules']['BTEC_Payments'] 
                         if 'amount' in f['api_name'].lower() or 'reference' in f['api_name'].lower()]
for f in payment_amount_fields:
    print(f"   {f['api_name']:<30} - {f['field_label']}")

print("\n8. BTEC_Enrollments - Class/Program/Status fields:")
enroll_class_fields = [f for f in data['modules']['BTEC_Enrollments'] 
                       if any(x in f['api_name'].lower() for x in ['class', 'program', 'status', 'unit'])]
for f in enroll_class_fields:
    print(f"   {f['api_name']:<30} - {f['field_label']}")

print("\n9. BTEC_Payments - All payment-related fields:")
for f in data['modules']['BTEC_Payments']:
    if any(x in f['api_name'].lower() for x in ['payment', 'date', 'status', 'method']):
        print(f"   {f['api_name']:<30} - {f['field_label']}")

print("\n" + "=" * 80)
