# Moodle WS Functions Audit Report
> Plugin: `local_moodle_zoho_sync`  
> Audit Date: 2026-02-20  
> Scope: All `local_mzi_*` write functions called by backend `student_dashboard_webhooks.py`

---

## A — WS Function Status Table

| WS Function | Exists in PHP? | File | Method Name | Registered in `db/services.php`? | In `$services` block? | Writes to DB? | DB Table Written |
|---|---|---|---|---|---|---|---|
| `local_mzi_update_student` | ✅ | `classes/external/student_dashboard.php:31` | `update_student()` | ✅ line 27 | ✅ | ✅ INSERT/UPDATE | `local_mzi_students` |
| `local_mzi_create_registration` | ✅ | `classes/external/student_dashboard.php:163` | `create_registration()` | ✅ line 37 | ✅ | ⚠️ WRITES WRONG COLUMNS | `local_mzi_registrations` |
| `local_mzi_record_payment` | ✅ | `classes/external/student_dashboard.php:220` | `record_payment()` | ✅ line 44 | ✅ | ⚠️ WRITES WRONG COLUMNS | `local_mzi_payments` |
| `local_mzi_create_class` | ✅ | `classes/external/student_dashboard.php:315` | `create_class()` | ✅ line 51 | ✅ | ⚠️ WRITES WRONG COLUMNS | `local_mzi_classes` |
| `local_mzi_update_enrollment` | ✅ | `classes/external/student_dashboard.php:387` | `update_enrollment()` | ✅ line 58 | ✅ | ⚠️ WRITES WRONG COLUMN | `local_mzi_enrollments` |
| `local_mzi_submit_grade` | ✅ | `classes/external/student_dashboard.php:470` | `submit_grade()` | ✅ line 65 | ✅ | ⚠️ WRITES WRONG COLUMNS | `local_mzi_grades` |
| `local_mzi_update_request_status` | ✅ | `classes/external/student_dashboard.php:552` | `update_request_status()` | ✅ line 72 | ✅ | ⚠️ WRITES WRONG COLUMN | `local_mzi_requests` |
| `local_mzi_delete_student` | ✅ | `classes/external/student_dashboard.php:660` | `delete_student()` | ✅ line 79 | ✅ | ✅ | `local_mzi_students` |

**Summary:** All 7 write functions exist, are registered, and are in the service. The issue is not missing functions — it is **column name mismatches** in 5 of 7 functions, which cause silent `DB::insert_record` failures or store the data in the wrong columns.

---

## B — Critical Gaps (Column Name Mismatches)

All mismatches discovered by comparing `classes/external/student_dashboard.php` against `db/install.xml`.

---

### MISMATCH-1 — `create_registration` — 3 wrong column names

