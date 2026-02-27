"""
Full Sync Endpoint
POST /api/v1/admin/full-sync        -> starts sync in background, returns job_id immediately
GET  /api/v1/admin/full-sync/status -> poll progress (by job_id or latest)

Sync order respects FK dependencies:
  1. Teachers       -> local_mzi_sync_teacher          (BTEC_Teachers; Classes reference teachers)
  2. Students       -> local_mzi_update_student
  3. Classes        -> core_course_create_courses + local_mzi_create_class
  4. Registrations  -> local_mzi_create_registration   (Student → Program)
  5. Enrollments    -> local_mzi_update_enrollment     (Student ⇔ Class; triggers Moodle enrolment)
  6. Payments       -> local_mzi_record_payment        (Registration → Payment)
  7. Grades         -> local_mzi_submit_grade          (Student + Class)
  8. Requests       -> local_mzi_update_request_status
"""

import asyncio
import json
import logging
import uuid
from datetime import datetime
from typing import Dict, List, Any, Optional, Set

import httpx
from fastapi import APIRouter, Query
from pydantic import BaseModel

from fastapi import Body
from app.core.config import settings
from app.api.v1.endpoints.student_dashboard_webhooks import (
    ZOHO_MODULE_MAP,
    transform_zoho_to_moodle,
    call_moodle_ws,
)

logger = logging.getLogger(__name__)
router = APIRouter(prefix="/admin", tags=["admin"])

# In-memory job registry: { job_id: { status, current_step, results, ... } }
JOBS: Dict[str, Dict[str, Any]] = {}
LATEST_JOB_ID: Optional[str] = None

ZOHO_PER_PAGE = 200


async def _get_zoho_token() -> str:
    from app.infra.zoho.auth import ZohoAuthClient
    auth = ZohoAuthClient(
        client_id=settings.ZOHO_CLIENT_ID,
        client_secret=settings.ZOHO_CLIENT_SECRET,
        refresh_token=settings.ZOHO_REFRESH_TOKEN,
        region=settings.ZOHO_REGION,
    )
    return await auth.get_access_token()


async def fetch_all_zoho_records(module: str) -> List[Dict]:
    """Fetch every record from a Zoho module using pagination."""
    token = await _get_zoho_token()
    base_url = f"https://www.zohoapis.com/crm/v2/{module}"
    headers = {"Authorization": f"Zoho-oauthtoken {token}"}
    records: List[Dict] = []
    page = 1

    async with httpx.AsyncClient(timeout=60.0) as client:
        while True:
            resp = await client.get(base_url, headers=headers,
                                    params={"page": page, "per_page": ZOHO_PER_PAGE})
            if resp.status_code == 204:
                break
            if resp.status_code != 200:
                logger.warning(f"Zoho {module} page {page}: {resp.status_code} {resp.text[:200]}")
                break
            body = resp.json()
            page_data: List[Dict] = body.get("data", [])
            records.extend(page_data)
            info = body.get("info", {})
            logger.info(f"  {module} page {page}: {len(page_data)} records")
            if not info.get("more_records", False):
                break
            page += 1

    return records


class StepResult(BaseModel):
    module: str
    total: int
    synced: int
    skipped: int
    errors: int
    error_details: List[str] = []


def _is_duplicate(e: Exception) -> bool:
    """Moodle returns 500 with 'Duplicate entry' when row already exists."""
    return "Duplicate entry" in str(e)


def _is_parent_not_found(e: Exception, parent: str) -> bool:
    """e.g. 'Registration with zoho_registration_id ... not found'"""
    msg = str(e).lower()
    return parent.lower() in msg and "not found" in msg


