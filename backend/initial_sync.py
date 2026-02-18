"""
Initial Sync Script - Sync all data from Zoho CRM to Moodle
Fetches all records from Zoho modules and sends them to Moodle via webhooks
"""
import asyncio
import httpx
import json
import os
import base64
from typing import Dict, List, Any
from datetime import datetime
from pathlib import Path
from app.core.config import settings
from app.infra.zoho import create_zoho_client
from zoho_attachments import ZohoAttachmentHandler

# Zoho Module Names from zoho_api_names.json
MODULES = {
    "students": "BTEC_Students",
    "registrations": "BTEC_Registrations", 
    "payments": "BTEC_Payments",
    "classes": "BTEC_Classes",
    "enrollments": "BTEC_Enrollments",
    "grades": "BTEC_Grades",
    "requests": "BTEC_Student_Requests"
}

# Moodle Webhook Endpoints
MOODLE_ENDPOINTS = {
    "students": "local_mzi_update_student",
    "registrations": "local_mzi_create_registration",
    "payments": "local_mzi_record_payment",
    "classes": "local_mzi_create_class",
    "enrollments": "local_mzi_update_enrollment",
    "grades": "local_mzi_submit_grade",
    "requests": "local_mzi_change_request_status"
}

# Field Mapping: Zoho API Name → Moodle Field Name
FIELD_MAPPINGS = {
    "students": {
        "id": "zoho_student_id",
        "Name": "student_id",  # Student ID النصي (مثل A01B3660C)
        "Academic_Email": "email",
        "First_Name": "first_name",
        "Last_Name": "last_name", 
        "Phone_Number": "phone_number",
        "Nationality": "nationality",
        "Birth_Date": "date_of_birth",
        "Gender": "gender",
        "Address": "address",
        "Emergency_Contact_Name": "emergency_contact_name",
        "Emergency_Phone_Number": "emergency_contact_phone",
        "Status": "status",
        "Student_Moodle_ID": "moodle_user_id",
        "Created_Time": "zoho_created_time",
        "Modified_Time": "zoho_modified_time"
    },
    "registrations": {
        "id": "zoho_registration_id",
        "Student": "zoho_student_id",
        "Registration_Number": "registration_number",
        "Program_Name": "program_name",
        "Program_Level": "program_level",
        "Registration_Date": "registration_date",
        "Expected_Graduation": "expected_graduation",
        "Status": "registration_status",
        "Total_Fees": "total_fees",
        "Paid_Amount": "paid_amount",
        "Remaining_Amount": "remaining_amount",
        "Currency": "currency",
        "Payment_Plan": "payment_plan",
        "Number_of_Installments": "number_of_installments",
        "Created_Time": "zoho_created_time",
        "Modified_Time": "zoho_modified_time"
    },
    "payments": {
        "id": "zoho_payment_id",
        "Registration": "zoho_registration_id",
        "Payment_Number": "payment_number",
        "Payment_Date": "payment_date",
        "Amount": "amount",
        "Payment_Method": "payment_method",
        "Status": "status",
        "Receipt_Number": "receipt_number",
        "Notes": "notes",
        "Created_Time": "zoho_created_time",
        "Modified_Time": "zoho_modified_time"
    },
    "classes": {
        "id": "zoho_class_id",
        "Class_Code": "class_code",
        "Class_Name": "class_name",
        "Program": "program",
        "Instructor": "instructor",
        "Start_Date": "start_date",
        "End_Date": "end_date",
        "Status": "status",
        "Max_Students": "max_students",
        "Enrolled_Count": "enrolled_count",
        "Schedule": "schedule",
        "Location": "location",
        "Created_Time": "zoho_created_time",
        "Modified_Time": "zoho_modified_time"
    },
    "enrollments": {
        "id": "zoho_enrollment_id",
        "Student": "zoho_student_id",
        "Class": "zoho_class_id",
        "Enrollment_Date": "enrollment_date",
        "Status": "status",
        "Attendance_Percentage": "attendance_percentage",
        "Created_Time": "zoho_created_time",
        "Modified_Time": "zoho_modified_time"
    },
    "grades": {
        "id": "zoho_grade_id",
        "Student": "zoho_student_id",
        "Class": "zoho_class_id",
        "Unit_Code": "unit_code",
        "Unit_Name": "unit_name",
        "Grade": "grade",
        "Submission_Date": "submission_date",
        "Grading_Date": "grading_date",
        "Instructor_Feedback": "instructor_feedback",
        "Created_Time": "zoho_created_time",
        "Modified_Time": "zoho_modified_time"
    },
    "requests": {
        "id": "zoho_request_id",
        "Student": "zoho_student_id",
        "Request_Number": "request_number",
        "Request_Type": "request_type",
        "Description": "description",
        "Status": "status",
        "Priority": "priority",
        "Requested_Date": "requested_date",
        "Response": "response",
        "Resolved_Date": "resolved_date",
        "Created_Time": "zoho_created_time",
        "Modified_Time": "zoho_modified_time"
    }
}


