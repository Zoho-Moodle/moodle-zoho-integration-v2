"""
Enhanced Debug Endpoint - معالج متقدم للبيانات الضخمة من Zoho

يتعامل مع:
- البيانات الضخمة (الآلاف من السجلات)
- تحليل شامل للحقول والأنواع
- إحصائيات مفصلة
- تصفية وبحث متقدم
"""

import logging
import json
from datetime import datetime
from fastapi import APIRouter, Request, Query, HTTPException
from typing import Dict, Any, List, Optional
from collections import defaultdict
import hashlib

logger = logging.getLogger(__name__)

router = APIRouter(
    prefix="/debug",
    tags=["debug"]
)

# ============================================================================
# Storage Structure - محسّن للبيانات الضخمة
# ============================================================================

class ModuleData:
    """هيكل تخزين البيانات لكل موديول"""
    def __init__(self, module_name: str):
        self.module = module_name
        self.records: List[Dict] = []
        self.record_count = 0
        self.fields_summary = {}
        self.first_record_timestamp = None
        self.last_record_timestamp = datetime.now()
        self.error = None
        self.status = "pending"

# Storage
DEBUG_MODULES = {}  # {module_name: ModuleData}
STATISTICS = {
    "total_records": 0,
    "total_modules": 0,
    "last_update": None,
    "modules_summary": {}
}


# ============================================================================
# Helper Functions
# ============================================================================

def _get_field_type(value: Any) -> str:
    """تحديد نوع الحقل من القيمة"""
    if value is None:
        return "null"
    
    if isinstance(value, bool):
        return "boolean"
    elif isinstance(value, int):
        return "integer"
    elif isinstance(value, float):
        return "decimal"
    elif isinstance(value, str):
        # محاولة تحديد النوع من المحتوى
        if "@" in value:
            return "email"
        elif value.isdigit():
            return "numeric_string"
        elif value.lower() in ["true", "false"]:
            return "boolean_string"
        else:
            return "text"
    elif isinstance(value, list):
        if value and isinstance(value[0], dict):
            return "lookup_list"
        return "list"
    elif isinstance(value, dict):
        return "object"
    
    return "unknown"


def _analyze_fields(records: List[Dict]) -> Dict[str, Dict[str, Any]]:
    """تحليل شامل للحقول في السجلات"""
    fields_info = {}
    
    for record in records:
        for field_name, field_value in record.items():
            if field_name not in fields_info:
                fields_info[field_name] = {
                    "name": field_name,
                    "type": _get_field_type(field_value),
                    "count": 0,
                    "null_count": 0,
                    "sample_values": [],
                    "types_seen": set()
                }
            
            info = fields_info[field_name]
            info["count"] += 1
            info["types_seen"].add(_get_field_type(field_value))
            
            if field_value is None:
                info["null_count"] += 1
            elif len(info["sample_values"]) < 3:
                # خذ عينات من القيم
                if isinstance(field_value, (dict, list)):
                    info["sample_values"].append(str(field_value)[:100])
                else:
                    info["sample_values"].append(str(field_value))
    
    # تنسيق النتيجة
    for field_name, info in fields_info.items():
        info["types_seen"] = list(info["types_seen"])
        info["null_percentage"] = (info["null_count"] / info["count"] * 100) if info["count"] > 0 else 0
    
    return fields_info


def _detect_module_type(data: Any) -> str:
    """كشف نوع الموديول من البيانات"""
    if not isinstance(data, dict):
        return "unknown"
    
    # فحص حقول خاصة بالموديول
    module_field = data.get("module", "").lower()
    source_field = data.get("source", "").lower()
    
    # حسب اسم الموديول
    if "contact" in module_field:
        return "contacts"
    elif "student" in module_field or "btec_student" in module_field:
        return "students"
    elif "product" in module_field:
        return "products"
    elif "class" in module_field or "btec_class" in module_field:
        return "classes"
    elif "enrollment" in module_field or "btec_enrollment" in module_field:
        return "enrollments"
    elif "registration" in module_field or "btec_registration" in module_field:
        return "registrations"
    elif "payment" in module_field or "btec_payment" in module_field:
        return "payments"
    elif "grade" in module_field or "btec_grade" in module_field:
        return "grades"
    elif "unit" in module_field or "btec_unit" in module_field:
        return "units"
    
    return "other"


# ============================================================================
# Endpoints
# ============================================================================

