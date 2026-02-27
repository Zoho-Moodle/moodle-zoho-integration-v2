# REBUILD BLUEPRINT — Moodle-Zoho Integration v3
**مخطط إعادة البناء المنظّم والمبسّط**

> **تاريخ الإعداد:** 22 فبراير 2026  
> **الحالة:** وثيقة مرجعية للتوحيد والتبسيط — **لا تعديل على الكود في هذه المرحلة**  
> **القاعدة الذهبية:** بسيط وعملي > معقد ومثالي

---

## A) ملخص تنفيذي

### نقاط الألم الحالية (من الكود)

| المشكلة | الملف / المكان |
|---------|----------------|
| **قناتان مزدوجتان للـ webhook** — `/api/v1/events/zoho/student` يحدّث SQLite فقط دون إرسال لـ Moodle WS، بينما `/api/v1/webhooks/student-dashboard/student_updated` هو الصحيح | `events.py` + `student_dashboard_webhooks.py` |
| **PostgreSQL و SQLite و MariaDB في نفس الوقت** — الـ middleware يكتب بيانات الطالب في SQLite (backend DB) وهذه نسخة مكررة للبيانات الموجودة في `local_mzi_students` في MySQL | `base.py`, `.env` |
| **Redis مستخدم في `event_handler_service`** بدون تثبيت فعلي — `cache.delete_pattern()` يُستدعى لكن Redis غير موجود | `event_handler_service.py` line ~218 |
| **`EventProcessingResult` تغيير API** — بعض الـ calls تستخدم `success/action/message` (قديم) بدل `status/action_taken/error` (حديث) | `event_handler_service.py` line 225 (تم تصليحه) |
| **حقول مفقودة في تحديث الطالب** — `city`, `national_id`, `address` كانت غائبة عن الـ update في `event_handler_service` | `event_handler_service.py` (تم تصليحه) |
| **تعقيد غير مبرر** — Extension API, Multi-tenancy, Alembic migrations, APScheduler كلها موجودة ولا تُستخدم فعلياً | `app/api/v1/endpoints/extension_*` |
| **طلبات الطلاب غير مكتملة** — صفحة `requests.php` موجودة لكن لا يوجد WS function لإنشاء طلب في Zoho Student_Requests | `moodle_plugin/ui/student/requests.php` |
| **بطاقة الطالب** موجودة لكن لا تُنشئ record في Zoho | `student_card.php` |
| **Admin dashboard** موجود كـ pages منفصلة غير متصلة ببعضها بشكل كامل | `moodle_plugin/ui/admin/` |

### ملخص المعمارية المستهدفة

```
Zoho CRM (مصدر الحقيقة للبيانات الإدارية)
   │
   │ Webhook (HMAC verified)
   ▼
FastAPI Middleware (بوابة التكامل فقط)
   │ يُعيّن api_names → يستدعي Moodle WS → يسجّل audit log في SQLite
   ▼
Moodle Web Services (local_mzi_*)
   │
   ▼
Moodle DB (local_mzi_* tables) ← مصدر الحقيقة للعرض
   │
   ▼
Student Dashboard (PHP — يقرأ DB مباشرة، صفر API calls)
```

### ما سنزيله
- ❌ Redis (لا يوجد فائدة في هذا السياق)
- ❌ Dashboard Read APIs من Backend (لا يجب استدعاء backend لقراءة بيانات الداشبورد)
- ❌ Student/Business data في قاعدة بيانات الـ Middleware (SQLite/PostgreSQL لـ backend) — يبقى للـ audit log فقط
- ❌ Extension API / Multi-tenancy (تعقيد غير مستخدم)
- ❌ Alembic migrations (نستخدم `Base.metadata.create_all()` للـ audit tables فقط)

---

## B) خريطة النظام الحالي "AS-IS"

### جدول التدفقات الموجودة

