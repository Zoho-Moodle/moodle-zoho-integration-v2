# خطة التهجير: من Backend-as-Read إلى Moodle DB Projection
> التاريخ: 2026-02-20  
> المؤلف: تحليل آلي شامل  
> الحالة: **Draft — جاهز للتنفيذ**

---

## جدول المحتويات

A. [خريطة القراءة الحالية (Current Read Map)](#a-current-read-map)  
B. [مخطط جداول Moodle DB المقترحة](#b-moodle-db-projection-schema)  
C. [خريطة كتابة الـ Webhooks والـ Sync](#c-webhooksync-write-map)  
D. [خطة التهجير التدريجي](#d-migration-steps)

---

## A. Current Read Map

### A.1 البنية الحالية — اتجاهان موجودان معاً

```
┌──────────────────────────────────────────────── DASHBOARD PATH A (القديم) ─┐
│  المتصفح                                                                     │
│    └─► dashboard.js::loadData(type)                                         │
│              └─► GET /ui/ajax/get_student_data.php?type=profile&userid=X    │
│                       └─► fetch_backend_data('/api/v1/extension/students/profile') │
│                                └─► Backend API  →  PostgreSQL               │
└─────────────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────── DASHBOARD PATH B (الجديد) ─┐
│  PHP Server-Side                                                             │
│    profile.php   → SELECT * FROM {local_mzi_students}                       │
│    programs.php  → SELECT FROM {local_mzi_registrations}                    │
│                    LEFT JOIN {local_mzi_payments}                           │
│    classes.php   → SELECT FROM {local_mzi_enrollments}                      │
│                    INNER JOIN {local_mzi_classes}                           │
│    requests.php  → SELECT * FROM {local_mzi_requests}                       │
│    student_card  → SELECT FROM {local_mzi_students}                         │
│                    + subquery on {local_mzi_registrations}                  │
└─────────────────────────────────────────────────────────────────────────────┘
```

### A.2 تقرير تبويب بتبويب

| التبويب | الصفحة | مصدر البيانات الحالي | الدوال/الاستعلامات المسؤولة | مفاتيح البيانات المتوقعة في JS |
|---|---|---|---|---|
| **Profile** | `ui/student/profile.php` | **Moodle DB** `local_mzi_students` | `$DB->get_record_sql("SELECT * FROM {local_mzi_students} WHERE moodle_user_id = ?")` | `student_id`, `first_name`, `last_name`, `email`, `phone_number`, `nationality`, `date_of_birth`, `status`, `photo_url`, `address`, `updated_at` |
| **Profile (Auth path)** | `ui/ajax/get_student_data.php` → `dashboard.js::renderProfile()` | **Backend API** `/api/v1/extension/students/profile` | `fetch_backend_data('/api/v1/extension/students/profile', ['moodle_user_id' => $userid])` | `data.student.student_id`, `data.student.full_name`, `data.student.email`, `data.student.phone`, `data.student.student_status` |
| **My Programs** | `ui/student/programs.php` | **Moodle DB** `local_mzi_registrations` + `local_mzi_payments` | `$DB->get_records_sql("SELECT r.*, COALESCE(SUM(p.payment_amount),...) FROM {local_mzi_registrations} r LEFT JOIN {local_mzi_payments} p ON p.registration_id = r.id WHERE r.student_id = ?")` | `program_name`, `zoho_registration_id`, `registration_status`, `registration_date`, `total_fees`, `paid_amount`, `balance`, `payment_plan`, `number_of_installments` |
| **Finance (AJAX path)** | `get_student_data.php?type=finance` → `renderFinance()` | **Backend API** `/api/v1/extension/students/finance` | `fetch_backend_data('/api/v1/extension/students/finance')` | `data.summary.total_fees`, `data.summary.amount_paid`, `data.summary.balance_due`, `data.payments[].payment_date`, `data.payments[].amount`, `data.payments[].payment_method`, `data.payments[].payment_status` |
| **Classes & Grades** | `ui/student/classes.php` | **Moodle DB** `local_mzi_enrollments` JOIN `local_mzi_classes` + subquery `local_mzi_grades` | `$DB->get_records_sql("SELECT e.*, c.class_name, c.program_level, c.teacher_name, c.start_date, c.end_date, c.class_status, (SELECT COUNT(*) FROM {local_mzi_grades} WHERE student_id=... AND class_id=...) FROM {local_mzi_enrollments} e INNER JOIN {local_mzi_classes} c ON c.id = e.class_id WHERE e.student_id = ?")` | `class_name`, `program_level`, `teacher_name`, `start_date`, `end_date`, `class_status`, `enrollment_status`, `enrollment_date`, `zoho_enrollment_id`, `grade_count` |
| **Classes (AJAX path)** | `get_student_data.php?type=classes` → `renderClasses()` | **Backend API** `/api/v1/extension/students/classes` | `fetch_backend_data('/api/v1/extension/students/classes')` | `data.classes[].class_name`, `data.classes[].instructor`, `data.classes[].schedule`, `data.classes[].room` |
| **Grades (AJAX path)** | `get_student_data.php?type=grades` → `renderGrades()` | **Backend API** `/api/v1/extension/students/grades` | `fetch_backend_data('/api/v1/extension/students/grades')` | `data.grades[].unit_name`, `data.grades[].grade`, `data.grades[].grade_status`, `data.grades[].submission_date` |
| **Requests** | `ui/student/requests.php` | **Moodle DB** `local_mzi_requests` | `$DB->get_records_sql("SELECT * FROM {local_mzi_requests} WHERE student_id = ? ORDER BY created_at DESC")` | `zoho_request_id`, `request_type`, `description`, `status`, `created_at`, `updated_at` |
| **Student Card** | `ui/student/student_card.php` | **Moodle DB** `local_mzi_students` + subquery `local_mzi_registrations` | `$DB->get_record_sql("SELECT s.*, (SELECT program_name FROM {local_mzi_registrations} WHERE student_id = s.id AND registration_status='Active' ORDER BY registration_date DESC LIMIT 1) ... FROM {local_mzi_students} s WHERE s.moodle_user_id = ?")` | `first_name`, `last_name`, `student_id`, `email`, `nationality`, `photo_url`, `current_program` |

### A.3 الوضع الحالي المشكلة

```
⚠️  التناقض القائم:
  - الصفحات الجديدة (profile.php, programs.php, classes.php, requests.php)
    تقرأ من local_mzi_* مباشرة ✅
    
  - لكن مسار الكتابة إلى local_mzi_* مكسور:
    → events.py (handler الأساسي لiwebhooks زوهو) يكتب فقط لـ PostgreSQL ❌
    → student_dashboard_webhooks.py يستدعي Moodle WS (local_mzi_update_student...)
      لكن هل WS functions مطبّقة في moodle plugin؟ يحتاج تحقق
      
  - مسار AJAX القديم (get_student_data.php → backend API) لا يزال موجوداً
    (backend endpoints /api/v1/extension/students/* قد لا تكون مطبّقة)
```

---

## B. Moodle DB Projection Schema

> **ملاحظة هامة:** جداول Moodle DB الـ Projection موجودة بالفعل في `moodle_plugin/db/install.xml`.  
> هذا القسم يوثّق هذه الجداول مع إضافة ما هو ناقص ويحتاج تعديل.

### B.1 `local_mzi_students` — بيانات الطالب (موجودة بالفعل ✅)

**الملف:** `moodle_plugin/db/install.xml` السطر 200+

```sql
-- موجود بالفعل، لا يحتاج تعديل
CREATE TABLE {local_mzi_students} (
    id                  INT(10)  PRIMARY KEY AUTO_INCREMENT,
    moodle_user_id      INT(10)  NOT NULL,   -- FK → mdl_user.id
    zoho_student_id     CHAR(20) NOT NULL,   -- Zoho BTEC_Students.id
    
    -- الحقول التي تحتاجها profile.php
    student_id          CHAR(120),           -- Zoho Name (رقم الطالب)
    registration_number CHAR(255),
    first_name          CHAR(255),
    last_name           CHAR(255),
    email               CHAR(100),           -- البريد الشخصي
    academic_email      CHAR(100),           -- البريد الأكاديمي = Moodle username
    phone_number        CHAR(30),
    date_of_birth       CHAR(20),
    nationality         CHAR(120),
    address             TEXT,
    city                CHAR(255),
    status              CHAR(120),           -- Active, Pending, Approved
    photo_url           CHAR(512),
    
    -- للصفحات الأكاديمية
    academic_program    CHAR(255),
    registration_date   CHAR(20),
    study_language      CHAR(50),
    
    -- Sync metadata
    created_at          INT(10) NOT NULL DEFAULT 0,
    updated_at          INT(10) NOT NULL DEFAULT 0,
    synced_at           INT(10) NOT NULL DEFAULT 0,
    zoho_created_time   CHAR(30),
    zoho_modified_time  CHAR(30)
);
CREATE UNIQUE INDEX ON {local_mzi_students} (zoho_student_id);
CREATE UNIQUE INDEX ON {local_mzi_students} (moodle_user_id);
CREATE        INDEX ON {local_mzi_students} (academic_email);
CREATE        INDEX ON {local_mzi_students} (synced_at);
```

**يقرأ منه:** `profile.php`, `student_card.php`  
**يكتب إليه:** `local_mzi_update_student` (Moodle WS)

---

### B.2 `local_mzi_registrations` — التسجيل في البرامج (موجودة بالفعل ✅)

**الملف:** `moodle_plugin/db/install.xml` السطر 270+

```sql
CREATE TABLE {local_mzi_registrations} (
    id                      INT(10)  PRIMARY KEY AUTO_INCREMENT,
    student_id              INT(10)  NOT NULL,   -- FK → local_mzi_students.id
    zoho_registration_id    CHAR(20) NOT NULL,   -- Zoho BTEC_Registrations.id
    
    -- يقرأها programs.php
    registration_number     CHAR(120),
    program_name            CHAR(255),           -- lookup name
    program_level           CHAR(50),
    registration_date       CHAR(20),
    expected_graduation     CHAR(20),
    registration_status     CHAR(50),            -- Active, Completed, Cancelled
    
    -- المالية (يعرضها programs.php)
    total_fees              DECIMAL(10,2),
    paid_amount             DECIMAL(10,2),        -- يُحسب أيضاً من SUM(payments)
    remaining_amount        DECIMAL(10,2),
    currency                CHAR(10),
    payment_plan            CHAR(50),
    number_of_installments  INT(3),
    
    -- Sync metadata
    created_at          INT(10) NOT NULL DEFAULT 0,
    updated_at          INT(10) NOT NULL DEFAULT 0,
    synced_at           INT(10) NOT NULL DEFAULT 0,
    zoho_created_time   CHAR(30),
    zoho_modified_time  CHAR(30)
);
CREATE UNIQUE INDEX ON {local_mzi_registrations} (zoho_registration_id);
CREATE        INDEX ON {local_mzi_registrations} (student_id);
CREATE        INDEX ON {local_mzi_registrations} (registration_status);
CREATE        INDEX ON {local_mzi_registrations} (synced_at);
```

**يقرأ منه:** `programs.php`, `student_card.php` (subquery)  
**يكتب إليه:** `local_mzi_create_registration` (Moodle WS) — ويجب أن تكتب إليه أيضاً:  
`backend/app/api/v1/endpoints/events.py::handle_zoho_event()` عبر dual-write

---

### B.3 `local_mzi_payments` — المدفوعات (موجودة بالفعل ✅)

**الملف:** `moodle_plugin/db/install.xml` السطر 320+

```sql
CREATE TABLE {local_mzi_payments} (
    id                  INT(10)  PRIMARY KEY AUTO_INCREMENT,
    registration_id     INT(10)  NOT NULL,    -- FK → local_mzi_registrations.id
    zoho_payment_id     CHAR(20) NOT NULL,    -- Zoho BTEC_Payments.id
    
    -- يقرأها programs.php (SUM) و dashboard.js::renderFinance()
    payment_number      CHAR(120),
    payment_date        CHAR(20),
    payment_amount      DECIMAL(10,2),        -- ⚠️ اسم الحقل يختلف عن Zoho (Amount)
    payment_method      CHAR(50),
    voucher_number      CHAR(100),
    bank_name           CHAR(100),
    receipt_number      CHAR(100),
    payment_notes       TEXT,
    payment_status      CHAR(20),             -- Confirmed, Pending, Voided
    
    -- Sync metadata
    created_at          INT(10) NOT NULL DEFAULT 0,
    updated_at          INT(10) NOT NULL DEFAULT 0,
    synced_at           INT(10) NOT NULL DEFAULT 0,
    zoho_created_time   CHAR(30),
    zoho_modified_time  CHAR(30)
);
CREATE UNIQUE INDEX ON {local_mzi_payments} (zoho_payment_id);
CREATE        INDEX ON {local_mzi_payments} (registration_id);
CREATE        INDEX ON {local_mzi_payments} (payment_date);
CREATE        INDEX ON {local_mzi_payments} (synced_at);
```

**يقرأ منه:** `programs.php` (LEFT JOIN + SUM), dashboard.js `renderFinance()`  
**يكتب إليه:** `local_mzi_record_payment` (Moodle WS) + dual-write من Backend

---

### B.4 `local_mzi_installments` — جدول الأقساط (موجودة، لتخزين subform)

**الملف:** `moodle_plugin/db/install.xml` السطر 310+

```sql
-- تخزين subform الأقساط كجدول منفصل (أفضل من JSON للاستعلام)
CREATE TABLE {local_mzi_installments} (
    id                  INT(10) PRIMARY KEY AUTO_INCREMENT,
    registration_id     INT(10) NOT NULL,   -- FK → local_mzi_registrations.id
    zoho_installment_id CHAR(20),           -- إن كان subform له ID في Zoho
    
    installment_number  INT(3)  NOT NULL,
    due_date            CHAR(20),
    amount              DECIMAL(10,2),
    status              CHAR(20),           -- Paid, Pending, Overdue
    paid_date           CHAR(20),
    
    created_at          INT(10) NOT NULL DEFAULT 0,
    updated_at          INT(10) NOT NULL DEFAULT 0
);
CREATE INDEX ON {local_mzi_installments} (registration_id);
CREATE INDEX ON {local_mzi_installments} (status);
CREATE INDEX ON {local_mzi_installments} (due_date);
```

> **قرار التصميم:** subform الأقساط في جدول منفصل وليس JSON — لأن programs.php يحتاج `SUM(amount)` وعرض الجدول. JSON يجعل هذا أصعب.

---

### B.5 `local_mzi_classes` — الفصول الدراسية (موجودة بالفعل ✅)

**الملف:** `moodle_plugin/db/install.xml` السطر 360+

```sql
CREATE TABLE {local_mzi_classes} (
    id              INT(10)  PRIMARY KEY AUTO_INCREMENT,
    zoho_class_id   CHAR(20) NOT NULL,   -- Zoho BTEC_Classes.id
    
    -- يقرأها classes.php
    class_number    CHAR(120),
    class_name      CHAR(255),
    unit_name       CHAR(255),
    program_level   CHAR(50),
    teacher_name    CHAR(255),           -- Lookup: Teacher.name
    class_type      CHAR(50),
    start_date      CHAR(20),
    end_date        CHAR(20),
    schedule        TEXT,
    class_status    CHAR(20),            -- Active, Completed, Cancelled
    
    -- Sync metadata
    created_at          INT(10) NOT NULL DEFAULT 0,
    updated_at          INT(10) NOT NULL DEFAULT 0,
    synced_at           INT(10) NOT NULL DEFAULT 0,
    zoho_created_time   CHAR(30),
    zoho_modified_time  CHAR(30)
);
CREATE UNIQUE INDEX ON {local_mzi_classes} (zoho_class_id);
CREATE        INDEX ON {local_mzi_classes} (class_status);
CREATE        INDEX ON {local_mzi_classes} (program_level);
CREATE        INDEX ON {local_mzi_classes} (synced_at);
```

> **انتبه:** `dashboard.js::renderClasses()` يتوقع مفتاح `instructor` لكن قاعدة البيانات تخزنه كـ `teacher_name`. الحل: إما تعديل JS، أو إرجاع alias.

---

### B.6 `local_mzi_enrollments` — تسجيلات الفصول (موجودة بالفعل ✅)

**الملف:** `moodle_plugin/db/install.xml` السطر 400+

```sql
CREATE TABLE {local_mzi_enrollments} (
    id                  INT(10) PRIMARY KEY AUTO_INCREMENT,
    student_id          INT(10) NOT NULL,   -- FK → local_mzi_students.id
    class_id            INT(10) NOT NULL,   -- FK → local_mzi_classes.id
    zoho_enrollment_id  CHAR(20),           -- Zoho BTEC_Enrollments.id
    
    -- يقرأها classes.php
    enrollment_date     CHAR(20),
    enrollment_status   CHAR(20),           -- Active, Dropped, Completed
    attendance_percentage DECIMAL(5,2),
    completion_date     CHAR(20),
    
    -- Sync metadata
    created_at          INT(10) NOT NULL DEFAULT 0,
    updated_at          INT(10) NOT NULL DEFAULT 0,
    synced_at           INT(10) NOT NULL DEFAULT 0
);
CREATE INDEX ON {local_mzi_enrollments} (student_id);
CREATE INDEX ON {local_mzi_enrollments} (class_id);
CREATE INDEX ON {local_mzi_enrollments} (enrollment_status);
-- Composite index للاستعلام الأكثر شيوعاً
CREATE UNIQUE INDEX ON {local_mzi_enrollments} (student_id, class_id);
```

---

### B.7 `local_mzi_grades` — الدرجات (موجودة بالفعل ✅)

**الملف:** `moodle_plugin/db/install.xml` السطر 430+

```sql
CREATE TABLE {local_mzi_grades} (
    id              INT(10)  PRIMARY KEY AUTO_INCREMENT,
    student_id      INT(10)  NOT NULL,   -- FK → local_mzi_students.id
    class_id        INT(10)  NOT NULL,   -- FK → local_mzi_classes.id
    zoho_grade_id   CHAR(20) NOT NULL,   -- Zoho BTEC_Grades.id
    
    -- يقرأها classes.php (grade_count فقط حالياً) و dashboard.js::renderGrades()
    grade_number    CHAR(120),
    assignment_name CHAR(255),
    btec_grade_name CHAR(50),            -- P, M, D, R, F, RR
    numeric_grade   DECIMAL(5,2),
    submission_date CHAR(20),
    grade_date      CHAR(20),
    feedback        TEXT,
    
    -- تخزين Learning Outcomes كـ JSON (subform)
    -- القرار: JSON لأن LOs تُعرض كقائمة فقط ولا تُجمَع رياضياً
    learning_outcomes TEXT,              -- JSON: [{LO_Code, LO_Definition, LO_Score, LO_Feedback}]
    
    -- تتبع الإشعارات
    feedback_acknowledged     INT(1) NOT NULL DEFAULT 0,
    feedback_acknowledged_at  INT(10),
    
    -- إعادة التسليم
    attempt_number  INT(2),              -- 1, 2
    is_resubmission INT(1) NOT NULL DEFAULT 0,
    
    -- Sync metadata
    created_at          INT(10) NOT NULL DEFAULT 0,
    updated_at          INT(10) NOT NULL DEFAULT 0,
    synced_at           INT(10) NOT NULL DEFAULT 0,
    zoho_created_time   CHAR(30),
    zoho_modified_time  CHAR(30)
);
CREATE UNIQUE INDEX ON {local_mzi_grades} (zoho_grade_id);
CREATE        INDEX ON {local_mzi_grades} (student_id);
CREATE        INDEX ON {local_mzi_grades} (class_id);
CREATE        INDEX ON {local_mzi_grades} (btec_grade_name);
CREATE        INDEX ON {local_mzi_grades} (feedback_acknowledged);
CREATE        INDEX ON {local_mzi_grades} (synced_at);
-- Composite: البحث بالطالب والفصل
CREATE        INDEX ON {local_mzi_grades} (student_id, class_id);
```

> **قرار التصميم بخصوص اللـ subforms:**  
> - **الأقساط (Installments)**: جدول منفصل ← لأن programs.php يستعلم `SUM(amount)`  
> - **Learning Outcomes**: حقل `TEXT` يحتوي JSON ← لأنها تُعرض كقائمة فقط دون عمليات رياضية  

---

### B.8 `local_mzi_requests` — طلبات الطلاب (موجودة بالفعل ✅)

**الملف:** `moodle_plugin/db/install.xml` السطر 470+

```sql
CREATE TABLE {local_mzi_requests} (
    id              INT(10)  PRIMARY KEY AUTO_INCREMENT,
    student_id      INT(10)  NOT NULL,   -- FK → local_mzi_students.id
    zoho_request_id CHAR(20),            -- Zoho BTEC_Student_Requests.id (null if Moodle-originated)
    
    -- يقرأها requests.php
    request_number  CHAR(120),
    request_type    CHAR(50),            -- Class Drop, Grade Review, Program Change
    request_status  CHAR(20),            -- Submitted, Under Review, Approved, Rejected
    priority        CHAR(20),
    reason          TEXT,
    description     TEXT,
    
    -- حقول JSON للمرونة (بدل subform)
    requested_classes   TEXT,            -- JSON array
    grade_details       TEXT,            -- JSON
    change_information  TEXT,            -- JSON
    
    -- ردود الإدارة
    admin_notes     TEXT,
    admin_response  TEXT,
    reviewed_by     INT(10),            -- FK → mdl_user.id
    reviewed_at     INT(10),
    
    -- Sync metadata
    created_at          INT(10) NOT NULL DEFAULT 0,
    updated_at          INT(10) NOT NULL DEFAULT 0,
    synced_at           INT(10) NOT NULL DEFAULT 0,
    zoho_created_time   CHAR(30),
    zoho_modified_time  CHAR(30)
);
CREATE        INDEX ON {local_mzi_requests} (zoho_request_id);
CREATE        INDEX ON {local_mzi_requests} (student_id);
CREATE        INDEX ON {local_mzi_requests} (request_type);
CREATE        INDEX ON {local_mzi_requests} (request_status);
CREATE        INDEX ON {local_mzi_requests} (synced_at);
```

### B.9 ملخص الجداول وحالة التطبيق

| الجدول | الصفحة التي تقرأ منه | الحالة | ملاحظة |
|---|---|---|---|
| `local_mzi_students` | `profile.php`, `student_card.php` | ✅ موجود في install.xml | يحتاج كاتب (Writer) موثوق |
| `local_mzi_registrations` | `programs.php`, `student_card.php` | ✅ موجود في install.xml | يحتاج كاتب موثوق |
| `local_mzi_payments` | `programs.php` (SUM), `renderFinance()` | ✅ موجود في install.xml | يحتاج كاتب موثوق |
| `local_mzi_installments` | غير مستخدم حالياً | ✅ موجود في install.xml | يمكن استخدامه لاحقاً |
| `local_mzi_classes` | `classes.php` | ✅ موجود في install.xml | يحتاج كاتب موثوق |
| `local_mzi_enrollments` | `classes.php` | ✅ موجود في install.xml | يحتاج كاتب موثوق |
| `local_mzi_grades` | `classes.php` (count), `renderGrades()` | ✅ موجود في install.xml | يحتاج كاتب موثوق |
| `local_mzi_requests` | `requests.php` | ✅ موجود في install.xml | يحتاج كاتب موثوق |

> **الخلاصة:** الجداول موجودة والصفحات تقرأ منها. المشكلة الوحيدة هي ضمان الكتابة المنتظمة إليها.

---

## C. Webhook/Sync Write Map

### C.1 المسارات الحالية للكتابة — تشخيص المشكلة

```
ZOHO CRM EVENT (مثلاً: تحديث طالب)
    │
    ├── [مسار 1] POST /api/v1/events/zoho/student
    │       ملف: backend/app/api/v1/endpoints/events.py
    │       دالة: handle_zoho_student_event()
    │       □ يكتب لـ: PostgreSQL (students table)
    │       ✗ لا يكتب لـ: local_mzi_students  ← المشكلة
    │
    └── [مسار 2] POST /api/v1/webhooks/student-dashboard/student_updated
            ملف: backend/app/api/v1/endpoints/student_dashboard_webhooks.py
            دالة: handle_student_updated()
            □ يستدعي: Moodle WS local_mzi_update_student
            ✓ يكتب لـ: local_mzi_students  (عبر WS)
            ⚠️  يحتاج: أن تكون Moodle WS functions مطبّقة
```

### C.2 الجدول الكامل: وحدة Zoho → Handler → الجدول المستهدف

#### BTEC_Students (بيانات الطالب)

| زوهو الوحدة | حقول زوهو (API Names — محفوظة) | الـ Handler الحالي | يكتب لـ PostgreSQL | يجب أن يكتب لـ Moodle DB | عمود الربط |
|---|---|---|---|---|---|
| `BTEC_Students` | `id` | `handle_zoho_student_event()` `events.py:58` | `students.zoho_id` | `local_mzi_students.zoho_student_id` | `zoho_student_id` |
| `BTEC_Students` | `Name` | ← | `students.display_name` | `local_mzi_students.student_id` | — |
| `BTEC_Students` | `First_Name` | ← | — | `local_mzi_students.first_name` | — |
| `BTEC_Students` | `Last_Name` | ← | — | `local_mzi_students.last_name` | — |
| `BTEC_Students` | `Academic_Email` | ← | `students.academic_email` | `local_mzi_students.academic_email` | يُستخدم لربط Moodle user |
| `BTEC_Students` | `Phone_Number` | ← | `students.phone` | `local_mzi_students.phone_number` | — |
| `BTEC_Students` | `Status` | ← | `students.status` | `local_mzi_students.status` | — |
| `BTEC_Students` | `Date_of_Birth` | ← | — | `local_mzi_students.date_of_birth` | — |
| `BTEC_Students` | `Nationality` | ← | — | `local_mzi_students.nationality` | — |
| `BTEC_Students` | `Address` | ← | — | `local_mzi_students.address` | — |
| `BTEC_Students` | `City` | ← | — | `local_mzi_students.city` | — |

**Handler الحالي في student_dashboard_webhooks.py (مسار 2):**  
`handle_student_updated()` ← يأخذ payload يحتوي `zoho_student_id` ويرسله لـ `local_mzi_update_student`

---

#### BTEC_Registrations (تسجيلات البرامج)

| زوهو الوحدة | حقول زوهو (API Names — محفوظة) | الـ Handler الحالي | يكتب لـ PostgreSQL | يجب أن يكتب لـ Moodle DB | التحويل |
|---|---|---|---|---|---|
| `BTEC_Registrations` | `id` | `sync_registrations.py` / `events.py` (غير مُطبّق في events) | `registrations.zoho_id` | `local_mzi_registrations.zoho_registration_id` | مباشر |
| `BTEC_Registrations` | `Student.id` | ← | `registrations.student_zoho_id` | يُستخدم للبحث عن `local_mzi_students.id` | Lookup |
| `BTEC_Registrations` | `Program` | ← | — | `local_mzi_registrations.program_name` | `.name` من Lookup |
| `BTEC_Registrations` | `Registration_Status` | ← | `registrations.enrollment_status` | `local_mzi_registrations.registration_status` | مباشر |
| `BTEC_Registrations` | `Registration_Date` | ← | `registrations.registration_date` | `local_mzi_registrations.registration_date` | مباشر |
| `BTEC_Registrations` | `Total_Fees` | ← | — | `local_mzi_registrations.total_fees` | مباشر |
| `BTEC_Registrations` | `Paid_Amount` | ← | — | `local_mzi_registrations.paid_amount` | مباشر |
| `BTEC_Registrations` | `Remaining_Amount` | ← | — | `local_mzi_registrations.remaining_amount` | مباشر (formula) |
| `BTEC_Registrations` | `Payment_Plan` | ← | — | `local_mzi_registrations.payment_plan` | مباشر |
| `BTEC_Registrations` | `Number_of_Installments` | ← | — | `local_mzi_registrations.number_of_installments` | مباشر |

**Handler في student_dashboard_webhooks.py (مسار 2):**  
`handle_registration_created()` ← يستدعي `transform_zoho_to_moodle(payload, "registrations")` ثم `local_mzi_create_registration`

**Field Mapping النشط في transform_zoho_to_moodle() (محفوظة بالكامل):**  
```python
"registrations": {
    "id": "zoho_registration_id",
    "Student": "zoho_student_id",          # Extract .id من lookup
    "Program": "program_name",             # Extract .name من lookup
    "Registration_Number": "registration_number",
    "Registration_Date": "registration_date",
    "Registration_Status": "registration_status",
    "Total_Fees": "total_fees",
    "Paid_Amount": "paid_amount",
    "Remaining_Amount": "remaining_amount",
    "Created_Time": "zoho_created_time",
    "Modified_Time": "zoho_modified_time"
}
```

---

#### BTEC_Payments (المدفوعات)

| زوهو الوحدة | حقول زوهو (API Names — محفوظة) | الـ Handler الحالي | يكتب لـ PostgreSQL | يجب أن يكتب لـ Moodle DB |
|---|---|---|---|---|
| `BTEC_Payments` | `id` | `events.py::handle_zoho_payment_event()` (يكتب لـ PostgreSQL) | `payments.zoho_id` | `local_mzi_payments.zoho_payment_id` |
| `BTEC_Payments` | `Registration.id` | ← | `payments.registration_zoho_id` | يُستخدم لإيجاد `local_mzi_registrations.id` |
| `BTEC_Payments` | `Amount` | ← | `payments.amount` | `local_mzi_payments.payment_amount` |
| `BTEC_Payments` | `Payment_Date` | ← | `payments.payment_date` | `local_mzi_payments.payment_date` |
| `BTEC_Payments` | `Payment_Method` | ← | `payments.payment_method` | `local_mzi_payments.payment_method` |
| `BTEC_Payments` | `Payment_Status` | ← | `payments.payment_status` | `local_mzi_payments.payment_status` |
| `BTEC_Payments` | `Voucher_Number` | ← | — | `local_mzi_payments.voucher_number` |
| `BTEC_Payments` | `Receipt_Number` | ← | — | `local_mzi_payments.receipt_number` |

**Handler في student_dashboard_webhooks.py (مسار 2):**  
`handle_payment_recorded()` ← يرسل payload لـ `local_mzi_record_payment`

---

#### BTEC_Classes (الفصول الدراسية)

| زوهو الوحدة | حقول زوهو (API Names — محفوظة) | الـ Handler الحالي | يكتب لـ PostgreSQL | يجب أن يكتب لـ Moodle DB |
|---|---|---|---|---|
| `BTEC_Classes` | `id` | `sync_classes.py` / `create_course.py` | `classes.zoho_id` | `local_mzi_classes.zoho_class_id` |
| `BTEC_Classes` | `Class_Name` | ← | `classes.name` | `local_mzi_classes.class_name` |
| `BTEC_Classes` | `Program_Level` | ← | — | `local_mzi_classes.program_level` |
| `BTEC_Classes` | `Teacher.name` | ← | `classes.teacher_zoho_id` | `local_mzi_classes.teacher_name` |
| `BTEC_Classes` | `Start_Date` | ← | `classes.start_date` | `local_mzi_classes.start_date` |
| `BTEC_Classes` | `End_Date` | ← | `classes.end_date` | `local_mzi_classes.end_date` |
| `BTEC_Classes` | `Status` | ← | — | `local_mzi_classes.class_status` |
| `BTEC_Classes` | `Class_Type` | ← | — | `local_mzi_classes.class_type` |

**Field Mapping النشط في transform_zoho_to_moodle() (محفوظة بالكامل):**  
```python
"classes": {
    "id": "zoho_class_id",
    "Class_Name": "class_name",
    "Unit": "unit_name",
    "Program_Level": "program_level",
    "Teacher": "teacher_name",       # Extract .name من lookup
    "Start_Date": "start_date",
    "End_Date": "end_date",
    "Status": "class_status",
    "Class_Type": "class_type",
    "Created_Time": "zoho_created_time",
    "Modified_Time": "zoho_modified_time"
}
```

**Handler في student_dashboard_webhooks.py:**  
`handle_class_created()` ← `transform_zoho_to_moodle(payload, "classes")` → `local_mzi_create_class`

---

#### BTEC_Enrollments (تسجيلات الفصول)

| زوهو الوحدة | حقول زوهو (API Names — محفوظة) | الـ Handler الحالي | يكتب لـ PostgreSQL | يجب أن يكتب لـ Moodle DB |
|---|---|---|---|---|
| `BTEC_Enrollments` | `id` | `events.py::handle_zoho_enrollment_event()` | `enrollments.zoho_id` | `local_mzi_enrollments.zoho_enrollment_id` |
| `BTEC_Enrollments` | `Student.id` | ← | `enrollments.student_zoho_id` | يُستخدم لإيجاد `local_mzi_students.id` |
| `BTEC_Enrollments` | `Class.id` / `BTEC_Class.id` | ← | `enrollments.class_zoho_id` | يُستخدم لإيجاد `local_mzi_classes.id` |
| `BTEC_Enrollments` | `Enrollment_Date` | ← | `enrollments.start_date` | `local_mzi_enrollments.enrollment_date` |
| `BTEC_Enrollments` | `Status` | ← | — | `local_mzi_enrollments.enrollment_status` |

**Field Mapping النشط في transform_zoho_to_moodle() (محفوظة بالكامل):**  
```python
"enrollments": {
    "id": "zoho_enrollment_id",
    "Student": "zoho_student_id",   # Extract .id من lookup
    "Class": "zoho_class_id",       # Extract .id من lookup
    "Enrollment_Date": "enrollment_date",
    "Status": "enrollment_status",
    "Created_Time": "zoho_created_time",
    "Modified_Time": "zoho_modified_time"
}
```

**Handler في student_dashboard_webhooks.py:**  
`handle_enrollment_updated()` ← `transform_zoho_to_moodle(payload, "enrollments")` → `local_mzi_update_enrollment`

---

#### BTEC_Grades (الدرجات)

| زوهو الوحدة | حقول زوهو (API Names — محفوظة) | الـ Handler الحالي | يكتب لـ PostgreSQL | يجب أن يكتب لـ Moodle DB |
|---|---|---|---|---|
| `BTEC_Grades` | `id` | `events.py::handle_zoho_grade_event()` | `grades.zoho_id` | `local_mzi_grades.zoho_grade_id` |
| `BTEC_Grades` | `Student.id` | ← | `grades.student_zoho_id` | يُستخدم لإيجاد `local_mzi_students.id` |
| `BTEC_Grades` | `Grade_Value` | ← | `grades.grade_value` | `local_mzi_grades.btec_grade_name` |
| `BTEC_Grades` | `Score` | ← | `grades.score` | `local_mzi_grades.numeric_grade` |
| `BTEC_Grades` | `Grade_Date` | ← | `grades.grade_date` | `local_mzi_grades.grade_date` |
| `BTEC_Grades` | `Comments` | ← | `grades.comments` | `local_mzi_grades.feedback` |
| `BTEC_Grades` | `Learning_Outcomes_Assessm` | ← (subform) | — | `local_mzi_grades.learning_outcomes` (JSON) |

**Handler في student_dashboard_webhooks.py:**  
`handle_grade_submitted()` ← `local_mzi_submit_grade`

---

#### BTEC_Student_Requests (الطلبات)

| زوهو الوحدة | حقول زوهو (API Names — محفوظة) | الـ Handler الحالي | يكتب لـ PostgreSQL | يجب أن يكتب لـ Moodle DB |
|---|---|---|---|---|
| `BTEC_Student_Requests` | `id` | **غير مكتوب في events.py** ← **فجوة** | — | `local_mzi_requests.zoho_request_id` |
| `BTEC_Student_Requests` | `Student.id` | — | — | يُستخدم لإيجاد `local_mzi_students.id` |
| `BTEC_Student_Requests` | `Request_Type` | — | — | `local_mzi_requests.request_type` |
| `BTEC_Student_Requests` | `Status` | — | — | `local_mzi_requests.request_status` |
| `BTEC_Student_Requests` | `Reason` | — | — | `local_mzi_requests.reason` |
| `BTEC_Student_Requests` | `Admin_Notes` | — | — | `local_mzi_requests.admin_notes` |

**Handler في student_dashboard_webhooks.py:**  
`handle_request_status_changed()` ← `local_mzi_update_request_status`

---

## D. Migration Steps

### نظرة عامة على المسار

```
الحالة الآن:
    Zoho → [events.py] → PostgreSQL ONLY
    Zoho → [student_dashboard_webhooks.py] → Moodle WS → local_mzi_* (جزئياً)

الهدف:
    Zoho → [events.py] → PostgreSQL + local_mzi_* (dual-write)
    Zoho → [student_dashboard_webhooks.py] → Moodle WS → local_mzi_* (unified)
    Moodle UI → local_mzi_* (direct read, no backend API)
    
    مع الحفاظ على جميع API names الحالية دون تغيير.
```

---

### Step 0: فحص وتحقق (لا تعديل كود)

**الهدف:** تحديد ما هو موجود فعلاً وما هو ناقص.

**قائمة التحقق:**

- [ ] **تأكيد وجود الجداول:** هل `local_mzi_students`, `local_mzi_registrations` ... إلخ موجودة في قاعدة بيانات Moodle الفعلية؟  
  ```sql
  SHOW TABLES LIKE 'mdl_local_mzi_%';
  ```

- [ ] **تأكيد Moodle WS functions:** هل `local_mzi_update_student`, `local_mzi_create_registration` ... إلخ مُسجَّلة في `moodle_plugin/db/services.php`؟  
  ```bash
  grep -n "local_mzi_update_student\|local_mzi_create_registration" moodle_plugin/db/services.php
  ```

- [ ] **تأكيد Backend extension endpoints:** هل `/api/v1/extension/students/profile` مطبّقة؟  
  ```bash
  grep -rn "extension/students" backend/app/api/
  ```

- [ ] **تأكيد مسار الكتابة:** هل `EventHandlerService.handle_zoho_event()` يكتب شيئاً لـ Moodle؟  
  ```bash
  grep -n "local_mzi\|moodle_db\|moodle_client" backend/app/services/event_handler_service.py
  ```

**الملفات المعنية:**
- `moodle_plugin/db/services.php`
- `moodle_plugin/db/install.xml`
- `backend/app/services/event_handler_service.py`

**نقطة التحقق (Checkpoint):** إذا فشل أي من هذه الفحوصات، يجب معالجته قبل المتابعة.  
**استراتيجية الرجوع:** لا شيء تغيّر، لا rollback مطلوب.

---

### Step 1: إضافة طبقة الكتابة لـ Moodle DB في Backend

**الهدف:** جعل `events.py` يكتب إلى `local_mzi_*` بجانب PostgreSQL.

**ملاحظة:** لا نغيّر أسماء API أو الحقول — فقط نضيف كاتب ثانٍ.

#### 1A: إنشاء `MoodleProjectionWriter` في Backend

**الملف المراد إنشاؤه:** `backend/app/infra/moodle/projection_writer.py`

```python
"""
MoodleProjectionWriter
يكتب إلى جداول local_mzi_* عبر Moodle Web Services API
يحافظ على جميع أسماء الحقول كما هي في Zoho API
"""
import logging
import json
from typing import Dict, Any, Optional
import httpx
from app.core.config import settings

logger = logging.getLogger(__name__)


class MoodleProjectionWriter:
    """
    Dual-write layer: يكتب بيانات Projection إلى Moodle DB عبر WS
    لا يغيّر أي أسماء حقول — يمرّر الـ payload كما هو
    """
    
    def __init__(self):
        self.base_url = settings.MOODLE_BASE_URL
        self.token = settings.MOODLE_TOKEN
        self.enabled = settings.MOODLE_ENABLED
    
    async def _call_ws(self, wsfunction: str, params: Dict[str, Any]) -> Dict:
        """استدعاء Moodle Web Service"""
        if not self.enabled:
            logger.debug(f"Moodle WS disabled, skipping {wsfunction}")
            return {"status": "skipped"}
        
        url = f"{self.base_url}/webservice/rest/server.php"
        data = {
            "wstoken": self.token,
            "wsfunction": wsfunction,
            "moodlewsrestformat": "json",
            **params
        }
        
        async with httpx.AsyncClient(timeout=15.0) as client:
            try:
                response = await client.post(url, data=data)
                response.raise_for_status()
                result = response.json()
                if isinstance(result, dict) and "exception" in result:
                    logger.error(f"Moodle WS error in {wsfunction}: {result}")
                    return {"status": "error", "detail": result.get("message")}
                return result
            except Exception as e:
                logger.error(f"MoodleProjectionWriter._call_ws({wsfunction}) failed: {e}")
                return {"status": "error", "detail": str(e)}
    
    async def upsert_student(self, zoho_payload: Dict[str, Any]) -> Dict:
        """
        كتابة/تحديث بيانات الطالب في local_mzi_students
        يحافظ على أسماء حقول Zoho: id, Name, Academic_Email, Phone_Number, Status
        """
        return await self._call_ws(
            "local_mzi_update_student",
            {"studentdata": json.dumps(zoho_payload)}
        )
    
    async def upsert_registration(self, zoho_payload: Dict[str, Any]) -> Dict:
        """
        كتابة/تحديث تسجيل في local_mzi_registrations
        يحافظ على: id, Student.id, Program.name, Registration_Status, Total_Fees
        """
        return await self._call_ws(
            "local_mzi_create_registration",
            {"registrationdata": json.dumps(zoho_payload)}
        )
    
    async def upsert_payment(self, zoho_payload: Dict[str, Any]) -> Dict:
        """
        كتابة/تحديث دفعة في local_mzi_payments
        يحافظ على: id, Registration.id, Amount, Payment_Date, Payment_Status
        """
        return await self._call_ws(
            "local_mzi_record_payment",
            {"paymentdata": json.dumps(zoho_payload)}
        )
    
    async def upsert_enrollment(self, zoho_payload: Dict[str, Any]) -> Dict:
        """
        كتابة/تحديث تسجيل فصل في local_mzi_enrollments
        يحافظ على: id, Student.id, Class.id, Status, Enrollment_Date
        """
        return await self._call_ws(
            "local_mzi_update_enrollment",
            {"enrollmentdata": json.dumps(zoho_payload)}
        )
    
    async def upsert_grade(self, zoho_payload: Dict[str, Any]) -> Dict:
        """
        كتابة/تحديث درجة في local_mzi_grades
        يحافظ على: id, Student.id, Unit.id, Grade_Value, Score, Learning_Outcomes_Assessm
        """
        return await self._call_ws(
            "local_mzi_submit_grade",
            {"gradedata": json.dumps(zoho_payload)}
        )
    
    async def upsert_request_status(self, zoho_payload: Dict[str, Any]) -> Dict:
        """
        تحديث حالة طلب في local_mzi_requests
        يحافظ على: id, Student.id, Status, Admin_Notes
        """
        return await self._call_ws(
            "local_mzi_update_request_status",
            {"requestdata": json.dumps(zoho_payload)}
        )
    
    async def upsert_class(self, zoho_payload: Dict[str, Any]) -> Dict:
        """
        كتابة/تحديث فصل في local_mzi_classes
        يحافظ على: id, Class_Name, Teacher.name, Program_Level, Status
        """
        return await self._call_ws(
            "local_mzi_create_class",
            {"classdata": json.dumps(zoho_payload)}
        )
```

**الملف:** `backend/app/services/event_handler_service.py`  
**التعديل المطلوب:** إضافة استدعاء `MoodleProjectionWriter` بعد الكتابة لـ PostgreSQL:

```python
# في handle_zoho_event() — بعد السطر الذي يكتب لـ PostgreSQL
# لا تغيير على الكود الموجود، فقط إضافة:

from app.infra.moodle.projection_writer import MoodleProjectionWriter

# Dual-write: كتابة إلى Moodle DB أيضاً (بشكل متوازٍ وليس blocking)
projection_writer = MoodleProjectionWriter()
try:
    if event.module == "BTEC_Students":
        await projection_writer.upsert_student(event.data)
    elif event.module == "BTEC_Registrations":
        await projection_writer.upsert_registration(event.data)
    elif event.module == "BTEC_Payments":
        await projection_writer.upsert_payment(event.data)
    elif event.module == "BTEC_Enrollments":
        await projection_writer.upsert_enrollment(event.data)
    elif event.module == "BTEC_Grades":
        await projection_writer.upsert_grade(event.data)
    elif event.module == "BTEC_Student_Requests":
        await projection_writer.upsert_request_status(event.data)
except Exception as moodle_write_error:
    # لا نوقف المعالجة إذا فشلت الكتابة لـ Moodle
    # PostgreSQL هو المصدر الأساسي — Moodle DB هو Projection فقط
    logger.error(f"Dual-write to Moodle failed (non-blocking): {moodle_write_error}")
```

**نقطة التحقق:** بعد هذا الـ step:
- كل webhook من Zoho → يكتب لـ PostgreSQL ✓ + يكتب لـ Moodle DB ✓
- إذا فشلت الكتابة لـ Moodle — يسجّل error ويكمل (non-blocking)

**استراتيجية الرجوع:** إزالة `try/except` block الجديد فقط. PostgreSQL لم يتغيّر.

---

### Step 2: تطبيق Moodle WS Functions (إن لم تكن مطبّقة)

**الهدف:** ضمان وجود Moodle Web Service functions التي تكتب إلى `local_mzi_*`.

**الملف المعني:** `moodle_plugin/db/services.php`

التحقق من وجود:
```php
// يجب أن يكون هذا موجوداً في services.php
$functions = [
    'local_mzi_update_student' => [
        'classname'   => 'local_moodle_zoho_sync\external\student_api',
        'methodname'  => 'update_student',
        'description' => 'Create or update student projection from Zoho',
        'type'        => 'write',
        'capabilities'=> '',
    ],
    'local_mzi_create_registration' => [ ... ],
    'local_mzi_record_payment'      => [ ... ],
    'local_mzi_create_class'        => [ ... ],
    'local_mzi_update_enrollment'   => [ ... ],
    'local_mzi_submit_grade'        => [ ... ],
    'local_mzi_update_request_status' => [ ... ],
    'local_mzi_delete_student'      => [ ... ],
];
```

**إن لم تكن موجودة:** إنشاء `moodle_plugin/classes/external/student_api.php` إلخ تحتوي على `execute()` handler يكتب إلى `local_mzi_students` باستخدام `$DB->insert_record_raw()` / `$DB->update_record()`.

**نقطة التحقق:** اختبار كل WS function بـ:
```bash
curl -X POST "https://moodle.domain/webservice/rest/server.php" \
  -d "wstoken=TOKEN&wsfunction=local_mzi_update_student&studentdata={...}&moodlewsrestformat=json"
```

**استراتيجية الرجوع:** تعطيل الدالة في `services.php` → إعادة `$enabled = false`.

---

### Step 3: تحويل قراءة الـ Dashboard إلى Moodle DB

**الهدف:** إيقاف استدعاء `get_student_data.php` → Backend API، والاعتماد على الصفحات الجديدة فقط.

**الحالة الراهنة:**
- الصفحات الجديدة (profile.php, programs.php, classes.php, requests.php) **تقرأ من local_mzi_* مباشرة في PHP** ✅
- dashboard.js لا يزال يستدعي `get_student_data.php` → Backend API ← مسار قديم

**الإجراءات المطلوبة:**

#### 3A: إيقاف AJAX path في get_student_data.php

**الملف:** `moodle_plugin/ui/ajax/get_student_data.php`

```php
// التعديل: لكل نوع، قراءة من local_mzi_* مباشرة بدل Backend API
// مثال لـ type=profile:
case 'profile':
    // بدلاً من: fetch_backend_data('/api/v1/extension/students/profile', ...)
    // نقرأ مباشرة:
    $student = $DB->get_record_sql(
        "SELECT * FROM {local_mzi_students} WHERE moodle_user_id = ?", 
        [$userid]
    );
    if ($student) {
        json_response([
            'success' => true,
            'student' => [
                'student_id'    => $student->student_id,
                'full_name'     => $student->first_name . ' ' . $student->last_name,
                'email'         => $student->email,
                'phone'         => $student->phone_number,
                'student_status'=> $student->status,
            ]
        ]);
    } else {
        json_response(['success' => false, 'message' => 'Student not found']);
    }
    break;
```

> **ملاحظة المفاتيح:** dashboard.js::renderProfile() يتوقع:  
> `data.student.student_id`, `data.student.full_name`, `data.student.email`, `data.student.phone`, `data.student.student_status`  
> هذه المفاتيح يجب أن تبقى كما هي في الـ response.

#### 3B: تحديث renderClasses() في dashboard.js

**الملف:** `moodle_plugin/assets/js/dashboard.js` السطر 248

```javascript
// الـ JS يتوقع: cls.instructor — لكن DB تخزّن teacher_name
// الحل الأبسط: في PHP response، إرجاع instructor كـ alias لـ teacher_name
// في get_student_data.php:
'instructor' => $class->teacher_name,   // توافق مع dashboard.js::renderClasses()
```

**نقطة التحقق:** فتح كل تبويب في المتصفح والتحقق من:
- Profile → يعرض البيانات من local_mzi_students
- My Programs → يعرض من local_mzi_registrations + local_mzi_payments
- Classes → يعرض من local_mzi_enrollments + local_mzi_classes
- Requests → يعرض من local_mzi_requests

**استراتيجية الرجوع:** إعادة تعليق الكود القديم (fetch_backend_data) وإزالة الكود الجديد في get_student_data.php.

---

### Step 4: إيقاف Backend Dashboard Read APIs (تدريجي)

**الهدف:** إيقاف endpoint `/api/v1/extension/students/*` من Backend بعد تأكيد عمل الـ Moodle DB path.

**لا تحذف — فقط علّم للإيقاف:**

**الملف المتوقع:** `backend/app/api/v1/endpoints/extension_students.py` (إذا وُجد)

```python
# أضف تحذير Deprecation:
@router.get("/extension/students/profile")
async def get_student_profile(moodle_user_id: int, db: Session = Depends(get_db)):
    """
    ⚠️  DEPRECATED: هذا الـ endpoint لا يُستخدم من Moodle UI بعد الآن.
    يُستخدم فقط لأغراض Admin وتصحيح الأخطاء.
    Dashboard يقرأ الآن من local_mzi_students مباشرة.
    """
    import warnings
    warnings.warn("Deprecated: Use Moodle DB projection tables directly", DeprecationWarning)
    # ... الكود القائم بدون تغيير
```

**نقطة التحقق (اختياري بعد 2 أسبوع):** تحقق من logs أن `/api/v1/extension/students/*` لا يتلقى أي طلبات.

**استراتيجية الرجوع:** إزالة تعليق `DEPRECATED` فقط — الكود لا يزال يعمل.

---

### ملخص خطوات التنفيذ

| الخطوة | الملفات المعدّلة | النوع | مخاطرة | Rollback |
|---|---|---|---|---|
| **Step 0** | لا شيء | فحص فقط | لا يوجد | لا يلزم |
| **Step 1A** | `backend/app/infra/moodle/projection_writer.py` (جديد) | إضافة ملف | منخفضة | حذف الملف |
| **Step 1B** | `backend/app/services/event_handler_service.py` | إضافة try/except block | منخفضة جداً | حذف الـ block |
| **Step 2** | `moodle_plugin/db/services.php`, `moodle_plugin/classes/external/` | إضافة WS functions | متوسطة | تعطيل الدوال |
| **Step 3A** | `moodle_plugin/ui/ajax/get_student_data.php` | تعديل switch cases | متوسطة | إعادة fetch_backend_data |
| **Step 3B** | `moodle_plugin/assets/js/dashboard.js` | تعديل alias | منخفضة | تعديل السطر |
| **Step 4** | `backend/app/api/v1/endpoints/extension_students.py` | إضافة Deprecation header | منخفضة | إزالة التعليق |

---

### شجرة التبعيات

```
Step 0 (فحص)
    └─► Step 1 (Dual-write في Backend)
            └─► Step 2 (Moodle WS functions)
                    └─► Step 3 (تحويل قراءة Dashboard)
                                └─► Step 4 (إيقاف Backend read APIs)
```

> **قاعدة ذهبية:** كل خطوة مستقلة — يمكن الرجوع عنها دون التأثير على الخطوات السابقة.  
> **أسماء API والحقول:** لا يُسمح بتغيير أي API name أو field mapping موجود. كل إضافة في **طبقة جديدة** فوق الكود الحالي.

---

*نهاية التقرير*