@router.post("/webhook/zoho")
async def debug_zoho_webhook(request: Request) -> Dict[str, Any]:
    """
    استقبل البيانات من Zoho بشكل خام
    
    POST /v1/debug/webhook/zoho
    Content-Type: application/json
    
    الرد:
    {
        "status": "received",
        "module": "contacts",
        "records_received": 1378,
        "timestamp": "2026-01-21T10:30:00"
    }
    """
    try:
        body = await request.json()
        
        # كشف نوع الموديول
        module_name = body.get("module", "unknown")
        module_type = _detect_module_type(body)
        
        # استخراج السجلات
        records = body.get("records", [])
        if not records:
            records = body.get("data", [])
        
        # إنشاء/تحديث ModuleData
        if module_name not in DEBUG_MODULES:
            DEBUG_MODULES[module_name] = ModuleData(module_name)
        
        module_data = DEBUG_MODULES[module_name]
        module_data.records = records
        module_data.record_count = len(records)
        module_data.status = "received"
        module_data.last_record_timestamp = datetime.now()
        
        if not module_data.first_record_timestamp:
            module_data.first_record_timestamp = datetime.now()
        
        # تحليل الحقول
        if records:
            module_data.fields_summary = _analyze_fields(records)
        
        # تحديث الإحصائيات
        STATISTICS["last_update"] = datetime.now().isoformat()
        STATISTICS["total_records"] = sum(m.record_count for m in DEBUG_MODULES.values())
        STATISTICS["total_modules"] = len(DEBUG_MODULES)
        
        # إنشاء ملخص للموديول
        STATISTICS["modules_summary"][module_name] = {
            "type": module_type,
            "records": module_data.record_count,
            "fields": len(module_data.fields_summary),
            "timestamp": module_data.last_record_timestamp.isoformat()
        }
        
        logger.info(f"✅ تم استقبال {module_name}: {module_data.record_count} سجلات")
        
        return {
            "status": "received",
            "module": module_name,
            "type": module_type,
            "records_received": module_data.record_count,
            "timestamp": datetime.now().isoformat()
        }
        
    except Exception as e:
        logger.error(f"❌ خطأ في استقبال البيانات: {str(e)}")
        return {
            "status": "error",
            "error": str(e),
            "timestamp": datetime.now().isoformat()
        }


@router.get("/stats")
async def get_statistics() -> Dict[str, Any]:
    """
    احصائيات عامة عن البيانات المستقبلة
    
    GET /v1/debug/stats
    
    الرد:
    {
        "total_records": 12185,
        "total_modules": 8,
        "last_update": "2026-01-21T10:30:00",
        "modules_summary": {...}
    }
    """
    return {
        "total_records": STATISTICS["total_records"],
        "total_modules": STATISTICS["total_modules"],
        "last_update": STATISTICS["last_update"],
        "modules": [
            {
                "name": name,
                "records": data["records"],
                "fields": data["fields"],
                "timestamp": data["timestamp"]
            }
            for name, data in STATISTICS["modules_summary"].items()
        ]
    }


@router.get("/modules")
async def get_all_modules() -> List[Dict[str, Any]]:
    """
    قائمة بجميع الموديولات المستقبلة
    
    GET /v1/debug/modules
    """
    return [
        {
            "name": name,
            "type": _detect_module_type({"module": name}),
            "record_count": data.record_count,
            "field_count": len(data.fields_summary),
            "status": data.status,
            "timestamp": data.last_record_timestamp.isoformat()
        }
        for name, data in DEBUG_MODULES.items()
    ]


@router.get("/module/{module_name}")
async def get_module_details(
    module_name: str,
    limit: int = Query(10, ge=1, le=1000),
    offset: int = Query(0, ge=0)
) -> Dict[str, Any]:
    """
    تفاصيل كاملة عن موديول معين
    
    GET /v1/debug/module/BTEC_Enrollments?limit=100&offset=0
    
    تتضمن:
    - ملخص الموديول
    - تفاصيل الحقول
    - عينات من السجلات
    """
    if module_name not in DEBUG_MODULES:
        raise HTTPException(status_code=404, detail=f"Module {module_name} not found")
    
    module_data = DEBUG_MODULES[module_name]
    
    # استخراج عينات
    records_sample = module_data.records[offset:offset + limit]
    
    return {
        "module": module_name,
        "summary": {
            "total_records": module_data.record_count,
            "total_fields": len(module_data.fields_summary),
            "status": module_data.status,
            "received_at": module_data.last_record_timestamp.isoformat()
        },
        "fields": {
            name: {
                "name": info["name"],
                "type": info["type"],
                "types_seen": info["types_seen"],
                "coverage": f"{100 - info['null_percentage']:.1f}%",
                "null_percentage": f"{info['null_percentage']:.1f}%",
                "sample_values": info["sample_values"]
            }
            for name, info in module_data.fields_summary.items()
        },
        "records_sample": {
            "offset": offset,
            "limit": limit,
            "count": len(records_sample),
            "data": records_sample
        }
    }


