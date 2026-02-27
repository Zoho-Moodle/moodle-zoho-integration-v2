"""
Extension API Endpoints - Field Mappings & Metadata
"""

from typing import List, Dict, Any, Optional
from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session
from pydantic import BaseModel
from app.core.auth_extension import ExtensionAuth
from app.infra.db.session import get_db
from app.services.extension_service import ExtensionService


router = APIRouter(prefix="/extension", tags=["Extension - Mappings"])


# ===== Schemas =====

class FieldMappingItem(BaseModel):
    canonical_field: str
    zoho_field_api_name: str
    required: bool = False
    default_value: Optional[str] = None
    transform_rules: Optional[Dict[str, Any]] = {}


class FieldMappingResponse(BaseModel):
    canonical_field: str
    zoho_field_api_name: str
    required: bool
    default_value: Optional[str]
    transform_rules: Dict[str, Any]
    
    class Config:
        from_attributes = True


class FieldMappingBulkUpdate(BaseModel):
    mappings: List[FieldMappingItem]


# ===== Endpoints =====

@router.get("/mappings/{module_name}", response_model=List[FieldMappingResponse])
async def get_mappings(
    module_name: str,
    auth: dict = ExtensionAuth,
    db: Session = Depends(get_db)
):
    """Get field mappings for module"""
    service = ExtensionService(db)
    tenant_id = auth["tenant_id"]
    
    mappings = service.get_field_mappings(tenant_id, module_name)
    
    return [
        FieldMappingResponse(
            canonical_field=m.canonical_field,
            zoho_field_api_name=m.zoho_field_api_name,
            required=m.required,
            default_value=m.default_value,
            transform_rules=m.transform_rules_json
        )
        for m in mappings
    ]


@router.put("/mappings/{module_name}", response_model=List[FieldMappingResponse])
async def update_mappings(
    module_name: str,
    data: FieldMappingBulkUpdate,
    auth: dict = ExtensionAuth,
    db: Session = Depends(get_db)
):
    """Replace all field mappings for module"""
    service = ExtensionService(db)
    tenant_id = auth["tenant_id"]
    
    mappings_data = [m.model_dump() for m in data.mappings]
    mappings = service.update_field_mappings(tenant_id, module_name, mappings_data)
    
    return [
        FieldMappingResponse(
            canonical_field=m.canonical_field,
            zoho_field_api_name=m.zoho_field_api_name,
            required=m.required,
            default_value=m.default_value,
            transform_rules=m.transform_rules_json
        )
        for m in mappings
    ]


@router.get("/metadata/canonical-schema")
async def get_canonical_schema(
    auth: dict = ExtensionAuth
):
    """Get canonical schema for all modules (required fields, types, etc.)"""
    return {
        "students": {
            "fields": {
                "academic_email": {"type": "string", "required": True, "description": "Primary email"},
                "username": {"type": "string", "required": True, "description": "Moodle username (derived from email)"},
                "display_name": {"type": "string", "required": False, "description": "Student full name"},
                "first_name": {"type": "string", "required": False},
                "last_name": {"type": "string", "required": False},
                "phone": {"type": "string", "required": False},
                "status": {"type": "string", "required": True, "description": "Active, Inactive, Suspended"},
                "profile_image_url": {"type": "string", "required": False}
            }
        },
        "programs": {
            "fields": {
                "name": {"type": "string", "required": True},
                "code": {"type": "string", "required": False},
                "description": {"type": "string", "required": False},
                "status": {"type": "string", "required": True}
            }
        },
        "classes": {
            "fields": {
                "name": {"type": "string", "required": True},
                "teacher_zoho_id": {"type": "string", "required": False},
                "program_zoho_id": {"type": "string", "required": False},
                "start_date": {"type": "date", "required": False},
                "end_date": {"type": "date", "required": False}
            }
        },
        "enrollments": {
            "fields": {
                "student_zoho_id": {"type": "string", "required": True},
                "class_zoho_id": {"type": "string", "required": True},
                "enrollment_status": {"type": "string", "required": True},
                "enrollment_date": {"type": "date", "required": False}
            }
        },
        "units": {
            "fields": {
                "name": {"type": "string", "required": True},
                "code": {"type": "string", "required": False},
                "credits": {"type": "integer", "required": False},
                "description": {"type": "string", "required": False}
            }
        },
        "registrations": {
            "fields": {
                "student_zoho_id": {"type": "string", "required": True},
                "program_zoho_id": {"type": "string", "required": True},
                "enrollment_status": {"type": "string", "required": True},
                "registration_date": {"type": "date", "required": False}
            }
        },
        "payments": {
            "fields": {
                "registration_zoho_id": {"type": "string", "required": True},
                "amount": {"type": "decimal", "required": True},
                "payment_status": {"type": "string", "required": True},
                "payment_date": {"type": "date", "required": False},
                "payment_method": {"type": "string", "required": False}
            }
        },
        "grades": {
            "fields": {
                "student_zoho_id": {"type": "string", "required": True},
                "unit_zoho_id": {"type": "string", "required": True},
                "grade_value": {"type": "string", "required": True},
                "grade_date": {"type": "date", "required": False}
            },
            "note": "Grades sync is Moodle -> Zoho (not implemented in this phase)"
        }
    }


@router.get("/metadata/moodle-adapter")
async def get_moodle_adapter(
    auth: dict = ExtensionAuth
):
    """Get read-only Moodle adapter metadata (canonical -> Moodle mapping)"""
    return {
        "students": {
            "moodle_endpoint": "core_user_create_users",
            "mapping": {
                "username": "username (immutable after creation)",
                "academic_email": "email",
                "display_name": "fullname",
                "first_name": "firstname",
                "last_name": "lastname"
            },
            "constraints": {
                "username": "Must be unique, cannot be changed after creation",
                "email": "Must be valid email format"
            }
        },
        "note": "This is read-only metadata showing how canonical fields map to Moodle API. Actual transformation happens in backend adapters."
    }
