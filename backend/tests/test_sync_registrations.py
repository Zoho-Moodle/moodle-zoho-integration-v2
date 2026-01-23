"""
Tests for Registration Sync Endpoint

Covers:
- NEW registrations
- UNCHANGED registrations
- UPDATED registrations
- INVALID registrations (missing dependencies)
- Batch processing
- Idempotency
"""

import pytest
from sqlalchemy.orm import Session
from app.infra.db.models.student import Student
from app.infra.db.models.program import Program
from app.infra.db.models.registration import Registration
from app.services.registration_service import RegistrationService
from app.domain.registration import CanonicalRegistration, LookupField
from datetime import date


@pytest.fixture
def db_session(db: Session):
    """Fixture to get DB session."""
    return db


@pytest.fixture
def setup_dependencies(db_session: Session):
    """Create test student and program."""
    student = Student(
        tenant_id="default",
        zoho_id="stud_test_001",
        academic_email="student@test.com",
        status="Active"
    )
    db_session.add(student)
    
    program = Program(
        tenant_id="default",
        zoho_id="prog_test_001",
        name="Test Program",
        status="Active"
    )
    db_session.add(program)
    db_session.commit()
    
    return {"student": student, "program": program}


def test_create_new_registration(db_session: Session, setup_dependencies):
    """Test creating a NEW registration."""
    service = RegistrationService(db_session)
    
    canonical = CanonicalRegistration(
        zoho_id="reg_test_001",
        student=LookupField(id="stud_test_001", name="Test Student"),
        program=LookupField(id="prog_test_001", name="Test Program"),
        enrollment_status="Active",
        registration_date=date(2026, 1, 15),
    )
    
    result = service.sync_registration(canonical, "default")
    
    assert result["status"] == "NEW"
    assert result["message"] == "Registration created"
    
    # Verify in DB
    db_reg = db_session.query(Registration).filter_by(zoho_id="reg_test_001").first()
    assert db_reg is not None
    assert db_reg.enrollment_status == "Active"
    assert db_reg.student_zoho_id == "stud_test_001"
    assert db_reg.program_zoho_id == "prog_test_001"


def test_unchanged_registration(db_session: Session, setup_dependencies):
    """Test that UNCHANGED registrations are not modified."""
    service = RegistrationService(db_session)
    
    canonical = CanonicalRegistration(
        zoho_id="reg_test_002",
        student=LookupField(id="stud_test_001", name="Test Student"),
        program=LookupField(id="prog_test_001", name="Test Program"),
        enrollment_status="Active",
        registration_date=date(2026, 1, 15),
    )
    
    # First sync
    result1 = service.sync_registration(canonical, "default")
    assert result1["status"] == "NEW"
    
    # Second sync (same data)
    result2 = service.sync_registration(canonical, "default")
    assert result2["status"] == "UNCHANGED"
    assert result2["message"] == "No changes detected"


def test_updated_registration(db_session: Session, setup_dependencies):
    """Test updating a registration with changed status."""
    service = RegistrationService(db_session)
    
    canonical1 = CanonicalRegistration(
        zoho_id="reg_test_003",
        student=LookupField(id="stud_test_001"),
        program=LookupField(id="prog_test_001"),
        enrollment_status="Active",
        registration_date=date(2026, 1, 15),
    )
    
    # Create
    result1 = service.sync_registration(canonical1, "default")
    assert result1["status"] == "NEW"
    
    # Update status
    canonical2 = CanonicalRegistration(
        zoho_id="reg_test_003",
        student=LookupField(id="stud_test_001"),
        program=LookupField(id="prog_test_001"),
        enrollment_status="Inactive",  # Changed
        registration_date=date(2026, 1, 15),
    )
    
    result2 = service.sync_registration(canonical2, "default")
    assert result2["status"] == "UPDATED"
    assert "enrollment_status" in result2["changed_fields"]
    
    # Verify in DB
    db_reg = db_session.query(Registration).filter_by(zoho_id="reg_test_003").first()
    assert db_reg.enrollment_status == "Inactive"


def test_invalid_registration_missing_student(db_session: Session, setup_dependencies):
    """Test INVALID when student doesn't exist."""
    service = RegistrationService(db_session)
    
    canonical = CanonicalRegistration(
        zoho_id="reg_test_invalid_1",
        student=LookupField(id="stud_nonexistent"),
        program=LookupField(id="prog_test_001"),
        enrollment_status="Active",
    )
    
    result = service.sync_registration(canonical, "default")
    
    assert result["status"] == "INVALID"
    assert "Student" in result["message"] and "not found" in result["message"]


def test_invalid_registration_missing_program(db_session: Session, setup_dependencies):
    """Test INVALID when program doesn't exist."""
    service = RegistrationService(db_session)
    
    canonical = CanonicalRegistration(
        zoho_id="reg_test_invalid_2",
        student=LookupField(id="stud_test_001"),
        program=LookupField(id="prog_nonexistent"),
        enrollment_status="Active",
    )
    
    result = service.sync_registration(canonical, "default")
    
    assert result["status"] == "INVALID"
    assert "Program" in result["message"] and "not found" in result["message"]


def test_batch_sync_registrations(db_session: Session, setup_dependencies):
    """Test batch sync with multiple registrations."""
    service = RegistrationService(db_session)
    
    canonicals = [
        CanonicalRegistration(
            zoho_id=f"reg_batch_{i}",
            student=LookupField(id="stud_test_001"),
            program=LookupField(id="prog_test_001"),
            enrollment_status="Active",
        )
        for i in range(3)
    ]
    
    result = service.sync_batch(canonicals, "default")
    
    assert result["total"] == 3
    assert result["new"] == 3
    assert result["unchanged"] == 0
    assert result["updated"] == 0
    assert len(result["records"]) == 3
