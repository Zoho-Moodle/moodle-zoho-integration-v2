"""
Admin Panel Router — all HTML (server-side) routes.
Mounted at: /admin
"""

import json
import os
import re
import time
from datetime import datetime
from pathlib import Path
from typing import Any, Dict, Optional

import httpx
from fastapi import APIRouter, Depends, Form, Query, Request, Response
from fastapi.responses import HTMLResponse, RedirectResponse
from fastapi.templating import Jinja2Templates

from admin.auth import (
    COOKIE_NAME,
    authenticate,
    change_password,
    create_session,
    create_user,
    delete_user,
    destroy_session,
    get_session,
    list_users,
)

router = APIRouter(prefix="/admin", include_in_schema=False)

TEMPLATES_DIR = Path(__file__).parent / "templates"
templates = Jinja2Templates(directory=str(TEMPLATES_DIR))

ENV_FILE = Path(__file__).parent.parent / ".env"
BACKEND_BASE = "http://localhost:8001"

# ─── Auth dependency ──────────────────────────────────────────────────────────

def get_current_user(request: Request) -> Optional[dict]:
    token = request.cookies.get(COOKIE_NAME)
    return get_session(token)


def require_admin(request: Request) -> dict:
    user = get_current_user(request)
    if not user:
        return None
    if user["role"] != "admin":
        return None
    return user


def require_any_role(request: Request) -> dict:
    return get_current_user(request)


def _redirect_login(next_path: str = "/admin/dashboard") -> RedirectResponse:
    return RedirectResponse(url=f"/admin/login?next={next_path}", status_code=302)


# ─── Helpers ─────────────────────────────────────────────────────────────────

def _read_env() -> dict:
    """Read .env file into a dict."""
    env = {}
    if not ENV_FILE.exists():
        return env
    for line in ENV_FILE.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#"):
            continue
        if "=" in line:
            k, v = line.split("=", 1)
            env[k.strip()] = v.strip().strip('"').strip("'")
    return env


def _write_env_key(key: str, value: str) -> None:
    """Update or append a single key in the .env file."""
    content = ENV_FILE.read_text(encoding="utf-8") if ENV_FILE.exists() else ""
    pattern = re.compile(rf"^{re.escape(key)}\s*=.*$", re.MULTILINE)
    new_line = f'{key}={value}'
    if pattern.search(content):
        content = pattern.sub(new_line, content)
    else:
        content = content.rstrip("\n") + f"\n{new_line}\n"
    ENV_FILE.write_text(content, encoding="utf-8")


async def _get_sync_status() -> dict:
    try:
        async with httpx.AsyncClient(timeout=5.0) as client:
            r = await client.get(f"{BACKEND_BASE}/api/v1/admin/full-sync/status")
            return r.json()
    except Exception:
        return {"status": "unreachable"}


async def _start_full_sync() -> dict:
    try:
        async with httpx.AsyncClient(timeout=10.0) as client:
            r = await client.post(f"{BACKEND_BASE}/api/v1/admin/full-sync")
            return r.json()
    except Exception as e:
        return {"error": str(e)}


def _ts_to_str(ts) -> str:
    if not ts:
        return "—"
    try:
        if isinstance(ts, (int, float)):
            return datetime.fromtimestamp(ts).strftime("%Y-%m-%d %H:%M:%S")
        return str(ts)[:19]
    except Exception:
        return str(ts)


# ─── LOGIN ────────────────────────────────────────────────────────────────────

@router.get("/login", response_class=HTMLResponse)
async def login_get(request: Request, next: str = "/admin/dashboard"):
    user = get_current_user(request)
    if user:
        return RedirectResponse(url="/admin/dashboard", status_code=302)
    return templates.TemplateResponse("login.html", {"request": request, "next": next, "error": None})


@router.post("/login", response_class=HTMLResponse)
async def login_post(
    request: Request,
    response: Response,
    username: str = Form(...),
    password: str = Form(...),
    next: str = Form(default="/admin/dashboard"),
):
    user = authenticate(username, password)
    if not user:
        return templates.TemplateResponse(
            "login.html",
            {"request": request, "next": next, "error": "Invalid username or password."},
            status_code=401,
        )
    token = create_session(user["username"], user["role"], user["full_name"])
    resp = RedirectResponse(url=next, status_code=302)
    resp.set_cookie(COOKIE_NAME, token, httponly=True, max_age=60 * 60 * 8, samesite="lax")
    return resp


@router.post("/logout")
async def logout(request: Request):
    token = request.cookies.get(COOKIE_NAME)
    if token:
        destroy_session(token)
    resp = RedirectResponse(url="/admin/login", status_code=302)
    resp.delete_cookie(COOKIE_NAME)
    return resp


# ─── ROOT ─────────────────────────────────────────────────────────────────────

@router.get("/", response_class=HTMLResponse)
async def admin_root(request: Request):
    return RedirectResponse(url="/admin/dashboard", status_code=302)


# ─── DASHBOARD ───────────────────────────────────────────────────────────────