class InitialSyncService:
    def __init__(self):
        self.zoho = create_zoho_client()
        self.attachment_handler = None  # Will be initialized when needed
        self.moodle_url = settings.MOODLE_BASE_URL
        self.moodle_token = settings.MOODLE_TOKEN
        self.photos_dir = "student_photos"  # Directory to save photos
        Path(self.photos_dir).mkdir(parents=True, exist_ok=True)
        self.stats = {
            "students": {"fetched": 0, "synced": 0, "failed": 0, "photos_downloaded": 0},
            "registrations": {"fetched": 0, "synced": 0, "failed": 0},
            "payments": {"fetched": 0, "synced": 0, "failed": 0},
            "classes": {"fetched": 0, "synced": 0, "failed": 0},
            "enrollments": {"fetched": 0, "synced": 0, "failed": 0},
            "grades": {"fetched": 0, "synced": 0, "failed": 0},
            "requests": {"fetched": 0, "synced": 0, "failed": 0}
        }

    async def get_access_token(self) -> str:
        """Get fresh Zoho access token"""
        # Not needed - ZohoClient handles this internally
        pass

    async def fetch_records(self, module: str, page: int = 1, per_page: int = 200) -> List[Dict]:
        """Fetch records from Zoho CRM module with pagination"""
        try:
            response = await self.zoho.get_records(module, page=page, per_page=per_page)
            return response.get("data", [])
        except Exception as e:
            print(f"❌ Error fetching {module}: {str(e)}")
            return []

    async def fetch_all_records(self, module: str) -> List[Dict]:
        """Fetch all records from a module (handle pagination)"""
        all_records = []
        page = 1
        
        while True:
            records = await self.fetch_records(module, page=page)
            if not records:
                break
            
            all_records.extend(records)
            print(f"📥 Fetched page {page} of {module}: {len(records)} records")
            
            if len(records) < 200:  # Last page
                break
            
            page += 1
        
        return all_records

    def transform_record(self, record: Dict, entity_type: str) -> Dict:
        """Transform Zoho record to Moodle format"""
        mapping = FIELD_MAPPINGS[entity_type]
        transformed = {}
        
        for zoho_field, moodle_field in mapping.items():
            value = record.get(zoho_field)
            
            # Handle lookup fields (extract ID)
            if isinstance(value, dict) and "id" in value:
                transformed[moodle_field] = value["id"]
            elif value is not None:
                transformed[moodle_field] = value
        
        return transformed

    async def call_moodle_ws(self, function: str, params: Dict) -> Dict:
        """Call Moodle Web Service"""
        url = f"{self.moodle_url}/webservice/rest/server.php"
        
        # Convert params dict to JSON string
        json_data = json.dumps(params)
        
        # Determine parameter name based on function
        if "grade" in function:
            param_name = "gradedata"
        elif "class" in function:
            param_name = "classdata"
        elif "registration" in function:
            param_name = "registrationdata"
        elif "payment" in function:
            param_name = "paymentdata"
        elif "enrollment" in function:
            param_name = "enrollmentdata"
        elif "request" in function:
            param_name = "requestdata"
        else:
            param_name = "studentdata"
        
        data = {
            "wstoken": self.moodle_token,
            "wsfunction": function,
            "moodlewsrestformat": "json",
            param_name: json_data
        }
        
        async with httpx.AsyncClient(timeout=30.0) as client:
            response = await client.post(url, data=data)
            
            if response.status_code == 200:
                result = response.json()
                if isinstance(result, dict) and "exception" in result:
                    raise Exception(f"Moodle error: {result.get('message', 'Unknown error')}")
                return result
            else:
                raise Exception(f"HTTP error: {response.status_code}")

    async def sync_entity(self, entity_type: str):
        """Sync all records of a specific entity type"""
        print(f"\n{'='*60}")
        print(f"🔄 Syncing {entity_type.upper()}...")
        print(f"{'='*60}")
        
        module = MODULES[entity_type]
        endpoint = MOODLE_ENDPOINTS[entity_type]
        
        # Fetch all records from Zoho
        records = await self.fetch_all_records(module)
        self.stats[entity_type]["fetched"] = len(records)
        
        if not records:
            print(f"ℹ️  No records found for {entity_type}")
            return
        
        print(f"📊 Total {entity_type} to sync: {len(records)}")
        
        # Initialize attachment handler for students
        if entity_type == "students" and not self.attachment_handler:
            access_token = await self.zoho.auth.get_access_token()
            self.attachment_handler = ZohoAttachmentHandler(access_token)
        
        # Sync each record to Moodle
        for idx, record in enumerate(records, 1):
            try:
                # Transform record
                transformed = self.transform_record(record, entity_type)
                
                # Download photo for students
                if entity_type == "students" and self.attachment_handler:
                    student_id = transformed.get("student_id", "")
                    zoho_id = transformed.get("zoho_student_id", "")
                    
                    if student_id and zoho_id:
                        print(f"   📷 Downloading photo for {student_id}...")
                        photo_path = await self.attachment_handler.find_and_download_photo(
                            module=module,
                            record_id=zoho_id,
                            student_id=student_id,
                            save_dir=self.photos_dir
                        )
                        
                        if photo_path and os.path.exists(photo_path):
                            # Read and encode photo as base64
                            try:
                                with open(photo_path, 'rb') as f:
                                    photo_base64 = base64.b64encode(f.read()).decode('utf-8')
                                
                                # Add to transformed data
                                transformed["photo_data"] = photo_base64
                                transformed["photo_filename"] = os.path.basename(photo_path)
                                transformed["photo_url"] = f"/student_photos/{os.path.basename(photo_path)}"
                                
                                self.stats["students"]["photos_downloaded"] += 1
                                print(f"   ✅ Photo encoded and ready to send")
                            except Exception as e:
                                print(f"   ⚠️ Failed to encode photo: {str(e)}")
                
                # Send to Moodle
                result = await self.call_moodle_ws(endpoint, transformed)
                
                self.stats[entity_type]["synced"] += 1
                print(f"✅ [{idx}/{len(records)}] Synced {entity_type}: {transformed.get(f'zoho_{entity_type[:-1]}_id', 'N/A')}")
                
            except Exception as e:
                self.stats[entity_type]["failed"] += 1
                print(f"❌ [{idx}/{len(records)}] Failed {entity_type}: {str(e)}")

    async def sync_single_student(self, email: str):
        """Sync a single student by email"""
        print(f"\n{'='*60}")
        print(f"🔍 Searching for student: {email}")
        print(f"{'='*60}")
        
        # Search student by Academic_Email or Email
        criteria = f"(Academic_Email:equals:{email})or(Email:equals:{email})"
        
        try:
            students = await self.zoho.search_records(MODULES['students'], criteria)
            
            if not students:
                print(f"❌ No student found with email: {email}")
                return
            
            student = students[0]
            
            print(f"\n{'='*60}")
            print(f"📋 RAW ZOHO DATA FOR STUDENT")
            print(f"{'='*60}")
            print(json.dumps(student, indent=2, ensure_ascii=False))
            print(f"{'='*60}\n")
            
            print(f"✅ Found student: {student.get('First_Name')} {student.get('Last_Name')}")
            print(f"   Zoho ID: {student.get('id')}")
            print(f"   Status: {student.get('Status')}")
            print(f"   Email: {student.get('Email')}")
            print(f"   Academic Email: {student.get('Academic_Email')}")
            
            # Transform and sync
            transformed = self.transform_record(student, "students")
            
            print(f"\n{'='*60}")
            print(f"📤 TRANSFORMED DATA FOR MOODLE")
            print(f"{'='*60}")
            print(json.dumps(transformed, indent=2, ensure_ascii=False))
            print(f"{'='*60}\n")
            
            # Try to find Moodle user by email and link automatically
            moodle_user_id = await self.find_moodle_user_by_email(email)
            if moodle_user_id:
                transformed['moodle_user_id'] = moodle_user_id
                print(f"   🔗 Linked to Moodle User ID: {moodle_user_id}")
            
            result = await self.call_moodle_ws(MOODLE_ENDPOINTS["students"], transformed)
            
            print(f"✅ Student synced successfully to Moodle!")
            print(f"   Response: {json.dumps(result, indent=2)}")
            
            # Now sync related records
            student_id = student.get("id")
            await self.sync_student_related_records(student_id)
            
        except Exception as e:
            print(f"❌ Error: {str(e)}")

    async def find_moodle_user_by_email(self, email: str) -> int:
        """Find Moodle user ID by email"""
        try:
            # Call Moodle core_user_get_users_by_field
            url = f"{self.moodle_url}/webservice/rest/server.php"
            
            data = {
                "wstoken": self.moodle_token,
                "wsfunction": "core_user_get_users_by_field",
                "moodlewsrestformat": "json",
                "field": "email",
                "values[0]": email
            }
            
            async with httpx.AsyncClient(timeout=30.0) as client:
                response = await client.post(url, data=data)
                
                if response.status_code == 200:
                    users = response.json()
                    if users and len(users) > 0:
                        return users[0]['id']
            
            return None
            
        except Exception as e:
            print(f"   ⚠️  Could not find Moodle user: {str(e)}")
            return None

    async def sync_student_related_records(self, zoho_student_id: str):
        """Sync all records related to a specific student"""
        print(f"\n🔗 Syncing related records for student: {zoho_student_id}")
        
        # ⚠️ Zoho API doesn't support searching by lookup fields directly
        # So we fetch ALL records and filter manually by zoho_student_id
        
        # 1. Sync ALL Classes first (independent, needed for enrollments)
        print("\n   📦 Syncing ALL classes from Zoho...")
        classes_response = await self.zoho.get_records(MODULES["classes"], per_page=5)  # LIMIT TO 5 FOR TESTING
        all_classes = classes_response.get("data", [])
        print(f"   📊 Found {len(all_classes)} total classes (limited to 5 for testing)")
        
        for cls in all_classes:
            try:
                transformed = self.transform_record(cls, "classes")
                result = await self.call_moodle_ws(MOODLE_ENDPOINTS["classes"], transformed)
                print(f"   ✅ Synced class: {cls.get('Class_Name', 'N/A')}")
            except Exception as e:
                print(f"   ❌ Failed class: {str(e)}")
        
        # 2. Sync Registrations for this student
        print("\n   📦 Fetching registrations for student...")
        regs_response = await self.zoho.get_records(MODULES["registrations"], per_page=200)
        all_registrations = regs_response.get("data", [])
        
        print(f"   ℹ️  Sample registration structure: {all_registrations[0] if all_registrations else 'No registrations'}")
        print(f"   ℹ️  Looking for student ID: {zoho_student_id}")
        
        student_registrations = [r for r in all_registrations if r.get("Student", {}).get("id") == zoho_student_id]
        print(f"   📊 Found {len(student_registrations)} registrations for this student")
        
        for reg in student_registrations:
            try:
                transformed = self.transform_record(reg, "registrations")
                result = await self.call_moodle_ws(MOODLE_ENDPOINTS["registrations"], transformed)
                print(f"   ✅ Synced registration: {reg.get('Program', {}).get('name', 'N/A')}")
                
                # Sync payments for this registration
                reg_id = reg.get("id")
                pmts_response = await self.zoho.get_records(MODULES["payments"], per_page=200)
                all_payments = pmts_response.get("data", [])
                reg_payments = [p for p in all_payments if p.get("Registration", {}).get("id") == reg_id]
                print(f"      💰 Found {len(reg_payments)} payments for this registration")
                
                for pmt in reg_payments:
                    try:
                        pmt_transformed = self.transform_record(pmt, "payments")
                        pmt_result = await self.call_moodle_ws(MOODLE_ENDPOINTS["payments"], pmt_transformed)
                        print(f"      ✅ Synced payment: {pmt.get('Payment_Number', 'N/A')}")
                    except Exception as e:
                        print(f"      ❌ Failed payment: {str(e)}")
                        
            except Exception as e:
                print(f"   ❌ Failed registration: {str(e)}")
        
        # 3. Sync Enrollments for this student
        print("\n   📦 Fetching enrollments for student...")
        enrs_response = await self.zoho.get_records(MODULES["enrollments"], per_page=200)
        all_enrollments = enrs_response.get("data", [])
        student_enrollments = [e for e in all_enrollments if e.get("Student", {}).get("id") == zoho_student_id]
        print(f"   📊 Found {len(student_enrollments)} enrollments for this student")
        
        for enr in student_enrollments:
            try:
                transformed = self.transform_record(enr, "enrollments")
                result = await self.call_moodle_ws(MOODLE_ENDPOINTS["enrollments"], transformed)
                print(f"   ✅ Synced enrollment: {enr.get('Class', {}).get('name', 'N/A')}")
            except Exception as e:
                print(f"   ❌ Failed enrollment: {str(e)}")
        
        # 4. Sync Grades for this student
        print("\n   📦 Fetching grades for student...")
        grades_response = await self.zoho.get_records(MODULES["grades"], per_page=200)
        all_grades = grades_response.get("data", [])
        student_grades = [g for g in all_grades if g.get("Student", {}).get("id") == zoho_student_id]
        print(f"   📊 Found {len(student_grades)} grades for this student")
        
        for grade in student_grades:
            try:
                transformed = self.transform_record(grade, "grades")
                result = await self.call_moodle_ws(MOODLE_ENDPOINTS["grades"], transformed)
                print(f"   ✅ Synced grade: {grade.get('Unit_Code', 'N/A')}")
            except Exception as e:
                print(f"   ❌ Failed grade: {str(e)}")
        
        # 5. Sync Requests for this student
        print("\n   📦 Fetching requests for student...")
        reqs_response = await self.zoho.get_records(MODULES["requests"], per_page=200)
        all_requests = reqs_response.get("data", [])
        student_requests = [r for r in all_requests if r.get("Student", {}).get("id") == zoho_student_id]
        print(f"   📊 Found {len(student_requests)} requests for this student")
        
        for req in student_requests:
            try:
                transformed = self.transform_record(req, "requests")
                result = await self.call_moodle_ws(MOODLE_ENDPOINTS["requests"], transformed)
                print(f"   ✅ Synced request: {req.get('Request_Type', 'N/A')}")
            except Exception as e:
                print(f"   ❌ Failed request: {str(e)}")

    async def sync_related(self, entity_type: str, lookup_field: str, lookup_id: str):
        """Sync related records by lookup field"""
        criteria = f"{lookup_field}:equals:{lookup_id}"
        
        try:
            records = await self.zoho.search_records(MODULES[entity_type], criteria)
            
            print(f"   📦 Found {len(records)} {entity_type}")
            
            for record in records:
                try:
                    transformed = self.transform_record(record, entity_type)
                    result = await self.call_moodle_ws(MOODLE_ENDPOINTS[entity_type], transformed)
                    print(f"   ✅ Synced {entity_type}: {transformed.get(f'zoho_{entity_type[:-1]}_id')}")
                except Exception as e:
                    print(f"   ❌ Failed {entity_type}: {str(e)}")
                    
        except Exception as e:
            print(f"   ℹ️  No {entity_type} found or error: {str(e)}")

    async def sync_all(self):
        """Sync all entities in correct order (maintain referential integrity)"""
        print("\n" + "="*60)
        print("🚀 STARTING INITIAL FULL SYNC FROM ZOHO TO MOODLE")
        print("="*60)
        print(f"⏰ Started at: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
        
        # Sync in order of dependencies
        sync_order = [
            "students",      # Must be first (no dependencies)
            "classes",       # Independent
            "registrations", # Depends on students
            "payments",      # Depends on registrations
            "enrollments",   # Depends on students + classes
            "grades",        # Depends on students + classes
            "requests"       # Depends on students
        ]
        
        for entity in sync_order:
            await self.sync_entity(entity)
        
        # Print summary
        print("\n" + "="*60)
        print("📊 SYNC SUMMARY")
        print("="*60)
        
        total_fetched = sum(s["fetched"] for s in self.stats.values())
        total_synced = sum(s["synced"] for s in self.stats.values())
        total_failed = sum(s["failed"] for s in self.stats.values())
        
        for entity, stats in self.stats.items():
            print(f"{entity.upper():15} | Fetched: {stats['fetched']:4} | Synced: {stats['synced']:4} | Failed: {stats['failed']:4}")
        
        print("-" * 60)
        print(f"{'TOTAL':15} | Fetched: {total_fetched:4} | Synced: {total_synced:4} | Failed: {total_failed:4}")
        print("="*60)
        print(f"⏰ Completed at: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
        
        success_rate = (total_synced / total_fetched * 100) if total_fetched > 0 else 0
        print(f"✅ Success Rate: {success_rate:.1f}%")


async def main():
    """Main entry point"""
    import sys
    
    service = InitialSyncService()
    
    if len(sys.argv) > 1:
        # Sync specific student by email
        email = sys.argv[1]
        await service.sync_single_student(email)
    else:
        # Full sync
        await service.sync_all()


if __name__ == "__main__":
    asyncio.run(main())
