"""
Unit tests for EnrollmentSyncService
"""

import pytest
from unittest.mock import AsyncMock, MagicMock

from app.services.enrollment_sync_service import (
    EnrollmentSyncService,
    EnrollmentData
)
from app.infra.zoho.client import ZohoClient


class TestEnrollmentData:
    """Test EnrollmentData class."""
    
    def test_initialization_minimal(self):
        """Test initialization with minimal fields."""
        enrollment = EnrollmentData(
            zoho_student_id="5843017000000123456",
            zoho_class_id="5843017000000789012",
            moodle_course_id="101"
        )
        
        assert enrollment.zoho_student_id == "5843017000000123456"
        assert enrollment.zoho_class_id == "5843017000000789012"
        assert enrollment.moodle_course_id == "101"
        assert enrollment.enrollment_status == "Active"  # Default
        assert enrollment.enrollment_date is not None  # Auto-generated
    
    def test_composite_key(self):
        """Test composite key generation."""
        enrollment = EnrollmentData(
            zoho_student_id="5843017000000123456",
            zoho_class_id="5843017000000789012",
            moodle_course_id="101"
        )
        
        assert enrollment.composite_key == "5843017000000123456_5843017000000789012"
    
    def test_to_zoho_dict_minimal(self):
        """Test conversion to Zoho dict with minimal fields."""
        enrollment = EnrollmentData(
            zoho_student_id="5843017000000123456",
            zoho_class_id="5843017000000789012",
            moodle_course_id="101",
            enrollment_date="2025-09-01"
        )
        
        zoho_dict = enrollment.to_zoho_dict()
        
        assert zoho_dict["Enrolled_Students"] == "5843017000000123456"
        assert zoho_dict["Classes"] == "5843017000000789012"
        assert zoho_dict["Moodle_Course_ID"] == "101"
        assert zoho_dict["Enrollment_Status"] == "Active"
        assert zoho_dict["Enrollment_Date"] == "2025-09-01"
        
        # Optional fields should not be present
        assert "Completion_Date" not in zoho_dict
        assert "Grade" not in zoho_dict
    
    def test_to_zoho_dict_full(self):
        """Test conversion with all fields."""
        enrollment = EnrollmentData(
            zoho_student_id="5843017000000123456",
            zoho_class_id="5843017000000789012",
            moodle_course_id="101",
            enrollment_status="Completed",
            enrollment_date="2025-09-01",
            completion_date="2026-06-30",
            grade="Distinction",
            attendance_percentage=95.5,
            notes="Excellent performance"
        )
        
        zoho_dict = enrollment.to_zoho_dict()
        
        assert zoho_dict["Enrollment_Status"] == "Completed"
        assert zoho_dict["Completion_Date"] == "2026-06-30"
        assert zoho_dict["Grade"] == "Distinction"
        assert zoho_dict["Attendance_Percentage"] == 95.5
        assert zoho_dict["Notes"] == "Excellent performance"


