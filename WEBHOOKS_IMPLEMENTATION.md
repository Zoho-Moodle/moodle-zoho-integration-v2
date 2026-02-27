# Webhooks Implementation — Zoho CRM → Python Backend → Moodle

## نظرة عامة على المعمارية

```
Zoho CRM
  │  (HTTP POST — Workflow Rules)
  ▼
Python Backend  (FastAPI, port 8001)
  │  (Moodle Web Services REST API)
  ▼
Moodle  (plugin: local_moodle_zoho_sync)
  │
  ▼
mdl_* tables + local_mzi_* tables
```

---

## هيكل الملفات

```
backend/app/api/v1/endpoints/
├── student_dashboard_webhooks.py   ← aggregator (يجمع كل sub-routers)
├── webhooks_dashboard_sync.py      ← student / registration / payment / grade / request
├── webhooks_moodle_courses.py      ← class_updated / class_deleted
├── webhooks_moodle_enrol.py        ← enrollment_updated / enrollment_deleted
├── webhooks_btec_units.py          ← btec_definition_updated / btec_definition_deleted
└── webhooks_shared.py              ← helpers مشتركة (call_moodle_ws, fetch_zoho_full_record, ...)

moodle_plugin/
├── version.php                     ← 2026022209
├── db/services.php                 ← تسجيل كل WS functions
└── classes/external/
    ├── student_dashboard.php       ← معظم WS functions
    └── create_btec_definition.php  ← execute() + delete()
```

---

## URL الـ Webhooks

**Base URL**: `https://<ngrok-url>/api/v1/webhooks/student-dashboard`

---

## جدول كل الـ Endpoints

### 1. Student Dashboard Sync (`webhooks_dashboard_sync.py`)

| Endpoint | Zoho Module | Moodle WS | وصف |
|----------|-------------|-----------|-----|
| `POST /student_updated` | BTEC_Students | `local_mzi_update_student` | إنشاء/تحديث student record |
| `POST /registration_created` | BTEC_Registrations | `local_mzi_create_registration` | تسجيل طالب في برنامج |
| `POST /payment_recorded` | BTEC_Payments | `local_mzi_record_payment` | تسجيل دفعة |
| `POST /grade_submitted` | BTEC_Grades | `local_mzi_submit_grade` | رفع درجة |
| `POST /request_status_changed` | BTEC_Requests | `local_mzi_update_request_status` | تحديث حالة طلب |
| `POST /submit_student_request` | BTEC_Requests | `local_mzi_update_request_status` | إرسال طلب جديد |
| `POST /student_deleted` | BTEC_Students | `local_mzi_delete_student` | حذف ناعم |
| `POST /registration_deleted` | BTEC_Registrations | `local_mzi_delete_registration` | إلغاء تسجيل |
| `POST /payment_deleted` | BTEC_Payments | `local_mzi_delete_payment` | إلغاء دفعة |
| `POST /grade_deleted` | BTEC_Grades | `local_mzi_delete_grade` | حذف درجة |
| `POST /request_deleted` | BTEC_Requests | `local_mzi_delete_request` | إلغاء طلب |

---

### 2. Course Management (`webhooks_moodle_courses.py`)

| Endpoint | Trigger في Zoho | وصف |
|----------|-----------------|-----|
| `POST /class_updated` | BTEC_Classes — **Edit only** (لا Create) | Decision tree كامل |
| `POST /class_deleted` | BTEC_Classes — Delete | Soft delete من local_mzi_classes |

#### Decision Tree — `class_updated`

```
moodle_class_id موجود في Zoho؟
  │
  ├── نعم ──→ UPDATE course في Moodle
  │            + sync جميع BTEC_Enrollments
  │
  └── لا  ──→ status == Active؟
               │
               ├── نعم ──→ CREATE course جديد في Moodle
               │            + enrol defaults + teacher
               │            + كتابة Moodle_Class_ID في Zoho
               │            + sync جميع BTEC_Enrollments
               │
               └── لا  ──→ تجاهل Moodle، upsert local_mzi_classes فقط
```

#### الـ Default Enrollments عند إنشاء Course