async def sync_generic(entity_type: str, ws_function: str, ws_param_key: str,
                       required_field: Optional[str]) -> StepResult:
    module = ZOHO_MODULE_MAP[entity_type]
    try:
        records = await fetch_all_zoho_records(module)
    except Exception as e:
        return StepResult(module=module, total=0, synced=0, skipped=0, errors=1,
                          error_details=[f"Zoho fetch failed: {e}"])

    # For payments: cache of registration IDs already attempted to avoid N calls
    _auto_synced_regs: Set[str] = set()

    r = StepResult(module=module, total=len(records), synced=0, skipped=0, errors=0)
    for rec in records:
        zoho_id = rec.get("id", "?")
        try:
            t = transform_zoho_to_moodle(rec, entity_type)
            if required_field and not t.get(required_field):
                r.skipped += 1
                continue
            try:
                await call_moodle_ws(ws_function, {ws_param_key: json.dumps(t)})
                r.synced += 1
            except Exception as moodle_err:
                if _is_duplicate(moodle_err):
                    # Row already exists in Moodle — treat as already synced
                    r.skipped += 1
                elif _is_parent_not_found(moodle_err, "registration"):
                    # Payment/enrollment references a registration not yet in Moodle
                    reg_id = t.get("zoho_registration_id")
                    if reg_id and reg_id not in _auto_synced_regs:
                        _auto_synced_regs.add(reg_id)
                        try:

                            from app.api.v1.endpoints.student_dashboard_webhooks import fetch_zoho_full_record
                            reg_full = await fetch_zoho_full_record("BTEC_Registrations", reg_id)
                            if reg_full:
                                reg_t = transform_zoho_to_moodle(reg_full, "registrations")
                                try:
                                    await call_moodle_ws("local_mzi_create_registration",
                                                        {"registrationdata": json.dumps(reg_t)})
                                    logger.info(f"  Auto-synced missing registration {reg_id}")
                                except Exception as re:
                                    if not _is_duplicate(re):
                                        raise re
                        except Exception as ae:
                            logger.warning(f"  Could not auto-sync registration {reg_id}: {ae}")
                    # Retry payment
                    try:
                        await call_moodle_ws(ws_function, {ws_param_key: json.dumps(t)})
                        r.synced += 1
                    except Exception as retry_err:
                        if _is_duplicate(retry_err):
                            r.skipped += 1
                        else:
                            r.errors += 1
                            r.error_details.append(f"{module}/{zoho_id}: {retry_err}")
                else:
                    r.errors += 1
                    r.error_details.append(f"{module}/{zoho_id}: {moodle_err}")
                    logger.error(f"ERR {module}/{zoho_id}: {moodle_err}")
        except Exception as e:
            r.errors += 1
            r.error_details.append(f"{module}/{zoho_id}: {e}")
            logger.error(f"ERR {module}/{zoho_id}: {e}")
    return r


async def sync_teachers() -> StepResult:
    """
    Sync BTEC_Teachers to Moodle.
    For each teacher, the Moodle plugin (local_mzi_sync_teacher) will:
      1. Look up or create a Moodle user account (by Academic_Email).
      2. Store the resulting mdl_user.id in local_mzi_teachers.moodle_user_id.
    Classes synced afterward can then assign the teacher by teacher_zoho_id.
    Moodle WS: local_mzi_sync_teacher  { teacherdata: JSON }
    """
    module = ZOHO_MODULE_MAP["teachers"]
    try:
        records = await fetch_all_zoho_records(module)
    except Exception as e:
        return StepResult(module=module, total=0, synced=0, skipped=0, errors=1,
                          error_details=[f"Zoho fetch failed: {e}"])

    r = StepResult(module=module, total=len(records), synced=0, skipped=0, errors=0)
    for rec in records:
        zoho_id = rec.get("id", "?")
        try:
            t = transform_zoho_to_moodle(rec, "teachers")
            if not t.get("zoho_teacher_id"):
                r.skipped += 1
                continue
            try:
                await call_moodle_ws("local_mzi_sync_teacher", {"teacherdata": json.dumps(t)})
                r.synced += 1
            except Exception as me:
                if _is_duplicate(me):
                    r.skipped += 1
                else:
                    r.errors += 1
                    r.error_details.append(f"{module}/{zoho_id}: {me}")
        except Exception as e:
            r.errors += 1
            r.error_details.append(f"{module}/{zoho_id}: {e}")
    return r


