"""
Webhook endpoints to receive events from Moodle Plugin

Handles incoming webhook events from Moodle and forwards them to appropriate processors.

@package    backend
@copyright  2026 ABC Horizon
@license    MIT
"""

from fastapi import APIRouter, HTTPException, Header, Request, status
from pydantic import BaseModel, Field
from typing import Optional, Dict, Any
from datetime import datetime
import logging

logger = logging.getLogger(__name__)

router = APIRouter()


class WebhookEvent(BaseModel):
    """Model for incoming webhook events from Moodle"""
    event_id: str = Field(..., description="Unique event UUID")
    event_type: str = Field(..., description="Event type (user_created, enrollment_created, etc.)")
    event_data: Dict[str, Any] = Field(..., description="Event payload data")
    moodle_event_id: Optional[int] = Field(default=None, description="Moodle internal event ID")
    timestamp: Optional[int] = Field(default=None, description="Unix timestamp of event creation")
    
    class Config:
        schema_extra = {
            "example": {
                "event_id": "a12ec7d1-89ab-4cde-f012-3456789abcde",
                "event_type": "user_created",
                "event_data": {
                    "userid": 123,
                    "username": "student1",
                    "email": "student1@example.com",
                    "firstname": "John",
                    "lastname": "Doe"
                },
                "moodle_event_id": 456,
                "timestamp": 1706745600
            }
        }


class WebhookResponse(BaseModel):
    """Response model for webhook processing"""
    success: bool
    event_id: str
    message: str
    processed_at: str
    action: Optional[str] = Field(default=None, description="Action taken (created/updated/deleted)")



@router.post("/webhooks", response_model=WebhookResponse, status_code=status.HTTP_200_OK)
async def receive_webhook(
    event: WebhookEvent,
    authorization: Optional[str] = Header(None),
    request: Request = None
):
    """
    Receive webhook event from Moodle Plugin.
    
    This endpoint accepts webhook events from Moodle and processes them accordingly.
    Events are validated, logged, and forwarded to appropriate handlers.
    
    **Supported Event Types:**
    - `user_created` - New user created in Moodle
    - `user_updated` - User profile updated
    - `enrollment_created` - Student enrolled in course
    - `enrollment_deleted` - Student unenrolled from course
    - `grade_updated` - Grade assigned/updated for student
    - `course_created` - New course created
    - `course_updated` - Course details updated
    
    **Authentication:**
    - Optional Bearer token in Authorization header
    - Token validation can be enabled in configuration
    
    **Returns:**
    - 200 OK: Event received and processed successfully
    - 400 Bad Request: Invalid event data
    - 401 Unauthorized: Invalid or missing authentication token
    - 500 Internal Server Error: Processing failed
    """
    try:
        logger.info(f"Received webhook: {event.event_type} (ID: {event.event_id})")
        
        # TODO: Add token validation if required
        # if authorization:
        #     token = authorization.replace("Bearer ", "")
        #     if not validate_token(token):
        #         raise HTTPException(status_code=401, detail="Invalid authentication token")
        
        # Validate event type
        valid_event_types = [
            "user_created",
            "user_updated",
            "enrollment_created",
            "enrollment_deleted",
            "grade_updated",
            "course_created",
            "course_updated"
        ]
        
        if event.event_type not in valid_event_types:
            logger.warning(f"Unknown event type: {event.event_type}")
            raise HTTPException(
                status_code=400,
                detail=f"Unsupported event type: {event.event_type}"
            )
        
        # Process event based on type
        result = await process_webhook_event(event)
        
        if not result["success"]:
            raise HTTPException(status_code=500, detail=result["message"])
        
        response = WebhookResponse(
            success=True,
            event_id=event.event_id,
            message=f"Event {event.event_type} processed successfully",
            processed_at=datetime.utcnow().isoformat(),
            action=result.get("action")  # Pass action from result
        )
        
        logger.info(f"Successfully processed webhook: {event.event_id}")
        return response
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Error processing webhook {event.event_id}: {str(e)}", exc_info=True)
        raise HTTPException(
            status_code=500,
            detail=f"Internal server error: {str(e)}"
        )


