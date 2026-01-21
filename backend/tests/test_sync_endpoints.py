"""
Tests for Programs, Classes, and Enrollments sync endpoints.

Requirements:
- pytest installed
- Database accessible via DATABASE_URL

To run:
    pytest tests/ -v
    pytest tests/test_sync_programs.py -v  # specific test file
    pytest tests/test_sync_programs.py::test_new_program -v  # specific test
"""

import pytest
import json
import hashlib
from sqlalchemy.orm import Session
from fastapi.testclient import TestClient

from app.main import app
from app.infra.db.session import get_db
from app.infra.db.base import Base, engine
from app.core.idempotency import compute_request_hash

# Test database setup
@pytest.fixture(scope="session")
def setup_db():
    """Create tables before tests"""
    Base.metadata.create_all(bind=engine)
    yield
    # Cleanup after tests
    Base.metadata.drop_all(bind=engine)


@pytest.fixture
def db_session(setup_db):
    """Provide a fresh DB session for each test"""
    connection = engine.connect()
    transaction = connection.begin()
    session = Session(bind=connection)

    yield session

    session.close()
    transaction.rollback()
    connection.close()


@pytest.fixture
def client(db_session):
    """Provide a test client"""
    def override_get_db():
        return db_session
    
    app.dependency_overrides[get_db] = override_get_db
    
    with TestClient(app) as test_client:
        yield test_client
    
    app.dependency_overrides.clear()


# ============================================================================
# PROGRAMS TESTS
# ============================================================================

class TestProgramsSync:
    """Test /v1/sync/programs endpoint"""

    def test_new_program(self, client):
        """Test creating a new program"""
        payload = {
            "data": [
                {
                    "id": "prog_001",
                    "Product_Name": "Python Basics",
                    "Price": "99.99",
                    "status": "Active"
                }
            ]
        }
        
        response = client.post("/v1/sync/programs", json=payload)
        assert response.status_code == 200
        data = response.json()
        
        assert data["status"] == "success"
        assert "idempotency_key" in data
        assert len(data["results"]) == 1
        assert data["results"][0]["status"] == "NEW"
        assert data["results"][0]["zoho_program_id"] == "prog_001"

    def test_duplicate_request(self, client):
        """Test idempotency - same request within 1 hour returns cached result"""
        payload = {
            "data": [
                {
                    "id": "prog_002",
                    "Product_Name": "Data Science",
                    "Price": "149.99",
                    "status": "Active"
                }
            ]
        }
        
        # First request
        response1 = client.post("/v1/sync/programs", json=payload)
        data1 = response1.json()
        
        # Duplicate request
        response2 = client.post("/v1/sync/programs", json=payload)
        data2 = response2.json()
        
        # Both should have same idempotency key
        assert data1["idempotency_key"] == data2["idempotency_key"]
        # Both should have same results
        assert data1["results"][0]["status"] == data2["results"][0]["status"] == "NEW"

    def test_updated_program(self, client):
        """Test updating an existing program"""
        prog_id = "prog_003"
        
        # Create program
        payload1 = {
            "data": [
                {
                    "id": prog_id,
                    "Product_Name": "Web Development",
                    "Price": "199.99",
                    "status": "Active"
                }
            ]
        }
        response1 = client.post("/v1/sync/programs", json=payload1)
        assert response1.json()["results"][0]["status"] == "NEW"
        
        # Update program (different name)
        payload2 = {
            "data": [
                {
                    "id": prog_id,
                    "Product_Name": "Advanced Web Development",
                    "Price": "199.99",
                    "status": "Active"
                }
            ]
        }
        response2 = client.post("/v1/sync/programs", json=payload2)
        data = response2.json()
        
        assert data["results"][0]["status"] == "UPDATED"
        assert "changes" in data["results"][0]

    def test_unchanged_program(self, client):
        """Test syncing unchanged program"""
        prog_id = "prog_004"
        
        payload = {
            "data": [
                {
                    "id": prog_id,
                    "Product_Name": "Mobile Dev",
                    "Price": "129.99",
                    "status": "Active"
                }
            ]
        }
        
        # First sync
        response1 = client.post("/v1/sync/programs", json=payload)
        assert response1.json()["results"][0]["status"] == "NEW"
        
        # Exact duplicate (clear idempotency cache first by different format)
        payload_copy = json.loads(json.dumps(payload))  # Deep copy
        
        # Send with different order (different hash) but same content
        response2 = client.post("/v1/sync/programs", json=payload_copy)
        data = response2.json()
        
        # Should detect as UNCHANGED since fingerprint is same
        assert data["results"][0]["status"] == "UNCHANGED"

    def test_invalid_program(self, client):
        """Test handling invalid program data"""
        payload = {
            "data": [
                {
                    # Missing id (zoho_id)
                    "Product_Name": "No ID Program"
                }
            ]
        }
        
        response = client.post("/v1/sync/programs", json=payload)
        data = response.json()
        
        # Should either skip or mark invalid
        assert data["results"][0]["status"] in ["INVALID", "SKIPPED"]

    def test_batch_programs(self, client):
        """Test syncing multiple programs at once"""
        payload = {
            "data": [
                {
                    "id": f"prog_batch_{i}",
                    "Product_Name": f"Program {i}",
                    "Price": f"{50 * i}",
                    "status": "Active"
                }
                for i in range(1, 4)
            ]
        }
        
        response = client.post("/v1/sync/programs", json=payload)
        data = response.json()
        
        assert response.status_code == 200
        assert len(data["results"]) == 3
        assert all(r["status"] == "NEW" for r in data["results"])


