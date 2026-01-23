"""
Debug Endpoint - لتسجيل raw Zoho webhook data

هذا الـ endpoint بستقبل أي data من Zoho بدون معالجة ويحفظها
للتحليل والفهم الأفضل للـ format
"""

import logging
import json
from datetime import datetime
from fastapi import APIRouter, Request
from typing import Dict, Any

logger = logging.getLogger(__name__)

router = APIRouter(
    prefix="/debug",
    tags=["debug"]
)

# Store for raw data (memory only - for testing)
RECEIVED_DATA = {
    "products": [],
    "classes": [],
    "enrollments": [],
    "students": [],
    "other": []
}


@router.post("/webhook/zoho")
async def debug_zoho_webhook(request: Request) -> Dict[str, Any]:
    """
    استقبل أي data من Zoho وسجلها كما هي
    
    POST /v1/debug/webhook/zoho
    Content-Type: application/json
    
    سيتم تسجيل:
    - Headers
    - Body (raw JSON)
    - الوقت
    - نوع الـ data
    """
    try:
        # اقرأ الـ body الخام
        body = await request.json()
        
        # حاول حدد النوع
        data_type = _detect_type(body)
        
        # سجل الـ data
        record = {
            "timestamp": datetime.now().isoformat(),
            "headers": dict(request.headers),
            "body": body,
            "type": data_type
        }
        
        RECEIVED_DATA[data_type].append(record)
        
        # طبع في الـ log
        logger.info(f"🔍 DEBUG: Received {data_type} webhook")
        logger.info(f"📋 Data:\n{json.dumps(body, indent=2, default=str)}")
        
        return {
            "status": "received",
            "type": data_type,
            "message": f"✅ تم استقبال {data_type} webhook",
            "timestamp": record["timestamp"],
            "records_count": {
                "products": len(RECEIVED_DATA["products"]),
                "classes": len(RECEIVED_DATA["classes"]),
                "enrollments": len(RECEIVED_DATA["enrollments"]),
                "students": len(RECEIVED_DATA["students"]),
                "other": len(RECEIVED_DATA["other"])
            }
        }
    
    except Exception as e:
        logger.error(f"❌ Debug webhook error: {e}", exc_info=True)
        return {
            "status": "error",
            "error": str(e)
        }


@router.get("/data")
def get_collected_data() -> Dict[str, Any]:
    """
    احصل على كل الـ data اللي تم استقبالها
    
    GET /v1/debug/data
    """
    return {
        "total_records": sum(len(v) for v in RECEIVED_DATA.values()),
        "data": RECEIVED_DATA
    }


@router.get("/data/{data_type}")
def get_data_by_type(data_type: str) -> Dict[str, Any]:
    """
    احصل على data نوع معين
    
    GET /v1/debug/data/products
    GET /v1/debug/data/classes
    GET /v1/debug/data/enrollments
    GET /v1/debug/data/students
    """
    if data_type not in RECEIVED_DATA:
        return {"error": f"Unknown type: {data_type}"}
    
    records = RECEIVED_DATA[data_type]
    return {
        "type": data_type,
        "count": len(records),
        "records": records
    }


@router.get("/data/{data_type}/latest")
def get_latest_data(data_type: str, count: int = 1) -> Dict[str, Any]:
    """
    احصل على آخر N records من نوع معين
    
    GET /v1/debug/data/products/latest?count=1
    """
    if data_type not in RECEIVED_DATA:
        return {"error": f"Unknown type: {data_type}"}
    
    records = RECEIVED_DATA[data_type][-count:]
    return {
        "type": data_type,
        "count": len(records),
        "records": records
    }


@router.delete("/data")
def clear_collected_data() -> Dict[str, str]:
    """
    امسح كل الـ data المجمعة
    
    DELETE /v1/debug/data
    """
    global RECEIVED_DATA
    RECEIVED_DATA = {
        "products": [],
        "classes": [],
        "enrollments": [],
        "students": [],
        "other": []
    }
    return {"status": "cleared"}


@router.delete("/data/{data_type}")
def clear_data_type(data_type: str) -> Dict[str, Any]:
    """
    امسح data نوع معين
    
    DELETE /v1/debug/data/products
    """
    if data_type not in RECEIVED_DATA:
        return {"error": f"Unknown type: {data_type}"}
    
    count = len(RECEIVED_DATA[data_type])
    RECEIVED_DATA[data_type] = []
    
    return {
        "status": "cleared",
        "type": data_type,
        "deleted_count": count
    }


@router.post("/format-analysis")
def analyze_format() -> Dict[str, Any]:
    """
    حلل الـ format اللي استقبلناه
    
    POST /v1/debug/format-analysis
    """
    analysis = {}
    
    for data_type, records in RECEIVED_DATA.items():
        if not records:
            analysis[data_type] = {
                "count": 0,
                "fields": [],
                "sample": None
            }
            continue
        
        # احصل على آخر record
        latest = records[-1]["body"]
        
        # حاول استخرج الـ fields
        if isinstance(latest, dict):
            if "data" in latest:
                fields = list(latest["data"][0].keys()) if latest["data"] else []
            else:
                fields = list(latest.keys())
        else:
            fields = []
        
        analysis[data_type] = {
            "count": len(records),
            "fields": fields,
            "sample": latest
        }
    
    return {
        "timestamp": datetime.now().isoformat(),
        "analysis": analysis,
        "summary": f"تم استقبال {sum(len(v) for v in RECEIVED_DATA.values())} records"
    }


def _detect_type(data: Any) -> str:
    """
    حاول حدد نوع الـ data
    """
    if not isinstance(data, dict):
        return "other"
    
    # شيك على الـ keys والـ source
    keys = data.keys()
    body_data = data.get("data", [])
    source = data.get("source", "").lower()
    module = data.get("module", "").lower()
    
    # تحقق من الـ source والـ module أولاً
    if "student" in source or "contact" in source or "btec_student" in module:
        return "students"
    if "product" in source:
        return "products"
    if "class" in source or "btec_class" in module:
        return "classes"
    if "enroll" in source:
        return "enrollments"
    
    # إذا ما فيش source، حاول من خلال الـ fields
    if body_data and isinstance(body_data, list) and body_data:
        first_record = body_data[0]
        
        # Products
        if "Product_Name" in first_record or "price" in first_record.keys():
            return "products"
        
        # Classes
        if "BTEC_Class_Name" in first_record or "Class_Name" in first_record:
            return "classes"
        
        # Enrollments
        if "Student" in first_record or "BTEC_Class" in first_record:
            return "enrollments"
        
        # Students - شيك على multiple field names
        student_fields = [
            "email", "Email", "Academic_Email", "contact", "Contact",
            "Name", "First_Name", "Last_Name", "Phone_Number", "Phone"
        ]
        if any(field in first_record for field in student_fields):
            # تأكد أنه مش enrollment
            if "Student" not in first_record and "BTEC_Class" not in first_record:
                return "students"
    
    return "other"