async def process_webhook_event(event: WebhookEvent) -> Dict[str, Any]:
    """
    Process webhook event based on type.
    
    Routes the event to appropriate handler based on event_type.
    Can be extended to forward to Zoho CRM, send notifications, etc.
    
    Args:
        event: WebhookEvent object containing event data
        
    Returns:
        Dict with success status and message
    """
    try:
        event_data = event.event_data
        
        if event.event_type == "user_created":
            return await handle_user_created(event_data, event.event_id)
        
        elif event.event_type == "user_updated":
            return await handle_user_updated(event_data, event.event_id)
        
        elif event.event_type == "enrollment_created":
            return await handle_enrollment_created(event_data, event.event_id)
        
        elif event.event_type == "enrollment_deleted":
            return await handle_enrollment_deleted(event_data, event.event_id)
        
        elif event.event_type == "grade_updated":
            return await handle_grade_updated(event_data, event.event_id)
        
        elif event.event_type == "course_created":
            return await handle_course_created(event_data, event.event_id)
        
        elif event.event_type == "course_updated":
            return await handle_course_updated(event_data, event.event_id)
        
        else:
            logger.warning(f"No handler for event type: {event.event_type}")
            return {
                "success": False,
                "message": f"No handler configured for event type: {event.event_type}"
            }
            
    except Exception as e:
        logger.error(f"Error in process_webhook_event: {str(e)}", exc_info=True)
        return {
            "success": False,
            "message": str(e)
        }


# ============================================================================
# Event Handlers
# ============================================================================

async def handle_user_created(data: Dict[str, Any], event_id: str) -> Dict[str, Any]:
    """Handle user_created event"""
    logger.info(f"Processing user_created: User ID {data.get('userid')}")
    
    # TODO: Implement actual processing
    # - Validate user data
    # - Create record in local database
    # - Forward to Zoho CRM
    # - Send welcome email
    
    return {
        "success": True,
        "action": "created",
        "message": f"User {data.get('username')} created successfully"
    }


async def handle_user_updated(data: Dict[str, Any], event_id: str) -> Dict[str, Any]:
    """Handle user_updated event"""
    logger.info(f"Processing user_updated: User ID {data.get('userid')}")
    
    # TODO: Implement actual processing
    # - Update user record
    # - Sync changes to Zoho CRM
    
    return {
        "success": True,
        "action": "updated",
        "message": f"User {data.get('username')} updated successfully"
    }


async def handle_enrollment_created(data: Dict[str, Any], event_id: str) -> Dict[str, Any]:
    """Handle enrollment_created event"""
    logger.info(f"Processing enrollment_created: User {data.get('userid')} enrolled in course {data.get('courseid')}")
    
    # TODO: Implement actual processing
    # - Create enrollment record
    # - Update Zoho CRM enrollment status
    # - Trigger course access provisioning
    
    return {
        "success": True,
        "action": "created",
        "message": "Enrollment created successfully"
    }


async def handle_enrollment_deleted(data: Dict[str, Any], event_id: str) -> Dict[str, Any]:
    """Handle enrollment_deleted event"""
    logger.info(f"Processing enrollment_deleted: User {data.get('userid')} unenrolled from course {data.get('courseid')}")
    
    # TODO: Implement actual processing
    # - Update enrollment status
    # - Revoke course access
    # - Update Zoho CRM
    
    return {
        "success": True,
        "action": "deleted",
        "message": "Enrollment deleted successfully"
    }


