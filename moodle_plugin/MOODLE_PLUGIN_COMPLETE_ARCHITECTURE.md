# 🏗️ معمارية Moodle Plugin - التحليل الكامل والمتكامل
# Complete Moodle Plugin Architecture & Analysis

<div dir="rtl">

## 📋 جدول المحتويات

1. [نظرة عامة شاملة](#نظرة-عامة-شاملة)
2. [تحليل البيانات الموجودة](#تحليل-البيانات-الموجودة)
3. [المعمارية المقترحة الكاملة](#المعمارية-المقترحة-الكاملة)
4. [هيكل الملفات التفصيلي](#هيكل-الملفات-التفصيلي)
5. [واجهات المستخدم](#واجهات-المستخدم)
6. [واجهات الإدارة](#واجهات-الإدارة)
7. [التكامل مع Backend](#التكامل-مع-backend)
8. [قواعد البيانات](#قواعد-البيانات)
9. [الأمان والمصادقة](#الأمان-والمصادقة)
10. [خطة التنفيذ](#خطة-التنفيذ)
11. [الاختبار والجودة](#الاختبار-والجودة)

---

## 🎯 نظرة عامة شاملة

### الهدف الاستراتيجي
إنشاء **Moodle Plugin متكامل** يربط نظام Moodle LMS مع Backend API بطريقة ثنائية الاتجاه (bidirectional)، مع توفير:

1. **Real-time Event Streaming** - إرسال الأحداث فورياً إلى Backend
2. **Student Dashboard** - لوحة طالب غنية بالبيانات من Zoho/Backend
3. **Admin Control Panel** - لوحة إدارة كاملة للإعدادات والمزامنة
4. **Data Sync Interface** - واجهة مزامنة البيانات الشاملة
5. **Financial Management** - إدارة البيانات المالية للطلاب

### المبادئ الأساسية

```
┌─────────────────────────────────────────────────────────────┐
│  1. Event-Driven Architecture (Real-time)                   │
│  2. Bidirectional Integration (Moodle ↔ Backend ↔ Zoho)    │
│  3. Separation of Concerns (Clean Architecture)             │
│  4. Security First (Authentication, Authorization, Audit)   │
│  5. User Experience (Beautiful UI, Fast Response)           │
│  6. Maintainability (Modular, Documented, Tested)           │
└─────────────────────────────────────────────────────────────┘
```

### نطاق المشروع

**المدخلات (من Moodle):**
- ✅ User Created/Updated Events
- ✅ Enrollment Created Events
- ✅ Grade Submitted/Updated Events
- ✅ Assignment Submissions
- ✅ Course Completions

**المخرجات (إلى Backend/Zoho):**
- ✅ Real-time webhooks (JSON payloads)
- ✅ Batch data sync (bulk operations)
- ✅ Event logs (audit trail)

**الواجهات:**
- ✅ Student Dashboard (read-only profile)
- ✅ Admin Settings Panel
- ✅ Sync Management Interface
- ✅ Financial Data Management

---

## 📊 تحليل البيانات الموجودة

### 1. Plugin الحالي (`mb_zoho_sync`)

**الملفات الموجودة:**
```
mb_zoho_sync/
├── version.php              ✅ معلومات Plugin
├── settings.php             ✅ رابط لوحة الإدارة
├── lib.php                  ✅ Navigation extension
├── classes/
│   ├── external.php         ✅ Webservice API (Rubric creation)
│   └── observers.php        ✅ Event handlers (Grade, User)
├── student.php              ✅ Student dashboard (outdated)
├── student_dashboard.php    ✅ Modern dashboard
├── manage.php               ⚠️ Finance management (incomplete)
└── ajax/                    ⚠️ AJAX endpoints (basic)
```

**التحليل:**

#### ✅ النقاط الإيجابية:
1. **Event Observers موجودة** - يتم التقاط الأحداث (grades, users)
2. **Student Dashboard موجودة** - واجهة أساسية للطالب
3. **Zoho Integration** - اتصال مباشر مع Zoho CRM
4. **Grade Conversion** - تحويل BTEC grades

#### ⚠️ النقاط التي تحتاج تحسين:
1. **No Backend Integration** - يتصل مباشرة مع Zoho (tight coupling)
2. **Hardcoded Token Management** - يقرأ من ملف `token.json`
3. **Limited Error Handling** - لا يوجد retry logic أو error recovery
4. **No Idempotency** - قد يرسل نفس الحدث مرتين
5. **Basic UI** - Dashboard بدائية، تحتاج تطوير
6. **No Configuration UI** - الإعدادات مشفرة في الكود
7. **No Audit Trail** - لا يوجد سجل للعمليات
8. **Direct DB Access** - يكتب مباشرة إلى Zoho بدون Backend

### 2. Backend API (الموجود)

**الـ Endpoints المتوفرة:**

```bash
# Phase 1-4: Zoho → Backend (موجودة ✅)
POST /v1/sync/students
POST /v1/sync/programs
POST /v1/sync/classes
POST /v1/sync/enrollments
POST /v1/sync/units
POST /v1/sync/registrations
POST /v1/sync/payments
POST /v1/sync/grades

# Phase 12: Moodle → Backend (موجودة ✅)
POST /v1/moodle/users          # Batch import
POST /v1/moodle/enrollments    # Batch import
POST /v1/moodle/grades          # Batch import
POST /v1/events/moodle/user_created
POST /v1/events/moodle/user_updated
POST /v1/events/moodle/enrollment_created
POST /v1/events/moodle/grade_updated

# Extension API (موجودة ✅)
GET /v1/extension/settings
PUT /v1/extension/settings
GET /v1/extension/modules
GET /v1/extension/field-mappings/{module}
```

**Database Schema:**

```sql
-- Core Tables (موجودة)
students (15+ fields)
programs (10+ fields)
classes (15+ fields)
enrollments (18+ fields)
units (10+ fields)
registrations (12+ fields)
payments (12+ fields)
grades (12+ fields)

-- Extension Tables (موجودة)
extension_tenants
extension_settings
extension_modules
extension_field_mappings
extension_sync_history
extension_api_keys
```

### 3. تدفق البيانات الحالي

```
┌─────────────────────────────────────────────────────────────┐
│                   CURRENT FLOW (mb_zoho_sync)               │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Moodle Event (grade_submitted)                            │
│       │                                                     │
│       ▼                                                     │
│  Observer::submission_graded_handler()                     │
│       │                                                     │
│       ├─ Get Token (from token.json)                       │
│       ├─ Search Zoho (Student ID, Class ID)                │
│       ├─ Convert Grade (BTEC)                              │
│       └─ POST directly to Zoho CRM API ❌                  │
│                                                             │
└─────────────────────────────────────────────────────────────┘

❌ المشاكل:
1. No Backend involvement (tight coupling)
2. Token management insecure
3. No retry on failure
4. No event deduplication
5. Zoho API changes break plugin
```

---

## 🏗️ المعمارية المقترحة الكاملة

### النموذج المعماري الجديد

```
┌────────────────────────────────────────────────────────────────────┐
│                        MOODLE SYSTEM                               │
├────────────────────────────────────────────────────────────────────┤
│                                                                    │
│  ┌──────────────────────────────────────────────────────────┐     │
│  │              EVENT DETECTION LAYER                        │     │
│  │  - core\event\user_created                               │     │
│  │  - core\event\user_updated                               │     │
│  │  - core\event\user_enrolment_created                     │     │
│  │  - mod_assign\event\submission_graded                    │     │
│  └─────────────────────┬────────────────────────────────────┘     │
│                        │                                           │
│                        ▼                                           │
│  ┌──────────────────────────────────────────────────────────┐     │
│  │        EVENT OBSERVER (classes/observer.php)             │     │
│  │  - Validate event                                        │     │
│  │  - Extract data                                          │     │
│  │  - Generate event_id (UUID)                              │     │
│  │  - Build JSON payload                                    │     │
│  └─────────────────────┬────────────────────────────────────┘     │
│                        │                                           │
│                        ▼                                           │
│  ┌──────────────────────────────────────────────────────────┐     │
│  │     DATA EXTRACTOR (classes/data_extractor.php)          │     │
│  │  - Query mdl_user                                        │     │
│  │  - Query mdl_grade_grades                                │     │
│  │  - Query mdl_enrol                                       │     │
│  │  - Format data for Backend API                           │     │
│  └─────────────────────┬────────────────────────────────────┘     │
│                        │                                           │
│                        ▼                                           │
│  ┌──────────────────────────────────────────────────────────┐     │
│  │     WEBHOOK SENDER (classes/webhook_sender.php)          │     │
│  │  - Add authentication (X-Moodle-Token)                   │     │
│  │  - HTTP POST to Backend                                  │     │
│  │  - Retry logic (3 attempts)                              │     │
│  │  - Log success/failure (mdl_mb_zoho_event_log)           │     │
│  └─────────────────────┬────────────────────────────────────┘     │
│                        │                                           │
└────────────────────────┼───────────────────────────────────────────┘
                         │ HTTPS
                         │
                         ▼
┌────────────────────────────────────────────────────────────────────┐
│                      BACKEND API SERVER                            │
├────────────────────────────────────────────────────────────────────┤
│                                                                    │
│  POST /v1/events/moodle/user_created                              │
│  POST /v1/events/moodle/user_updated                              │
│  POST /v1/events/moodle/enrollment_created                        │
│  POST /v1/events/moodle/grade_updated                             │
│                        │                                           │
│                        ▼                                           │
│  ┌──────────────────────────────────────────────────────────┐     │
│  │    Event Router + Deduplication                          │     │
│  │    - Check event_id uniqueness                           │     │
│  │    - Validate payload                                    │     │
│  │    - Queue for processing                                │     │
│  └─────────────────────┬────────────────────────────────────┘     │
│                        │                                           │
│                        ▼                                           │
│  ┌──────────────────────────────────────────────────────────┐     │
│  │    Service Layer (Business Logic)                        │     │
│  │    - Map Moodle → Canonical model                        │     │
│  │    - Apply transformations                               │     │
│  │    - Calculate fingerprint                               │     │
│  └─────────────────────┬────────────────────────────────────┘     │
│                        │                                           │
│                        ▼                                           │
│  ┌──────────────────────────────────────────────────────────┐     │
│  │    Database Layer (PostgreSQL)                           │     │
│  │    - students, enrollments, grades, etc.                 │     │
│  │    - integration_events_log                              │     │
│  └─────────────────────┬────────────────────────────────────┘     │
│                        │                                           │
│                        ▼                                           │
│  ┌──────────────────────────────────────────────────────────┐     │
│  │    Zoho Sync Service (Outbound)                          │     │
│  │    - Batch sync to Zoho CRM                              │     │
│  │    - Retry failed events                                 │     │
│  └──────────────────────────────────────────────────────────┘     │
│                                                                    │
└────────────────────────────────────────────────────────────────────┘
                         │
                         ▼
                  ┌──────────────┐
                  │  Zoho CRM    │
                  │  (BTEC)      │
                  └──────────────┘
```

### الفوائد من المعمارية الجديدة

| الميزة | قبل | بعد |
|--------|-----|-----|
| **Coupling** | مباشر مع Zoho ❌ | عبر Backend ✅ |
| **Retry Logic** | لا يوجد ❌ | 3 محاولات ✅ |
| **Deduplication** | لا يوجد ❌ | Event ID ✅ |
| **Audit Trail** | محدود ❌ | كامل ✅ |
| **Token Management** | ملف JSON ❌ | ENV vars ✅ |
| **Error Recovery** | يدوي ❌ | تلقائي ✅ |
| **Testing** | صعب ❌ | سهل ✅ |
| **Maintainability** | منخفض ❌ | عالي ✅ |

---

## 📁 هيكل الملفات التفصيلي

### الهيكل الكامل المقترح

```
moodle/local/moodle_zoho_integration/
├── version.php                         # Plugin metadata (v3.0)
├── settings.php                        # Admin settings page link
├── lib.php                             # Plugin hooks & utilities
├── README.md                           # Documentation
│
├── db/
│   ├── install.xml                     # Database schema
│   ├── upgrade.php                     # Database upgrades
│   ├── events.php                      # Event observer definitions
│   └── access.php                      # Capability definitions
│
├── classes/
│   ├── observer.php                    # Main event handler (NEW)
│   ├── data_extractor.php              # Extract data from Moodle DB (NEW)
│   ├── webhook_sender.php              # HTTP client for Backend (NEW)
│   ├── config_manager.php              # Settings management (NEW)
│   ├── event_logger.php                # Local event logging (NEW)
│   │
│   ├── api/
│   │   ├── student_profile_api.php     # Fetch student data from Backend
│   │   ├── sync_api.php                # Manual sync operations
│   │   └── health_check.php            # Backend health check
│   │
│   ├── forms/
│   │   ├── settings_form.php           # Admin settings form
│   │   ├── manual_sync_form.php        # Manual sync form
│   │   └── field_mapping_form.php      # Field mapping editor
│   │
│   └── task/
│       ├── retry_failed_webhooks.php   # Retry failed events (scheduled)
│       ├── cleanup_old_logs.php        # Clean old logs (scheduled)
│       └── health_monitor.php          # Monitor Backend health
│
├── ui/
│   ├── dashboard/
│   │   ├── student.php                 # Student dashboard (main)
│   │   ├── profile_tab.php             # Profile section
│   │   ├── academics_tab.php           # Academic info
│   │   ├── finance_tab.php             # Financial info
│   │   ├── classes_tab.php             # Enrolled classes
│   │   └── grades_tab.php              # Grade history
│   │
│   ├── admin/
│   │   ├── settings.php                # Main settings page
│   │   ├── sync_management.php         # Sync operations page
│   │   ├── event_log.php               # View event logs
│   │   ├── field_mappings.php          # Configure field mappings
│   │   └── diagnostics.php             # System diagnostics
│   │
│   └── ajax/
│       ├── get_student_data.php        # Fetch student profile (AJAX)
│       ├── search_students.php         # Search students (admin)
│       ├── trigger_sync.php            # Trigger manual sync
│       └── get_event_logs.php          # Fetch event logs
│
├── assets/
│   ├── css/
│   │   ├── dashboard.css               # Student dashboard styles
│   │   ├── admin.css                   # Admin panel styles
│   │   └── components.css              # Shared components
│   │
│   ├── js/
│   │   ├── dashboard.js                # Dashboard interactions
│   │   ├── admin.js                    # Admin panel scripts
│   │   ├── live_search.js              # Live search functionality
│   │   └── sync_manager.js             # Sync operations UI
│   │
│   └── images/
│       ├── icons/
│       └── logos/
│
├── lang/
│   ├── en/
│   │   └── local_moodle_zoho_integration.php  # English strings
│   └── ar/
│       └── local_moodle_zoho_integration.php  # Arabic strings
│
└── tests/
    ├── observer_test.php               # Test event handlers
    ├── webhook_sender_test.php         # Test HTTP client
    └── data_extractor_test.php         # Test data extraction
```

---

## 👤 واجهات المستخدم (Student)

### 1. Student Dashboard

**الموقع:** `ui/dashboard/student.php`

**المتطلبات:**
- ✅ Read-only (لا تعديل)
- ✅ Real-time data من Backend
- ✅ Modern UI (Bootstrap 5)
- ✅ Responsive design
- ✅ Dark/Light theme toggle
- ✅ Fast loading (AJAX tabs)

**الأقسام (Tabs):**

```
┌────────────────────────────────────────────────────────────┐
│  [Profile] [Academics] [Finance] [Classes] [Grades]       │
├────────────────────────────────────────────────────────────┤
│                                                            │
│  Profile Tab:                                              │
│  ├─ Student Photo                                          │
│  ├─ Full Name                                              │
│  ├─ Academic Email                                         │
│  ├─ Phone Number                                           │
│  ├─ Date of Birth                                          │
│  ├─ Address / City / Country                               │
│  └─ Student Status (Active/Inactive)                       │
│                                                            │
│  Academics Tab:                                            │
│  ├─ Enrolled Programs (list)                               │
│  ├─ Registration Dates                                     │
│  ├─ Program Status                                         │
│  └─ Expected Completion Date                               │
│                                                            │
│  Finance Tab:                                              │
│  ├─ Total Fees                                             │
│  ├─ Paid Amount                                            │
│  ├─ Outstanding Balance                                    │
│  ├─ Payment History (table)                                │
│  │   - Date, Amount, Method, Status                        │
│  └─ Download Receipts                                      │
│                                                            │
│  Classes Tab:                                              │
│  ├─ Current Classes (list)                                 │
│  │   - Class Name, Teacher, Schedule                       │
│  ├─ Class Status (Active/Completed)                        │
│  └─ Moodle Course Link                                     │
│                                                            │
│  Grades Tab:                                               │
│  ├─ Grade Summary (Distinction, Merit, Pass, Refer)        │
│  ├─ Grade History (table)                                  │
│  │   - Unit, Grade, Date, Feedback                         │
│  └─ GPA / Average                                          │
│                                                            │
└────────────────────────────────────────────────────────────┘
```

**Data Flow:**

```php
// ui/dashboard/student.php
$userid = $USER->id;

// AJAX call to backend
$profile_data = api_call("GET /v1/students/profile?moodle_user_id=$userid");

// Display in tabs
render_profile_tab($profile_data);
render_academics_tab($profile_data['programs']);
render_finance_tab($profile_data['payments']);
render_classes_tab($profile_data['classes']);
render_grades_tab($profile_data['grades']);
```

**Backend Endpoint المطلوب (NEW):**

```python
# app/api/v1/endpoints/student_profile.py

@router.get("/students/profile")
def get_student_profile(moodle_user_id: int, db: Session = Depends(get_db)):
    """
    Get complete student profile for dashboard
    
    Returns:
        {
            "student": {...},
            "programs": [...],
            "payments": [...],
            "classes": [...],
            "grades": [...]
        }
    """
    student = db.query(Student).filter_by(moodle_userid=moodle_user_id).first()
    if not student:
        raise HTTPException(404, "Student not found")
    
    # Join all related data
    return {
        "student": StudentSchema.from_orm(student),
        "programs": get_student_programs(student.zoho_id),
        "payments": get_student_payments(student.zoho_id),
        "classes": get_student_classes(student.zoho_id),
        "grades": get_student_grades(student.zoho_id)
    }
```

### 2. Admin Search Interface

**الموقع:** `ui/ajax/search_students.php`

**الميزات:**
- ✅ Live search (كتابة بدون زر)
- ✅ Search by name, email, username
- ✅ Real-time results (AJAX)
- ✅ Click to view student dashboard
- ✅ Admin-only access

```javascript
// assets/js/live_search.js
$('#liveSearchInput').on('input', debounce(async function() {
    const query = $(this).val().trim();
    if (query.length < 2) return;
    
    const response = await fetch(`ajax/search_students.php?q=${query}`);
    const html = await response.text();
    $('#liveSearchResults').html(html).show();
}, 300));
```

---

## ⚙️ واجهات الإدارة (Admin)

### 1. Main Settings Page

**الموقع:** `ui/admin/settings.php`

**الإعدادات المطلوبة:**

```php
┌────────────────────────────────────────────────────────────┐
│            Integration Settings                            │
├────────────────────────────────────────────────────────────┤
│                                                            │
│  Backend Configuration:                                    │
│  ├─ Backend URL: [https://backend.example.com]            │
│  ├─ API Token: [••••••••••••••] (encrypted)               │
│  ├─ Tenant ID: [default]                                   │
│  └─ Enable Integration: [✓]                                │
│                                                            │
│  Webhook Settings:                                         │
│  ├─ Enable Real-time Sync: [✓]                            │
│  ├─ Retry Failed Events: [✓]                              │
│  ├─ Max Retry Attempts: [3]                               │
│  ├─ Retry Delay (seconds): [60]                           │
│  └─ Log Level: [INFO] ▼                                    │
│                                                            │
│  Event Filters:                                            │
│  ├─ Sync User Created: [✓]                                │
│  ├─ Sync User Updated: [✓]                                │
│  ├─ Sync Enrollments: [✓]                                 │
│  ├─ Sync Grades: [✓]                                      │
│  └─ Sync Submissions: [ ]                                 │
│                                                            │
│  Dashboard Settings:                                       │
│  ├─ Show Financial Info: [✓]                              │
│  ├─ Show Grade Details: [✓]                               │
│  ├─ Enable Download Receipts: [✓]                         │
│  └─ Default Theme: [Light] ▼                              │
│                                                            │
│  [Save Settings]  [Test Connection]  [Reset to Default]   │
│                                                            │
└────────────────────────────────────────────────────────────┘
```

**الحفظ في Moodle Config:**

```php
// classes/config_manager.php
class config_manager {
    public static function save_settings($settings) {
        set_config('backend_url', $settings['backend_url'], 'local_moodle_zoho_integration');
        set_config('api_token', encrypt($settings['api_token']), 'local_moodle_zoho_integration');
        set_config('tenant_id', $settings['tenant_id'], 'local_moodle_zoho_integration');
        // ... etc
    }
    
    public static function get_settings() {
        return [
            'backend_url' => get_config('local_moodle_zoho_integration', 'backend_url'),
            'api_token' => decrypt(get_config('local_moodle_zoho_integration', 'api_token')),
            'tenant_id' => get_config('local_moodle_zoho_integration', 'tenant_id'),
            // ... etc
        ];
    }
}
```

### 2. Sync Management Interface

**الموقع:** `ui/admin/sync_management.php`

**الميزات:**

```
┌────────────────────────────────────────────────────────────┐
│            Sync Management                                 │
├────────────────────────────────────────────────────────────┤
│                                                            │
│  Manual Sync Operations:                                   │
│  ┌──────────────────────────────────────────────────┐     │
│  │ Sync Type: [Students ▼]                          │     │
│  │ Action: [Full Sync ▼] [Incremental ▼]           │     │
│  │ [Trigger Sync]                                    │     │
│  └──────────────────────────────────────────────────┘     │
│                                                            │
│  Sync History:                                             │
│  ┌────────────────────────────────────────────────────┐   │
│  │ Date       | Type      | Status   | Records | Time │   │
│  ├────────────────────────────────────────────────────┤   │
│  │ 2026-02-01 | Students  | Success  | 150     | 2s   │   │
│  │ 2026-02-01 | Grades    | Failed   | 0       | -    │   │
│  │ 2026-01-31 | Enrolls   | Success  | 45      | 1s   │   │
│  └────────────────────────────────────────────────────┘   │
│                                                            │
│  Failed Events (need retry):                               │
│  ┌────────────────────────────────────────────────────┐   │
│  │ Event ID  | Type      | Error      | [Retry]       │   │
│  ├────────────────────────────────────────────────────┤   │
│  │ uuid-123  | Grade     | Timeout    | [Retry Now]   │   │
│  │ uuid-456  | User      | 500 Error  | [Retry Now]   │   │
│  └────────────────────────────────────────────────────┘   │
│                                                            │
└────────────────────────────────────────────────────────────┘
```

### 3. Event Log Viewer

**الموقع:** `ui/admin/event_log.php`

**الميزات:**
- ✅ Filterable table (date, type, status)
- ✅ Search by event_id, user
- ✅ Pagination (50 per page)
- ✅ Export to CSV
- ✅ View full payload (JSON)

```
┌────────────────────────────────────────────────────────────┐
│            Event Log                                       │
├────────────────────────────────────────────────────────────┤
│                                                            │
│  Filters:                                                  │
│  Date: [2026-02-01] to [2026-02-01]                       │
│  Type: [All ▼]  Status: [All ▼]  [Search]                │
│                                                            │
│  Results (150 events):                                     │
│  ┌──────────────────────────────────────────────────┐     │
│  │ Time     | Type    | User      | Status  | Action│     │
│  ├──────────────────────────────────────────────────┤     │
│  │ 14:23:15 | Grade   | John Doe  | Success | [View]│     │
│  │ 14:22:10 | Enroll  | Jane Smith| Success | [View]│     │
│  │ 14:20:05 | User    | Ali Ahmad | Failed  | [View]│     │
│  └──────────────────────────────────────────────────┘     │
│                                                            │
│  [Export to CSV]  [Clear Old Logs]                        │
│                                                            │
└────────────────────────────────────────────────────────────┘
```

### 4. Field Mappings Editor

**الموقع:** `ui/admin/field_mappings.php`

**الغرض:** تخصيص mapping بين حقول Moodle وحقول Backend/Zoho

```
┌────────────────────────────────────────────────────────────┐
│            Field Mappings Configuration                    │
├────────────────────────────────────────────────────────────┤
│                                                            │
│  Module: [Students ▼]                                      │
│                                                            │
│  ┌──────────────────────────────────────────────────┐     │
│  │ Moodle Field    → Backend Field    → Transform   │     │
│  ├──────────────────────────────────────────────────┤     │
│  │ username        → academic_email   → None        │     │
│  │ firstname       → display_name     → Concat      │     │
│  │ lastname        →                  →             │     │
│  │ email           → academic_email   → None        │     │
│  │ phone1          → phone            → None        │     │
│  │ [+ Add Mapping]                                  │     │
│  └──────────────────────────────────────────────────┘     │
│                                                            │
│  [Save Mappings]  [Reset to Default]                      │
│                                                            │
└────────────────────────────────────────────────────────────┘
```

---

## 🔗 التكامل مع Backend

### 1. Authentication

**Method:** Token-based (X-Moodle-Token header)

```php
// classes/webhook_sender.php
class webhook_sender {
    private function get_headers() {
        $config = config_manager::get_settings();
        return [
            'Content-Type: application/json',
            'X-Moodle-Token: ' . $config['api_token'],
            'X-Tenant-ID: ' . $config['tenant_id'],
            'X-Event-ID: ' . $this->event_id,  // For idempotency
        ];
    }
}
```

**Backend Verification:**

```python
# app/api/v1/dependencies/auth.py

async def verify_moodle_token(
    x_moodle_token: str = Header(...),
    x_tenant_id: str = Header(default="default")
):
    """Verify Moodle API token"""
    expected_token = settings.MOODLE_API_TOKEN
    if x_moodle_token != expected_token:
        raise HTTPException(401, "Invalid Moodle token")
    return x_tenant_id
```

### 2. Event Payload Structure

**Standard Format:**

```json
{
  "event_id": "uuid-v4-here",
  "event_type": "user_created",
  "timestamp": "2026-02-01T14:30:00Z",
  "source": "moodle",
  "moodle_url": "https://elearning.abchorizon.com",
  "tenant_id": "default",
  "data": {
    "userid": 123,
    "username": "john.doe@example.com",
    "firstname": "John",
    "lastname": "Doe",
    "email": "john.doe@example.com",
    "idnumber": "STU12345",
    "phone1": "+962791234567",
    "city": "Amman",
    "country": "JO",
    "suspended": false,
    "deleted": false,
    "timecreated": 1640000000,
    "timemodified": 1640000000
  }
}
```

### 3. Retry Logic

```php
// classes/webhook_sender.php
public function send_with_retry($url, $payload, $max_attempts = 3) {
    $attempt = 0;
    $delay = 5; // seconds
    
    while ($attempt < $max_attempts) {
        $attempt++;
        
        try {
            $response = $this->send($url, $payload);
            
            if ($response['http_code'] >= 200 && $response['http_code'] < 300) {
                // Success
                $this->log_event($payload['event_id'], 'success', $response);
                return true;
            }
        } catch (Exception $e) {
            $this->log_event($payload['event_id'], 'failed', [
                'attempt' => $attempt,
                'error' => $e->getMessage()
            ]);
        }
        
        if ($attempt < $max_attempts) {
            sleep($delay);
            $delay *= 2; // Exponential backoff
        }
    }
    
    // All retries failed
    $this->log_event($payload['event_id'], 'failed_all_retries', []);
    return false;
}
```

### 4. Error Handling

```php
// classes/observer.php
public static function user_created_handler(\core\event\user_created $event) {
    try {
        // Extract data
        $data = data_extractor::extract_user_data($event);
        
        // Send webhook
        $sender = new webhook_sender();
        $success = $sender->send_event('user_created', $data);
        
        if (!$success) {
            // Queue for retry
            event_logger::queue_for_retry($event->objectid, 'user_created', $data);
        }
        
    } catch (Exception $e) {
        // Log error but don't break Moodle
        error_log("Webhook failed: " . $e->getMessage());
        event_logger::log_error($event->objectid, 'user_created', $e->getMessage());
    }
}
```

---

## 💾 قواعد البيانات

### 1. Moodle Tables (إضافات جديدة)

```sql
-- Event log table (local)
CREATE TABLE mdl_mb_zoho_event_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    event_id VARCHAR(36) UNIQUE NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    event_data LONGTEXT,
    status VARCHAR(20) NOT NULL, -- pending, success, failed, retry
    retry_count INT DEFAULT 0,
    last_error TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    INDEX idx_event_type (event_type),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Sync history table
CREATE TABLE mdl_mb_zoho_sync_history (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    sync_type VARCHAR(50) NOT NULL, -- students, grades, enrollments
    sync_action VARCHAR(50) NOT NULL, -- full, incremental, manual
    status VARCHAR(20) NOT NULL, -- running, completed, failed
    records_processed INT DEFAULT 0,
    records_failed INT DEFAULT 0,
    started_at TIMESTAMP NOT NULL,
    completed_at TIMESTAMP NULL,
    error_message TEXT,
    triggered_by INT, -- userid who triggered
    INDEX idx_sync_type (sync_type),
    INDEX idx_status (status)
);

-- Config cache table (for encrypted settings)
CREATE TABLE mdl_mb_zoho_config (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) UNIQUE NOT NULL,
    config_value LONGTEXT,
    is_encrypted TINYINT DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### 2. Backend Tables (موجودة مسبقاً)

```sql
-- Students table (موجودة)
students (
    id, zoho_id, moodle_userid, username, academic_email,
    display_name, phone, status, fingerprint, created_at, updated_at
)

-- Enrollments table (موجودة)
enrollments (
    id, zoho_id, student_zoho_id, class_zoho_id,
    moodle_user_id, moodle_course_id, moodle_enrollment_id,
    status, fingerprint, created_at, updated_at
)

-- Grades table (موجودة)
grades (
    id, zoho_id, student_zoho_id, unit_zoho_id,
    grade_value, score, grade_date, comments,
    fingerprint, created_at, updated_at
)

-- integration_events_log (موجودة)
integration_events_log (
    id, event_id, source, event_type, module,
    record_id, payload, status, retry_count,
    processed_at, created_at
)
```

### 3. Data Sync Strategy

```
┌─────────────────────────────────────────────────────────────┐
│              BIDIRECTIONAL SYNC FLOW                        │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Moodle → Backend (Real-time):                             │
│  ├─ Event occurs (user_created, grade_updated)             │
│  ├─ Observer captures event                                │
│  ├─ Generate unique event_id (UUID)                        │
│  ├─ POST to Backend /v1/events/moodle/*                    │
│  ├─ Backend checks event_id (deduplication)                │
│  ├─ Backend processes and stores in PostgreSQL             │
│  └─ Backend syncs to Zoho (async)                          │
│                                                             │
│  Backend → Moodle (On-demand):                             │
│  ├─ Student views dashboard                                │
│  ├─ Moodle calls Backend API (GET /v1/students/profile)    │
│  ├─ Backend returns aggregated data                        │
│  └─ Moodle renders UI                                      │
│                                                             │
│  Zoho → Backend → Moodle (Scheduled):                      │
│  ├─ Zoho Workflow triggers webhook                         │
│  ├─ Backend receives and stores data                       │
│  ├─ Moodle cron job checks for updates                     │
│  └─ Moodle pulls new data via API                          │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## 🔐 الأمان والمصادقة

### 1. Token Storage (Encrypted)

```php
// classes/config_manager.php
class config_manager {
    private static function encrypt($value) {
        $key = get_config('local_moodle_zoho_integration', 'encryption_key');
        if (!$key) {
            // Generate new key on first use
            $key = bin2hex(random_bytes(32));
            set_config('encryption_key', $key, 'local_moodle_zoho_integration');
        }
        
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($value, 'AES-256-CBC', hex2bin($key), 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    private static function decrypt($encrypted) {
        $key = get_config('local_moodle_zoho_integration', 'encryption_key');
        $data = base64_decode($encrypted);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', hex2bin($key), 0, $iv);
    }
}
```

### 2. Capability System

```php
// db/access.php
$capabilities = [
    'local/moodle_zoho_integration:manage' => [
        'riskbitmask' => RISK_CONFIG,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
    'local/moodle_zoho_integration:viewdashboard' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_USER,
        'archetypes' => [
            'student' => CAP_ALLOW,
            'user' => CAP_ALLOW,
        ],
    ],
    'local/moodle_zoho_integration:viewothers' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
        ],
    ],
];
```

### 3. Input Validation

```php
// classes/data_extractor.php
class data_extractor {
    public static function extract_user_data($event) {
        global $DB;
        
        $userid = clean_param($event->objectid, PARAM_INT);
        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        
        return [
            'userid' => (int)$user->id,
            'username' => clean_param($user->username, PARAM_EMAIL),
            'firstname' => clean_param($user->firstname, PARAM_TEXT),
            'lastname' => clean_param($user->lastname, PARAM_TEXT),
            'email' => clean_param($user->email, PARAM_EMAIL),
            'phone1' => clean_param($user->phone1, PARAM_TEXT),
            // ... validate all fields
        ];
    }
}
```

### 4. HTTPS Enforcement

```php
// classes/webhook_sender.php
private function send($url, $payload) {
    // Enforce HTTPS
    if (strpos($url, 'https://') !== 0) {
        throw new Exception('Only HTTPS URLs are allowed');
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    // ... rest of cURL config
}
```

---

## 📅 خطة التنفيذ (7 أسابيع)

### Week 1: Core Infrastructure

**Day 1-2: Project Setup**
- ✅ Create plugin structure
- ✅ Setup version.php, db/install.xml
- ✅ Create database tables (event_log, sync_history, config)
- ✅ Test plugin installation

**Day 3-4: Observer & Data Extractor**
- ✅ Implement classes/observer.php (4 event handlers)
- ✅ Implement classes/data_extractor.php
- ✅ Unit tests for data extraction

**Day 5: Webhook Sender**
- ✅ Implement classes/webhook_sender.php
- ✅ Add retry logic (exponential backoff)
- ✅ Add event logging

**Day 6-7: Testing & Bug Fixes**
- ✅ Integration testing (Moodle → Backend)
- ✅ Test all 4 event types
- ✅ Fix any issues

**Deliverables:**
- [ ] Plugin installable in Moodle
- [ ] Events captured and sent to Backend
- [ ] Retry logic working
- [ ] Event log populated

---

### Week 2: Admin Interface

**Day 1-2: Settings Page**
- ✅ Create ui/admin/settings.php
- ✅ Implement config_manager.php
- ✅ Add encryption for sensitive data
- ✅ Test connection button

**Day 3-4: Sync Management**
- ✅ Create ui/admin/sync_management.php
- ✅ Manual sync trigger
- ✅ Sync history viewer
- ✅ Failed events retry UI

**Day 5: Event Log Viewer**
- ✅ Create ui/admin/event_log.php
- ✅ Filterable table
- ✅ Pagination
- ✅ Export to CSV

**Day 6-7: Field Mappings Editor**
- ✅ Create ui/admin/field_mappings.php
- ✅ Editable mapping UI
- ✅ Save/load mappings

**Deliverables:**
- [ ] Full admin panel working
- [ ] Settings saved and loaded
- [ ] Manual sync functional
- [ ] Event logs viewable

---

### Week 3: Student Dashboard

**Day 1-2: Dashboard Structure**
- ✅ Create ui/dashboard/student.php
- ✅ Tab layout (5 tabs)
- ✅ AJAX loading
- ✅ Theme toggle

**Day 3: Profile & Academics Tabs**
- ✅ Implement profile_tab.php
- ✅ Implement academics_tab.php
- ✅ Fetch data from Backend API

**Day 4: Finance & Classes Tabs**
- ✅ Implement finance_tab.php
- ✅ Implement classes_tab.php
- ✅ Payment history table

**Day 5: Grades Tab**
- ✅ Implement grades_tab.php
- ✅ Grade history table
- ✅ GPA calculation

**Day 6-7: UI Polish**
- ✅ Styling (CSS)
- ✅ Responsive design
- ✅ Loading states
- ✅ Error handling

**Deliverables:**
- [ ] Student dashboard complete
- [ ] All 5 tabs working
- [ ] Data loaded from Backend
- [ ] Beautiful UI

---

### Week 4: Backend API Extensions

**Day 1-3: Student Profile Endpoint**
```python
# app/api/v1/endpoints/student_profile.py
@router.get("/students/profile")
def get_student_profile(moodle_user_id: int):
    # Aggregate all student data
    pass
```

**Day 4-5: Batch Sync Endpoints**
```python
# app/api/v1/endpoints/batch_sync.py
@router.post("/batch/sync/students")
def batch_sync_students(user_ids: List[int]):
    # Sync multiple students at once
    pass
```

**Day 6-7: Health Check & Diagnostics**
```python
# app/api/v1/endpoints/health.py
@router.get("/health/moodle")
def moodle_health_check():
    # Return system health status
    pass
```

**Deliverables:**
- [ ] Backend API endpoints complete
- [ ] Student profile endpoint working
- [ ] Batch sync functional
- [ ] Health check available

---

### Week 5: Scheduled Tasks

**Day 1-2: Retry Failed Webhooks**
```php
// classes/task/retry_failed_webhooks.php
class retry_failed_webhooks extends \core\task\scheduled_task {
    public function execute() {
        // Retry all failed events
    }
}
```

**Day 3-4: Cleanup Old Logs**
```php
// classes/task/cleanup_old_logs.php
class cleanup_old_logs extends \core\task\scheduled_task {
    public function execute() {
        // Delete logs older than 90 days
    }
}
```

**Day 5-6: Health Monitor**
```php
// classes/task/health_monitor.php
class health_monitor extends \core\task\scheduled_task {
    public function execute() {
        // Check Backend health, send alerts
    }
}
```

**Day 7: Testing**
- ✅ Test all scheduled tasks
- ✅ Verify cron execution

**Deliverables:**
- [ ] 3 scheduled tasks implemented
- [ ] Cron jobs configured
- [ ] Tasks tested

---

### Week 6: Integration Testing

**Day 1-2: End-to-End Testing**
- ✅ Test full workflow: Moodle → Backend → Zoho
- ✅ Create 100 test users
- ✅ Submit 50 test grades
- ✅ Verify data in Backend and Zoho

**Day 3-4: Performance Testing**
- ✅ Load test (1000 concurrent events)
- ✅ Measure response times
- ✅ Optimize slow queries

**Day 5-6: Security Testing**
- ✅ Penetration testing
- ✅ Token security audit
- ✅ SQL injection checks

**Day 7: Bug Fixes**
- ✅ Fix all critical bugs
- ✅ Address performance issues

**Deliverables:**
- [ ] All tests passing
- [ ] Performance optimized
- [ ] Security validated

---

### Week 7: Documentation & Deployment

**Day 1-2: Documentation**
- ✅ README.md (installation guide)
- ✅ ADMIN_GUIDE.md (configuration)
- ✅ USER_GUIDE.md (student dashboard)
- ✅ API_REFERENCE.md (Backend endpoints)

**Day 3-4: Deployment**
- ✅ Deploy to production Moodle
- ✅ Configure settings
- ✅ Test live data

**Day 5: Training**
- ✅ Train admin staff
- ✅ Create video tutorials
- ✅ Demo to stakeholders

**Day 6-7: Monitoring & Support**
- ✅ Monitor logs
- ✅ Fix any production issues
- ✅ Gather feedback

**Deliverables:**
- [ ] Plugin deployed to production ✅
- [ ] Documentation complete
- [ ] Training completed
- [ ] System live! 🚀

---

## 🧪 الاختبار والجودة

### 1. Unit Tests

```php
// tests/observer_test.php
class observer_testcase extends advanced_testcase {
    public function test_user_created_event() {
        $this->resetAfterTest();
        
        // Create test user
        $user = $this->getDataGenerator()->create_user();
        
        // Verify event logged
        $log = $DB->get_record('mb_zoho_event_log', [
            'event_type' => 'user_created'
        ]);
        
        $this->assertNotEmpty($log);
        $this->assertEquals('pending', $log->status);
    }
}
```

### 2. Integration Tests

```php
// tests/integration_test.php
class integration_testcase extends advanced_testcase {
    public function test_end_to_end_grade_sync() {
        // 1. Create student in Moodle
        $student = $this->create_test_student();
        
        // 2. Submit grade
        $grade = $this->submit_test_grade($student->id);
        
        // 3. Verify webhook sent
        $this->assert_webhook_sent('grade_updated');
        
        // 4. Check Backend received data
        $backend_grade = $this->fetch_from_backend($student->id);
        $this->assertEquals($grade->grade, $backend_grade['score']);
    }
}
```

### 3. Performance Benchmarks

**Target Metrics:**
- ✅ Event capture: < 50ms
- ✅ Webhook send: < 200ms
- ✅ Dashboard load: < 1s
- ✅ Admin page load: < 500ms
- ✅ Database query: < 100ms

### 4. Code Quality

```bash
# PHPStan (Static Analysis)
phpstan analyse classes/ ui/ --level=5

# PHP Code Sniffer (PSR-12)
phpcs --standard=PSR12 classes/ ui/

# Moodle Code Checker
vendor/bin/phpcbf --standard=moodle classes/
```

---

## 📊 ملخص المشروع

### الإحصائيات المتوقعة

| المقياس | القيمة |
|---------|--------|
| **الملفات** | ~40 ملف PHP |
| **الأكواد** | ~5,000 سطر |
| **الـ UI Pages** | 8 صفحات |
| **API Endpoints** | 15+ endpoint |
| **Database Tables** | 3 جداول (Moodle) |
| **Event Types** | 4 أنواع أحداث |
| **الوقت** | 7 أسابيع |
| **المطورين** | 1-2 مطور |

### الميزات الرئيسية

✅ **Real-time Event Streaming** - Moodle → Backend  
✅ **Beautiful Student Dashboard** - 5 tabs, modern UI  
✅ **Complete Admin Panel** - Settings, sync, logs  
✅ **Retry Logic** - Automatic retry with exponential backoff  
✅ **Event Deduplication** - UUID-based idempotency  
✅ **Encrypted Configuration** - Secure token storage  
✅ **Scheduled Tasks** - Auto-retry, cleanup, monitoring  
✅ **Comprehensive Logging** - Full audit trail  
✅ **Bidirectional Sync** - Moodle ↔ Backend ↔ Zoho  
✅ **Production-Ready** - Tested, documented, deployed  

### التأثير المتوقع

**للطلاب:**
- ✅ رؤية شاملة لبياناتهم الأكاديمية والمالية
- ✅ واجهة جميلة وسهلة الاستخدام
- ✅ بيانات محدثة في الوقت الفعلي

**للإداريين:**
- ✅ إدارة كاملة من لوحة واحدة
- ✅ رؤية لجميع عمليات المزامنة
- ✅ إصلاح الأخطاء بسهولة

**للنظام:**
- ✅ تكامل ثنائي الاتجاه سلس
- ✅ موثوقية عالية (retry + idempotency)
- ✅ قابلية الصيانة والتطوير

---

## 🎯 الخلاصة

هذه المعمارية توفر:

1. **Separation of Concerns** - Moodle يركز على التعليم، Backend يدير التكامل
2. **Reliability** - Retry logic + event deduplication + audit trail
3. **Maintainability** - Clean code + documentation + tests
4. **Security** - Encrypted tokens + HTTPS + input validation
5. **User Experience** - Beautiful UI + fast response + real-time data
6. **Scalability** - يمكن توسيعه لآلاف الطلاب

**الخطوة التالية:** ابدأ بـ Week 1 - Core Infrastructure! 🚀

</div>

---

**تاريخ الإنشاء:** فبراير 1, 2026  
**الإصدار:** 1.0 (Complete Architecture)  
**المؤلف:** AI Architecture Team  
**الحالة:** Ready for Implementation ✅
