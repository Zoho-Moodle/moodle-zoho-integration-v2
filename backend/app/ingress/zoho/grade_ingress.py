"""
Grade Ingress

Converts raw Zoho webhook payload to canonical grades and syncs to DB.
"""

from typing import Any, Dict, List
from sqlalchemy.orm import Session
from app.ingress.zoho.grade_parser import parse_grade
from app.services.grade_service import GradeService
from app.core.config import settings


def ingest_grades(payload: Dict[str, Any], db: Session, tenant_id: str = None) -> List[Dict[str, Any]]:
    """
    Ingress stage: Parse Zoho payload, map to domain model, and sync to DB.
    
    Payload expected format:
    {
      "data": [
        {
          "id": "grade_123",
          "Student": {...},
          "Unit": {...},
          ...
        },
        ...
      ]
    }
    
    Returns list of results for each grade processed.
    """
    if tenant_id is None:
        tenant_id = settings.DEFAULT_TENANT_ID
    
    # Extract data array
    data_array = payload.get("data", [])
    if not isinstance(data_array, list):
        data_array = [payload]  # Fallback: assume single record
    
    service = GradeService(db)
    results = []

    for raw_record in data_array:
        try:
            # Parse raw Zoho record to canonical
            canonical = parse_grade(raw_record)
            
            # Sync to database
            outcome = service.sync_grade(canonical, tenant_id)
            results.append(outcome)
            
        except ValueError as e:
            results.append({
                "zoho_grade_id": raw_record.get("id", "unknown"),
                "status": "INVALID",
                "message": str(e)
            })
        except Exception as e:
            results.append({
                "zoho_grade_id": raw_record.get("id", "unknown"),
                "status": "ERROR",
                "message": f"Database error: {str(e)}"
            })

    return results