def _date_to_epoch_gmt3(date_str: str) -> int:
    """Convert 'YYYY-MM-DD' (or ISO) to Unix epoch seconds anchored at GMT+3,
    matching the Zoho Deluge: vStartDate.unixEpoch('GMT+3:00') / 1000."""
    if not date_str:
        return 0
    try:
        from datetime import timezone, timedelta
        tz = timezone(timedelta(hours=3))
        ds = str(date_str)[:10]  # keep only the date portion
        dt = datetime.strptime(ds, "%Y-%m-%d").replace(tzinfo=tz)
        return int(dt.timestamp())
    except Exception:
        return 0


async def _get_program_category(prog_zoho_id: str,
                                cache: Dict[str, int],
                                default: int) -> int:
    """Fetch BTEC_Program (Products module) and return its MoodleID as category.
    Results are cached per sync run to avoid repeated Zoho API calls."""
    if prog_zoho_id in cache:
        return cache[prog_zoho_id]
    try:
        from app.api.v1.endpoints.student_dashboard_webhooks import fetch_zoho_full_record
        prog = await fetch_zoho_full_record("Products", prog_zoho_id)
        mid = int(prog.get("MoodleID") or 0) if prog else 0
        cat = mid if mid > 0 else default
    except Exception as pe:
        logger.warning(f"Could not fetch MoodleID for program {prog_zoho_id}: {pe}")
        cat = default
    cache[prog_zoho_id] = cat
    return cat


async def sync_classes() -> StepResult:
    module = ZOHO_MODULE_MAP["classes"]
    try:
        records = await fetch_all_zoho_records(module)
    except Exception as e:
        return StepResult(module=module, total=0, synced=0, skipped=0, errors=1,
                          error_details=[f"Zoho fetch failed: {e}"])

    r = StepResult(module=module, total=len(records), synced=0, skipped=0, errors=0)
    default_cat = getattr(settings, "MOODLE_DEFAULT_CATEGORY_ID", 1)
    _prog_cat_cache: Dict[str, int] = {}  # program_zoho_id → moodle_category_id

    for rec in records:
        zoho_id = rec.get("id", "?")
        try:
            t = transform_zoho_to_moodle(rec, "classes")
            if not t.get("moodle_class_id") and t.get("class_name"):
                name = t["class_name"]
                short = t.get("class_short_name") or name[:50]

                # ── Resolve category_id from BTEC_Program.MoodleID (like Zoho Deluge) ──
                prog_ref = rec.get("BTEC_Program") or {}
                prog_zoho_id = prog_ref.get("id") if isinstance(prog_ref, dict) else None
                if prog_zoho_id:
                    cat = await _get_program_category(prog_zoho_id, _prog_cat_cache, default_cat)
                else:
                    cat = default_cat

                # ── Convert start_date to epoch in GMT+3 (same as Zoho Deluge) ──
                start_epoch = _date_to_epoch_gmt3(t.get("start_date", ""))

                try:
                    params = {
                        "courses[0][fullname]": name,
                        "courses[0][shortname]": short,
                        "courses[0][categoryid]": cat,
                        "courses[0][format]": "weeks",
                        "courses[0][numsections]": "12",
                    }
                    if start_epoch:
                        params["courses[0][startdate]"] = str(start_epoch)
                    cr = await call_moodle_ws("core_course_create_courses", params)
                    if isinstance(cr, list) and cr:
                        t["moodle_class_id"] = str(cr[0].get("id", ""))
                except Exception as ce:
                    logger.warning(f"Course creation skipped for '{name}': {ce}")
            try:
                await call_moodle_ws("local_mzi_create_class", {"classdata": json.dumps(t)})
                r.synced += 1
            except Exception as me:
                if _is_duplicate(me):
                    r.skipped += 1
                else:
                    r.errors += 1
                    r.error_details.append(f"{module}/{zoho_id}: {me}")
                    logger.error(f"ERR {module}/{zoho_id}: {me}")
        except Exception as e:
            r.errors += 1
            r.error_details.append(f"{module}/{zoho_id}: {e}")
            logger.error(f"ERR {module}/{zoho_id}: {e}")
    return r