| التدفق | المصدر | المسار | الوجهة | الجداول | الحالة |
|--------|--------|--------|--------|---------|--------|
| Zoho → Student Profile | BTEC_Students webhook | `/events/zoho/student` | SQLite backend فقط | `students` (SQLite) | ⚠️ ناقص — لا يصل لـ Moodle |
| Zoho → Student Profile (الصحيح) | BTEC_Students webhook | `/webhooks/student-dashboard/student_updated` | Moodle WS `local_mzi_update_student` | `local_mzi_students` | ✅ يعمل |
| Zoho → Registration | BTEC_Registrations webhook | `/webhooks/student-dashboard/registration_created` | `local_mzi_create_registration` | `local_mzi_registrations` | ✅ يعمل |
| Zoho → Payment | BTEC_Payments webhook | `/webhooks/student-dashboard/payment_recorded` | `local_mzi_record_payment` | `local_mzi_payments` | ✅ يعمل |
| Zoho → Class | BTEC_Classes webhook | `/webhooks/student-dashboard/class_created` | `local_mzi_create_class` | `local_mzi_classes` | ✅ يعمل |
| Zoho → Enrollment | BTEC_Enrollments webhook | `/webhooks/student-dashboard/enrollment_updated` | `local_mzi_update_enrollment` | `local_mzi_enrollments` | ✅ يعمل |
| Zoho → Grade mirror | BTEC_Grades webhook | `/webhooks/student-dashboard/grade_submitted` | `local_mzi_submit_grade` | `local_mzi_grades` | ✅ يعمل |
| Moodle Grade → Zoho | Observer grading event | Event Log → Backend | Zoho `BTEC_Grades` | `local_mzi_grade_queue`, `local_mzi_event_log` | ✅ يعمل |
| Student Request → Zoho | `requests.php` AJAX | Backend → Zoho | Zoho `BTEC_Student_Requests` | - | ⚠️ جزئي — `/submit_student_request` موجود |
| Zoho Request status → Moodle | BTEC_Student_Requests webhook | `/webhooks/student-dashboard/request_status_changed` | `local_mzi_update_request_status` | `local_mzi_requests` | ✅ موجود |
| BTEC Templates | Admin action | `/api/v1/btec-templates/sync` | Moodle `local_moodle_zoho_sync_create_btec_definition` | `grading_definitions`, `local_mzi_btec_templates` | ✅ يعمل |
| Moodle User Created → Zoho | Observer | Event Log → Backend → Zoho | BTEC_Students.Student_Moodle_ID | `local_mzi_students` | ✅ يعمل |
| Student Card | `student_card.php` | AJAX → PHP | لا شيء يُرسل لـ Zoho | - | ❌ لا يُنشئ record في Zoho |

### نقاط الخلل الموثّقة

1. **مسار مزدوج للطالب** — Zoho يضرب endpoint قديم (`/events/zoho/student`) أو الصحيح؟ يجب التحقق من إعدادات الـ webhook في Zoho.
2. **Redis غير موجود** — كود `cache.delete_pattern()` في `event_handler_service.py` سيتسبب بخطأ صامت.
3. **Student_Requests لا تصل لـ Zoho** من صفحة `requests.php` بشكل مكتمل لجميع أنواع الطلبات.
4. **Student Card** لا تُرسل للـ middleware ولا تُنشئ record في Zoho BTEC_Student_Requests.
5. **حقل `study_mode`** في `local_mzi_registrations` غير متطابق مع `FIELD_MAPPINGS`.
6. **مراقبة نافذة الطلبات** (Enroll Next Semester, Class Drop) غائبة — لا يوجد جدول لتخزين الفترات المسموحة.

---

## C) معمارية النظام المستهدف "TO-BE"

