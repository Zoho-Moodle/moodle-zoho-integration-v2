# Dashboard Read/Write Verified Map
> Date: 2026-02-20  
> Scope: Moodle Plugin + FastAPI Backend — Student Dashboard only  
> Method: Direct code inspection, file paths and function names cited for every claim

---

## 1 — Dashboard READ Map

| Page / Tab | File | Query Location | Tables Queried | Keys Used by Renderer |
|---|---|---|---|---|
| **Profile** | `moodle_plugin/ui/student/profile.php` | `$DB->get_record_sql()` line ~28 | `{local_mzi_students}` WHERE `moodle_user_id = $USER->id` | `student_id`, `first_name`, `last_name`, `email`, `phone_number`, `nationality`, `date_of_birth`, `status`, `photo_url`, `address`, `updated_at` |
| **Profile (AJAX)** | `moodle_plugin/ui/ajax/get_student_data.php` → `assets/js/dashboard.js::renderProfile()` | `fetch_backend_data('/api/v1/extension/students/profile')` | **Backend API — not local DB** | `data.student.student_id`, `data.student.full_name`, `data.student.email`, `data.student.phone`, `data.student.student_status` |
| **My Programs** | `moodle_plugin/ui/student/programs.php` | `$DB->get_records_sql()` line ~32 | `{local_mzi_registrations} r` LEFT JOIN `{local_mzi_payments} p ON p.registration_id = r.id AND p.payment_status != 'Voided'` GROUP BY `r.id` WHERE `r.student_id = $student->id` | `program_name`, `zoho_registration_id`, `registration_status`, `registration_date`, `total_fees`, `paid_amount`, `balance` (computed: `total_fees − SUM(payment_amount)`), `payment_plan`, `number_of_installments` |
| **Finance (AJAX)** | `get_student_data.php?type=finance` → `dashboard.js::renderFinance()` | `fetch_backend_data('/api/v1/extension/students/finance')` | **Backend API — not local DB** | `data.summary.total_fees`, `data.summary.amount_paid`, `data.summary.balance_due`, `data.payments[].payment_date`, `data.payments[].amount`, `data.payments[].payment_method`, `data.payments[].payment_status` |
| **Classes** | `moodle_plugin/ui/student/classes.php` | `$DB->get_records_sql()` line ~32 | `{local_mzi_enrollments} e` INNER JOIN `{local_mzi_classes} c ON c.id = e.class_id` + COUNT subquery on `{local_mzi_grades}` WHERE `e.student_id = $student->id` | `class_name`, `program_level`, `teacher_name`, `start_date`, `end_date`, `class_status`, `enrollment_status`, `enrollment_date`, `zoho_enrollment_id`, `grade_count` |
| **Classes (AJAX)** | `get_student_data.php?type=classes` → `dashboard.js::renderClasses()` | `fetch_backend_data('/api/v1/extension/students/classes')` | **Backend API — not local DB** | `data.classes[].class_name`, `data.classes[].instructor`, `data.classes[].schedule`, `data.classes[].room` |
| **Grades (AJAX)** | `get_student_data.php?type=grades` → `dashboard.js::renderGrades()` | `fetch_backend_data('/api/v1/extension/students/grades')` | **Backend API — not local DB** | `data.grades[].unit_name`, `data.grades[].grade`, `data.grades[].grade_status`, `data.grades[].submission_date` |
| **Requests** | `moodle_plugin/ui/student/requests.php` | `$DB->get_records_sql()` line ~33 | `{local_mzi_requests}` WHERE `student_id = $student->id` ORDER BY `created_at DESC` | `zoho_request_id`, `request_type`, `description`, `status`, `created_at`, `updated_at` |
| **Student Card** | `moodle_plugin/ui/student/student_card.php` | `$DB->get_record_sql()` line ~28 | `{local_mzi_students} s` + subquery `(SELECT program_name FROM {local_mzi_registrations} WHERE student_id = s.id AND registration_status = 'Active' ORDER BY registration_date DESC LIMIT 1) AS current_program` | `first_name`, `last_name`, `student_id`, `email`, `nationality`, `photo_url`, `current_program` |

---

## 2 — Sync WRITE Map

> **Legend**  
> ✅ = implemented and traceable in code  
> ✗ = not present  
> ⚠️ = called in code but target function existence unverified in `moodle_plugin/db/services.php`

