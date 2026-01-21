from typing import List, Dict, Any
from datetime import datetime


def parse_zoho_classes_payload(payload: dict) -> List[Dict[str, Any]]:
    """Parse Zoho classes webhook payload"""
    records = payload.get("data", [])
    if not records:
        records = payload.get("records", [])
    
    parsed = []
    for r in records:
        zoho_id = r.get("id") or r.get("ID")
        # Try multiple field names for class name
        name = r.get("BTEC_Class_Name") or r.get("Class_Name") or r.get("Name")

        if not zoho_id or not name:
            parsed.append({
                "valid": False,
                "reason": "MISSING_ID_OR_NAME",
                "raw": r
            })
            continue

        # Parse dates
        start_date = None
        end_date = None
        if r.get("Start_Date"):
            try:
                start_date = r["Start_Date"]
            except:
                pass
        if r.get("End_Date"):
            try:
                end_date = r["End_Date"]
            except:
                pass

        # Try multiple field names for short name
        short_name = r.get("Short_Name") or r.get("Class_Short_Name")

        parsed.append({
            "valid": True,
            "zoho_id": str(zoho_id),
            "name": str(name).strip(),
            "short_name": short_name,
            "status": r.get("status") or r.get("Class_Status"),
            "start_date": start_date,
            "end_date": end_date,
            "moodle_class_id": r.get("Moodle_Class_ID"),
            "ms_teams_id": r.get("MS_Teams_ID"),
            "teacher_zoho_id": r.get("Teacher", {}).get("id") if isinstance(r.get("Teacher"), dict) else r.get("Teacher"),
            "unit_zoho_id": r.get("Unit", {}).get("id") if isinstance(r.get("Unit"), dict) else r.get("Unit"),
            "program_zoho_id": r.get("BTEC_Program", {}).get("id") if isinstance(r.get("BTEC_Program"), dict) else r.get("BTEC_Program"),
        })

    return parsed
