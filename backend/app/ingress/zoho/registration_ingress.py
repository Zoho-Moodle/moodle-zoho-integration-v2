"""
Registration Ingress

Converts raw Zoho webhook payload to canonical registrations and syncs to DB.
"""

from typing import Any, Dict, List
from sqlalchemy.orm import Session
from app.ingress.zoho.registration_parser import parse_registration
from app.services.registration_service import RegistrationService
from app.core.config import settings


def ingest_registrations(payload: Dict[str, Any], db: Session, tenant_id: str = None) -> List[Dict[str, Any]]:
    """
    Ingress stage: Parse Zoho payload, map to domain model, and sync to DB.
    
    Payload expected format:
    {
      "data": [
        {
          "id": "reg_123",
          "Student": {...},
          "Program": {...},
          ...
        },
        ...
      ]
    }
    
    Returns list of results for each registration processed.
    """
    if tenant_id is None:
        tenant_id = settings.DEFAULT_TENANT_ID
    
    # Extract data array
    data_array = payload.get("data", [])
    if not isinstance(data_array, list):
        data_array = [payload]  # Fallback: assume single record
    
    service = RegistrationService(db)
    results = []

    for raw_record in data_array:
        try:
            # Parse raw Zoho record to canonical
            canonical = parse_registration(raw_record)
            
            # Sync to database
            outcome = service.sync_registration(canonical, tenant_id)
            results.append(outcome)
            
        except ValueError as e:
            results.append({
                "zoho_registration_id": raw_record.get("id", "unknown"),
                "status": "INVALID",
                "message": str(e)
            })
        except Exception as e:
            results.append({
                "zoho_registration_id": raw_record.get("id", "unknown"),
                "status": "ERROR",
                "message": f"Database error: {str(e)}"
            })

    return results