```
┌─────────────────────────────────────────────────────────────────────┐
│                         ZOHO CRM                                     │
│  BTEC_Students │ BTEC_Classes │ BTEC_Enrollments │ BTEC_Payments    │
│  BTEC_Registrations │ Products │ BTEC_Units │ BTEC_Teachers         │
│  BTEC_Grades (mirror) │ BTEC_Student_Requests                        │
└───────────────────┬─────────────────────────────────┬───────────────┘
                    │ Webhook (HMAC)                   │ API (read/write)
                    ▼                                  ▲
┌───────────────────────────────────────────────────────────────────┐
│                    FASTAPI MIDDLEWARE (port 8001)                   │
│                                                                    │
│  ┌──────────────────┐  ┌──────────────────┐  ┌─────────────────┐  │
│  │ Webhook Handlers │  │ Field Mapper     │  │ Moodle WS Proxy │  │
│  │ (HMAC verify)    │→ │ (FIELD_MAPPINGS) │→ │ (httpx client)  │  │
│  └──────────────────┘  └──────────────────┘  └─────────────────┘  │
│                                                                    │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │ SQLite (Audit Log only)                                      │  │
│  │ integration_events_log │ (no student business data here)    │  │
│  └──────────────────────────────────────────────────────────────┘  │
└───────────────────────────────────┬───────────────────────────────┘
                                    │ HTTPS POST (WS token)
                                    ▼
┌───────────────────────────────────────────────────────────────────┐
│                     MOODLE (lms.abchorizon.com)                    │
│                                                                    │
│  Web Services (services.php)                                       │
│  local_mzi_update_student    local_mzi_create_class               │
│  local_mzi_create_registration  local_mzi_update_enrollment       │
│  local_mzi_record_payment    local_mzi_submit_grade               │
│  local_mzi_update_request_status  local_mzi_sync_teacher          │
│  local_moodle_zoho_sync_create_btec_definition                    │
│                        │                                           │
│                        ▼ writes                                    │
│  ┌────────────────────────────────────────────────────────────┐   │
│  │  local_mzi_students        local_mzi_registrations        │   │
│  │  local_mzi_payments        local_mzi_classes              │   │
│  │  local_mzi_enrollments     local_mzi_grades               │   │
│  │  local_mzi_requests        local_mzi_teachers             │   │
│  │  local_mzi_programs        local_mzi_units                │   │
│  │  local_mzi_request_windows (جديد)                         │   │
│  └────────────────────────────┬───────────────────────────────┘   │
│                               │ يقرأ مباشرة (PHP)                 │
│                               ▼                                    │
│  ┌────────────────────────────────────────────────────────────┐   │
│  │  Student Dashboard (PHP — direct DB reads)                 │   │
│  │  profile.php │ programs.php │ classes.php │ grades.php     │   │
│  │  requests.php │ student_card.php                           │   │
│  └────────────────────────────────────────────────────────────┘   │
│                                                                    │
│  ┌────────────────────────────────────────────────────────────┐   │
│  │  Admin Dashboard (PHP — direct DB reads + config writes)   │   │
│  │  dashboard.php │ student_search.php │ sync_management.php  │   │
│  │  grade_queue_monitor.php │ health_check.php │ event_logs.php│   │
│  │  request_windows.php (جديد)                               │   │
│  └────────────────────────────────────────────────────────────┘   │
│                                                                    │
│  ┌────────────────────────────────────────────────────────────┐   │
│  │  Moodle Observers + Scheduled Tasks                        │   │
│  │  grade_observer → event_log → backend → Zoho BTEC_Grades  │   │
│  │  user_observer  → backend → Zoho Student_Moodle_ID        │   │
│  └────────────────────────────────────────────────────────────┘   │
└───────────────────────────────────────────────────────────────────┘
```

---

## D) كتالوج الخدمات — عقود التكامل

---

### D1) مزامنة الطلاب — BTEC_Students

| العنصر | القيمة |
|--------|--------|
| **المُشغّل** | Zoho webhook: BTEC_Students create/update/delete |
| **Endpoint** | `POST /api/v1/webhooks/student-dashboard/student_updated` |
| **الـ api_names المستخدمة** | `id`, `First_Name`, `Last_Name`, `Display_Name`, `Academic_Email`, `Phone_Number`, `Address`, `City`, `Nationality`, `Birth_Date`, `Gender`, `Status`, `Student_Moodle_ID`, `National_Number`, `Emergency_Contact_Name`, `Emergency_Phone_Number`, `Created_Time`, `Modified_Time` |
| **قاعدة التعيين** | FIELD_MAPPINGS["students"] في `student_dashboard_webhooks.py` |
| **Moodle WS** | `local_mzi_update_student` |
| **الجداول** | `local_mzi_students` (upsert بناءً على `zoho_student_id`) |
| **الـ Idempotency** | `ON DUPLICATE KEY UPDATE` / upsert على `zoho_student_id` |
| **إرسال Moodle_ID لـ Zoho** | Moodle Observer عند إنشاء user → يرسل `Student_Moodle_ID` لـ Zoho عبر backend |
| **عند الحذف** | `POST /webhooks/student-dashboard/student_deleted` → soft delete: `status = 'Deleted'` |
| **معالجة الأخطاء** | إعادة المحاولة 3 مرات، تسجيل في `integration_events_log` |

---

### D2) مزامنة البرامج — Products (Programs)

| العنصر | القيمة |
|--------|--------|
| **المُشغّل** | Zoho webhook أو Full Sync يدوي |
| **Endpoint** | `POST /api/v1/sync/programs` (Full Sync) |
| **الـ api_names** | `id`, `Product_Name`, `Product_Code`, `Product_Level`, `Product_Duration`, `Product_Category`, `Program_Price`, `Currency` |
| **Moodle WS** | WS function مطلوبة: `local_mzi_upsert_program` *(تحتاج إنشاء)* |
| **الجداول** | `local_mzi_programs` |
| **الـ Idempotency** | upsert على `zoho_program_id` |
| **معالجة الأخطاء** | تسجيل في event log، لا يوقف المزامنة |

---

### D3) مزامنة التسجيلات — BTEC_Registrations