| Zoho Module | Webhook Endpoint | Handler Function | Mapping Function | → PostgreSQL | → Moodle WS | → Moodle DB Direct |
|---|---|---|---|---|---|---|
| `BTEC_Students` | `POST /api/v1/events/zoho/student` | `handle_zoho_student_event()` `endpoints/events.py:58` | `EventHandlerService.handle_zoho_event()` → `ingress/zoho/parser.py` | `students` ✅ | ✗ | ✗ |
| `BTEC_Students` | `POST /api/v1/webhooks/student-dashboard/student_updated` | `handle_student_updated()` `endpoints/student_dashboard_webhooks.py:117` | payload passed as-is (already snake_case) | ✗ | `local_mzi_update_student` ⚠️ | ✗ |
| `BTEC_Registrations` | `POST /api/v1/sync/registrations` | `endpoints/sync_registrations.py` | `ingress/zoho/registration_parser.py` | `registrations` ✅ | ✗ | ✗ |
| `BTEC_Registrations` | `POST /api/v1/webhooks/student-dashboard/registration_created` | `handle_registration_created()` `student_dashboard_webhooks.py:137` | `transform_zoho_to_moodle(payload, "registrations")` | ✗ | `local_mzi_create_registration` ⚠️ | ✗ |
| `BTEC_Payments` | `POST /api/v1/events/zoho/payment` | `handle_zoho_payment_event()` `endpoints/events.py:245` | `EventHandlerService` → `ingress/zoho/payment_parser.py` | `payments` ✅ | ✗ | ✗ |
| `BTEC_Payments` | `POST /api/v1/webhooks/student-dashboard/payment_recorded` | `handle_payment_recorded()` `student_dashboard_webhooks.py:150` | payload passed as-is | ✗ | `local_mzi_record_payment` ⚠️ | ✗ |
| `BTEC_Classes` | `POST /api/v1/sync/classes` | `endpoints/sync_classes.py` | `ingress/zoho/class_parser.py` | `classes` ✅ | ✗ | ✗ |
| `BTEC_Classes` | `POST /api/v1/classes/create` | `endpoints/create_course.py` | direct field access | `classes` (partial) ✅ | `core_course_create_courses` ✅ | ✗ |
| `BTEC_Classes` | `POST /api/v1/webhooks/student-dashboard/class_created` | `handle_class_created()` `student_dashboard_webhooks.py:190` | `transform_zoho_to_moodle(payload, "classes")` | ✗ | `local_mzi_create_class` ⚠️ | ✗ |
| `BTEC_Enrollments` | `POST /api/v1/events/zoho/enrollment` | `handle_zoho_enrollment_event()` `endpoints/events.py:147` | `EventHandlerService` → `ingress/zoho/enrollment_parser.py` | `enrollments` ✅ | ✗ | ✗ |
| `BTEC_Enrollments` | `POST /api/v1/webhooks/student-dashboard/enrollment_updated` | `handle_enrollment_updated()` `student_dashboard_webhooks.py:215` | `transform_zoho_to_moodle(payload, "enrollments")` | ✗ | `local_mzi_update_enrollment` ⚠️ | ✗ |
| `BTEC_Grades` | `POST /api/v1/events/zoho/grade` | `handle_zoho_grade_event()` `endpoints/events.py:196` | `EventHandlerService` → `ingress/zoho/grade_parser.py` | `grades` ✅ | ✗ | ✗ |
| `BTEC_Grades` | `POST /api/v1/webhooks/student-dashboard/grade_submitted` | `handle_grade_submitted()` `student_dashboard_webhooks.py:235` | payload passed as-is | ✗ | `local_mzi_submit_grade` ⚠️ | ✗ |
| `BTEC_Student_Requests` | `POST /api/v1/webhooks/student-dashboard/request_status_changed` | `handle_request_status_changed()` `student_dashboard_webhooks.py:252` | payload passed as-is | ✗ | `local_mzi_update_request_status` ⚠️ | ✗ |
| `BTEC_Student_Requests` | **no `/api/v1/events/zoho/*` handler** | **— MISSING —** | — | ✗ | ✗ | ✗ |

---

## 3 — Gaps

### GAP-1 — Two disconnected webhook paths per module, no unification
Every Zoho module has **two separate backend endpoints** with no code link between them:

| Path A (PostgreSQL only) | Path B (Moodle WS only) |
|---|---|
| `/api/v1/events/zoho/*` → `events.py` | `/api/v1/webhooks/student-dashboard/*` → `student_dashboard_webhooks.py` |

Zoho must fire **two separate Workflow Rules per module** to reach both paths. If only one fires, either PostgreSQL or `local_mzi_*` is never updated.  
**Affected modules:** BTEC_Students, BTEC_Registrations, BTEC_Payments, BTEC_Classes, BTEC_Enrollments, BTEC_Grades.

---

### GAP-2 — `events.py` writes only to PostgreSQL, never to `local_mzi_*`
The following functions in `backend/app/api/v1/endpoints/events.py` call `EventHandlerService.handle_zoho_event()` which routes to `ingress/zoho/*_parser.py` → service layer → **PostgreSQL only**:

- `handle_zoho_student_event()` → writes `students` table, never `local_mzi_students`
- `handle_zoho_enrollment_event()` → writes `enrollments`, never `local_mzi_enrollments`
- `handle_zoho_grade_event()` → writes `grades`, never `local_mzi_grades`
- `handle_zoho_payment_event()` → writes `payments`, never `local_mzi_payments`

