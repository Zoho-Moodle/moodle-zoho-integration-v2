"""
Unit Ingress

Converts raw Zoho webhook payload to canonical units and syncs to DB.
"""

from typing import Any, Dict, List
from sqlalchemy.orm import Session
from app.ingress.zoho.unit_parser import parse_unit
from app.services.unit_service import UnitService
from app.core.config import settings


def ingest_units(payload: Dict[str, Any], db: Session, tenant_id: str = None) -> List[Dict[str, Any]]:
    """
    Ingress stage: Parse Zoho payload, map to domain model, and sync to DB.
    
    Payload expected format:
    {
      "data": [
        {
          "id": "unit_123",
          "Unit_Code": "UNIT001",
          ...
        },
        ...
      ]
    }
    
    Returns list of results for each unit processed.
    """
    if tenant_id is None:
        tenant_id = settings.DEFAULT_TENANT_ID
    
    # Extract data array
    data_array = payload.get("data", [])
    if not isinstance(data_array, list):
        data_array = [payload]  # Fallback: assume single record
    
    service = UnitService(db)
    results = []

    for raw_record in data_array:
        try:
            # Parse raw Zoho record to canonical
            canonical = parse_unit(raw_record)
            
            # Sync to database
            outcome = service.sync_unit(canonical, tenant_id)
            results.append(outcome)
            
        except ValueError as e:
            results.append({
                "zoho_unit_id": raw_record.get("id", "unknown"),
                "status": "INVALID",
                "message": str(e)
            })
        except Exception as e:
            results.append({
                "zoho_unit_id": raw_record.get("id", "unknown"),
                "status": "ERROR",
                "message": f"Database error: {str(e)}"
            })

    return results
