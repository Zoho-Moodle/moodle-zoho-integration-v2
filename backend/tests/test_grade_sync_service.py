"""
Unit tests for GradeSyncService
"""

import pytest
from unittest.mock import AsyncMock, MagicMock
from datetime import datetime

from app.services.grade_sync_service import (
    GradeSyncService,
    GradingTemplate,
    MoodleGradeData
)
from app.infra.zoho.client import ZohoClient
from app.infra.zoho.exceptions import ZohoNotFoundError


class TestGradingTemplate:
    """Test GradingTemplate class."""
    
    def test_extract_pass_criteria(self):
        """Test extraction of Pass criteria."""
        unit_data = {
            'id': '123',
            'Name': 'Unit 1 - Programming',
            'Unit_Code': 'U1',
            'P1_description': 'Explain components',
            'P2_description': 'Describe process',
            'P3_description': 'Demonstrate knowledge'
        }
        
        template = GradingTemplate('123', unit_data)
        
        assert len(template.pass_criteria) == 3
        assert template.pass_criteria[0]['code'] == 'P1'
        assert template.pass_criteria[0]['description'] == 'Explain components'
    
    def test_extract_merit_distinction(self):
        """Test extraction of Merit and Distinction criteria."""
        unit_data = {
            'id': '123',
            'Name': 'Unit 1',
            'M1_description': 'Analyze impact',
            'M2_description': 'Compare approaches',
            'D1_description': 'Evaluate effectiveness',
        }
        
        template = GradingTemplate('123', unit_data)
        
        assert len(template.merit_criteria) == 2
        assert len(template.distinction_criteria) == 1
        assert template.merit_criteria[0]['code'] == 'M1'
        assert template.distinction_criteria[0]['code'] == 'D1'
    
    def test_get_criterion_by_code(self):
        """Test finding criterion by code."""
        unit_data = {
            'id': '123',
            'Name': 'Unit 1',
            'P1_description': 'Test P1',
            'M1_description': 'Test M1'
        }
        
        template = GradingTemplate('123', unit_data)
        
        p1 = template.get_criterion_by_code('P1')
        assert p1 is not None
        assert p1['description'] == 'Test P1'
        
        m1 = template.get_criterion_by_code('M1')
        assert m1 is not None
        
        # Non-existent
        p99 = template.get_criterion_by_code('P99')
        assert p99 is None
    
    def test_get_all_criteria(self):
        """Test getting all criteria as flat list."""
        unit_data = {
            'id': '123',
            'Name': 'Unit 1',
            'P1_description': 'P1',
            'P2_description': 'P2',
            'M1_description': 'M1',
            'D1_description': 'D1'
        }
        
        template = GradingTemplate('123', unit_data)
        all_criteria = template.get_all_criteria()
        
        assert len(all_criteria) == 4
        codes = [c['code'] for c in all_criteria]
        assert 'P1' in codes
        assert 'M1' in codes
        assert 'D1' in codes


class TestMoodleGradeData:
    """Test MoodleGradeData class."""
    
    def test_initialization(self):
        """Test basic initialization."""
        grade = MoodleGradeData(
            moodle_grade_id="12345",
            student_id="101",
            course_id="202",
            zoho_student_id="5843017000000111111",
            zoho_class_id="5843017000000222222",
            zoho_unit_id="5843017000000333333",
            overall_grade="Pass",
            graded_date="2026-01-25",
            feedback="Good work"
        )
        
        assert grade.moodle_grade_id == "12345"
        assert grade.overall_grade == "Pass"
        assert len(grade.criteria_scores) == 0
    
    def test_add_criterion_score(self):
        """Test adding criterion scores."""
        grade = MoodleGradeData(
            moodle_grade_id="12345",
            student_id="101",
            course_id="202",
            zoho_student_id="111",
            zoho_class_id="222",
            zoho_unit_id="333",
            overall_grade="Pass",
            graded_date="2026-01-25"
        )
        
        grade.add_criterion_score("P1", "Achieved", "Good")
        grade.add_criterion_score("P2", "Not Achieved", "Needs work")
        
        assert len(grade.criteria_scores) == 2
        assert grade.criteria_scores[0]['code'] == "P1"
        assert grade.criteria_scores[0]['score'] == "Achieved"
        assert grade.criteria_scores[1]['score'] == "Not Achieved"
    
    def test_composite_key(self):
        """Test composite key generation."""
        grade = MoodleGradeData(
            moodle_grade_id="12345",
            student_id="101",
            course_id="202",
            zoho_student_id="111",
            zoho_class_id="222",
            zoho_unit_id="333",
            overall_grade="Pass",
            graded_date="2026-01-25"
        )
        
        assert grade.composite_key == "101_202"


