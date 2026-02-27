"""
Shared helpers for the Zoho → Middleware → Moodle webhook pipeline.

Exports (used by all three webhook modules + full_sync.py):
  ZOHO_MODULE_MAP            – entity_type → Zoho CRM module name
  FIELD_MAPPINGS             – Locked Zoho api_name → Moodle column mappings
  extract_zoho_record()      – Normalize 'data' array wrapper
  fetch_zoho_full_record()   – Fetch a full record from Zoho CRM API
  resolve_zoho_payload()     – Resolve Zoho notification → full record dict
  transform_zoho_to_moodle() – Map Zoho fields to Moodle DB column names
  call_moodle_ws()           – Call Moodle Web Service REST API (dual-token)
  read_zoho_body()           – Parse Zoho notification body (JSON or form-encoded)
  ensure_registration_synced() – Auto-sync missing parent registration
"""
import json
import logging
import httpx
from datetime import datetime
from typing import Dict, Any, Optional

from fastapi import HTTPException, Request
from app.core.config import settings

logger = logging.getLogger(__name__)


# ===========================================================================
# PAYLOAD HELPERS
# ===========================================================================

def extract_zoho_record(payload: Dict) -> Dict:
    """
    Normalize incoming Zoho notification payload.

    Zoho wraps the actual record(s) in a 'data' array:
        { "channel_id": "...", "event": "BTEC_Students.edit",
          "data": [{"id": "...", "First_Name": "...", ...}] }

    For backward compatibility with direct/test payloads (no 'data' wrapper)
    the raw payload is returned as-is.
    """
    data = payload.get("data")
    if isinstance(data, list) and data:
        record = data[0]
        logger.debug("Extracted record from Zoho 'data' array: id=%s", record.get("id"))
        return record
    return payload


# Zoho module names for API fetch fallback
ZOHO_MODULE_MAP = {
    "teachers":      "BTEC_Teachers",       # must sync before Classes
    "students":      "BTEC_Students",
    "registrations": "BTEC_Registrations",
    "payments":      "BTEC_Payments",
    "classes":       "BTEC_Classes",
    "enrollments":   "BTEC_Enrollments",
    "grades":        "BTEC_Grades",
    "requests":      "BTEC_Student_Requests",
    "btec_units":    "BTEC",                # BTEC Units — api_name is "BTEC" in this org
}


async def fetch_zoho_full_record(module: str, record_id: str) -> Dict:
    """
    Fetch a full record from Zoho CRM API when the notification only contained
    the record ID.  Called when return_affected_field_values=false on the channel.
    """
    try:
        from app.infra.zoho.auth import ZohoAuthClient
        auth = ZohoAuthClient(
            client_id=settings.ZOHO_CLIENT_ID,
            client_secret=settings.ZOHO_CLIENT_SECRET,
            refresh_token=settings.ZOHO_REFRESH_TOKEN,
            region=settings.ZOHO_REGION,
        )
        token = await auth.get_access_token()
        url = f"https://www.zohoapis.com/crm/v2/{module}/{record_id}"
        async with httpx.AsyncClient(timeout=30.0) as client:
            resp = await client.get(url, headers={"Authorization": f"Zoho-oauthtoken {token}"})
        if resp.status_code == 200:
            data = resp.json().get("data", [])
            if data:
                logger.info(f"✅ Fetched full record from Zoho: {module}/{record_id}")
                return data[0]
        logger.warning(f"⚠️ Could not fetch {module}/{record_id} from Zoho: {resp.status_code}")
    except Exception as e:
        logger.warning(f"⚠️ fetch_zoho_full_record failed for {module}/{record_id}: {e}")
    return {}