| العنصر | القيمة |
|--------|--------|
| **المُشغّل** | Zoho webhook: BTEC_Registrations create/update |
| **Endpoint** | `POST /api/v1/webhooks/student-dashboard/registration_created` |
| **الـ api_names** | `id`, `Name`, `Student_ID` (lookup→zoho_student_id), `Program`, `Program_Name`, `Registration_Number`, `Registration_Date`, `Registration_Status`, `Status`, `Program_Price`, `Total_Fees`, `Paid_Amount`, `Remaining_Amount`, `Currency`, `Payment_Plan`, `Study_Mode`, `Expected_Graduation`, `Number_of_Installments`, `Program_Level`, `Created_Time`, `Modified_Time` |
| **Moodle WS** | `local_mzi_create_registration` |
| **الجداول** | `local_mzi_registrations` |
| **شرط خاص** | إذا وصل Payment قبل Registration: `ensure_registration_synced()` يُشغّل تلقائياً |
| **الـ Idempotency** | upsert على `zoho_registration_id` |
| **عند الحذف** | `status = 'Cancelled'` |

---

### D4) مزامنة الفصول — BTEC_Classes + إنشاء Moodle Course

| العنصر | القيمة |
|--------|--------|
| **المُشغّل** | Zoho webhook: BTEC_Classes create/update |
| **Endpoint** | `POST /api/v1/webhooks/student-dashboard/class_created` |
| **الـ api_names** | `id`, `Class_Name`, `Class_Short_Name`, `BTEC_Program` (lookup_id + lookup_name), `Unit` (lookup_id + lookup_name), `Teacher` (lookup_id + lookup_name), `Moodle_Class_ID`, `Class_Status`, `Start_Date`, `End_Date`, `Created_Time`, `Modified_Time` |
| **Moodle WS** | `local_mzi_create_class` |
| **الجداول** | `local_mzi_classes` |
| **سلوك Moodle** | WS تتحقق: إذا `moodle_class_id` فارغ → `core_course_create_courses` → تُعيد `moodle_class_id` |
| **كتابة ID لـ Zoho** | Backend يستدعي Zoho API: `PATCH BTEC_Classes/{id}` بـ `Moodle_Class_ID` |
| **تسجيل المعلم والمدراء** | `enrol_manual_enrol_users` للـ teacher + site admins في الـ course |
| **الـ Idempotency** | upsert على `zoho_class_id`؛ إذا `moodle_class_id` موجود → update فقط |
| **عند الحذف** | `class_status = 'Cancelled'`؛ اختياري: إلغاء تفعيل الـ course في Moodle |

---

### D5) مزامنة التسجيل في الفصول — BTEC_Enrollments + تسجيل/فصل المستخدمين

| العنصر | القيمة |
|--------|--------|
| **المُشغّل** | Zoho webhook: BTEC_Enrollments create/update |
| **Endpoint** | `POST /api/v1/webhooks/student-dashboard/enrollment_updated` |
| **الـ api_names** | `id`, `Enrolled_Students` (lookup_id→zoho_student_id), `Classes` (lookup_id→zoho_class_id), `Enrollment_Status`, `Enrollment_Date`, `Created_Time`, `Modified_Time` |
| **Moodle WS** | `local_mzi_update_enrollment` |
| **الجداول** | `local_mzi_enrollments` |
| **سلوك Moodle** | قرر Enrollment_Status: إذا Active → `enrol_manual_enrol_users`؛ إذا Withdrawn → `enrol_manual_unenrol_users` |
| **الـ Idempotency** | upsert على `zoho_enrollment_id`؛ `enrol_manual` آمن للاستدعاء المتكرر |
| **عند الحذف** | unenrol المستخدم من الـ course |

---

### D6) مزامنة المدفوعات — BTEC_Payments

| العنصر | القيمة |
|--------|--------|
| **المُشغّل** | Zoho webhook: BTEC_Payments create/update |
| **Endpoint** | `POST /api/v1/webhooks/student-dashboard/payment_recorded` |
| **الـ api_names** | `id`, `Registration_ID` (lookup_id), `Student_ID` (lookup_id), `Payment_Amount`, `Payment_Date`, `Payment_Method`, `Note`, `Created_Time`, `Modified_Time` |
| **Moodle WS** | `local_mzi_record_payment` |
| **الجداول** | `local_mzi_payments` |
| **شرط خاص** | يتحقق من وجود Registration؛ إذا غائبة: `ensure_registration_synced()` |
| **الـ Idempotency** | upsert على `zoho_payment_id` |
| **عند الحذف** | `payment_status = 'Voided'` |

---

### D7) مزامنة النتائج — BTEC_Grades (اتجاهان)

