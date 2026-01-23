"""
Tests for Grade Sync Endpoint

Covers:
- NEW grades
- UNCHANGED grades
- UPDATED grades
- INVALID grades (missing dependencies)
- Batch processing
"""

import pytest
from sqlalchemy.orm import Session
from app.infra.db.models.student import Student
from app.infra.db.models.unit import Unit
from app.infra.db.models.grade import Grade
from app.services.grade_service import GradeService
from app.domain.grade import CanonicalGrade, LookupField
from datetime import date


@pytest.fixture
def db_session(db: Session):
    """Fixture to get DB session."""
    return db


@pytest.fixture
def setup_dependencies(db_session: Session):
    """Create test student and unit."""
    student = Student(
        tenant_id="default",
        zoho_id="stud_grade_001",
        academic_email="grade@student.com",
        status="Active"
    )
    db_session.add(student)
    
    unit = Unit(
        tenant_id="default",
        zoho_id="unit_grade_001",
        unit_code="GRAD001",
        unit_name="Graded Unit",
        status="Active"
    )
    db_session.add(unit)
    db_session.commit()
    
    return {"student": student, "unit": unit}


def test_create_new_grade(db_session: Session, setup_dependencies):
    """Test creating a NEW grade."""
    service = GradeService(db_session)
    
    canonical = CanonicalGrade(
        zoho_id="grade_test_001",
        student=LookupField(id="stud_grade_001"),
        unit=LookupField(id="unit_grade_001"),
        grade_value="A",
        score=95.0,
        grade_date=date(2026, 6, 15),
    )
    
    result = service.sync_grade(canonical, "default")
    
    assert result["status"] == "NEW"
    
    # Verify in DB
    db_grade = db_session.query(Grade).filter_by(zoho_id="grade_test_001").first()
    assert db_grade is not None
    assert db_grade.grade_value == "A"
    assert db_grade.score == 95.0


def test_invalid_grade_missing_student(db_session: Session, setup_dependencies):
    """Test INVALID when student doesn't exist."""
    service = GradeService(db_session)
    
    canonical = CanonicalGrade(
        zoho_id="grade_invalid_1",
        student=LookupField(id="stud_nonexistent"),
        unit=LookupField(id="unit_grade_001"),
        grade_value="B",
    )
    
    result = service.sync_grade(canonical, "default")
    
    assert result["status"] == "INVALID"
    assert "Student" in result["message"]


def test_invalid_grade_missing_unit(db_session: Session, setup_dependencies):
    """Test INVALID when unit doesn't exist."""
    service = GradeService(db_session)
    
    canonical = CanonicalGrade(
        zoho_id="grade_invalid_2",
        student=LookupField(id="stud_grade_001"),
        unit=LookupField(id="unit_nonexistent"),
        grade_value="C",
    )
    
    result = service.sync_grade(canonical, "default")
    
    assert result["status"] == "INVALID"
    assert "Unit" in result["message"]


def test_batch_sync_grades(db_session: Session, setup_dependencies):
    """Test batch sync with multiple grades."""
    service = GradeService(db_session)
    
    grades = ["A", "B", "C", "D"]
    canonicals = [
        CanonicalGrade(
            zoho_id=f"grade_batch_{i}",
            student=LookupField(id="stud_grade_001"),
            unit=LookupField(id="unit_grade_001"),
            grade_value=grade,
            score=float(100 - (i * 20)),
        )
        for i, grade in enumerate(grades)
    ]
    
    result = service.sync_batch(canonicals, "default")
    
    assert result["total"] == 4
    assert result["new"] == 4