async def resolve_zoho_payload(raw: Dict, entity_type: str) -> Dict:
    """
    Extract record ID from Zoho notification and fetch the full record.

    Zoho substitutes ${!ModuleName.Id} in raw_data_content (JSON body) at send time,
    so the body arrives as: {"zoho_id": "ACTUAL_ID", "module": "ModuleName"}.
    We read zoho_id from there and fetch the full record via the CRM API.
    """
    module = raw.get("module") or ZOHO_MODULE_MAP.get(entity_type, "")

    # Priority 1: zoho_id from body — Zoho substitutes ${!Module.Id} in raw_data_content
    body_zoho_id = raw.get("zoho_id", "")
    if body_zoho_id and not str(body_zoho_id).startswith("$"):
        logger.info(f"📡 Zoho workflow webhook: fetching {module}/{body_zoho_id} (from body zoho_id)")
        full = await fetch_zoho_full_record(module, body_zoho_id)
        if full:
            return full
        logger.warning(f"⚠️ Could not fetch full record — returning minimal dict with id only")
        return {"id": body_zoho_id}
    elif body_zoho_id.startswith("$"):
        logger.error(
            f"❌ Zoho sent unsubstituted template '{body_zoho_id}' in body zoho_id. "
            f"This means ${'{'}!{module}.Id{'}'} was NOT substituted. "
            f"Run /api/v1/admin/setup-zoho-automations to rebuild webhooks."
        )
        return {"id": ""}

    # Priority 2: Legacy URL query param (kept for backward compat / manual tests)
    url_zoho_id = raw.get("_url_zoho_id", "")
    if url_zoho_id and not url_zoho_id.startswith("$"):
        logger.info(f"📡 Fallback: fetching {module}/{url_zoho_id} (from URL param)")
        full = await fetch_zoho_full_record(module, url_zoho_id)
        if full:
            return full
        return {"id": url_zoho_id}

    # Priority 3: Legacy ids[] array (Notification Channel / manual tests)
    ids = raw.get("ids")
    if isinstance(ids, list) and ids:
        record_id = ids[0]
        if not str(record_id).startswith("$"):
            logger.info(f"📡 Fallback: fetching full record {module}/{record_id} (from body ids[])")
            full = await fetch_zoho_full_record(module, record_id)
            if full:
                return full
            return {"id": record_id}

    # Priority 4: Zoho "data" array wrapper (return_affected_field_values=true)
    data = raw.get("data")
    if isinstance(data, list) and data:
        record = data[0]
        if len(record) <= 2 and record.get("id"):
            full = await fetch_zoho_full_record(module, record["id"])
            if full:
                return full
        return record

    # Priority 5: Direct record (manual tests / no wrapper)
    return raw


# ===========================================================================
# LOCKED FIELD MAPPINGS
# Source of truth: backend/zoho_api_names.json
# Rule: Zoho api_name (left) → Moodle DB column (right)
# Lookup fields: extract .id for FK references, .name for display fields
# ===========================================================================

