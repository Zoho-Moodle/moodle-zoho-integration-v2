def parse_zoho_payload(payload: dict) -> list[dict]:
    records = payload.get("data", [])
    parsed = []

    for r in records:
        zoho_id = r.get("id") or r.get("ID")
        name = r.get("Name")

        if not zoho_id or not name:
            parsed.append({
                "valid": False,
                "reason": "MISSING_ZOHO_ID_OR_NAME",
                "raw": r
            })
            continue

        parsed.append({
            "valid": True,
            "zoho_id": zoho_id,
            "name": name,
            "academic_email": r.get("Academic_Email"),
            "phone": r.get("Phone_Number"),
            "status": r.get("Status")
        })

    return parsed
