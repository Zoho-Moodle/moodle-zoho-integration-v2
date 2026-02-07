# 🏗️ Event-Driven Moodle-Zoho Integration - Production Architecture

## 📋 Table of Contents
1. [System Overview](#system-overview)
2. [Architecture Principles](#architecture-principles)
3. [Event-Driven Design](#event-driven-design) ⭐ **NEW**
4. [Zoho Workflow Automation](#zoho-workflow-automation) ⭐ **NEW**
5. [Event Router](#event-router) ⭐ **NEW**
6. [Student Dashboard](#student-dashboard) ⭐ **NEW**
7. [Layer Architecture](#layer-architecture)
8. [Project Structure](#project-structure)
9. [Component Design](#component-design)
10. [Database Architecture](#database-architecture)
11. [Configuration Management](#configuration-management) ⭐ **NEW**
12. [API Design](#api-design)
13. [Security Architecture](#security-architecture)
14. [Integration Flows](#integration-flows)
15. [Deployment Architecture](#deployment-architecture)
16. [Migration Strategy](#migration-strategy)

---

## 🎯 System Overview

### Purpose
**Event-driven**, production-ready integration system between:
- **Moodle LMS** (https://elearning.abchorizon.com)
- **Zoho CRM** (BTEC modules: Students, Teachers, Classes, Grades)
- **Microsoft Teams/SharePoint** (via Graph API)

### System Scale (Right-Sized for Production)
- **Students**: 1,500 initial + ~30 every 3-4 months
- **Classes**: 200 max + 5-10 every 3-4 months
- **Daily Operations**: Event-driven (10-50 events/day)
- **Peak Load**: Initial sync only (~1,500 records once via CLI)

### Integration Direction ⭐ **BIDIRECTIONAL**
```
┌─────────────────────────────────────────────────────────────┐
│  Moodle → Backend → Zoho                                    │
│  - User created/updated (Observer → Webhook)                │
│  - Enrollment created (Observer → Webhook)                  │
│  - Grade submitted (Observer → Webhook → BTEC conversion)   │
├─────────────────────────────────────────────────────────────┤
│  Zoho → Backend → Moodle                                    │
│  - Unit created (Workflow → Moodle API)                     │
│  - Program created (Workflow → Moodle API)                  │
│  - Registration created (Workflow → Moodle Enrolment API)   │
└─────────────────────────────────────────────────────────────┘
```

### Architecture Model ⭐ **CRITICAL**
```
┌────────────────────────────────────────────────────┐
│  Runtime: 24/7 FastAPI Server (Uvicorn)            │
│  Triggers: Zoho Workflows → Webhooks ONLY          │
│  Processing: FastAPI BackgroundTasks (async)       │
│  Bulk Ops: CLI Scripts (python manage.py sync)     │
│  Storage: PostgreSQL ONLY (no Redis/Celery)        │
│  Deployment: Single VPS (4 CPU, 8GB RAM)           │
└────────────────────────────────────────────────────┘
```

### Key Requirements
- ✅ **Event-driven** (Zoho Workflows as primary trigger)
- ✅ **Auto-workflow based** (no manual buttons in main flow)
- ✅ **Real-time processing** (FastAPI BackgroundTasks)
- ✅ **Idempotent** (event deduplication built-in)
- ✅ **Maintainable by ONE developer**
- ✅ **Production-ready and sellable**
- ✅ Comprehensive error handling & retry (3 attempts)
- ✅ Audit trail & detailed logging
- ✅ Student Dashboard (read-only inside Moodle)

---

## 🧩 Architecture Principles

### 1. **Event-Driven First** ⭐⭐⭐
- **Zoho Workflows are PRIMARY triggers** (not manual buttons!)
- FastAPI server runs 24/7, reacts to events ONLY
- No polling, no continuous jobs
- Idempotent event processing (deduplication built-in)

### 2. **Solo-Developer Friendly**
- One person can understand, deploy, and maintain
- No Celery, no Redis, no Kubernetes
- Single VPS deployment
- Standard FastAPI + PostgreSQL stack
- Clear troubleshooting paths

### 3. **Right-Sized for 1,500 Students**
- FastAPI BackgroundTasks for event handling
- CLI scripts for bulk operations
- PostgreSQL connection pooling (10 connections)
- Simple retry logic (3 attempts, exponential backoff)

### 4. **Production-Ready & Sellable**
- Complete audit trail
- Proper error handling
- Student-facing dashboard
- Professional logging
- Easy to demo and sell

### 5. **Security**
- Secrets in ENV only (never in DB)
- Runtime settings in simple `app_settings` table
- HMAC webhook verification
- Token encryption
- Input validation

### 6. **Maintainability**
- Clear code structure
- Practical documentation
- Type hints everywhere
- Tests for critical flows only

---

## ⚡ Event-Driven Design

### Core Concept
```
┌──────────────┐       Webhook        ┌──────────────┐
│ Zoho CRM     │ ─────────────────────>│  FastAPI     │
│ (Workflows)  │   POST /v1/events/   │  (24/7)      │
└──────────────┘      zoho/*          └──────┬───────┘
                                              │
┌──────────────┐       Webhook               │ BackgroundTask
│ Moodle LMS   │ ─────────────────────>      │
│ (Observers)  │   POST /v1/events/          ▼
└──────────────┘      moodle/*        ┌──────────────┐
                                       │  Services    │
                                       │  (Execute)   │
                                       └──────┬───────┘
                                              │
                                              ▼
                                       ┌──────────────┐
                                       │ PostgreSQL   │
                                       │ (Event Log)  │
                                       └──────────────┘
```

### Event Flow Rules

**1. Zoho → Backend → Moodle (Main Flow)**
- Zoho Workflow detects change (create/update/delete)
- Sends minimal webhook payload (record_id + event_type only)
- Backend receives event, deduplicates, fetches full data
- Processes via Service → updates Moodle
- Logs everything in `integration_events_log` (source='zoho')

**2. Moodle → Backend → Zoho (Reverse Flow)**
- Moodle Observer detects event (grade_submitted, enrollment_created)
- Sends webhook to Backend
- Backend processes → updates Zoho
- Logs in `integration_events_log` (source='moodle')

**3. Bulk Operations (Initial Sync / Recovery)**
- CLI scripts ONLY: `python manage.py sync --all`
- Not triggered by events
- Manual or scheduled (cron)

### Event Deduplication Strategy

```python
# Every event has unique event_id from source
# Single unified table for ALL events (Zoho + Moodle)

CREATE TABLE integration_events_log (
    id SERIAL PRIMARY KEY,
    event_id TEXT UNIQUE NOT NULL,   -- From source payload
    source TEXT NOT NULL,             -- 'zoho' or 'moodle'
    event_type TEXT NOT NULL,         -- created/updated/deleted/grade_submitted/etc
    module TEXT,                      -- BTEC_Students, etc (Zoho)
    entity_type TEXT,                 -- grade, enrollment (Moodle)
    record_id TEXT NOT NULL,          -- Source record ID
    payload JSONB,                    -- Full webhook payload
    status TEXT DEFAULT 'pending',    -- pending/processing/completed/failed
    retry_count INT DEFAULT 0,
    processed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW()
);

# Before processing, check:
SELECT 1 FROM integration_events_log WHERE event_id = ? AND status = 'completed';
# If exists → skip (already processed)

# Benefits:
# ✅ Single table to monitor ALL events
# ✅ Simpler queries: SELECT * FROM integration_events_log WHERE status = 'failed'
# ✅ Unified retention policy
# ✅ Easier backup/restore
```

---

## 🔄 Zoho Workflow Automation

### ⚠️ IMPORTANT: Zoho API Contract

**This integration follows ZOHO_API_CONTRACT.md strictly.**

**Module Mapping (Zoho API Name → Business Name):**
- `Products` → BTEC Programs *(Note: API name is "Products", not "BTEC_Programs")*
- `BTEC` → BTEC Units *(Source for grading templates)*
- `BTEC_Students` → Students
- `BTEC_Teachers` → Teachers
- `BTEC_Registrations` → Registrations
- `BTEC_Classes` → Classes
- `BTEC_Enrollments` → Enrollments
- `BTEC_Payments` → Payments
- `BTEC_Grades` → Grades *(Header + Learning_Outcomes_Assessm subform)*

**Grading Flow:**
- Template Source: `BTEC` module fields (P1_description...P19_description, M1_description...M9_description, D1_description...D6_description)
- Results Source: Moodle (grades + feedback)
- Storage: `BTEC_Grades` header + `Learning_Outcomes_Assessm` subform (one row per P/M/D criterion)
- Composite Key: `Moodle_Grade_Composite_Key` = student_id + course_id

**Forbidden:**
- ❌ NO `SRM_*` fields (legacy)
- ❌ NO Student subforms except `Learning_Outcomes_Assessm`
- ❌ NO invented field names

---

### MANDATORY: All Modules Must Be Automated

| Module (API Name) | Triggers | Action | Webhook URL |
|-------------------|----------|--------|-------------|
| **BTEC_Students** | Create, Update, Delete | Webhook | `/v1/events/zoho/student` |
| **BTEC_Teachers** | Create, Update, Delete | Webhook | `/v1/events/zoho/teacher` |
| **BTEC_Registrations** | Create, Update, Delete | Webhook | `/v1/events/zoho/registration` |
| **BTEC_Classes** | Create, Update, Delete | Webhook | `/v1/events/zoho/class` |
| **BTEC_Enrollments** | Create, Update, Delete | Webhook | `/v1/events/zoho/enrollment` |
| **BTEC_Payments** | Create, Update, Delete | Webhook | `/v1/events/zoho/payment` |
| **BTEC_Grades** | Create, Update | Webhook | `/v1/events/zoho/grade` |
| **BTEC** (Units) | Create, Update | Webhook | `/v1/events/zoho/unit` |
| **Products** (Programs) | Create, Update | Webhook | `/v1/events/zoho/program` |

**Note:** `BTEC_Attendance` removed - not in API contract.

### Webhook Payload Format (MANDATORY)

**✅ Good Payload (Minimal - REQUIRED):**
```json
{
  "event_id": "evt_1234567890_uuid",
  "event_type": "created",
  "module": "BTEC_Students",
  "record_id": "5847596000000123456",
  "changed_fields": ["First_Name", "Academic_Email"],
  "timestamp": "2026-01-25T10:30:00Z"
}
```

**❌ Bad Payload (Full Record - DON'T DO THIS):**
```json
{
  "event_id": "...",
  "record": {
    // 50+ fields here - waste of bandwidth!
  }
}
```

**Why Minimal Payload?**
- Lighter webhook calls
- Backend fetches latest data anyway (prevents race conditions)
- Easier to debug
- Less coupling

### Zoho Workflow Configuration Example

```
Workflow Name: Student Created → Sync to Moodle
Module: BTEC_Students
Trigger: On Create
Conditions: Academic_Email IS NOT NULL
Actions:
  1. Webhook
     URL: https://your-domain.com/v1/events/zoho/student
     Method: POST
     Headers:
       X-Zoho-Signature: {signature}
     Body (JSON):
       {
         "event_id": "${record.id}_${CURRENT_TIMESTAMP}",
         "event_type": "created",
         "module": "BTEC_Students",
         "record_id": "${record.id}",
         "changed_fields": ["all"],
         "timestamp": "${CURRENT_TIMESTAMP}"
       }
```

### Fallback: Manual Buttons (Secondary Only)

Manual buttons exist ONLY for:
- Testing
- Emergency re-sync
- Admin troubleshooting

**NOT for daily operations!**

---

## 🎯 Event Router

### Entry Points

```python
# app/api/v1/endpoints/events.py

from fastapi import APIRouter, BackgroundTasks, Header, Request
from app.services.event_router import EventRouter

router = APIRouter()

@router.post("/events/zoho/{event_type}")
async def handle_zoho_event(
    event_type: str,  # student, teacher, class, enrollment, payment, grade, etc
    request: Request,
    background_tasks: BackgroundTasks,
    x_zoho_signature: str = Header(None)
):
    """
    Central webhook endpoint for ALL Zoho events.
    Routes to appropriate service based on event_type.
    """
    # 1. Read payload
    payload = await request.json()
    
    # 2. Verify HMAC signature
    verify_webhook_signature(payload, x_zoho_signature)
    
    # 3. Check deduplication
    if is_duplicate_event(payload['event_id']):
        return {"status": "skipped", "reason": "duplicate"}
    
    # 4. Log event as 'pending'
    log_event(payload, status='pending')
    
    # 5. Route to service (background task)
    background_tasks.add_task(
        EventRouter.route_zoho_event,
        event_type=event_type,
        payload=payload
    )
    
    return {
        "status": "accepted",
        "event_id": payload['event_id'],
        "message": "Event queued for processing"
    }

@router.post("/events/moodle/{event_type}")
async def handle_moodle_event(
    event_type: str,  # grade, enrollment
    request: Request,
    background_tasks: BackgroundTasks,
    x_moodle_signature: str = Header(None)
):
    """
    Central webhook endpoint for Moodle events.
    """
    # Similar to Zoho handler
    pass
```

### Event Routing Logic

```python
# app/services/event_router.py

class EventRouter:
    """Routes events to appropriate service."""
    
    @staticmethod
    async def route_zoho_event(event_type: str, payload: dict):
        """Route Zoho webhook to correct service."""
        
        routing_map = {
            'student': StudentProfileService,
            'teacher': TeacherSyncService,
            'registration': EnrollmentSyncService,
            'class': ClassSyncService,
            'enrollment': EnrollmentSyncService,
            'payment': FinanceSyncService,
            'grade': GradeSyncService,
            'unit': BTECTemplateService,
            'attendance': AttendanceSyncService,
        }
        
        service_class = routing_map.get(event_type)
        if not service_class:
            raise ValueError(f"Unknown event_type: {event_type}")
        
        # Initialize service
        service = service_class(db=get_db(), ...)
        
        # Execute based on event_type
        if payload['event_type'] == 'created':
            await service.handle_create(payload)
        elif payload['event_type'] == 'updated':
            await service.handle_update(payload)
        elif payload['event_type'] == 'deleted':
            await service.handle_delete(payload)
        
        # Mark event as completed
        mark_event_completed(payload['event_id'])
    
    @staticmethod
    async def route_moodle_event(event_type: str, payload: dict):
        """Route Moodle webhook to correct service."""
        
        if event_type == 'grade':
            service = GradeSyncService(...)
            await service.handle_grade_from_moodle(payload)
        
        elif event_type == 'enrollment':
            service = EnrollmentSyncService(...)
            await service.handle_enrollment_from_moodle(payload)
```

---

## 🎓 Student Dashboard (Inside Moodle)

### Purpose
Allow students to view their Zoho data **without Zoho access**.

### Location
- Moodle Local Plugin: `local/student_dashboard`
- Accessible via: `https://elearning.abchorizon.com/local/student_dashboard/`

### Features (Configurable via `app_settings`)

```python
student_dashboard_config = {
    "show_profile": True,           # Show full name, email, photo
    "show_academics": True,          # Show registrations, programs
    "show_finance": True,            # Show finance summary
    "show_payments": True,           # Show installment breakdown
    "show_remaining_balance": False, # Optional: calculate remaining
    "show_grades": True,             # Show BTEC grades (P/M/D/R)
    "show_attendance": False,        # Future feature
}
```

### Data Source
- **NOT from Zoho API directly!**
- Reads from local Moodle DB tables:
  - `moodle_finance_info`
  - `moodle_finance_payments`
  - `grading_definitions`
  - `mdl_user` (profile)
  - `mdl_course` (courses)

### Access Control
```php
// Moodle capability check
require_capability('local/student_dashboard:view', $context);

// Students see ONLY their own data
$userid = $USER->id;
$data = get_student_dashboard_data($userid);
```

### Dashboard Sections

**1. Profile**
```
┌────────────────────────────────────┐
│ 👤 John Smith                      │
│ 📧 john.smith@student.edu          │
│ 🆔 Student ID: STU-2024-001        │
└────────────────────────────────────┘
```

**2. Academic Registrations**
```
┌────────────────────────────────────┐
│ 📚 Current Programs                │
│ • BTEC Level 5 Diploma             │
│ • Start: Sep 2024 | End: Jun 2026  │
└────────────────────────────────────┘
```

**3. Finance Summary** (if enabled)
```
┌────────────────────────────────────┐
│ 💰 Finance                         │
│ Total Fee: $10,000                 │
│ Scholarship: 20% ($2,000)          │
│ Net Amount: $8,000                 │
│ Paid: $6,000                       │
│ Remaining: $2,000                  │
└────────────────────────────────────┘
```

**4. Payment History**
```
┌────────────────────────────────────┐
│ 💳 Payments                        │
│ 1. Jan 15, 2025 - $2,000 (Paid)   │
│ 2. Feb 15, 2025 - $2,000 (Paid)   │
│ 3. Mar 15, 2025 - $2,000 (Paid)   │
│ 4. Apr 15, 2025 - $2,000 (Due)    │
└────────────────────────────────────┘
```

**5. BTEC Grades**
```
┌────────────────────────────────────┐
│ 📊 Grades                          │
│ Unit 1: Pass                       │
│ Unit 2: Merit                      │
│ Unit 3: Distinction                │
└────────────────────────────────────┘
```

### Implementation Notes
- Pure PHP (no React/Vue needed)
- Bootstrap 5 for styling
- AJAX for section loading (optional)
- Responsive design
- Print-friendly version

---

## 🏛️ Layer Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                      Presentation Layer                      │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │ REST API     │  │ Zoho Widget  │  │ Admin UI     │     │
│  │ (FastAPI)    │  │ (React/Vue)  │  │ (Dashboard)  │     │
│  └──────────────┘  └──────────────┘  └──────────────┘     │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│                      Application Layer                       │
│  ┌──────────────────────────────────────────────────────┐  │
│  │                   Service Layer                       │  │
│  │  • UserSyncService                                    │  │
│  │  • StudentProfileService                              │  │
│  │  • FinanceSyncService                                 │  │
│  │  • EnrollmentSyncService                              │  │
│  │  • BTECTemplateService                                │  │
│  │  • GradeSyncService                                   │  │
│  │  • SharePointSyncService                              │  │
│  └──────────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────────┐  │
│  │                   Use Cases / Jobs                    │  │
│  │  • SyncStudentProfileJob                              │  │
│  │  • SyncFinanceInfoJob                                 │  │
│  │  • ProcessGradeEventJob                               │  │
│  └──────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│                      Domain Layer                            │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │   Models     │  │   Schemas    │  │  Validators  │     │
│  │ (Entities)   │  │  (Pydantic)  │  │              │     │
│  └──────────────┘  └──────────────┘  └──────────────┘     │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│                   Infrastructure Layer                       │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │ MoodleClient │  │  ZohoClient  │  │  MSGraphAPI  │     │
│  └──────────────┘  └──────────────┘  └──────────────┘     │
│  ┌──────────────┐  ┌──────────────────────────────────┐   │
│  │  PostgreSQL  │  │  FastAPI BackgroundTasks          │   │
│  │  (Database)  │  │  (No Celery/Redis needed!)        │   │
│  └──────────────┘  └──────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

---

## 📁 Project Structure

```
backend/
├── app/
│   ├── __init__.py
│   ├── main.py                          # FastAPI application
│   ├── config.py                        # Configuration management
│   │
│   ├── api/                             # Presentation Layer
│   │   ├── __init__.py
│   │   ├── deps.py                      # Dependencies (auth, db, etc.)
│   │   └── v1/
│   │       ├── __init__.py
│   │       └── endpoints/
│   │           ├── __init__.py
│   │           ├── extension.py         # Extension API (existing)
│   │           ├── events.py            # ⭐ NEW: Event webhooks (Zoho + Moodle)
│   │           ├── settings.py          # ⭐ NEW: Runtime settings API
│   │           ├── sync.py              # Manual sync (fallback only!)
│   │           ├── students.py          # Student management
│   │           ├── finance.py           # Finance operations
│   │           ├── enrollments.py       # Enrollment operations
│   │           ├── grades.py            # Grade operations
│   │           └── health.py            # Health checks
│   │
│   ├── services/                        # Application Layer
│   │   ├── __init__.py
│   │   ├── base_service.py              # Base service class
│   │   ├── event_router.py              # ⭐ NEW: Event routing logic
│   │   ├── student_profile_service.py   # Student profile sync
│   │   ├── finance_sync_service.py      # Finance data sync
│   │   ├── enrollment_sync_service.py   # Enrollment sync (bidirectional)
│   │   ├── btec_template_service.py     # BTEC grading templates
│   │   ├── grade_sync_service.py        # Grade sync (Moodle → Zoho)
│   │   ├── sharepoint_sync_service.py   # SharePoint/Teams integration
│   │   └── settings_service.py          # ⭐ NEW: Settings management
│   │
│   ├── tasks/                           # Background Tasks (FastAPI)
│   │   ├── __init__.py
│   │   ├── event_tasks.py               # ⭐ Event processing tasks
│   │   └── utils.py                     # Task helpers
│   │
│   ├── cli/                             # ⭐ NEW: CLI Scripts
│   │   ├── __init__.py
│   │   ├── manage.py                    # Main CLI entry (like Django)
│   │   ├── sync_all.py                  # Bulk sync all data
│   │   ├── sync_students.py             # Sync students only
│   │   ├── sync_finance.py              # Sync finance only
│   │   └── reset_events.py              # Reset failed events
│   │
│   ├── domain/                          # Domain Layer
│   │   ├── __init__.py
│   │   ├── models/                      # SQLAlchemy models (existing)
│   │   │   ├── __init__.py
│   │   │   ├── extension.py
│   │   │   ├── sync.py
│   │   │   └── moodle.py                # New: Moodle-specific models
│   │   ├── schemas/                     # Pydantic schemas
│   │   │   ├── __init__.py
│   │   │   ├── student.py
│   │   │   ├── finance.py
│   │   │   ├── enrollment.py
│   │   │   ├── grade.py
│   │   │   ├── btec_template.py
│   │   │   └── sync_request.py
│   │   └── validators/                  # Business logic validators
│   │       ├── __init__.py
│   │       ├── student_validator.py
│   │       └── finance_validator.py
│   │
│   ├── infra/                           # Infrastructure Layer
│   │   ├── __init__.py
│   │   ├── db/                          # Database (existing)
│   │   │   ├── __init__.py
│   │   │   ├── base.py
│   │   │   ├── session.py
│   │   │   └── models/
│   │   │
│   │   ├── moodle/                      # ⭐ NEW: Moodle Integration
│   │   │   ├── __init__.py
│   │   │   ├── client.py                # Main Moodle client
│   │   │   ├── models.py                # Moodle entity models
│   │   │   ├── exceptions.py            # Moodle-specific exceptions
│   │   │   ├── user_api.py              # User management
│   │   │   ├── course_api.py            # Course management
│   │   │   ├── enrollment_api.py        # Enrollment operations
│   │   │   ├── finance_api.py           # Finance custom tables
│   │   │   ├── grading_api.py           # BTEC grading system
│   │   │   └── utils.py                 # Helper functions
│   │   │
│   │   ├── zoho/                        # Zoho CRM client (existing)
│   │   │   └── ...
│   │   │
│   │   ├── msgraph/                     # ⭐ NEW: Microsoft Graph API
│   │   │   ├── __init__.py
│   │   └── msgraph/                     # ⭐ NEW: Microsoft Graph API (simple)
│   │       ├── __init__.py
│   │       ├── client.py                # Graph API client
│   │       └── exceptions            # Core utilities
│   │   ├── __init__.py
│   │   ├── auth_extension.py            # HMAC auth (existing)
│   │   ├── config.py                    # Settings
│   │   ├── security.py                  # Security utilities
│   │   ├── logging.py                   # ⭐ NEW: Structured logging
│   │   ├── retry.py                     # ⭐ NEW: Retry decorators
│   │   ├── circuit_breaker.py           # ⭐ NEW: Circuit breaker
│   │   └── exceptions.py                # Custom exceptions
│   │
│   └── utils/                           # Helper utilities
│       ├── __init__.py
│       ├── date_utils.py
│       ├── string_utils.py
│       └── validation_utils.py
│
├── tests/                               # Test suiimple structured logging
│   │   ├── retry.py                     # ⭐ NEW: Simple retry (3 attempts)
│   ├── unit/
│   │   ├── test_moodle_client.py
│   │   ├── test_services.py
│   │   └── test_validators.py
│   ├── integration/
│   │   ├── test_sync_flow.py
│   │   └── test_api_endpoints.py
│   └── e2e/
│       └── test_complete_sync.py
│
├── migrations/                          # Alembic migrations
│   └── versions/
│
├── scripts/                             # Utility scripts
│   ├── migrate_from_legacy.py
│   ├── validate_data.py
│   └── seed_test_data.py
│
├── docs/                                # Documentation
│   ├── api/
│   ├── architecture/
│   └── deployment/
│
├── .env.example                         # Environment variables template
├── .env                                 # Local environment (gitignored)
├── requirements.txt                     # Python dependencies
├── pyproject.toml                       # Project metadata
├── docker-compose.yml                   # Local development
└── Dockerfile                           # Production deployment
```

---

## 🔧 Component Design

### 1. **MoodleClient** (Infrastructure Layer)

```python
# app/infra/moodle/client.py

from typing import Optional, List, Dict, Any
from .exceptions import MoodleAPIError, MoodleConnectionError
from .user_api import MoodleUserAPI
from .course_api import MoodleCourseAPI
from .enrollment_api import MoodleEnrollmentAPI
from .finance_api import MoodleFinanceAPI
from .grading_api import MoodleGradingAPI

class MoodleClient:
    """
    Main Moodle Web Services client.
    Handles authentication, connection pooling, retry logic.
    """
    
    def __init__(
        self,
        base_url: str,
        token: str,
        timeout: int = 30,
        max_retries: int = 3,
        pool_size: int = 10
    ):
        self.base_url = base_url.rstrip('/')
        self.token = token
        self.timeout = timeout
        self.max_retries = max_retries
        
        # Session with connection pooling
        self._session = self._create_session(pool_size)
        
        # API modules
        self.users = MoodleUserAPI(self)
        self.courses = MoodleCourseAPI(self)
        self.enrollments = MoodleEnrollmentAPI(self)
        self.finance = MoodleFinanceAPI(self)
        self.grading = MoodleGradingAPI(self)
    
    def call(
        self,
        function: str,
        params: Dict[str, Any] = None,
        method: str = 'POST'
    ) -> Dict[str, Any]:
        """
        Call Moodle Web Service function with retry logic.
        """
        # Implementation with retry decorator
        pass
    
    def health_check(self) -> bool:
        """Check Moodle connection health."""
        pass
    
    def close(self):
        """Close session and cleanup."""
        pass
```

#### MoodleUserAPI

```python
# app/infra/moodle/user_api.py

class MoodleUserAPI:
    """User management operations."""
    
    def __init__(self, client: 'MoodleClient'):
        self.client = client
    
    def create_user(
        self,
        username: str,
        email: str,
        firstname: str,
        lastname: str,
        password: Optional[str] = None,
        **kwargs
    ) -> int:
        """
        Create new user in Moodle.
        Returns: userid
        """
        pass
    
    def update_user(self, userid: int, **fields) -> bool:
        """Update user fields."""
        pass
    
    def get_user_by_email(self, email: str) -> Optional[Dict]:
        """Find user by email."""
        pass
    
    def get_user_by_username(self, username: str) -> Optional[Dict]:
        """Find user by username."""
        pass
    
    def get_users(
        self,
        criteria: Dict[str, Any],
        limit: int = 100
    ) -> List[Dict]:
        """Search users with criteria."""
        pass
```

#### MoodleCourseAPI

```python
# app/infra/moodle/course_api.py

class MoodleCourseAPI:
    """Course management operations."""
    
    def create_course(
        self,
        fullname: str,
        shortname: str,
        categoryid: int,
        startdate: int,
        **kwargs
    ) -> int:
        """Create new course. Returns: courseid"""
        pass
    
    def get_course(self, courseid: int) -> Optional[Dict]:
        """Get course by ID."""
        pass
    
    def search_courses(self, criteria: Dict[str, Any]) -> List[Dict]:
        """Search courses."""
        pass
    
    def update_course(self, courseid: int, **fields) -> bool:
        """Update course fields."""
        pass
```

#### MoodleEnrollmentAPI

```python
# app/infra/moodle/enrollment_api.py

class MoodleEnrollmentAPI:
    """Enrollment operations."""
    
    def enroll_user(
        self,
        userid: int,
        courseid: int,
        roleid: int = 5,  # 5 = Student
        timestart: Optional[int] = None,
        timeend: Optional[int] = None
    ) -> bool:
        """Enroll user in course."""
        pass
    
    def unenroll_user(self, userid: int, courseid: int) -> bool:
        """Unenroll user from course."""
        pass
    
    def get_enrolled_users(
        self,
        courseid: int,
        roleid: Optional[int] = None
    ) -> List[Dict]:
        """Get all enrolled users in course."""
        pass
    
    def get_user_enrollments(self, userid: int) -> List[Dict]:
        """Get all enrollments for a user."""
        pass
```

#### MoodleFinanceAPI

```python
# app/infra/moodle/finance_api.py

class MoodleFinanceAPI:
    """Custom finance tables operations."""
    
    def update_finance_info(
        self,
        userid: int,
        finance_data: Dict[str, Any]
    ) -> bool:
        """Update financeinfo table via custom web service."""
        pass
    
    def add_payment(
        self,
        financeinfoid: int,
        payment: Dict[str, Any]
    ) -> int:
        """Add payment record. Returns: payment_id"""
        pass
    
    def delete_payment(self, paymentid: int) -> bool:
        """Delete payment record."""
        pass
    
    def get_finance_info(self, userid: int) -> Optional[Dict]:
        """Get finance info for user."""
        pass
```

#### MoodleGradingAPI

```python
# app/infra/moodle/grading_api.py

class MoodleGradingAPI:
    """BTEC grading system operations."""
    
    def create_grading_definition(
        self,
        name: str,
        areaid: int,
        criteria: List[Dict[str, Any]]
    ) -> int:
        """Create BTEC grading definition. Returns: definitionid"""
        pass
    
    def update_grading_definition(
        self,
        definitionid: int,
        criteria: List[Dict[str, Any]]
    ) -> bool:
        """Update grading definition criteria."""
        pass
    
    def get_grading_definition(
        self,
        definitionid: int
    ) -> Optional[Dict]:
        """Get grading definition by ID."""
        pass
    
    def create_grading_area(
        self,
        contextid: int,
        component: str,
        areaname: str,
        activemethod: str = 'btec'
    ) -> int:
        """Create grading area. Returns: areaid"""
        pass
```

---

### 2. **Service Layer**

```python
# app/services/base_service.py

from abc import ABC, abstractmethod
from typing import Optional, Dict, Any
from sqlalchemy.orm import Session
from app.infra.moodle.client import MoodleClient
from app.infra.zoho.client import ZohoClient
from app.core.logging import get_logger

class BaseService(ABC):
    """Base service class with common functionality."""
    
    def __init__(
        self,
        db: Session,
        moodle_client: MoodleClient,
        zoho_client: ZohoClient
    ):
        self.db = db
        self.moodle = moodle_client
        self.zoho = zoho_client
        self.logger = get_logger(self.__class__.__name__)
    
    @abstractmethod
    def execute(self, **kwargs) -> Dict[str, Any]:
        """Main execution method - to be implemented by subclasses."""
        pass
    
    def log_sync(
        self,
        entity_type: str,
        entity_id: str,
        action: str,
        status: str,
        details: Optional[Dict] = None
    ):
        """Log sync operation to database."""
        pass
```

#### StudentProfileService

```python
# app/services/student_profile_service.py

from typing import List, Dict, Any, Optional
from .base_service import BaseService
from app.domain.schemas.student import StudentProfileSchema

class StudentProfileService(BaseService):
    """
    Syncs student profiles from Zoho to Moodle.
    Handles: profile data, academic info, and photo.
    """
    
    def execute(
        self,
        student_ids: Optional[List[str]] = None,
        sync_mode: str = 'all'  # 'profile', 'academics', 'all'
    ) -> Dict[str, Any]:
        """
        Sync student profiles.
        
        Args:
            student_ids: Optional list of Zoho student IDs
            sync_mode: What to sync (profile/academics/all)
        
        Returns:
            Sync statistics
        """
        stats = {
            'total': 0,
            'success': 0,
            'failed': 0,
            'skipped': 0,
            'errors': []
        }
        
        try:
            # 1. Fetch students from Zoho
            students = self._fetch_students_from_zoho(student_ids)
            stats['total'] = len(students)
            
            # 2. Process each student
            for student in students:
                try:
                    if sync_mode in ['profile', 'all']:
                        self._sync_profile(student)
                    
                    if sync_mode in ['academics', 'all']:
                        self._sync_academics(student)
                    
                    stats['success'] += 1
                    self.log_sync(
                        entity_type='student',
                        entity_id=student['id'],
                        action='sync_profile',
                        status='success'
                    )
                    
                except Exception as e:
                    stats['failed'] += 1
                    stats['errors'].append({
                        'student_id': student.get('id'),
                        'error': str(e)
                    })
                    self.logger.error(
                        f"Failed to sync student {student.get('id')}: {e}"
                    )
        
        except Exception as e:
            self.logger.error(f"Student sync failed: {e}")
            raise
        
        return stats
    
    def _fetch_students_from_zoho(
        self,
        student_ids: Optional[List[str]]
    ) -> List[Dict]:
        """Fetch students from Zoho with pagination."""
        if student_ids:
            return [
                self.zoho.get_record('BTEC_Students', sid)
                for sid in student_ids
            ]
        else:
            return self.zoho.get_all_records(
                'BTEC_Students',
                fields=['id', 'Full_Name', 'Academic_Email', 'Student_Moodle_ID', ...]
            )
    
    def _sync_profile(self, student: Dict):
        """Sync basic profile data."""
        email = student.get('Academic_Email')
        if not email:
            raise ValueError("Missing Academic_Email")
        
        # Find or create user in Moodle
        moodle_user = self.moodle.users.get_user_by_username(email)
        
        if not moodle_user:
            # Create new user
            userid = self.moodle.users.create_user(
                username=email,
                email=email,
                firstname=student.get('First_Name', ''),
                lastname=student.get('Last_Name', ''),
                country=student.get('Country', ''),
                city=student.get('City', '')
            )
        else:
            # Update existing user
            userid = moodle_user['id']
            self.moodle.users.update_user(
                userid=userid,
                firstname=student.get('First_Name'),
                lastname=student.get('Last_Name'),
                country=student.get('Country'),
                city=student.get('City')
            )
        
        # Update Student_Moodle_ID in Zoho
        if not student.get('Student_Moodle_ID'):
            self.zoho.update_record(
                'BTEC_Students',
                student['id'],
                {'Student_Moodle_ID': userid}
            )
    
    def _sync_academics(self, student: Dict):
        """Sync academic registrations."""
        # Implementation
        pass
```

#### FinanceSyncService

```python
# app/services/finance_sync_service.py

class FinanceSyncService(BaseService):
    """
    Syncs financial data from Zoho to Moodle.
    Handles: finance info + payment installments.
    """
    
    def execute(
        self,
        student_ids: Optional[List[str]] = None,
        force_update: bool = False
    ) -> Dict[str, Any]:
        """Sync finance data for students."""
        stats = {'total': 0, 'success': 0, 'failed': 0, 'errors': []}
        
        # Get students with finance data
        students = self._get_students_with_finance(student_ids)
        stats['total'] = len(students)
        
        for student in students:
            try:
                # Check if update needed (MD5 hash)
                if not force_update and not self._needs_update(student):
                    continue
                
                # Sync finance info
                self._sync_finance_info(student)
                
                # Sync payment installments
                self._sync_payments(student)
                
                stats['success'] += 1
                
            except Exception as e:
                stats['failed'] += 1
                stats['errors'].append({
                    'student_id': student.get('id'),
                    'error': str(e)
                })
        
        return stats
    
    def _sync_finance_info(self, student: Dict):
        """Sync 13 finance fields."""
        userid = student.get('Student_Moodle_ID')
        if not userid:
            raise ValueError("Missing Student_Moodle_ID")
        
        # ⚠️ MD5 USAGE NOTE:
        # MD5 is used ONLY for change detection, NOT for security or integrity guarantees.
        # We hash finance data to avoid unnecessary updates when data hasn't changed.
        # This is acceptable because:
        # 1. Data is not sensitive (finance summary only)
        # 2. We're not validating authenticity
        # 3. Goal is performance optimization (skip unchanged records)
        
        finance_data = {
            'scholarship': student.get('Scholarship'),
            'scholarship_reason': student.get('Scholarship_Reason'),
            'scholarship_percentage': student.get('Scholarship_Percentage'),
            'currency': student.get('Currency'),
            'amount_transferred': student.get('Amount_Transferred'),
            'payment_method': student.get('Payment_Method'),
            'payment_mode': student.get('Payment_Mode'),
            'bank_name': student.get('Bank_Name'),
            'bank_holder': student.get('Bank_Holder'),
            'registration_fees': student.get('Registration_Fees'),
            'invoice_reg_fees': student.get('Invoice_Reg_Fees'),
            'total_amount': student.get('Total_Amount'),
            'discount_amount': student.get('Discount_Amount'),
            'zoho_id': student['id']
        }
        
        self.moodle.finance.update_finance_info(userid, finance_data)
    
    def _sync_payments(self, student: Dict):
        """Sync 8 payment installments."""
        # Implementation for payment installments
        pass
```

---

### 3. **Job Queue System**

```python
# app/jobs/celery_app.py

from celery import Celery
from app.core.config import settings

celery_app = Celery(
    'moodle_zoho_integration',
    broker=settings.CELERY_BROKER_URL,
    backend=settings.CELERY_RESULT_BACKEND
)Background Tasks** (Simple FastAPI BackgroundTasks)

**Why No Celery/Redis?**
- 1,500 students = small dataset (sync in 2-3 minutes max)
- 30 new students every 3-4 months = very low frequency
- FastAPI BackgroundTasks is perfect for this scale!

```python
# app/tasks/sync_tasks.py

from fastapi import BackgroundTasks
from typing import Optional, List
import logging

logger = logging.getLogger(__name__)

async def sync_student_profile_task(
    student_id: str,
    db_session_factory
):
    """
    Background task to sync single student.
    Runs in background thread - no blocking!
    """
    db = db_session_factory()
    try:
        from app.services.student_profile_service import StudentProfileService
        
        service = StudentProfileService(db, ...)
        result = service.execute(student_ids=[student_id])
        
        logger.info(f"Student {student_id} synced: {result}")
        return result
        
    except Exception as e:
        logger.error(f"Failed to sync student {student_id}: {e}")
        raise
    finally:
        db.close()

async def sync_all_students_task(db_session_factory):
    """
    Sync all students in background.
    For 1,500 students, this takes 2-3 minutes max.
    """
    db = db_session_factory()
    try:
        from app.services.student_profile_service import StudentProfileService
        
        service = StudentProfileService(db, ...)
        result = service.execute()  # Syncs all
        
        logger.info(f"All students synced: {result}")
        return result
        
    except Exception as e:
        logger.error(f"Failed to sync all students: {e}")
        raise
    finally:
        db.close()

# Usage in endpoint:
# @router.post("/sync/students")
# async def sync_students(
#     background_tasks: BackgroundTasks,
#     db: Session = Depends(get_db)
# ):
#     background_tasks.add_task(sync_all_students_task, get_db_factory())
#     return {"status": "started", "message": "Sync running in background"}
├── extension_modules
├── extension_field_mappings
├── extension_sync_runs
└── extension_run_history

Sync Tables (Existing - 10 tables):
├── sync_students
├── sync_classes
├── sync_enrollments
├── sync_teachers               # 1,500 students max
├── sync_classes                # 200 classes max
├── sync_enrollments
├── sync_teachers
├── sync_units
├── sync_registrations
├── sync_payments
├── sync_grades
├── sync_attendance
└── sync_activities

Moodle Tables (NEW - Simplified - 4 tables):
├── moodle_finance_info          # Finance data (1,500 records max)
├── moodle_finance_payments      # Payment installments (~6,000 records max)
├── moodle_grading_definitions   # BTEC templates (~200 records)
└── moodle_sync_log              # Operation logs

Zoho Tables (NEW - 1 table):
└── zoho_tokens                  # Token storage & refresh (1 record!)

Event Processing (NEW - 1 table): ⭐
└── integration_events_log       # ALL events (Zoho + Moodle) unified

Configuration (NEW - 1 table):
└── app_settings                 # Runtime configuration (JSON key-value)

Note: No caching tables needed for 1,500 students - queries are fast enough!

```sql
-- migrations/versions/2026_01_25_add_moodle_tables.py

def upgrade():
    # 1. moodle_users_cache
    op.create_table(
        'moodle_users_cache',
        sa.Column('id', sa.Integer(), primary_key=True),
        sa.Column('moodle_userid', sa.Integer(), unique=True),
        sa.Column('username', sa.String(255), index=True),
        sa.Column('email', sa.String(255), index=True),
        sa.Column('firstname', sa.String(255)),
        sa.Column('lastname', sa.String(255)),
        sa.Column('data', sa.JSON()),  # Full user data
        sa.Column('last_sync', sa.DateTime()),
        sa.Column('created_at', sa.DateTime(), server_default=sa.func.now()),
        sa.Column('updated_at', sa.DateTime(), onupdate=sa.func.now())
    )
    
    # 2. moodle_finance_info
    op.create_table(
        'moodle_finance_info',
        sa.Column('id', sa.Integer(), primary_key=True),
        sa.Column('moodle_userid', sa.Integer(), unique=True),
        sa.Column('zoho_student_id', sa.String(50)),
        sa.Column('scholarship', sa.String(100)),
        sa.Column('scholarship_percentage', sa.Numeric(5, 2)),
        sa.Column('currency', sa.String(10)),
        sa.Column('total_amount', sa.Numeric(10, 2)),
        sa.Column('discount_amount', sa.Numeric(10, 2)),
        # ... 13 finance fields
        sa.Column('data_hash', sa.String(32)),  # MD5 for change detection (NOT security!)
        # ⚠️ MD5 USAGE: Only for detecting data changes to avoid unnecessary updates.
        # NOT for security or integrity guarantees.
        sa.Column('last_sync', sa.DateTime()),
        sa.Column('created_at', sa.DateTime()),
        sa.Column('updated_at', sa.DateTime())
    )
    
    # 3. moodle_finance_payments
    op.create_table(
        'moodle_finance_payments',
        sa.Column('id', sa.Integer(), primary_key=True),
        sa.Column('finance_info_id', sa.Integer(), 
                  sa.ForeignKey('moodle_finance_info.id', ondelete='CASCADE')),
        sa.Column('payment_name', sa.String(100)),
        sa.Column('amount', sa.Numeric(10, 2)),
        sa.Column('payment_date', sa.Date()),
        sa.Column('invoice_number', sa.String(100)),
        sa.Column('created_at', sa.DateTime()),
        sa.Column('updated_at', sa.DateTime())
    )
    
    # 4. zoho_tokens
    op.create_table(
        'zoho_tokens',
        sa.Column('id', sa.Integer(), primary_key=True),
        sa.Column('token_type', sa.String(50)),  # 'access', 'refresh'
        sa.Column('token_value', sa.Text()),  # Encrypted
        sa.Column('expires_at', sa.DateTime()),
        sa.Column('created_at', sa.DateTime()),
        sa.Column('updated_at', sa.DateTime())
    )
    
    # ... More tables
```

---

## 🔌 API Design

### REST API Endpoints

```
Extension API (Existing):
POST   /v1/extension/tenants/register
GET    /v1/extension/tenants/{tenant_id}
POST   /v1/extension/integrations
GET    /v1/extension/integrations/{integration_id}/modules
POST   /v1/extension/field-mappings
GET    /v1/extension/sync/status
POST   /v1/extension/sync/run

Moodle API (NEW):
GET    /v1/moodle/users
GET    /v1/moodle/users/{userid}
POST   /v1/moodle/users
PUT    /v1/moodle/users/{userid}
GET    /v1/moodle/courses
GET    /v1/moodle/courses/{courseid}
POST   /v1/moodle/courses
GET    /v1/moodle/enrollments
POST   /v1/moodle/enrollments
DELETE /v1/moodle/enrollments/{enrollmentid}

Sync API (NEW):
POST   /v1/sync/students/profile
POST   /v1/sync/students/finance
POST   /v1/sync/students/all
POST   /v1/sync/enrollments/moodle-to-zoho
POST   /v1/sync/enrollments/zoho-to-moodle
POST   /v1/sync/grades
POST   /v1/sync/btec-templates
GET    /v1/sync/status/{job_id}
GET    /v1/sync/history

Finance API (NEW):
GET    /v1/finance/{userid}
PUT    /v1/finance/{userid}
POST   /v1/finance/{userid}/payments
DELETE /v1/finance/payments/{payment_id}

Grades API (NEW):
POST   /v1/grades/sync
GET    /v1/grades/{studentid}
POST   /v1/grades/webhook  # For Moodle events

Health & Monitoring:
GET    /health
GET    /health/moodle
GET    /health/zoho
GET    /health/msgraph
GET    /metrics
```

### API Request/Response Examples

```json
// POST /v1/sync/students/profile
{
  "student_ids": ["5847596000000123456", "5847596000000789012"],
  "sync_mode": "all",
  "async": true
}

// Response
{
  "status": "accepted",
  "job_id": "550e8400-e29b-41d4-a716-446655440000",
  "message": "Sync job queued",
  "check_status_url": "/v1/sync/status/550e8400-e29b-41d4-a716-446655440000"
}

// GET /v1/sync/status/{job_id}
{
  "job_id": "550e8400-e29b-41d4-a716-446655440000",
  "status": "completed",
  "progress": {
    "total": 2,
    "success": 2,
    "failed": 0,
    "current": 2
  },
  "result": {
    "synced_students": [
      {
        "zoho_id": "5847596000000123456",
        "moodle_userid": 1234,
        "status": "success"
      },
      {
        "zoho_id": "5847596000000789012",
        "moodle_userid": 5678,
        "status": "success"
      }
    ]
  },
  "started_at": "2026-01-24T10:00:00Z",
  "completed_at": "2026-01-24T10:02:15Z"
}
```

---

## 🔐 Security Architecture

### 1. **Secrets Management**

```python
# app/core/config.py

from pydantic_settings import BaseSettings
from functools import lru_cache

class Settings(BaseSettings):
    # Environment
    ENVIRONMENT: str = "development"
    
    # Moodle
    MOODLE_BASE_URL: str
    MOODLE_TOKEN: str  # From environment variable
    
    # Zoho
    ZOHO_CLIENT_ID: str
    ZOHO_CLIENT_SECRET: str
    ZOHO_REFRESH_TOKEN: str
    ZOHO_DC: str = "https://www.zohoapis.com"
    
    # Microsoft
    MS_TENANT_ID: str
    MS_CLIENT_ID: str
    MS_CLIENT_SECRET: str
    
    # Database
    DATABASE_URL: str
    
    # Redis
    REDIS_URL: str = "redis://localhost:6379/0"
    
    # Celery
    CELERY_BROKER_URL: str = "redis://localhost:6379/1"
    CELERY_RESULT_BACKEND: str = "redis://localhost:6379/2"
    
    # JWT Secret
    JWNo Redis needed for 1,500 students!
    # No Celery needed - FastAPI BackgroundTasks is enough!
        env_file = ".env"
        case_sensitive = True

@lru_cache()
def get_settings():
    return Settings()
```

### 2. **Token Encryption**

```python
# app/core/security.py

from cryptography.fernet import Fernet
from app.core.config import get_settings

class TokenEncryption:
    """Encrypt/decrypt sensitive tokens."""
    
    def __init__(self):
        settings = get_settings()
        self.cipher = Fernet(settings.ENCRYPTION_KEY.encode())
    
    def encrypt(self, plaintext: str) -> str:
        """Encrypt token."""
        return self.cipher.encrypt(plaintext.encode()).decode()
    
    def decrypt(self, ciphertext: str) -> str:
        """Decrypt token."""
        return self.cipher.decrypt(ciphertext.encode()).decode()
```

### 3. **API Authentication**

```python
# app/api/deps.py

from fastapi import Depends, HTTPException, Header
from sqlalchemy.orm import Session
from app.core.auth_extension import verify_hmac_signature
from app.infra.db.session import get_db
from app.domain.models.extension import ExtensionTenant

async def get_current_tenant(
    authorization: str = Header(...),
    db: Session = Depends(get_db)
) -> ExtensionTenant:
    """Verify HMAC and return tenant."""
    # Implementation
    pass
```

---

## 🔄 Integration Architecture

### Data Flow Diagrams

#### 1. Student Profile Sync (Zoho → Moodle)

```
┌─────────────┐
│ Zoho CRM    │
│ BTEC_Students│
└──────┬──────┘
       │ API Call (paginated)
       ↓
┌──────────────────────┐
│ ZohoClient           │
│ - Token refresh      │
│ - Pagination handler │
│ - Error retry        │
└──────┬───────────────┘
       │ Student records
       ↓
┌──────────────────────────────┐
│ StudentProfileService        │
│ 1. Validate data             │
│ 2. Transform fields          │
│ 3. Check MD5 hash            │
│ 4. Prepare Moodle format     │
└──────┬───────────────────────┘
       │ Processed data
       ↓
┌──────────────────────┐
│ MoodleClient         │
│ - Find/create user   │
│ - Update profile     │
│ - Handle errors      │
└──────┬───────────────┘
       │ Update Moodle ID
       ↓
┌──────────────────────┐
│ ZohoClient           │
│ Update Student_      │
│ Moodle_ID field      │
└──────┬───────────────┘
       │ Log operation
       ↓
┌──────────────────────┐
│ PostgreSQL           │
│ - Sync log           │
│ - Cache data         │
└──────────────────────┘
```

#### 2. Grade Sync (Moodle → Zoho)

```
┌─────────────────────┐
│ Moodle Gradebook    │
│ submission_graded   │
└──────┬──────────────┘
       │ Event/Observer
       ↓
┌──────────────────────────────┐
│ FastAPI Endpoint             │
│ POST /v1/events/moodle/grade │
└──────┬───────────────────────┘
       │ Queue BackgroundTask
       ↓
┌──────────────────────────────────────┐
│ GradeSyncService                     │
│ 1. Fetch grading template from BTEC │
│    (P1_description...P19, M1...M9,   │
│     D1...D6)                         │
│ 2. Map Moodle grades to P/M/D levels │
│ 3. Get student/course Zoho IDs       │
│ 4. Prepare BTEC_Grades header        │
│ 5. Build Learning_Outcomes_Assessm   │
│    subform (one row per criterion)   │
└──────┬───────────────────────────────┘
       │ Grade data + subform
       ↓
┌──────────────────────────────────┐
│ ZohoClient                       │
│ Create/Update BTEC_Grades        │
│ - Header: Student, Class, Unit   │
│ - Subform: Learning_Outcomes_    │
│   Assessm (LO_Code, LO_Title,    │
│   LO_Score, LO_Feedback)         │
│ - Composite Key: student_id +    │
│   course_id                      │
└──────┬───────────────────────────┘
       │ Log result
       ↓
┌──────────────────────┐
│ PostgreSQL           │
│ Save sync history    │
└──────────────────────┘
```

---

## 🚀 Deployment Architecture

### Production Setup

```
                    ┌────────────────┐
                    │   Cloudflare   │
                    │   (CDN + WAF)  │
                    └────────┬───────┘
                             │ HTTPS
                    ┌────────▼───────┐
                    │  Load Balancer │
                     (Simple & Right-Sized!)

```
                    ┌────────────────┐
                    │   Cloudflare   │
                    │   (Optional)   │
                    └────────┬───────┘
                             │ HTTPS
                    ┌────────▼────────────────┐
                    │   Single VPS/VM         │
                    │   (4 CPU, 8GB RAM)      │
                    │                         │
                    │  ┌──────────────────┐  │
                    │  │  Nginx           │  │
                    │  │  (Reverse Proxy) │  │
                    │  └────────┬─────────┘  │
                    │           │             │
                    │  ┌────────▼─────────┐  │
                    │  │  FastAPI         │  │
                    │  │  (Uvicorn)       │  │
                    │  │  Port 8001       │  │
                    │  └────────┬─────────┘  │
                    │           │             │
                    │  ┌────────▼─────────┐  │
                    │  │  PostgreSQL      │  │
                    │  │  Port 5432       │  │
                    │  └──────────────────┘  │
                    │                         │
                    │  Simple Logs to File    │
                    │  + Process Manager      │
                    └─────────────────────────┘

Total Cost: ~$20-40/month (DigitalOcean/Linode VPS)
No need for: Kubernetes, Load Balancer, Redis, Celery Workers!
```

**Process Manager Options:**
1. **PM2** (Recommended - Easy to use)
   ```bash
   npm install -g pm2
   pm2 start "uvicorn app.main:app --host 0.0.0.0 --port 8001" --name moodle-zoho
   pm2 startup  # Auto-start on reboot
   pm2 save
   ```

2. **systemd** (Production - More robust)
   ```ini
   # /etc/systemd/system/moodle-zoho.service
   [Unit]
   Description=Moodle-Zoho Integration
   After=network.target postgresql.service
   
   [Service]
   Type=simple
   User=www-data
   WorkingDirectory=/opt/moodle-zoho/backend
   Environment="PATH=/opt/moodle-zoho/venv/bin"
   ExecStart=/opt/moodle-zoho/venv/bin/uvicorn app.main:app --host 0.0.0.0 --port 8001
   Restart=always
   
   [Install]
   WantedBy=multi-user.target
   ```

3. **Supervisor** (Alternative)
   ```ini
   # /etc/supervisor/conf.d/moodle-zoho.conf
   [program:moodle-zoho]
   command=/opt/moodle-zoho/venv/bin/uvicorn app.main:app --host 0.0.0.0 --port 8001
   directory=/opt/moodle-zoho/backend
   autostart=true
   autorestart=true
   user=www-data
   ```

Choose based on your preference - all work well!

**Why This Works:**
- 1,500 students = tiny dataset
- Single server handles 10,000+ students easily
- FastAPI BackgroundTasks for async work
- PostgreSQL connection pooling  - Simplified!

```yaml
# docker-compose.yml - Just 2 services!

version: '3.8'

services:
  app:
    build: .
    ports:
      - "8001:8001"
    environment:
      - DATABASE_URL=postgresql://user:pass@db:5432/moodle_zoho
      - MOODLE_BASE_URL=${MOODLE_BASE_URL}
      - MOODLE_TOKEN=${MOODLE_TOKEN}
      - ZOHO_CLIENT_ID=${ZOHO_CLIENT_ID}
      - ZOHO_CLIENT_SECRET=${ZOHO_CLIENT_SECRET}
    depends_on:
      - db
    volumes:
      - ./app:/app/app
    command: uvicorn app.main:app --host 0.0.0.0 --port 8001 --reload
  
  db:
    image: postgres:15-alpine
    environment:
      - POSTGRES_USER=user
      - POSTGRES_PASSWORD=pass
      - POSTGRES_DB=moodle_zoho
    volumes:
      - postgres_data:/var/lib/postgresql/data
    ports:
      - "5432:5432"

volumes:
  postgres_data:

# That's it! No Redis, No Celery, No complexity!
# For 1,500 students, this is perfect.
### Logging Strategy

```python
# app/core/logging.py

import logging
import json
from typing import Any, Dict
from datetime import datetime

class StructuredLogger:
    """Structured JSON logger."""
    
    def __init__(self, name: str):
        self.logger = logging.getLogger(name)
        self.logger.setLevel(logging.INFO)
        
        # JSON formatter
        handler = logging.StreamHandler()
        handler.setFormatter(JSONFormatter())
        self.logger.addHandler(handler)
    
    def log(
        self,
        level: str,
        message: str,
        extra: Dict[str, Any] = None
    ):
        """Log structured message."""
        log_data = {
            'timestamp': datetime.utcnow().isoformat(),
            'level': level,
            'message': message,
            'service': 'moodle-zoho-integration',
            **(extra or {})
        }
        
        getattr(self.logger, level.lower())(
            json.dumps(log_data)
        )
```

### Health Checks

```python
# app/api/v1/endpoints/health.py

from fastapi import APIRouter, Depends
from sqlalchemy.orm import Session
from app.infra.db.session import get_db
from app.infra.moodle.client import MoodleClient
from app.infra.zoho.client import ZohoClient

router = APIRouter()

@router.get("/health")
async def health_check():
    """Basic health check."""
    return {"status": "healthy", "timestamp": datetime.utcnow()}

@router.get("/health/moodle")
async def moodle_health(db: Session = Depends(get_db)):
    """Check Moodle connection."""
    try:
        moodle = MoodleClient(...)
        is_healthy = moodle.health_check()
        return {
            "service": "moodle",
            "status": "healthy" if is_healthy else "unhealthy",
            "url": moodle.base_url
        }
    except Exception as e:
        return {
            "service": "moodle",
            "status": "unhealthy",
            "error": str(e)
        }

@router.get("/health/zoho")
async def zoho_health():
    """Check Zoho connection."""
    # Implementation
    pass

@router.get("/metrics")
async def prometheus_metrics():
    """Prometheus metrics endpoint."""
    # Implementation
    pass
```

---

## 🔄 Migration Strategy

### Phase 1: Setup (Week 1)
- ✅ Create project structure
- ✅ Setup environment configuration
- ✅ Database migrations
- ✅ MoodleClient implementation
- ✅ Testing framework

### Phase 2: Core Services (Week 2-3)
- ✅ StudentProfileService
- ✅ FinanceSyncService
- ✅ EnrollmentSyncService
- ✅ Unit tests for services

### Phase 3: Job Queue (Week 4)
- ✅ Celery setup
- ✅ Background jobs
- ✅ Scheduled tasks
- ✅ Monitoring

### Phase 4: Additional Services (Week 5)
- ✅ BTECTemplateService
- ✅ GradeSyncService
- ✅ SharePointSyncService
- ✅ ObserverService

### Phase 5: Testing & Validation (Week 6)
- ✅ Integration tests
- ✅ E2E tests
- ✅ Comparison with legacy
- ✅ Performance testing

### Phase 6: Deployment (Week 7)
- ✅ Production setup
- ✅ Parallel run with legacy
- ✅ Data validation
- ✅ Gradual migration (Simplified Timeline)

### Phase 1: Core Setup (Week 1)
- ✅ Enhance MoodleClient (already exists!)
- ✅ Database migrations (4 new tables only)
- ✅ Environment configuration
- ✅ Basic testing

### Phase 2: Main Services (Week 2)
- ✅ StudentProfileService (main one)
- ✅ FinanceSyncService
- ✅ EnrollmentSyncService
- ✅ Background task wrapper (FastAPI)

### Phase 3: Additional Features (Week 3)
- ✅ BTECTemplateService
- ✅ GradeSyncService
- ✅ Simple retry logic
- ✅ Good logging

### Phase 4: Testing (Week 4)
- ✅ Test with real data (100 students first)
- ✅ Then test full 1,500 students
- ✅ Verify vs legacy system
- ✅ Fix any bugs

### Phase 5: Deployment (Week 5)
- ✅ Deploy to single VPS
- ✅ Setup Nginx + HTTPS
- ✅ Run parallel with legacy
- ✅ Monitor for 1 week

### Phase 6: Cutover (Week 6)
- ✅ Final validation
- ✅ Switch DNS/traffic
- ✅ Keep legacy as backup
- ✅ Done! 🎉

**Total: 6 weeks instead of 8** (simpler = faster!)

---

## 🎛️ Optional Features (Smart but Not Required)

These features are **NOT mandatory** - the system works perfectly without them!
However, they provide extra flexibility for advanced use cases.

### 1. Per-Module Workflow Flags ⭐ (Optional)

Add granular control over which Zoho workflows are active:

```sql
-- Add to app_settings table
INSERT INTO app_settings (key, value_json, description) VALUES
(
    'workflows.auto_enabled',
    '{
        "BTEC_Students.created": true,
        "BTEC_Students.updated": true,
        "BTEC_Students.deleted": false,
        "BTEC_Payments.created": true,
        "BTEC_Payments.updated": true,
        "BTEC_Payments.deleted": false,
        "BTEC_Grades.created": true,
        "BTEC_Grades.updated": true
    }',
    'Enable/disable specific event workflows'
);
```

**Usage in Event Router:**
```python
class EventRouter:
    def process_event(self, source, module, event_type, payload):
        workflow_key = f"{module}.{event_type}"
        
        if not self.settings.is_workflow_enabled(workflow_key):
            logger.info(f"Workflow {workflow_key} disabled via settings")
            self.mark_event_skipped(payload['event_id'])
            return {"status": "skipped", "reason": "workflow_disabled"}
        
        # Process event normally...
```

**Benefits:**
- ✅ Emergency kill switch per event type
- ✅ Toggle workflows without changing Zoho
- ✅ Gradual rollout of new features
- ✅ Testing in production safely

**API Endpoint:**
```python
# PUT /v1/settings/workflows.auto_enabled
{
    "BTEC_Payments.deleted": true  # Enable payment deletion events
}
```

---

### 2. Dry-Run Mode 🧪 (Optional)

Test events without actually executing them:

```sql
INSERT INTO app_settings (key, value_json, description) VALUES
(
    'system.dry_run',
    '{
        "enabled": false,
        "modules": []  # Empty = all modules when enabled
    }',
    'Dry-run mode: validate and log events without execution'
);
```

**Implementation:**
```python
class EventRouter:
    def route_event(self, source, event_type, payload):
        dry_run_config = self.settings.get('system.dry_run', {})
        is_dry_run = dry_run_config.get('enabled', False)
        
        if is_dry_run:
            # Validate event structure
            self.validate_event_payload(payload)
            
            # Log what WOULD happen
            logger.info(f"[DRY-RUN] Event: {payload['event_id']}")
            logger.info(f"[DRY-RUN] Would process: {source}.{event_type}")
            logger.info(f"[DRY-RUN] Payload: {json.dumps(payload, indent=2)}")
            
            # Mark as completed (dry-run)
            self.mark_event_completed(
                payload['event_id'],
                dry_run=True,
                result={"status": "dry_run_success"}
            )
            
            return {"status": "dry_run_success"}
        
        # Normal execution
        return self.process_event_real(payload)
```

**Benefits:**
- ✅ Test Zoho workflows safely
- ✅ Debug event payloads
- ✅ Validate integration before going live
- ✅ Train staff without affecting data

**Enable Dry-Run:**
```bash
# Via API
curl -X PUT https://api.example.com/v1/settings/system.dry_run \
  -H "Authorization: Bearer ..." \
  -d '{"enabled": true}'

# Via SQL (emergency)
UPDATE app_settings 
SET value_json = '{"enabled": true}' 
WHERE key = 'system.dry_run';
```

---

### 3. Event Retention Policy 🗑️ (Optional)

Automatically cleanup old events:

```sql
INSERT INTO app_settings (key, value_json, description) VALUES
(
    'events.retention',
    '{
        "completed_days": 90,
        "failed_days": 365,
        "cleanup_enabled": true
    }',
    'Retention policy for integration_events_log'
);
```

**Cleanup Script:**
```python
# app/cli/cleanup_events.py

def cleanup_old_events():
    retention = settings.get('events.retention', {})
    
    if not retention.get('cleanup_enabled', False):
        logger.info("Event cleanup disabled")
        return
    
    # Delete old completed events
    completed_cutoff = datetime.now() - timedelta(
        days=retention.get('completed_days', 90)
    )
    deleted_completed = db.execute(
        "DELETE FROM integration_events_log "
        "WHERE status = 'completed' AND created_at < :cutoff",
        {"cutoff": completed_cutoff}
    ).rowcount
    
    # Delete old failed events (keep longer for analysis)
    failed_cutoff = datetime.now() - timedelta(
        days=retention.get('failed_days', 365)
    )
    deleted_failed = db.execute(
        "DELETE FROM integration_events_log "
        "WHERE status = 'failed' AND created_at < :cutoff",
        {"cutoff": failed_cutoff}
    ).rowcount
    
    logger.info(f"Cleaned up {deleted_completed} completed, {deleted_failed} failed events")
```

**Cron Job:**
```bash
# Run daily at 2 AM
0 2 * * * cd /opt/moodle-zoho && python manage.py cleanup-events
```

---

### 4. Rate Limiting (Optional)

Protect against event floods:

```sql
INSERT INTO app_settings (key, value_json, description) VALUES
(
    'events.rate_limit',
    '{
        "enabled": false,
        "max_per_minute": 100,
        "max_per_hour": 1000
    }',
    'Rate limiting for incoming webhooks'
);
```

**Middleware:**
```python
from fastapi import Request, HTTPException
from datetime import datetime, timedelta
import asyncio

# Simple in-memory counter (for single server)
event_counter = {
    'minute': {'count': 0, 'reset_at': None},
    'hour': {'count': 0, 'reset_at': None}
}

async def rate_limit_middleware(request: Request, call_next):
    limits = settings.get('events.rate_limit', {})
    
    if not limits.get('enabled', False):
        return await call_next(request)
    
    now = datetime.now()
    
    # Check minute limit
    if event_counter['minute']['reset_at'] and now > event_counter['minute']['reset_at']:
        event_counter['minute'] = {'count': 0, 'reset_at': now + timedelta(minutes=1)}
    
    if event_counter['minute']['count'] >= limits.get('max_per_minute', 100):
        raise HTTPException(429, "Rate limit exceeded (per minute)")
    
    # Check hour limit
    if event_counter['hour']['reset_at'] and now > event_counter['hour']['reset_at']:
        event_counter['hour'] = {'count': 0, 'reset_at': now + timedelta(hours=1)}
    
    if event_counter['hour']['count'] >= limits.get('max_per_hour', 1000):
        raise HTTPException(429, "Rate limit exceeded (per hour)")
    
    # Increment counters
    event_counter['minute']['count'] += 1
    event_counter['hour']['count'] += 1
    
    return await call_next(request)
```

---

## ⚠️ Important Notes on Optional Features

1. **Start Simple**: Deploy without optional features first
2. **Add as Needed**: Enable features when you encounter the specific need
3. **Monitor Impact**: Some features add complexity - use judiciously
4. **Document Changes**: Update team when enabling new features
5. **Test Thoroughly**: Always test optional features in dev environment first

**Current Recommendation**: 
- ✅ Use Feature Flags (simple, high value)
- ⏭️ Skip Dry-Run for now (add later if needed)
- ⏭️ Skip Rate Limiting (not needed for 1,500 students)
- ✅ Use Event Retention (good housekeeping)



---

## 🎛️ Optional Features (Smart but Not Required)

### 1. Feature Flags per Module (Optional)

Add granular control over Zoho auto-workflows:

```sql
-- Add to app_settings
INSERT INTO app_settings (key, value_json, description) VALUES
(
    'workflows.auto_enabled',
    '{
        "BTEC_Students.created": true,
        "BTEC_Students.updated": true,
        "BTEC_Students.deleted": false,
        "BTEC_Payments.created": true,
        "BTEC_Grades.created": true
    }',
    'Enable/disable specific event workflows'
);
```

Usage in Event Router:
```python
def process_event(source, module, event_type, payload):
    key = f"{module}.{event_type}"
    if not settings.is_workflow_enabled(key):
        logger.info(f"Workflow {key} disabled, skipping")
        mark_event_skipped(payload['event_id'])
        return
    
    # Process event...
```

**Benefits:**
- Toggle workflows without changing Zoho
- Emergency kill switch per event type
- Gradual rollout of new features

### 2. Dry-Run Mode (Optional)

Test events without execution:

```sql
INSERT INTO app_settings (key, value_json, description) VALUES
(
    'system.dry_run',
    '{
        "enabled": false,
        "modules": ["BTEC_Payments"]  # Dry-run only these
    }',
    'Dry-run mode: validate and log, no execution'
);
```

Implementation:
```python
class EventRouter:
    def route_event(self, source, event_type, payload):
        dry_run_config = self.settings.get('system.dry_run', {})
        is_dry_run = dry_run_config.get('enabled', False)
        
        if is_dry_run:
            # Validate only
            self.validate_event(payload)
            logger.info(f"[DRY-RUN] Would process: {payload}")
            mark_event_completed(payload['event_id'], dry_run=True)
            return {"status": "dry_run_success"}
        
        # Normal execution
        return self.process_event_real(payload)
```

**Benefits:**
- Test Zoho workflows safely
- Debug event payloads
- Validate integration before going live

**Note:** These features are **optional** - the system works perfectly without them!nal sync working

2. **Performance**
   - ✅ Sync time < 50% of legacy
   - ✅ API response time < 500ms
   - ✅ Handle 10,000 students

3. **Reliability**
   - ✅ 99.9% uptime
   - ✅ Automatic retry on failures
   - ✅ Graceful error handling

4. **Security**
   - ✅ No hard-coded credentials
   - ✅ Encrypted token storage
   - ✅ Audit trail for all operations

5. **Maintainability**
   - ✅ 80%+ test coverage
   - ✅ Clear documentation
   - ✅ Easy to extend

---

## 🎯 Next Steps

**Immediate Actions:**
1. Review and approve architecture
2. Setup development environment
3. Start Phase 1: MoodleClient implementation
4. Create first service (StudentProfileService)
5. Write unit tests

**Ready to proceed?** 🚀
 (Right-Sized)
   - ✅ Sync 1,500 students in < 3 minutes
   - ✅ API response time < 500ms
   - ✅ Can handle up to 5,000 students (future growth)

3. **Reliability**
   - ✅ Good uptime (single server with PM2 auto-restart)
   - ✅ Automatic retry on failures (3 attempts)
   - ✅ Clear error messages & logg (Most Important!)
   - ✅ Simple code (no fancy patterns)
   - ✅ Clear documentation
   - ✅ Easy to debug
   - ✅ One person can maintain it!