async def handle_grade_updated(data: Dict[str, Any], event_id: str) -> Dict[str, Any]:
    """
    Handle grade_updated event
    
    Checks if grade exists in Zoho, then creates or updates accordingly.
    Returns action taken ('created' or 'updated') for accurate logging.
    """
    logger.info(f"Processing grade_updated: User {data.get('userid')} grade in course {data.get('courseid')}")
    logger.info(f"📦 Full data received from Plugin: {data}")
    
    try:
        from app.infra.zoho import create_zoho_client
        
        # Create composite key (student_id_assignment_id) - Each assignment is a separate grade!
        student_id = data.get('userid')
        assignment_id = data.get('assignment_id')
        composite_key = f"{student_id}_{assignment_id}"
        
        logger.info(f"🔍 Checking Zoho for existing grade with composite key: {composite_key} (Student {student_id}, Assignment {assignment_id})")
        
        # Initialize Zoho client
        zoho = create_zoho_client()
        
        # Search for existing grade in Zoho
        existing_grades = await zoho.search_records(
            'BTEC_Grades',
            f"(Moodle_Grade_Composite_Key:equals:{composite_key})"
        )
        
        print(f"🔎 DEBUG: Search for key '{composite_key}' returned: {existing_grades}")
        print(f"🔎 DEBUG: Number of results: {len(existing_grades) if existing_grades else 0}")
        logger.info(f"🔎 Search results: found {len(existing_grades) if existing_grades else 0} grades with key {composite_key}")
        if existing_grades:
            logger.info(f"📋 Existing grades details: {existing_grades}")
        
        # 🔍 Lookup Zoho IDs for Student and Class (like old code)
        course_id = data.get('courseid')
        
        logger.info(f"🔎 Searching for Student ID: {student_id} in BTEC_Students")
        # Search for Student in Zoho BTEC_Students
        student_zoho_id = None
        try:
            student_records = await zoho.search_records(
                'BTEC_Students',
                f"(Student_Moodle_ID:equals:{student_id})"
            )
            if student_records and len(student_records) > 0:
                student_zoho_id = student_records[0].get('id')
                logger.info(f"✅ Found Student in Zoho (ID: {student_zoho_id})")
            else:
                logger.warning(f"⚠️ Student {student_id} not found in Zoho BTEC_Students")
        except Exception as e:
            logger.error(f"❌ Error searching for Student in Zoho: {e}")
        
        # Search for Class in Zoho BTEC_Classes
        class_zoho_id = None
        try:
            class_records = await zoho.search_records(
                'BTEC_Classes',
                f"(Moodle_Class_ID:equals:{course_id})"
            )
            if class_records and len(class_records) > 0:
                class_zoho_id = class_records[0].get('id')
                logger.info(f"✅ Found Class in Zoho (ID: {class_zoho_id})")
            else:
                logger.warning(f"⚠️ Class {course_id} not found in Zoho BTEC_Classes")
        except Exception as e:
            logger.error(f"❌ Error searching for Class in Zoho: {e}")
        
        # Prepare Zoho grade data
        learning_outcomes = data.get('learning_outcomes', [])
        
        # Transform learning outcomes to Zoho subform format
        # Zoho API fields: LO_Code, LO_Outcome_Identification, LO_Definition, LO_Feedback, LO_Score, LO_Title
        zoho_learning_outcomes = []
        for lo in learning_outcomes:
            lo_entry = {
                "LO_Code": lo.get('code', ''),  # e.g., "P1", "M2", "D3"
                "LO_Outcome_Identification": lo.get('code', ''),  # Same as code for identification
                "LO_Definition": lo.get('description', ''),  # Full definition/description
                "LO_Title": lo.get('description', ''),  # Title (same as definition)
                "LO_Score": lo.get('score', ''),  # Numeric score from fillings table
                "LO_Feedback": lo.get('feedback', ''),  # Remark/feedback from fillings table
            }
            zoho_learning_outcomes.append(lo_entry)
        
        # Build BTEC_Grade_Name: "Student Name - Course Name - Grade - Date"
        from datetime import datetime
        student_name = data.get('user_fullname', 'Unknown')
        course_name = data.get('course_name', 'Unknown')
        grade = data.get('btec_grade', 'N/A')
        timestamp = data.get('timemodified', data.get('timecreated', 0))
        grade_date = datetime.fromtimestamp(timestamp).strftime('%Y-%m-%d') if timestamp else 'Unknown'
        
        btec_grade_name = f"{student_name} - {course_name} - {grade} - {grade_date}"
        
        # Base Zoho grade data - MATCHED TO ZOHO API FIELDS
        zoho_grade_data = {
            "BTEC_Grade_Name": btec_grade_name,
            "Moodle_Grade_Composite_Key": composite_key,
            "Student_Name": student_name,  # Single Line field in Zoho
            "Class_Name": course_name,     # Single Line field in Zoho
            "Grade": data.get('btec_grade', ''),
            "Moodle_Grade_ID": str(data.get('grade_id', '')),
            "Attempt_Number": int(data.get('attempt_number', 1)) if data.get('attempt_number') else 1,
            "Attempt_Date": grade_date,
            "Grade_Status": data.get('workflow_state', 'Not marked'),
            "Feedback": data.get('feedback', ''),
            "Learning_Outcomes_Assessm": zoho_learning_outcomes if zoho_learning_outcomes else []
        }
        
        # 🔗 Add Student & Class Lookup Fields
        if student_zoho_id:
            zoho_grade_data["Student"] = {"id": student_zoho_id}
        
        if class_zoho_id:
            zoho_grade_data["Class"] = {"id": class_zoho_id}
        
        # ⚠️ Grader Role Logic (IV vs Teacher)
        grader_role = data.get('grader_role', 'other')
        grader_name = data.get('grader_fullname', '')
        
        if grader_role == 'iv':
            # Internal Verifier
            zoho_grade_data["IV_Name"] = grader_name
        elif grader_role == 'teacher' or grader_name:
            # Regular Teacher (or fallback if grader exists)
            zoho_grade_data["Grader_Name"] = grader_name
        
        action = "updated"
        if existing_grades and len(existing_grades) > 0:
            # Grade exists → UPDATE
            zoho_grade_id = existing_grades[0].get('id')
            logger.info(f"✅ Found existing grade in Zoho (ID: {zoho_grade_id}) → UPDATE")
            action = "updated"
            
            # Update the grade in Zoho
            await zoho.update_record('BTEC_Grades', zoho_grade_id, zoho_grade_data)
            logger.info(f"✅ Updated grade in Zoho (ID: {zoho_grade_id})")
        else:
            # Grade doesn't exist → CREATE
            logger.info(f"🆕 No existing grade found in Zoho → CREATE")
            action = "created"
            
            try:
                # Create the grade in Zoho
                result = await zoho.create_record('BTEC_Grades', zoho_grade_data)
                zoho_grade_id = result.get('details', {}).get('id')
                logger.info(f"✅ Created new grade in Zoho (ID: {zoho_grade_id})")
            except Exception as create_error:
                # Check if it's a duplicate error (race condition or search failed)
                error_str = str(create_error)
                if 'DUPLICATE_DATA' in error_str:
                    logger.warning(f"⚠️ DUPLICATE_DATA error - grade exists but search didn't find it!")
                    # Extract the Zoho ID from the error
                    import re
                    match = re.search(r"'id':\s*'(\d+)'", error_str)
                    if match:
                        zoho_grade_id = match.group(1)
                        logger.info(f"📌 Extracted existing grade ID from error: {zoho_grade_id}")
                        # Update instead of create
                        await zoho.update_record('BTEC_Grades', zoho_grade_id, zoho_grade_data)
                        logger.info(f"✅ Updated grade in Zoho (ID: {zoho_grade_id}) via fallback")
                        action = "updated"
                    else:
                        raise  # Re-raise if we can't extract ID
                else:
                    raise  # Re-raise if it's not a duplicate error
        
        return {
            "success": True,
            "action": action,  # 'created' or 'updated'
            "message": f"Grade {action} successfully"
        }
        
    except Exception as e:
        logger.error(f"❌ Error processing grade: {str(e)}")
        return {
            "success": False,
            "action": "error",
            "message": f"Error: {str(e)}"
        }


