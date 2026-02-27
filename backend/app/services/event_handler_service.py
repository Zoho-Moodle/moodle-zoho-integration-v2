"""
Event Handler Service

Routes webhook events to appropriate sync services and handles event processing.
"""

import logging
from typing import Dict, Any, Optional
from datetime import datetime
from sqlalchemy.orm import Session

from app.domain.events import (
    ZohoWebhookEvent, MoodleWebhookEvent,
    EventSource, EventStatus, EventProcessingResult
)
from app.infra.db.models.event_log import EventLog
from app.infra.db.models import Student
from app.services.grade_sync_service import GradeSyncService
from app.services.student_profile_service import StudentProfileService, StudentData
from app.services.enrollment_sync_service import EnrollmentSyncService
from app.services.payment_sync_service import PaymentSyncService

logger = logging.getLogger(__name__)


class EventHandlerService:
    """
    Central event handler that routes webhooks to appropriate services.
    
    Responsibilities:
    1. Log events to database (deduplication)
    2. Route events to correct sync service
    3. Track processing status
    4. Handle errors and retries
    """
    
    def __init__(
        self,
        db: Session,
        zoho_client,
        grade_service: Optional[GradeSyncService] = None,
        student_service: Optional[StudentProfileService] = None,
        enrollment_service: Optional[EnrollmentSyncService] = None,
        payment_service: Optional[PaymentSyncService] = None
    ):
        """
        Initialize event handler.
        
        Args:
            db: Database session
            zoho_client: ZohoClient instance
            grade_service: Optional GradeSyncService instance
            student_service: Optional StudentProfileService instance
            enrollment_service: Optional EnrollmentSyncService instance
            payment_service: Optional PaymentSyncService instance
        """
        self.db = db
        self.zoho_client = zoho_client
        
        # Initialize services if not provided
        self.grade_service = grade_service or GradeSyncService(zoho_client)
        self.student_service = student_service or StudentProfileService(zoho_client)
        self.enrollment_service = enrollment_service or EnrollmentSyncService(zoho_client)
        self.payment_service = payment_service or PaymentSyncService(zoho_client)
        
        logger.info("EventHandlerService initialized")
    
    async def handle_zoho_event(
        self,
        event: ZohoWebhookEvent
    ) -> EventProcessingResult:
        """
        Handle Zoho webhook event.
        
        Args:
            event: ZohoWebhookEvent instance
            
        Returns:
            EventProcessingResult with processing details
        """
        start_time = datetime.utcnow()
        
        try:
            logger.info(
                f"Handling Zoho event: {event.event_id}, "
                f"module={event.module}, operation={event.operation}"
            )
            
            # Check for duplicate
            if self._is_duplicate_event(event.event_id):
                logger.info(f"Duplicate event detected: {event.event_id}")
                return EventProcessingResult(
                    event_id=event.event_id,
                    status=EventStatus.DUPLICATE,
                    action_taken="skipped",
                    processing_time_ms=0
                )
            
            # Log event
            event_log = self._log_event(
                event_id=event.event_id,
                source=EventSource.ZOHO,
                module=event.module,
                event_type=event.operation,
                record_id=event.record_id,
                payload={
                    "module": event.module,
                    "operation": event.operation,
                    "record_id": event.record_id,
                    "data": event.record_data,
                    "timestamp": event.timestamp.isoformat()
                }
            )
            
            # Update status to processing
            event_log.status = EventStatus.PROCESSING.value
            self.db.commit()
            
            # Route to appropriate service based on module
            result = await self._route_zoho_event(event)
            
            # Update event log with result
            event_log.status = result.status.value
            event_log.result = {
                "action_taken": result.action_taken,
                "record_id": result.record_id,
                "error": result.error
            }
            event_log.processed_at = datetime.utcnow()
            if result.error:
                event_log.error_message = result.error
            
            self.db.commit()
            
            # Calculate processing time
            processing_time = (datetime.utcnow() - start_time).total_seconds() * 1000
            result.processing_time_ms = processing_time
            
            logger.info(
                f"Zoho event processed: {event.event_id}, "
                f"status={result.status}, time={processing_time:.2f}ms"
            )
            
            return result
            
        except Exception as e:
            logger.error(f"Error handling Zoho event {event.event_id}: {e}", exc_info=True)
            
            # Update event log with error
            if 'event_log' in locals():
                event_log.status = EventStatus.FAILED.value
                event_log.error_message = str(e)
                event_log.processed_at = datetime.utcnow()
                self.db.commit()
            
            processing_time = (datetime.utcnow() - start_time).total_seconds() * 1000
            
            return EventProcessingResult(
                event_id=event.event_id,
                status=EventStatus.FAILED,
                error=str(e),
                processing_time_ms=processing_time
            )
    
    async def _route_zoho_event(
        self,
        event: ZohoWebhookEvent
    ) -> EventProcessingResult:
        """
        Route Zoho event to appropriate service.
        
        Args:
            event: ZohoWebhookEvent instance
            
        Returns:
            EventProcessingResult
        """
        module = event.module
        operation = event.operation
        record_id = event.record_id
        
        try:
            # BTEC_Students - Student profile updates
            if module == "BTEC_Students":
                if operation in ["insert", "update"]:
                    logger.info(f"🔄 Processing student update for record_id: {record_id}")
                    
                    # Fetch latest student data from Zoho
                    zoho_data = await self.zoho_client.get_record(module, record_id)
                    logger.info(f"📥 Fetched student data from Zoho: {bool(zoho_data)}")
                    
                    if zoho_data:
                        # Parse name (Zoho has "Name" field which is full name)
                        full_name = zoho_data.get('Name', '')
                        name_parts = full_name.split(' ', 1)
                        first_name = name_parts[0] if name_parts else ''
                        last_name = name_parts[1] if len(name_parts) > 1 else ''
                        
                        # Update local database with Zoho data (read-only sync)
                        # Check if student exists in local DB
                        existing_student = self.db.query(Student).filter(
                            Student.zoho_id == record_id
                        ).first()
                        
                        if existing_student:
                            # Update existing student — all fields
                            existing_student.display_name = full_name
                            existing_student.academic_email = zoho_data.get('Academic_Email', '')
                            existing_student.phone = zoho_data.get('Phone_Number')
                            existing_student.userid = zoho_data.get('Student_ID_Number')
                            existing_student.city = zoho_data.get('City')
                            existing_student.address = zoho_data.get('Address')
                            existing_student.status = zoho_data.get('Status')
                            existing_student.major = zoho_data.get('Major')
                            existing_student.sub_major = zoho_data.get('Sub_Major')
                            self.db.commit()
                            action = "updated"
                            logger.info(f"💾 Local DB: Student updated in database")

                            # Push full student data to Moodle WS → local_mzi_students
                            try:
                                import json as _json
                                import httpx as _httpx
                                moodle_data = {
                                    "zoho_student_id":  record_id,
                                    "first_name":       first_name,
                                    "last_name":        last_name,
                                    "display_name":     full_name,
                                    "email":            zoho_data.get('Academic_Email', ''),
                                    "phone_number":     zoho_data.get('Phone_Number', ''),
                                    "address":          zoho_data.get('Address', ''),
                                    "city":             zoho_data.get('City', ''),
                                    "nationality":      zoho_data.get('Nationality', ''),
                                    "date_of_birth":    zoho_data.get('Birth_Date', ''),
                                    "gender":           zoho_data.get('Gender', ''),
                                    "status":           zoho_data.get('Status', ''),
                                    "national_id":      zoho_data.get('National_Number', ''),
                                    "moodle_user_id":   zoho_data.get('Student_Moodle_ID', ''),
                                    "emergency_contact_name":  zoho_data.get('Emergency_Contact_Name', ''),
                                    "emergency_contact_phone": zoho_data.get('Emergency_Phone_Number', ''),
                                    "academic_email":          zoho_data.get('Academic_Email', ''),
                                    "major":                   zoho_data.get('Major', ''),
                                    "sub_major":               zoho_data.get('Sub_Major', ''),
                                }
                                from app.core.config import settings as _settings
                                if _settings.MOODLE_ENABLED and _settings.MOODLE_BASE_URL and _settings.MOODLE_TOKEN:
                                    ws_url = f"{_settings.MOODLE_BASE_URL}/webservice/rest/server.php"
                                    ws_payload = {
                                        "wstoken": _settings.MOODLE_TOKEN,
                                        "wsfunction": "local_mzi_update_student",
                                        "moodlewsrestformat": "json",
                                        "studentdata": _json.dumps(moodle_data),
                                    }
                                    async with _httpx.AsyncClient(timeout=30.0) as _client:
                                        ws_resp = await _client.post(ws_url, data=ws_payload)
                                        ws_resp.raise_for_status()
                                        ws_result = ws_resp.json()
                                        if isinstance(ws_result, dict) and "exception" in ws_result:
                                            logger.error(f"Moodle WS error for student {record_id}: {ws_result}")
                                        else:
                                            logger.info(f"✅ Moodle WS updated student {record_id}: {ws_result}")
                            except Exception as ws_err:
                                logger.error(f"❌ Moodle WS call failed for student {record_id}: {ws_err}")

                        else:
                            # DISABLED: Do NOT create new students from Zoho webhooks
                            # Students should only be created in Moodle first, then synced to Zoho
                            logger.info(f"⚠️ Skipping new student creation from Zoho. Student must be created in Moodle first. (Zoho ID: {record_id})")
                            action = "skipped"
                            
                            return EventProcessingResult(
                                event_id=event.event_id,
                                status=EventStatus.COMPLETED,
                                action_taken=action,
                                record_id=record_id
                            )
                        
                        # Update sync status in Zoho (tracking fields only) - only for existing students
                        if action == "updated":
                            try:
                                update_data = {
                                    "Synced_to_Moodle": True,
                                    "Last_Sync_Date": datetime.now().strftime("%Y-%m-%dT%H:%M:%S")
                                }
                                logger.info(f"Attempting to update Zoho tracking fields: {update_data}")
                                
                                result = await self.zoho_client.update_record(
                                    module="BTEC_Students",
                                    record_id=record_id,
                                    data=update_data
                                )
                                logger.info(f"✅ Successfully updated sync status in Zoho for student {record_id}. Result: {result}")
                            except Exception as e:
                                # Don't fail the whole sync if status update fails
                                logger.error(f"❌ Failed to update sync status in Zoho for student {record_id}: {type(e).__name__}: {str(e)}")
                        
                        return EventProcessingResult(
                            event_id=event.event_id,
                            status=EventStatus.COMPLETED,
                            action_taken=action,
                            record_id=record_id
                        )
                    else:
                        return EventProcessingResult(
                            event_id=event.event_id,
                            status=EventStatus.FAILED,
                            error="Student data not found in Zoho"
                        )
                
                elif operation == "delete":
                    # Handle student deletion (soft delete or log)
                    logger.info(f"Student deleted: {record_id}")
                    return EventProcessingResult(
                        event_id=event.event_id,
                        status=EventStatus.COMPLETED,
                        action_taken="deleted",
                        record_id=record_id
                    )
            
            # BTEC_Enrollments - Enrollment updates
            elif module == "BTEC_Enrollments":
                if operation in ["insert", "update"]:
                    # Fetch enrollment data
                    enrollment_data = await self.zoho_client.get_record(module, record_id)
                    
                    if enrollment_data:
                        # Extract student and class IDs
                        student_id = enrollment_data.get('Enrolled_Students', {})
                        class_id = enrollment_data.get('Classes', {})
                        
                        if isinstance(student_id, dict):
                            student_id = student_id.get('id')
                        if isinstance(class_id, dict):
                            class_id = class_id.get('id')
                        
                        if student_id and class_id:
                            from app.services.enrollment_sync_service import EnrollmentData
                            
                            enrollment = EnrollmentData(
                                zoho_student_id=student_id,
                                zoho_class_id=class_id,
                                moodle_course_id=enrollment_data.get('Moodle_Course_ID'),
                                enrollment_status=enrollment_data.get('Enrollment_Status', 'Active'),
                                enrollment_date=enrollment_data.get('Enrollment_Date')
                            )
                            
                            result = await self.enrollment_service.sync_enrollment_to_zoho(enrollment)
                            
                            return EventProcessingResult(
                                event_id=event.event_id,
                                status=EventStatus.COMPLETED,
                                action_taken=result.get('action'),
                                record_id=result.get('zoho_enrollment_id')
                            )
                        else:
                            return EventProcessingResult(
                                event_id=event.event_id,
                                status=EventStatus.FAILED,
                                error="Missing student_id or class_id"
                            )
                    else:
                        return EventProcessingResult(
                            event_id=event.event_id,
                            status=EventStatus.FAILED,
                            error="Enrollment data not found"
                        )
            
            # BTEC_Grades - Grade updates
            elif module == "BTEC_Grades":
                if operation in ["insert", "update"]:
                    # Fetch grade data
                    grade_data = await self.zoho_client.get_record(module, record_id)
                    
                    if grade_data:
                        # Extract required fields
                        student_id = grade_data.get('Student', {})
                        class_id = grade_data.get('Class', {})
                        unit_id = grade_data.get('BTEC_Unit', {})
                        
                        if isinstance(student_id, dict):
                            student_id = student_id.get('id')
                        if isinstance(class_id, dict):
                            class_id = class_id.get('id')
                        if isinstance(unit_id, dict):
                            unit_id = unit_id.get('id')
                        
                        if student_id and class_id and unit_id:
                            from app.services.grade_sync_service import GradeData
                            
                            grade = GradeData(
                                zoho_student_id=student_id,
                                zoho_class_id=class_id,
                                zoho_unit_id=unit_id,
                                moodle_grade_composite_key=grade_data.get('Moodle_Grade_Composite_Key', ''),
                                final_grade=grade_data.get('Grade'),
                                moodle_course_id=grade_data.get('Moodle_Course_ID'),
                                moodle_user_id=grade_data.get('Moodle_User_ID')
                            )
                            
                            result = await self.grade_service.sync_grade_to_zoho(grade)
                            
                            return EventProcessingResult(
                                event_id=event.event_id,
                                status=EventStatus.COMPLETED,
                                action_taken=result.get('action'),
                                record_id=result.get('zoho_grade_id')
                            )
                        else:
                            return EventProcessingResult(
                                event_id=event.event_id,
                                status=EventStatus.FAILED,
                                error="Missing required IDs (student/class/unit)"
                            )
            
            # BTEC_Payments - Payment updates (read-only from Moodle perspective)
            elif module == "BTEC_Payments":
                # Payments are read-only for Moodle - just log the event
                logger.info(f"Payment {operation}: {record_id}")
                return EventProcessingResult(
                    event_id=event.event_id,
                    status=EventStatus.COMPLETED,
                    action_taken="logged",
                    record_id=record_id
                )
            
            # Unknown module
            else:
                logger.warning(f"Unknown Zoho module: {module}")
                return EventProcessingResult(
                    event_id=event.event_id,
                    status=EventStatus.FAILED,
                    error=f"Unknown module: {module}"
                )
        
        except Exception as e:
            logger.error(f"Error routing Zoho event: {e}", exc_info=True)
            return EventProcessingResult(
                event_id=event.event_id,
                status=EventStatus.FAILED,
                error=str(e)
            )
    
    async def handle_moodle_event(
        self,
        event: MoodleWebhookEvent
    ) -> EventProcessingResult:
        """
        Handle Moodle webhook event.
        
        Args:
            event: MoodleWebhookEvent instance
            
        Returns:
            EventProcessingResult
        """
        start_time = datetime.utcnow()
        
        try:
            logger.info(
                f"Handling Moodle event: {event.event_id}, "
                f"type={event.event_type}, course={event.course_id}"
            )
            
            # Check for duplicate
            if self._is_duplicate_event(event.event_id):
                logger.info(f"Duplicate Moodle event: {event.event_id}")
                return EventProcessingResult(
                    event_id=event.event_id,
                    status=EventStatus.DUPLICATE,
                    action_taken="skipped",
                    processing_time_ms=0
                )
            
            # Log event
            event_log = self._log_event(
                event_id=event.event_id,
                source=EventSource.MOODLE,
                module="moodle_enrollment",
                event_type=event.event_type,
                record_id=f"{event.user_id}_{event.course_id}",
                payload={
                    "event_type": event.event_type,
                    "course_id": event.course_id,
                    "user_id": event.user_id,
                    "course_shortname": event.course_shortname,
                    "user_email": event.user_email,
                    "enrollment_method": event.enrollment_method,
                    "timestamp": event.timestamp.isoformat()
                }
            )
            
            # Update status
            event_log.status = EventStatus.PROCESSING.value
            self.db.commit()
            
            # Process Moodle event
            result = await self._process_moodle_event(event)
            
            # Update event log
            event_log.status = result.status.value
            event_log.result = {
                "action_taken": result.action_taken,
                "record_id": result.record_id
            }
            event_log.processed_at = datetime.utcnow()
            if result.error:
                event_log.error_message = result.error
            
            self.db.commit()
            
            processing_time = (datetime.utcnow() - start_time).total_seconds() * 1000
            result.processing_time_ms = processing_time
            
            logger.info(
                f"Moodle event processed: {event.event_id}, "
                f"status={result.status}, time={processing_time:.2f}ms"
            )
            
            return result
            
        except Exception as e:
            logger.error(f"Error handling Moodle event {event.event_id}: {e}", exc_info=True)
            
            if 'event_log' in locals():
                event_log.status = EventStatus.FAILED.value
                event_log.error_message = str(e)
                event_log.processed_at = datetime.utcnow()
                self.db.commit()
            
            processing_time = (datetime.utcnow() - start_time).total_seconds() * 1000
            
            return EventProcessingResult(
                event_id=event.event_id,
                status=EventStatus.FAILED,
                error=str(e),
                processing_time_ms=processing_time
            )
    
    async def _process_moodle_event(
        self,
        event: MoodleWebhookEvent
    ) -> EventProcessingResult:
        """
        Process Moodle enrollment event.
        
        Args:
            event: MoodleWebhookEvent instance
            
        Returns:
            EventProcessingResult
        """
        try:
            # Handle user enrollment
            if event.event_type == "\\core\\event\\user_enrolment_created":
                # Find student by email
                if event.user_email:
                    students = await self.student_service.search_students_by_email(event.user_email)
                    
                    if students:
                        student_id = students[0].zoho_student_id
                        
                        # Find class by Moodle course ID
                        # (This would require a mapping table or API call)
                        # For now, log the enrollment
                        logger.info(
                            f"Student {student_id} enrolled in Moodle course {event.course_id}"
                        )
                        
                        return EventProcessingResult(
                            event_id=event.event_id,
                            status=EventStatus.COMPLETED,
                            action_taken="logged",
                            record_id=student_id
                        )
                    else:
                        return EventProcessingResult(
                            event_id=event.event_id,
                            status=EventStatus.FAILED,
                            error=f"Student not found: {event.user_email}"
                        )
            
            # Handle user unenrollment
            elif event.event_type == "\\core\\event\\user_enrolment_deleted":
                logger.info(f"User {event.user_id} unenrolled from course {event.course_id}")
                
                return EventProcessingResult(
                    event_id=event.event_id,
                    status=EventStatus.COMPLETED,
                    action_taken="logged",
                    record_id=f"{event.user_id}_{event.course_id}"
                )
            
            # Unknown event type
            else:
                logger.warning(f"Unknown Moodle event type: {event.event_type}")
                return EventProcessingResult(
                    event_id=event.event_id,
                    status=EventStatus.COMPLETED,
                    action_taken="skipped",
                    error=f"Unknown event type: {event.event_type}"
                )
        
        except Exception as e:
            logger.error(f"Error processing Moodle event: {e}", exc_info=True)
            return EventProcessingResult(
                event_id=event.event_id,
                status=EventStatus.FAILED,
                error=str(e)
            )
    
    def _is_duplicate_event(self, event_id: str) -> bool:
        """
        Check if event already processed.
        
        Args:
            event_id: Unique event ID
            
        Returns:
            True if duplicate, False otherwise
        """
        existing = self.db.query(EventLog).filter(
            EventLog.event_id == event_id
        ).first()
        
        return existing is not None
    
    def _log_event(
        self,
        event_id: str,
        source: EventSource,
        module: str,
        event_type: str,
        record_id: str,
        payload: Dict[str, Any]
    ) -> EventLog:
        """
        Log event to database.
        
        Args:
            event_id: Unique event ID
            source: Event source (zoho/moodle)
            module: Module name
            event_type: Event type
            record_id: Record ID
            payload: Event payload
            
        Returns:
            EventLog instance
        """
        event_log = EventLog(
            event_id=event_id,
            source=source.value,
            module=module,
            event_type=event_type,
            record_id=record_id,
            payload=payload,
            status=EventStatus.PENDING.value
        )
        
        self.db.add(event_log)
        self.db.commit()
        self.db.refresh(event_log)
        
        return event_log
