"""
Student Dashboard Webhook Handler
Handles Zoho CRM webhooks and forwards to Moodle via Web Services API
"""
from fastapi import APIRouter, Depends, HTTPException, Request
from app.core.config import settings
import logging
import httpx
import json
from datetime import datetime
from typing import Dict, Any

logger = logging.getLogger(__name__)
router = APIRouter()


def transform_zoho_to_moodle(data: Dict, entity_type: str) -> Dict:
    """
    Transform Zoho CRM field names to Moodle format
    Converts PascalCase to snake_case and handles lookup fields
    """
    
    # Field mappings from Zoho to Moodle
    FIELD_MAPPINGS = {
        "classes": {
            "id": "zoho_class_id",
            "Class_Name": "class_name",
            "Unit": "unit_name",
            "Program_Level": "program_level",
            "Teacher": "teacher_name",  # Extract name from lookup
            "Start_Date": "start_date",
            "End_Date": "end_date",
            "Status": "class_status",
            "Class_Type": "class_type",
            "Created_Time": "zoho_created_time",
            "Modified_Time": "zoho_modified_time"
        },
        "registrations": {
            "id": "zoho_registration_id",
            "Student": "zoho_student_id",  # Extract id from lookup
            "Program": "program_name",  # Extract name from lookup
            "Registration_Number": "registration_number",
            "Registration_Date": "registration_date",
            "Registration_Status": "registration_status",
            "Total_Fees": "total_fees",
            "Paid_Amount": "paid_amount",
            "Remaining_Amount": "remaining_amount",
            "Created_Time": "zoho_created_time",
            "Modified_Time": "zoho_modified_time"
        },
        "enrollments": {
            "id": "zoho_enrollment_id",
            "Student": "zoho_student_id",  # Extract id from lookup
            "Class": "zoho_class_id",  # Extract id from lookup
            "Enrollment_Date": "enrollment_date",
            "Status": "enrollment_status",
            "Created_Time": "zoho_created_time",
            "Modified_Time": "zoho_modified_time"
        }
    }
    
    if entity_type not in FIELD_MAPPINGS:
        return data
    
    mapping = FIELD_MAPPINGS[entity_type]
    transformed = {}
    
    for zoho_field, moodle_field in mapping.items():
        value = data.get(zoho_field)
        
        # Handle lookup fields (extract id or name)
        if isinstance(value, dict):
            if "id" in value:
                transformed[moodle_field] = value["id"]
            elif "name" in value:
                transformed[moodle_field] = value["name"]
        elif value is not None:
            transformed[moodle_field] = value
    
    return transformed


async def call_moodle_ws(wsfunction: str, params: Dict[str, Any]) -> Dict:
    """
    Call Moodle Web Service API
    
    Args:
        wsfunction: Name of the Moodle web service function
        params: Parameters to pass to the function
    
    Returns:
        Response from Moodle API
    """
    if not settings.MOODLE_ENABLED:
        raise HTTPException(status_code=503, detail="Moodle API is disabled")
    
    if not settings.MOODLE_BASE_URL or not settings.MOODLE_TOKEN:
        raise HTTPException(status_code=503, detail="Moodle API not configured")
    
    url = f"{settings.MOODLE_BASE_URL}/webservice/rest/server.php"
    
    data = {
        "wstoken": settings.MOODLE_TOKEN,
        "wsfunction": wsfunction,
        "moodlewsrestformat": "json",
        **params
    }
    
    async with httpx.AsyncClient(timeout=30.0) as client:
        try:
            response = await client.post(url, data=data)
            response.raise_for_status()
            result = response.json()
            
            # Check for Moodle errors
            if isinstance(result, dict) and "exception" in result:
                logger.error(f"Moodle API error: {result}")
                raise HTTPException(status_code=500, detail=result.get("message", "Moodle API error"))
            
            return result
        except httpx.HTTPError as e:
            logger.error(f"HTTP error calling Moodle API: {e}")
            raise HTTPException(status_code=502, detail=f"Moodle API communication error: {str(e)}")


