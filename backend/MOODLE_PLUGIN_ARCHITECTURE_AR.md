# معمارية Moodle Plugin - التوثيق الكامل
# Moodle Plugin Complete Architecture

<div dir="rtl">

## 📋 جدول المحتويات

1. [نظرة عامة](#نظرة-عامة)
2. [تحليل البيانات المطلوبة](#تحليل-البيانات-المطلوبة)
3. [المعمارية الكاملة](#المعمارية-الكاملة)
4. [هيكل الملفات](#هيكل-الملفات)
5. [شرح كل ملف بالتفصيل](#شرح-كل-ملف-بالتفصيل)
6. [تدفق البيانات](#تدفق-البيانات)
7. [الأمان والمصادقة](#الأمان-والمصادقة)
8. [خطة التنفيذ](#خطة-التنفيذ)

---

## 🎯 نظرة عامة

### الهدف من الـ Plugin
إنشاء plugin في Moodle يقوم بإرسال الأحداث (events) في الوقت الفعلي إلى Backend API عندما:
- يتم إنشاء مستخدم جديد
- يتم تحديث ملف مستخدم
- يتم تسجيل مستخدم في كورس
- يتم إدخال أو تحديث درجة

### تدفق البيانات العام
```
Moodle Event → Observer → Webhook Request → Backend API → Database → Zoho CRM
```

---

## 📊 تحليل البيانات المطلوبة

### 1. User Created/Updated Event

**Backend Endpoint:** `POST /api/v1/events/moodle/user_created`  
**Backend Endpoint:** `POST /api/v1/events/moodle/user_updated`

**البيانات المطلوبة من Moodle:**

```json
{
  "eventname": "\\core\\event\\user_created",
  "userid": 123,                    // رقم المستخدم في Moodle (إجباري)
  "username": "john.doe@email.com", // Username (إجباري)
  "firstname": "John",              // الاسم الأول (إجباري)
  "lastname": "Doe",                // الاسم الأخير (إجباري)
  "email": "john.doe@email.com",    // البريد الإلكتروني (إجباري)
  "idnumber": "STU12345",           // رقم الطالب (اختياري)
  "phone1": "+962791234567",        // رقم الهاتف (اختياري)
  "city": "Amman",                  // المدينة (اختياري)
  "country": "JO",                  // كود الدولة (اختياري)
  "suspended": false,               // هل محظور (إجباري)
  "deleted": false,                 // هل محذوف (إجباري)
  "timecreated": 1640000000,        // وقت الإنشاء timestamp (إجباري)
  "timemodified": 1640000000        // وقت التعديل timestamp (إجباري)
}
```

**جداول Moodle المستخدمة:**
- `mdl_user` - الجدول الرئيسي للمستخدمين

**الحقول من قاعدة Moodle:**
```php
$user = $DB->get_record('user', ['id' => $event->relateduserid]);

$data = [
    'userid' => (int)$user->id,
    'username' => $user->username,
    'firstname' => $user->firstname,
    'lastname' => $user->lastname,
    'email' => $user->email,
    'idnumber' => $user->idnumber,
    'phone1' => $user->phone1,
    'city' => $user->city,
    'country' => $user->country,
    'suspended' => (bool)$user->suspended,
    'deleted' => (bool)$user->deleted,
    'timecreated' => (int)$user->timecreated,
    'timemodified' => (int)$user->timemodified,
];
```

---

### 2. User Enrolled Event

**Backend Endpoint:** `POST /api/v1/events/moodle/user_enrolled`

**البيانات المطلوبة:**

```json
{
  "eventname": "\\core\\event\\user_enrolment_created",
  "enrollmentid": 456,              // رقم التسجيل (إجباري)
  "userid": 123,                    // رقم المستخدم (إجباري)
  "courseid": 789,                  // رقم الكورس (إجباري)
  "roleid": 5,                      // رقم الدور (5=طالب) (إجباري)
  "status": 0,                      // الحالة: 0=نشط، 1=معلق (إجباري)
  "timestart": 1640000000,          // وقت بدء التسجيل (إجباري)
  "timeend": 1672536000,            // وقت نهاية التسجيل (اختياري)
  "timecreated": 1640000000         // وقت الإنشاء (إجباري)
}
```

**جداول Moodle المستخدمة:**
- `mdl_user_enrolments` - تسجيلات الطلاب
- `mdl_enrol` - طرق التسجيل
- `mdl_course` - الكورسات

**الحقول من قاعدة Moodle:**
```php
$enrolment = $DB->get_record('user_enrolments', ['id' => $event->objectid]);
$enrol = $DB->get_record('enrol', ['id' => $enrolment->enrolid]);

$data = [
    'enrollmentid' => (int)$enrolment->id,
    'userid' => (int)$enrolment->userid,
    'courseid' => (int)$enrol->courseid,
    'roleid' => 5, // Student role
    'status' => (int)$enrolment->status,
    'timestart' => (int)$enrolment->timestart,
    'timeend' => (int)$enrolment->timeend,
    'timecreated' => (int)$enrolment->timecreated,
];
```

---

### 3. Grade Updated Event

**Backend Endpoint:** `POST /api/v1/events/moodle/grade_updated`

**البيانات المطلوبة:**

```json
{
  "eventname": "\\core\\event\\user_graded",
  "gradeid": 789,                   // رقم الدرجة (إجباري)
  "userid": 123,                    // رقم الطالب (إجباري)
  "itemid": 456,                    // رقم Grade Item (إجباري)
  "itemname": "Assignment 1",       // اسم العنصر (اختياري)
  "finalgrade": 85.5,               // الدرجة النهائية 0-100 (اختياري)
  "feedback": "Excellent work!",    // التعليق (اختياري)
  "grader": 2,                      // رقم المصحح (اختياري)
  "timecreated": 1640000000,        // وقت الإنشاء (إجباري)
  "timemodified": 1640000100        // وقت التعديل (إجباري)
}
```

**جداول Moodle المستخدمة:**
- `mdl_grade_grades` - الدرجات
- `mdl_grade_items` - عناصر التقييم (assignments, quizzes)

**الحقول من قاعدة Moodle:**
```php
$grade = $DB->get_record('grade_grades', ['id' => $event->objectid]);
$grade_item = $DB->get_record('grade_items', ['id' => $grade->itemid]);

$data = [
    'gradeid' => (int)$grade->id,
    'userid' => (int)$grade->userid,
    'itemid' => (int)$grade->itemid,
    'itemname' => $grade_item->itemname,
    'finalgrade' => (float)$grade->finalgrade,
    'feedback' => $grade->feedback,
    'grader' => (int)$grade->usermodified,
    'timecreated' => (int)$grade->timecreated,
    'timemodified' => (int)$grade->timemodified,
];
```

**ملاحظة هامة:**  
Backend يقوم بتحويل الدرجة الرقمية إلى BTEC Grade تلقائياً:
- 70-100 → Distinction
- 60-69 → Merit
- 40-59 → Pass
- 0-39 → Refer

---

## 🏗️ المعمارية الكاملة

### Components Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    MOODLE SYSTEM                            │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌──────────────┐   ┌──────────────┐   ┌──────────────┐   │
│  │ User Created │   │ User Updated │   │User Enrolled │   │
│  │    Event     │   │    Event     │   │    Event     │   │
│  └──────┬───────┘   └──────┬───────┘   └──────┬───────┘   │
│         │                  │                  │            │
│         └──────────────────┴──────────────────┘            │
│                            │                               │
│                            ▼                               │
│              ┌─────────────────────────┐                   │
│              │   Event Dispatcher      │                   │
│              └────────────┬────────────┘                   │
│                           │                                │
│                           ▼                                │
│              ┌─────────────────────────┐                   │
│              │ local_backend_sync      │ ◄── Plugin        │
│              │      Observer           │                   │
│              └────────────┬────────────┘                   │
│                           │                                │
│            ┌──────────────┼──────────────┐                 │
│            ▼              ▼              ▼                 │
│      ┌─────────┐    ┌─────────┐   ┌──────────┐            │
│      │Get User │    │Get Enrol│   │Get Grade │            │
│      │  Data   │    │  Data   │   │   Data   │            │
│      └────┬────┘    └────┬────┘   └────┬─────┘            │
│           │              │             │                   │
│           └──────────────┼─────────────┘                   │
│                          ▼                                 │
│              ┌─────────────────────────┐                   │
│              │  Webhook Sender         │                   │
│              │  (HTTP POST)            │                   │
│              └────────────┬────────────┘                   │
└──────────────────────────┼─────────────────────────────────┘
                           │
                           │ HTTPS/HTTP
                           │
                           ▼
┌──────────────────────────────────────────────────────────┐
│                  BACKEND API SERVER                       │
├──────────────────────────────────────────────────────────┤
│                                                           │
│     ┌────────────────────────────────────────────┐       │
│     │   /api/v1/events/moodle/*                  │       │
│     │                                             │       │
│     │  ┌──────────────┐  ┌──────────────┐       │       │
│     │  │user_created  │  │user_updated  │       │       │
│     │  └──────┬───────┘  └──────┬───────┘       │       │
│     │  ┌──────────────┐  ┌──────────────┐       │       │
│     │  │user_enrolled │  │grade_updated │       │       │
│     │  └──────┬───────┘  └──────┬───────┘       │       │
│     └─────────┼─────────────────┼────────────────┘       │
│               │                 │                         │
│               ▼                 ▼                         │
│     ┌────────────────────────────────────┐               │
│     │   Data Validation & Processing     │               │
│     │   - Parse JSON                     │               │
│     │   - Validate fields                │               │
│     │   - Convert grades (BTEC)          │               │
│     └───────────────┬────────────────────┘               │
│                     │                                     │
│                     ▼                                     │
│     ┌────────────────────────────────────┐               │
│     │      PostgreSQL Database           │               │
│     │   - students                       │               │
│     │   - enrollments                    │               │
│     │   - grades                         │               │
│     └───────────────┬────────────────────┘               │
│                     │                                     │
└─────────────────────┼─────────────────────────────────────┘
                      │
                      │ Future: Sync Service
                      │
                      ▼
          ┌────────────────────────┐
          │     Zoho CRM API       │
          │   - BTEC_Students      │
          │   - Enrollments        │
          │   - Grades             │
          └────────────────────────┘
```

---

## 📁 هيكل الملفات الكامل

```
moodle/local/backend_sync/
├── version.php                      # معلومات النسخة والمتطلبات
├── settings.php                     # صفحة الإعدادات (NEW)
├── lib.php                          # وظائف عامة (helper functions)
├── db/
│   ├── install.xml                  # Database schema (optional)
│   ├── events.php                   # تعريف الـ Observers
│   ├── upgrade.php                  # Database upgrades (optional)
│   └── access.php                   # Permissions (optional)
├── classes/
│   ├── observer.php                 # Event handlers (الملف الأساسي)
│   ├── webhook_sender.php           # إرسال HTTP requests (NEW)
│   ├── data_extractor.php           # استخراج البيانات من Moodle (NEW)
│   └── task/
│       └── retry_failed_webhooks.php # إعادة محاولة الفاشلة (NEW)
├── lang/
│   └── en/
│       └── local_backend_sync.php   # الترجمة الإنجليزية
├── tests/
│   └── observer_test.php            # Unit tests (optional)
└── README.md                        # التوثيق
```

---

## 📝 شرح كل ملف بالتفصيل

### 1. version.php

```php
<?php
/**
 * Plugin version information
 * 
 * @package    local_backend_sync
 * @copyright  2026 Your Organization
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_backend_sync';
$plugin->version   = 2026012600;           // YYYYMMDDXX
$plugin->requires  = 2021051700;           // Moodle 3.11+
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = 'v1.0.0';
```

**الغرض:** تعريف معلومات الـ plugin (النسخة، المتطلبات، الاستقرار)

---

### 2. settings.php (جديد)

```php
<?php
/**
 * Plugin settings page
 * 
 * @package    local_backend_sync
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_backend_sync', 
        get_string('pluginname', 'local_backend_sync'));

    // Backend API URL
    $settings->add(new admin_setting_configtext(
        'local_backend_sync/backend_url',
        get_string('backend_url', 'local_backend_sync'),
        get_string('backend_url_desc', 'local_backend_sync'),
        'http://localhost:8001',
        PARAM_URL
    ));

    // API Token
    $settings->add(new admin_setting_configpasswordunmask(
        'local_backend_sync/api_token',
        get_string('api_token', 'local_backend_sync'),
        get_string('api_token_desc', 'local_backend_sync'),
        ''
    ));

    // Tenant ID
    $settings->add(new admin_setting_configtext(
        'local_backend_sync/tenant_id',
        get_string('tenant_id', 'local_backend_sync'),
        get_string('tenant_id_desc', 'local_backend_sync'),
        'default',
        PARAM_TEXT
    ));

    // Enable/Disable sync
    $settings->add(new admin_setting_configcheckbox(
        'local_backend_sync/enable_sync',
        get_string('enable_sync', 'local_backend_sync'),
        get_string('enable_sync_desc', 'local_backend_sync'),
        1
    ));

    // Retry attempts
    $settings->add(new admin_setting_configtext(
        'local_backend_sync/retry_attempts',
        get_string('retry_attempts', 'local_backend_sync'),
        get_string('retry_attempts_desc', 'local_backend_sync'),
        '3',
        PARAM_INT
    ));

    // Timeout (seconds)
    $settings->add(new admin_setting_configtext(
        'local_backend_sync/timeout',
        get_string('timeout', 'local_backend_sync'),
        get_string('timeout_desc', 'local_backend_sync'),
        '10',
        PARAM_INT
    ));

    // Debug mode
    $settings->add(new admin_setting_configcheckbox(
        'local_backend_sync/debug_mode',
        get_string('debug_mode', 'local_backend_sync'),
        get_string('debug_mode_desc', 'local_backend_sync'),
        0
    ));

    $ADMIN->add('localplugins', $settings);
}
```

**الغرض:** إعدادات الـ plugin التي يمكن للمدير تعديلها من لوحة التحكم

---

### 3. db/events.php

```php
<?php
/**
 * Event observers configuration
 * 
 * @package    local_backend_sync
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    // User Created Event
    [
        'eventname' => '\core\event\user_created',
        'callback'  => 'local_backend_sync_observer::user_created',
        'internal'  => false,
        'priority'  => 100,
    ],
    
    // User Updated Event
    [
        'eventname' => '\core\event\user_updated',
        'callback'  => 'local_backend_sync_observer::user_updated',
        'internal'  => false,
        'priority'  => 100,
    ],
    
    // User Enrolled Event
    [
        'eventname' => '\core\event\user_enrolment_created',
        'callback'  => 'local_backend_sync_observer::user_enrolled',
        'internal'  => false,
        'priority'  => 100,
    ],
    
    // Grade Updated Event
    [
        'eventname' => '\core\event\user_graded',
        'callback'  => 'local_backend_sync_observer::grade_updated',
        'internal'  => false,
        'priority'  => 100,
    ],
];
```

**الغرض:** تعريف الأحداث التي نريد مراقبتها والـ callback functions

**المعاملات:**
- `eventname`: اسم الحدث في Moodle
- `callback`: الدالة التي تُنفذ عند حدوث الحدث
- `internal`: false = يعمل في scheduled tasks أيضاً
- `priority`: أولوية التنفيذ (أعلى رقم = أولوية أقل)

---

### 4. classes/observer.php (الملف الرئيسي)

```php
<?php
/**
 * Event observers for Backend sync
 * 
 * @package    local_backend_sync
 */

namespace local_backend_sync;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/backend_sync/classes/webhook_sender.php');
require_once($CFG->dirroot . '/local/backend_sync/classes/data_extractor.php');

class observer {

    /**
     * Handle user created event
     * 
     * @param \core\event\user_created $event
     */
    public static function user_created(\core\event\user_created $event) {
        global $CFG;
        
        // Check if sync is enabled
        if (!get_config('local_backend_sync', 'enable_sync')) {
            return;
        }

        try {
            // Extract user data
            $extractor = new data_extractor();
            $userdata = $extractor->extract_user_data($event->relateduserid);
            
            if (!$userdata) {
                self::log_error('user_created', "Failed to extract user data for user ID: {$event->relateduserid}");
                return;
            }

            // Add event metadata
            $payload = array_merge([
                'eventname' => $event->eventname,
            ], $userdata);

            // Send webhook
            $sender = new webhook_sender();
            $result = $sender->send('user_created', $payload);

            if ($result['success']) {
                self::log_debug('user_created', "Successfully sent user_created webhook for user ID: {$event->relateduserid}");
            } else {
                self::log_error('user_created', "Failed to send webhook: " . $result['error']);
                // Queue for retry
                self::queue_retry('user_created', $payload);
            }

        } catch (\Exception $e) {
            self::log_error('user_created', "Exception: " . $e->getMessage());
        }
    }

    /**
     * Handle user updated event
     * 
     * @param \core\event\user_updated $event
     */
    public static function user_updated(\core\event\user_updated $event) {
        // Check if sync is enabled
        if (!get_config('local_backend_sync', 'enable_sync')) {
            return;
        }

        try {
            $extractor = new data_extractor();
            $userdata = $extractor->extract_user_data($event->relateduserid);
            
            if (!$userdata) {
                return;
            }

            $payload = array_merge([
                'eventname' => $event->eventname,
            ], $userdata);

            $sender = new webhook_sender();
            $result = $sender->send('user_updated', $payload);

            if (!$result['success']) {
                self::queue_retry('user_updated', $payload);
            }

        } catch (\Exception $e) {
            self::log_error('user_updated', "Exception: " . $e->getMessage());
        }
    }

    /**
     * Handle user enrolled event
     * 
     * @param \core\event\user_enrolment_created $event
     */
    public static function user_enrolled(\core\event\user_enrolment_created $event) {
        if (!get_config('local_backend_sync', 'enable_sync')) {
            return;
        }

        try {
            $extractor = new data_extractor();
            $enroldata = $extractor->extract_enrollment_data($event->objectid);
            
            if (!$enroldata) {
                return;
            }

            $payload = array_merge([
                'eventname' => $event->eventname,
            ], $enroldata);

            $sender = new webhook_sender();
            $result = $sender->send('user_enrolled', $payload);

            if (!$result['success']) {
                self::queue_retry('user_enrolled', $payload);
            }

        } catch (\Exception $e) {
            self::log_error('user_enrolled', "Exception: " . $e->getMessage());
        }
    }

    /**
     * Handle grade updated event
     * 
     * @param \core\event\user_graded $event
     */
    public static function grade_updated(\core\event\user_graded $event) {
        if (!get_config('local_backend_sync', 'enable_sync')) {
            return;
        }

        try {
            $extractor = new data_extractor();
            $gradedata = $extractor->extract_grade_data($event->objectid);
            
            if (!$gradedata) {
                return;
            }

            $payload = array_merge([
                'eventname' => $event->eventname,
            ], $gradedata);

            $sender = new webhook_sender();
            $result = $sender->send('grade_updated', $payload);

            if (!$result['success']) {
                self::queue_retry('grade_updated', $payload);
            }

        } catch (\Exception $e) {
            self::log_error('grade_updated', "Exception: " . $e->getMessage());
        }
    }

    /**
     * Queue failed webhook for retry
     */
    private static function queue_retry($event_type, $payload) {
        global $DB;
        
        try {
            $record = new \stdClass();
            $record->event_type = $event_type;
            $record->payload = json_encode($payload);
            $record->attempts = 0;
            $record->max_attempts = get_config('local_backend_sync', 'retry_attempts') ?: 3;
            $record->next_retry = time();
            $record->created_at = time();
            
            $DB->insert_record('local_backend_sync_queue', $record);
            
        } catch (\Exception $e) {
            self::log_error('queue_retry', "Failed to queue retry: " . $e->getMessage());
        }
    }

    /**
     * Log debug message
     */
    private static function log_debug($context, $message) {
        if (get_config('local_backend_sync', 'debug_mode')) {
            error_log("Backend Sync [{$context}]: {$message}");
        }
    }

    /**
     * Log error message
     */
    private static function log_error($context, $message) {
        error_log("Backend Sync ERROR [{$context}]: {$message}");
    }
}
```

**الغرض:** معالجة الأحداث وإرسال webhooks

---

### 5. classes/data_extractor.php (جديد)

```php
<?php
/**
 * Extract data from Moodle database for webhooks
 * 
 * @package    local_backend_sync
 */

namespace local_backend_sync;

defined('MOODLE_INTERNAL') || die();

class data_extractor {

    /**
     * Extract user data from Moodle
     * 
     * @param int $userid Moodle user ID
     * @return array|false User data or false on error
     */
    public function extract_user_data($userid) {
        global $DB;

        try {
            $user = $DB->get_record('user', ['id' => $userid]);
            
            if (!$user) {
                return false;
            }

            // Build data array matching Backend API expectations
            return [
                'userid' => (int)$user->id,
                'username' => $user->username,
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'email' => $user->email,
                'idnumber' => $user->idnumber ?: '',
                'phone1' => $user->phone1 ?: '',
                'city' => $user->city ?: '',
                'country' => $user->country ?: '',
                'suspended' => (bool)$user->suspended,
                'deleted' => (bool)$user->deleted,
                'timecreated' => (int)$user->timecreated,
                'timemodified' => (int)$user->timemodified,
            ];

        } catch (\Exception $e) {
            error_log("Backend Sync: Failed to extract user data - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Extract enrollment data from Moodle
     * 
     * @param int $enrolmentid User enrollment ID
     * @return array|false Enrollment data or false on error
     */
    public function extract_enrollment_data($enrolmentid) {
        global $DB;

        try {
            // Get enrollment record
            $enrolment = $DB->get_record('user_enrolments', ['id' => $enrolmentid]);
            
            if (!$enrolment) {
                return false;
            }

            // Get enrol instance to get course ID
            $enrol = $DB->get_record('enrol', ['id' => $enrolment->enrolid]);
            
            if (!$enrol) {
                return false;
            }

            // Build data array
            return [
                'enrollmentid' => (int)$enrolment->id,
                'userid' => (int)$enrolment->userid,
                'courseid' => (int)$enrol->courseid,
                'roleid' => 5, // Student role (default)
                'status' => (int)$enrolment->status,
                'timestart' => (int)$enrolment->timestart,
                'timeend' => $enrolment->timeend ? (int)$enrolment->timeend : null,
                'timecreated' => (int)$enrolment->timecreated,
            ];

        } catch (\Exception $e) {
            error_log("Backend Sync: Failed to extract enrollment data - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Extract grade data from Moodle
     * 
     * @param int $gradeid Grade ID
     * @return array|false Grade data or false on error
     */
    public function extract_grade_data($gradeid) {
        global $DB;

        try {
            // Get grade record
            $grade = $DB->get_record('grade_grades', ['id' => $gradeid]);
            
            if (!$grade) {
                return false;
            }

            // Get grade item for item name
            $grade_item = $DB->get_record('grade_items', ['id' => $grade->itemid]);

            // Build data array
            return [
                'gradeid' => (int)$grade->id,
                'userid' => (int)$grade->userid,
                'itemid' => (int)$grade->itemid,
                'itemname' => $grade_item ? $grade_item->itemname : '',
                'finalgrade' => $grade->finalgrade !== null ? (float)$grade->finalgrade : null,
                'feedback' => $grade->feedback ?: '',
                'grader' => $grade->usermodified ? (int)$grade->usermodified : null,
                'timecreated' => (int)$grade->timecreated,
                'timemodified' => (int)$grade->timemodified,
            ];

        } catch (\Exception $e) {
            error_log("Backend Sync: Failed to extract grade data - " . $e->getMessage());
            return false;
        }
    }
}
```

**الغرض:** استخراج البيانات من قاعدة Moodle وتنسيقها بالشكل المطلوب للـ API

---

### 6. classes/webhook_sender.php (جديد)

```php
<?php
/**
 * Send webhook requests to Backend API
 * 
 * @package    local_backend_sync
 */

namespace local_backend_sync;

defined('MOODLE_INTERNAL') || die();

class webhook_sender {

    private $backend_url;
    private $api_token;
    private $tenant_id;
    private $timeout;

    public function __construct() {
        $this->backend_url = get_config('local_backend_sync', 'backend_url') ?: 'http://localhost:8001';
        $this->api_token = get_config('local_backend_sync', 'api_token') ?: '';
        $this->tenant_id = get_config('local_backend_sync', 'tenant_id') ?: 'default';
        $this->timeout = get_config('local_backend_sync', 'timeout') ?: 10;
    }

    /**
     * Send webhook to backend
     * 
     * @param string $endpoint Endpoint name (user_created, user_updated, etc.)
     * @param array $data Payload data
     * @return array Result with 'success' and optional 'error'
     */
    public function send($endpoint, $data) {
        // Build full URL
        $url = rtrim($this->backend_url, '/') . '/api/v1/events/moodle/' . $endpoint;

        // Prepare headers
        $headers = [
            'Content-Type: application/json',
            'X-Moodle-Token: ' . $this->api_token,
            'X-Tenant-ID: ' . $this->tenant_id,
        ];

        // Prepare JSON payload
        $json_data = json_encode($data);

        if ($json_data === false) {
            return [
                'success' => false,
                'error' => 'Failed to encode JSON: ' . json_last_error_msg()
            ];
        }

        // Initialize cURL
        $ch = curl_init($url);
        
        if ($ch === false) {
            return [
                'success' => false,
                'error' => 'Failed to initialize cURL'
            ];
        }

        // Set cURL options
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json_data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        // Execute request
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        
        curl_close($ch);

        // Check for cURL errors
        if ($response === false) {
            return [
                'success' => false,
                'error' => 'cURL error: ' . $curl_error
            ];
        }

        // Check HTTP status code
        if ($http_code !== 200) {
            return [
                'success' => false,
                'error' => "HTTP {$http_code}: {$response}",
                'http_code' => $http_code
            ];
        }

        // Parse response
        $response_data = json_decode($response, true);

        if ($response_data === null) {
            return [
                'success' => false,
                'error' => 'Invalid JSON response: ' . json_last_error_msg()
            ];
        }

        return [
            'success' => true,
            'response' => $response_data,
            'http_code' => $http_code
        ];
    }

    /**
     * Test connection to backend
     * 
     * @return array Result with 'success' and optional 'error'
     */
    public function test_connection() {
        $url = rtrim($this->backend_url, '/') . '/api/v1/health';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $http_code !== 200) {
            return [
                'success' => false,
                'error' => "Failed to connect (HTTP {$http_code})"
            ];
        }

        return ['success' => true];
    }
}
```

**الغرض:** إرسال HTTP POST requests إلى Backend API مع معالجة الأخطاء

---

### 7. lang/en/local_backend_sync.php

```php
<?php
/**
 * English language strings
 * 
 * @package    local_backend_sync
 */

$string['pluginname'] = 'Backend Sync';

// Settings page
$string['backend_url'] = 'Backend API URL';
$string['backend_url_desc'] = 'Full URL of the Backend API server (e.g., http://localhost:8001 or https://api.example.com)';

$string['api_token'] = 'API Token';
$string['api_token_desc'] = 'Authentication token for securing webhook requests';

$string['tenant_id'] = 'Tenant ID';
$string['tenant_id_desc'] = 'Tenant identifier for multi-tenant setups (default: "default")';

$string['enable_sync'] = 'Enable Sync';
$string['enable_sync_desc'] = 'Enable/disable automatic syncing of events to Backend';

$string['retry_attempts'] = 'Retry Attempts';
$string['retry_attempts_desc'] = 'Number of times to retry failed webhook requests';

$string['timeout'] = 'Request Timeout';
$string['timeout_desc'] = 'HTTP request timeout in seconds';

$string['debug_mode'] = 'Debug Mode';
$string['debug_mode_desc'] = 'Enable detailed logging for troubleshooting';

// Task strings
$string['task_retry_webhooks'] = 'Retry failed webhook requests';
```

**الغرض:** الترجمات والنصوص المعروضة في الواجهة

---

### 8. lib.php (اختياري - للوظائف المساعدة)

```php
<?php
/**
 * Library functions
 * 
 * @package    local_backend_sync
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add link to admin menu
 */
function local_backend_sync_extend_navigation_user_settings($navigation, $user, $usercontext, $course, $coursecontext) {
    // Can add custom menu items here if needed
}

/**
 * Hook called when plugin is uninstalled
 */
function local_backend_sync_uninstall() {
    // Cleanup code if needed
    return true;
}
```

---

## 🔄 تدفق البيانات التفصيلي

### السيناريو 1: إنشاء مستخدم جديد

```
1. المستخدم يتم إنشاؤه في Moodle (يدوياً أو عن طريق Import)
   ↓
2. Moodle يُطلق event: \core\event\user_created
   ↓
3. Observer يستقبل الحدث: observer::user_created()
   ↓
4. يتحقق من enable_sync = true
   ↓
5. data_extractor يستخرج بيانات المستخدم من mdl_user
   ↓
6. webhook_sender يرسل POST request إلى:
   URL: http://backend/api/v1/events/moodle/user_created
   Headers:
     - Content-Type: application/json
     - X-Moodle-Token: [token]
     - X-Tenant-ID: default
   Body: {user data JSON}
   ↓
7. Backend API يستقبل ويعالج:
   - يتحقق من البيانات (Pydantic validation)
   - يتحقق إذا المستخدم موجود
   - يُنشئ record جديد في students table
   ↓
8. Backend يُرجع response:
   {
     "success": true,
     "message": "User created successfully",
     "event_id": "uuid",
     "timestamp": "2026-01-26T..."
   }
   ↓
9. Observer يسجل النجاح في logs
```

### السيناريو 2: فشل الـ Webhook

```
1. webhook_sender يحاول إرسال request
   ↓
2. فشل (Timeout / Network error / Backend down)
   ↓
3. observer::queue_retry() يضيف السجل إلى mdl_local_backend_sync_queue:
   {
     event_type: "user_created",
     payload: "{...}",
     attempts: 0,
     max_attempts: 3,
     next_retry: timestamp,
     created_at: timestamp
   }
   ↓
4. Scheduled task يعمل كل 5 دقائق
   ↓
5. يحاول إعادة إرسال الـ webhooks الفاشلة
   ↓
6. إذا نجح → يحذف السجل من الـ queue
7. إذا فشل → يزيد attempts++
8. إذا attempts >= max_attempts → يُعلم المدير (log error)
```

---

## 🔐 الأمان والمصادقة

### 1. API Token Authentication

```php
// في webhook_sender.php
$headers = [
    'X-Moodle-Token: ' . $this->api_token,
];
```

```python
# في Backend API
x_moodle_token: Optional[str] = Header(None)

# يمكن التحقق من الـ token
if x_moodle_token != expected_token:
    raise HTTPException(status_code=401, detail="Invalid token")
```

### 2. SSL/TLS للـ Production

```php
// في settings.php
Backend URL: https://api.yourdomain.com  // استخدام HTTPS

// في webhook_sender.php
CURLOPT_SSL_VERIFYPEER => true,  // التحقق من SSL certificate
CURLOPT_SSL_VERIFYHOST => 2,     // التحقق من hostname
```

### 3. IP Whitelist (في Backend)

```python
# يمكن إضافة middleware في Backend للتحقق من IP
allowed_ips = ["203.0.113.5", "203.0.113.6"]  # Moodle server IPs

if request.client.host not in allowed_ips:
    raise HTTPException(status_code=403)
```

### 4. Request Signing (HMAC) - مستقبلاً

```php
// في Moodle: توقيع الـ request
$signature = hash_hmac('sha256', $json_data, $secret_key);
$headers[] = 'X-Signature: ' . $signature;
```

```python
# في Backend: التحقق من التوقيع
import hmac
import hashlib

def verify_signature(body: bytes, signature: str, secret: str):
    expected = hmac.new(secret.encode(), body, hashlib.sha256).hexdigest()
    return hmac.compare_digest(expected, signature)
```

---

## 📋 خطة التنفيذ

### المرحلة 1: الإعداد الأساسي (يوم 1)

✅ **الخطوة 1:** إنشاء هيكل المجلدات
```bash
cd /path/to/moodle/local/
mkdir backend_sync
cd backend_sync
mkdir db classes lang lang/en
```

✅ **الخطوة 2:** إنشاء الملفات الأساسية
- version.php
- settings.php
- db/events.php
- lang/en/local_backend_sync.php

✅ **الخطوة 3:** تثبيت الـ Plugin
- Site administration → Notifications → Upgrade Moodle database

---

### المرحلة 2: تطوير الـ Classes (يوم 2-3)

✅ **الخطوة 4:** إنشاء data_extractor.php
- وظيفة extract_user_data()
- وظيفة extract_enrollment_data()
- وظيفة extract_grade_data()

✅ **الخطوة 5:** إنشاء webhook_sender.php
- وظيفة send()
- وظيفة test_connection()
- معالجة الأخطاء

✅ **الخطوة 6:** إنشاء observer.php
- user_created()
- user_updated()
- user_enrolled()
- grade_updated()

---

### المرحلة 3: الاختبار (يوم 4)

✅ **الخطوة 7:** اختبار كل حدث
```
1. إنشاء مستخدم جديد → تحقق من logs Backend
2. تعديل ملف مستخدم → تحقق من webhook
3. تسجيل مستخدم في كورس → تحقق
4. إدخال درجة → تحقق
```

✅ **الخطوة 8:** اختبار معالجة الأخطاء
- إيقاف Backend → تحقق من retry queue
- Token خاطئ → تحقق من error logs

---

### المرحلة 4: التحسينات (يوم 5)

✅ **الخطوة 9:** Retry mechanism
- إنشاء جدول mdl_local_backend_sync_queue
- Scheduled task للـ retry

✅ **الخطوة 10:** Dashboard/Monitoring
- صفحة لعرض الإحصائيات
- Webhooks sent/failed
- Recent errors

---

## 📊 Database Schema (اختياري)

إذا أردنا تتبع الـ webhooks والأخطاء:

```sql
CREATE TABLE mdl_local_backend_sync_queue (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL,
    payload LONGTEXT NOT NULL,
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    last_error LONGTEXT,
    next_retry BIGINT NOT NULL,
    created_at BIGINT NOT NULL,
    updated_at BIGINT,
    INDEX idx_next_retry (next_retry),
    INDEX idx_event_type (event_type)
);

CREATE TABLE mdl_local_backend_sync_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL,
    user_id BIGINT,
    status VARCHAR(20) NOT NULL,  -- success, failed, retry
    http_code INT,
    response TEXT,
    error TEXT,
    created_at BIGINT NOT NULL,
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);
```

---

## 🎯 ملخص المعمارية

### Components:

1. **Observer** - يستمع للأحداث
2. **Data Extractor** - يستخرج البيانات من Moodle DB
3. **Webhook Sender** - يرسل HTTP requests
4. **Retry Queue** - يعيد محاولة الفاشلة
5. **Settings Page** - الإعدادات
6. **Logs** - التتبع والمراقبة

### Data Flow:

```
Moodle Event → Observer → Extractor → Sender → Backend API → Database → Zoho
                                         ↓
                                   Failed? → Retry Queue
```

### Configuration:

- Backend URL
- API Token
- Tenant ID
- Retry attempts
- Timeout
- Debug mode

---

## ✅ Next Steps

1. **إنشاء الملفات الأساسية** (version.php, settings.php, events.php)
2. **تطوير data_extractor.php** (أهم ملف)
3. **تطوير webhook_sender.php**
4. **تطوير observer.php**
5. **الاختبار مع Backend**
6. **إضافة Retry mechanism**
7. **التوثيق والتدريب**

</div>

---

## English Summary

### Plugin Architecture Overview

**Purpose:** Send real-time events from Moodle to Backend API

**Components:**
1. **Observer** - Event listeners (4 events)
2. **Data Extractor** - Extract data from Moodle DB
3. **Webhook Sender** - Send HTTP POST requests
4. **Retry Queue** - Retry failed webhooks
5. **Settings Page** - Admin configuration

**Events Monitored:**
- User created
- User updated
- User enrolled
- Grade updated

**Technology Stack:**
- PHP 7.4+
- Moodle 3.11+
- cURL for HTTP requests
- JSON for data exchange

**Security:**
- API Token authentication
- HTTPS support
- Request timeout
- Error handling

**Next Phase:** Implementation (5 days)