#### A) Moodle → Zoho (المسار الرئيسي)
| العنصر | القيمة |
|--------|--------|
| **المُشغّل** | Moodle Observer: `\core\event\user_graded` |
| **المسار** | Observer → `local_mzi_grade_queue` → Scheduled Task → Backend `/api/v1/sync/grades` → Zoho BTEC_Grades |
| **البيانات المُرسلة** | grade value, learning outcomes, composite_key, moodle_user_id, assignment_id |
| **آلية RR/F** | Scheduled task يفحص grades للـ R → يبحث عن الـ RR submission → يُحدّث Zoho |
| **حالة الجداول** | `local_mzi_grade_queue` + `local_mzi_event_log` |

#### B) Zoho → Moodle (Mirror للقراءة فقط في الداشبورد)
| العنصر | القيمة |
|--------|--------|
| **المُشغّل** | Zoho webhook: BTEC_Grades create/update |
| **Endpoint** | `POST /api/v1/webhooks/student-dashboard/grade_submitted` |
| **الـ api_names** | `id`, `Student` (lookup_id), `Class` (lookup_id), `BTEC_Unit` (lookup_id), `Grade`, `Moodle_Grade_Composite_Key`, `Moodle_Course_ID`, `Moodle_User_ID`, `Created_Time`, `Modified_Time` |
| **Moodle WS** | `local_mzi_submit_grade` |
| **الجداول** | `local_mzi_grades` (للعرض في الداشبورد) |
| **الـ Idempotency** | upsert على `zoho_grade_id` |

---

### D8) طلبات الطلاب — Moodle → Zoho + Mirror

#### أنواع الطلبات
1. **التسجيل للفصل الدراسي القادم** — checkbox + رسالة تأكيد (مقيّدة بنافذة زمنية)
2. **حذف فصل (Class Drop)** — اختيار 1 أو 2 فصل من المسجّلين مؤخراً (خلال شهر، مقيّدة بنافذة زمنية)
3. **تقديم متأخر (Late Submission)** — اسم الوحدة + صورة إيصال الدفع
4. **تغيير معلومات** — نموذج تعديل البيانات الشخصية
5. **بطاقة الطالب** — تُنشئ record في Zoho (`BTEC_Student_Requests`) لكل بطاقة تُولَّد

#### المسار
```
Moodle UI (requests.php / student_card.php)
   ↓ AJAX → PHP handler
   ↓ HTTP POST → Backend /api/v1/webhooks/student-dashboard/submit_student_request
   ↓ Zoho API → إنشاء record في BTEC_Student_Requests
   ↓ (اختياري) Zoho webhook → /webhooks/student-dashboard/request_status_changed
   ↓ Moodle WS local_mzi_update_request_status
   ↓ local_mzi_requests (mirror للعرض)
```

| العنصر | القيمة |
|--------|--------|
| **Endpoint للإرسال** | `POST /api/v1/webhooks/student-dashboard/submit_student_request` |
| **الـ api_names لـ Student_Requests** | `Subject`, `Student_ID` (lookup), `Request_Type`, `Status`, `Description`, `Attachment` (إيصال), `Class_Drop_Classes` (multi-select lookup), `Request_Date` |
| **Moodle WS (mirror)** | `local_mzi_update_request_status` |
| **الجداول** | `local_mzi_requests` (mirror فقط، Zoho هو مصدر الحقيقة) |

#### ضبط النوافذ الزمنية
- **جدول جديد مطلوب:** `local_mzi_request_windows`

```sql
CREATE TABLE local_mzi_request_windows (
  id INT AUTO_INCREMENT PRIMARY KEY,
  request_type VARCHAR(50) NOT NULL,  -- 'enroll_next_semester', 'class_drop'
  enabled TINYINT(1) DEFAULT 0,
  open_from INT(10),   -- Unix timestamp
  open_until INT(10),  -- Unix timestamp
  updated_by INT(10),
  updated_at INT(10)
);
```

- Admin يضبط النوافذ من `request_windows.php`
- Student UI تتحقق من الجدول عند تحميل الصفحة → تُخفي/تُعطّل نوع الطلب خارج النافذة

---

### D9) مزامنة قوالب BTEC — كما هي (تعمل بشكل صحيح)

| العنصر | القيمة |
|--------|--------|
| **المُشغّل** | Admin يُشغّل يدوياً من `btec_templates.php` |
| **Endpoint** | `POST /api/v1/btec-templates/sync` |
| **المصدر** | Zoho BTEC_Units (unit definitions + learning outcomes) |
| **Moodle WS** | `local_moodle_zoho_sync_create_btec_definition` |
| **الجداول** | `grading_definitions` + `local_mzi_btec_templates` |
| **الحالة** | ✅ يعمل — لا تعديل مطلوب |

---

## E) متطلبات لوحة الإدارة — Admin Dashboard