FIELD_MAPPINGS: Dict[str, Dict] = {

    # BTEC_Teachers → local_mzi_teachers
    # Synced first — Classes reference teachers by zoho_teacher_id
    "teachers": {
        "id":                ("zoho_teacher_id",    "value"),
        "Name":              ("teacher_name",       "value"),
        "Email":             ("email",              "value"),
        "Academic_Email":    ("academic_email",     "value"),
        "Phone_Number":      ("phone_number",       "value"),
        "Teacher_Moodle_ID": ("moodle_user_id",     "value"),
        "Created_Time":      ("zoho_created_time",  "value"),
        "Modified_Time":     ("zoho_modified_time", "value"),
    },

    # BTEC_Students → local_mzi_students
    "students": {
        "id":                       ("zoho_student_id",        "value"),
        "Name":                     ("student_id",             "value"),
        "First_Name":               ("first_name",             "value"),
        "Last_Name":                ("last_name",              "value"),
        "Display_Name":             ("display_name",           "value"),
        "Academic_Email":           ("email",                  "value"),
        "Phone_Number":             ("phone_number",           "value"),
        "Address":                  ("address",                "value"),
        "City":                     ("city",                   "value"),
        "Nationality":              ("nationality",            "value"),
        "Birth_Date":               ("date_of_birth",          "value"),
        "Gender":                   ("gender",                 "value"),
        "Emergency_Contact_Name":   ("emergency_contact_name", "value"),
        "Emergency_Phone_Number":   ("emergency_contact_phone","value"),
        "Status":                   ("status",                 "value"),
        "Student_Moodle_ID":        ("moodle_user_id",         "value"),
        "National_Number":          ("national_id",            "value"),
        "Major":                    ("major",                  "value"),
        "Sub_Major":                ("sub_major",              "value"),
        "Created_Time":             ("zoho_created_time",      "value"),
        "Modified_Time":            ("zoho_modified_time",     "value"),
    },

    # BTEC_Registrations → local_mzi_registrations
    "registrations": {
        "id":                       ("zoho_registration_id",    "value"),
        # Note: "Name" is the auto-name field (e.g. REG-00012), NOT a student lookup — excluded intentionally
        "Student_ID":               ("zoho_student_id",         "lookup_id"),
        "Program":                  ("program_name",            "lookup_name"),
        "Program_Name":             ("program_name",            "value"),
        "Registration_Number":      ("registration_number",     "value"),
        "Registration_Date":        ("registration_date",       "value"),
        "Registration_Status":      ("registration_status",     "value"),
        "Status":                   ("registration_status",     "value"),
        # Program_Price is the original price; Total_Fees may include discounts.
        # Both map to total_fees — whichever is non-null wins (handled post-transform).
        "Program_Price":            ("total_fees",              "value"),
        "Total_Fees":               ("total_fees",              "value"),
        "Paid_Amount":              ("paid_amount",             "value"),
        "Remaining_Amount":         ("remaining_amount",        "value"),
        "Currency":                 ("currency",                "value"),
        "Currency_Symbol":          ("currency",                "value"),
        # Payment_Plan: field does NOT exist in BTEC_Registrations Zoho module — excluded intentionally
        "Study_Mode":               ("study_mode",              "value"),
        "Expected_Graduation":      ("expected_graduation",     "value"),
        "Number_of_Installments":   ("number_of_installments",  "value"),
        "Program_Level":            ("program_level",           "value"),
        "Created_Time":             ("zoho_created_time",       "value"),
        "Modified_Time":            ("zoho_modified_time",      "value"),
    },

    # BTEC_Payments → local_mzi_payments
    "payments": {
        "id":                       ("zoho_payment_id",       "value"),
        "Name":                     ("payment_number",        "value"),   # Zoho auto-name e.g. PAY-00012
        "Registration_ID":          ("zoho_registration_id",  "lookup_id"),
        "Student_ID":               ("zoho_student_id",       "lookup_id"),
        "Payment_Amount":           ("payment_amount",        "value"),
        "Payment_Date":             ("payment_date",          "value"),
        "Payment_Method":           ("payment_method",        "value"),
        "Payment_Status":           ("payment_status",        "value"),   # Confirmed/Pending/Voided
        "Voucher_Number":           ("voucher_number",        "value"),
        "Receipt_Number":           ("receipt_number",        "value"),
        "Bank_Name":                ("bank_name",             "value"),
        "Note":                     ("payment_notes",         "value"),
        "Created_Time":             ("zoho_created_time",     "value"),
        "Modified_Time":            ("zoho_modified_time",    "value"),
    },

    # BTEC_Classes → local_mzi_classes
    "classes": {
        "id":               ("zoho_class_id",    "value"),
        "Class_Name":       ("class_name",       "value"),
        "Class_Short_Name": ("class_short_name", "value"),
        "BTEC_Program":     [("program_zoho_id", "lookup_id"),
                             ("program_name",    "lookup_name")],
        "Unit":             [("unit_zoho_id",    "lookup_id"),
                             ("unit_name",       "lookup_name")],
        "Teacher":          [("teacher_zoho_id", "lookup_id"),
                             ("teacher_name",    "lookup_name")],
        "Moodle_Class_ID":  ("moodle_class_id",  "value"),
        "Class_Status":     ("class_status",     "value"),
        "Class_Major":      ("class_major",       "value"),
        "Start_Date":       ("start_date",        "value"),
        "End_Date":         ("end_date",          "value"),
        "Created_Time":     ("zoho_created_time", "value"),
        "Modified_Time":    ("zoho_modified_time","value"),
    },

    # BTEC_Enrollments → local_mzi_enrollments
    "enrollments": {
        "id":                ("zoho_enrollment_id",  "value"),
        "Enrolled_Students": ("zoho_student_id",     "lookup_id"),
        "Classes":           ("zoho_class_id",       "lookup_id"),
        "Start_Date":        ("enrollment_date",     "value"),
        "End_Date":          ("end_date",            "value"),
        "Enrollment_Type":   ("enrollment_type",     "value"),
        "Student_Name":      ("student_name",        "value"),
        "Class_Name":        ("class_name",          "value"),
        "Enrolled_Program":  ("enrolled_program",    "value"),
        "Moodle_Course_ID":  ("moodle_course_id",    "value"),
        "Synced_to_Moodle":  ("synced_to_moodle",    "value"),
        "Enrollment_Status": ("enrollment_status",   "value"),
        "Created_Time":      ("zoho_created_time",   "value"),
        "Modified_Time":     ("zoho_modified_time",  "value"),
    },

    # BTEC_Grades → local_mzi_grades
    "grades": {
        "id":                       ("zoho_grade_id",     "value"),
        "Student":                  ("zoho_student_id",   "lookup_id"),
        "Class":                    ("zoho_class_id",     "lookup_id"),
        "BTEC_Unit":                ("unit_name",         "lookup_name"),
        "Assignment_Name":          ("assignment_name",   "value"),
        "BTEC_Grade_Name":          ("btec_grade_name",   "value"),
        "Grade":                    ("numeric_grade",     "value"),
        "Attempt_Number":           ("attempt_number",    "value"),
        "Feedback":                 ("feedback",          "value"),
        "Grade_Status":             ("grade_status",      "value"),
        "Attempt_Date":             ("grade_date",        "value"),
        "Learning_Outcomes_Assessm": ("learning_outcomes",  "json"),
        "Created_Time":             ("zoho_created_time", "value"),
        "Modified_Time":            ("zoho_modified_time","value"),
    },

    # BTEC_Student_Requests → local_mzi_requests
    "requests": {
        "id":                       ("zoho_request_id",   "value"),
        "Student":                  ("zoho_student_id",   "lookup_id"),
        "Request_Type":             ("request_type",      "value"),
        "Status":                   ("request_status",    "value"),
        "Reason":                   ("description",       "value"),
        "Request_Date":             ("request_date",      "date_only"),
        "Created_Time":             ("zoho_created_time", "value"),
        "Modified_Time":            ("zoho_modified_time","value"),
    },
}