# ============================================================================
# CLASSES TESTS
# ============================================================================

class TestClassesSync:
    """Test /v1/sync/classes endpoint"""

    def test_new_class(self, client):
        """Test creating a new class"""
        payload = {
            "data": [
                {
                    "id": "class_001",
                    "BTEC_Class_Name": "Python 101",
                    "Short_Name": "PY101",
                    "status": "Active",
                    "Start_Date": "2024-01-15",
                    "End_Date": "2024-06-15"
                }
            ]
        }
        
        response = client.post("/v1/sync/classes", json=payload)
        assert response.status_code == 200
        data = response.json()
        
        assert data["status"] == "success"
        assert len(data["results"]) == 1
        assert data["results"][0]["status"] == "NEW"
        assert data["results"][0]["zoho_class_id"] == "class_001"

    def test_updated_class(self, client):
        """Test updating an existing class"""
        class_id = "class_002"
        
        # Create class
        payload1 = {
            "data": [
                {
                    "id": class_id,
                    "BTEC_Class_Name": "Java Basics",
                    "Short_Name": "JAVA101",
                    "status": "Active",
                    "Start_Date": "2024-02-01",
                    "End_Date": "2024-07-01"
                }
            ]
        }
        client.post("/v1/sync/classes", json=payload1)
        
        # Update class (different end date)
        payload2 = {
            "data": [
                {
                    "id": class_id,
                    "BTEC_Class_Name": "Java Basics",
                    "Short_Name": "JAVA101",
                    "status": "Active",
                    "Start_Date": "2024-02-01",
                    "End_Date": "2024-08-01"  # Changed
                }
            ]
        }
        response2 = client.post("/v1/sync/classes", json=payload2)
        data = response2.json()
        
        assert data["results"][0]["status"] == "UPDATED"
        assert "changes" in data["results"][0]

    def test_unchanged_class(self, client):
        """Test syncing unchanged class"""
        class_id = "class_003"
        
        payload = {
            "data": [
                {
                    "id": class_id,
                    "BTEC_Class_Name": "C# Advanced",
                    "Short_Name": "CS301",
                    "status": "Active",
                    "Start_Date": "2024-03-01",
                    "End_Date": "2024-09-01"
                }
            ]
        }
        
        # First sync
        client.post("/v1/sync/classes", json=payload)
        
        # Second sync (should be UNCHANGED)
        response2 = client.post("/v1/sync/classes", json=payload)
        data = response2.json()
        
        assert data["results"][0]["status"] == "UNCHANGED"

    def test_invalid_class(self, client):
        """Test handling invalid class data"""
        payload = {
            "data": [
                {
                    # Missing required id
                    "BTEC_Class_Name": "No ID Class"
                }
            ]
        }
        
        response = client.post("/v1/sync/classes", json=payload)
        data = response.json()
        
        assert data["results"][0]["status"] in ["INVALID", "SKIPPED"]

    def test_batch_classes(self, client):
        """Test syncing multiple classes at once"""
        payload = {
            "data": [
                {
                    "id": f"class_batch_{i}",
                    "BTEC_Class_Name": f"Class {i}",
                    "Short_Name": f"C{i}",
                    "status": "Active",
                    "Start_Date": "2024-01-15",
                    "End_Date": "2024-06-15"
                }
                for i in range(1, 4)
            ]
        }
        
        response = client.post("/v1/sync/classes", json=payload)
        data = response.json()
        
        assert response.status_code == 200
        assert len(data["results"]) == 3


# ============================================================================
# ENROLLMENTS TESTS
# ============================================================================