@router.post("/student_updated")
async def handle_student_updated(request: Request):
    """
    Webhook: Student data updated in Zoho CRM
    Calls Moodle: local_mzi_update_student
    """
    try:
        payload = await request.json()
        logger.info(f"📥 Student update webhook received: {payload.get('zoho_student_id')}")
        
        zoho_student_id = payload.get("zoho_student_id")
        if not zoho_student_id:
            raise HTTPException(status_code=400, detail="Missing 'zoho_student_id' in payload")
        
        # Send data directly to Moodle (already lowercase keys)
        # Call Moodle Web Service
        result = await call_moodle_ws(
            "local_mzi_update_student",
            {"studentdata": json.dumps(payload)}  # Send payload as-is with lowercase keys
        )
        
        logger.info(f"✅ Student {zoho_student_id} updated via Moodle API")
        return {
            "status": "success",
            "zoho_student_id": zoho_student_id,
            "moodle_response": result
        }
        
    except Exception as e:
        logger.error(f"❌ Error processing student webhook: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/registration_created")
async def handle_registration_created(request: Request):
    """Webhook: New registration created in Zoho CRM"""
    try:
        payload = await request.json()
        logger.info(f"📥 Registration created webhook: {payload.get('id')}")
        
        # Transform Zoho format to Moodle format
        transformed = transform_zoho_to_moodle(payload, "registrations")
        logger.info(f"🔄 Transformed data: {transformed}")
        
        result = await call_moodle_ws(
            "local_mzi_create_registration",
            {"registrationdata": json.dumps(transformed)}
        )
        
        return {"status": "success", "moodle_response": result}
    except Exception as e:
        logger.error(f"❌ Error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/payment_recorded")
async def handle_payment_recorded(request: Request):
    """Webhook: Payment recorded in Zoho CRM"""
    try:
        payload = await request.json()
        logger.info(f"📥 Payment recorded webhook: {payload.get('id')}")
        
        result = await call_moodle_ws(
            "local_mzi_record_payment",
            {"paymentdata": json.dumps(payload)}
        )
        
        return {"status": "success", "moodle_response": result}
    except Exception as e:
        logger.error(f"❌ Error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/class_created")
async def handle_class_created(request: Request):
    """Webhook: New class created in Zoho CRM"""
    try:
        payload = await request.json()
        logger.info(f"📥 Class created webhook: {payload.get('id')}")
        
        # Transform Zoho format to Moodle format
        transformed = transform_zoho_to_moodle(payload, "classes")
        logger.info(f"🔄 Transformed data: {transformed}")
        
        result = await call_moodle_ws(
            "local_mzi_create_class",
            {"classdata": json.dumps(transformed)}
        )
        
        return {"status": "success", "moodle_response": result}
    except Exception as e:
        logger.error(f"❌ Error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/enrollment_updated")
async def handle_enrollment_updated(request: Request):
    """Webhook: Student enrollment updated in Zoho CRM"""
    try:
        payload = await request.json()
        logger.info(f"📥 Enrollment updated webhook: {payload.get('id')}")
        
        # Transform Zoho format to Moodle format
        transformed = transform_zoho_to_moodle(payload, "enrollments")
        logger.info(f"🔄 Transformed data: {transformed}")
        
        result = await call_moodle_ws(
            "local_mzi_update_enrollment",
            {"enrollmentdata": json.dumps(transformed)}
        )
        
        return {"status": "success", "moodle_response": result}
    except Exception as e:
        logger.error(f"❌ Error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/grade_submitted")
async def handle_grade_submitted(request: Request):
    """Webhook: Grade submitted in Zoho CRM"""
    try:
        payload = await request.json()
        logger.info(f"📥 Grade submitted webhook: {payload.get('id')}")
        
        result = await call_moodle_ws(
            "local_mzi_submit_grade",
            {"gradedata": json.dumps(payload)}
        )
        
        return {"status": "success", "moodle_response": result}
    except Exception as e:
        logger.error(f"❌ Error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/request_status_changed")