async def _run_full_sync(job_id: str) -> None:
    global LATEST_JOB_ID
    job = JOBS[job_id]
    job["status"] = "running"
    job["started_at"] = datetime.utcnow().isoformat()
    total_synced = 0
    total_errors = 0

    steps = [
        ("Step 1/8: Teachers",      "teachers"),
        ("Step 2/8: Students",      "students"),
        ("Step 3/8: Classes",       "classes"),
        ("Step 4/8: Registrations", "registrations"),
        ("Step 5/8: Enrollments",   "enrollments"),
        ("Step 6/8: Payments",      "payments"),
        ("Step 7/8: Grades",        "grades"),
        ("Step 8/8: Requests",      "requests"),
    ]

    coro_map = {
        "teachers":      lambda: sync_teachers(),
        "students":      lambda: sync_generic("students",      "local_mzi_update_student",       "studentdata",      "zoho_student_id"),
        "classes":       lambda: sync_classes(),
        "registrations": lambda: sync_generic("registrations", "local_mzi_create_registration",  "registrationdata", "zoho_registration_id"),
        "enrollments":   lambda: sync_generic("enrollments",   "local_mzi_update_enrollment",    "enrollmentdata",   "zoho_enrollment_id"),
        "payments":      lambda: sync_generic("payments",      "local_mzi_record_payment",       "paymentdata",      "zoho_payment_id"),
        "grades":        lambda: sync_generic("grades",        "local_mzi_submit_grade",         "gradedata",        "zoho_grade_id"),
        "requests":      lambda: sync_generic("requests",      "local_mzi_update_request_status","requestdata",      "zoho_request_id"),
    }

    for label, key in steps:
        job["current_step"] = label
        logger.info(f"[{job_id[:8]}] {label}")
        try:
            r = await coro_map[key]()
        except Exception as exc:
            logger.error(f"[{job_id[:8]}] {label} crashed: {exc}", exc_info=True)
            r = StepResult(module=label, total=0, synced=0, skipped=0, errors=1,
                           error_details=[str(exc)])
        total_synced += r.synced
        total_errors += r.errors
        job["results"][key] = r.model_dump()
        job["total_synced"] = total_synced
        job["total_errors"] = total_errors
        logger.info(f"[{job_id[:8]}] {label}: {r.synced} synced, {r.errors} errors")

    job["status"] = "complete"
    job["current_step"] = None
    job["finished_at"] = datetime.utcnow().isoformat()
    LATEST_JOB_ID = job_id
    logger.info(f"[{job_id[:8]}] Full sync DONE: {total_synced} synced, {total_errors} errors")


@router.post("/full-sync", summary="Start Full Zoho -> Moodle Sync (background)")
async def start_full_sync():
    """
    Starts a full Zoho->Moodle sync in the background and returns immediately.
    Poll GET /admin/full-sync/status to track progress.
    """
    global LATEST_JOB_ID
    job_id = str(uuid.uuid4())
    JOBS[job_id] = {
        "job_id": job_id,
        "status": "pending",
        "current_step": None,
        "total_synced": 0,
        "total_errors": 0,
        "results": {},
        "started_at": None,
        "finished_at": None,
    }
    LATEST_JOB_ID = job_id
    asyncio.create_task(_run_full_sync(job_id))
    logger.info(f"Full sync started: job_id={job_id}")
    return {
        "job_id": job_id,
        "status": "started",
        "poll_url": f"/api/v1/admin/full-sync/status?job_id={job_id}",
        "message": "Sync running in background. Poll the poll_url every few seconds.",
    }


