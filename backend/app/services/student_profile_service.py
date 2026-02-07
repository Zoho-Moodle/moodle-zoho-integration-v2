"""
Student Profile Sync Service

Syncs student data between Moodle and Zoho BTEC_Students module.

Flow:
1. Moodle → Zoho: Create/update BTEC_Students, store Student_Moodle_ID
2. Zoho → Moodle: Read-only (display in Moodle dashboard)

Contract Compliance:
- Module: BTEC_Students
- Key fields: Name, First_Name, Last_Name, Academic_Email, Student_ID
- Sync field: Student_Moodle_ID (stores Moodle user ID)
- Status tracking: Synced_to_Moodle (True/False)

Usage:
    from app.services.student_profile_service import StudentProfileService
    
    service = StudentProfileService(zoho_client, moodle_client)
    
    # Sync from Moodle to Zoho
    result = await service.sync_student_to_zoho(
        moodle_user_id=123,
        email="student@example.com",
        first_name="John",
        last_name="Doe"
    )
    
    # Get student from Zoho
    student = await service.get_student_by_moodle_id(123)
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


class StudentData:
    """Represents student data for syncing."""
    
    def __init__(
        self,
        moodle_user_id: str,
        email: str,
        first_name: str,
        last_name: str,
        student_id: Optional[str] = None,
        phone: Optional[str] = None,
        date_of_birth: Optional[str] = None,
        gender: Optional[str] = None,
        address: Optional[str] = None,
        city: Optional[str] = None,
        country: Optional[str] = None,
        postal_code: Optional[str] = None,
        emergency_contact_name: Optional[str] = None,
        emergency_contact_phone: Optional[str] = None,
        status: str = "Active",
        zoho_student_id: Optional[str] = None
    ):
        """
        Initialize student data.
        
        Args:
            moodle_user_id: Moodle user ID (required)
            email: Academic email (required)
            first_name: First name (required)
            last_name: Last name (required)
            student_id: Institution student ID (optional)
            phone: Phone number (optional)
            date_of_birth: Date of birth in YYYY-MM-DD format (optional)
            gender: Gender (Male/Female/Other/Prefer not to say) (optional)
            address: Street address (optional)
            city: City (optional)
            country: Country (optional)
            postal_code: Postal/ZIP code (optional)
            emergency_contact_name: Emergency contact name (optional)
            emergency_contact_phone: Emergency contact phone (optional)
            status: Student status (Active/Inactive/Graduated) (default: Active)
            zoho_student_id: Zoho record ID if already synced (optional)
        """
        self.moodle_user_id = str(moodle_user_id)
        self.email = email
        self.first_name = first_name
        self.last_name = last_name
        self.student_id = student_id
        self.phone = phone
        self.date_of_birth = date_of_birth
        self.gender = gender
        self.address = address
        self.city = city
        self.country = country
        self.postal_code = postal_code
        self.emergency_contact_name = emergency_contact_name
        self.emergency_contact_phone = emergency_contact_phone
        self.status = status
        self.zoho_student_id = zoho_student_id
    
    @property
    def full_name(self) -> str:
        """Get full name."""
        return f"{self.first_name} {self.last_name}"
    
    def to_zoho_dict(self) -> Dict[str, Any]:
        """
        Convert to Zoho BTEC_Students record format.
        
        Returns:
            Dict with Zoho field names and values
        """
        data = {
            "Name": self.full_name,
            "First_Name": self.first_name,
            "Last_Name": self.last_name,
            "Academic_Email": self.email,
            "Student_Moodle_ID": self.moodle_user_id,
            "Status": self.status,
            "Synced_to_Moodle": True
        }
        
        # Optional fields (only include if provided)
        if self.student_id:
            data["Student_ID"] = self.student_id
        
        if self.phone:
            data["Phone"] = self.phone
        
        if self.date_of_birth:
            data["Date_of_Birth"] = self.date_of_birth
        
        if self.gender:
            data["Gender"] = self.gender
        
        if self.address:
            data["Address"] = self.address
        
        if self.city:
            data["City"] = self.city
        
        if self.country:
            data["Country"] = self.country
        
        if self.postal_code:
            data["Postal_Code"] = self.postal_code
        
        if self.emergency_contact_name:
            data["Emergency_Contact_Name"] = self.emergency_contact_name
        
        if self.emergency_contact_phone:
            data["Emergency_Contact_Phone"] = self.emergency_contact_phone
        
        return data


class StudentProfileService:
    """
    Service for syncing student profiles between Moodle and Zoho.
    
    Responsibilities:
    - Sync student data from Moodle to Zoho BTEC_Students
    - Update Student_Moodle_ID for bidirectional linking
    - Mark students as Synced_to_Moodle
    - Handle create vs update logic
    - Search students by email or Moodle ID
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
    
    async def sync_student_to_zoho(
        self,
        student_data: StudentData
    ) -> Dict[str, Any]:
        """
        Sync student from Moodle to Zoho.
        
        Workflow:
        1. Search for existing student by email
        2. If found → update with Moodle ID
        3. If not found → create new student
        4. Return Zoho student ID
        
        Args:
            student_data: Student data to sync
        
        Returns:
            Dict with status, zoho_student_id, action
        
        Example:
            student = StudentData(
                moodle_user_id="123",
                email="john.doe@example.com",
                first_name="John",
                last_name="Doe",
                student_id="STU001"
            )
            
            result = await service.sync_student_to_zoho(student)
            # {'status': 'success', 'zoho_student_id': '5843017000000123456', 'action': 'created'}
        
        Raises:
            ZohoValidationError: If data validation fails
            ZohoAPIError: If API call fails
        """
        logger.info(
            f"Syncing student to Zoho: {student_data.full_name} "
            f"(Moodle ID: {student_data.moodle_user_id}, Email: {student_data.email})"
        )
        
        try:
            # Prepare Zoho data
            zoho_data = student_data.to_zoho_dict()
            
            # Use upsert with Academic_Email as duplicate check field
            # This will create if email doesn't exist, or update if it does
            result = await self.zoho.upsert_record(
                'BTEC_Students',
                zoho_data,
                duplicate_check_fields=['Academic_Email']
            )
            
            if result.get('code') == 'SUCCESS':
                student_id = result['details']['id']
                action = result.get('action', 'unknown')  # 'insert' or 'update'
                
                logger.info(
                    f"Student {action}d in Zoho: {student_data.full_name} "
                    f"(ID: {student_id})"
                )
                
                return {
                    'status': 'success',
                    'zoho_student_id': student_id,
                    'action': 'created' if action == 'insert' else 'updated',
                    'moodle_user_id': student_data.moodle_user_id,
                    'email': student_data.email
                }
            else:
                raise ZohoAPIError(f"Upsert failed: {result}")
        
        except ZohoValidationError as e:
            logger.error(f"Validation error syncing student: {e}")
            logger.error(f"Student data: {zoho_data}")
            raise
        
        except ZohoAPIError as e:
            logger.error(f"API error syncing student: {e}")
            raise
        
        except Exception as e:
            logger.error(f"Unexpected error syncing student: {e}")
            raise ZohoAPIError(f"Unexpected error: {str(e)}")
    
    async def sync_student_simple(
        self,
        moodle_user_id: str,
        email: str,
        first_name: str,
        last_name: str,
        **kwargs
    ) -> Dict[str, Any]:
        """
        Simplified interface for syncing student.
        
        Args:
            moodle_user_id: Moodle user ID
            email: Academic email
            first_name: First name
            last_name: Last name
            **kwargs: Additional optional fields (student_id, phone, etc.)
        
        Returns:
            Sync result dict
        
        Example:
            result = await service.sync_student_simple(
                moodle_user_id="123",
                email="john.doe@example.com",
                first_name="John",
                last_name="Doe",
                student_id="STU001",
                phone="+1234567890"
            )
        """
        student = StudentData(
            moodle_user_id=moodle_user_id,
            email=email,
            first_name=first_name,
            last_name=last_name,
            **kwargs
        )
        
        return await self.sync_student_to_zoho(student)
    
    async def get_student_by_email(self, email: str) -> Optional[Dict]:
        """
        Get student from Zoho by email.
        
        Args:
            email: Academic email
        
        Returns:
            Student record or None if not found
        
        Example:
            student = await service.get_student_by_email("john.doe@example.com")
            if student:
                print(f"Found: {student['Name']}")
        """
        logger.info(f"Searching for student by email: {email}")
        
        try:
            results = await self.zoho.search_records(
                'BTEC_Students',
                f"(Academic_Email:equals:{email})"
            )
            
            if results and len(results) > 0:
                logger.info(f"Found student: {results[0].get('Name')} (ID: {results[0]['id']})")
                return results[0]
            else:
                logger.info(f"No student found with email: {email}")
                return None
        
        except ZohoNotFoundError:
            return None
        
        except Exception as e:
            logger.error(f"Error searching for student: {e}")
            raise
    
    async def get_student_by_moodle_id(self, moodle_user_id: str) -> Optional[Dict]:
        """
        Get student from Zoho by Moodle user ID.
        
        Args:
            moodle_user_id: Moodle user ID
        
        Returns:
            Student record or None if not found
        
        Example:
            student = await service.get_student_by_moodle_id("123")
            if student:
                print(f"Zoho ID: {student['id']}")
        """
        logger.info(f"Searching for student by Moodle ID: {moodle_user_id}")
        
        try:
            results = await self.zoho.search_records(
                'BTEC_Students',
                f"(Student_Moodle_ID:equals:{moodle_user_id})"
            )
            
            if results and len(results) > 0:
                logger.info(f"Found student: {results[0].get('Name')} (ID: {results[0]['id']})")
                return results[0]
            else:
                logger.info(f"No student found with Moodle ID: {moodle_user_id}")
                return None
        
        except ZohoNotFoundError:
            return None
        
        except Exception as e:
            logger.error(f"Error searching for student: {e}")
            raise
    
    async def get_student_by_id(self, zoho_student_id: str) -> Dict:
        """
        Get student from Zoho by Zoho record ID.
        
        Args:
            zoho_student_id: Zoho record ID
        
        Returns:
            Student record
        
        Raises:
            ZohoNotFoundError: If student not found
        
        Example:
            student = await service.get_student_by_id("5843017000000123456")
            print(f"Name: {student['Name']}")
        """
        logger.info(f"Fetching student by Zoho ID: {zoho_student_id}")
        
        return await self.zoho.get_record('BTEC_Students', zoho_student_id)
    
    async def update_student_moodle_id(
        self,
        zoho_student_id: str,
        moodle_user_id: str
    ) -> Dict:
        """
        Update Student_Moodle_ID field in Zoho.
        
        Used when linking existing Zoho student to Moodle user.
        
        Args:
            zoho_student_id: Zoho record ID
            moodle_user_id: Moodle user ID
        
        Returns:
            Update result
        
        Example:
            result = await service.update_student_moodle_id(
                zoho_student_id="5843017000000123456",
                moodle_user_id="123"
            )
        """
        logger.info(
            f"Updating Moodle ID for student {zoho_student_id} "
            f"to {moodle_user_id}"
        )
        
        data = {
            "Student_Moodle_ID": str(moodle_user_id),
            "Synced_to_Moodle": True
        }
        
        result = await self.zoho.update_record(
            'BTEC_Students',
            zoho_student_id,
            data
        )
        
        logger.info(f"Moodle ID updated successfully")
        
        return result
    
    async def get_all_students(
        self,
        page: int = 1,
        per_page: int = 200,
        filters: Optional[Dict[str, str]] = None
    ) -> Dict:
        """
        Get all students from Zoho with pagination.
        
        Args:
            page: Page number (1-indexed)
            per_page: Records per page (max 200)
            filters: Optional filters (e.g., {'Status': 'Active'})
        
        Returns:
            Dict with 'data' (list of students) and 'info' (pagination)
        
        Example:
            response = await service.get_all_students(page=1, per_page=100)
            students = response['data']
            has_more = response['info']['more_records']
        """
        logger.info(f"Fetching students (page {page}, {per_page} per page)")
        
        response = await self.zoho.get_records(
            'BTEC_Students',
            page=page,
            per_page=per_page
        )
        
        students = response.get('data', [])
        
        # Apply filters if provided
        if filters and students:
            filtered = []
            for student in students:
                match = True
                for field, value in filters.items():
                    if student.get(field) != value:
                        match = False
                        break
                if match:
                    filtered.append(student)
            
            response['data'] = filtered
        
        logger.info(f"Retrieved {len(response.get('data', []))} students")
        
        return response
    
    async def get_synced_students(
        self,
        page: int = 1,
        per_page: int = 200
    ) -> List[Dict]:
        """
        Get students that have been synced to Moodle.
        
        Args:
            page: Page number
            per_page: Records per page
        
        Returns:
            List of synced students
        
        Example:
            synced = await service.get_synced_students()
            for student in synced:
                print(f"{student['Name']} - Moodle ID: {student['Student_Moodle_ID']}")
        """
        logger.info("Fetching synced students")
        
        try:
            results = await self.zoho.search_records(
                'BTEC_Students',
                "(Synced_to_Moodle:equals:true)",
                page=page,
                per_page=per_page
            )
            
            logger.info(f"Found {len(results)} synced students")
            
            return results
        
        except Exception as e:
            logger.error(f"Error fetching synced students: {e}")
            raise
    
    async def bulk_sync_students(
        self,
        students: List[StudentData]
    ) -> Dict[str, Any]:
        """
        Sync multiple students in bulk.
        
        Args:
            students: List of StudentData objects
        
        Returns:
            Dict with summary (total, created, updated, failed)
        
        Example:
            students = [
                StudentData(moodle_user_id="1", email="a@x.com", ...),
                StudentData(moodle_user_id="2", email="b@x.com", ...),
            ]
            
            summary = await service.bulk_sync_students(students)
            print(f"Created: {summary['created']}, Updated: {summary['updated']}")
        """
        logger.info(f"Bulk syncing {len(students)} students")
        
        results = {
            'total': len(students),
            'created': 0,
            'updated': 0,
            'failed': 0,
            'errors': []
        }
        
        for student in students:
            try:
                result = await self.sync_student_to_zoho(student)
                
                if result['action'] == 'created':
                    results['created'] += 1
                elif result['action'] == 'updated':
                    results['updated'] += 1
            
            except Exception as e:
                results['failed'] += 1
                results['errors'].append({
                    'student': student.full_name,
                    'email': student.email,
                    'error': str(e)
                })
                logger.error(f"Failed to sync {student.full_name}: {e}")
        
        logger.info(
            f"Bulk sync complete: {results['created']} created, "
            f"{results['updated']} updated, {results['failed']} failed"
        )
        
        return results
