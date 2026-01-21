import logging
from typing import List, Dict, Any
from sqlalchemy.orm import Session

from app.domain.enrollment import CanonicalEnrollment
from app.services.enrollment_mapper import map_zoho_to_canonical_enrollment
from app.services.enrollment_service import EnrollmentService
from app.ingress.zoho.enrollment_parser import parse_zoho_enrollments_payload

logger = logging.getLogger(__name__)


def ingest_enrollments(payload: dict, db: Session, tenant_id: str = "default") -> List[Dict[str, Any]]:
    """
    Ingest Zoho enrollments webhook payload.
    
    Returns list of sync results with status: NEW/UPDATED/UNCHANGED/INVALID/SKIPPED/ERROR
    """
    try:
        # Parse payload
        parsed_records = parse_zoho_enrollments_payload(payload)
        logger.info(f"Parsed {len(parsed_records)} enrollment records")
        
        # Map to canonical models
        canonical_enrollments = []
        for record in parsed_records:
            if not record.get("valid"):
                logger.warning(f"Invalid record: {record.get('reason')}")
                continue
            
            try:
                canonical = map_zoho_to_canonical_enrollment(record)
                canonical_enrollments.append(canonical)
            except Exception as e:
                logger.error(f"Mapping error for {record.get('zoho_id')}: {str(e)}")
                continue
        
        if not canonical_enrollments:
            logger.warning("No valid canonical enrollments to sync")
            return []
        
        # Sync enrollments
        service = EnrollmentService(db)
        results = []
        for enrollment in canonical_enrollments:
            try:
                result = service.sync_enrollment(enrollment, tenant_id)
                results.append(result)
            except Exception as e:
                logger.exception(f"Sync error for {enrollment.zoho_id}: {str(e)}")
                results.append({
                    "zoho_enrollment_id": enrollment.zoho_id,
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
