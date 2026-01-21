from typing import List, Dict, Any
from datetime import datetime


def parse_zoho_enrollments_payload(payload: dict) -> List[Dict[str, Any]]:
    """Parse Zoho enrollments webhook payload"""
    records = payload.get("data", [])
    if not records:
        records = payload.get("records", [])
    
    parsed = []
    for r in records:
        zoho_id = r.get("id") or r.get("ID") or r.get("Enrollment_ID")
        
        # Enrollment Name is the auto number
        enrollment_name = r.get("Name")
        
        # Student is required - try multiple field names
        student_lookup = r.get("Student") or r.get("Contact")
        student_zoho_id = student_lookup.get("id") if isinstance(student_lookup, dict) else student_lookup
        
        # Class is required - try multiple field names
        class_lookup = r.get("BTEC_Class") or r.get("Class")
        class_zoho_id = class_lookup.get("id") if isinstance(class_lookup, dict) else class_lookup
        
        if not zoho_id or not student_zoho_id or not class_zoho_id:
            parsed.append({
                "valid": False,
                "reason": "MISSING_REQUIRED_FIELDS",
                "raw": r
            })
            continue

        # Parse dates
        start_date = None
        last_sync_date = None
        if r.get("Start_Date"):
            try:
                start_date = r["Start_Date"]
            except:
                pass
        if r.get("Last_Sync_Date"):
            try:
                last_sync_date = r["Last_Sync_Date"]
            except:
                pass

        parsed.append({
            "valid": True,
            "zoho_id": str(zoho_id),
            "enrollment_name": enrollment_name,
            "student_zoho_id": str(student_zoho_id),
            "student_name": r.get("Student_Name"),
            "class_zoho_id": str(class_zoho_id),
            "class_name": r.get("Class_Name"),
            "program_zoho_id": r.get("Enrolled_Program", {}).get("id") if isinstance(r.get("Enrolled_Program"), dict) else r.get("Enrolled_Program"),
            "start_date": start_date,
            "status": r.get("Status"),
            "moodle_course_id": r.get("Moodle_Course_ID"),
            "last_sync_date": last_sync_date,
        })

    return parsed
