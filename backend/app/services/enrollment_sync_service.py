"""
Enrollment Sync Service

Syncs course enrollments between Moodle and Zoho BTEC_Enrollments module.

Flow:
1. Moodle → Zoho: When student enrolls in course, create BTEC_Enrollments
2. Zoho → Moodle: When enrollment status changes, update Moodle enrollment

Contract Compliance:
- Module: BTEC_Enrollments
- Key fields: Enrolled_Students (lookup), Classes (lookup), Enrollment_Status
- Sync field: Moodle_Course_ID (links to Moodle course)
- Composite key: Student + Class (prevents duplicate enrollments)

Usage:
    from app.services.enrollment_sync_service import EnrollmentSyncService
    
    service = EnrollmentSyncService(zoho_client, moodle_client)
    
    # Sync enrollment from Moodle to Zoho
    result = await service.sync_enrollment_to_zoho(
        zoho_student_id="5843017000000123456",
        zoho_class_id="5843017000000789012",
        moodle_course_id="101",
        enrollment_status="Active"
    )
"""

import logging
from typing import Dict, List, Optional, Any
from datetime import datetime

from app.infra.zoho.client import ZohoClient
from app.infra.zoho.exceptions import (
    ZohoAPIError,
    ZohoNotFoundError,
    ZohoValidationError
)

logger = logging.getLogger(__name__)


class EnrollmentData:
    """Represents enrollment data for syncing."""
    
    def __init__(
        self,
        zoho_student_id: str,
        zoho_class_id: str,
        moodle_course_id: str,
        enrollment_status: str = "Active",
        enrollment_date: Optional[str] = None,
        completion_date: Optional[str] = None,
        grade: Optional[str] = None,
        attendance_percentage: Optional[float] = None,
        notes: Optional[str] = None,
        zoho_enrollment_id: Optional[str] = None
    ):
        """
        Initialize enrollment data.
        
        Args:
            zoho_student_id: Zoho BTEC_Students record ID (required)
            zoho_class_id: Zoho BTEC_Classes record ID (required)
            moodle_course_id: Moodle course ID (required)
            enrollment_status: Active/Completed/Withdrawn/Suspended (default: Active)
            enrollment_date: Enrollment date in YYYY-MM-DD format (optional, defaults to today)
            completion_date: Completion date (optional, for Completed status)
            grade: Final grade (optional)
            attendance_percentage: Attendance % (optional)
            notes: Additional notes (optional)
            zoho_enrollment_id: Zoho record ID if already synced (optional)
        """
        self.zoho_student_id = zoho_student_id
        self.zoho_class_id = zoho_class_id
        self.moodle_course_id = str(moodle_course_id)
        self.enrollment_status = enrollment_status
        self.enrollment_date = enrollment_date or datetime.now().strftime("%Y-%m-%d")
        self.completion_date = completion_date
        self.grade = grade
        self.attendance_percentage = attendance_percentage
        self.notes = notes
        self.zoho_enrollment_id = zoho_enrollment_id
    
    @property
    def composite_key(self) -> str:
        """
        Generate composite key for deduplication.
        
        Format: {student_id}_{class_id}
        """
        return f"{self.zoho_student_id}_{self.zoho_class_id}"
    
    def to_zoho_dict(self) -> Dict[str, Any]:
        """
        Convert to Zoho BTEC_Enrollments record format.
        
        Returns:
            Dict with Zoho field names and values
        """
        data = {
            # Required lookups
            "Enrolled_Students": self.zoho_student_id,
            "Classes": self.zoho_class_id,
            
            # Status and dates
            "Enrollment_Status": self.enrollment_status,
            "Enrollment_Date": self.enrollment_date,
            
            # Moodle linking
            "Moodle_Course_ID": self.moodle_course_id,
        }
        
        # Optional fields
        if self.completion_date:
            data["Completion_Date"] = self.completion_date
        
        if self.grade:
            data["Grade"] = self.grade
        
        if self.attendance_percentage is not None:
            data["Attendance_Percentage"] = self.attendance_percentage
        
        if self.notes:
            data["Notes"] = self.notes
        
        return data