class TestGradeSyncService:
    """Test GradeSyncService."""
    
    @pytest.fixture
    def mock_zoho(self):
        """Create mock Zoho client."""
        zoho = MagicMock(spec=ZohoClient)
        zoho.get_record = AsyncMock()
        zoho.search_records = AsyncMock()
        zoho.create_record = AsyncMock()
        zoho.update_record = AsyncMock()
        return zoho
    
    @pytest.fixture
    def service(self, mock_zoho):
        """Create service instance."""
        return GradeSyncService(zoho_client=mock_zoho)
    
    @pytest.mark.asyncio
    async def test_get_grading_template(self, service, mock_zoho):
        """Test fetching grading template from BTEC module."""
        unit_data = {
            'id': '5843017000000333333',
            'Name': 'Unit 1 - Programming',
            'Unit_Code': 'U1',
            'P1_description': 'Explain components of a computer system',
            'P2_description': 'Describe the software development process',
            'M1_description': 'Analyze the impact of technology',
            'D1_description': 'Evaluate the effectiveness of solutions'
        }
        
        mock_zoho.get_record.return_value = unit_data
        
        template = await service.get_grading_template('5843017000000333333')
        
        # Verify called with correct module name
        mock_zoho.get_record.assert_called_once_with('BTEC', '5843017000000333333')
        
        assert template.unit_name == 'Unit 1 - Programming'
        assert len(template.pass_criteria) == 2
        assert len(template.merit_criteria) == 1
        assert len(template.distinction_criteria) == 1
    
    @pytest.mark.asyncio
    async def test_template_caching(self, service, mock_zoho):
        """Test that templates are cached."""
        unit_data = {
            'id': '123',
            'Name': 'Unit 1',
            'P1_description': 'Test'
        }
        
        mock_zoho.get_record.return_value = unit_data
        
        # First call - should fetch
        template1 = await service.get_grading_template('123')
        assert mock_zoho.get_record.call_count == 1
        
        # Second call - should use cache
        template2 = await service.get_grading_template('123')
        assert mock_zoho.get_record.call_count == 1  # Not called again
        
        assert template1 is template2  # Same instance
    
    def test_build_learning_outcomes_subform(self, service):
        """Test building Learning_Outcomes_Assessm subform."""
        # Create template
        unit_data = {
            'id': '123',
            'Name': 'Unit 1',
            'P1_description': 'Explain the components of a computer system',
            'P2_description': 'Describe the software development process',
            'M1_description': 'Analyze the impact of technology on society'
        }
        template = GradingTemplate('123', unit_data)
        
        # Create grade data
        grade = MoodleGradeData(
            moodle_grade_id="12345",
            student_id="101",
            course_id="202",
            zoho_student_id="111",
            zoho_class_id="222",
            zoho_unit_id="123",
            overall_grade="Pass",
            graded_date="2026-01-25"
        )
        grade.add_criterion_score("P1", "Achieved", "Clear explanation")
        grade.add_criterion_score("P2", "Achieved", "Good detail")
        grade.add_criterion_score("M1", "Not Achieved", "Needs more analysis")
        
        # Build subform
        subform = service.build_learning_outcomes_subform(grade, template)
        
        assert len(subform) == 3
        
        # Check P1
        p1_row = subform[0]
        assert p1_row['LO_Code'] == 'P1'
        assert p1_row['LO_Score'] == 'Achieved'
        assert p1_row['LO_Feedback'] == 'Clear explanation'
        assert p1_row['LO_Definition'] == 'Explain the components of a computer system'
        assert len(p1_row['LO_Title']) <= 100  # Truncated
        
        # Check M1
        m1_row = subform[2]
        assert m1_row['LO_Code'] == 'M1'
        assert m1_row['LO_Score'] == 'Not Achieved'
    
    @pytest.mark.asyncio
    async def test_sync_grade_create_new(self, service, mock_zoho):
        """Test creating new grade record."""
        # Mock template fetch
        unit_data = {
            'id': '333',
            'Name': 'Unit 1',
            'P1_description': 'Test P1',
            'P2_description': 'Test P2'
        }
        mock_zoho.get_record.return_value = unit_data
        
        # Mock search - no existing grade
        mock_zoho.search_records.return_value = []
        
        # Mock create success
        mock_zoho.create_record.return_value = {
            'code': 'SUCCESS',
            'details': {'id': '5843017000000444444'}
        }
        
        # Create grade data
        grade = MoodleGradeData(
            moodle_grade_id="12345",
            student_id="101",
            course_id="202",
            zoho_student_id="111",
            zoho_class_id="222",
            zoho_unit_id="333",
            overall_grade="Pass",
            graded_date="2026-01-25",
            feedback="Good work"
        )
        grade.add_criterion_score("P1", "Achieved", "Good")
        grade.add_criterion_score("P2", "Achieved", "Well done")
        
        # Sync
        result = await service.sync_grade(grade)
        
        assert result['status'] == 'created'
        assert result['zoho_record_id'] == '5843017000000444444'
        assert result['composite_key'] == '101_202'
        assert result['criteria_count'] == 2
        
        # Verify create was called
        mock_zoho.create_record.assert_called_once()
        call_args = mock_zoho.create_record.call_args[0]
        assert call_args[0] == 'BTEC_Grades'
        
        grade_data = call_args[1]
        assert grade_data['Student'] == '111'
        assert grade_data['Grade'] == 'Pass'
        assert grade_data['Moodle_Grade_Composite_Key'] == '101_202'
        assert len(grade_data['Learning_Outcomes_Assessm']) == 2
    
    @pytest.mark.asyncio
    async def test_sync_grade_update_existing(self, service, mock_zoho):
        """Test updating existing grade record."""
        # Mock template
        unit_data = {'id': '333', 'Name': 'Unit 1', 'P1_description': 'Test'}
        mock_zoho.get_record.return_value = unit_data
        
        # Mock search - existing grade found
        mock_zoho.search_records.return_value = [
            {'id': '5843017000000444444', 'Grade': 'Pass'}
        ]
        
        # Mock update success
        mock_zoho.update_record.return_value = {
            'code': 'SUCCESS',
            'details': {'id': '5843017000000444444'}
        }
        
        # Create grade data
        grade = MoodleGradeData(
            moodle_grade_id="12345",
            student_id="101",
            course_id="202",
            zoho_student_id="111",
            zoho_class_id="222",
            zoho_unit_id="333",
            overall_grade="Merit",  # Upgraded
            graded_date="2026-01-25"
        )
        grade.add_criterion_score("P1", "Achieved")
        
        # Sync
        result = await service.sync_grade(grade)
        
        assert result['status'] == 'updated'
        assert result['zoho_record_id'] == '5843017000000444444'
        
        # Verify update was called
        mock_zoho.update_record.assert_called_once()
        call_args = mock_zoho.update_record.call_args[0]
        assert call_args[0] == 'BTEC_Grades'
        assert call_args[1] == '5843017000000444444'
        
        grade_data = call_args[2]
        assert grade_data['Grade'] == 'Merit'
    
    @pytest.mark.asyncio
    async def test_sync_grade_simple(self, service, mock_zoho):
        """Test simplified sync interface."""
        # Mock template
        unit_data = {'id': '333', 'Name': 'Unit 1', 'P1_description': 'Test'}
        mock_zoho.get_record.return_value = unit_data
        
        # Mock search - no existing
        mock_zoho.search_records.return_value = []
        
        # Mock create
        mock_zoho.create_record.return_value = {
            'code': 'SUCCESS',
            'details': {'id': '5843017000000444444'}
        }
        
        # Sync using simple interface
        result = await service.sync_grade_simple(
            moodle_grade_id="12345",
            student_zoho_id="111",
            class_zoho_id="222",
            unit_zoho_id="333",
            overall_grade="Pass",
            criteria_scores=[
                ("P1", "Achieved", "Good work")
            ],
            student_moodle_id="101",
            course_moodle_id="202",
            feedback="Overall good"
        )
        
        assert result['status'] == 'created'
        assert result['zoho_record_id'] == '5843017000000444444'


if __name__ == '__main__':
    pytest.main([__file__, '-v'])