### الصفحات المطلوبة

#### E1) الإعدادات العامة — `settings.php`
- تكوين Zoho API credentials
- تكوين Moodle token + base URL
- تكوين ngrok / webhook URL
- اختبار الاتصال بـ Zoho وبـ Moodle WS
- حفظ كل الإعدادات في `local_mzi_config`

#### E2) إدارة الطلاب — `student_dashboard_management.php`
- **البحث** عن أي طالب بـ: Moodle User ID، Student_ID، Email
- **العرض** (read-only من `local_mzi_*`):
  - Profile / البرنامج / الفصول والنتائج / الطلبات
  - **نفس UI style الداشبورد**، لكن للمشرف
- **إدارة نوافذ الطلبات:**
  - نافذة "التسجيل للفصل التالي": تفعيل/تعطيل + من تاريخ → إلى تاريخ
  - نافذة "حذف الفصل": تفعيل/تعطيل + من تاريخ → إلى تاريخ
  - يُحفظ في `local_mzi_request_windows`
  - Student UI تقرأ هذا الجدول تلقائياً

#### E3) إدارة ومراقبة مزامنة النتائج — `grade_queue_monitor.php`
*(موجود — يحتاج تطوير)*
- عرض `local_mzi_grade_queue` بحالاتها: BASIC_SENT / ENRICHED / FAILED
- زر إعادة المحاولة لـ grades الفاشلة
- إحصائيات: كم grade سُنت اليوم / فشلت / في الانتظار
- Scheduled Task status: آخر تشغيل، النتيجة

#### E4) إدارة ومراقبة مزامنة المستخدمين — `sync_users_monitor.php`
- عرض طلاب مفقودي `moodle_user_id` في `local_mzi_students`
- زر "Send Moodle ID to Zoho" يدوياً
- Observer status: آخر event معالج

#### E5) إدارة قوالب BTEC — `btec_templates.php`
*(موجود)*
- عرض القوالب المزامنة في `local_mzi_btec_templates`
- زر "Sync All Templates" يُشغّل الـ endpoint
- عرض آخر sync time لكل template

#### E6) سجلات الأحداث — `event_logs.php`
*(موجود — يحتاج تحسين)*
- عرض `local_mzi_event_log` + `local_mzi_webhook_logs`
- فلتر حسب: module، status، تاريخ
- تفاصيل الـ payload لكل حدث
- زر إعادة المحاولة للأحداث الفاشلة

#### E7) فحص الصحة — `health_check.php`
*(موجود)*
- حالة الاتصال بـ Zoho API
- حالة الاتصال بـ Moodle WS
- حالة الـ Backend (ping `/health`)
- إحصائيات `local_mzi_*` tables (عدد السجلات)

---

## F) خطة إعادة البناء المرحلية

### المرحلة 0 — تنظيف فوري (يوم واحد)

**الهدف:** إزالة الكود الميت والمسارات المزدوجة

**الملفات:**
- `backend/app/services/event_handler_service.py` — حذف `cache.delete_pattern()` (Redis غير موجود)
- `backend/.env` — تأكيد إزالة Redis URL إن وجد
- `backend/app/api/v1/endpoints/events.py` — إيقاف أو توجيه `/events/zoho/student` → `student_dashboard_webhooks.py`

**المخرجات:**
- ✅ لا أخطاء صامتة من Redis
- ✅ مسار واحد واضح للـ webhook

**خطة التراجع:** `git revert` على الـ commits

**الاختبار:**
- أرسل webhook test من Zoho → تأكد من وصوله لـ student_dashboard_webhooks
- تحقق من اللوق: لا`cache` errors

---

### المرحلة 1 — تصليح المسار الأساسي (3 أيام)

**الهدف:** تأكيد أن تحديث الطالب في Zoho يصل كاملاً لـ `local_mzi_students`

**الملفات:**
- `backend/app/api/v1/endpoints/student_dashboard_webhooks.py` — تأكيد صحة FIELD_MAPPINGS (City ✅، National_Number ✅)
- `moodle_plugin/classes/external/student_dashboard.php` — تأكيد `update_student` يُعالج `city` و`national_id`
- Zoho dashboard — التحقق من أن webhook مُسجّل على `/api/v1/webhooks/student-dashboard/student_updated` وليس `/events/zoho/student`

**المخرجات:**
- ✅ تعديل أي حقل في BTEC_Students → يظهر في profile.php خلال ثوانٍ

**الاختبار:**
1. عدّل City في Zoho → تحقق من `local_mzi_students.city`
2. عدّل National_Number → تحقق من `local_mzi_students.national_id`
3. تحقق من اللوق: `✅ Moodle WS updated student`

