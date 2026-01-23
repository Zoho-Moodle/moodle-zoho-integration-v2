"""
Tests for Unit Sync Endpoint

Covers:
- NEW units
- UNCHANGED units
- UPDATED units
- Batch processing
"""

import pytest
from sqlalchemy.orm import Session
from app.infra.db.models.unit import Unit
from app.services.unit_service import UnitService
from app.domain.unit import CanonicalUnit


@pytest.fixture
def db_session(db: Session):
    """Fixture to get DB session."""
    return db


def test_create_new_unit(db_session: Session):
    """Test creating a NEW unit."""
    service = UnitService(db_session)
    
    canonical = CanonicalUnit(
        zoho_id="unit_test_001",
        unit_code="UNIT001",
        unit_name="Introduction to Business",
        credit_hours=30.0,
        level="L3",
        status="Active",
    )
    
    result = service.sync_unit(canonical, "default")
    
    assert result["status"] == "NEW"
    assert result["message"] == "Unit created"
    
    # Verify in DB
    db_unit = db_session.query(Unit).filter_by(zoho_id="unit_test_001").first()
    assert db_unit is not None
    assert db_unit.unit_name == "Introduction to Business"
    assert db_unit.credit_hours == 30.0
    assert db_unit.level == "L3"


def test_unchanged_unit(db_session: Session):
    """Test that UNCHANGED units are not modified."""
    service = UnitService(db_session)
    
    canonical = CanonicalUnit(
        zoho_id="unit_test_002",
        unit_code="UNIT002",
        unit_name="Advanced Topics",
        status="Active",
    )
    
    # First sync
    result1 = service.sync_unit(canonical, "default")
    assert result1["status"] == "NEW"
    
    # Second sync (same data)
    result2 = service.sync_unit(canonical, "default")
    assert result2["status"] == "UNCHANGED"


def test_updated_unit(db_session: Session):
    """Test updating a unit with changed name."""
    service = UnitService(db_session)
    
    canonical1 = CanonicalUnit(
        zoho_id="unit_test_003",
        unit_code="UNIT003",
        unit_name="Original Name",
        status="Active",
    )
    
    # Create
    result1 = service.sync_unit(canonical1, "default")
    assert result1["status"] == "NEW"
    
    # Update name
    canonical2 = CanonicalUnit(
        zoho_id="unit_test_003",
        unit_code="UNIT003",
        unit_name="Updated Name",
        status="Active",
    )
    
    result2 = service.sync_unit(canonical2, "default")
    assert result2["status"] == "UPDATED"
    assert "unit_name" in result2["changed_fields"]
    
    # Verify in DB
    db_unit = db_session.query(Unit).filter_by(zoho_id="unit_test_003").first()
    assert db_unit.unit_name == "Updated Name"


def test_batch_sync_units(db_session: Session):
    """Test batch sync with multiple units."""
    service = UnitService(db_session)
    
    canonicals = [
        CanonicalUnit(
            zoho_id=f"unit_batch_{i}",
            unit_code=f"CODE{i:03d}",
            unit_name=f"Unit {i}",
            status="Active",
        )
        for i in range(5)
    ]
    
    result = service.sync_batch(canonicals, "default")
    
    assert result["total"] == 5
    assert result["new"] == 5
    assert result["unchanged"] == 0
    assert result["updated"] == 0
    assert len(result["records"]) == 5
