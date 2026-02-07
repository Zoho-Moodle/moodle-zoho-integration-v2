# ⚡ Event-Driven Architecture - Final Production Design

## 🎯 Executive Summary

**Production-Ready | Solo-Developer Friendly | Right-Sized for 1,500 Students**

This is the **FINAL** architecture for the Moodle-Zoho Integration system optimized for:
- ✅ **Event-driven** (Zoho Workflows → Webhooks → Backend)
- ✅ **Auto-workflow based** (no manual buttons as main flow)
- ✅ **Maintainable by ONE developer**
- ✅ **Right-sized** (FastAPI + PostgreSQL only, NO Celery/Redis)
- ✅ **Production-ready and sellable**

---

## 🏗️ System Architecture

```
┌──────────────────────────────────────────────────────────────┐
│                    ZOHO CRM (Trigger Engine)                  │
│  Workflow Rules: Create/Update/Delete → Webhook              │
│  Modules: Students, Teachers, Classes, Enrollments, etc      │
└────────────┬─────────────────────────────────────────────────┘
             │ Webhook POST (minimal payload)
             ▼
┌──────────────────────────────────────────────────────────────┐
│               FastAPI Server (24/7 Event Listener)            │
│  POST /v1/events/zoho/* → Event Router                       │
│  - Verify HMAC signature                                      │
│  - Deduplicate (check zoho_events_log)                        │
│  - Queue to BackgroundTask                                    │
└────────────┬─────────────────────────────────────────────────┘
             │ Background processing
             ▼
┌──────────────────────────────────────────────────────────────┐
│                  Service Layer (Business Logic)               │
│  StudentProfileService, FinanceSyncService, etc               │
│  - Fetch full data from Zoho                                  │
│  - Transform & validate                                       │
│  - Call MoodleClient                                          │
│  - Log results                                                │
└────────────┬─────────────────────────────────────────────────┘
             │ API calls
             ▼
┌──────────────────────────────────────────────────────────────┐
│                    Moodle LMS (Consumer)                      │
│  - Receives synced data                                       │
│  - Student Dashboard (shows Zoho data)                        │
│  - Sends grade/enrollment events back → Webhook               │
└────────────┬─────────────────────────────────────────────────┘
             │ Store locally
             ▼
┌──────────────────────────────────────────────────────────────┐
│                 PostgreSQL (Single Database)                  │
│  - Event logs (idempotency)                                   │
│  - Finance data (local copy)                                  │
│  - Configuration (app_settings)                               │
│  - Sync audit trail                                           │
└──────────────────────────────────────────────────────────────┘
```

---

## 🔄 Event Flow (PRIMARY)

### 1. Zoho → Backend → Moodle (Main Flow)

```
Student Created in Zoho:
┌─────────────────┐
│ Admin creates   │
│ student in Zoho │
└────────┬────────┘
         │
         ▼
┌─────────────────────────────────────┐
│ Zoho Workflow Rule triggers:        │
│ - On Create                         │
│ - Condition: Academic_Email != NULL │
│ - Action: Webhook                   │
└────────┬────────────────────────────┘
         │ POST /v1/events/zoho/student
         ▼
┌─────────────────────────────────────┐
│ Backend receives:                   │
│ {                                   │
│   "event_id": "evt_123_456",        │
│   "event_type": "created",          │
│   "module": "BTEC_Students",        │
│   "record_id": "5847596000012345"   │
│ }                                   │
└────────┬────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────┐
│ Event Router (FastAPI):             │
│ 1. Verify HMAC signature            │
│ 2. Check if duplicate (event_id)    │
│ 3. Log to zoho_events_log (pending) │
│ 4. Queue BackgroundTask              │
└────────┬────────────────────────────┘
         │
         ▼ (async - non-blocking)
┌─────────────────────────────────────┐
│ StudentProfileService:              │
│ 1. Fetch full student from Zoho API │
│ 2. Check if exists in Moodle        │
│ 3. Create/update user in Moodle     │
│ 4. Update Student_Moodle_ID in Zoho │
│ 5. Mark event as 'completed'        │
└────────┬────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────┐
│ Student now visible in Moodle!      │
│ Student Dashboard shows profile     │
└─────────────────────────────────────┘
```

### 2. Moodle → Backend → Zoho (Reverse Flow)