---

### المرحلة 2 — جدول نوافذ الطلبات (يومان)

**الهدف:** إنشاء آلية التحكم في أنواع الطلبات الزمنية

**الملفات:**
- **إنشاء:** `moodle_plugin/db/upgrade.php` — إضافة `local_mzi_request_windows`
- **إنشاء:** `moodle_plugin/db/install.xml` — إضافة TABLE `local_mzi_request_windows`
- **تعديل:** `moodle_plugin/version.php` → رقم إصدار جديد
- **إنشاء:** `moodle_plugin/ui/admin/request_windows.php` — UI للإدارة
- **تعديل:** `moodle_plugin/ui/student/requests.php` — قراءة الجدول وإخفاء الأنواع المغلقة

**المخرجات:**
- ✅ Admin يتحكم في نوافذ طلب التسجيل وحذف الفصل
- ✅ Student يرى فقط الطلبات المتاحة في الوقت الحالي

**الاختبار:**
1. ضبط نافذة مغلقة → تحقق أن الخيار مخفي في UI الطالب
2. ضبط نافذة مفتوحة → تحقق أن الخيار يظهر

---

### المرحلة 3 — طلبات الطلاب الكاملة (3 أيام)

**الهدف:** تكامل كامل لجميع أنواع الطلبات مع Zoho

**الملفات:**
- **تعديل:** `moodle_plugin/ui/student/requests.php` — إضافة منطق لكل نوع طلب
- **تعديل:** `backend/app/api/v1/endpoints/student_dashboard_webhooks.py` — تحسين `/submit_student_request` لدعم كل الأنواع + رفع الملفات (Late Submission)
- **تعديل:** `moodle_plugin/ui/student/student_card.php` — إضافة استدعاء `/submit_student_request` عند توليد البطاقة
- **تحقق:** Moodle WS `local_mzi_update_request_status` يعمل بشكل صحيح

**المخرجات:**
- ✅ جميع أنواع الطلبات تُنشئ record في Zoho BTEC_Student_Requests
- ✅ بطاقة الطالب تُنشئ record في Zoho
- ✅ تحديث الحالة من Zoho يظهر في `local_mzi_requests`

**الاختبار:**
1. قدّم كل نوع طلب → تحقق من وجود record في Zoho
2. غيّر status في Zoho → تحقق من تحديث `local_mzi_requests`
3. ولّد بطاقة طالب → تحقق من Zoho

---

### المرحلة 4 — Admin Dashboard الكامل (5 أيام)

**الهدف:** لوحة إدارة وظيفية ومترابطة

**الملفات:**
- **إنشاء:** `moodle_plugin/ui/admin/request_windows.php`
- **تحسين:** `moodle_plugin/ui/admin/sync_management.php` — إضافة Full Sync لكل module
- **تحسين:** `moodle_plugin/ui/admin/grade_queue_monitor.php` — إضافة إعادة المحاولة
- **إنشاء:** `moodle_plugin/ui/admin/sync_users_monitor.php`
- **تحسين:** `moodle_plugin/ui/admin/event_logs.php` — فلتر + تفاصيل

**المخرجات:**
- ✅ Admin يرى حالة كل Module
- ✅ Admin يتحكم في نوافذ الطلبات
- ✅ Admin يبحث عن أي طالب ويرى بياناته

---

### المرحلة 5 — تنظيف Backend (يومان)

**الهدف:** إزالة الكود غير المستخدم وتبسيط الـ middleware

**الملفات للحذف / التعطيل:**
- `backend/app/api/v1/endpoints/extension_*.py` — غير مستخدم
- `backend/app/infra/db/models/extension.py` — غير مستخدم
- أي استخدامات Redis في الكود
- تبسيط `event_handler_service.py` — إزالة منطق SQLite للبيانات التجارية

**المخرجات:**
- ✅ Backend أصغر وأسرع
- ✅ لا code paths ميتة

---

## G) أهداف الأداء ولماذا يتحمّل النمو

### السيناريو: 2000–5000 طالب، 100+ مستخدم متزامن

| المكون | التحمّل | السبب |
|--------|---------|--------|
| **Student Dashboard** | 500+ concurrent reads | يقرأ من `local_mzi_*` مباشرة بـ SQL بسيط — صفر API calls خارجية |
| **Webhook Processor** | آلاف الأحداث يومياً | كل webhook = validate + map + 1 Moodle WS call → ~200ms |
| **Moodle WS** | عدة استدعاءات/ثانية | كل WS call = upsert واحد + idempotent = آمن للتكرار |
| **SQLite Audit Log** | غير محدود | تسجيل فقط، لا reads للداشبورد |
| **lاقراءة Admin** | سريع دائماً | `local_mzi_*` tables مع indexes على `moodle_user_id`, `zoho_*_id` |