@router.get("/full-sync/status", summary="Check Full Sync Progress")
async def get_sync_status(job_id: Optional[str] = Query(default=None)):
    """Returns current progress. Omit job_id to get the most recent job."""
    jid = job_id or LATEST_JOB_ID
    if not jid or jid not in JOBS:
        return {"status": "no_job", "message": "No sync job found. Run POST /admin/full-sync first."}
    return JOBS[jid]


# ─── Helper: search Zoho module by a criteria field ──────────────────────────

async def fetch_zoho_records_by_criteria(module: str, field: str, value: str) -> List[Dict]:
    """
    Search a Zoho module for records where `field` equals `value`.
    Uses the Zoho CRM search API: GET /crm/v2/{module}/search?criteria=((field:equals:value))
    Returns all matching records (handles pagination).
    """
    token = await _get_zoho_token()
    base_url = f"https://www.zohoapis.com/crm/v2/{module}/search"
    headers = {"Authorization": f"Zoho-oauthtoken {token}"}
    criteria = f"(({field}:equals:{value}))"
    records: List[Dict] = []
    page = 1

    async with httpx.AsyncClient(timeout=60.0) as client:
        while True:
            resp = await client.get(base_url, headers=headers,
                                    params={"criteria": criteria, "page": page, "per_page": ZOHO_PER_PAGE})
            if resp.status_code == 204:
                break
            if resp.status_code != 200:
                logger.warning(f"Zoho search {module} ({field}={value}) page {page}: {resp.status_code}")
                break
            body = resp.json()
            page_data: List[Dict] = body.get("data", [])
            records.extend(page_data)
            info = body.get("info", {})
            if not info.get("more_records", False):
                break
            page += 1

    return records


async def _push_single(entity_type: str, ws_function: str, ws_param_key: str,
                        records: List[Dict]) -> Dict[str, Any]:
    """Push a list of records to Moodle, return a summary dict."""
    synced = skipped = errors = 0
    error_details: List[str] = []
    for rec in records:
        zoho_id = rec.get("id", "?")
        try:
            t = transform_zoho_to_moodle(rec, entity_type)
            await call_moodle_ws(ws_function, {ws_param_key: json.dumps(t)})
            synced += 1
        except Exception as e:
            if _is_duplicate(e):
                skipped += 1
            else:
                errors += 1
                error_details.append(f"{zoho_id}: {str(e)}")
    return {"total": len(records), "synced": synced, "skipped": skipped,
            "errors": errors, "error_details": error_details[:10]}


