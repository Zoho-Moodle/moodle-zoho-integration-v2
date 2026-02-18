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
import time
from collections import defaultdict

logger = logging.getLogger(__name__)

router = APIRouter()

# ✅ Request deduplication cache (composite_key → timestamp)
# Prevents multiple webhook calls for same grade within 10 seconds
_request_cache = defaultdict(float)


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
    logger.info(f"Processing grade_updated: User {data.get('student_id')} grade in course {data.get('course_id')}")
    logger.info(f"📦 Full data received from Plugin: {data}")
    
    try:
        from app.infra.zoho import create_zoho_client
        
        # ✅ CRITICAL FIX: Use correct field names from new observer
        # Old observer sent: userid, courseid, assignmentid
        # New observer sends: student_id, course_id, assignment_id
        student_id = data.get('student_id')  # ✅ Fixed from 'userid'
        assignment_id = data.get('assignment_id')  # ✅ Already correct
        course_id = data.get('course_id')  # ✅ Fixed from 'courseid'
        
        # Validate required fields
        if not student_id or not assignment_id:
            logger.error(f"❌ Missing required fields: student_id={student_id}, assignment_id={assignment_id}")
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail=f"Missing required fields: student_id={student_id}, assignment_id={assignment_id}"
            )
        
        # ✅ Deduplication: Check if this request was processed recently
        # ⚠️ SKIP deduplication for enrichment updates (Learning Outcomes)
        is_enrichment = data.get('is_enrichment_update', False) or data.get('sync_type') == 'enriched'
        
        composite_key = f"{student_id}_{course_id}_{assignment_id}"
        current_time = time.time()
        last_processed = _request_cache.get(composite_key, 0)
        
        if not is_enrichment and current_time - last_processed < 10:  # 10 seconds window
            logger.warning(f"⚠️ DUPLICATE REQUEST BLOCKED: {composite_key} (last processed {current_time - last_processed:.1f}s ago)")
            return {
                "success": True,
                "action": "deduplicated",
                "message": "Request already processed recently",
                "composite_key": composite_key
            }
        
        # Mark as processing (only for non-enrichment)
        if not is_enrichment:
            _request_cache[composite_key] = current_time
        
        # Clean old cache entries (older than 60 seconds)
        for key in list(_request_cache.keys()):
            if current_time - _request_cache[key] > 60:
                del _request_cache[key]
        
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
        
        # 🔍 Extract basic data from payload
        # ✅ Search for Student in Zoho by Moodle_ID
        # ✅ Search for Class in Zoho by name
        
        # Support multiple field names (observer vs extractor)
        student_name = data.get('student_name') or data.get('user_fullname', 'Unknown')
        student_email = data.get('student_email') or data.get('user_email', '')
        course_name = data.get('course_name', 'Unknown')
        moodle_student_id = data.get('student_id') or data.get('userid')  # Moodle's internal user ID
        
        logger.info(f"📝 Grade data: Student={student_name} (Moodle ID: {moodle_student_id}), Course={course_name}")
        
        # ✅ PRIORITY CHECK: RR Update Mode (must be FIRST before any enrichment)
        # This is triggered by Scheduled Task when detecting RR (R + No Submit)
        is_rr_update = data.get('is_rr_update', False)
        
        if is_rr_update and existing_grades and len(existing_grades) > 0:
            # RR Update: Fetch existing record and UPDATE only specific fields
            zoho_grade_id = existing_grades[0]['id']
            logger.info(f"🔴 RR Update mode: Updating existing record (ID: {zoho_grade_id}) to RR")
            
            try:
                # ✅ Fetch existing record to preserve all fields
                existing_record = await zoho.get_record('BTEC_Grades', zoho_grade_id)
                logger.info(f"✅ Fetched existing record with {len(existing_record)} fields")
                
                # ✅ Extract grade date from payload
                grade_date = data.get('graded_at', '')
                if grade_date and ' ' in grade_date:
                    grade_date = grade_date.split(' ')[0]  # Extract date only
                elif not grade_date:
                    from datetime import datetime
                    grade_date = datetime.now().strftime('%Y-%m-%d')
                
                # ✅ Merge: Update ONLY RR-specific fields, preserve everything else
                zoho_grade_data = existing_record.copy()
                zoho_grade_data.update({
                    "Grade": "RR",  # R → RR
                    "Grade_Status": "Double Refer",  # Clear status
                    "Attempt_Number": 2,  # Attempt 2
                    "BTEC_Grade_Name": f"{existing_record.get('Student_Name', student_name)} - {existing_record.get('Class_Name', course_name)} - RR - {existing_record.get('Attempt_Date', grade_date)}"
                })
                
                await zoho.update_record('BTEC_Grades', zoho_grade_id, zoho_grade_data)
                logger.info(f"✅ Updated to RR in Zoho (ID: {zoho_grade_id}) - Preserved: Grader, Feedback, Learning Outcomes")
                
                return {
                    "success": True,
                    "action": "rr_updated",
                    "message": "Grade updated from R to RR (preserved existing data)",
                    "zoho_id": zoho_grade_id
                }
            except Exception as e:
                logger.error(f"❌ Failed to update RR: {e}")
                raise HTTPException(status_code=500, detail=f"Failed to update RR: {e}")
        
        
        # ✅ Search for Student in Zoho by Student_Moodle_ID (CORRECTED FIELD NAME)
        student_zoho_id = None
        try:
            student_results = await zoho.search_records(
                'BTEC_Students',
                f"(Student_Moodle_ID:equals:{moodle_student_id})"
            )
            if student_results and len(student_results) > 0:
                student_zoho_id = student_results[0].get('id')
                logger.info(f"✅ Found Student in Zoho: ID={student_zoho_id}, Student_Moodle_ID={moodle_student_id}")
            else:
                logger.warning(f"⚠️ Student not found in Zoho: Student_Moodle_ID={moodle_student_id}")
        except Exception as e:
            logger.error(f"❌ Error searching for Student in Zoho: {e}")
        
        # ✅ Search for Class in Zoho by Class_Name (CORRECTED FIELD NAME)
        class_zoho_id = None
        try:
            class_results = await zoho.search_records(
                'BTEC_Classes',
                f"(Class_Name:equals:{course_name})"
            )
            if class_results and len(class_results) > 0:
                class_zoho_id = class_results[0].get('id')
                logger.info(f"✅ Found Class in Zoho: ID={class_zoho_id}, Class_Name={course_name}")
            else:
                logger.warning(f"⚠️ Class not found in Zoho: Class_Name={course_name}")
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
        # ✅ Data already extracted above (student_name, course_name)
        # ✅ Support both 'grade' (from observer) and 'btec_grade' (from scheduled task/extractor)
        grade = data.get('grade') or data.get('btec_grade')
        
        # ✅ Validate grade field - should NOT be empty/None
        if not grade or grade == '':
            logger.error(f"❌ Missing 'grade' or 'btec_grade' field in payload! Data keys: {data.keys()}")
            grade = 'N/A'  # Fallback only if truly missing
        else:
            # ✅ Normalize grade to single letter format
            grade_map = {
                'Fail': 'F', 'F': 'F',
                'Refer': 'R', 'R': 'R',
                'Pass': 'P', 'P': 'P',
                'Merit': 'M', 'M': 'M',
                'Distinction': 'D', 'D': 'D',
                'RR': 'RR'  # Double Refer stays as is
            }
            grade = grade_map.get(grade, grade)
            logger.info(f"✅ Grade extracted: {grade}")
        
        # ✅ Use graded_at field (format: "2026-02-09 14:30:00") - already in correct format!
        grade_date = data.get('graded_at', '')
        if grade_date and ' ' in grade_date:
            # Extract date part only (YYYY-MM-DD)
            grade_date = grade_date.split(' ')[0]
        elif not grade_date:
            # Fallback to timestamp conversion
            timestamp = data.get('timestamp', 0)
            grade_date = datetime.fromtimestamp(timestamp).strftime('%Y-%m-%d') if timestamp else datetime.now().strftime('%Y-%m-%d')
        
        btec_grade_name = f"{student_name} - {course_name} - {grade} - {grade_date}"
        
        # ✅ Check if this is enrichment-only update (Learning Outcomes OR grade change only)
        if is_enrichment and data.get('zoho_record_id'):
            zoho_grade_id = data.get('zoho_record_id')
            
            # ✅ CRITICAL: Fetch existing record to preserve all fields during UPDATE
            logger.info(f"🔄 Fetching existing record from Zoho (ID: {zoho_grade_id}) to preserve fields")
            try:
                existing_record = await zoho.get_record('BTEC_Grades', zoho_grade_id)
                logger.info(f"✅ Fetched existing record with {len(existing_record)} fields")
            except Exception as e:
                logger.error(f"❌ Failed to fetch existing record: {e}")
                existing_record = {}
            
            # ✅ Check if this is RR (Double Refer) update
            if grade == 'RR' or data.get('status') == 'Double Refer':
                logger.info(f"🔴 RR Detection mode: Updating grade to RR (Double Refer)")
                
                # ✅ Merge with existing data (preserve all fields)
                zoho_grade_data = existing_record.copy() if existing_record else {}
                zoho_grade_data.update({
                    "Grade": "RR",
                    "Grade_Status": "Double Refer",
                    "BTEC_Grade_Name": f"{existing_record.get('Student_Name', student_name)} - {existing_record.get('Class_Name', course_name)} - RR - {grade_date}"
                })
                
                await zoho.update_record('BTEC_Grades', zoho_grade_id, zoho_grade_data)
                logger.info(f"✅ Updated to RR in Zoho (ID: {zoho_grade_id})")
                
                return {
                    "success": True,
                    "action": "rr_detected",
                    "message": "Grade updated to RR (Double Refer)",
                    "zoho_id": zoho_grade_id
                }
            
            # ✅ Learning Outcomes enrichment (normal case)
            logger.info(f"🔄 Enrichment mode: Adding Learning Outcomes (preserving existing fields)")
            
            # ✅ Merge with existing data (preserve all fields)
            zoho_grade_data = existing_record.copy() if existing_record else {}
            zoho_grade_data.update({
                "Learning_Outcomes_Assessm": zoho_learning_outcomes if zoho_learning_outcomes else []
            })
            
            # Update existing record with merged data
            await zoho.update_record('BTEC_Grades', zoho_grade_id, zoho_grade_data)
            logger.info(f"✅ Enriched grade in Zoho (ID: {zoho_grade_id}) with {len(zoho_learning_outcomes)} Learning Outcomes")
            
            return {
                "success": True,
                "action": "enriched",
                "message": f"Grade enriched with {len(zoho_learning_outcomes)} Learning Outcomes",
                "zoho_id": zoho_grade_id
            }
        
        # ✅ FULL MODE: Create or update complete grade record
        # Base Zoho grade data - MATCHED TO ZOHO API FIELDS
        zoho_grade_data = {
            "BTEC_Grade_Name": btec_grade_name,
            "Moodle_Grade_Composite_Key": composite_key,
            "Student_Name": student_name,  # Single Line field in Zoho (fallback text)
            "Class_Name": course_name,     # Single Line field in Zoho (fallback text)
            "Grade": grade,
            "Moodle_Grade_ID": str(data.get('grade_id', '')),
            "Attempt_Number": int(data.get('attempt_number', 1)) if data.get('attempt_number') else 1,
            "Attempt_Date": grade_date,  # ✅ Now in YYYY-MM-DD format
            "Grade_Status": data.get('status') or data.get('workflow_state', 'Not marked'),  # ✅ Priority: status > workflow_state
            "Feedback": data.get('feedback', ''),
            "Learning_Outcomes_Assessm": zoho_learning_outcomes if zoho_learning_outcomes else []
        }
        
        # ✅ Add lookup fields if found in Zoho
        if student_zoho_id:
            zoho_grade_data["Student"] = {"id": student_zoho_id}
            logger.info(f"✅ Added Student lookup: {student_zoho_id}")
        else:
            logger.warning(f"⚠️ No Student lookup - using text-only: {student_name}")
            
        if class_zoho_id:
            zoho_grade_data["Class"] = {"id": class_zoho_id}
            logger.info(f"✅ Added Class lookup: {class_zoho_id}")
        else:
            logger.warning(f"⚠️ No Class lookup - using text-only: {course_name}")
        
        # ⚠️ Grader Role Logic (IV vs Teacher)
        # ✅ Updated field names from new observer
        grader_role = data.get('grader_role', 'other')  # Already correct
        grader_name = data.get('grader_name', '')  # ✅ Changed from 'grader_fullname' to 'grader_name'
        
        if grader_role.lower() == 'iv':  # ✅ Added .lower() for case-insensitive comparison
            # Internal Verifier
            zoho_grade_data["IV_Name"] = grader_name
        elif grader_role.lower() == 'teacher' or grader_name:
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
