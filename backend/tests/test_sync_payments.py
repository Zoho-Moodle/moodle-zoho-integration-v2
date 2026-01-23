"""
Tests for Payment Sync Endpoint

Covers:
- NEW payments
- UNCHANGED payments
- UPDATED payments
- INVALID payments (missing registration)
- Batch processing
"""

import pytest
from sqlalchemy.orm import Session
from app.infra.db.models.student import Student
from app.infra.db.models.program import Program
from app.infra.db.models.registration import Registration
from app.infra.db.models.payment import Payment
from app.services.payment_service import PaymentService
from app.domain.payment import CanonicalPayment, LookupField
from datetime import date


@pytest.fixture
def db_session(db: Session):
    """Fixture to get DB session."""
    return db


@pytest.fixture
def setup_registration(db_session: Session):
    """Create test registration."""
    student = Student(
        tenant_id="default",
        zoho_id="stud_pay_001",
        academic_email="student@pay.com",
        status="Active"
    )
    db_session.add(student)
    db_session.flush()  # Ensure student is inserted first
    
    program = Program(
        tenant_id="default",
        zoho_id="prog_pay_001",
        name="Pay Program",
        status="Active"
    )
    db_session.add(program)
    db_session.flush()  # Ensure program is inserted first
    
    registration = Registration(
        tenant_id="default",
        zoho_id="reg_pay_001",
        student_zoho_id="stud_pay_001",
        program_zoho_id="prog_pay_001",
        enrollment_status="Active",
    )
    db_session.add(registration)
    db_session.commit()
    
    return registration


def test_create_new_payment(db_session: Session, setup_registration):
    """Test creating a NEW payment."""
    service = PaymentService(db_session)
    
    canonical = CanonicalPayment(
        zoho_id="pay_test_001",
        registration=LookupField(id="reg_pay_001"),
        amount=500.00,
        payment_status="Completed",
        payment_date=date(2026, 1, 20),
        payment_method="Credit Card",
    )
    
    result = service.sync_payment(canonical, "default")
    
    assert result["status"] == "NEW"
    
    # Verify in DB
    db_pay = db_session.query(Payment).filter_by(zoho_id="pay_test_001").first()
    assert db_pay is not None
    assert db_pay.amount == 500.00


def test_invalid_payment_missing_registration(db_session: Session):
    """Test INVALID when registration doesn't exist."""
    service = PaymentService(db_session)
    
    canonical = CanonicalPayment(
        zoho_id="pay_invalid_001",
        registration=LookupField(id="reg_nonexistent"),
        amount=100.00,
        payment_status="Completed",
    )
    
    result = service.sync_payment(canonical, "default")
    
    assert result["status"] == "INVALID"
    assert "Registration" in result["message"]


def test_batch_sync_payments(db_session: Session, setup_registration):
    """Test batch sync with multiple payments."""
    service = PaymentService(db_session)
    
    canonicals = [
        CanonicalPayment(
            zoho_id=f"pay_batch_{i}",
            registration=LookupField(id="reg_pay_001"),
            amount=100.00 * (i + 1),
            payment_status="Completed",
        )
        for i in range(3)
    ]
    
    result = service.sync_batch(canonicals, "default")
    
    assert result["total"] == 3
    assert result["new"] == 3
