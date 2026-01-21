import logging
from typing import List, Dict, Any
from sqlalchemy.orm import Session

from app.domain.program import CanonicalProgram
from app.services.program_mapper import map_zoho_to_canonical_program
from app.services.program_service import ProgramService
from app.ingress.zoho.program_parser import parse_zoho_programs_payload

logger = logging.getLogger(__name__)


def ingest_programs(payload: dict, db: Session, tenant_id: str = "default") -> List[Dict[str, Any]]:
    """
    Ingest Zoho programs webhook payload.
    
    Returns list of sync results with status: NEW/UPDATED/UNCHANGED/INVALID/ERROR
    """
    try:
        # Parse payload
        parsed_records = parse_zoho_programs_payload(payload)
        logger.info(f"Parsed {len(parsed_records)} program records")
        
        # Map to canonical models
        canonical_programs = []
        for record in parsed_records:
            if not record.get("valid"):
                logger.warning(f"Invalid record: {record.get('reason')}")
                continue
            
            try:
                canonical = map_zoho_to_canonical_program(record)
                canonical_programs.append(canonical)
            except Exception as e:
                logger.error(f"Mapping error for {record.get('zoho_id')}: {str(e)}")
                continue
        
        if not canonical_programs:
            logger.warning("No valid canonical programs to sync")
            return []
        
        # Sync programs
        service = ProgramService(db)
        results = []
        for program in canonical_programs:
            try:
                result = service.sync_program(program, tenant_id)
                results.append(result)
            except Exception as e:
                logger.exception(f"Sync error for {program.zoho_id}: {str(e)}")
                results.append({
                    "zoho_program_id": program.zoho_id,
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
