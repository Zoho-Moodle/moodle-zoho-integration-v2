import logging
from typing import List, Dict, Any
from sqlalchemy.orm import Session

from app.domain.class_ import CanonicalClass
from app.services.class_mapper import map_zoho_to_canonical_class
from app.services.class_service import ClassService
from app.ingress.zoho.class_parser import parse_zoho_classes_payload

logger = logging.getLogger(__name__)


def ingest_classes(payload: dict, db: Session, tenant_id: str = "default") -> List[Dict[str, Any]]:
    """
    Ingest Zoho classes webhook payload.
    
    Returns list of sync results with status: NEW/UPDATED/UNCHANGED/INVALID/ERROR
    """
    try:
        # Parse payload
        parsed_records = parse_zoho_classes_payload(payload)
        logger.info(f"Parsed {len(parsed_records)} class records")
        
        # Map to canonical models
        canonical_classes = []
        for record in parsed_records:
            if not record.get("valid"):
                logger.warning(f"Invalid record: {record.get('reason')}")
                continue
            
            try:
                canonical = map_zoho_to_canonical_class(record)
                canonical_classes.append(canonical)
            except Exception as e:
                logger.error(f"Mapping error for {record.get('zoho_id')}: {str(e)}")
                continue
        
        if not canonical_classes:
            logger.warning("No valid canonical classes to sync")
            return []
        
        # Sync classes
        service = ClassService(db)
        results = []
        for cls in canonical_classes:
            try:
                result = service.sync_class(cls, tenant_id)
                results.append(result)
            except Exception as e:
                logger.exception(f"Sync error for {cls.zoho_id}: {str(e)}")
                results.append({
                    "zoho_class_id": cls.zoho_id,
                    "status": "ERROR",
                    "message": f"Sync error: {str(e)}"
                })
        
        return results
    
    except Exception as e:
        logger.exception(f"Ingestion error: {str(e)}")
        return [{
            "status": "ERROR",
            "message": f"Ingestion failed: {str(e)}"
        }]
