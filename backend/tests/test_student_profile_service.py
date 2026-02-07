"""
Unit tests for StudentProfileService
"""

import pytest
from unittest.mock import AsyncMock, MagicMock

from app.services.student_profile_service import (
    StudentProfileService,
    StudentData
)
from app.infra.zoho.client import ZohoClient


class TestStudentData:
    """Test StudentData class."""
    
    def test_initialization(self):
        """Test basic initialization."""
        student = StudentData(
            moodle_user_id="123",
            email="john.doe@example.com",
            first_name="John",
            last_name="Doe"
        )
        
        assert student.moodle_user_id == "123"
        assert student.email == "john.doe@example.com"
        assert student.first_name == "John"
        assert student.last_name == "Doe"
        assert student.status == "Active"  # Default
    
    def test_full_name(self):
        """Test full name property."""
        student = StudentData(
            moodle_user_id="123",
            email="john.doe@example.com",
            first_name="John",
            last_name="Doe"
        )
        
        assert student.full_name == "John Doe"
    
    def test_to_zoho_dict_minimal(self):
        """Test conversion to Zoho dict with minimal fields."""
        student = StudentData(
            moodle_user_id="123",
            email="john.doe@example.com",
            first_name="John",
            last_name="Doe"
        )
        
        zoho_dict = student.to_zoho_dict()
        
        assert zoho_dict["Name"] == "John Doe"
        assert zoho_dict["First_Name"] == "John"
        assert zoho_dict["Last_Name"] == "Doe"
        assert zoho_dict["Academic_Email"] == "john.doe@example.com"
        assert zoho_dict["Student_Moodle_ID"] == "123"
        assert zoho_dict["Status"] == "Active"
        assert zoho_dict["Synced_to_Moodle"] is True
        
        # Optional fields should not be present
        assert "Phone" not in zoho_dict
        assert "Date_of_Birth" not in zoho_dict
    
    def test_to_zoho_dict_full(self):
        """Test conversion with all optional fields."""
        student = StudentData(
            moodle_user_id="123",
            email="john.doe@example.com",
            first_name="John",
            last_name="Doe",
            student_id="STU001",
            phone="+1234567890",
            date_of_birth="2000-01-15",
            gender="Male",
            address="123 Main St",
            city="London",
            country="UK",
            postal_code="SW1A 1AA",
            emergency_contact_name="Jane Doe",
            emergency_contact_phone="+9876543210",
            status="Active"
        )
        
        zoho_dict = student.to_zoho_dict()
        
        assert zoho_dict["Student_ID"] == "STU001"
        assert zoho_dict["Phone"] == "+1234567890"
        assert zoho_dict["Date_of_Birth"] == "2000-01-15"
        assert zoho_dict["Gender"] == "Male"
        assert zoho_dict["Address"] == "123 Main St"
        assert zoho_dict["City"] == "London"
        assert zoho_dict["Country"] == "UK"
        assert zoho_dict["Postal_Code"] == "SW1A 1AA"
        assert zoho_dict["Emergency_Contact_Name"] == "Jane Doe"
        assert zoho_dict["Emergency_Contact_Phone"] == "+9876543210"


