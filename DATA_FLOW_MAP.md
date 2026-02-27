# 🗺️ Complete Data Flow Map — Moodle-Zoho Integration v3

> Generated: 2026-02-20  
> Covers: Backend (FastAPI/Python) + Moodle Plugin (PHP)  
> Database: PostgreSQL (backend) + MariaDB/MySQL (Moodle)

---

## Table of Contents

1. [System Overview Diagram](#1-system-overview-diagram)
2. [Entry Points from Zoho](#2-entry-points-from-zoho)
3. [All Backend API Endpoints](#3-all-backend-api-endpoints)
4. [Moodle → Backend API Calls](#4-moodle--backend-api-calls)
5. [Detailed Sync Flow per Entity](#5-detailed-sync-flow-per-entity)
6. [Service → Database Table Mapping](#6-service--database-table-mapping)
7. [PostgreSQL Table Inventory](#7-postgresql-table-inventory)
8. [Moodle Database Table Inventory](#8-moodle-database-table-inventory)
9. [Data Transformation & Field Mapping Layers](#9-data-transformation--field-mapping-layers)
10. [File Path Index](#10-file-path-index)

---

## 1. System Overview Diagram

```
╔══════════════════════════════════════════════════════════════════════════╗
║                         ZOHO CRM                                        ║
║  Modules: BTEC_Students · BTEC_Teachers · BTEC_Classes                  ║
║           BTEC_Enrollments · BTEC_Registrations · BTEC_Payments         ║
║           BTEC_Grades · BTEC (Units) · Products (Programs)              ║
╚══════════╦═══════════════════════════════════════════════╦══════════════╝
           ║  Zoho Workflow Webhooks                        ║  Zoho CRM API
           ║  (event-driven, per record change)             ║  (ZohoClient READ)
           ▼                                                ▼
╔══════════════════════════════════════════════════════════════════════════╗
║                     BACKEND API  (FastAPI / Uvicorn)                    ║
║  Port: 8001  |  Prefix: /api/v1  |  DB: PostgreSQL                      ║
║                                                                          ║
║  ┌─────────────┐  ┌──────────────┐  ┌────────────┐  ┌───────────────┐  ║
║  │  API Layer  │  │ Ingress Layer│  │   Domain   │  │ Service Layer │  ║
║  │  24 files   │→ │  /ingress/   │→ │  /domain/  │→ │  /services/   │  ║
║  │ /endpoints/ │  │  parsers     │  │  Pydantic  │  │  sync logic   │  ║
║  └─────────────┘  └──────────────┘  └────────────┘  └──────┬────────┘  ║
║                                                              │           ║
║  ┌──────────────────────────────────────────────────────────▼────────┐  ║
║  │                    Infrastructure Layer                            │  ║
║  │  PostgreSQL (SQLAlchemy)  │  ZohoClient (OAuth2)  │ MoodleClient  │  ║
║  └────────────────────────────────────────────────────────────────────┘  ║
╚══════╦═══════════════════════════════════════════════╦══════════════════╝
       ║  Webhook Events (HTTP POST)                    ║  Moodle Web Service API
       ║  to /api/v1/webhooks                           ║  (core_user_*, core_course_*)
       ▼                                                ▼
╔══════════════════════════════════════════════════════════════════════════╗
║                 MOODLE PLUGIN  (local_moodle_zoho_sync)                 ║
║  Moodle 4.x  |  PHP  |  DB: MariaDB/MySQL                               ║
║                                                                          ║
║  ┌──────────────────┐  ┌───────────────────┐  ┌─────────────────────┐  ║
║  │  Event Observers │  │  Webhook Sender   │  │   Admin UI Pages    │  ║
║  │  user_created    │  │  cURL + Retry(3)  │  │   Event Logs        │  ║
║  │  user_updated    │  │  event_id (UUID)  │  │   Grade Monitor     │  ║
║  │  enrol_created   │  │  event_logger     │  │   Dashboard         │  ║
║  │  enrol_deleted   │  └───────────────────┘  └─────────────────────┘  ║
║  │  grade_updated   │                                                    ║
║  │  submission_graded│ ┌───────────────────┐  ┌─────────────────────┐  ║
║  └──────────────────┘  │  Scheduled Tasks  │  │  Student Dashboard  │  ║
║                        │  retry_failed     │  │  (20% complete)     │  ║
║                        │  cleanup_logs     │  │                     │  ║
║                        │  sync_missing     │  │                     │  ║
║                        │  health_monitor   │  └─────────────────────┘  ║
║                        └───────────────────┘                            ║
╚══════════════════════════════════════════════════════════════════════════╝
```

---

## 2. Entry Points from Zoho

### 2.1 Zoho Workflow Webhooks (Event-Driven — PRIMARY)

These are triggered automatically by Zoho CRM Workflow Rules when records change.

| Zoho Module | Trigger | Backend Endpoint | File |
|---|---|---|---|
| `BTEC_Students` | Insert / Update | `POST /api/v1/events/zoho/student` | `endpoints/events.py` |
| `BTEC_Enrollments` | Insert / Update / Delete | `POST /api/v1/events/zoho/enrollment` | `endpoints/events.py` |
| `BTEC_Grades` | Insert / Update | `POST /api/v1/events/zoho/grade` | `endpoints/events.py` |
| `BTEC_Payments` | Insert / Update | `POST /api/v1/events/zoho/payment` | `endpoints/events.py` |
| `BTEC_Classes` | Insert | `POST /api/v1/classes/create` | `endpoints/create_course.py` |
| *(Student Dashboard)* | Update Student | `POST /api/v1/webhooks/student-dashboard/student_updated` | `endpoints/student_dashboard_webhooks.py` |
| *(Student Dashboard)* | Create Registration | `POST /api/v1/webhooks/student-dashboard/registration_created` | `endpoints/student_dashboard_webhooks.py` |
| *(Student Dashboard)* | Record Payment | `POST /api/v1/webhooks/student-dashboard/payment_recorded` | `endpoints/student_dashboard_webhooks.py` |
| *(Student Dashboard)* | Create Class | `POST /api/v1/webhooks/student-dashboard/class_created` | `endpoints/student_dashboard_webhooks.py` |

**Security:** All Zoho webhooks carry an `X-Zoho-Signature` HMAC-SHA256 header,
verified in `app/core/security.py → ZohoHMACVerifier`.

### 2.2 Zoho Scheduled / Manual Bulk Sync (Batch — via CLI)

These are one-time or scheduled Python scripts called directly on the server.

| Script | Purpose | Writes To |
|---|---|---|
| `backend/sync_students_from_zoho.py` | Bulk student sync | `students` table |
| `backend/initial_sync.py` | Initial full sync | All tables |
| `backend/quick_sync_students.py` | Fast student sync | `students` table |

**How:** Script calls Zoho API via `ZohoClient`, then calls `POST /api/v1/sync/*` endpoints or directly invokes services.

### 2.3 Zoho Sigma Widget / Extension API (Configuration)

A Zoho Sigma embedded widget calls these endpoints to configure the integration.

| HTTP Method | Endpoint | Purpose |
|---|---|---|
| GET/POST | `/api/v1/extension/tenants/*` | Manage tenants |
| GET/POST | `/api/v1/extension/settings/*` | Moodle/Zoho config |
| GET/POST | `/api/v1/extension/mappings/*` | Field mappings |
| GET/POST | `/api/v1/extension/runs/*` | Sync history / retry |

**Auth:** HMAC-SHA256 per-request signature via `app/core/auth_extension.py`.

### 2.4 Zoho Deluge Function Button (Manual Trigger)

A button inside Zoho UI can call:

| Action | Endpoint | Purpose |
|---|---|---|
| Create Moodle Course | `POST /api/v1/classes/create` | Create course + enroll users |

---

## 3. All Backend API Endpoints

Registered in `backend/app/api/v1/router.py`. Full prefix is `/api/v1/`.

### 3.1 Sync Endpoints (Zoho → Backend → DB)

| Method | Path | Handler File | Entity |
|---|---|---|---|
| POST | `/sync/students` | `endpoints/sync_students.py` | BTEC_Students |
| POST | `/sync/programs` | `endpoints/sync_programs.py` | Products (Programs) |
| POST | `/sync/classes` | `endpoints/sync_classes.py` | BTEC_Classes |
| POST | `/sync/enrollments` | `endpoints/sync_enrollments.py` | BTEC_Enrollments |
| POST | `/sync/registrations` | `endpoints/sync_registrations.py` | BTEC_Registrations |
| POST | `/sync/payments` | `endpoints/sync_payments.py` | BTEC_Payments |
| POST | `/sync/units` | `endpoints/sync_units.py` | BTEC (Units) |
| POST | `/sync/grades` | `endpoints/sync_grades.py` | BTEC_Grades |

### 3.2 Event Router (Webhook Entry Points)

| Method | Path | Handler File | Triggered By |
|---|---|---|---|
| POST | `/events/zoho/student` | `endpoints/events.py` | Zoho Workflow |
| POST | `/events/zoho/enrollment` | `endpoints/events.py` | Zoho Workflow |
| POST | `/events/zoho/grade` | `endpoints/events.py` | Zoho Workflow |
| POST | `/events/zoho/payment` | `endpoints/events.py` | Zoho Workflow |
| POST | `/events/moodle/user_created` | `endpoints/moodle_events.py` | Moodle Observer |
| POST | `/events/moodle/user_updated` | `endpoints/moodle_events.py` | Moodle Observer |
| POST | `/events/moodle/enrollment` | `endpoints/moodle_events.py` | Moodle Observer |
| POST | `/events/moodle/grade_updated` | `endpoints/moodle_events.py` | Moodle Observer |

### 3.3 Webhook Receiver (Moodle Plugin → Backend)

| Method | Path | Handler File | Description |
|---|---|---|---|
| POST | `/webhooks` | `endpoints/webhooks.py` | Main Moodle webhook receiver |

Handles: `user_created`, `user_updated`, `enrollment_created`, `enrollment_deleted`, `grade_updated`, `course_created`, `course_updated`.

### 3.4 Student Dashboard Webhooks (Zoho → Moodle via Backend)

| Method | Path | Handler File | Description |
|---|---|---|---|
| POST | `/webhooks/student-dashboard/student_updated` | `endpoints/student_dashboard_webhooks.py` | Zoho student data → Moodle WS |
| POST | `/webhooks/student-dashboard/registration_created` | `endpoints/student_dashboard_webhooks.py` | Zoho registration → Moodle WS |
| POST | `/webhooks/student-dashboard/payment_recorded` | `endpoints/student_dashboard_webhooks.py` | Zoho payment → Moodle WS |
| POST | `/webhooks/student-dashboard/class_created` | `endpoints/student_dashboard_webhooks.py` | Zoho class → Moodle WS |
| POST | `/webhooks/student-dashboard/enrollment_created` | `endpoints/student_dashboard_webhooks.py` | Zoho enrollment → Moodle WS |

### 3.5 Moodle Ingestion Endpoints (Moodle → Backend DB)

| Method | Path | Handler File | Description |
|---|---|---|---|
| POST | `/moodle/users` | `endpoints/moodle_users.py` | Sync Moodle users into backend |
| POST | `/moodle/enrollments` | `endpoints/moodle_enrollments.py` | Sync Moodle enrollments |
| POST | `/moodle/grades` | `endpoints/moodle_grades.py` | Sync Moodle grades |

### 3.6 Course Creation (Zoho → Moodle via Backend)

| Method | Path | Handler File | Description |
|---|---|---|---|
| POST | `/classes/create` | `endpoints/create_course.py` | Create course in Moodle + update Zoho |

### 3.7 BTEC Templates

| Method | Path | Handler File | Description |
|---|---|---|---|
| POST | `/btec/templates/sync` | `endpoints/btec_templates.py` | Sync BTEC grading templates from Zoho |

### 3.8 Extension API (Configuration)

| Method | Path | Handler File |
|---|---|---|
| GET/POST/DELETE | `/extension/tenants/*` | `endpoints/extension_tenants.py` |
| GET/POST/PUT | `/extension/settings/*` | `endpoints/extension_settings.py` |
| GET/POST/PUT/DELETE | `/extension/mappings/*` | `endpoints/extension_mappings.py` |
| GET/POST | `/extension/runs/*` | `endpoints/extension_runs.py` |

### 3.9 Utility Endpoints

| Method | Path | Handler File | Description |
|---|---|---|---|
| GET | `/health` | `endpoints/health.py` | Health check |
| GET/POST | `/debug/*` | `endpoints/debug_enhanced.py` | Zoho data debugging |

---

## 4. Moodle → Backend API Calls

The Moodle Plugin sends data to the Backend in two directions.

### 4.1 Event Observer → Webhook Sender → Backend

Every time a Moodle event fires, `observer.php` calls `webhook_sender.php`, which POSTs to the backend.

```
Moodle LMS Event
    │
    └─► observer.php (catches event)
            │
            ├─► data_extractor.php (queries Moodle DB)
            │
            └─► webhook_sender::send_webhook_with_logging()
                    │
                    ├─► event_logger::log_event()  [writes mdl_local_mzi_event_log]
                    │
                    └─► HTTP POST → Backend /api/v1/webhooks
                            │
                            └─► Response 200 → event_logger::update_event_status('sent')
                                Error       → event_logger::update_event_status('failed')
```

| Observer Method | Moodle Event | Backend Endpoint | Auth |
|---|---|---|---|
| `observer::user_created()` | `\core\event\user_created` | `POST /api/v1/webhooks` (event_type=user_created) | Bearer token |
| `observer::user_updated()` | `\core\event\user_updated` | `POST /api/v1/webhooks` (event_type=user_updated) | Bearer token |
| `observer::enrollment_created()` | `\core\event\user_enrolment_created` | `POST /api/v1/webhooks` (event_type=enrollment_created) | Bearer token |
| `observer::enrollment_deleted()` | `\core\event\user_enrolment_deleted` | `POST /api/v1/webhooks` (event_type=enrollment_deleted) | Bearer token |
| `observer::grade_updated()` | `\core\event\user_graded` | `POST /api/v1/webhooks` (event_type=grade_updated) | Bearer token |
| `observer::submission_graded()` | `\mod_assign\event\submission_graded` | `POST /api/v1/webhooks` (event_type=grade_updated) | Bearer token |

**Payload Format sent to backend:**
```json
{
  "event_id": "uuid-v4",
  "event_type": "grade_updated",
  "event_data": { ... },
  "moodle_event_id": 456,
  "timestamp": 1740012345
}
```

### 4.2 Scheduled Tasks → Backend

| Task Class | Schedule | Backend Call |
|---|---|---|
| `task\retry_failed_webhooks` | Every 5 min | Re-sends failed events to `POST /api/v1/webhooks` |
| `task\cleanup_old_logs` | Daily | No backend call — deletes from `mdl_local_mzi_event_log` |
| `task\sync_missing_grades` | Every 30 min | Sends to `POST /api/v1/webhooks` |
| `task\health_monitor` | Every 15 min | Calls backend `GET /api/v1/health` |

**Files:** `moodle_plugin/classes/task/`

---

## 5. Detailed Sync Flow per Entity

### 5.1 Students (Zoho → Backend)

```
Zoho CRM: BTEC_Students record change
    │
    ├── [Webhook] POST /api/v1/events/zoho/student
    │       │  File: endpoints/events.py → handle_zoho_student_event()
    │       │
    │       └─ BackgroundTask → process_zoho_event_task()
    │               │  File: services/event_handler_service.py
    │               │
    │               ├─ Check duplicate → event_logs table
    │               ├─ Fetch full record from Zoho API
    │               │    ZohoClient.get_record('BTEC_Students', record_id)
    │               └─ Update local DB → students table
    │
    └── [Bulk] POST /api/v1/sync/students
            │  File: endpoints/sync_students.py
            │
            ├─ parse_zoho_payload()        [ingress/zoho/parser.py]
            ├─ map_zoho_to_canonical()     [services/student_mapper.py]
            ├─ StudentService.sync_student() [services/student_service.py]
            └─ WRITE → students table      [infra/db/models/student.py]
```

**Zoho Fields Parsed:**

| Zoho Field | Maps To | Notes |
|---|---|---|
| `id` / `ID` | `students.zoho_id` | Required |
| `Name` | `students.display_name` | Required |
| `Academic_Email` | `students.academic_email` | Required (Moodle username) |
| `Phone_Number` | `students.phone` | Optional |
| `Status` | `students.status` | Optional |

---

### 5.2 Students (Moodle → Backend)

```
Moodle: User created/updated event
    │
    ├─ observer::user_created() / user_updated()
    │       File: moodle_plugin/classes/observer.php
    │
    ├─ data_extractor::extract_user_data($userid)
    │       File: moodle_plugin/classes/data_extractor.php
    │       Queries: mdl_user, mdl_role_assignments
    │
    ├─ webhook_sender::send_user_created() → POST /api/v1/webhooks
    │       File: moodle_plugin/classes/webhook_sender.php
    │
    └─ Backend: process_webhook_event(event_type='user_created')
            │  File: endpoints/webhooks.py
            │
            └─ handle_user_created() → moodle_events.py
                    └─ WRITE → students table (source='moodle')
```

---

### 5.3 Programs (Zoho → Backend)

```
POST /api/v1/sync/programs
    │  File: endpoints/sync_programs.py
    │
    ├─ parse_zoho_programs_payload()     [ingress/zoho/program_parser.py]
    │       Zoho Field: id → zoho_id
    │       Zoho Field: Product_Name / Name → name
    │       Zoho Field: Program_Price → price
    │       Zoho Field: MoodleID → moodle_id
    │       Zoho Field: Status → status
    │
    ├─ ProgramService.sync_program()    [services/program_service.py]
    └─ WRITE → programs table           [infra/db/models/program.py]
```

---

### 5.4 Classes (Zoho → Backend → Moodle)

**Direction A: Zoho → Backend (data sync)**
```
POST /api/v1/sync/classes
    │  File: endpoints/sync_classes.py
    │
    ├─ parse_zoho_classes_payload()     [ingress/zoho/class_parser.py]
    │       Zoho Field: id → zoho_id
    │       Zoho Field: BTEC_Class_Name / Name → name
    │       Zoho Field: Short_Name → short_name
    │       Zoho Field: Class_Status → status
    │       Zoho Field: Start_Date → start_date
    │       Zoho Field: End_Date → end_date
    │       Zoho Field: Moodle_Class_ID → moodle_class_id
    │       Zoho Field: MS_Teams_ID → ms_teams_id
    │       Zoho Field: Teacher.id → teacher_zoho_id
    │       Zoho Field: Unit.id → unit_zoho_id
    │       Zoho Field: BTEC_Program.id → program_zoho_id
    │
    ├─ ClassService.sync_class()        [services/class_service.py]
    └─ WRITE → classes table            [infra/db/models/class_.py]
```

**Direction B: Zoho Button → Backend → Moodle (course creation)**
```
POST /api/v1/classes/create
    │  File: endpoints/create_course.py
    │
    ├─ MoodleClient.create_course()
    │       Moodle WS: core_course_create_courses
    │       → Returns moodle_course_id
    │
    ├─ MoodleClient.enrol_user()        (teacher + default users)
    │       Moodle WS: enrol_manual_enrol_users
    │       Default Users: IT Support(8157), Student Affairs(8181),
    │                      CEO(8154), Admin(2), IT Leader(8133) if IT major
    │
    └─ ZohoClient.update_record('BTEC_Classes', zoho_class_id)
            Updates: Moodle_Class_ID field in Zoho
```

---

### 5.5 Enrollments (Zoho → Backend)

```
POST /api/v1/sync/enrollments
    │  File: endpoints/sync_enrollments.py
    │
    ├─ parse_zoho_enrollments_payload()  [ingress/zoho/enrollment_parser.py]
    │       Zoho Field: id → zoho_id
    │       Zoho Field: Student.id → student_zoho_id
    │       Zoho Field: BTEC_Class.id → class_zoho_id
    │       Zoho Field: Enrolled_Program.id → program_zoho_id
    │       Zoho Field: Status → status
    │       Zoho Field: Start_Date → start_date
    │       Zoho Field: Moodle_Course_ID → moodle_course_id
    │
    ├─ map_zoho_to_canonical_enrollment() [services/enrollment_mapper.py]
    ├─ EnrollmentService.sync_enrollment() [services/enrollment_service.py]
    └─ WRITE → enrollments table          [infra/db/models/enrollment.py]
```

**Also triggered from Moodle:**
```
Moodle: user_enrolment_created event
    │
    ├─ observer::enrollment_created()
    ├─ data_extractor::extract_enrollment_data($enrolmentid)
    │       SQL JOIN: mdl_user_enrolments + mdl_enrol + mdl_course
    │
    └─ POST /api/v1/webhooks (event_type='enrollment_created')
            └─ WRITE → enrollments table (source='moodle')
```

---

### 5.6 Registrations (Zoho → Backend)

```
POST /api/v1/sync/registrations
    │  File: endpoints/sync_registrations.py
    │
    ├─ parse_registration()             [ingress/zoho/registration_parser.py]
    │       Zoho Field: id → zoho_id
    │       Zoho Field: Student.id → student_zoho_id (FK → students)
    │       Zoho Field: Program.id → program_zoho_id (FK → programs)
    │       Zoho Field: Enrollment_Status → enrollment_status
    │       Zoho Field: Registration_Date → registration_date
    │       Zoho Field: Completion_Date → completion_date
    │       Zoho Field: Version → version
    │
    ├─ RegistrationService.sync_registration() [services/registration_service.py]
    └─ WRITE → registrations table     [infra/db/models/registration.py]
```

---

### 5.7 Payments (Zoho → Backend)

```
POST /api/v1/sync/payments
    │  File: endpoints/sync_payments.py
    │
    ├─ parse_payment()                  [ingress/zoho/payment_parser.py]
    │       Zoho Field: id → zoho_id
    │       Zoho Field: Registration.id → registration_zoho_id (FK → registrations)
    │       Zoho Field: Amount → amount
    │       Zoho Field: Payment_Date → payment_date
    │       Zoho Field: Payment_Method → payment_method
    │       Zoho Field: Payment_Status → payment_status
    │       Zoho Field: Description → description
    │
    ├─ PaymentService.sync_payment()    [services/payment_service.py]
    └─ WRITE → payments table          [infra/db/models/payment.py]
```

---

### 5.8 Units (Zoho → Backend)

```
POST /api/v1/sync/units
    │  File: endpoints/sync_units.py
    │
    ├─ parse_unit()                     [ingress/zoho/unit_parser.py]
    │       Zoho Field: id → zoho_id
    │       Zoho Field: Unit_Code → unit_code
    │       Zoho Field: Unit_Name → unit_name
    │       Zoho Field: Description → description
    │       Zoho Field: Credit_Hours → credit_hours
    │       Zoho Field: Level → level
    │       Zoho Field: Status → status
    │
    ├─ UnitService.sync_unit()          [services/unit_service.py]
    └─ WRITE → units table             [infra/db/models/unit.py]
```

---

### 5.9 Grades (Moodle → Backend → Zoho) ⭐ BTEC-Specific

```
Moodle: Assignment graded (submission_graded event)
    │
    ├─ observer::submission_graded()
    │       File: moodle_plugin/classes/observer.php
    │       Uses: $DB->get_record('assign_grades', ...)
    │
    ├─ data_extractor::extract_grade_data($gradeid)
    │       File: moodle_plugin/classes/data_extractor.php
    │
    │       SQL JOIN:
    │         grade_grades gg
    │         JOIN grade_items gi ON gi.id = gg.itemid
    │         LEFT JOIN course c ON c.id = gi.courseid
    │
    │       Normalization: (finalgrade - grademin) / (grademax - grademin) × 100
    │
    │       BTEC conversion:
    │         rawgrade == 0  → 'F'  (Fail / invalid submission 01122)
    │         rawgrade >= 4  → 'D'  (Distinction)
    │         rawgrade >= 3  → 'M'  (Merit)
    │         rawgrade >= 2  → 'P'  (Pass)
    │         default        → 'R'  (Refer)
    │
    │       extract_btec_learning_outcomes():
    │         SQL JOIN:
    │           grading_instances gi
    │           JOIN gradingform_btec_fillings gf ON gf.instanceid = gi.id
    │           JOIN gradingform_btec_criteria gc ON gc.id = gf.criterionid
    │         Returns: [{ LO_Code, LO_Definition, LO_Score, LO_Feedback }]
    │
    ├─ webhook_sender::send_grade_updated()
    │       POST /api/v1/webhooks  (event_type='grade_updated')
    │
    └─ Backend: handle_grade_updated()
            │  File: endpoints/webhooks.py
            │
            └─ GradeSyncService.sync_grade_to_zoho()
                    │  File: services/grade_sync_service.py
                    │
                    ├─ Find Student in Zoho by Moodle user ID
                    ├─ Find Class in Zoho by Moodle course ID
                    ├─ Get BTEC template from Zoho BTEC module
                    │      (P1-P19, M1-M9, D1-D6 criteria)
                    │
                    ├─ Build Learning_Outcomes_Assessm subform
                    │      maps: LO_Code, LO_Score, LO_Feedback
                    │
                    └─ ZohoClient.create_record('BTEC_Grades', {
                           Student: zoho_student_id,
                           Class: zoho_class_id,
                           Grade: 'Pass'|'Merit'|'Distinction'|'Refer',
                           Moodle_Grade_Composite_Key: student_id + '_' + course_id,
                           Learning_Outcomes_Assessm: [subform rows]
                       })
```

**Also triggered via:**
```
POST /api/v1/sync/grades
    │  File: endpoints/sync_grades.py
    │
    ├─ [With Zoho BTEC_Grades data] parse_grade()  [ingress/zoho/grade_parser.py]
    │       Zoho Field: id → zoho_id
    │       Zoho Field: Student.id → student_zoho_id (FK → students)
    │       Zoho Field: Unit.id → unit_zoho_id (FK → units)
    │       Zoho Field: Grade_Value → grade_value
    │       Zoho Field: Score → score (0-100)
    │       Zoho Field: Grade_Date → grade_date
    │       Zoho Field: Comments → comments
    │
    └─ GradeService.sync_grade()  → WRITE → grades table
```

---

### 5.10 BTEC Templates (Zoho → Moodle)

```
POST /api/v1/btec/templates/sync
    │  File: endpoints/btec_templates.py
    │
    ├─ ZohoClient.get_records('BTEC')   (Units module)
    │       Fetches: P1-P19, M1-M9, D1-D6 criteria descriptions
    │
    └─ MoodleClient → Moodle Web Services
            Creates: grading_definitions records in Moodle DB
            Tracks: in mdl_local_mzi_btec_templates
```

---

## 6. Service → Database Table Mapping

### Backend Services → PostgreSQL Tables

| Service File | Reads | Writes |
|---|---|---|
| `services/student_service.py` | `students` | `students` |
| `services/student_mapper.py` | — | — (pure mapper) |
| `services/student_profile_service.py` | `students` + Zoho API | `students` |
| `services/program_service.py` | `programs` | `programs` |
| `services/class_service.py` | `classes` | `classes` |
| `services/enrollment_service.py` | `enrollments`, `students`, `classes` | `enrollments` |
| `services/enrollment_sync_service.py` | `enrollments`, `students`, `classes` | `enrollments` |
| `services/registration_service.py` | `registrations`, `students`, `programs` | `registrations` |
| `services/payment_service.py` | `payments`, `registrations` | `payments` |
| `services/payment_sync_service.py` | `payments`, `registrations` | `payments` |
| `services/unit_service.py` | `units` | `units` |
| `services/grade_service.py` | `grades`, `students`, `units` | `grades` |
| `services/grade_sync_service.py` | `grades`, `students`, `classes` | `grades` + Zoho CRM |
| `services/event_handler_service.py` | `event_logs`, `students` | `event_logs`, `students` |
| `services/extension_service.py` | `extension_*` tables | `extension_*` tables |
| `services/btec_students_service.py` | `students` | `students` |

### Moodle Plugin → Moodle DB Tables

| PHP File | Reads | Writes |
|---|---|---|
| `classes/data_extractor.php` | `mdl_user`, `mdl_grade_grades`, `mdl_grade_items`, `mdl_user_enrolments`, `mdl_enrol`, `mdl_course`, `mdl_assign`, `mdl_assign_grades`, `mdl_assign_submission`, `grading_instances`, `gradingform_btec_fillings`, `gradingform_btec_criteria` | — (read only) |
| `classes/event_logger.php` | `mdl_local_mzi_event_log` | `mdl_local_mzi_event_log` |
| `classes/webhook_sender.php` | — | `mdl_local_mzi_event_log` (via event_logger) |
| `classes/config_manager.php` | `mdl_local_mzi_config` | `mdl_local_mzi_config` |
| `classes/task/retry_failed_webhooks.php` | `mdl_local_mzi_event_log` | `mdl_local_mzi_event_log` |
| `classes/task/cleanup_old_logs.php` | `mdl_local_mzi_event_log` | `mdl_local_mzi_event_log` (delete) |

---

## 7. PostgreSQL Table Inventory

Located in: `backend/app/infra/db/models/`  
Schema SQL: `backend/db_complete_schema.sql`

### Core Tables (Phases 1–4)

| Table | Model File | Primary Key | Key Columns | Indexes |
|---|---|---|---|---|
| `students` | `models/student.py` | UUID (String) | `zoho_id`, `moodle_userid`, `academic_email`, `username`, `fingerprint` | `zoho_id`, `username`, `moodle_userid` |
| `programs` | `models/program.py` | UUID (String) | `zoho_id`, `name`, `moodle_id`, `price` | `zoho_id`, `moodle_id`, `(tenant_id, zoho_id) UNIQUE` |
| `classes` | `models/class_.py` | UUID (String) | `zoho_id`, `name`, `moodle_class_id`, `teacher_zoho_id`, `program_zoho_id` | `zoho_id`, `moodle_class_id`, `(tenant_id, zoho_id) UNIQUE` |
| `enrollments` | `models/enrollment.py` | UUID (String) | `zoho_id`, `student_zoho_id`, `class_zoho_id`, `moodle_course_id`, `moodle_user_id` | `zoho_id`, `student_zoho_id`, `(tenant_id, student_zoho_id, class_zoho_id)` |
| `units` | `models/unit.py` | UUID (String) | `zoho_id`, `unit_code`, `unit_name`, `level` | `zoho_id`, `(tenant_id, zoho_id)`, `(tenant_id, unit_code)` |
| `registrations` | `models/registration.py` | UUID (String) | `zoho_id`, `student_zoho_id` (FK), `program_zoho_id` (FK), `enrollment_status` | `zoho_id`, `student_zoho_id`, `(tenant_id, student_zoho_id, program_zoho_id)` |
| `payments` | `models/payment.py` | UUID (String) | `zoho_id`, `registration_zoho_id` (FK), `amount`, `payment_status` | `zoho_id`, `registration_zoho_id`, `(tenant_id, registration_zoho_id)` |
| `grades` | `models/grade.py` | UUID (String) | `zoho_id`, `student_zoho_id` (FK), `unit_zoho_id` (FK), `grade_value`, `score` | `zoho_id`, `student_zoho_id`, `(tenant_id, student_zoho_id, unit_zoho_id)` |

### Extension Tables (Configuration API)

| Table | Model File | Purpose |
|---|---|---|
| `extension_tenants` | `models/extension.py` | Multi-tenancy |
| `extension_integrations` | `models/extension.py` | Moodle & Zoho connection settings |
| `extension_modules` | `models/extension.py` | Enable/disable per-module sync |
| `extension_field_mappings` | `models/extension.py` | Custom Zoho→Canonical field maps |
| `extension_sync_runs` | `models/extension.py` | Sync history & results |
| `extension_sync_schedules` | `models/extension.py` | Scheduled sync config |

### Event & Audit Tables

| Table | Model File | Purpose |
|---|---|---|
| `event_logs` | `models/event_log.py` | Webhook event deduplication & audit |

---

## 8. Moodle Database Table Inventory

Defined in: `moodle_plugin/db/install.xml`  
All table names prefixed with `mdl_` at runtime.

### Plugin-Managed Tables

| Table | Purpose | Key Columns |
|---|---|---|
| `local_mzi_event_log` | All webhook events: sent, failed, pending | `event_id` (UUID), `event_type`, `status`, `student_name`, `course_name`, `grade_name`, `retry_count`, `next_retry_at` |
| `local_mzi_sync_history` | Manual sync operations history | `sync_type`, `sync_action`, `status`, `records_processed`, `records_failed` |
| `local_mzi_config` | Encrypted key-value config storage | `config_key`, `config_value`, `is_encrypted` |
| `local_mzi_btec_templates` | Tracks synced BTEC grading templates | `definition_id` (FK→grading_definitions), `zoho_unit_id`, `unit_name`, `synced_at` |
| `local_mzi_grade_queue` | Grade operations queue (Hybrid Grading) | `assignment_id`, `student_id`, `grade_letter`, `lo_data`, `status`, `attempt_number` |

### Moodle Standard Tables — Read by Plugin

| Table | Read By | Purpose |
|---|---|---|
| `mdl_user` | `data_extractor.php` | User info for webhook payload |
| `mdl_role_assignments` + `mdl_role` | `data_extractor.php` | Determine student/teacher role |
| `mdl_user_enrolments` | `data_extractor.php` | Enrollment data |
| `mdl_enrol` | `data_extractor.php` | Enrolment method |
| `mdl_course` | `data_extractor.php` | Course name/shortname |
| `mdl_grade_grades` | `data_extractor.php` | Final grade value |
| `mdl_grade_items` | `data_extractor.php` | Grade item (max/min, module) |
| `mdl_assign` | `data_extractor.php` | Assignment metadata |
| `mdl_assign_grades` | `observer.php` + `data_extractor.php` | Assignment grade record |
| `mdl_assign_submission` | `observer.php` | Submission status check |
| `grading_instances` | `data_extractor.php` | BTEC grading instance |
| `gradingform_btec_fillings` | `data_extractor.php` | LO score per criterion |
| `gradingform_btec_criteria` | `data_extractor.php` | LO code & definition |
| `grading_definitions` | Backend sets FK | BTEC template definition |

---

## 9. Data Transformation & Field Mapping Layers

### Layer 1: Zoho Webhook Payload → Parsed Dict

**File:** `backend/app/ingress/zoho/`  
These parsers handle Zoho's inconsistent field names and normalize them.

```
parser.py           → generic: data[].id, data[].Name
program_parser.py   → data[].id, Product_Name/Name, Program_Price, MoodleID
class_parser.py     → data[].id, BTEC_Class_Name/Name, Short_Name, Teacher.id, BTEC_Program.id
enrollment_parser.py → data[].id, Student.id/Contact, BTEC_Class/Class, Enrolled_Program
registration_parser.py → raw.id, Student.id, Program.id, Enrollment_Status
payment_parser.py   → raw.id, Registration.id, Amount, Payment_Date, Payment_Status
unit_parser.py      → raw.id, Unit_Code, Unit_Name, Credit_Hours, Level, Status
grade_parser.py     → raw.id, Student.id, Unit.id, Grade_Value, Score, Grade_Date
```

### Layer 2: Parsed Dict → Canonical Domain Model (Pydantic)

**File:** `backend/app/domain/` + `backend/app/services/*_mapper.py`

```
student_mapper.py   → CanonicalStudent    [domain/student.py]
enrollment_mapper.py  → CanonicalEnrollment [domain/enrollment.py]
grade_mapper.py     → CanonicalGrade      [domain/grade.py]
payment_mapper.py   → CanonicalPayment    [domain/payment.py]
program_mapper.py   → CanonicalProgram    [domain/program.py]
class_mapper.py     → CanonicalClass      [domain/class_.py]
registration_mapper.py → CanonicalRegistration [domain/registration.py]
unit_mapper.py      → CanonicalUnit       [domain/unit.py]
```

Pydantic validation runs at this layer. Examples:
- `academic_email` must contain `@` and valid TLD
- `zoho_id` cannot be empty
- `enrollment_status` must be non-empty
- `amount` must be > 0

### Layer 3: Canonical Model → PostgreSQL ORM

**File:** `backend/app/services/*_service.py`  

Services use SHA256 fingerprint to detect changes before writing:

```python
fingerprint = sha256("|".join([field1, field2, ...]).encode()).hexdigest()
if existing.fingerprint == fingerprint:
    return { "status": "UNCHANGED" }
```

States returned: `NEW`, `UPDATED`, `UNCHANGED`, `INVALID`, `ERROR`

### Layer 4: Moodle PHP → Webhook Payload

**File:** `moodle_plugin/classes/data_extractor.php`

| Moodle Data | Transformation | Webhook Field |
|---|---|---|
| `mdl_user.email` | as-is | `email` |
| `mdl_user.firstname + lastname` | concat | `user_fullname` |
| `mdl_grade_grades.finalgrade` | `(fg - min) / (max - min) × 100` | `finalgrade_numeric` (0–100) |
| `mdl_grade_grades.finalgrade` | BTEC scale (0–4→P/M/D/R/F) | `btec_grade` |
| `gradingform_btec_fillings.score` | join with criteria | `learning_outcomes[]` |

### Layer 5: Backend → Zoho CRM (Write Back)

**File:** `backend/app/services/grade_sync_service.py` + `app/infra/zoho/client.py`  

When syncing grades from Moodle → Zoho:

| Moodle Value | Zoho Field | Notes |
|---|---|---|
| `moodle_userid` | Lookup in `BTEC_Students.Moodle_Student_ID` | Find Zoho Student ID |
| `courseid` | Lookup in `BTEC_Classes.Moodle_Class_ID` | Find Zoho Class ID |
| `btec_grade` | `BTEC_Grades.Grade` | Pass/Merit/Distinction/Refer |
| `learning_outcomes[]` | `BTEC_Grades.Learning_Outcomes_Assessm` | Subform (array of rows) |
| `student_id + '_' + course_id` | `BTEC_Grades.Moodle_Grade_Composite_Key` | For deduplication |

### Layer 6: Student Dashboard — Zoho → Moodle via Backend

**File:** `backend/app/api/v1/endpoints/student_dashboard_webhooks.py`  
**Function:** `transform_zoho_to_moodle(data, entity_type)`

| Entity | Zoho Field | Moodle Field |
|---|---|---|
| `classes` | `id` | `zoho_class_id` |
| `classes` | `Class_Name` | `class_name` |
| `classes` | `Teacher` (lookup) | `teacher_name` |
| `classes` | `Start_Date` | `start_date` |
| `registrations` | `id` | `zoho_registration_id` |
| `registrations` | `Student.id` | `zoho_student_id` |
| `registrations` | `Total_Fees` | `total_fees` |
| `registrations` | `Paid_Amount` | `paid_amount` |
| `registrations` | `Remaining_Amount` | `remaining_amount` |
| `enrollments` | `id` | `zoho_enrollment_id` |
| `enrollments` | `Student.id` | `zoho_student_id` |
| `enrollments` | `Class.id` | `zoho_class_id` |

---

## 10. File Path Index

### Backend Entry Points

| Flow | File |
|---|---|
| App startup | `backend/app/main.py` |
| All routes registered | `backend/app/api/v1/router.py` |
| Settings / config | `backend/app/core/config.py` |
| HMAC verification | `backend/app/core/security.py` |
| Idempotency store | `backend/app/core/idempotency.py` |

### Backend API Endpoints

| File | Endpoints |
|---|---|
| `app/api/v1/endpoints/events.py` | `/events/zoho/*` |
| `app/api/v1/endpoints/moodle_events.py` | `/events/moodle/*` |
| `app/api/v1/endpoints/webhooks.py` | `/webhooks` |
| `app/api/v1/endpoints/student_dashboard_webhooks.py` | `/webhooks/student-dashboard/*` |
| `app/api/v1/endpoints/sync_students.py` | `/sync/students` |
| `app/api/v1/endpoints/sync_programs.py` | `/sync/programs` |
| `app/api/v1/endpoints/sync_classes.py` | `/sync/classes` |
| `app/api/v1/endpoints/sync_enrollments.py` | `/sync/enrollments` |
| `app/api/v1/endpoints/sync_registrations.py` | `/sync/registrations` |
| `app/api/v1/endpoints/sync_payments.py` | `/sync/payments` |
| `app/api/v1/endpoints/sync_units.py` | `/sync/units` |
| `app/api/v1/endpoints/sync_grades.py` | `/sync/grades` |
| `app/api/v1/endpoints/create_course.py` | `/classes/create` |
| `app/api/v1/endpoints/btec_templates.py` | `/btec/templates/sync` |
| `app/api/v1/endpoints/moodle_users.py` | `/moodle/users` |
| `app/api/v1/endpoints/moodle_enrollments.py` | `/moodle/enrollments` |
| `app/api/v1/endpoints/moodle_grades.py` | `/moodle/grades` |
| `app/api/v1/endpoints/extension_tenants.py` | `/extension/tenants/*` |
| `app/api/v1/endpoints/extension_settings.py` | `/extension/settings/*` |
| `app/api/v1/endpoints/extension_mappings.py` | `/extension/mappings/*` |
| `app/api/v1/endpoints/extension_runs.py` | `/extension/runs/*` |
| `app/api/v1/endpoints/health.py` | `/health` |
| `app/api/v1/endpoints/debug_enhanced.py` | `/debug/*` |

### Backend Ingress (Zoho Parsers)

| File | Parses |
|---|---|
| `app/ingress/zoho/parser.py` | Generic BTEC_Students payload |
| `app/ingress/zoho/program_parser.py` | Products (Programs) |
| `app/ingress/zoho/class_parser.py` | BTEC_Classes |
| `app/ingress/zoho/enrollment_parser.py` | BTEC_Enrollments |
| `app/ingress/zoho/registration_parser.py` | BTEC_Registrations |
| `app/ingress/zoho/payment_parser.py` | BTEC_Payments |
| `app/ingress/zoho/unit_parser.py` | BTEC (Units) |
| `app/ingress/zoho/grade_parser.py` | BTEC_Grades |
| `app/ingress/zoho/btec_students_parser.py` | BTEC_Students (alternate) |

### Backend Domain Models

| File | Model |
|---|---|
| `app/domain/student.py` | `CanonicalStudent` |
| `app/domain/program.py` | `CanonicalProgram` |
| `app/domain/class_.py` | `CanonicalClass` |
| `app/domain/enrollment.py` | `CanonicalEnrollment` |
| `app/domain/registration.py` | `CanonicalRegistration` |
| `app/domain/payment.py` | `CanonicalPayment` |
| `app/domain/unit.py` | `CanonicalUnit` |
| `app/domain/grade.py` | `CanonicalGrade` |
| `app/domain/events.py` | `ZohoWebhookEvent`, `MoodleWebhookEvent` |

### Backend Services (Business Logic)

| File | Responsibility |
|---|---|
| `app/services/student_service.py` | Sync student, fingerprint, CRUD |
| `app/services/student_mapper.py` | Zoho dict → CanonicalStudent |
| `app/services/grade_sync_service.py` | **BTEC Grade sync to Zoho** |
| `app/services/event_handler_service.py` | Route webhook events |
| `app/services/enrollment_service.py` | Sync enrollment |
| `app/services/enrollment_sync_service.py` | Enrollment + Zoho sync |
| `app/services/payment_service.py` | Sync payment |
| `app/services/payment_sync_service.py` | Payment + Zoho sync |
| `app/services/registration_service.py` | Sync registration |
| `app/services/class_service.py` | Sync class |
| `app/services/program_service.py` | Sync program |
| `app/services/unit_service.py` | Sync unit |
| `app/services/grade_service.py` | Zoho→Backend grade storage |

### Backend Infrastructure

| File | Responsibility |
|---|---|
| `app/infra/zoho/client.py` | Zoho CRM API calls (async, 587 lines) |
| `app/infra/zoho/auth.py` | OAuth2 token refresh |
| `app/infra/moodle/users.py` | Moodle Web Service API client |
| `app/infra/db/models/student.py` | `students` ORM |
| `app/infra/db/models/program.py` | `programs` ORM |
| `app/infra/db/models/class_.py` | `classes` ORM |
| `app/infra/db/models/enrollment.py` | `enrollments` ORM |
| `app/infra/db/models/registration.py` | `registrations` ORM |
| `app/infra/db/models/payment.py` | `payments` ORM |
| `app/infra/db/models/unit.py` | `units` ORM |
| `app/infra/db/models/grade.py` | `grades` ORM |
| `app/infra/db/models/event_log.py` | `event_logs` ORM |
| `app/infra/db/models/extension.py` | `extension_*` ORM |
| `app/infra/db/session.py` | `get_db()` dependency |

### Moodle Plugin

| File | Responsibility |
|---|---|
| `moodle_plugin/version.php` | Plugin version (4.1.2) |
| `moodle_plugin/lib.php` | Navigation hooks |
| `moodle_plugin/settings.php` | Admin settings UI |
| `moodle_plugin/classes/observer.php` | All 6 event observers |
| `moodle_plugin/classes/data_extractor.php` | Query Moodle DB for payloads |
| `moodle_plugin/classes/webhook_sender.php` | HTTP client + retry logic |
| `moodle_plugin/classes/event_logger.php` | UUID + DB logging |
| `moodle_plugin/classes/config_manager.php` | Encrypted config |
| `moodle_plugin/classes/admin_setting_encrypted_token.php` | AES-256 token storage |
| `moodle_plugin/classes/task/retry_failed_webhooks.php` | Retry scheduled task |
| `moodle_plugin/classes/task/cleanup_old_logs.php` | Log cleanup task |
| `moodle_plugin/classes/task/sync_missing_grades.php` | Grade re-sync task |
| `moodle_plugin/classes/task/health_monitor.php` | Backend health check task |
| `moodle_plugin/db/install.xml` | All Moodle table definitions |

---

*End of Data Flow Map*
