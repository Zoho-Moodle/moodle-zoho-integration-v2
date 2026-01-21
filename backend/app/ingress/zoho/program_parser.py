from typing import List, Dict, Any


def parse_zoho_programs_payload(payload: dict) -> List[Dict[str, Any]]:
    """Parse Zoho programs webhook payload"""
    records = payload.get("data", [])
    if not records:
        records = payload.get("records", [])
    
    parsed = []
    for r in records:
        zoho_id = r.get("id") or r.get("ID") or r.get("Product_ID")
        name = r.get("Product_Name") or r.get("Name")

        if not zoho_id or not name:
            parsed.append({
                "valid": False,
                "reason": "MISSING_ID_OR_NAME",
                "raw": r
            })
            continue

        parsed.append({
            "valid": True,
            "zoho_id": str(zoho_id),
            "name": str(name).strip(),
            "price": float(r.get("Program_Price", 0)) if r.get("Program_Price") else None,
            "moodle_id": r.get("MoodleID"),
            "status": r.get("Status")
        })

    return parsed