class TestStudentProfileService:
    """Test StudentProfileService."""
    
    @pytest.fixture
    def mock_zoho(self):
        """Create mock Zoho client."""
        zoho = MagicMock(spec=ZohoClient)
        zoho.upsert_record = AsyncMock()
        zoho.search_records = AsyncMock()
        zoho.get_record = AsyncMock()
        zoho.get_records = AsyncMock()
        zoho.update_record = AsyncMock()
        return zoho
    
    @pytest.fixture
    def service(self, mock_zoho):
        """Create service instance."""
        return StudentProfileService(zoho_client=mock_zoho)
    
    async def test_sync_student_create(self, service, mock_zoho):
        """Test syncing new student (create)."""
        # Mock upsert response (insert)
        mock_zoho.upsert_record.return_value = {
            'code': 'SUCCESS',
            'details': {'id': '5843017000000123456'},
            'action': 'insert'
        }
        
        student = StudentData(
            moodle_user_id="123",
            email="john.doe@example.com",
            first_name="John",
            last_name="Doe",
            student_id="STU001"
        )
        
        result = await service.sync_student_to_zoho(student)
        
        assert result['status'] == 'success'
        assert result['action'] == 'created'
        assert result['zoho_student_id'] == '5843017000000123456'
        assert result['moodle_user_id'] == '123'
        assert result['email'] == 'john.doe@example.com'
        
        # Verify upsert was called with correct data
        mock_zoho.upsert_record.assert_called_once()
        
        # Get call arguments - upsert_record(module, data, duplicate_check_fields)
        call_args, call_kwargs = mock_zoho.upsert_record.call_args
        
        # Check module name
        assert call_args[0] == 'BTEC_Students'
        
        # Check data
        data = call_args[1]
        assert data['Name'] == 'John Doe'
        assert data['Academic_Email'] == 'john.doe@example.com'
        assert data['Student_Moodle_ID'] == '123'
        
        # Check duplicate_check_fields (might be positional or keyword arg)
        if len(call_args) > 2:
            assert call_args[2] == ['Academic_Email']
        else:
            assert call_kwargs.get('duplicate_check_fields') == ['Academic_Email']
    
    async def test_sync_student_update(self, service, mock_zoho):
        """Test syncing existing student (update)."""
        # Mock upsert response (update)
        mock_zoho.upsert_record.return_value = {
            'code': 'SUCCESS',
            'details': {'id': '5843017000000123456'},
            'action': 'update'
        }
        
        student = StudentData(
            moodle_user_id="123",
            email="john.doe@example.com",
            first_name="John",
            last_name="Doe"
        )
        
        result = await service.sync_student_to_zoho(student)
        
        assert result['status'] == 'success'
        assert result['action'] == 'updated'
        assert result['zoho_student_id'] == '5843017000000123456'
    
    async def test_sync_student_simple(self, service, mock_zoho):
        """Test simplified sync interface."""
        mock_zoho.upsert_record.return_value = {
            'code': 'SUCCESS',
            'details': {'id': '5843017000000123456'},
            'action': 'insert'
        }
        
        result = await service.sync_student_simple(
            moodle_user_id="123",
            email="john.doe@example.com",
            first_name="John",
            last_name="Doe",
            student_id="STU001",
            phone="+1234567890"
        )
        
        assert result['status'] == 'success'
        assert result['zoho_student_id'] == '5843017000000123456'
        
        # Verify phone was included
        call_kwargs = mock_zoho.upsert_record.call_args
        data = call_kwargs[0][1]
        assert data['Phone'] == '+1234567890'
    
    async def test_get_student_by_email_found(self, service, mock_zoho):
        """Test getting student by email when found."""
        mock_zoho.search_records.return_value = [
            {
                'id': '5843017000000123456',
                'Name': 'John Doe',
                'Academic_Email': 'john.doe@example.com',
                'Student_Moodle_ID': '123'
            }
        ]
        
        student = await service.get_student_by_email('john.doe@example.com')
        
        assert student is not None
        assert student['id'] == '5843017000000123456'
        assert student['Name'] == 'John Doe'
        
        mock_zoho.search_records.assert_called_once_with(
            'BTEC_Students',
            '(Academic_Email:equals:john.doe@example.com)'
        )
    
    async def test_get_student_by_email_not_found(self, service, mock_zoho):
        """Test getting student by email when not found."""
        mock_zoho.search_records.return_value = []
        
        student = await service.get_student_by_email('notfound@example.com')
        
        assert student is None
    
    async def test_get_student_by_moodle_id_found(self, service, mock_zoho):
        """Test getting student by Moodle ID when found."""
        mock_zoho.search_records.return_value = [
            {
                'id': '5843017000000123456',
                'Name': 'John Doe',
                'Student_Moodle_ID': '123'
            }
        ]
        
        student = await service.get_student_by_moodle_id('123')
        
        assert student is not None
        assert student['Student_Moodle_ID'] == '123'
        
        mock_zoho.search_records.assert_called_once_with(
            'BTEC_Students',
            '(Student_Moodle_ID:equals:123)'
        )
    
    async def test_get_student_by_moodle_id_not_found(self, service, mock_zoho):
        """Test getting student by Moodle ID when not found."""
        mock_zoho.search_records.return_value = []
        
        student = await service.get_student_by_moodle_id('999')
        
        assert student is None
    
    async def test_get_student_by_id(self, service, mock_zoho):
        """Test getting student by Zoho ID."""
        mock_zoho.get_record.return_value = {
            'id': '5843017000000123456',
            'Name': 'John Doe',
            'Academic_Email': 'john.doe@example.com'
        }
        
        student = await service.get_student_by_id('5843017000000123456')
        
        assert student['id'] == '5843017000000123456'
        assert student['Name'] == 'John Doe'
        
        mock_zoho.get_record.assert_called_once_with(
            'BTEC_Students',
            '5843017000000123456'
        )
    
    async def test_update_student_moodle_id(self, service, mock_zoho):
        """Test updating Student_Moodle_ID."""
        mock_zoho.update_record.return_value = {
            'code': 'SUCCESS',
            'details': {'id': '5843017000000123456'}
        }
        
        result = await service.update_student_moodle_id(
            zoho_student_id='5843017000000123456',
            moodle_user_id='123'
        )
        
        assert result['code'] == 'SUCCESS'
        
        mock_zoho.update_record.assert_called_once()
        call_kwargs = mock_zoho.update_record.call_args
        assert call_kwargs[0][0] == 'BTEC_Students'
        assert call_kwargs[0][1] == '5843017000000123456'
        
        data = call_kwargs[0][2]
        assert data['Student_Moodle_ID'] == '123'
        assert data['Synced_to_Moodle'] is True
    
    async def test_get_all_students(self, service, mock_zoho):
        """Test getting all students."""
        mock_zoho.get_records.return_value = {
            'data': [
                {'id': '1', 'Name': 'Student 1', 'Status': 'Active'},
                {'id': '2', 'Name': 'Student 2', 'Status': 'Inactive'},
                {'id': '3', 'Name': 'Student 3', 'Status': 'Active'}
            ],
            'info': {'more_records': False}
        }
        
        response = await service.get_all_students(page=1, per_page=100)
        
        assert len(response['data']) == 3
        assert response['info']['more_records'] is False
        
        mock_zoho.get_records.assert_called_once_with(
            'BTEC_Students',
            page=1,
            per_page=100
        )
    
    async def test_get_all_students_with_filter(self, service, mock_zoho):
        """Test getting students with status filter."""
        mock_zoho.get_records.return_value = {
            'data': [
                {'id': '1', 'Name': 'Student 1', 'Status': 'Active'},
                {'id': '2', 'Name': 'Student 2', 'Status': 'Inactive'},
                {'id': '3', 'Name': 'Student 3', 'Status': 'Active'}
            ],
            'info': {'more_records': False}
        }
        
        response = await service.get_all_students(
            filters={'Status': 'Active'}
        )
        
        # Should filter to only Active students
        assert len(response['data']) == 2
        assert all(s['Status'] == 'Active' for s in response['data'])
    
    async def test_get_synced_students(self, service, mock_zoho):
        """Test getting synced students."""
        mock_zoho.search_records.return_value = [
            {'id': '1', 'Name': 'Student 1', 'Synced_to_Moodle': True},
            {'id': '2', 'Name': 'Student 2', 'Synced_to_Moodle': True}
        ]
        
        students = await service.get_synced_students()
        
        assert len(students) == 2
        
        mock_zoho.search_records.assert_called_once_with(
            'BTEC_Students',
            '(Synced_to_Moodle:equals:true)',
            page=1,
            per_page=200
        )
    
    async def test_bulk_sync_students(self, service, mock_zoho):
        """Test bulk syncing students."""
        # Mock successful upserts
        mock_zoho.upsert_record.side_effect = [
            {'code': 'SUCCESS', 'details': {'id': '1'}, 'action': 'insert'},
            {'code': 'SUCCESS', 'details': {'id': '2'}, 'action': 'update'},
            {'code': 'SUCCESS', 'details': {'id': '3'}, 'action': 'insert'}
        ]
        
        students = [
            StudentData("1", "a@x.com", "A", "1"),
            StudentData("2", "b@x.com", "B", "2"),
            StudentData("3", "c@x.com", "C", "3")
        ]
        
        summary = await service.bulk_sync_students(students)
        
        assert summary['total'] == 3
        assert summary['created'] == 2
        assert summary['updated'] == 1
        assert summary['failed'] == 0


if __name__ == '__main__':
    pytest.main([__file__, '-v'])