```
Teacher Submits Grade in Moodle:
┌─────────────────┐
│ Teacher grades  │
│ assignment      │
└────────┬────────┘
         │
         ▼
┌─────────────────────────────────────┐
│ Moodle Observer fires:              │
│ \mod_assign\event\submission_graded │
│ Sends webhook to Backend            │
└────────┬────────────────────────────┘
         │ POST /v1/events/moodle/grade
         ▼
┌─────────────────────────────────────┐
│ Backend receives:                   │
│ {                                   │
│   "event_id": "moodle_evt_789",     │
│   "event_type": "grade_submitted",  │
│   "entity_type": "grade",           │
│   "entity_id": "12345",             │
│   "student_id": 1234,               │
│   "course_id": 567,                 │
│   "grade": 85.5                     │
│ }                                   │
└────────┬────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────┐
│ GradeSyncService:                   │
│ 1. Convert grade to BTEC level      │
│ 2. Find Zoho student ID              │
│ 3. Create/update BTEC_Grades in Zoho│
│ 4. Mark event as 'completed'        │
└─────────────────────────────────────┘
```

---

## 📋 Zoho Workflow Configuration (MANDATORY)

### All Modules Must Have Workflows

| Module | Workflow Name | Trigger | Webhook URL |
|--------|--------------|---------|-------------|
| BTEC_Students | Student Sync | Create/Update/Delete | /v1/events/zoho/student |
| BTEC_Teachers | Teacher Sync | Create/Update/Delete | /v1/events/zoho/teacher |
| BTEC_Registrations | Registration Sync | Create/Update/Delete | /v1/events/zoho/registration |
| BTEC_Classes | Class Sync | Create/Update/Delete | /v1/events/zoho/class |
| BTEC_Enrollments | Enrollment Sync | Create/Update/Delete | /v1/events/zoho/enrollment |
| BTEC_Payments | Payment Sync | Create/Update/Delete | /v1/events/zoho/payment |
| BTEC_Grades | Grade Sync | Create/Update | /v1/events/zoho/grade |
| BTEC_Units | Unit Sync | Create/Update | /v1/events/zoho/unit |

### Example Workflow Rule (Zoho Deluge)

```deluge
// Workflow: Student Created → Sync to Moodle
// Module: BTEC_Students
// Trigger: On Create
// Condition: Academic_Email IS NOT NULL

webhookURL = "https://your-domain.com/v1/events/zoho/student";
eventID = record.get("id") + "_" + zoho.currenttime.toString("yyyyMMddHHmmss");

payload = {
    "event_id": eventID,
    "event_type": "created",
    "module": "BTEC_Students",
    "record_id": record.get("id"),
    "changed_fields": ["all"],
    "timestamp": zoho.currenttime
};

headers = {
    "Content-Type": "application/json",
    "X-Zoho-Signature": generateHMAC(payload)  // Your HMAC logic
};

response = invokeurl
[
    url: webhookURL
    type: POST
    parameters: payload.toString()
    headers: headers
];

// Log response
info "Webhook sent: " + response;
```