async def handle_course_created(data: Dict[str, Any], event_id: str) -> Dict[str, Any]:
    """Handle course_created event"""
    logger.info(f"Processing course_created: Course ID {data.get('courseid')}")
    
    # TODO: Implement actual processing
    # - Create course record
    # - Sync to Zoho CRM
    
    return {
        "success": True,
        "action": "created",
        "message": "Course created successfully"
    }


async def handle_course_updated(data: Dict[str, Any], event_id: str) -> Dict[str, Any]:
    """Handle course_updated event"""
    logger.info(f"Processing course_updated: Course ID {data.get('courseid')}")
    
    # TODO: Implement actual processing
    # - Update course record
    # - Sync changes to Zoho CRM
    
    return {
        "success": True,
        "action": "updated",
        "message": "Course updated successfully"
    }


# ============================================================================
# Webhook Status & Testing Endpoints
# ============================================================================

@router.get("/webhooks/status")
async def webhook_status():
    """
    Get webhook receiver status.
    
    Useful for health checks and monitoring.
    """
    return {
        "status": "operational",
        "service": "Webhook Receiver",
        "version": "3.1.1",
        "supported_events": [
            "user_created",
            "user_updated",
            "enrollment_created",
            "enrollment_deleted",
            "grade_updated",
            "course_created",
            "course_updated"
        ],
        "timestamp": datetime.utcnow().isoformat()
    }


@router.post("/webhooks/test")
async def test_webhook():
    """
    Test webhook endpoint.
    
    Accepts any payload and returns success.
    Useful for testing Moodle plugin webhook configuration.
    """
    return {
        "success": True,
        "message": "Test webhook received successfully",
        "timestamp": datetime.utcnow().isoformat()
    }