@router.get("/dashboard", response_class=HTMLResponse)
async def dashboard(request: Request):
    user = get_current_user(request)
    if not user:
        return _redirect_login("/admin/dashboard")

    # Gather quick stats from DB
    from app.infra.db.session import SessionLocal
    from app.infra.db.models.student import Student
    from app.infra.db.models.event_log import EventLog
    from sqlalchemy import func, text

    stats = {
        "students": 0,
        "events_total": 0,
        "events_completed": 0,
        "events_failed": 0,
        "events_pending": 0,
    }

    try:
        db = SessionLocal()
        stats["students"] = db.query(func.count(Student.id)).scalar() or 0
        stats["events_total"] = db.query(func.count(EventLog.id)).scalar() or 0
        stats["events_completed"] = (
            db.query(func.count(EventLog.id))
            .filter(EventLog.status == "completed")
            .scalar() or 0
        )
        stats["events_failed"] = (
            db.query(func.count(EventLog.id))
            .filter(EventLog.status == "failed")
            .scalar() or 0
        )
        stats["events_pending"] = (
            db.query(func.count(EventLog.id))
            .filter(EventLog.status.in_(["pending", "processing"]))
            .scalar() or 0
        )
        # Recent events (last 10)
        recent_events = (
            db.query(EventLog)
            .order_by(EventLog.created_at.desc())
            .limit(10)
            .all()
        )
        db.close()
    except Exception as e:
        recent_events = []
        stats["error"] = str(e)

    # Sync status
    sync = await _get_sync_status()

    env = _read_env()
    integration_ok = bool(env.get("ZOHO_CLIENT_ID") and env.get("MOODLE_BASE_URL"))

    return templates.TemplateResponse(
        "dashboard.html",
        {
            "request": request,
            "user": user,
            "stats": stats,
            "sync": sync,
            "recent_events": recent_events,
            "integration_ok": integration_ok,
            "ts": _ts_to_str,
        },
    )


# ─── SYNC CONTROL ────────────────────────────────────────────────────────────

@router.get("/sync", response_class=HTMLResponse)
async def sync_control(request: Request):
    user = get_current_user(request)
    if not user:
        return _redirect_login("/admin/sync")

    sync = await _get_sync_status()
    return templates.TemplateResponse(
        "sync_control.html",
        {"request": request, "user": user, "sync": sync, "ts": _ts_to_str},
    )


@router.post("/sync/start", response_class=HTMLResponse)
async def sync_start(request: Request):
    user = get_current_user(request)
    if not user:
        return HTMLResponse("<p class='text-red-600'>Unauthorized</p>", status_code=401)

    result = await _start_full_sync()
    sync = await _get_sync_status()
    return templates.TemplateResponse(
        "partials/sync_status.html",
        {"request": request, "sync": sync, "started": True, "ts": _ts_to_str},
    )


@router.get("/sync/status-partial", response_class=HTMLResponse)
async def sync_status_partial(request: Request):
    """HTMX polling target — returns the sync status cards partial."""
    user = get_current_user(request)
    if not user:
        return HTMLResponse("", status_code=401)

    sync = await _get_sync_status()
    return templates.TemplateResponse(
        "partials/sync_status.html",
        {"request": request, "sync": sync, "started": False, "ts": _ts_to_str},
    )


@router.post("/sync/single-student", response_class=HTMLResponse)
async def sync_single_student(request: Request, zoho_student_id: str = Form(...)):
    """Sync a single student by Zoho ID."""
    user = get_current_user(request)
    if not user:
        return HTMLResponse("<p>Unauthorized</p>", status_code=401)
    try:
        async with httpx.AsyncClient(timeout=30.0) as client:
            r = await client.post(
                f"{BACKEND_BASE}/api/v1/admin/sync-student",
                json={"zoho_student_id": zoho_student_id, "include_related": True},
            )
            result = r.json()
        msg = "✅ " + json.dumps(result, indent=2)[:500]
        color = "green"
    except Exception as e:
        msg = f"❌ Error: {e}"
        color = "red"

    return HTMLResponse(
        f'<pre class="text-{color}-700 text-xs bg-{color}-50 p-3 rounded-lg overflow-auto max-h-40">{msg}</pre>'
    )


# ─── SYNC HISTORY ─────────────────────────────────────────────────────────────

@router.get("/sync/history", response_class=HTMLResponse)
async def sync_history(request: Request):
    user = get_current_user(request)
    if not user:
        return _redirect_login("/admin/sync/history")

    # Read all JOBS from memory (imported from full_sync module)
    try:
        from app.api.v1.endpoints.full_sync import JOBS
        jobs = sorted(JOBS.values(), key=lambda j: j.get("started_at") or "", reverse=True)
    except Exception:
        jobs = []

    return templates.TemplateResponse(
        "sync_history.html",
        {"request": request, "user": user, "jobs": jobs, "ts": _ts_to_str},
    )


# ─── LOGS ─────────────────────────────────────────────────────────────────────