@router.get("/module/{module_name}/fields")
async def get_module_fields(module_name: str) -> Dict[str, Any]:
    """
    قائمة الحقول في الموديول مع تفاصيلها
    
    GET /v1/debug/module/BTEC_Enrollments/fields
    """
    if module_name not in DEBUG_MODULES:
        raise HTTPException(status_code=404, detail=f"Module {module_name} not found")
    
    module_data = DEBUG_MODULES[module_name]
    
    return {
        "module": module_name,
        "total_fields": len(module_data.fields_summary),
        "fields": [
            {
                "name": name,
                "api_name": name,
                "type": info["type"],
                "types_observed": info["types_seen"],
                "coverage": 100 - info["null_percentage"],
                "null_percentage": info["null_percentage"],
                "example_values": info["sample_values"]
            }
            for name, info in module_data.fields_summary.items()
        ]
    }


@router.get("/module/{module_name}/sample")
async def get_module_sample(
    module_name: str,
    count: int = Query(5, ge=1, le=100)
) -> Dict[str, Any]:
    """
    عينات من السجلات في الموديول
    
    GET /v1/debug/module/BTEC_Enrollments/sample?count=10
    """
    if module_name not in DEBUG_MODULES:
        raise HTTPException(status_code=404, detail=f"Module {module_name} not found")
    
    module_data = DEBUG_MODULES[module_name]
    records_sample = module_data.records[:count]
    
    return {
        "module": module_name,
        "total_records": module_data.record_count,
        "sample_count": len(records_sample),
        "records": records_sample
    }


@router.get("/search")
async def search_records(
    module: Optional[str] = None,
    field: Optional[str] = None,
    value: Optional[str] = None,
    limit: int = Query(50, ge=1, le=1000)
) -> Dict[str, Any]:
    """
    البحث والتصفية في البيانات
    
    GET /v1/debug/search?module=BTEC_Enrollments&field=id&value=123
    """
    results = []
    
    for mod_name, module_data in DEBUG_MODULES.items():
        if module and module.lower() not in mod_name.lower():
            continue
        
        for record in module_data.records:
            if field and field not in record:
                continue
            
            if field and value:
                record_value = str(record.get(field, "")).lower()
                if value.lower() not in record_value:
                    continue
            
            results.append({
                "module": mod_name,
                "record": record
            })
            
            if len(results) >= limit:
                break
        
        if len(results) >= limit:
            break
    
    return {
        "query": {
            "module": module,
            "field": field,
            "value": value
        },
        "results_count": len(results),
        "results": results
    }


@router.get("/comparison")
async def compare_modules() -> Dict[str, Any]:
    """
    مقارنة بين جميع الموديولات
    
    GET /v1/debug/comparison
    """
    return {
        "timestamp": datetime.now().isoformat(),
        "modules": [
            {
                "name": name,
                "records": data.record_count,
                "fields": len(data.fields_summary),
                "status": data.status
            }
            for name, data in sorted(
                DEBUG_MODULES.items(),
                key=lambda x: x[1].record_count,
                reverse=True
            )
        ],
        "totals": {
            "total_records": STATISTICS["total_records"],
            "total_modules": STATISTICS["total_modules"],
            "total_fields": sum(len(m.fields_summary) for m in DEBUG_MODULES.values())
        }
    }


@router.delete("/clear/{module_name}")
async def clear_module(module_name: str) -> Dict[str, str]:
    """
    حذف بيانات موديول معين
    
    DELETE /v1/debug/clear/BTEC_Enrollments
    """
    if module_name not in DEBUG_MODULES:
        raise HTTPException(status_code=404, detail=f"Module {module_name} not found")
    
    del DEBUG_MODULES[module_name]
    STATISTICS["total_records"] = sum(m.record_count for m in DEBUG_MODULES.values())
    STATISTICS["total_modules"] = len(DEBUG_MODULES)
    
    return {"status": "cleared", "module": module_name}


@router.delete("/clear")
async def clear_all() -> Dict[str, str]:
    """
    حذف جميع البيانات
    
    DELETE /v1/debug/clear
    """
    global DEBUG_MODULES, STATISTICS
    
    DEBUG_MODULES = {}
    STATISTICS = {
        "total_records": 0,
        "total_modules": 0,
        "last_update": None,
        "modules_summary": {}
    }
    
    return {"status": "all_data_cleared"}


