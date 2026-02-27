# FULL FIELD MAPPING AUDIT REPORT
**Zoho CRM ↔ Middleware (FastAPI) ↔ Moodle WS ↔ DB ↔ Student UI**

**Date:** 2025-07  
**Auditor:** GitHub Copilot (Claude Sonnet 4.6)  
**Scope:** All 8 synced modules — Students, Teachers, Registrations, Payments, Classes, Enrollments, Grades, Requests

---

## EXECUTIVE SUMMARY

| Severity | Count | Examples |
|---|---|---|
| 🔴 **CRITICAL** (data loss / DB crash) | 5 | `zoho_student_id` in registrations, `zoho_registration_id` in payments, `class_short_name` in classes, `study_mode` no DB column, `moodle_user_id_str` no DB column |
| 🟠 **HIGH** (silent data drop / duplicate risk) | 6 | `Display_Name` never written to DB, `Enrollment_Status` not in FIELD_MAPPINGS, `request_date` not written in WS, no UNIQUE on `zoho_enrollment_id`, no UNIQUE on `zoho_request_id`, `reason` column always empty |
| 🟡 **MEDIUM** (minor data gaps) | 4 | `academic_email` not sent by backend, `display_name` sent but dropped at WS layer, `Teacher_Moodle_ID` resolved by email not field, `Currency_Symbol` alt mapping can override `Currency` |
| ✅ **OK** | All other fields | See per-module sections |

**Overall Readiness: NOT PRODUCTION READY — 5 critical DB-crash bugs must be fixed before going live.**

---

## ARCHITECTURE OVERVIEW

```
Zoho CRM Webhook
    │ (api_name fields)
    ▼
FIELD_MAPPINGS (student_dashboard_webhooks.py)
    │ (remapped keys → Python dict)
    ▼
event_handler_service.py
    │ (SQLite update + Moodle WS call)
    ▼
Moodle WS (student_dashboard.php)
    │ (JSON decode → $record)
    ▼
install.xml DB Schema (local_mzi_*)
    │ (MySQL/MariaDB columns)
    ▼
Student UI (ui/student/*.php)
    │ ($student->column reads)
    ▼
Browser
```

---

## MODULE 1: STUDENTS (BTEC_Students)

### 1A. Zoho → FIELD_MAPPINGS (student_dashboard_webhooks.py)

| Zoho api_name | FIELD_MAPPINGS key | Notes |
|---|---|---|
| `id` | `zoho_student_id` | ✅ |
| `First_Name` | `first_name` | ✅ |
| `Last_Name` | `last_name` | ✅ |
| `Display_Name` | `display_name` | 🟠 Mapped but never written (see 1C) |
| `Academic_Email` | `email` | ⚠️ Maps to `email`, not `academic_email` — see 1D |
| `Phone_Number` | `phone_number` | ✅ |
| `Address` | `address` | ✅ |
| `City` | `city` | ✅ |
| `Nationality` | `nationality` | ✅ |
| `Birth_Date` | `date_of_birth` | ✅ |
| `Gender` | `gender` | ✅ |
| `Emergency_Contact_Name` | `emergency_contact_name` | ✅ |
| `Emergency_Phone_Number` | `emergency_contact_phone` | ✅ |
| `Status` | `status` | ✅ |
| `Student_Moodle_ID` | `moodle_user_id` | ✅ |
| `National_Number` | `national_id` | ✅ |
| `Created_Time` | `zoho_created_time` | ✅ |
| `Modified_Time` | `zoho_modified_time` | ✅ |

### 1B. FIELD_MAPPINGS → event_handler_service.py (SQLite + Moodle WS)

The backend sends to Moodle WS (`local_mzi_update_student`):
- All fields from FIELD_MAPPINGS, translated to WS parameters ✅
- `display_name` is sent in WS payload but **not written by WS** (see 1C)

### 1C. Moodle WS: update_student (student_dashboard.php)

