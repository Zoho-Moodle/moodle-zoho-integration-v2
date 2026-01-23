"""
Extension API Tests
"""

import pytest
import hmac
import hashlib
import time
import json
from fastapi.testclient import TestClient
from sqlalchemy.orm import Session
from app.main import app
from app.services.extension_service import ExtensionService


client = TestClient(app)


def generate_signature(
    method: str, path: str, body: str, timestamp: str, nonce: str, secret: str
) -> str:
    """Generate HMAC signature"""
    body_hash = hashlib.sha256(body.encode()).hexdigest()
    message = f"{timestamp}.{nonce}.{method}.{path}.{body_hash}"
    return hmac.new(secret.encode(), message.encode(), hashlib.sha256).hexdigest()


def make_authenticated_request(
    method: str,
    path: str,
    json_data: dict = None,
    tenant_id: str = "default",
    api_key: str = "ext_key_default",
    secret: str = "ext_secret_change_me_in_production"
):
    """Make authenticated request to extension API"""
    timestamp = str(time.time())
    nonce = f"test_nonce_{int(time.time() * 1000)}"
    body = json.dumps(json_data) if json_data else ""
    
    signature = generate_signature(method, path, body, timestamp, nonce, secret)
    
    headers = {
        "X-Ext-Key": api_key,
        "X-Ext-Timestamp": timestamp,
        "X-Ext-Nonce": nonce,
        "X-Ext-Signature": signature,
        "X-Tenant-ID": tenant_id,
        "Content-Type": "application/json"
    }
    
    if method == "GET":
        return client.get(path, headers=headers)
    elif method == "POST":
        return client.post(path, json=json_data, headers=headers)
    elif method == "PUT":
        return client.put(path, json=json_data, headers=headers)
    else:
        raise ValueError(f"Unsupported method: {method}")


def test_auth_invalid_signature():
    """Test authentication with invalid signature"""
    headers = {
        "X-Ext-Key": "ext_key_default",
        "X-Ext-Timestamp": str(time.time()),
        "X-Ext-Nonce": "test_nonce",
        "X-Ext-Signature": "invalid_signature",
        "X-Tenant-ID": "default"
    }
    
    response = client.get("/v1/extension/settings", headers=headers)
    assert response.status_code == 401


def test_auth_expired_timestamp():
    """Test authentication with expired timestamp"""
    timestamp = str(time.time() - 600)  # 10 minutes ago
    nonce = "test_nonce"
    body = ""
    secret = "ext_secret_change_me_in_production"
    
    signature = generate_signature("GET", "/v1/extension/settings", body, timestamp, nonce, secret)
    
    headers = {
        "X-Ext-Key": "ext_key_default",
        "X-Ext-Timestamp": timestamp,
        "X-Ext-Nonce": nonce,
        "X-Ext-Signature": signature,
        "X-Tenant-ID": "default"
    }
    
    response = client.get("/v1/extension/settings", headers=headers)
    assert response.status_code == 401
    assert "timestamp" in response.json()["detail"].lower()


def test_get_settings():
    """Test getting integration settings"""
    response = make_authenticated_request("GET", "/v1/extension/settings")
    assert response.status_code == 200
    data = response.json()
    assert data["tenant_id"] == "default"
    assert "extension_api_key" in data


def test_update_settings():
    """Test updating integration settings"""
    updates = {
        "moodle_enabled": True,
        "moodle_base_url": "https://moodle.example.com",
        "zoho_enabled": True
    }
    
    response = make_authenticated_request("PUT", "/v1/extension/settings", updates)
    assert response.status_code == 200
    data = response.json()
    assert data["moodle_enabled"] is True
    assert data["moodle_base_url"] == "https://moodle.example.com"


def test_get_modules():
    """Test getting module settings"""
    response = make_authenticated_request("GET", "/v1/extension/modules")
    assert response.status_code == 200
    data = response.json()
    assert isinstance(data, list)
    assert len(data) > 0


def test_update_module():
    """Test updating module settings"""
    updates = {
        "enabled": True,
        "schedule_mode": "cron",
        "schedule_cron": "0 */6 * * *"
    }
    
    response = make_authenticated_request("PUT", "/v1/extension/modules/students", updates)
    assert response.status_code == 200
    data = response.json()
    assert data["enabled"] is True
    assert data["schedule_mode"] == "cron"


def test_block_grades_module():
    """Test that grades module cannot be enabled"""
    updates = {"enabled": True}
    
    response = make_authenticated_request("PUT", "/v1/extension/modules/grades", updates)
    assert response.status_code == 400
    assert "Moodle -> Zoho" in response.json()["detail"]


def test_get_mappings():
    """Test getting field mappings"""
    response = make_authenticated_request("GET", "/v1/extension/mappings/students")
    assert response.status_code == 200
    data = response.json()
    assert isinstance(data, list)


def test_update_mappings():
    """Test updating field mappings"""
    mappings = {
        "mappings": [
            {
                "canonical_field": "academic_email",
                "zoho_field_api_name": "Academic_Email",
                "required": True
            },
            {
                "canonical_field": "username",
                "zoho_field_api_name": "Academic_Email",
                "required": True,
                "transform_rules": {"type": "before_at"}
            }
        ]
    }
    
    response = make_authenticated_request("PUT", "/v1/extension/mappings/students", mappings)
    assert response.status_code == 200
    data = response.json()
    assert len(data) == 2


def test_get_canonical_schema():
    """Test getting canonical schema"""
    response = make_authenticated_request("GET", "/v1/extension/metadata/canonical-schema")
    assert response.status_code == 200
    data = response.json()
    assert "students" in data
    assert "fields" in data["students"]


def test_trigger_manual_sync(db: Session):
    """Test triggering manual sync"""
    # First enable the module
    make_authenticated_request("PUT", "/v1/extension/modules/students", {"enabled": True})
    
    # Then trigger sync
    response = make_authenticated_request(
        "POST",
        "/v1/extension/sync/students/run",
        {"triggered_by": "test_user"}
    )
    
    assert response.status_code == 200
    data = response.json()
    assert data["module_name"] == "students"
    assert data["trigger_source"] == "manual"
    assert "run_id" in data


def test_block_grades_sync():
    """Test that grades sync cannot be triggered"""
    response = make_authenticated_request(
        "POST",
        "/v1/extension/sync/grades/run",
        {"triggered_by": "test_user"}
    )
    
    assert response.status_code == 400
    assert "Moodle -> Zoho" in response.json()["detail"]


def test_get_sync_runs():
    """Test getting sync run history"""
    response = make_authenticated_request("GET", "/v1/extension/runs?limit=10")
    assert response.status_code == 200
    data = response.json()
    assert isinstance(data, list)
