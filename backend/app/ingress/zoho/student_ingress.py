from typing import Any, Dict, List
from sqlalchemy.orm import Session
from app.ingress.zoho.parser import parse_zoho_payload
from app.services.student_mapper import map_zoho_to_canonical
from app.services.student_service import StudentService


def ingest_students(payload: Dict[str, Any], db: Session) -> List[Dict[str, Any]]:
    """
    Ingress stage: Parse Zoho payload, map to domain model, and sync to DB.
    
    Returns list of results for each student processed.
    """
    parsed_records = parse_zoho_payload(payload)
    service = StudentService(db)
    results = []

    for record in parsed_records:
        # Invalid record (failed parsing)
        if not record.get("valid"):
            results.append({
                "zoho_student_id": record.get("zoho_id", "unknown"),
                "status": "INVALID",
                "message": record.get("reason", "Failed to parse record")
            })
            continue

        # Map to domain model
        canonical = map_zoho_to_canonical(record)

        if canonical is None:
            results.append({
                "zoho_student_id": record.get("zoho_id", "unknown"),
                "status": "INVALID",
                "message": "Mapping or validation failed"
            })
            continue

        try:
            # Sync to database
            outcome = service.sync_student(canonical)
            results.append(outcome)
        except Exception as e:
            results.append({
                "zoho_student_id": record.get("zoho_id", "unknown"),
                "status": "ERROR",
                "message": f"Database error: {str(e)}"
            })

    return results