@router.get("/logs", response_class=HTMLResponse)
async def logs_page(
    request: Request,
    source: str = Query(default=""),
    status: str = Query(default=""),
    search: str = Query(default=""),
    page: int = Query(default=1, ge=1),
):
    user = get_current_user(request)
    if not user:
        return _redirect_login("/admin/logs")

    logs, total = _fetch_logs(source=source, status=status, search=search, page=page, per_page=50)
    total_pages = max(1, (total + 49) // 50)

    return templates.TemplateResponse(
        "logs.html",
        {
            "request": request,
            "user": user,
            "logs": logs,
            "total": total,
            "page": page,
            "total_pages": total_pages,
            "source": source,
            "status": status,
            "search": search,
            "ts": _ts_to_str,
        },
    )


@router.get("/logs/partial", response_class=HTMLResponse)
async def logs_partial(
    request: Request,
    source: str = Query(default=""),
    status: str = Query(default=""),
    search: str = Query(default=""),
    page: int = Query(default=1, ge=1),
):
    """HTMX partial — returns the logs table only."""
    user = get_current_user(request)
    if not user:
        return HTMLResponse("", status_code=401)

    logs, total = _fetch_logs(source=source, status=status, search=search, page=page, per_page=50)
    total_pages = max(1, (total + 49) // 50)

    return templates.TemplateResponse(
        "partials/logs_table.html",
        {
            "request": request,
            "logs": logs,
            "total": total,
            "page": page,
            "total_pages": total_pages,
            "source": source,
            "status": status,
            "search": search,
            "ts": _ts_to_str,
        },
    )


def _fetch_logs(source: str, status: str, search: str, page: int, per_page: int):
    from app.infra.db.session import SessionLocal
    from app.infra.db.models.event_log import EventLog
    from sqlalchemy import or_

    try:
        db = SessionLocal()
        q = db.query(EventLog)
        if source:
            q = q.filter(EventLog.source == source)
        if status:
            q = q.filter(EventLog.status == status)
        if search:
            q = q.filter(
                or_(
                    EventLog.record_id.ilike(f"%{search}%"),
                    EventLog.module.ilike(f"%{search}%"),
                    EventLog.event_type.ilike(f"%{search}%"),
                )
            )
        total = q.count()
        logs = (
            q.order_by(EventLog.created_at.desc())
            .offset((page - 1) * per_page)
            .limit(per_page)
            .all()
        )
        db.close()
        return logs, total
    except Exception:
        return [], 0


# ─── DATA BROWSER ─────────────────────────────────────────────────────────────

@router.get("/data/{entity}", response_class=HTMLResponse)
async def data_browser(
    request: Request,
    entity: str,
    search: str = Query(default=""),
    page: int = Query(default=1, ge=1),
    status: str = Query(default=""),
):
    user = get_current_user(request)
    if not user:
        return _redirect_login(f"/admin/data/{entity}")

    allowed = {"students", "registrations", "enrollments", "payments", "grades", "events"}
    if entity not in allowed:
        entity = "students"

    rows, total, columns = _fetch_entity(entity, search=search, status=status, page=page, per_page=30)
    total_pages = max(1, (total + 29) // 30)

    return templates.TemplateResponse(
        "data_browser.html",
        {
            "request": request,
            "user": user,
            "entity": entity,
            "rows": rows,
            "columns": columns,
            "total": total,
            "page": page,
            "total_pages": total_pages,
            "search": search,
            "status": status,
            "ts": _ts_to_str,
        },
    )


def _fetch_entity(entity: str, search: str, status: str, page: int, per_page: int):
    from app.infra.db.session import SessionLocal
    from sqlalchemy import or_, text

    try:
        db = SessionLocal()

        if entity == "students":
            from app.infra.db.models.student import Student
            q = db.query(Student)
            if search:
                q = q.filter(
                    or_(
                        Student.display_name.ilike(f"%{search}%"),
                        Student.academic_email.ilike(f"%{search}%"),
                        Student.zoho_id.ilike(f"%{search}%"),
                        Student.username.ilike(f"%{search}%"),
                    )
                )
            if status:
                q = q.filter(Student.status == status)
            total = q.count()
            rows = q.order_by(Student.created_at.desc()).offset((page - 1) * per_page).limit(per_page).all()
            columns = ["zoho_id", "display_name", "academic_email", "phone", "status", "sync_status", "created_at"]

        elif entity == "events":
            from app.infra.db.models.event_log import EventLog
            q = db.query(EventLog)
            if search:
                q = q.filter(
                    or_(
                        EventLog.record_id.ilike(f"%{search}%"),
                        EventLog.module.ilike(f"%{search}%"),
                        EventLog.event_type.ilike(f"%{search}%"),
                    )
                )
            if status:
                q = q.filter(EventLog.status == status)
            total = q.count()
            rows = q.order_by(EventLog.created_at.desc()).offset((page - 1) * per_page).limit(per_page).all()
            columns = ["source", "module", "event_type", "record_id", "status", "created_at", "error_message"]

        elif entity == "registrations":
            from app.infra.db.models.registration import Registration
            q = db.query(Registration)
            if search:
                q = q.filter(Registration.zoho_id.ilike(f"%{search}%"))
            if status:
                q = q.filter(Registration.enrollment_status == status)
            total = q.count()
            rows = q.order_by(Registration.created_at.desc()).offset((page - 1) * per_page).limit(per_page).all()
            columns = ["zoho_id", "student_zoho_id", "enrollment_status", "registration_date", "sync_status", "created_at"]

        elif entity == "enrollments":
            from app.infra.db.models.enrollment import Enrollment
            q = db.query(Enrollment)
            if search:
                q = q.filter(
                    or_(
                        Enrollment.zoho_id.ilike(f"%{search}%"),
                        Enrollment.student_zoho_id.ilike(f"%{search}%"),
                        Enrollment.class_zoho_id.ilike(f"%{search}%"),
                    )
                )
            if status:
                q = q.filter(Enrollment.status == status)
            total = q.count()
            rows = q.order_by(Enrollment.created_at.desc()).offset((page - 1) * per_page).limit(per_page).all()
            columns = ["zoho_id", "student_zoho_id", "class_zoho_id", "status", "start_date", "created_at"]

        elif entity == "payments":
            from app.infra.db.models.payment import Payment
            q = db.query(Payment)
            if search:
                q = q.filter(Payment.zoho_id.ilike(f"%{search}%"))
            total = q.count()
            rows = q.order_by(Payment.created_at.desc()).offset((page - 1) * per_page).limit(per_page).all()
            columns = ["zoho_id", "amount", "status", "payment_date", "created_at"]

        elif entity == "grades":
            from app.infra.db.models.grade import Grade
            q = db.query(Grade)
            if search:
                q = q.filter(Grade.zoho_id.ilike(f"%{search}%"))
            total = q.count()
            rows = q.order_by(Grade.created_at.desc()).offset((page - 1) * per_page).limit(per_page).all()
            columns = ["zoho_id", "grade_value", "grade_type", "status", "created_at"]

        else:
            total = 0
            rows = []
            columns = []

        db.close()

        # Convert to dicts
        rows_dict = []
        for row in rows:
            d = {}
            for col in columns:
                val = getattr(row, col, "—")
                if hasattr(val, "isoformat"):
                    val = val.strftime("%Y-%m-%d %H:%M")
                elif val is None:
                    val = "—"
                d[col] = str(val)
            rows_dict.append(d)

        return rows_dict, total, columns

    except Exception as e:
        return [{"error": str(e)}], 0, ["error"]


# ─── SETTINGS ─────────────────────────────────────────────────────────────────

@router.get("/settings", response_class=HTMLResponse)
async def settings_page(request: Request, tab: str = Query(default="zoho"), saved: str = Query(default="")):
    user = get_current_user(request)
    if not user:
        return _redirect_login("/admin/settings")
    if user["role"] != "admin":
        return RedirectResponse(url="/admin/dashboard", status_code=302)

    env = _read_env()
    return templates.TemplateResponse(
        "settings.html",
        {"request": request, "user": user, "env": env, "tab": tab, "saved": saved},
    )


@router.post("/settings/zoho", response_class=HTMLResponse)
async def settings_save_zoho(
    request: Request,
    ZOHO_CLIENT_ID: str = Form(default=""),
    ZOHO_CLIENT_SECRET: str = Form(default=""),
    ZOHO_REFRESH_TOKEN: str = Form(default=""),
    ZOHO_REGION: str = Form(default="com"),
    ZOHO_ORGANIZATION_ID: str = Form(default=""),
    ZOHO_WEBHOOK_SECRET: str = Form(default=""),
    ZOHO_TIMEOUT: str = Form(default="30.0"),
):
    user = get_current_user(request)
    if not user or user["role"] != "admin":
        return RedirectResponse(url="/admin/login", status_code=302)

    for key, val in {
        "ZOHO_CLIENT_ID": ZOHO_CLIENT_ID,
        "ZOHO_CLIENT_SECRET": ZOHO_CLIENT_SECRET,
        "ZOHO_REFRESH_TOKEN": ZOHO_REFRESH_TOKEN,
        "ZOHO_REGION": ZOHO_REGION,
        "ZOHO_ORGANIZATION_ID": ZOHO_ORGANIZATION_ID,
        "ZOHO_WEBHOOK_SECRET": ZOHO_WEBHOOK_SECRET,
        "ZOHO_TIMEOUT": ZOHO_TIMEOUT,
    }.items():
        if val:
            _write_env_key(key, val)

    return RedirectResponse(url="/admin/settings?tab=zoho&saved=1", status_code=302)


@router.post("/settings/moodle", response_class=HTMLResponse)
async def settings_save_moodle(
    request: Request,
    MOODLE_BASE_URL: str = Form(default=""),
    MOODLE_TOKEN: str = Form(default=""),
    MOODLE_DB_URL: str = Form(default=""),
    MOODLE_DEFAULT_CATEGORY_ID: str = Form(default="1"),
    MOODLE_WEBHOOK_SECRET: str = Form(default=""),
):
    user = get_current_user(request)
    if not user or user["role"] != "admin":
        return RedirectResponse(url="/admin/login", status_code=302)

    for key, val in {
        "MOODLE_BASE_URL": MOODLE_BASE_URL,
        "MOODLE_TOKEN": MOODLE_TOKEN,
        "MOODLE_DB_URL": MOODLE_DB_URL,
        "MOODLE_DEFAULT_CATEGORY_ID": MOODLE_DEFAULT_CATEGORY_ID,
        "MOODLE_WEBHOOK_SECRET": MOODLE_WEBHOOK_SECRET,
    }.items():
        if val:
            _write_env_key(key, val)

    if MOODLE_BASE_URL or MOODLE_TOKEN:
        _write_env_key("MOODLE_ENABLED", "true")

    return RedirectResponse(url="/admin/settings?tab=moodle&saved=1", status_code=302)


@router.post("/settings/webhooks", response_class=HTMLResponse)
async def settings_save_webhooks(
    request: Request,
    WEBHOOK_BASE_URL: str = Form(default=""),
    ZOHO_WEBHOOK_HMAC_SECRET: str = Form(default=""),
):
    user = get_current_user(request)
    if not user or user["role"] != "admin":
        return RedirectResponse(url="/admin/login", status_code=302)

    for key, val in {
        "WEBHOOK_BASE_URL": WEBHOOK_BASE_URL,
        "ZOHO_WEBHOOK_HMAC_SECRET": ZOHO_WEBHOOK_HMAC_SECRET,
    }.items():
        if val:
            _write_env_key(key, val)

    return RedirectResponse(url="/admin/settings?tab=webhooks&saved=1", status_code=302)


@router.post("/settings/schedule", response_class=HTMLResponse)
async def settings_save_schedule(
    request: Request,
    SYNC_SCHEDULE_ENABLED: str = Form(default="false"),
    SYNC_SCHEDULE_CRON: str = Form(default="0 2 * * *"),
):
    user = get_current_user(request)
    if not user or user["role"] != "admin":
        return RedirectResponse(url="/admin/login", status_code=302)

    _write_env_key("SYNC_SCHEDULE_ENABLED", SYNC_SCHEDULE_ENABLED)
    _write_env_key("SYNC_SCHEDULE_CRON", SYNC_SCHEDULE_CRON)

    return RedirectResponse(url="/admin/settings?tab=schedule&saved=1", status_code=302)


@router.post("/settings/notifications", response_class=HTMLResponse)
async def settings_save_notifications(
    request: Request,
    NOTIFY_EMAIL: str = Form(default=""),
    NOTIFY_ON_ERROR: str = Form(default="false"),
    NOTIFY_ON_COMPLETE: str = Form(default="false"),
    SMTP_HOST: str = Form(default=""),
    SMTP_PORT: str = Form(default="587"),
    SMTP_USER: str = Form(default=""),
    SMTP_PASSWORD: str = Form(default=""),
):
    user = get_current_user(request)
    if not user or user["role"] != "admin":
        return RedirectResponse(url="/admin/login", status_code=302)

    for key, val in {
        "NOTIFY_EMAIL": NOTIFY_EMAIL,
        "NOTIFY_ON_ERROR": NOTIFY_ON_ERROR,
        "NOTIFY_ON_COMPLETE": NOTIFY_ON_COMPLETE,
        "SMTP_HOST": SMTP_HOST,
        "SMTP_PORT": SMTP_PORT,
        "SMTP_USER": SMTP_USER,
        "SMTP_PASSWORD": SMTP_PASSWORD,
    }.items():
        if val:
            _write_env_key(key, val)

    return RedirectResponse(url="/admin/settings?tab=notifications&saved=1", status_code=302)


# ─── USERS ────────────────────────────────────────────────────────────────────

@router.get("/users", response_class=HTMLResponse)
async def users_page(request: Request, msg: str = Query(default=""), error: str = Query(default="")):
    user = get_current_user(request)
    if not user:
        return _redirect_login("/admin/users")
    if user["role"] != "admin":
        return RedirectResponse(url="/admin/dashboard", status_code=302)

    users = list_users()
    return templates.TemplateResponse(
        "users.html",
        {"request": request, "user": user, "users": users, "msg": msg, "error": error},
    )


@router.post("/users/create", response_class=HTMLResponse)
async def users_create(
    request: Request,
    new_username: str = Form(...),
    new_password: str = Form(...),
    new_role: str = Form(...),
    new_full_name: str = Form(...),
):
    user = get_current_user(request)
    if not user or user["role"] != "admin":
        return RedirectResponse(url="/admin/login", status_code=302)

    try:
        ok = create_user(new_username, new_password, new_role, new_full_name)
        if ok:
            msg = f"User '{new_full_name}' created successfully."
            return RedirectResponse(url=f"/admin/users?msg={msg}", status_code=302)
        else:
            return RedirectResponse(url="/admin/users?error=Username already exists.", status_code=302)
    except ValueError as e:
        return RedirectResponse(url=f"/admin/users?error={e}", status_code=302)


@router.post("/users/delete/{username}", response_class=HTMLResponse)
async def users_delete(request: Request, username: str):
    user = get_current_user(request)
    if not user or user["role"] != "admin":
        return RedirectResponse(url="/admin/login", status_code=302)
    if username == user["username"]:
        return RedirectResponse(url="/admin/users?error=Cannot delete your own account.", status_code=302)
    try:
        delete_user(username)
        return RedirectResponse(url=f"/admin/users?msg=User '{username}' deleted.", status_code=302)
    except ValueError as e:
        return RedirectResponse(url=f"/admin/users?error={e}", status_code=302)


@router.post("/users/change-password", response_class=HTMLResponse)
async def users_change_password(
    request: Request,
    target_username: str = Form(...),
    new_password: str = Form(...),
):
    user = get_current_user(request)
    if not user or user["role"] != "admin":
        return RedirectResponse(url="/admin/login", status_code=302)
    change_password(target_username, new_password)
    return RedirectResponse(url=f"/admin/users?msg=Password changed for '{target_username}'.", status_code=302)


# ─── SETUP WIZARD ─────────────────────────────────────────────────────────────

def _mark_setup_step_done(step: str) -> None:
    """Persist a completed setup step to .env."""
    env = _read_env()
    completed = set(s.strip() for s in env.get("SETUP_COMPLETED_STEPS", "").split(",") if s.strip())
    completed.add(step)
    _write_env_key("SETUP_COMPLETED_STEPS", ",".join(sorted(completed)))


@router.get("/setup", response_class=HTMLResponse)
async def setup_wizard_page(request: Request):
    user = get_current_user(request)
    if not user:
        return _redirect_login("/admin/setup")
    env = _read_env()
    completed_steps = [s.strip() for s in env.get("SETUP_COMPLETED_STEPS", "").split(",") if s.strip()]
    return templates.TemplateResponse(
        "setup.html",
        {"request": request, "user": user, "env": env, "completed_steps": completed_steps},
    )


@router.post("/setup/test-backend")
async def setup_test_backend(request: Request):
    user = get_current_user(request)
    if not user:
        return {"ok": False, "error": "Not authenticated"}

    import sys
    checks = []

    # Python version
    py_ver = f"{sys.version_info.major}.{sys.version_info.minor}.{sys.version_info.micro}"
    py_ok = sys.version_info >= (3, 9)
    checks.append({"name": "Python Version", "value": py_ver, "ok": py_ok})

    # .env file
    env_exists = ENV_FILE.exists()
    checks.append({
        "name": ".env file",
        "value": str(ENV_FILE) if env_exists else "Not found at " + str(ENV_FILE),
        "ok": env_exists,
    })

    # Required env vars
    env = _read_env()
    for key in ["ZOHO_CLIENT_ID", "ZOHO_CLIENT_SECRET", "ZOHO_REFRESH_TOKEN", "MOODLE_BASE_URL", "MOODLE_TOKEN"]:
        has_val = bool(env.get(key, "").strip())
        checks.append({"name": key, "value": "Configured ✓" if has_val else "Not set", "ok": has_val})

    # Key packages
    for pkg in ["httpx", "sqlalchemy", "fastapi"]:
        try:
            __import__(pkg)
            checks.append({"name": pkg, "value": "Installed", "ok": True})
        except ImportError:
            checks.append({"name": pkg, "value": "Not installed", "ok": False})

    all_ok = all(c["ok"] for c in checks)
    if all_ok:
        _mark_setup_step_done("backend")
    return {"ok": all_ok, "checks": checks}


@router.post("/setup/test-zoho")
async def setup_test_zoho(request: Request):
    user = get_current_user(request)
    if not user:
        return {"ok": False, "error": "Not authenticated"}
    try:
        from app.infra.zoho.config import create_zoho_client
        zoho = create_zoho_client()
        result = await zoho._make_request("GET", "/settings/modules", params={"type": "api_supported"})
        count = len(result.get("modules", []))
        _mark_setup_step_done("zoho")
        return {"ok": True, "message": f"Connected to Zoho CRM. Found {count} modules.", "modules_count": count}
    except Exception as e:
        return {"ok": False, "error": str(e)}


@router.post("/setup/test-moodle")
async def setup_test_moodle(request: Request):
    user = get_current_user(request)
    if not user:
        return {"ok": False, "error": "Not authenticated"}

    env = _read_env()
    moodle_url = env.get("MOODLE_BASE_URL", "").rstrip("/")
    token = env.get("MOODLE_TOKEN", "")

    if not moodle_url or not token:
        return {"ok": False, "error": "MOODLE_BASE_URL or MOODLE_TOKEN not configured. Go to Settings → Moodle tab."}

    REQUIRED_FUNCTIONS = [
        "core_user_get_users",
        "core_course_create_courses",
        "core_enrol_get_users_courses",
        "mod_assign_get_grades",
        "core_webservice_get_site_info",
        "gradereport_user_get_grades_table",
    ]

    try:
        async with httpx.AsyncClient(timeout=15.0, verify=False) as client:
            r = await client.get(
                f"{moodle_url}/webservice/rest/server.php",
                params={
                    "wstoken": token,
                    "wsfunction": "core_webservice_get_site_info",
                    "moodlewsrestformat": "json",
                },
            )
            data = r.json()
        if "exception" in data:
            exc = data.get("errorcode", "")
            msg = data.get("message", str(data))
            if exc == "invalidtoken":
                return {"ok": False, "error": "Invalid token — check MOODLE_TOKEN in Settings → Moodle tab."}
            if exc == "accessException":
                return {
                    "ok": False,
                    "error": (
                        "Token is valid but 'core_webservice_get_site_info' is not in your External Service. "
                        "Go to Moodle Admin → Server → Web Services → External Services → "
                        "open your service → Add Functions, and add all required functions listed below."
                    ),
                }
            return {"ok": False, "error": msg}

        func_names = {f["name"] for f in data.get("functions", [])}
        missing = [f for f in REQUIRED_FUNCTIONS if f not in func_names]
        _mark_setup_step_done("moodle")
        return {
            "ok": True,
            "site_name": data.get("sitename", "—"),
            "release": data.get("release", "—"),
            "functions_count": len(func_names),
            "missing_functions": missing,
            "message": f"Connected to '{data.get('sitename', '—')}'. {len(func_names)} WS functions available.",
        }
    except Exception as e:
        return {"ok": False, "error": str(e)}


@router.get("/setup/db-status")
async def setup_db_status(request: Request):
    user = get_current_user(request)
    if not user:
        return {"ok": False, "error": "Not authenticated"}
    try:
        from app.infra.db.base import engine
        from sqlalchemy import inspect as sa_inspect
        inspector = sa_inspect(engine)
        existing = set(inspector.get_table_names())
        required = [
            "students", "registrations", "classes", "enrollments",
            "grades", "payments", "integration_events_log", "sync_runs",
        ]
        table_status = [{"name": t, "exists": t in existing} for t in required]
        all_present = all(t["exists"] for t in table_status)
        if all_present:
            _mark_setup_step_done("database")
        return {
            "ok": all_present,
            "tables": table_status,
            "db_url": str(engine.url).replace(str(engine.url.password or ""), "****") if engine.url.password else str(engine.url),
            "existing_count": len(existing),
        }
    except Exception as e:
        return {"ok": False, "error": str(e)}


@router.post("/setup/init-db")
async def setup_init_db(request: Request):
    user = get_current_user(request)
    if not user:
        return {"ok": False, "error": "Not authenticated"}
    try:
        from app.infra.db.base import Base, engine
        import app.infra.db.models  # noqa — ensures all models are registered
        Base.metadata.create_all(bind=engine)
        _mark_setup_step_done("database")
        return {"ok": True, "message": "✓ Database initialized. All tables created."}
    except Exception as e:
        return {"ok": False, "error": str(e)}


@router.get("/setup/zoho-modules")
async def setup_zoho_modules(request: Request):
    user = get_current_user(request)
    if not user:
        return {"ok": False, "error": "Not authenticated"}
    try:
        from app.infra.zoho.config import create_zoho_client
        zoho = create_zoho_client()
        result = await zoho._make_request("GET", "/settings/modules", params={"type": "api_supported"})
        modules = sorted(
            [{"api_name": m.get("api_name", ""), "label": m.get("plural_label", m.get("api_name", ""))}
             for m in result.get("modules", [])],
            key=lambda x: x["label"],
        )
        return {"ok": True, "modules": modules}
    except Exception as e:
        return {"ok": False, "error": str(e)}


@router.get("/setup/zoho-fields")
async def setup_zoho_fields(request: Request, module: str = ""):
    user = get_current_user(request)
    if not user:
        return {"ok": False, "error": "Not authenticated"}
    if not module:
        return {"ok": False, "error": "module query param required"}
    try:
        from app.infra.zoho.config import create_zoho_client
        zoho = create_zoho_client()
        # Fetch 1 sample record to discover field names (avoids settings/fields scope issue)
        result = await zoho._make_request("GET", f"/{module}", params={"page": 1, "per_page": 1})
        records = result.get("data", [])
        if not records:
            # Module exists but no records — return empty fields list with a note
            return {"ok": True, "fields": [], "module": module, "note": "No records found in this module to discover fields. Add at least one record in Zoho first."}
        sample = records[0]
        fields = sorted(
            [{"api_name": k, "label": k.replace("_", " ").title()}
             for k in sample.keys()
             if k not in ("$", "id") and not k.startswith("$")],
            key=lambda x: x["label"],
        )
        return {"ok": True, "fields": fields, "module": module}
    except Exception as e:
        return {"ok": False, "error": str(e)}


@router.get("/setup/load-field-mappings")
async def setup_load_field_mappings(request: Request):
    user = get_current_user(request)
    if not user:
        return {"ok": False, "error": "Not authenticated"}
    try:
        from app.infra.db.base import engine
        from sqlalchemy.orm import Session
        from app.infra.db.models.extension import FieldMapping
        env = _read_env()
        with Session(engine) as session:
            rows = session.query(FieldMapping).filter_by(tenant_id="default").all()
        grouped: dict = {}
        for row in rows:
            svc = row.module_name
            if svc not in grouped:
                grouped[svc] = {
                    "zoho_module": env.get(f"ZOHO_MODULE_{svc.upper()}", ""),
                    "mappings": {},
                }
            grouped[svc]["mappings"][row.canonical_field] = row.zoho_field_api_name
        return {"ok": True, "data": grouped}
    except Exception as e:
        return {"ok": False, "error": str(e)}


@router.post("/setup/save-field-mapping")
async def setup_save_field_mapping(request: Request):
    user = get_current_user(request)
    if not user:
        return {"ok": False, "error": "Not authenticated"}
    data = await request.json()
    service = data.get("service", "").strip()
    zoho_module = data.get("zoho_module", "").strip()
    mappings: dict = data.get("mappings", {})
    tenant_id = data.get("tenant_id", "default")
    if not service or not zoho_module:
        return {"ok": False, "error": "service and zoho_module are required"}
    try:
        import uuid
        from app.infra.db.base import engine
        from sqlalchemy.orm import Session
        from app.infra.db.models.extension import FieldMapping
        from app.infra.db.mapping_loader import ensure_default_tenant
        with Session(engine) as session:
            ensure_default_tenant(session)
            session.query(FieldMapping).filter_by(module_name=service, tenant_id=tenant_id).delete()
            for canonical, zoho_api_name in mappings.items():
                if canonical and zoho_api_name:
                    session.add(FieldMapping(
                        id=str(uuid.uuid4()),
                        tenant_id=tenant_id,
                        module_name=service,
                        canonical_field=canonical,
                        zoho_field_api_name=zoho_api_name,
                        required=False,
                    ))
            session.commit()
        _write_env_key(f"ZOHO_MODULE_{service.upper()}", zoho_module)
        return {"ok": True, "saved": len(mappings)}
    except Exception as e:
        return {"ok": False, "error": str(e)}


@router.post("/setup/save-mapping")
async def setup_save_mapping(request: Request):
    user = get_current_user(request)
    if not user:
        return {"ok": False, "error": "Not authenticated"}
    data = await request.json()
    _write_env_key("ENABLED_SERVICES", ",".join(data.get("services", [])))
    _mark_setup_step_done("mapping")
    return {"ok": True}


@router.post("/setup/create-webhooks")
async def setup_create_webhooks(request: Request):
    user = get_current_user(request)
    if not user:
        return {"ok": False, "error": "Not authenticated"}
    try:
        async with httpx.AsyncClient(timeout=30.0) as client:
            r = await client.post(f"{BACKEND_BASE}/api/v1/admin/setup-zoho-webhooks")
            result = r.json()
        _mark_setup_step_done("webhooks")
        return {"ok": True, "result": result}
    except Exception as e:
        return {"ok": False, "error": str(e)}


@router.post("/setup/mark-done")
async def setup_mark_done(request: Request):
    user = get_current_user(request)
    if not user:
        return {"ok": False, "error": "Not authenticated"}
    _mark_setup_step_done("golive")
    return {"ok": True}


# ─── SERVICES OVERVIEW ────────────────────────────────────────────────────────

@router.get("/services", response_class=HTMLResponse)
async def services_page(request: Request):
    user = get_current_user(request)
    if not user:
        return _redirect_login("/admin/services")

    services = [
        # Zoho → Moodle Full Sync
        {"direction": "Zoho → Moodle", "group": "Full Sync", "name": "Sync All (7 Steps)", "endpoint": "POST /api/v1/admin/full-sync", "description": "Full bi-directional sync: Students → Classes → Registrations → Enrollments → Payments → Grades → Requests"},
        {"direction": "Zoho → Moodle", "group": "Full Sync", "name": "Sync Status", "endpoint": "GET /api/v1/admin/full-sync/status", "description": "Poll the current running sync job progress"},
        {"direction": "Zoho → Moodle", "group": "Full Sync", "name": "Sync Single Student", "endpoint": "POST /api/v1/admin/sync-student", "description": "Sync one student and all their related data from Zoho"},
        # Individual syncs
        {"direction": "Zoho → Moodle", "group": "Individual Sync", "name": "Sync Students", "endpoint": "POST /api/v1/sync/students", "description": "Sync all students from Zoho BTEC_Students module"},
        {"direction": "Zoho → Moodle", "group": "Individual Sync", "name": "Sync Classes", "endpoint": "POST /api/v1/sync/classes", "description": "Sync all classes from Zoho BTEC_Classes module"},
        {"direction": "Zoho → Moodle", "group": "Individual Sync", "name": "Sync Registrations", "endpoint": "POST /api/v1/sync/registrations", "description": "Sync registrations + fees + program info"},
        {"direction": "Zoho → Moodle", "group": "Individual Sync", "name": "Sync Enrollments", "endpoint": "POST /api/v1/sync/enrollments", "description": "Sync student-class enrollments"},
        {"direction": "Zoho → Moodle", "group": "Individual Sync", "name": "Sync Payments", "endpoint": "POST /api/v1/sync/payments", "description": "Sync student payment records"},
        {"direction": "Zoho → Moodle", "group": "Individual Sync", "name": "Sync Grades", "endpoint": "POST /api/v1/sync/grades", "description": "Sync BTEC grade records"},
        {"direction": "Zoho → Moodle", "group": "Individual Sync", "name": "Sync Units", "endpoint": "POST /api/v1/sync/units", "description": "Sync BTEC unit definitions"},
        {"direction": "Zoho → Moodle", "group": "Individual Sync", "name": "Sync Programs", "endpoint": "POST /api/v1/sync/programs", "description": "Sync program/course templates"},
        # Zoho Webhooks → Moodle
        {"direction": "Zoho → Moodle", "group": "Webhooks (Real-time)", "name": "Student Webhook", "endpoint": "POST /api/v1/events/zoho/student", "description": "Receive student create/update/delete from Zoho"},
        {"direction": "Zoho → Moodle", "group": "Webhooks (Real-time)", "name": "Enrollment Webhook", "endpoint": "POST /api/v1/events/zoho/enrollment", "description": "Receive enrollment events from Zoho"},
        {"direction": "Zoho → Moodle", "group": "Webhooks (Real-time)", "name": "Grade Webhook", "endpoint": "POST /api/v1/events/zoho/grade", "description": "Receive grade submission events from Zoho"},
        {"direction": "Zoho → Moodle", "group": "Webhooks (Real-time)", "name": "Payment Webhook", "endpoint": "POST /api/v1/events/zoho/payment", "description": "Receive payment recorded events from Zoho"},
        # Student Dashboard Webhooks
        {"direction": "Zoho → Moodle", "group": "Student Dashboard", "name": "Dashboard Sync (All)", "endpoint": "POST /api/v1/webhooks/student-dashboard/full-sync", "description": "Full sync for student dashboard data"},
        {"direction": "Zoho → Moodle", "group": "Student Dashboard", "name": "Update Student", "endpoint": "POST /api/v1/webhooks/student-dashboard/student", "description": "Update single student in dashboard DB"},
        {"direction": "Zoho → Moodle", "group": "Student Dashboard", "name": "Create Registration", "endpoint": "POST /api/v1/webhooks/student-dashboard/registration", "description": "Create/update registration in dashboard DB"},
        {"direction": "Zoho → Moodle", "group": "Student Dashboard", "name": "Record Payment", "endpoint": "POST /api/v1/webhooks/student-dashboard/payment", "description": "Record payment in dashboard DB"},
        # Moodle → Zoho
        {"direction": "Moodle → Zoho", "group": "Moodle Events", "name": "User Created", "endpoint": "POST /api/v1/moodle/user_created", "description": "New Moodle user → create in Zoho CRM"},
        {"direction": "Moodle → Zoho", "group": "Moodle Events", "name": "User Updated", "endpoint": "POST /api/v1/moodle/user_updated", "description": "Moodle user update → update in Zoho"},
        {"direction": "Moodle → Zoho", "group": "Moodle Events", "name": "User Enrolled", "endpoint": "POST /api/v1/moodle/user_enrolled", "description": "Moodle course enrollment → sync to Zoho"},
        {"direction": "Moodle → Zoho", "group": "Moodle Events", "name": "Grade Updated", "endpoint": "POST /api/v1/moodle/grade_updated", "description": "Moodle grade → update in Zoho BTEC Grades"},
        # Other
        {"direction": "Backend Internal", "group": "Moodle Actions", "name": "Create Course", "endpoint": "POST /api/v1/create-course", "description": "Create a Moodle course from Zoho class data"},
        {"direction": "Backend Internal", "group": "Misc", "name": "Submit Request", "endpoint": "POST /api/v1/submit-request", "description": "Student submits a request from the dashboard"},
        {"direction": "Backend Internal", "group": "Misc", "name": "Setup Zoho Webhooks", "endpoint": "POST /api/v1/admin/setup-zoho-webhooks", "description": "Register Moodle URLs as Zoho CRM notification channels"},
        {"direction": "Backend Internal", "group": "BTEC", "name": "BTEC Templates", "endpoint": "/api/v1/btec/*", "description": "Manage BTEC result templates for grade formatting"},
    ]

    return templates.TemplateResponse(
        "services.html",
        {"request": request, "user": user, "services": services},
    )


# ─── FIELD MAPPINGS PAGE ───────────────────────────────────────────────────────

@router.get("/mappings", response_class=HTMLResponse)
async def mappings_page(request: Request):
    user = get_current_user(request)
    if not user:
        return _redirect_login("/admin/mappings")
    return templates.TemplateResponse("mappings.html", {"request": request, "user": user})


@router.post("/mappings/clear")
async def clear_service_mapping(request: Request):
    """Clear all field mappings for a specific service from the DB."""
    user = get_current_user(request)
    if not user:
        return {"ok": False, "error": "Not authenticated"}

    body = await request.json()
    service = body.get("service", "").strip()
    if not service:
        return {"ok": False, "error": "service is required"}

    from app.infra.db.session import SessionLocal
    from app.infra.db.models.extension import FieldMapping

    db = SessionLocal()
    try:
        deleted = db.query(FieldMapping).filter(FieldMapping.module_name == service).delete()
        db.commit()
    finally:
        db.close()

    # Also remove the ZOHO_MODULE_* env key
    env_key = f"ZOHO_MODULE_{service.upper()}"
    _write_env_key(env_key, "")

    return {"ok": True, "deleted": deleted}