### مبادئ التحمّل

1. **الداشبورد يقرأ DB محلي فقط** — لا Zoho API، لا Backend API عند عرض البيانات
2. **الـ Webhooks خفيفة الوزن** — validate + map + 1 WS call، ثم ترجع 200 OK فوراً  
3. **idempotent بالكامل** — كل WS call يمكن تكراره بأمان = retry بدون مشاكل  
4. **audit log منفصل** — تسجيل الأحداث في SQLite لا يؤثر على مسار البيانات  
5. **لا Redis** — الـ cache يُضيف تعقيداً ولا فائدة عملية في هذا الحجم  
6. **لا duplicated business data** — Zoho هو مصدر الحقيقة، `local_mzi_*` هو mirror للعرض  
7. **Scheduled Tasks لـ bulk** — التزامن الكامل يعمل ليلاً لا يُثقل الـ server أثناء الاستخدام

### تقدير الأحجام

```
5000 طالب × 5 جداول رئيسية = 25,000 صف
كل صف ~500 bytes = ~12.5 MB بيانات
MySQL يتعامل مع ملايين الصفوف بسهولة
لا حاجة لـ sharding أو Redis في هذا الحجم
```

---

## ملاحق

### الـ api_names المُقفلة (لا تُغيّر أبداً)

```
BTEC_Students:        First_Name, Last_Name, Display_Name, Academic_Email,
                      Phone_Number, Address, City, Nationality, Birth_Date,
                      Gender, Status, Student_Moodle_ID, National_Number,
                      Emergency_Contact_Name, Emergency_Phone_Number

BTEC_Registrations:   Registration_Number, Registration_Date, Registration_Status,
                      Program_Price, Total_Fees, Paid_Amount, Remaining_Amount,
                      Currency, Payment_Plan, Study_Mode, Expected_Graduation,
                      Number_of_Installments, Program_Level

BTEC_Classes:         Class_Name, Class_Short_Name, BTEC_Program (lookup),
                      Unit (lookup), Teacher (lookup), Moodle_Class_ID,
                      Class_Status, Start_Date, End_Date

BTEC_Enrollments:     Enrolled_Students (lookup), Classes (lookup),
                      Enrollment_Status, Enrollment_Date

BTEC_Payments:        Registration_ID (lookup), Student_ID (lookup),
                      Payment_Amount, Payment_Date, Payment_Method, Note

BTEC_Grades:          Student (lookup), Class (lookup), BTEC_Unit (lookup),
                      Grade, Moodle_Grade_Composite_Key, Moodle_Course_ID,
                      Moodle_User_ID

BTEC_Student_Requests: Subject, Student_ID (lookup), Request_Type, Status,
                        Description, Attachment, Class_Drop_Classes, Request_Date

BTEC_Teachers:        Name, Email, Academic_Email, Phone_Number,
                      Teacher_Moodle_ID
```

### Moodle WS Functions الحالية

| الدالة | الحالة | الغرض |
|--------|--------|--------|
| `local_mzi_update_student` | ✅ موجودة | إنشاء/تحديث `local_mzi_students` |
| `local_mzi_create_registration` | ✅ موجودة | إنشاء/تحديث `local_mzi_registrations` |
| `local_mzi_record_payment` | ✅ موجودة | تسجيل دفعة في `local_mzi_payments` |
| `local_mzi_create_class` | ✅ موجودة | إنشاء class + Moodle course |
| `local_mzi_update_enrollment` | ✅ موجودة | تسجيل/فصل طالب من course |
| `local_mzi_submit_grade` | ✅ موجودة | حفظ grade في `local_mzi_grades` |
| `local_mzi_update_request_status` | ✅ موجودة | mirror لحالة الطلب |
| `local_mzi_sync_teacher` | ✅ موجودة | إنشاء/تحديث `local_mzi_teachers` |
| `local_moodle_zoho_sync_create_btec_definition` | ✅ موجودة | إنشاء BTEC template |
| `local_mzi_get_moodle_ids` | ✅ موجودة | جلب moodle IDs من zoho IDs |
| `local_mzi_upsert_program` | ❌ مطلوبة | إنشاء/تحديث `local_mzi_programs` |
| `local_mzi_submit_student_request` | ❌ مطلوبة | إرسال طلب الطالب لـ Zoho |

---

*هذه الوثيقة هي المرجع الوحيد لبناء وتطوير التكامل. أي تغيير على api_names يستوجب مراجعة هذه الوثيقة أولاً.*