class TestEnrollmentSyncService:
    """Test EnrollmentSyncService."""
    
    @pytest.fixture
    def mock_zoho(self):
        """Create mock Zoho client."""
        zoho = MagicMock(spec=ZohoClient)
        zoho.search_records = AsyncMock()
        zoho.create_record = AsyncMock()
        zoho.update_record = AsyncMock()
        zoho.get_record = AsyncMock()
        return zoho
    
    @pytest.fixture
    def service(self, mock_zoho):
        """Create service instance."""
        return EnrollmentSyncService(zoho_client=mock_zoho)
    
    async def test_sync_enrollment_create(self, service, mock_zoho):
        """Test creating new enrollment."""
        # Mock search - no existing enrollment
        mock_zoho.search_records.return_value = []
        
        # Mock create success
        mock_zoho.create_record.return_value = {
            'code': 'SUCCESS',
            'details': {'id': '5843017000000111111'}
        }
        
        enrollment = EnrollmentData(
            zoho_student_id="5843017000000123456",
            zoho_class_id="5843017000000789012",
            moodle_course_id="101",
            enrollment_status="Active",
            enrollment_date="2025-09-01"
        )
        
        result = await service.sync_enrollment_to_zoho(enrollment)
        
        assert result['status'] == 'success'
        assert result['action'] == 'created'
        assert result['zoho_enrollment_id'] == '5843017000000111111'
        assert result['enrollment_status'] == 'Active'
        
        # Verify search was called
        mock_zoho.search_records.assert_called_once()
        search_criteria = mock_zoho.search_records.call_args[0][1]
        assert "Enrolled_Students:equals:5843017000000123456" in search_criteria
        assert "Classes:equals:5843017000000789012" in search_criteria
        
        # Verify create was called
        mock_zoho.create_record.assert_called_once()
        call_args = mock_zoho.create_record.call_args[0]
        assert call_args[0] == 'BTEC_Enrollments'
        
        data = call_args[1]
        assert data['Enrolled_Students'] == "5843017000000123456"
        assert data['Classes'] == "5843017000000789012"
        assert data['Moodle_Course_ID'] == "101"
    
    async def test_sync_enrollment_update(self, service, mock_zoho):
        """Test updating existing enrollment."""
        # Mock search - existing enrollment found
        mock_zoho.search_records.return_value = [
            {
                'id': '5843017000000111111',
                'Enrollment_Status': 'Active'
            }
        ]
        
        # Mock update success
        mock_zoho.update_record.return_value = {
            'code': 'SUCCESS',
            'details': {'id': '5843017000000111111'}
        }
        
        enrollment = EnrollmentData(
            zoho_student_id="5843017000000123456",
            zoho_class_id="5843017000000789012",
            moodle_course_id="101",
            enrollment_status="Completed",
            completion_date="2026-06-30"
        )
        
        result = await service.sync_enrollment_to_zoho(enrollment)
        
        assert result['status'] == 'success'
        assert result['action'] == 'updated'
        assert result['zoho_enrollment_id'] == '5843017000000111111'
        
        # Verify update was called
        mock_zoho.update_record.assert_called_once()
        call_args = mock_zoho.update_record.call_args[0]
        assert call_args[0] == 'BTEC_Enrollments'
        assert call_args[1] == '5843017000000111111'
        
        data = call_args[2]
        assert data['Enrollment_Status'] == 'Completed'
    
    async def test_get_student_enrollments(self, service, mock_zoho):
        """Test getting enrollments for a student."""
        mock_zoho.search_records.return_value = [
            {'id': '1', 'Classes': 'Class1', 'Enrollment_Status': 'Active'},
            {'id': '2', 'Classes': 'Class2', 'Enrollment_Status': 'Active'}
        ]
        
        enrollments = await service.get_student_enrollments("5843017000000123456")
        
        assert len(enrollments) == 2
        
        mock_zoho.search_records.assert_called_once_with(
            'BTEC_Enrollments',
            '(Enrolled_Students:equals:5843017000000123456)'
        )
    
    async def test_get_student_enrollments_with_filter(self, service, mock_zoho):
        """Test getting enrollments with status filter."""
        mock_zoho.search_records.return_value = [
            {'id': '1', 'Enrollment_Status': 'Active'}
        ]
        
        enrollments = await service.get_student_enrollments(
            "5843017000000123456",
            status_filter="Active"
        )
        
        assert len(enrollments) == 1
        
        # Verify filter was applied
        call_args = mock_zoho.search_records.call_args[0]
        criteria = call_args[1]
        assert "Enrolled_Students:equals:5843017000000123456" in criteria
        assert "Enrollment_Status:equals:Active" in criteria
    
    async def test_get_class_enrollments(self, service, mock_zoho):
        """Test getting enrollments for a class."""
        mock_zoho.search_records.return_value = [
            {'id': '1', 'Enrolled_Students': 'Student1'},
            {'id': '2', 'Enrolled_Students': 'Student2'},
            {'id': '3', 'Enrolled_Students': 'Student3'}
        ]
        
        enrollments = await service.get_class_enrollments("5843017000000789012")
        
        assert len(enrollments) == 3
        
        mock_zoho.search_records.assert_called_once_with(
            'BTEC_Enrollments',
            '(Classes:equals:5843017000000789012)'
        )
    
    async def test_update_enrollment_status(self, service, mock_zoho):
        """Test updating enrollment status."""
        mock_zoho.update_record.return_value = {
            'code': 'SUCCESS',
            'details': {'id': '5843017000000111111'}
        }
        
        result = await service.update_enrollment_status(
            zoho_enrollment_id='5843017000000111111',
            new_status='Completed',
            completion_date='2026-06-30'
        )
        
        assert result['code'] == 'SUCCESS'
        
        mock_zoho.update_record.assert_called_once()
        call_args = mock_zoho.update_record.call_args[0]
        assert call_args[0] == 'BTEC_Enrollments'
        assert call_args[1] == '5843017000000111111'
        
        data = call_args[2]
        assert data['Enrollment_Status'] == 'Completed'
        assert data['Completion_Date'] == '2026-06-30'
    
    async def test_withdraw_enrollment(self, service, mock_zoho):
        """Test withdrawing enrollment."""
        mock_zoho.update_record.return_value = {
            'code': 'SUCCESS',
            'details': {'id': '5843017000000111111'}
        }
        
        result = await service.withdraw_enrollment(
            zoho_enrollment_id='5843017000000111111',
            reason='Student transferred'
        )
        
        assert result['code'] == 'SUCCESS'
        
        call_args = mock_zoho.update_record.call_args[0]
        data = call_args[2]
        assert data['Enrollment_Status'] == 'Withdrawn'
        assert 'Student transferred' in data['Notes']
    
    async def test_complete_enrollment(self, service, mock_zoho):
        """Test completing enrollment."""
        mock_zoho.update_record.return_value = {
            'code': 'SUCCESS',
            'details': {'id': '5843017000000111111'}
        }
        
        result = await service.complete_enrollment(
            zoho_enrollment_id='5843017000000111111',
            completion_date='2026-06-30',
            final_grade='Distinction'
        )
        
        assert result['code'] == 'SUCCESS'
        
        call_args = mock_zoho.update_record.call_args[0]
        data = call_args[2]
        assert data['Enrollment_Status'] == 'Completed'
        assert data['Completion_Date'] == '2026-06-30'
        assert data['Grade'] == 'Distinction'
    
    async def test_get_active_enrollments(self, service, mock_zoho):
        """Test getting active enrollments."""
        mock_zoho.search_records.return_value = [
            {'id': '1', 'Enrollment_Status': 'Active'},
            {'id': '2', 'Enrollment_Status': 'Active'}
        ]
        
        enrollments = await service.get_active_enrollments()
        
        assert len(enrollments) == 2
        
        mock_zoho.search_records.assert_called_once_with(
            'BTEC_Enrollments',
            '(Enrollment_Status:equals:Active)',
            page=1,
            per_page=200
        )
    
    async def test_bulk_sync_enrollments(self, service, mock_zoho):
        """Test bulk syncing enrollments."""
        # Mock: first enrollment is new, second exists
        mock_zoho.search_records.side_effect = [
            [],  # No existing for first
            [{'id': '2', 'Enrollment_Status': 'Active'}],  # Existing for second
            []   # No existing for third
        ]
        
        # Mock create/update
        mock_zoho.create_record.side_effect = [
            {'code': 'SUCCESS', 'details': {'id': '1'}},
            {'code': 'SUCCESS', 'details': {'id': '3'}}
        ]
        
        mock_zoho.update_record.return_value = {
            'code': 'SUCCESS',
            'details': {'id': '2'}
        }
        
        enrollments = [
            EnrollmentData("student1", "class1", "101"),
            EnrollmentData("student2", "class1", "101"),
            EnrollmentData("student3", "class1", "101")
        ]
        
        summary = await service.bulk_sync_enrollments(enrollments)
        
        assert summary['total'] == 3
        assert summary['created'] == 2
        assert summary['updated'] == 1
        assert summary['failed'] == 0


if __name__ == '__main__':
    pytest.main([__file__, '-v'])