@router.post("/sync-student", summary="Manually sync a single student + their data from Zoho")
async def sync_single_student(
    zoho_student_id: str = Body(..., embed=True),
    include_related: bool = Body(default=True, embed=True),
):
    """
    Fetch a student record from Zoho by their Zoho CRM record ID
    and push their data (and optionally related registrations, payments,
    enrollments, grades and requests) to Moodle.

    Body JSON:
    {
        "zoho_student_id": "<Zoho CRM record ID>",
        "include_related": true   // also sync registrations, payments, etc.
    }
    """
    from app.api.v1.endpoints.student_dashboard_webhooks import fetch_zoho_full_record

    results: Dict[str, Any] = {}

    # ── 1. Fetch + sync student ──────────────────────────────────────────────
    student_rec = await fetch_zoho_full_record("BTEC_Students", zoho_student_id)
    if not student_rec:
        return {
            "success": False,
            "error": f"Student {zoho_student_id} not found in Zoho CRM.",
            "results": {}
        }

    student_data = transform_zoho_to_moodle(student_rec, "students")
    try:
        await call_moodle_ws("local_mzi_update_student", {"studentdata": json.dumps(student_data)})
        results["student"] = {"status": "synced"}
    except Exception as e:
        if _is_duplicate(e):
            results["student"] = {"status": "already_exists"}
        else:
            results["student"] = {"status": "error", "detail": str(e)}
            return {"success": False, "error": f"Student sync failed: {e}", "results": results}

    if not include_related:
        return {"success": True, "message": "Student synced.", "results": results}

    # ── 2. Related records (search by Student_ID lookup) ────────────────────
    # Use Zoho's criteria search for each related module

    # Registrations
    try:
        regs = await fetch_zoho_records_by_criteria("BTEC_Registrations", "Student_ID", zoho_student_id)
        results["registrations"] = await _push_single(
            "registrations", "local_mzi_create_registration", "registrationdata", regs)
    except Exception as e:
        results["registrations"] = {"status": "error", "detail": str(e)}

    # Payments
    try:
        pays = await fetch_zoho_records_by_criteria("BTEC_Payments", "Student_ID", zoho_student_id)
        results["payments"] = await _push_single(
            "payments", "local_mzi_record_payment", "paymentdata", pays)
    except Exception as e:
        results["payments"] = {"status": "error", "detail": str(e)}

    # Enrollments
    try:
        enrs = await fetch_zoho_records_by_criteria("BTEC_Enrollments", "Enrolled_Students", zoho_student_id)
        results["enrollments"] = await _push_single(
            "enrollments", "local_mzi_update_enrollment", "enrollmentdata", enrs)
    except Exception as e:
        results["enrollments"] = {"status": "error", "detail": str(e)}

    # Grades
    try:
        grds = await fetch_zoho_records_by_criteria("BTEC_Grades", "Student", zoho_student_id)
        results["grades"] = await _push_single(
            "grades", "local_mzi_submit_grade", "gradedata", grds)
    except Exception as e:
        results["grades"] = {"status": "error", "detail": str(e)}

    # Requests
    try:
        reqs = await fetch_zoho_records_by_criteria("BTEC_Student_Requests", "Student", zoho_student_id)
        results["requests"] = await _push_single(
            "requests", "local_mzi_update_request_status", "requestdata", reqs)
    except Exception as e:
        results["requests"] = {"status": "error", "detail": str(e)}

    logger.info(f"sync-student {zoho_student_id}: {results}")
    return {"success": True, "message": "Student and related data synced.", "results": results}


# ─── Student Lookup (search by Name or Academic_Email in Zoho) ───────────────

@router.get("/student-lookup", summary="Search BTEC_Students in Zoho by Name or Academic_Email")
async def lookup_student(q: str = Query(..., min_length=2, description="Name (auto-number) or academic email to search")):
    """
    Searches Zoho CRM BTEC_Students module for records where Name contains `q`
    OR Academic_Email contains `q`.  Returns up to 10 matches.

    Response:
    {
        "results": [
            { "zoho_id": "...", "name": "S-000123", "full_name": "...", "academic_email": "..." }
        ]
    }
    """
    from fastapi import HTTPException as _HTTPException

    token = await _get_zoho_token()
    criteria = f"((Name:starts_with:{q})OR(Academic_Email:starts_with:{q}))"
    url = "https://www.zohoapis.com/crm/v2/BTEC_Students/search"
    headers = {"Authorization": f"Zoho-oauthtoken {token}"}

    async with httpx.AsyncClient(timeout=30.0) as client:
        resp = await client.get(url, headers=headers,
                                params={"criteria": criteria, "per_page": 10, "page": 1})

    if resp.status_code == 204:
        return {"results": []}
    if resp.status_code != 200:
        raise _HTTPException(status_code=502, detail=f"Zoho search failed: {resp.status_code} {resp.text[:200]}")

    data = resp.json().get("data", [])
    results = []
    for rec in data:
        full_name = (
            rec.get("Full_Name")
            or rec.get("Student_Name")
            or f"{rec.get('First_Name', '')} {rec.get('Last_Name', '')}".strip()
            or rec.get("Name", "")
        )
        results.append({
            "zoho_id":        rec.get("id", ""),
            "name":           rec.get("Name", ""),           # auto-number e.g. S-000123
            "full_name":      full_name,
            "academic_email": rec.get("Academic_Email", ""),
        })

    logger.info(f"student-lookup q={q!r}: {len(results)} results")
    return {"results": results}