def _apply_extract(value: Any, moodle_field: str, extract: str, out: Dict) -> None:
    """Apply a single (moodle_field, extract) rule onto the output dict."""
    if extract == "lookup_id":
        out[moodle_field] = value.get("id") if isinstance(value, dict) else value
    elif extract == "lookup_name":
        out[moodle_field] = value.get("name") if isinstance(value, dict) else value
    elif extract == "date_only":
        out[moodle_field] = str(value)[:10] if value else None
    elif extract == "json":
        # Serialize subform arrays / dicts to a JSON string for TEXT columns
        out[moodle_field] = json.dumps(value, ensure_ascii=False) if value else None
    else:  # "value"
        out[moodle_field] = value


def transform_zoho_to_moodle(data: Dict, entity_type: str) -> Dict:
    """
    Transform Zoho CRM webhook payload to Moodle DB field names.

    Tries DB field_mappings table first (populated by Setup Wizard).
    Falls back to hardcoded FIELD_MAPPINGS if DB is empty or unavailable.

    Each mapping value can be:
      - a single tuple  (moodle_field, extract)
      - a list of tuples [(moodle_field, extract), ...]   ← multi-target (hardcoded only)

    extract modes:
      'value'       → copy as-is
      'lookup_id'   → extract the 'id' key from the Zoho lookup dict
      'lookup_name' → extract the 'name' key from the Zoho lookup dict
      'date_only'   → keep only YYYY-MM-DD portion
    """
    # ── Try DB mappings first ──────────────────────────────────────
    mapping = None
    try:
        from app.infra.db.base import engine
        from sqlalchemy.orm import Session
        from app.infra.db.mapping_loader import get_field_mappings
        with Session(engine) as session:
            all_mappings = get_field_mappings(session, fallback=False)
        mapping = all_mappings.get(entity_type)
    except Exception as exc:
        logger.debug("transform_zoho_to_moodle: DB lookup failed (%s), using hardcoded", exc)

    # ── Fall back to hardcoded ─────────────────────────────────────
    if not mapping:
        mapping = FIELD_MAPPINGS.get(entity_type)

    if not mapping:
        logger.warning(f"No mapping defined for entity_type='{entity_type}', returning raw payload")
        return data

    transformed: Dict[str, Any] = {}

    for zoho_field, targets in mapping.items():
        value = data.get(zoho_field)
        if value is None:
            continue

        if isinstance(targets, list):
            for (moodle_field, extract) in targets:
                _apply_extract(value, moodle_field, extract, transformed)
        else:
            moodle_field, extract = targets
            _apply_extract(value, moodle_field, extract, transformed)

    return transformed