**File:** [classes/external/student_dashboard.php](moodle_plugin/classes/external/student_dashboard.php#L178)

| PHP writes (`$record->X`) | DB column (`install.xml`) | Status |
|---|---|---|
| `$record->program` | `program_name` | ❌ Wrong key — data is lost |
| `$record->status` | `registration_status` | ❌ Wrong key — data is lost |
| `$record->registration_date = strtotime(...)` (int) | `registration_date` is `TYPE="char"` | ⚠️ Type mismatch — stores unix int in char column |

---

### MISMATCH-2 — `record_payment` — 2 wrong column names

**File:** [classes/external/student_dashboard.php](moodle_plugin/classes/external/student_dashboard.php#L253)

| PHP writes (`$record->X`) | DB column (`install.xml`) | Status |
|---|---|---|
| `$record->amount` | `payment_amount` | ❌ Wrong key — data is lost |
| `$record->status` | `payment_status` | ❌ Wrong key — data is lost |
| `$record->payment_date = strtotime(...)` (int) | `payment_date` is `TYPE="char"` | ⚠️ Type mismatch — stores unix int in char column |

---

### MISMATCH-3 — `create_class` — 3 wrong column names + 1 non-existent column

**File:** [classes/external/student_dashboard.php](moodle_plugin/classes/external/student_dashboard.php#L346)

| PHP writes (`$record->X`) | DB column (`install.xml`) | Status |
|---|---|---|
| `$record->program` | No `program` column exists | ❌ Non-existent column — Moodle throws DDL exception |
| `$record->instructor` | `teacher_name` | ❌ Wrong key — data is lost |
| `$record->status` | `class_status` | ❌ Wrong key — data is lost |
| `$record->start_date = strtotime(...)` (int) | `start_date` is `TYPE="char"` | ⚠️ Type mismatch |
| `$record->end_date = strtotime(...)` (int) | `end_date` is `TYPE="char"` | ⚠️ Type mismatch |

> **Note:** `$record->program` writes to a column that does not exist. On strict MySQL/MariaDB this will cause `DB::insert_record` to throw an exception, **aborting the entire class creation**.

---

### MISMATCH-4 — `update_enrollment` — 1 wrong column name

**File:** [classes/external/student_dashboard.php](moodle_plugin/classes/external/student_dashboard.php#L437)

| PHP writes (`$record->X`) | DB column (`install.xml`) | Status |
|---|---|---|
| `$record->status` | `enrollment_status` | ❌ Wrong key — enrollment status is never written |

---

### MISMATCH-5 — `submit_grade` — 2 wrong column names

**File:** [classes/external/student_dashboard.php](moodle_plugin/classes/external/student_dashboard.php#L521)

| PHP writes (`$record->X`) | DB column (`install.xml`) | Status |
|---|---|---|
| `$record->unit` | DB has `assignment_name`, `btec_grade_name`, `numeric_grade` — no `unit` column | ❌ Non-existent column |
| `$record->grade` | `btec_grade_name` (for BTEC: P/M/D) or `numeric_grade` (for numeric) | ❌ Wrong key — grade value is never written |
| `$record->grade_date = strtotime(...)` (int) | `grade_date` is `TYPE="char"` | ⚠️ Type mismatch |

---

### MISMATCH-6 — `update_request_status` — 1 wrong column name

**File:** [classes/external/student_dashboard.php](moodle_plugin/classes/external/student_dashboard.php#L577)

| PHP writes (`$record->X`) | DB column (`install.xml`) | Status |
|---|---|---|
| `$record->status` | `request_status` | ❌ Wrong key — request status is never written |

---

### MISMATCH-7 — `db/services.php` — Missing `capabilities` key on all dashboard functions

**File:** [db/services.php](moodle_plugin/db/services.php#L27)

All `local_mzi_*` registrations omit the `'capabilities'` key:
```php
'local_mzi_update_student' => [
    'classname'   => 'local_moodle_zoho_sync\external\student_dashboard',
    'methodname'  => 'update_student',
    'type'        => 'write',
    'ajax'        => true,
    // 'capabilities' => missing
],
```
The only capability check is the inline `require_capability('moodle/site:config', $context)` inside each method, which is functionally correct but means the function declaration in `db/services.php` does not advertise its required capability. This is a non-blocking issue (Moodle allows it) but is against best practices and breaks automated capability export.

---

### MISMATCH-8 — Moodle Admin Configuration (runtime — cannot confirm from code)

The following must be verified in the Moodle admin panel after plugin upgrade:

| Check | What to verify |
|---|---|
| External service exists | `Site administration → Server → Web services → External services` — service `Moodle-Zoho Integration Service` (shortname: `moodle_zoho_sync`) must appear |
| Functions added | All `local_mzi_*` functions must be listed under the service |
| Token created | A token must exist for the service user (the account the backend uses) |
| Token used by backend | Backend `student_dashboard_webhooks.py` `MOODLE_TOKEN` env var must match this token |
| Service `enabled = 1` | `db/services.php` sets this, confirmed ✅ |
| `restrictedusers = 0` | Confirmed ✅ — any authenticated user with the right capability can call it |

---

## C — Minimal Fix Plan

> No table names changed. No API names changed. Backend payload keys preserved.  
> All fixes are in one file: `moodle_plugin/classes/external/student_dashboard.php`

---

### FIX-1 — `create_registration` — correct 3 field names

**File:** [classes/external/student_dashboard.php](moodle_plugin/classes/external/student_dashboard.php#L178)

```php
// BEFORE (wrong):
$record->program           = $data['program'] ?? '';
$record->registration_date = !empty($data['registration_date']) ? strtotime($data['registration_date']) : time();
$record->status            = $data['status'] ?? 'Pending';

// AFTER (correct):
$record->program_name         = $data['program_name'] ?? $data['program'] ?? '';
$record->registration_date    = $data['registration_date'] ?? '';   // keep as string — DB is TYPE="char"
$record->registration_status  = $data['registration_status'] ?? $data['status'] ?? 'Pending';
```

---

### FIX-2 — `record_payment` — correct 2 field names

**File:** [classes/external/student_dashboard.php](moodle_plugin/classes/external/student_dashboard.php#L253)

```php
// BEFORE (wrong):
$record->amount       = $data['amount'] ?? 0;
$record->payment_date = !empty($data['payment_date']) ? strtotime($data['payment_date']) : time();
$record->status       = $data['status'] ?? 'Completed';

// AFTER (correct):
$record->payment_amount = $data['payment_amount'] ?? $data['amount'] ?? 0;
$record->payment_date   = $data['payment_date'] ?? '';   // keep as string — DB is TYPE="char"
$record->payment_status = $data['payment_status'] ?? $data['status'] ?? 'Completed';
```

---

### FIX-3 — `create_class` — correct 3 field names, remove non-existent `program`

**File:** [classes/external/student_dashboard.php](moodle_plugin/classes/external/student_dashboard.php#L346)

```php
// BEFORE (wrong):
$record->program    = $data['program'] ?? '';
$record->instructor = $data['instructor'] ?? '';
$record->start_date = !empty($data['start_date']) ? strtotime($data['start_date']) : null;
$record->end_date   = !empty($data['end_date']) ? strtotime($data['end_date']) : null;
$record->status     = $data['status'] ?? 'Scheduled';

// AFTER (correct):
$record->program_level = $data['program_level'] ?? $data['program'] ?? '';
$record->teacher_name  = $data['teacher_name'] ?? $data['instructor'] ?? '';
$record->start_date    = $data['start_date'] ?? '';   // keep as string — DB is TYPE="char"
$record->end_date      = $data['end_date'] ?? '';     // keep as string — DB is TYPE="char"
$record->class_status  = $data['class_status'] ?? $data['status'] ?? 'Scheduled';
```

---

### FIX-4 — `update_enrollment` — correct 1 field name

**File:** [classes/external/student_dashboard.php](moodle_plugin/classes/external/student_dashboard.php#L437)

```php
// BEFORE (wrong):
$record->status = $data['status'] ?? 'Active';

// AFTER (correct):
$record->enrollment_status = $data['enrollment_status'] ?? $data['status'] ?? 'Active';
```

---

### FIX-5 — `submit_grade` — correct 2 field names, remove non-existent `unit`

**File:** [classes/external/student_dashboard.php](moodle_plugin/classes/external/student_dashboard.php#L521)

```php
// BEFORE (wrong):
$record->unit       = $data['unit'] ?? '';
$record->grade      = $data['grade'] ?? '';
$record->grade_date = !empty($data['grade_date']) ? strtotime($data['grade_date']) : time();

// AFTER (correct):
$record->assignment_name  = $data['assignment_name'] ?? $data['unit'] ?? '';
$record->btec_grade_name  = $data['btec_grade_name'] ?? $data['grade'] ?? '';
$record->numeric_grade    = $data['numeric_grade'] ?? null;
$record->grade_date       = $data['grade_date'] ?? '';   // keep as string — DB is TYPE="char"
```

---

### FIX-6 — `update_request_status` — correct 1 field name

**File:** [classes/external/student_dashboard.php](moodle_plugin/classes/external/student_dashboard.php#L577)

```php
// BEFORE (wrong):
$record->status = $data['status'] ?? 'Pending';

// AFTER (correct):
$record->request_status = $data['request_status'] ?? $data['status'] ?? 'Pending';
```

---

### FIX-7 — `db/services.php` — add `capabilities` key to all dashboard functions (non-breaking)

**File:** [db/services.php](moodle_plugin/db/services.php#L27)

Add `'capabilities' => 'moodle/site:config'` to all `local_mzi_*` entries:

```php
'local_mzi_update_student' => [
    'classname'    => 'local_moodle_zoho_sync\external\student_dashboard',
    'methodname'   => 'update_student',
    'description'  => 'Update or create student record from Zoho CRM',
    'type'         => 'write',
    'ajax'         => true,
    'capabilities' => 'moodle/site:config',   // ADD THIS
],
```
Repeat for all 7 dashboard functions and the delete functions.

---

### FIX-8 — After deploying fixes, run Moodle upgrade

```bash
php admin/cli/upgrade.php --non-interactive
```
Then in Moodle admin, verify the service and token:
- `Site admin → Server → Web services → Manage tokens` — token for the backend service user must exist
- `Site admin → Server → Web services → External services → moodle_zoho_sync → Functions` — all `local_mzi_*` functions must be listed

---

## D — Skeleton Implementation: `local_mzi_update_student` (reference example)

This is the corrected reference implementation showing the complete pattern all functions should follow.

**File:** `moodle_plugin/classes/external/student_dashboard.php`

```php
<?php
namespace local_moodle_zoho_sync\external;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use context_system;

class student_dashboard extends external_api {

    // ─────────────────────────────────────────────────────────────────
    // local_mzi_update_student
    // Called by: backend/app/api/v1/endpoints/student_dashboard_webhooks.py
    //            function handle_student_updated() via call_moodle_ws()
    // Writes to: {local_mzi_students}
    // ─────────────────────────────────────────────────────────────────

    public static function update_student_parameters() {
        return new external_function_parameters([
            'studentdata' => new external_value(PARAM_RAW, 'JSON string of student data from Zoho')
        ]);
    }

    public static function update_student($studentdata) {
        global $DB;

        // 1. Validate incoming parameters against declared schema
        $params = self::validate_parameters(
            self::update_student_parameters(),
            ['studentdata' => $studentdata]
        );

        // 2. Capability check — backend service user must have moodle/site:config
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:config', $context);

        // 3. Parse JSON payload
        $data = json_decode($params['studentdata'], true);
        if (!$data || !isset($data['zoho_student_id'])) {
            throw new \invalid_parameter_exception('Invalid JSON data or missing zoho_student_id');
        }

        // 4. Check if student already exists (upsert pattern)
        $existing = $DB->get_record('local_mzi_students', [
            'zoho_student_id' => $data['zoho_student_id']
        ]);

        // 5. Build record — all column names match install.xml exactly
        $record = new \stdClass();
        $record->zoho_student_id           = $data['zoho_student_id'];
        $record->student_id                = $data['student_id'] ?? '';
        $record->first_name                = $data['first_name'] ?? '';
        $record->last_name                 = $data['last_name'] ?? '';
        $record->email                     = $data['email'] ?? '';
        $record->phone_number              = $data['phone_number'] ?? '';
        $record->address                   = $data['address'] ?? '';
        $record->nationality               = $data['nationality'] ?? '';
        $record->date_of_birth             = $data['date_of_birth'] ?? null;  // char column — keep as string
        $record->gender                    = $data['gender'] ?? '';
        $record->emergency_contact_name    = $data['emergency_contact_name'] ?? '';
        $record->emergency_contact_phone   = $data['emergency_contact_phone'] ?? '';
        $record->status                    = $data['status'] ?? 'Active';
        $record->photo_url                 = $data['photo_url'] ?? '';
        $record->updated_at                = time();
        $record->synced_at                 = time();

        // 6. Validate moodle_user_id if provided
        if (!empty($data['moodle_user_id'])) {
            $record->moodle_user_id = $DB->record_exists('user', ['id' => $data['moodle_user_id']])
                ? (int)$data['moodle_user_id']
                : null;
        } else {
            $record->moodle_user_id = null;
        }

        // 7. Upsert
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_mzi_students', $record);
            $action = 'updated';
        } else {
            $record->created_at = time();
            $DB->insert_record('local_mzi_students', $record);
            $action = 'created';
        }

        // 8. Log to local_mzi_webhook_logs
        self::log_webhook('student_updated', $data['zoho_student_id'], 'success', null);

        // 9. Return structured response
        return [
            'success'    => true,
            'action'     => $action,
            'student_id' => $data['zoho_student_id'],
            'message'    => "Student {$action} successfully"
        ];
    }

    public static function update_student_returns() {
        return new external_single_structure([
            'success'    => new external_value(PARAM_BOOL, 'Operation success status'),
            'action'     => new external_value(PARAM_TEXT, 'Action performed: created|updated'),
            'student_id' => new external_value(PARAM_TEXT, 'Zoho student ID processed'),
            'message'    => new external_value(PARAM_TEXT, 'Human-readable result message')
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // Registration in db/services.php must be:
    //
    // 'local_mzi_update_student' => [
    //     'classname'    => 'local_moodle_zoho_sync\external\student_dashboard',
    //     'methodname'   => 'update_student',
    //     'description'  => 'Update or create student record from Zoho CRM',
    //     'type'         => 'write',
    //     'ajax'         => true,
    //     'capabilities' => 'moodle/site:config',
    // ],
    // ─────────────────────────────────────────────────────────────────
}
```

---

## Summary: What Works vs What Breaks

| Status | What is working |
|---|---|
| ✅ | All 7 WS functions physically exist in `classes/external/student_dashboard.php` |
| ✅ | All 7 are registered in `db/services.php` with correct classname, methodname, type=write |
| ✅ | All 7 are listed in the `$services` block with the service enabled |
| ✅ | `local_mzi_update_student` writes correct columns — `update_student` is clean |
| ✅ | `local_mzi_delete_student` performs correct soft-delete on `local_mzi_students` |
| ✅ | Capability check `require_capability('moodle/site:config', $context)` present in all methods |
| ✅ | Each function has `_parameters()`, the execute method, and `_returns()` — Moodle WS triple pattern complete |

| Status | What is broken |
|---|---|
| ❌ | `create_registration` — writes `program` and `status`; DB expects `program_name` and `registration_status` |
| ❌ | `record_payment` — writes `amount` and `status`; DB expects `payment_amount` and `payment_status` |
| ❌ | `create_class` — writes non-existent `program` column (causes exception), `instructor` (DB: `teacher_name`), `status` (DB: `class_status`) |
| ❌ | `update_enrollment` — writes `status`; DB expects `enrollment_status` |
| ❌ | `submit_grade` — writes non-existent `unit` column and generic `grade`; DB expects `assignment_name`, `btec_grade_name`, `numeric_grade` |
| ❌ | `update_request_status` — writes `status`; DB expects `request_status` |
| ⚠️ | All date fields in 4 functions use `strtotime()` producing int, but DB columns are `TYPE="char"` |
| ⚠️ | `db/services.php` — `capabilities` key missing from all `local_mzi_*` declarations |