class EnrollmentSyncService:
    """
    Service for syncing course enrollments between Moodle and Zoho.
    
    Responsibilities:
    - Sync enrollments from Moodle to Zoho BTEC_Enrollments
    - Link students (Enrolled_Students) with classes (Classes)
    - Update Moodle_Course_ID for bidirectional linking
    - Handle enrollment status changes
    - Prevent duplicate enrollments (composite key: student + class)
    """
    
    def __init__(
        self,
        zoho_client: ZohoClient,
        moodle_client: Optional[Any] = None,
        db: Optional[Any] = None
    ):
        """
        Initialize service.
        
        Args:
            zoho_client: Zoho CRM API client
            moodle_client: Moodle API client (optional)
            db: Database session (optional)
        """
        self.zoho = zoho_client
        self.moodle = moodle_client
        self.db = db
    
    async def sync_enrollment_to_zoho(
        self,
        enrollment_data: EnrollmentData
    ) -> Dict[str, Any]:
        """
        Sync enrollment from Moodle to Zoho.
        
        Workflow:
        1. Check if enrollment exists (student + class combination)
        2. If exists → update status/dates
        3. If not exists → create new enrollment
        4. Return Zoho enrollment ID
        
        Args:
            enrollment_data: Enrollment data to sync
        
        Returns:
            Dict with status, zoho_enrollment_id, action
        
        Example:
            enrollment = EnrollmentData(
                zoho_student_id="5843017000000123456",
                zoho_class_id="5843017000000789012",
                moodle_course_id="101",
                enrollment_status="Active"
            )
            
            result = await service.sync_enrollment_to_zoho(enrollment)
            # {'status': 'success', 'zoho_enrollment_id': '...', 'action': 'created'}
        
        Raises:
            ZohoValidationError: If data validation fails
            ZohoAPIError: If API call fails
        """
        logger.info(
            f"Syncing enrollment to Zoho: Student {enrollment_data.zoho_student_id} "
            f"→ Class {enrollment_data.zoho_class_id} "
            f"(Moodle Course: {enrollment_data.moodle_course_id})"
        )
        
        try:
            # Check if enrollment already exists
            # Search by student AND class
            existing = await self._find_existing_enrollment(
                enrollment_data.zoho_student_id,
                enrollment_data.zoho_class_id
            )
            
            zoho_data = enrollment_data.to_zoho_dict()
            
            if existing:
                # Update existing enrollment
                enrollment_id = existing['id']
                
                logger.info(
                    f"Updating existing enrollment {enrollment_id} "
                    f"(Status: {enrollment_data.enrollment_status})"
                )
                
                result = await self.zoho.update_record(
                    'BTEC_Enrollments',
                    enrollment_id,
                    zoho_data
                )
                
                return {
                    'status': 'success',
                    'zoho_enrollment_id': enrollment_id,
                    'action': 'updated',
                    'enrollment_status': enrollment_data.enrollment_status
                }
            
            else:
                # Create new enrollment
                logger.info(
                    f"Creating new enrollment: Student {enrollment_data.zoho_student_id} "
                    f"→ Class {enrollment_data.zoho_class_id}"
                )
                
                result = await self.zoho.create_record('BTEC_Enrollments', zoho_data)
                
                if result.get('code') == 'SUCCESS':
                    enrollment_id = result['details']['id']
                    
                    logger.info(f"Enrollment created: {enrollment_id}")
                    
                    return {
                        'status': 'success',
                        'zoho_enrollment_id': enrollment_id,
                        'action': 'created',
                        'enrollment_status': enrollment_data.enrollment_status
                    }
                else:
                    raise ZohoAPIError(f"Failed to create enrollment: {result}")
        
        except ZohoValidationError as e:
            logger.error(f"Validation error syncing enrollment: {e}")
            raise
        
        except ZohoAPIError as e:
            logger.error(f"API error syncing enrollment: {e}")
            raise
        
        except Exception as e:
            logger.error(f"Unexpected error syncing enrollment: {e}")
            raise ZohoAPIError(f"Unexpected error: {str(e)}")
    
    async def _find_existing_enrollment(
        self,
        student_id: str,
        class_id: str
    ) -> Optional[Dict]:
        """
        Find existing enrollment by student + class.
        
        Args:
            student_id: Zoho student ID
            class_id: Zoho class ID
        
        Returns:
            Enrollment record or None
        """
        try:
            # Search by both student and class
            # Note: Zoho search syntax: (field1:equals:value1)AND(field2:equals:value2)
            criteria = f"((Enrolled_Students:equals:{student_id})AND(Classes:equals:{class_id}))"
            
            results = await self.zoho.search_records('BTEC_Enrollments', criteria)
            
            if results and len(results) > 0:
                logger.info(
                    f"Found existing enrollment: {results[0]['id']} "
                    f"(Student: {student_id}, Class: {class_id})"
                )
                return results[0]
            
            return None
        
        except ZohoNotFoundError:
            return None
        
        except Exception as e:
            logger.warning(f"Error searching for enrollment: {e}")
            return None
    
    async def sync_enrollment_simple(
        self,
        zoho_student_id: str,
        zoho_class_id: str,
        moodle_course_id: str,
        enrollment_status: str = "Active",
        **kwargs
    ) -> Dict[str, Any]:
        """
        Simplified interface for syncing enrollment.
        
        Args:
            zoho_student_id: Zoho student ID
            zoho_class_id: Zoho class ID
            moodle_course_id: Moodle course ID
            enrollment_status: Enrollment status (default: Active)
            **kwargs: Additional optional fields
        
        Returns:
            Sync result dict
        
        Example:
            result = await service.sync_enrollment_simple(
                zoho_student_id="5843017000000123456",
                zoho_class_id="5843017000000789012",
                moodle_course_id="101",
                enrollment_date="2025-09-01"
            )
        """
        enrollment = EnrollmentData(
            zoho_student_id=zoho_student_id,
            zoho_class_id=zoho_class_id,
            moodle_course_id=moodle_course_id,
            enrollment_status=enrollment_status,
            **kwargs
        )
        
        return await self.sync_enrollment_to_zoho(enrollment)
    
    async def get_student_enrollments(
        self,
        zoho_student_id: str,
        status_filter: Optional[str] = None
    ) -> List[Dict]:
        """
        Get all enrollments for a student.
        
        Args:
            zoho_student_id: Zoho student ID
            status_filter: Filter by status (Active/Completed/Withdrawn) (optional)
        
        Returns:
            List of enrollment records
        
        Example:
            # All enrollments
            enrollments = await service.get_student_enrollments("5843017000000123456")
            
            # Active only
            active = await service.get_student_enrollments(
                "5843017000000123456",
                status_filter="Active"
            )
        """
        logger.info(f"Fetching enrollments for student {zoho_student_id}")
        
        try:
            criteria = f"(Enrolled_Students:equals:{zoho_student_id})"
            
            if status_filter:
                criteria = f"({criteria}AND(Enrollment_Status:equals:{status_filter}))"
            
            results = await self.zoho.search_records('BTEC_Enrollments', criteria)
            
            logger.info(f"Found {len(results)} enrollments for student")
            
            return results
        
        except ZohoNotFoundError:
            return []
        
        except Exception as e:
            logger.error(f"Error fetching student enrollments: {e}")
            raise
    
    async def get_class_enrollments(
        self,
        zoho_class_id: str,
        status_filter: Optional[str] = None
    ) -> List[Dict]:
        """
        Get all enrollments for a class.
        
        Args:
            zoho_class_id: Zoho class ID
            status_filter: Filter by status (optional)
        
        Returns:
            List of enrollment records
        
        Example:
            # All students in class
            enrollments = await service.get_class_enrollments("5843017000000789012")
            
            # Active students only
            active = await service.get_class_enrollments(
                "5843017000000789012",
                status_filter="Active"
            )
        """
        logger.info(f"Fetching enrollments for class {zoho_class_id}")
        
        try:
            criteria = f"(Classes:equals:{zoho_class_id})"
            
            if status_filter:
                criteria = f"({criteria}AND(Enrollment_Status:equals:{status_filter}))"
            
            results = await self.zoho.search_records('BTEC_Enrollments', criteria)
            
            logger.info(f"Found {len(results)} enrollments for class")
            
            return results
        
        except ZohoNotFoundError:
            return []
        
        except Exception as e:
            logger.error(f"Error fetching class enrollments: {e}")
            raise
    
    async def update_enrollment_status(
        self,
        zoho_enrollment_id: str,
        new_status: str,
        completion_date: Optional[str] = None
    ) -> Dict:
        """
        Update enrollment status.
        
        Args:
            zoho_enrollment_id: Zoho enrollment record ID
            new_status: New status (Active/Completed/Withdrawn/Suspended)
            completion_date: Completion date (required if status is Completed)
        
        Returns:
            Update result
        
        Example:
            # Mark as completed
            result = await service.update_enrollment_status(
                zoho_enrollment_id="5843017000000111111",
                new_status="Completed",
                completion_date="2026-06-30"
            )
            
            # Withdraw student
            result = await service.update_enrollment_status(
                zoho_enrollment_id="5843017000000111111",
                new_status="Withdrawn"
            )
        """
        logger.info(
            f"Updating enrollment {zoho_enrollment_id} status to {new_status}"
        )
        
        data = {
            "Enrollment_Status": new_status
        }
        
        if completion_date:
            data["Completion_Date"] = completion_date
        
        result = await self.zoho.update_record(
            'BTEC_Enrollments',
            zoho_enrollment_id,
            data
        )
        
        logger.info(f"Enrollment status updated successfully")
        
        return result
    
    async def get_enrollment_by_id(self, zoho_enrollment_id: str) -> Dict:
        """
        Get enrollment from Zoho by record ID.
        
        Args:
            zoho_enrollment_id: Zoho record ID
        
        Returns:
            Enrollment record
        
        Raises:
            ZohoNotFoundError: If enrollment not found
        """
        logger.info(f"Fetching enrollment by ID: {zoho_enrollment_id}")
        
        return await self.zoho.get_record('BTEC_Enrollments', zoho_enrollment_id)
    
    async def get_enrollment_by_moodle_course(
        self,
        moodle_course_id: str
    ) -> List[Dict]:
        """
        Get all enrollments for a Moodle course.
        
        Args:
            moodle_course_id: Moodle course ID
        
        Returns:
            List of enrollment records
        
        Example:
            enrollments = await service.get_enrollment_by_moodle_course("101")
            for e in enrollments:
                print(f"Student: {e['Enrolled_Students']}")
        """
        logger.info(f"Fetching enrollments for Moodle course {moodle_course_id}")
        
        try:
            criteria = f"(Moodle_Course_ID:equals:{moodle_course_id})"
            results = await self.zoho.search_records('BTEC_Enrollments', criteria)
            
            logger.info(f"Found {len(results)} enrollments for Moodle course")
            
            return results
        
        except ZohoNotFoundError:
            return []
        
        except Exception as e:
            logger.error(f"Error fetching enrollments by Moodle course: {e}")
            raise
    
    async def get_active_enrollments(
        self,
        page: int = 1,
        per_page: int = 200
    ) -> List[Dict]:
        """
        Get all active enrollments.
        
        Note: Filters client-side since Enrollment_Status may not be searchable.
        
        Args:
            page: Page number (1-indexed)
            per_page: Records per page (max 200)
        
        Returns:
            List of active enrollment records
        
        Example:
            active = await service.get_active_enrollments()
            print(f"Active enrollments: {len(active)}")
        """
        logger.info("Fetching active enrollments")
        
        try:
            # Get all enrollments and filter client-side
            # (Enrollment_Status may not be searchable in Zoho)
            response = await self.zoho.get_records(
                'BTEC_Enrollments',
                page=page,
                per_page=per_page
            )
            
            all_enrollments = response.get('data', [])
            
            # Filter for Active status
            active = [e for e in all_enrollments if e.get('Enrollment_Status') == 'Active']
            
            logger.info(f"Found {len(active)} active enrollments (out of {len(all_enrollments)} total)")
            
            return active
        
        except Exception as e:
            logger.error(f"Error fetching active enrollments: {e}")
            raise
    
    async def bulk_sync_enrollments(
        self,
        enrollments: List[EnrollmentData]
    ) -> Dict[str, Any]:
        """
        Sync multiple enrollments in bulk.
        
        Args:
            enrollments: List of EnrollmentData objects
        
        Returns:
            Dict with summary (total, created, updated, failed)
        
        Example:
            enrollments = [
                EnrollmentData(student_id="1", class_id="101", ...),
                EnrollmentData(student_id="2", class_id="101", ...),
            ]
            
            summary = await service.bulk_sync_enrollments(enrollments)
            print(f"Created: {summary['created']}, Updated: {summary['updated']}")
        """
        logger.info(f"Bulk syncing {len(enrollments)} enrollments")
        
        results = {
            'total': len(enrollments),
            'created': 0,
            'updated': 0,
            'failed': 0,
            'errors': []
        }
        
        for enrollment in enrollments:
            try:
                result = await self.sync_enrollment_to_zoho(enrollment)
                
                if result['action'] == 'created':
                    results['created'] += 1
                elif result['action'] == 'updated':
                    results['updated'] += 1
            
            except Exception as e:
                results['failed'] += 1
                results['errors'].append({
                    'student_id': enrollment.zoho_student_id,
                    'class_id': enrollment.zoho_class_id,
                    'error': str(e)
                })
                logger.error(
                    f"Failed to sync enrollment (Student: {enrollment.zoho_student_id}, "
                    f"Class: {enrollment.zoho_class_id}): {e}"
                )
        
        logger.info(
            f"Bulk sync complete: {results['created']} created, "
            f"{results['updated']} updated, {results['failed']} failed"
        )
        
        return results
    
    async def withdraw_enrollment(
        self,
        zoho_enrollment_id: str,
        withdrawal_date: Optional[str] = None,
        reason: Optional[str] = None
    ) -> Dict:
        """
        Withdraw student from class.
        
        Args:
            zoho_enrollment_id: Zoho enrollment record ID
            withdrawal_date: Withdrawal date (defaults to today)
            reason: Withdrawal reason (optional)
        
        Returns:
            Update result
        
        Example:
            result = await service.withdraw_enrollment(
                zoho_enrollment_id="5843017000000111111",
                reason="Student transferred to another institution"
            )
        """
        logger.info(f"Withdrawing enrollment {zoho_enrollment_id}")
        
        data = {
            "Enrollment_Status": "Withdrawn",
            "Completion_Date": withdrawal_date or datetime.now().strftime("%Y-%m-%d")
        }
        
        if reason:
            data["Notes"] = f"Withdrawal reason: {reason}"
        
        result = await self.zoho.update_record(
            'BTEC_Enrollments',
            zoho_enrollment_id,
            data
        )
        
        logger.info("Enrollment withdrawn successfully")
        
        return result
    
    async def complete_enrollment(
        self,
        zoho_enrollment_id: str,
        completion_date: str,
        final_grade: Optional[str] = None
    ) -> Dict:
        """
        Mark enrollment as completed.
        
        Args:
            zoho_enrollment_id: Zoho enrollment record ID
            completion_date: Completion date (YYYY-MM-DD)
            final_grade: Final grade (optional)
        
        Returns:
            Update result
        
        Example:
            result = await service.complete_enrollment(
                zoho_enrollment_id="5843017000000111111",
                completion_date="2026-06-30",
                final_grade="Distinction"
            )
        """
        logger.info(f"Completing enrollment {zoho_enrollment_id}")
        
        data = {
            "Enrollment_Status": "Completed",
            "Completion_Date": completion_date
        }
        
        if final_grade:
            data["Grade"] = final_grade
        
        result = await self.zoho.update_record(
            'BTEC_Enrollments',
            zoho_enrollment_id,
            data
        )
        
        logger.info("Enrollment completed successfully")
        
        return result