**Critical Rules:**
1. ✅ **Minimal payload** (record_id only, NOT full record)
2. ✅ **Unique event_id** (for deduplication)
3. ✅ **HMAC signature** (for security)
4. ✅ **Async** (don't wait for response)

---

## 🎓 Student Dashboard (Inside Moodle)

### Purpose
Students can view their Zoho data **without Zoho login**.

### Implementation
- **Location**: Moodle local plugin (`local/student_dashboard`)
- **URL**: `https://elearning.abchorizon.com/local/student_dashboard/`
- **Access**: Students see ONLY their own data

### Dashboard Sections (Configurable)

Configured via `app_settings` table:
```json
{
  "show_profile": true,           // Name, email, ID
  "show_academics": true,          // Registrations, programs
  "show_finance": true,            // Fee summary
  "show_payments": true,           // Payment history
  "show_remaining_balance": false, // Optional calculation
  "show_grades": true,             // BTEC grades
  "show_attendance": false         // Future feature
}
```

### Data Source
- **NOT from Zoho API!**
- Reads from local Moodle tables:
  - `moodle_finance_info`
  - `moodle_finance_payments`
  - `grading_definitions`
  - `mdl_user`
  - `mdl_course`

### Sample Dashboard View

```
┌──────────────────────────────────────────────────┐
│ 👤 Student Dashboard - John Smith                │
└──────────────────────────────────────────────────┘

📚 Profile
• Name: John Smith
• Email: john.smith@student.edu
• Student ID: STU-2024-001
• Program: BTEC Level 5 Diploma in IT

💰 Finance Summary
• Total Fee: $10,000
• Scholarship: 20% (-$2,000)
• Net Amount: $8,000
• Total Paid: $6,000
• Remaining: $2,000

💳 Payment History
┌────────────┬────────┬─────────┐
│ Date       │ Amount │ Status  │
├────────────┼────────┼─────────┤
│ Jan 15, 25 │ $2,000 │ ✅ Paid │
│ Feb 15, 25 │ $2,000 │ ✅ Paid │
│ Mar 15, 25 │ $2,000 │ ✅ Paid │
│ Apr 15, 25 │ $2,000 │ ⏳ Due  │
└────────────┴────────┴─────────┘

📊 BTEC Grades
• Unit 1: Pass
• Unit 2: Merit
• Unit 3: Distinction
```

---

## ⚙️ Configuration Management

### Design Philosophy

**🔐 Secrets → ENV ONLY**
- Moodle tokens, Zoho credentials, HMAC secrets
- Never in database
- Never exposed via API

**🎛️ Runtime Settings → `app_settings` Table**
- Feature toggles, behavior flags
- Changeable without redeployment
- Admin-only API access

### Settings Storage

```sql
CREATE TABLE app_settings (
    key TEXT PRIMARY KEY,
    value_json JSONB NOT NULL,
    description TEXT,
    updated_by TEXT,
    updated_at TIMESTAMP DEFAULT NOW()
);
```

### Initial Configuration

```sql
INSERT INTO app_settings (key, value_json, description) VALUES

-- Module Enablement
('modules.enabled', '{
    "BTEC_Students": true,
    "BTEC_Teachers": true,
    "BTEC_Registrations": true,
    "BTEC_Classes": true,
    "BTEC_Enrollments": true,
    "BTEC_Payments": true,
    "BTEC_Grades": true,
    "BTEC_Units": true,
    "BTEC_Attendance": false
}', 'Enable/disable automation per module'),

-- Sync Directions
('sync.directions', '{
    "student_profile": "zoho_to_moodle",
    "finance": "zoho_to_moodle",
    "enrollments": "bidirectional",
    "grades": "moodle_to_zoho",
    "attendance": "moodle_to_zoho"
}', 'Sync direction per entity'),

-- Retry Policy
('retry.policy', '{
    "max_retries": 3,
    "backoff_factor": 2,
    "initial_delay_seconds": 60
}', 'Retry configuration'),

-- Student Dashboard
('student_dashboard.visibility', '{
    "show_profile": true,
    "show_academics": true,
    "show_finance": true,
    "show_payments": true,
    "show_remaining_balance": false,
    "show_grades": true
}', 'Dashboard visibility'),

-- Moodle Roles
('moodle.roles', '{
    "student": 5,
    "teacher": 3,
    "editing_teacher": 4
}', 'Default Moodle role IDs');
```

### Settings API

```python
# GET /v1/settings (Admin-only)
{
  "modules.enabled": {...},
  "sync.directions": {...},
  "retry.policy": {...},
  ...
}

# PUT /v1/settings/modules.enabled
{
  "BTEC_Students": true,
  "BTEC_Attendance": true  // Enable attendance automation
}
```

---

## 🗄️ Database Schema (Event-Driven)

### Event Log Tables (CRITICAL)

```sql
-- Zoho Events (Idempotency + Audit)
CREATE TABLE zoho_events_log (
    id SERIAL PRIMARY KEY,
    event_id TEXT UNIQUE NOT NULL,
    event_type TEXT NOT NULL,      -- created/updated/deleted
    module TEXT NOT NULL,           -- BTEC_Students, etc
    record_id TEXT NOT NULL,        -- Zoho ID
    payload JSONB,
    status TEXT DEFAULT 'pending',  -- pending/processing/completed/failed
    retry_count INT DEFAULT 0,
    error_message TEXT,
    processed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW()
);
CREATE INDEX idx_zoho_event_id ON zoho_events_log(event_id);
CREATE INDEX idx_zoho_status ON zoho_events_log(status);

-- Moodle Events
CREATE TABLE moodle_events_log (
    id SERIAL PRIMARY KEY,
    event_id TEXT UNIQUE NOT NULL,
    event_type TEXT NOT NULL,      -- grade_submitted, enrollment_created
    entity_type TEXT NOT NULL,      -- grade, enrollment
    entity_id TEXT NOT NULL,
    payload JSONB,
    status TEXT DEFAULT 'pending',
    retry_count INT DEFAULT 0,
    error_message TEXT,
    processed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW()
);
CREATE INDEX idx_moodle_event_id ON moodle_events_log(event_id);
```

### Complete Table List

```
Event Tables (NEW - 2):
├── zoho_events_log              # Zoho webhook events
└── moodle_events_log            # Moodle event webhooks

Configuration (NEW - 1):
└── app_settings                 # Runtime configuration

Moodle Data (NEW - 4):
├── moodle_finance_info          # Finance data (1,500 records)
├── moodle_finance_payments      # Payments (~6,000 records)
├── moodle_grading_definitions   # BTEC templates (~200)
└── moodle_sync_log              # Operation audit

Zoho Auth (NEW - 1):
└── zoho_tokens                  # OAuth tokens (1 record)

Existing Tables (16):
├── extension_* (6 tables)
└── sync_* (10 tables)

Total: 24 tables (simple & manageable!)
```

---

## 🚀 Deployment (Single VPS)

```
┌────────────────────────────────────────────────┐
│ VPS (4 CPU, 8GB RAM) - $20-40/month            │
│                                                │
│  ┌──────────────┐                             │
│  │ Nginx        │ (Reverse Proxy + HTTPS)     │
│  │ Port 80/443  │                             │
│  └──────┬───────┘                             │
│         │                                      │
│  ┌──────▼───────┐                             │
│  │ FastAPI      │ (Uvicorn + BackgroundTasks) │
│  │ Port 8001    │ Handles webhooks 24/7       │
│  └──────┬───────┘                             │
│         │                                      │
│  ┌──────▼───────┐                             │
│  │ PostgreSQL   │ (Single DB)                 │
│  │ Port 5432    │                             │
│  └──────────────┘                             │
│                                                │
│  PM2: Auto-restart FastAPI                    │
│  Logs: /var/log/moodle-zoho/                  │
└────────────────────────────────────────────────┘
```

### What We DON'T Need
- ❌ Kubernetes
- ❌ Load Balancer
- ❌ Redis
- ❌ Celery Workers
- ❌ Multiple servers
- ❌ Microservices

---

## 🛠️ CLI Scripts (Bulk Operations)

```bash
# Initial sync (1,500 students)
python manage.py sync --all

# Sync specific module
python manage.py sync --module students

# Retry failed events
python manage.py retry-failed --hours 24

# View event queue status
python manage.py events-status

# Clear completed events (older than 30 days)
python manage.py events-cleanup --days 30
```

---

## ✅ Production Checklist

**Event-Driven Setup:**
- [ ] All Zoho Workflow Rules created (9 modules)
- [ ] Webhooks configured with HMAC signatures
- [ ] Event deduplication tested
- [ ] Retry logic validated

**Student Dashboard:**
- [ ] Moodle plugin installed
- [ ] Visibility settings configured
- [ ] Capability-based access working
- [ ] Data displays correctly

**Configuration:**
- [ ] All secrets in .env (never in DB!)
- [ ] `app_settings` table populated
- [ ] Settings API tested (admin-only)

**Monitoring:**
- [ ] Health check endpoints working
- [ ] Event log retention policy set
- [ ] Error notification system active

**Performance:**
- [ ] Initial sync (1,500 students) < 3 minutes
- [ ] Event processing < 5 seconds
- [ ] Database connection pooling configured

---

## 🎯 Success Metrics

1. **Automation Coverage**: 100% (all create/update/delete events automated)
2. **Event Processing Time**: < 5 seconds per event
3. **Initial Sync Time**: < 3 minutes for 1,500 students
4. **Uptime**: 99%+ (PM2 auto-restart)
5. **Solo Maintainability**: ✅ One developer can manage

**Total Infrastructure Cost**: $20-40/month (single VPS)

---

## 🎤 Selling Points

1. **Fully Automated** - No manual data entry
2. **Real-Time** - Students see updates instantly
3. **Student Portal** - Self-service dashboard in Moodle
4. **Audit Trail** - Every event logged
5. **Easy to Maintain** - One developer can run it
6. **Scalable** - Handles up to 5,000 students (future growth)
7. **Secure** - HMAC webhooks, encrypted secrets
8. **Production-Ready** - Not a prototype!

---

**✅ END OF ARCHITECTURE**

This is the **FINAL, PRODUCTION-READY** architecture optimized for real-world deployment by a solo developer.