| User ID | Role | وصف |
|---------|------|-----|
| 8157 | editingteacher (3) | IT Support |
| 8181 | editingteacher (3) | Student Affairs |
| 8154 | editingteacher (3) | CEO |
| 2 | manager (1) | Super Admin |
| 8133 | editingteacher (3) | IT Program Leader — **فقط إذا Major == IT** |
| Academic_Email | editingteacher (3) | المدرس — يُحدَّد من BTEC_Teachers |

---

### 3. Enrollment Management (`webhooks_moodle_enrol.py`)

| Endpoint | Zoho Module | Moodle WS | وصف |
|----------|-------------|-----------|-----|
| `POST /enrollment_updated` | BTEC_Enrollments | `local_mzi_update_enrollment` | تسجيل/تحديث طالب في course |
| `POST /enrollment_deleted` | BTEC_Enrollments | `local_mzi_delete_enrollment` | إلغاء تسجيل + unenrol من Moodle |

**Guard مهم في `enrollment_updated`**: إذا كان `zoho_student_id` فاضي (token failure) → يرجع `{"status": "skipped"}` بدل 500.

#### منطق `update_enrollment` في PHP (3-tier resolution)
```
1. local_mzi_students.moodle_user_id
2. mdl_user.email = academic_email
3. mdl_user.username = lower(student_id)
```

---

### 4. BTEC Definition Sync (`webhooks_btec_units.py`)

| Endpoint | Zoho Module | Moodle WS | وصف |
|----------|-------------|-----------|-----|
| `POST /btec_definition_updated` | BTEC | `local_mzi_create_btec_definition` | Upsert grading definition |
| `POST /btec_definition_deleted` | BTEC | `local_mzi_delete_btec_definition` | حذف grading definition |

#### منطق الـ Criteria (الترتيب الصحيح)

```python
# Sort order عالمي (مستمر بدون reset)
P1=1, P2=2, ..., P10=10,
M1=11, M2=12, ..., M3=13,
D1=14, D2=15, ...
```

حقول Zoho BTEC:
- Pass:        `P1_description` … `P19_description` (level=1)
- Merit:       `M1_description` … `M9_description`  (level=2)
- Distinction: `D1_description` … `D6_description`  (level=3)

---

## Moodle Plugin — WS Functions المسجّلة

**Plugin**: `local_moodle_zoho_sync` | **Version**: `2026022209`

| WS Function | PHP Class | Method | نوع |
|------------|-----------|--------|-----|
| `local_mzi_update_student` | `student_dashboard` | `update_student` | write |
| `local_mzi_create_registration` | `student_dashboard` | `create_registration` | write |
| `local_mzi_record_payment` | `student_dashboard` | `record_payment` | write |
| `local_mzi_create_class` | `student_dashboard` | `create_class` | write |
| `local_mzi_update_enrollment` | `student_dashboard` | `update_enrollment` | write |
| `local_mzi_submit_grade` | `student_dashboard` | `submit_grade` | write |
| `local_mzi_update_request_status` | `student_dashboard` | `update_request_status` | write |
| `local_mzi_enrol_users` | `student_dashboard` | `enrol_users_to_course` | write |
| `local_mzi_get_moodle_ids` | `student_dashboard` | `get_moodle_ids` | read |
| `local_mzi_delete_student` | `student_dashboard` | `delete_student` | write |
| `local_mzi_delete_registration` | `student_dashboard` | `delete_registration` | write |
| `local_mzi_delete_payment` | `student_dashboard` | `delete_payment` | write |
| `local_mzi_delete_class` | `student_dashboard` | `delete_class` | write |
| `local_mzi_delete_enrollment` | `student_dashboard` | `delete_enrollment` | write |
| `local_mzi_delete_grade` | `student_dashboard` | `delete_grade` | write |
| `local_mzi_delete_request` | `student_dashboard` | `delete_request` | write |
| `local_mzi_sync_teacher` | `student_dashboard` | `sync_teacher` | write |
| `local_mzi_create_btec_definition` | `create_btec_definition` | `execute` | write |
| `local_mzi_delete_btec_definition` | `create_btec_definition` | `delete` | write |
| `local_moodle_zoho_sync_create_btec_definition` | `create_btec_definition` | `execute` | write (legacy) |
| `core_user_get_users_by_field` | Moodle core | — | read |