# ===========================================================================
# MOODLE WEB SERVICE CALLER
# ===========================================================================

async def call_moodle_ws(
    wsfunction: str,
    params: Dict[str, Any],
) -> Dict:
    """
    Call Moodle Web Service REST API using MOODLE_TOKEN.

    A single unified Moodle service contains all functions:
    local_mzi_*, core_course_*, enrol_manual_*, etc.

    Raises HTTPException on communication failure or Moodle-level error.
    """
    if not settings.MOODLE_ENABLED:
        raise HTTPException(status_code=503, detail="Moodle API is disabled")

    if not settings.MOODLE_BASE_URL:
        raise HTTPException(status_code=503, detail="Moodle API not configured")

    token = settings.MOODLE_TOKEN
    if not token:
        raise HTTPException(status_code=503, detail="MOODLE_TOKEN is not set")

    url = f"{settings.MOODLE_BASE_URL}/webservice/rest/server.php"
    data = {
        "wstoken": token,
        "wsfunction": wsfunction,
        "moodlewsrestformat": "json",
        **params,
    }

    async with httpx.AsyncClient(timeout=30.0) as client:
        try:
            response = await client.post(url, data=data)
            response.raise_for_status()
            result = response.json()

            if isinstance(result, dict) and "exception" in result:
                logger.error(f"Moodle WS error [{wsfunction}]: {result}")
                raise HTTPException(status_code=500, detail=result.get("message", "Moodle WS error"))

            return result
        except httpx.HTTPError as e:
            logger.error(f"HTTP error calling Moodle WS [{wsfunction}]: {e}")
            raise HTTPException(status_code=502, detail=f"Moodle API communication error: {str(e)}")


# ===========================================================================
# REQUEST BODY PARSER
# ===========================================================================