Fields written to DB by WS:
```php
zoho_student_id, student_id, first_name, last_name, email, phone_number,
address, city, nationality, national_id, date_of_birth, gender,
emergency_contact_name, emergency_contact_phone, status, moodle_user_id,
photo_url (if provided), updated_at, synced_at
```

| Issue | Severity |
|---|---|
| `display_name` data sent by backend but NOT written by WS → silently dropped | 🟠 HIGH |
| `academic_email` NOT written by WS (`$record->academic_email` missing) | 🟡 MEDIUM |
| `zoho_created_time` / `zoho_modified_time` NOT written in update path | 🟡 MEDIUM |

### 1D. DB Schema: local_mzi_students

All columns used by WS exist in install.xml ✅

| DB Column | Source | Status |
|---|---|---|
| `display_name` | — | ❌ **No such column in install.xml** — column doesn't exist → `display_name` data is permanently lost |
| `academic_email` | Zoho `Academic_Email` | ⚠️ Column exists but WS never writes it |
| `email` | Mapped from `Academic_Email` | ✅ Written correctly |

### 1E. Student UI: profile.php

Reads: `first_name`, `last_name`, `status`, `phone_number`, `date_of_birth`, `nationality`, `address`, `city`, `national_id`, `email`, `academic_email`, `gender`
- `academic_email` displayed (line 132) but column always empty due to 1C issue → falls back to `email` via `?: $student->email` ✅ (graceful fallback)
- `national_id` displayed with safe null check ✅

---

## MODULE 2: TEACHERS (BTEC_Teachers)

### 2A. Zoho → FIELD_MAPPINGS

| Zoho api_name | FIELD_MAPPINGS key | Notes |
|---|---|---|
| `id` | `zoho_teacher_id` | ✅ |
| `Name` | `teacher_name` | ✅ |
| `Email` | `email` | ✅ |
| `Academic_Email` | `academic_email` | ✅ (confirmed at line 8315 in zoho_api_names.json) |
| `Phone_Number` | `phone_number` | ✅ |
| `Teacher_Moodle_ID` | `moodle_user_id` | 🟡 Field mapped but WS resolves by email lookup, not by this value |
| `Created_Time` | `zoho_created_time` | ✅ |
| `Modified_Time` | `zoho_modified_time` | ✅ |

### 2B. Moodle WS: sync_teacher

Fields written:
```php
zoho_teacher_id, moodle_user_id (resolved by email, not from data),
teacher_name, email, academic_email, phone_number,
updated_at, synced_at, zoho_modified_time, (zoho_created_time on insert)
```

| Issue | Severity |
|---|---|
| `moodle_user_id` from `Teacher_Moodle_ID` field is **ignored** — WS looks up by email instead | 🟡 MEDIUM (intended design, but means Zoho-set ID is never used) |

### 2C. DB Schema: local_mzi_teachers

All WS-written fields exist in install.xml ✅  
UNIQUE index on `zoho_teacher_id` ✅

---

## MODULE 3: REGISTRATIONS (BTEC_Registrations)

### 3A. Zoho → FIELD_MAPPINGS

| Zoho api_name | FIELD_MAPPINGS key | Notes |
|---|---|---|
| `id` | `zoho_registration_id` | ✅ |
| `Name` | `zoho_student_id` (lookup_id) | 🟠 Duplicate mapping — same target as `Student_ID` |
| `Student_ID` | `zoho_student_id` (lookup_id) | 🟠 Duplicate key → second entry always overwrites first |
| `Program` | `program_name` (lookup_name) | ✅ |
| `Program_Name` | `program_name` (alt) | ✅ |
| `Registration_Number` | `registration_number` | ✅ |
| `Registration_Date` | `registration_date` | ✅ |
| `Registration_Status` | `registration_status` | ✅ |
| `Status` | `registration_status` (alt) | ✅ |
| `Program_Price` | `total_fees` | ✅ |
| `Total_Fees` | `total_fees` (alt) | ✅ |
| `Paid_Amount` | `paid_amount` | ✅ |
| `Remaining_Amount` | `remaining_amount` | ✅ |
| `Currency` | `currency` | ✅ |
| `Currency_Symbol` | `currency` (alt) | 🟡 Can overwrite `currency` with symbol instead of code |
| `Payment_Plan` | `payment_plan` | ✅ |
| `Study_Mode` | `study_mode` | 🔴 **CRITICAL — no DB column** |
| `Expected_Graduation` | `expected_graduation` | ✅ |
| `Number_of_Installments` | `number_of_installments` | ✅ |
| `Program_Level` | `program_level` | ✅ |