**Effect:** `profile.php`, `programs.php`, `classes.php`, `requests.php` — which read exclusively from `local_mzi_*` — will show empty data unless the student-dashboard webhook path fires separately.

---

### GAP-3 — `BTEC_Student_Requests` has no `events.py` handler and no CREATE path
- `requests.php` queries `{local_mzi_requests}` for all student requests.
- `student_dashboard_webhooks.py::handle_request_status_changed()` handles **status updates only** — it calls `local_mzi_update_request_status`.
- There is **no endpoint** for initial request creation from Zoho (`POST /api/v1/events/zoho/requests` does not exist in `events.py`).
- **Effect:** New `BTEC_Student_Requests` records created in Zoho are never synced. `{local_mzi_requests}` is always empty for new requests.

---

### GAP-4 — Moodle WS functions called but not verified to exist
`student_dashboard_webhooks.py` calls these Moodle WS functions via `call_moodle_ws()`:

| WS Function Called | Called In |
|---|---|
| `local_mzi_update_student` | `student_dashboard_webhooks.py:128` |
| `local_mzi_create_registration` | `student_dashboard_webhooks.py:145` |
| `local_mzi_record_payment` | `student_dashboard_webhooks.py:157` |
| `local_mzi_create_class` | `student_dashboard_webhooks.py:204` |
| `local_mzi_update_enrollment` | `student_dashboard_webhooks.py:226` |
| `local_mzi_submit_grade` | `student_dashboard_webhooks.py:243` |
| `local_mzi_update_request_status` | `student_dashboard_webhooks.py:260` |
| `local_mzi_delete_student` | `student_dashboard_webhooks.py:274` |

None of these appear in the analyzed portions of `moodle_plugin/db/services.php`. If they are not registered there, every call returns a Moodle exception and `local_mzi_*` tables are **never written** by this path.

**Verification command:**
```bash
grep -n "local_mzi_update_student\|local_mzi_create_registration\|local_mzi_record_payment" moodle_plugin/db/services.php
```

---

### GAP-5 — Backend AJAX endpoint `/api/v1/extension/students/*` does not exist
`get_student_data.php::fetch_backend_data()` calls:
- `/api/v1/extension/students/profile`
- `/api/v1/extension/students/academics`
- `/api/v1/extension/students/finance`
- `/api/v1/extension/students/classes`
- `/api/v1/extension/students/grades`

No file matching `extension_students.py` exists in `backend/app/api/v1/endpoints/`. The router at `backend/app/api/v1/router.py` registers `extension_tenants`, `extension_settings`, `extension_mappings`, `extension_runs` — but not `extension_students`.

**Effect:** `dashboard.js` AJAX tabs (Profile AJAX, Finance, Classes AJAX, Grades) hit a 404 and fall through to the hardcoded fallback in `get_student_data.php` lines ~122–170 which returns empty arrays. All AJAX-rendered tabs show "no data available".

---

### GAP-5b — Column name mismatch: `teacher_name` vs `instructor`
- `local_mzi_classes.teacher_name` — column name in `install.xml` and `classes.php`
- `dashboard.js::renderClasses()` line ~248 reads `cls.instructor`

When `get_student_data.php` is eventually fixed to read from `local_mzi_classes`, the key `instructor` will not exist in the result unless explicitly aliased:
```php
// Required alias in PHP response:
'instructor' => $row->teacher_name,
```

---

### GAP-6 — `paid_amount` stored in two places, can diverge
`local_mzi_registrations.paid_amount` is a stored column (written by `local_mzi_create_registration`).  
`programs.php` line ~32 computes balance as `total_fees − COALESCE(SUM(p.payment_amount), 0)` directly from `local_mzi_payments` rows — **ignoring** `local_mzi_registrations.paid_amount`.

If a payment is recorded in `local_mzi_payments` but `local_mzi_registrations.paid_amount` is not updated (or vice versa), the two figures diverge.  
**Resolution:** use `local_mzi_payments` as the single source of truth for balance calculation (as `programs.php` already does), and treat `local_mzi_registrations.paid_amount` as a denormalized cache only.

---

### Summary Table

| # | Gap | Affected UI | Severity |
|---|---|---|---|
| GAP-1 | Two disconnected webhook paths — no dual-write | All tabs | 🔴 Critical |
| GAP-2 | `events.py` writes PostgreSQL only, not `local_mzi_*` | profile, programs, classes | 🔴 Critical |
| GAP-3 | No CREATE path for `BTEC_Student_Requests` | requests.php | 🔴 Critical |
| GAP-4 | Moodle WS functions not verified to exist | All tabs (dashboard path) | 🔴 Critical |
| GAP-5 | `/api/v1/extension/students/*` endpoints missing | AJAX tabs in dashboard.js | 🟠 High |
| GAP-5b | `teacher_name` vs `instructor` key mismatch | Classes AJAX tab | 🟡 Medium |
| GAP-6 | `paid_amount` stored in two places | programs.php finance section | 🟡 Medium |