async def read_zoho_body(request: Request) -> Dict:
    """
    Read and parse Zoho notification body.
    Handles both JSON and form-urlencoded formats.

    Zoho substitutes ${!ModuleName.Id} inside raw_data_content (JSON body) at
    send time, so the body arrives as: {"zoho_id": "ACTUAL_ID", "module": "..."}
    (POST webhooks do not support URL parameters per Zoho API docs).

    Also injects _url_zoho_id from any ?zoho_id= query param for backward
    compatibility with manually-tested or legacy requests.
    """
    from urllib.parse import parse_qs
    body_bytes = await request.body()
    body_str = body_bytes.decode("utf-8")
    logger.info(f"🔍 RAW Zoho body [{request.url.path}]: {body_str[:2000]}")
    content_type = request.headers.get("content-type", "")
    if "application/json" in content_type or body_str.lstrip().startswith("{"):
        try:
            raw = json.loads(body_str)
        except json.JSONDecodeError:
            raw = {}
    else:
        # form-urlencoded fallback
        parsed = parse_qs(body_str, keep_blank_values=True)
        raw = {k: v[0] if len(v) == 1 else v for k, v in parsed.items()}

    # Log zoho_id from body for debugging
    if raw.get("zoho_id"):
        logger.info(f"🔑 zoho_id from body: {raw['zoho_id']}")
    else:
        logger.warning(f"⚠️ No zoho_id in body — Zoho may not have substituted ${{!Module.Id}}. "
                       f"Run /api/v1/admin/setup-zoho-automations to rebuild webhooks.")

    # Also capture URL query param for backward compat / manual tests
    url_zoho_id = request.query_params.get("zoho_id", "")
    if url_zoho_id:
        raw["_url_zoho_id"] = url_zoho_id
        logger.info(f"🔑 zoho_id from URL param (legacy/manual): {url_zoho_id}")
    return raw


# ===========================================================================
# REGISTRATION AUTO-SYNC
# ===========================================================================

async def ensure_registration_synced(zoho_registration_id: str) -> None:
    """
    Auto-sync a BTEC_Registrations record from Zoho into Moodle if it is missing.
    Called automatically when a payment arrives before its parent registration.
    """
    await resync_registration_with_installments(zoho_registration_id)


async def resync_registration_with_installments(zoho_registration_id: str) -> None:
    """
    Re-fetch a BTEC_Registrations record from Zoho and upsert it into Moodle,
    including refreshing the Payment_Schedule subform (installments).

    Called:
    - After a payment is recorded, to pull fresh Paid_Amount / Remaining_Amount
      and updated installment statuses (Pending → Paid).
    - When auto-syncing a missing parent registration.
    """
    logger.info(f"🔄 Re-syncing registration {zoho_registration_id} from Zoho (incl. installments)...")
    record = await fetch_zoho_full_record("BTEC_Registrations", zoho_registration_id)
    if not record:
        raise ValueError(f"Cannot fetch registration {zoho_registration_id} from Zoho CRM")

    # ── 1. Upsert registration row ──────────────────────────────────────────
    transformed = transform_zoho_to_moodle(record, "registrations")
    await call_moodle_ws("local_mzi_create_registration", {"registrationdata": json.dumps(transformed)})

    # ── 2. Refresh installments (Payment_Schedule subform) ──────────────────
    payment_schedule = record.get("Payment_Schedule") or []
    if payment_schedule:
        installments = [
            {
                "installment_number": row.get("Installment_No") or idx,
                "due_date":           str(row.get("Due_Date")           or ""),
                "amount":             row.get("Installment_Amount")    or 0,
                "status":             str(row.get("Installment_Status") or "Pending"),
                "paid_date":          str(row.get("Paid_Date")          or ""),
            }
            for idx, row in enumerate(payment_schedule, start=1)
        ]
        try:
            inst_result = await call_moodle_ws(
                "local_mzi_sync_installments",
                {
                    "zoho_registration_id": zoho_registration_id,
                    "installmentsdata":     json.dumps(installments),
                },
            )
            logger.info(
                f"✅ Re-synced registration {zoho_registration_id}: "
                f"{inst_result.get('count', len(installments))} installments refreshed"
            )
        except Exception as err:
            logger.warning(f"⚠️  Installment refresh skipped for {zoho_registration_id}: {err}")
    else:
        logger.info(f"ℹ️  No Payment_Schedule subform for {zoho_registration_id} — installments unchanged")