async def handle_request_status_changed(request: Request):
    """Webhook: Student request status changed in Zoho CRM"""
    try:
        payload = await request.json()
        logger.info(f"📥 Request status changed webhook: {payload.get('id')}")
        
        result = await call_moodle_ws(
            "local_mzi_update_request_status",
            {"requestdata": json.dumps(payload)}
        )
        
        return {"status": "success", "moodle_response": result}
    except Exception as e:
        logger.error(f"❌ Error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))


# DELETE ENDPOINTS
@router.post("/student_deleted")
async def handle_student_deleted(request: Request):
    """Webhook: Student deleted in Zoho CRM"""
    try:
        payload = await request.json()
        zoho_student_id = payload.get("zoho_student_id")
        logger.info(f"📥 Student deleted webhook: {zoho_student_id}")
        
        if not zoho_student_id:
            raise HTTPException(status_code=400, detail="Missing 'zoho_student_id' in payload")
        
        result = await call_moodle_ws(
            "local_mzi_delete_student",
            {"zoho_student_id": zoho_student_id}
        )
        
        return {"status": "success", "moodle_response": result}
    except Exception as e:
        logger.error(f"❌ Error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/registration_deleted")
async def handle_registration_deleted(request: Request):
    """Webhook: Registration deleted in Zoho CRM"""
    try:
        payload = await request.json()
        zoho_registration_id = payload.get("zoho_registration_id")
        logger.info(f"📥 Registration deleted webhook: {zoho_registration_id}")
        
        if not zoho_registration_id:
            raise HTTPException(status_code=400, detail="Missing 'zoho_registration_id' in payload")
        
        result = await call_moodle_ws(
            "local_mzi_delete_registration",
            {"zoho_registration_id": zoho_registration_id}
        )
        
        return {"status": "success", "moodle_response": result}
    except Exception as e:
        logger.error(f"❌ Error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/payment_deleted")
async def handle_payment_deleted(request: Request):
    """Webhook: Payment deleted in Zoho CRM"""
    try:
        payload = await request.json()
        zoho_payment_id = payload.get("zoho_payment_id")
        logger.info(f"📥 Payment deleted webhook: {zoho_payment_id}")
        
        if not zoho_payment_id:
            raise HTTPException(status_code=400, detail="Missing 'zoho_payment_id' in payload")
        
        result = await call_moodle_ws(
            "local_mzi_delete_payment",
            {"zoho_payment_id": zoho_payment_id}
        )
        
        return {"status": "success", "moodle_response": result}
    except Exception as e:
        logger.error(f"❌ Error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/class_deleted")
async def handle_class_deleted(request: Request):
    """Webhook: Class deleted in Zoho CRM"""
    try:
        payload = await request.json()
        zoho_class_id = payload.get("zoho_class_id")
        logger.info(f"📥 Class deleted webhook: {zoho_class_id}")
        
        if not zoho_class_id:
            raise HTTPException(status_code=400, detail="Missing 'zoho_class_id' in payload")
        
        result = await call_moodle_ws(
            "local_mzi_delete_class",
            {"zoho_class_id": zoho_class_id}
        )
        
        return {"status": "success", "moodle_response": result}
    except Exception as e:
        logger.error(f"❌ Error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/enrollment_deleted")
async def handle_enrollment_deleted(request: Request):
    """Webhook: Enrollment deleted in Zoho CRM"""
    try:
        payload = await request.json()
        zoho_enrollment_id = payload.get("zoho_enrollment_id")
        logger.info(f"📥 Enrollment deleted webhook: {zoho_enrollment_id}")
        
        if not zoho_enrollment_id:
            raise HTTPException(status_code=400, detail="Missing 'zoho_enrollment_id' in payload")
        
        result = await call_moodle_ws(
            "local_mzi_delete_enrollment",
            {"zoho_enrollment_id": zoho_enrollment_id}
        )
        
        return {"status": "success", "moodle_response": result}
    except Exception as e:
        logger.error(f"❌ Error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/grade_deleted")
async def handle_grade_deleted(request: Request):
    """Webhook: Grade deleted in Zoho CRM"""
    try:
        payload = await request.json()
        zoho_grade_id = payload.get("zoho_grade_id")
        logger.info(f"📥 Grade deleted webhook: {zoho_grade_id}")
        
        if not zoho_grade_id:
            raise HTTPException(status_code=400, detail="Missing 'zoho_grade_id' in payload")
        
        result = await call_moodle_ws(
            "local_mzi_delete_grade",
            {"zoho_grade_id": zoho_grade_id}
        )
        
        return {"status": "success", "moodle_response": result}
    except Exception as e:
        logger.error(f"❌ Error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/request_deleted")
async def handle_request_deleted(request: Request):
    """Webhook: Request deleted in Zoho CRM"""
    try:
        payload = await request.json()
        zoho_request_id = payload.get("zoho_request_id")
        logger.info(f"📥 Request deleted webhook: {zoho_request_id}")
        
        if not zoho_request_id:
            raise HTTPException(status_code=400, detail="Missing 'zoho_request_id' in payload")
        
        result = await call_moodle_ws(
            "local_mzi_delete_request",
            {"zoho_request_id": zoho_request_id}
        )
        
        return {"status": "success", "moodle_response": result}
    except Exception as e:
        logger.error(f"❌ Error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))