class TestEnrollmentsSync:
    """Test /v1/sync/enrollments endpoint"""

    @pytest.fixture
    def setup_student_and_class(self, client):
        """Setup a student and class for enrollment tests"""
        # Create student
        student_payload = {
            "data": [
                {
                    "id": "student_test_001",
                    "email": "student@test.com",
                    "name": "Test Student"
                }
            ]
        }
        client.post("/v1/sync/students", json=student_payload)
        
        # Create class
        class_payload = {
            "data": [
                {
                    "id": "class_test_001",
                    "BTEC_Class_Name": "Test Class",
                    "Short_Name": "TC",
                    "status": "Active",
                    "Start_Date": "2024-01-15",
                    "End_Date": "2024-06-15"
                }
            ]
        }
        client.post("/v1/sync/classes", json=class_payload)
        
        return {
            "student_id": "student_test_001",
            "class_id": "class_test_001"
        }

    def test_enrollment_skipped_no_student(self, client):
        """Test enrollment skipped when student not synced"""
        payload = {
            "data": [
                {
                    "id": "enr_001",
                    "Student": {"id": "nonexistent_student"},
                    "BTEC_Class": {"id": "class_001"},
                    "status": "Active",
                    "Start_Date": "2024-01-15"
                }
            ]
        }
        
        response = client.post("/v1/sync/enrollments", json=payload)
        data = response.json()
        
        assert data["results"][0]["status"] == "SKIPPED"
        assert "student_not_synced_yet" in data["results"][0]["reason"]

    def test_enrollment_skipped_no_class(self, client):
        """Test enrollment skipped when class not synced"""
        # First create a student
        student_payload = {
            "data": [
                {
                    "id": "student_001",
                    "email": "student@example.com",
                    "name": "Test Student"
                }
            ]
        }
        client.post("/v1/sync/students", json=student_payload)
        
        # Try enrollment with nonexistent class
        payload = {
            "data": [
                {
                    "id": "enr_002",
                    "Student": {"id": "student_001"},
                    "BTEC_Class": {"id": "nonexistent_class"},
                    "status": "Active",
                    "Start_Date": "2024-01-15"
                }
            ]
        }
        
        response = client.post("/v1/sync/enrollments", json=payload)
        data = response.json()
        
        assert data["results"][0]["status"] == "SKIPPED"
        assert "class_not_synced_yet" in data["results"][0]["reason"]

    def test_new_enrollment(self, client, setup_student_and_class):
        """Test creating enrollment when both dependencies exist"""
        deps = setup_student_and_class
        
        payload = {
            "data": [
                {
                    "id": "enr_003",
                    "Student": {"id": deps["student_id"]},
                    "BTEC_Class": {"id": deps["class_id"]},
                    "status": "Active",
                    "Start_Date": "2024-01-15"
                }
            ]
        }
        
        response = client.post("/v1/sync/enrollments", json=payload)
        data = response.json()
        
        assert data["results"][0]["status"] == "NEW"

    def test_updated_enrollment(self, client, setup_student_and_class):
        """Test updating enrollment"""
        deps = setup_student_and_class
        
        # Create enrollment
        payload1 = {
            "data": [
                {
                    "id": "enr_004",
                    "Student": {"id": deps["student_id"]},
                    "BTEC_Class": {"id": deps["class_id"]},
                    "status": "Active",
                    "Start_Date": "2024-01-15"
                }
            ]
        }
        client.post("/v1/sync/enrollments", json=payload1)
        
        # Update enrollment
        payload2 = {
            "data": [
                {
                    "id": "enr_004",
                    "Student": {"id": deps["student_id"]},
                    "BTEC_Class": {"id": deps["class_id"]},
                    "status": "Inactive",  # Changed
                    "Start_Date": "2024-01-15"
                }
            ]
        }
        response2 = client.post("/v1/sync/enrollments", json=payload2)
        data = response2.json()
        
        assert data["results"][0]["status"] == "UPDATED"

    def test_batch_enrollments_mixed(self, client):
        """Test batch with mix of skipped and new"""
        # Create one student and class
        student_payload = {
            "data": [
                {
                    "id": "student_batch_001",
                    "email": "batch@test.com",
                    "name": "Batch Student"
                }
            ]
        }
        client.post("/v1/sync/students", json=student_payload)
        
        class_payload = {
            "data": [
                {
                    "id": "class_batch_001",
                    "BTEC_Class_Name": "Batch Class",
                    "Short_Name": "BC",
                    "status": "Active"
                }
            ]
        }
        client.post("/v1/sync/classes", json=class_payload)
        
        # Batch with 2 valid, 2 invalid
        payload = {
            "data": [
                {
                    "id": "enr_batch_001",
                    "Student": {"id": "student_batch_001"},
                    "BTEC_Class": {"id": "class_batch_001"},
                    "status": "Active"
                },
                {
                    "id": "enr_batch_002",
                    "Student": {"id": "nonexistent"},
                    "BTEC_Class": {"id": "class_batch_001"},
                    "status": "Active"
                },
            ]
        }
        
        response = client.post("/v1/sync/enrollments", json=payload)
        data = response.json()
        
        assert len(data["results"]) == 2
        assert data["results"][0]["status"] == "NEW"
        assert data["results"][1]["status"] == "SKIPPED"