---

## Zoho Workflow Rules المطلوبة

| Rule Name | Module | Trigger | Webhook URL |
|-----------|--------|---------|-------------|
| MZI - BTEC_Students | BTEC_Students | Create + Edit | `.../student_updated` |
| MZI - BTEC_Students Delete | BTEC_Students | Delete | `.../student_deleted` |
| MZI - BTEC_Registrations | BTEC_Registrations | Create + Edit | `.../registration_created` |
| MZI - BTEC_Registrations Delete | BTEC_Registrations | Delete | `.../registration_deleted` |
| MZI - BTEC_Payments | BTEC_Payments | Create + Edit | `.../payment_recorded` |
| MZI - BTEC_Payments Delete | BTEC_Payments | Delete | `.../payment_deleted` |
| MZI - BTEC_Classes | BTEC_Classes | **Edit فقط** (لا Create) | `.../class_updated` |
| MZI - BTEC_Classes Delete | BTEC_Classes | Delete | `.../class_deleted` |
| MZI - BTEC_Enrollments | BTEC_Enrollments | Create + Edit | `.../enrollment_updated` |
| MZI - BTEC_Enrollments Delete | BTEC_Enrollments | Delete | `.../enrollment_deleted` |
| MZI - BTEC_Grades | BTEC_Grades | Create + Edit | `.../grade_submitted` |
| MZI - BTEC_Grades Delete | BTEC_Grades | Delete | `.../grade_deleted` |
| MZI - BTEC_Requests | BTEC_Requests | Create + Edit | `.../request_status_changed` |
| MZI - BTEC_Requests Delete | BTEC_Requests | Delete | `.../request_deleted` |
| MZI - BTEC (Definition) | BTEC | Create + Edit | `.../btec_definition_updated` |
| MZI - BTEC Delete | BTEC | Delete | `.../btec_definition_deleted` |

---

## إصلاحات تمت خلال التطوير

| المشكلة | السبب | الحل |
|---------|-------|------|
| `local_mzi_enrol_users` not found | Plugin ما اتعمّل له upgrade | رفع version.php + Notifications |
| `TypeError` في `enrol_user()` | User ID غير موجود في mdl_user | `$DB->record_exists()` check قبل الـ enrol |
| `enrollment_updated` 500 | Zoho token منتهي → `zoho_student_id` فاضي | Guard يرجع `skipped` |
| `local_mzi_create_btec_definition` not found | ما كان مسجّل في services.php بالاسم الصح | أضفنا كلا الاسمين |
| Criteria ترتيب خاطئ (P1,M1,D1,P2...) | sortorder reset مع كل level | عداد global مستمر |
| Zoho يعيد المحاولة (webhook retries) | 500 errors سابقة → Zoho يعيد تلقائياً | بعد تصحيح الأخطاء رجع 200 وتوقف |

---

## نقاط Action مطلوبة (Manual)

### يُرفع للسيرفر
```
moodle_plugin/version.php                          (2026022209)
moodle_plugin/db/services.php                      (كل WS functions)
moodle_plugin/classes/external/student_dashboard.php
moodle_plugin/classes/external/create_btec_definition.php  (execute + delete)
```

### بعد الرفع
```
Moodle Admin → Site administration → Notifications → Continue
```

### Zoho Rule تحتاج تعديل
- **MZI - BTEC_Classes**: غيّر trigger من Create+Edit → **Edit فقط**
- **URL**: تأكد إنها `.../class_updated` (مش `class_created`)

### Zoho Refresh Token
إذا ظهر `Access Denied`:
1. [api-console.zoho.com](https://api-console.zoho.com) → Self Client
2. Generate Grant Token بـ scopes: `ZohoCRM.modules.ALL,ZohoCRM.settings.ALL`
3. Exchange for refresh token → حدّث `ZOHO_REFRESH_TOKEN` في `.env`
4. أعد تشغيل السيرفر