### 3B. Moodle WS: create_registration

**CRITICAL BUGS in WS code:**

```php
$record->zoho_student_id = $data['zoho_student_id'] ?? '';  // ← NOT IN DB!
$record->payment_plan = $data['payment_plan'] ?? $data['study_mode'] ?? '';  // study_mode used as fallback only
```

| Issue | Severity |
|---|---|
| `$record->zoho_student_id` written but NO `zoho_student_id` column in `local_mzi_registrations` → **DB INSERT WILL CRASH** | 🔴 CRITICAL |
| `study_mode` in FIELD_MAPPINGS maps to non-existent DB column | 🔴 CRITICAL |
| `study_mode` is actually used as fallback for `payment_plan` in WS — intended design? | 🟡 MEDIUM |

### 3C. DB Schema: local_mzi_registrations

Columns: `id, student_id, zoho_registration_id, registration_number, program_name, program_level, registration_date, expected_graduation, registration_status, total_fees, paid_amount, remaining_amount, currency, payment_plan, number_of_installments, created_at, updated_at, synced_at, zoho_created_time, zoho_modified_time`

**Missing columns (WS tries to write but don't exist):**
- `zoho_student_id` — ❌ not in schema → **DB crash**
- `study_mode` — ❌ not in schema (data silently lost via FIELD_MAPPINGS)

---

## MODULE 4: PAYMENTS (BTEC_Payments)

### 4A. Zoho → FIELD_MAPPINGS

| Zoho api_name | FIELD_MAPPINGS key | Notes |
|---|---|---|
| `id` | `zoho_payment_id` | ✅ |
| `Registration_ID` | `zoho_registration_id` (lookup_id) | ✅ |
| `Student_ID` | `zoho_student_id` (lookup_id) | ✅ |
| `Payment_Amount` | `payment_amount` | ✅ |
| `Payment_Date` | `payment_date` | ✅ |
| `Payment_Method` | `payment_method` | ✅ |
| `Note` | `payment_notes` | ✅ |
| `Created_Time` | `zoho_created_time` | ✅ |
| `Modified_Time` | `zoho_modified_time` | ✅ |

### 4B. Moodle WS: record_payment

WS code writes:
```php
$record->registration_id      // resolved FK ✅
$record->zoho_payment_id      // ✅
$record->zoho_registration_id // ← NOT IN DB!
$record->payment_amount       // ✅
$record->payment_date         // ✅
$record->payment_method       // ✅
$record->payment_status       // ✅
$record->voucher_number       // ✅
$record->receipt_number       // ✅
$record->payment_notes        // ✅
```

| Issue | Severity |
|---|---|
| `$record->zoho_registration_id` written but NO `zoho_registration_id` column in `local_mzi_payments` → **DB INSERT WILL CRASH** | 🔴 CRITICAL |
| `voucher_number`, `receipt_number` written by WS but not sent by backend FIELD_MAPPINGS (will always be empty string) | 🟡 MEDIUM |
| `bank_name` column in DB never populated anywhere | 🟡 MEDIUM |
| `payment_number` column in DB never populated anywhere | 🟡 MEDIUM |

### 4C. DB Schema: local_mzi_payments

Columns: `id, registration_id, zoho_payment_id, payment_number, payment_date, payment_amount, payment_method, voucher_number, bank_name, receipt_number, payment_notes, payment_status, created_at, updated_at, synced_at, zoho_created_time, zoho_modified_time`

**Missing columns (WS tries to write):**
- `zoho_registration_id` — ❌ not in schema → **DB crash**

---

## MODULE 5: CLASSES (BTEC_Classes)

### 5A. Zoho → FIELD_MAPPINGS

| Zoho api_name | FIELD_MAPPINGS key | Notes |
|---|---|---|
| `id` | `zoho_class_id` | ✅ |
| `Class_Name` | `class_name` | ✅ |
| `Class_Short_Name` | `class_short_name` | 🔴 **CRITICAL — no DB column** |
| `BTEC_Program` | `program_zoho_id` + `program_name` (lookup) | ✅ |
| `Unit` | `unit_zoho_id` + `unit_name` (lookup) | ✅ |
| `Teacher` | `teacher_zoho_id` + `teacher_name` (lookup) | ✅ |
| `Moodle_Class_ID` | `moodle_class_id` | ✅ |
| `Class_Status` | `class_status` | ✅ |
| `Start_Date` | `start_date` | ✅ |
| `End_Date` | `end_date` | ✅ |
| `Created_Time` | `zoho_created_time` | ✅ |
| `Modified_Time` | `zoho_modified_time` | ✅ |

### 5B. Moodle WS: create_class

WS writes `$record->class_short_name = $data['class_short_name'] ?? '';`

| Issue | Severity |
|---|---|
| `class_short_name` written by WS but **NO column in `local_mzi_classes`** → **DB INSERT WILL CRASH** | 🔴 CRITICAL |
| `class_type` column in DB never populated (no Zoho field mapped) | 🟡 MEDIUM |
| `schedule` column in DB never populated (no Zoho field mapped) | 🟡 MEDIUM |

### 5C. DB Schema: local_mzi_classes

Columns: `id, zoho_class_id, class_number, class_name, unit_name, unit_zoho_id, program_level, program_zoho_id, teacher_name, teacher_zoho_id, class_type, start_date, end_date, schedule, class_status, moodle_class_id, created_at, updated_at, synced_at`

**Missing columns (WS tries to write):**
- `class_short_name` — ❌ not in schema → **DB crash**

---

## MODULE 6: ENROLLMENTS (BTEC_Enrollments)

### 6A. Zoho → FIELD_MAPPINGS

| Zoho api_name | FIELD_MAPPINGS key | Notes |
|---|---|---|
| `id` | `zoho_enrollment_id` | ✅ |
| `Enrolled_Students` | `zoho_student_id` (lookup_id) | ✅ |
| `Classes` | `zoho_class_id` (lookup_id) | ✅ |
| `Start_Date` | `enrollment_date` | ✅ |
| `End_Date` | `end_date` | ✅ |
| `Enrollment_Type` | `enrollment_type` | ✅ (confirmed at line 7037) |
| `Student_Name` | `student_name` | ✅ |
| `Class_Name` | `class_name` | ✅ |
| `Enrolled_Program` | `enrolled_program` | ✅ |
| `Moodle_Course_ID` | `moodle_course_id` | ✅ (confirmed at line 6993) |
| `Synced_to_Moodle` | `synced_to_moodle` | ✅ (confirmed at line 6218) |
| ❌ **(missing)** | `enrollment_status` | 🟠 **HIGH — DB column exists, never synced from Zoho** |

### 6B. Moodle WS: update_enrollment

WS handles `enrollment_status` correctly — uses `$data['enrollment_status'] ?? $data['status'] ?? 'Active'`  
But backend never sends `enrollment_status` (not in FIELD_MAPPINGS) → **always defaults to 'Active'**

| Issue | Severity |
|---|---|
| `Enrollment_Status` not in FIELD_MAPPINGS → backend never sends it → always 'Active' in DB | 🟠 HIGH |
| No UNIQUE index on `zoho_enrollment_id` in install.xml → duplicate records possible on webhook replay | 🟠 HIGH |

### 6C. DB Schema: local_mzi_enrollments

All WS-written fields exist in DB ✅  
UNIQUE index on `zoho_enrollment_id`: **MISSING** 🟠

---

## MODULE 7: GRADES (BTEC_Grades)

### 7A. Zoho → FIELD_MAPPINGS

| Zoho api_name | FIELD_MAPPINGS key | Notes |
|---|---|---|
| `id` | `zoho_grade_id` | ✅ |
| `Student` | `zoho_student_id` (lookup_id) | ✅ |
| `Class` | `zoho_class_id` (lookup_id) | ✅ |
| `BTEC_Unit` | `unit_name` (lookup_name) | ✅ |
| `Assignment_Name` | `assignment_name` | ✅ |
| `BTEC_Grade_Name` | `btec_grade_name` | ✅ (confirmed at line 9272) |
| `Grade` | `numeric_grade` | ✅ |
| `Attempt_Number` | `attempt_number` | ✅ (confirmed at line 9305) |
| `Feedback` | `feedback` | ✅ |
| `Grade_Status` | `grade_status` | ✅ (confirmed at line 9316) |
| `Attempt_Date` | `grade_date` | ✅ |

### 7B. Moodle WS: submit_grade

All FIELD_MAPPINGS fields correctly written to DB ✅  
UNIQUE key on `zoho_grade_id` exists ✅

| Issue | Severity |
|---|---|
| `learning_outcomes` column in DB but **no Zoho field mapped** | 🟡 MEDIUM |
| `is_resubmission` column in DB but **no Zoho field mapped** | 🟡 MEDIUM |
| `submission_date` column in DB but **no Zoho field mapped** | 🟡 MEDIUM |

### 7C. DB Schema: local_mzi_grades

All WS-written fields exist in DB ✅  
UNIQUE index on `zoho_grade_id` ✅ **GOOD**

---

## MODULE 8: REQUESTS (BTEC_Student_Requests)

### 8A. Zoho → FIELD_MAPPINGS

| Zoho api_name | FIELD_MAPPINGS key | Notes |
|---|---|---|
| `id` | `zoho_request_id` | ✅ |
| `Student` | `zoho_student_id` (lookup_id) | ✅ |
| `Request_Type` | `request_type` | ✅ |
| `Status` | `request_status` | ✅ |
| `Reason` | `description` | 🟠 Maps to `description` but DB has separate `reason` column |
| `Request_Date` | `request_date` | 🟠 **Mapped but WS never writes it** |
| `Moodle_User_ID` | `moodle_user_id_str` | 🔴 **CRITICAL — no DB column `moodle_user_id_str`** |

### 8B. Moodle WS: update_request_status

WS writes:
```php
$record->student_id      // FK resolved ✅
$record->zoho_request_id // ✅
$record->request_type    // ✅
$record->description     // ✅
$record->request_status  // ✅
$record->updated_at, synced_at // ✅
```

| Issue | Severity |
|---|---|
| `moodle_user_id_str` in FIELD_MAPPINGS — **no such column in `local_mzi_requests`** → backend tries to send it, WS ignores, but it's a dead mapping | 🔴 CRITICAL |
| `request_date` in FIELD_MAPPINGS but WS **never writes** `$record->request_date` → data lost | 🟠 HIGH |
| `reason` column in DB always empty (WS only writes `description`, never `reason`) | 🟠 HIGH |
| UNIQUE index `zoho_request_id_idx` is `UNIQUE="false"` in install.xml → **duplicate risk** | 🟠 HIGH |
| `request_number`, `priority`, `admin_notes`, `admin_response`, `reviewed_by`, `reviewed_at` in DB → never populated from Zoho (admin-only fields, likely intentional) | 🟡 MEDIUM |

### 8C. DB Schema: local_mzi_requests

Columns: `id, student_id, zoho_request_id, request_number, request_type, request_status, priority, reason, description, requested_classes, grade_details, change_information, admin_notes, admin_response, reviewed_by, reviewed_at, created_at, updated_at, synced_at, zoho_created_time, zoho_modified_time`

**Missing column:**
- `moodle_user_id_str` — ❌ not in schema

---

## CROSS-LAYER IDEMPOTENCY CHECK

| Module | Unique Key | UNIQUE Index | Safe Replay? |
|---|---|---|---|
| students | `zoho_student_id` | ✅ YES | ✅ Safe |
| teachers | `zoho_teacher_id` | ✅ YES | ✅ Safe |
| registrations | `zoho_registration_id` | ✅ YES | ✅ Safe |
| payments | `zoho_payment_id` | ✅ YES | ✅ Safe |
| classes | `zoho_class_id` | ✅ YES | ✅ Safe |
| enrollments | `zoho_enrollment_id` | ❌ **MISSING** | 🟠 DUPLICATE RISK |
| grades | `zoho_grade_id` | ✅ YES | ✅ Safe |
| requests | `zoho_request_id` | ❌ `UNIQUE="false"` | 🟠 DUPLICATE RISK |

---

## CRITICAL FIX CHECKLIST

### FIX 1 — Remove `zoho_student_id` write from create_registration WS
**File:** `moodle_plugin/classes/external/student_dashboard.php` ~line 240  
**Action:** Remove `$record->zoho_student_id = $data['zoho_student_id'] ?? '';`  
OR add `zoho_student_id CHAR(20)` column to `local_mzi_registrations` in both `install.xml` and `upgrade.php`

### FIX 2 — Remove `zoho_registration_id` write from record_payment WS
**File:** `moodle_plugin/classes/external/student_dashboard.php` ~line 310  
**Action:** Remove `$record->zoho_registration_id = $data['zoho_registration_id'] ?? '';`  
OR add `zoho_registration_id CHAR(20)` column to `local_mzi_payments`

### FIX 3 — Remove `class_short_name` write from create_class WS
**File:** `moodle_plugin/classes/external/student_dashboard.php` ~line 490  
**Action:** Remove `$record->class_short_name = $data['class_short_name'] ?? '';`  
OR add `class_short_name CHAR(100)` column to `local_mzi_classes`

### FIX 4 — Remove `study_mode` from registrations FIELD_MAPPINGS
**File:** `backend/app/api/v1/endpoints/student_dashboard_webhooks.py`  
**Action:** Remove `"Study_Mode": {"db_field": "study_mode"}` entry  
OR add `study_mode CHAR(50)` column to `local_mzi_registrations`

### FIX 5 — Remove `moodle_user_id_str` from requests FIELD_MAPPINGS
**File:** `backend/app/api/v1/endpoints/student_dashboard_webhooks.py`  
**Action:** Remove `"Moodle_User_ID": {"db_field": "moodle_user_id_str"}` entry  
OR add `moodle_user_id_str CHAR(50)` column to `local_mzi_requests`

### FIX 6 — Add UNIQUE constraint to `zoho_enrollment_id`
**File:** `moodle_plugin/db/install.xml` + `upgrade.php`  
**Action:** Change `zoho_enrollment_id_idx` to `UNIQUE="true"`, add `upgrade.php` step

### FIX 7 — Add UNIQUE constraint to `zoho_request_id`
**File:** `moodle_plugin/db/install.xml` + `upgrade.php`  
**Action:** Change `zoho_request_id_idx` to `UNIQUE="true"`, add `upgrade.php` step

### FIX 8 (HIGH) — Add `Enrollment_Status` to enrollments FIELD_MAPPINGS
**File:** `backend/app/api/v1/endpoints/student_dashboard_webhooks.py`  
**Action:** Add `"Enrollment_Status": {"db_field": "enrollment_status"}` 

### FIX 9 (HIGH) — Add `request_date` write to update_request_status WS
**File:** `moodle_plugin/classes/external/student_dashboard.php` ~line 775  
**Action:** Add `$record->request_date = $data['request_date'] ?? '';`

### FIX 10 (MEDIUM) — Add `academic_email` write to update_student WS
**File:** `moodle_plugin/classes/external/student_dashboard.php` ~line 90  
**Action:** Add `$record->academic_email = $data['academic_email'] ?? '';` after email line

---

## STUDENT DASHBOARD READ VALIDATION

| Page | DB Read | Columns Used | OK? |
|---|---|---|---|
| `profile.php` | `local_mzi_students WHERE moodle_user_id = ?` | first_name, last_name, status, phone_number, date_of_birth, nationality, address, city, national_id, email, academic_email, gender | ✅ All exist in DB |
| Profile gender/emergency | same query | gender, emergency_contact_name, emergency_contact_phone | ✅ |
| `national_id` display | same query | national_id | ✅ (with safe null check) |
| `academic_email` display | same query | academic_email ?: email | ✅ (graceful fallback) |

---

## ZOHO api_name CONFIRMATION TABLE

| Module | api_name | Line in zoho_api_names.json | Status |
|---|---|---|---|
| BTEC_Students | `Birth_Date` | 258 | ✅ |
| BTEC_Students | `Academic_Email` | 1549 | ✅ |
| BTEC_Students | `Emergency_Contact_Name` | 1560 | ✅ |
| BTEC_Students | `Gender` | 1604 | ✅ |
| BTEC_Students | `Emergency_Phone_Number` | 2988 | ✅ |
| BTEC_Students | `National_Number` | 3021 | ✅ |
| BTEC_Students | `Phone_Number` | 3043 | ✅ |
| BTEC_Students | `Display_Name` | 4396 | ✅ |
| BTEC_Students | `Student_Moodle_ID` | 5973 | ✅ |
| BTEC_Registrations | `Registration_Status` | 6666 | ✅ |
| BTEC_Enrollments | `Synced_to_Moodle` | 6218, 6762 | ✅ |
| BTEC_Enrollments | `Moodle_Course_ID` | 6993 | ✅ |
| BTEC_Enrollments | `Enrollment_Type` | 7037 | ✅ |
| BTEC_Classes | `Class_Short_Name` | 7277 | ✅ |
| BTEC_Grades | `BTEC_Grade_Name` | 9272 | ✅ |
| BTEC_Grades | `Attempt_Number` | 9305 | ✅ |
| BTEC_Grades | `Grade_Status` | 9316 | ✅ |
| BTEC_Teachers | `Moodle_User_ID` | 8277 | ✅ |
| BTEC_Teachers | `Academic_Email` | 8315 | ✅ |

---

## SUMMARY MATRIX — ALL MODULES

| Module | Zoho→Backend | Backend→WS | WS→DB | DB Schema | Idempotent | Dashboard |
|---|---|---|---|---|---|---|
| Students | ✅ (17 fields) | ✅ | ⚠️ (academic_email missing) | ✅ | ✅ | ✅ |
| Teachers | ✅ | ✅ | ✅ | ✅ | ✅ | N/A |
| Registrations | ⚠️ (study_mode dead) | ⚠️ | 🔴 (zoho_student_id crash) | ⚠️ (missing cols) | ✅ | UI TBD |
| Payments | ✅ | ✅ | 🔴 (zoho_reg_id crash) | ⚠️ (missing cols) | ✅ | UI TBD |
| Classes | ⚠️ (class_short_name dead) | ⚠️ | 🔴 (class_short_name crash) | ⚠️ (missing col) | ✅ | UI TBD |
| Enrollments | ⚠️ (no Enroll_Status) | ⚠️ | ✅ | ✅ | 🟠 NO UNIQUE | UI TBD |
| Grades | ✅ | ✅ | ✅ | ✅ | ✅ | UI TBD |
| Requests | ⚠️ (moodle_user_id_str dead) | ⚠️ | ✅ | ✅ | 🟠 NO UNIQUE | UI TBD |

---

*Report generated: 2025-07 | Plugin version: 2026022201 | Backend: FastAPI + SQLite*