@router.get("/export/{module_name}")
async def export_module(module_name: str) -> Dict[str, Any]:
    """
    تصدير بيانات الموديول كاملة
    
    GET /v1/debug/export/BTEC_Enrollments
    """
    if module_name not in DEBUG_MODULES:
        raise HTTPException(status_code=404, detail=f"Module {module_name} not found")
    
    module_data = DEBUG_MODULES[module_name]
    
    return {
        "module": module_name,
        "export_timestamp": datetime.now().isoformat(),
        "record_count": module_data.record_count,
        "field_count": len(module_data.fields_summary),
        "records": module_data.records,
        "fields_schema": module_data.fields_summary
    }


# ============================================================================
# Health Check
# ============================================================================

@router.get("/health")
async def health_check() -> Dict[str, Any]:
    """
    فحص صحة النظام
    
    GET /v1/debug/health
    """
    return {
        "status": "ok",
        "timestamp": datetime.now().isoformat(),
        "modules_loaded": len(DEBUG_MODULES),
        "total_records": STATISTICS["total_records"],
        "last_update": STATISTICS["last_update"]
    }


# ============================================================================
# Test Endpoints - للاختبار والتطوير
# ============================================================================

@router.post("/test-load-sample-data")
async def load_sample_data() -> Dict[str, Any]:
    """
    تحميل بيانات تجريبية لـ testing
    
    POST /v1/debug/test-load-sample-data
    """
    global DEBUG_MODULES, STATISTICS
    
    # بيانات تجريبية
    sample_data = {
        "BTEC_Enrollments": {
            "records": [
                {
                    "id": "eno_001",
                    "Student": {"id": "stud_001", "name": "Ahmed Mohamed"},
                    "Class": {"id": "cls_001", "name": "BIS201"},
                    "Enrollment_Date": "2026-01-15",
                    "Status": "Active",
                    "Semester": "Spring 2026"
                },
                {
                    "id": "eno_002",
                    "Student": {"id": "stud_002", "name": "Fatima Ali"},
                    "Class": {"id": "cls_001", "name": "BIS201"},
                    "Enrollment_Date": "2026-01-16",
                    "Status": "Active",
                    "Semester": "Spring 2026"
                },
                {
                    "id": "eno_003",
                    "Student": {"id": "stud_003", "name": "Hassan Mohammed"},
                    "Class": {"id": "cls_002", "name": "BIS202"},
                    "Enrollment_Date": "2026-01-17",
                    "Status": "Pending",
                    "Semester": "Spring 2026"
                }
            ]
        },
        "BTEC_Classes": {
            "records": [
                {
                    "id": "cls_001",
                    "Name": "BIS201",
                    "Program": {"id": "prog_001", "name": "Business IT"},
                    "Semester": "Spring 2026",
                    "Capacity": 30
                },
                {
                    "id": "cls_002",
                    "Name": "BIS202",
                    "Program": {"id": "prog_001", "name": "Business IT"},
                    "Semester": "Spring 2026",
                    "Capacity": 25
                },
                {
                    "id": "cls_003",
                    "Name": "BIS301",
                    "Program": {"id": "prog_002", "name": "Software Engineering"},
                    "Semester": "Spring 2026",
                    "Capacity": 20
                }
            ]
        },
        "Products": {
            "records": [
                {
                    "id": "prod_001",
                    "Name": "Course Material",
                    "Price": 100.00,
                    "Status": "Active"
                },
                {
                    "id": "prod_002",
                    "Name": "Exam Fee",
                    "Price": 50.00,
                    "Status": "Active"
                },
                {
                    "id": "prod_003",
                    "Name": "Certificate",
                    "Price": 25.00,
                    "Status": "Active"
                }
            ]
        }
    }
    
    # حمّل البيانات
    for module_name, data in sample_data.items():
        records = data.get("records", [])
        
        if module_name not in DEBUG_MODULES:
            DEBUG_MODULES[module_name] = ModuleData(module_name)
        
        module_data = DEBUG_MODULES[module_name]
        module_data.records = records
        module_data.record_count = len(records)
        module_data.status = "loaded"
        module_data.last_record_timestamp = datetime.now()
        
        if records:
            module_data.fields_summary = _analyze_fields(records)
        
        STATISTICS["modules_summary"][module_name] = {
            "type": module_name.lower(),
            "records": module_data.record_count,
            "fields": len(module_data.fields_summary),
            "timestamp": module_data.last_record_timestamp.isoformat()
        }
    
    STATISTICS["last_update"] = datetime.now().isoformat()
    STATISTICS["total_records"] = sum(m.record_count for m in DEBUG_MODULES.values())
    STATISTICS["total_modules"] = len(DEBUG_MODULES)
    
    return {
        "status": "loaded",
        "modules": len(DEBUG_MODULES),
        "total_records": STATISTICS["total_records"],
        "message": "✅ بيانات تجريبية تم تحميلها بنجاح"
    }
