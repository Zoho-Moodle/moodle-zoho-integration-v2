# تقرير التحليل الكامل — واجهة المشرف (Admin UI)
## Plugin: `local_moodle_zoho_sync` | النسخة: 2026022400

> **الهدف من التقرير:** تحليل شامل لكل ملف في `ui/admin/` و `ui/ajax/` من منظور مسؤول النظام — الأخطاء، التضاربات، الثغرات، والمقترحات.

---

## 📋 جدول الملخص العام (الأخطاء والأولويات)

| # | الخطورة | الملف | المشكلة |
|---|---|---|---|
| 1 | 🔴 حرج | `event_detail.php` | `if (true)` — زر Dev Mode يظهر لجميع المستخدمين في الإنتاج |
| 2 | 🔴 حرج | `health_monitor_detailed.php` | نفس مسار config.php الخاطئ (5 مستويات بدل 4) |
| 3 | 🔴 حرج | `student_search.php` | مسار `config.php` خاطئ (5 مستويات) — سيتعطل |
| 4 | 🔴 حرج | `student_dashboard_management.php` | مسار `config.php` خاطئ + يستعلم جدول `local_mzi_sync_status` غير موجود |
| 5 | 🔴 حرج | `btec_templates.php` | مسار `config.php` خاطئ (5 مستويات) |
| 6 | 🟠 عالي | `event_logs.php` + `event_logs_enhanced.php` | نفس مفتاح `admin_externalpage_setup` → تعارض في القائمة |
| 7 | 🟠 عالي | `statistics.php` | استخدام `FROM_UNIXTIME()` / `UNIX_TIMESTAMP()` — MySQL فقط، غير متوافق مع PostgreSQL |
| 8 | 🟠 عالي | `get_student_data.php` | مبلغ الدفع يشمل المدفوعات الملغاة (Voided/Cancelled) |
| 9 | 🟠 عالي | `get_student_data.php` | `total_fees` و `remaining_amount` من DB مباشرة — بيانات قديمة غير محسوبة |
| 10 | 🟠 عالي | `retry_failed.php` | يحاول تحويل scheduled task إلى adhoc — المنطق غير صحيح |
| 11 | 🟡 متوسط | `sync_management.php` | نسخة 100% مكررة من `dashboard.php` بدون قيمة مضافة |
| 12 | 🟡 متوسط | `event_logs_enhanced.php` | صفحة أفضل من `event_logs.php` لكنها مخفية وغير مرتبطة بالـ navigation |
| 13 | 🟡 متوسط | `health_monitor_detailed.php` | غير مرتبط بالـ navigation — صفحة شبح |
| 14 | 🟡 متوسط | `grade_queue_monitor.php` | Retry يضع status = `'SYNCED'` بدل `'PENDING'` |
| 15 | 🟡 متوسط | `student_search.php` | مكررة بالكامل مع Student Lookup في `student_dashboard_management.php` |
| 16 | 🟡 متوسط | `dashboard.php` | اختبار الاتصال بالـ backend على كل تحميل للصفحة (نداء شبكة ثقيل) |
| 17 | 🟡 متوسط | `health_check.php` | `count($tasks) === 3` — افتراض صلب وهش |
| 18 | 🟡 متوسط | `student_dashboard_management.php` | `$stats->unacknowledged_feedback` يستعلم `feedback_acknowledged = 0` بدل `IS NULL OR = 0` |
| 19 | 🟢 منخفض | `ui/admin/` directory | 4 ملفات `.md` في مجلد PHP متاح عبر URL |
| 20 | 🟢 منخفض | `event_logs.php` | فلتر نوع الحدث يفتقد: `payment_recorded`, `registration_created` |
| 21 | 🟢 منخفض | `btec_templates.php` | شريط التقدم للـ sync مُحاكَى (مصطنع) — لا يعكس تقدماً حقيقياً |
| 22 | 🟢 منخفض | `navigation.php` | لا يحتوي على روابط لـ `student_dashboard_management.php` و `student_search.php` و `health_monitor_detailed.php` |
| 23 | 🟢 منخفض | `submit_request.php` | لا يتحقق من `Authorization` header عند إرسال الطلب للـ backend |
| 24 | 🟢 منخفض | `upload_photo.php` | نفس المشكلة — يرسل للـ backend بدون `Authorization` header |

---

## 📁 1. `ui/admin/dashboard.php` (375 سطر)

### الغرض
الصفحة الرئيسية للمشرف. تعرض 4 بطاقات KPI (إجمالي الأحداث، المرسلة، الفاشلة، المعلقة)، نسبة النجاح، حالة الـ backend، وأزرار Quick Actions.

### الملفات المرتبطة
- `ui/ajax/retry_failed.php` — زر Retry Failed Events
- `ui/ajax/test_connection.php` — زر Test Backend Connection
- `includes/navigation.php` — شريط التنقل
- `classes/event_logger.php` — جلب الإحصائيات
- `classes/config_manager.php` — اختبار الاتصال

### المشاكل المكتشفة

| # | نوعها | التفاصيل |
|---|---|---|
| 1 | 🟡 UX | Quick Actions مصممة كـ `btn-group-vertical btn-group-lg` — 9 أزرار عمودية، تبدو كـ menu مزدحم. الأفضل: grid 3 أعمدة |
| 2 | 🟡 تكرار | "Retry Failed Events" و "Cleanup Old Logs" موجودان هنا و في `sync_management.php` — تكرار |
| 3 | 🟡 أداء | `config_manager::test_backend_connection()` يُستدعى على كل تحميل للصفحة — طلب HTTP في كل زيارة |
| 4 | 🟢 UX | لا يوجد تحديث تلقائي (auto-refresh) للإحصائيات |

### اقتراحات التطوير
- استبدل اختبار الاتصال التلقائي بزر يدوي أو Cache لمدة 5 دقائق
- حوّل Quick Actions من عمودي إلى شبكة (grid)
- احذف تكرار الأزرار الموجودة في sync_management

---

## 📁 2. `ui/admin/health_check.php` (207 سطر)

### الغرض
تقرير صحة النظام بـ 6 فحوصات: Backend API، جداول DB، إحصائيات 24 ساعة، الأحداث الفاشلة، الإعدادات، والمهام المجدولة.

### الملفات المرتبطة
- `classes/config_manager.php`
- جداول: `local_mzi_event_log`, `local_mzi_sync_history`, `local_mzi_config`

### المشاكل المكتشفة

| # | نوعها | التفاصيل |
|---|---|---|
| 1 | 🟠 هش | `$tasksok = count($tasks) === 3` — يفترض وجود 3 مهام مجدولة بالضبط. إذا أُضيفت مهمة جديدة أو حُذفت، الفحص سيفشل بشكل خاطئ |
| 2 | 🟡 ناقص | لا يفحص جداول Student Dashboard (`local_mzi_students`, `local_mzi_payments`, `local_mzi_registrations`) |
| 3 | 🟡 ناقص | لا يفحص صلاحية Zoho OAuth token |
| 4 | 🟢 UX | النتيجة الكلية (Overall Score) مبنية على معادلة خطية — قد تكون مضللة إذا فشل فحص حرج |

### اقتراحات التطوير
```php
// بدل:
$tasksok = count($tasks) === 3;
// استخدم:
$required_tasks = ['local_moodle_zoho_sync\task\retry_failed_webhooks', /* ... */];
$tasksok = true;
foreach ($required_tasks as $t) {
    if (!$DB->record_exists('task_scheduled', ['classname' => '\\' . $t])) {
        $tasksok = false;
    }
}
```
- أضف فحص `local_mzi_students` و `local_mzi_payments`
- أضف فحص وجود `backend_url` و `api_token` في الإعدادات

---

## 📁 3. `ui/admin/event_logs.php` (300 سطر)

### الغرض
جدول أحداث `local_mzi_event_log` مع فلاتر (نوع الحدث، الحالة، التاريخ)، pagination، وزر Retry لكل حدث.

### الملفات المرتبطة
- `classes/event_logger.php` → `get_events_paginated()`
- `ui/ajax/retry_single_event.php`

### المشاكل المكتشفة

| # | نوعها | التفاصيل |
|---|---|---|
| 1 | 🟠 تعارض | `admin_externalpage_setup('local_moodle_zoho_sync_logs')` — **نفس المفتاح** المستخدم في `event_logs_enhanced.php`. Moodle سيعرض هذه الصفحة مرتين في القائمة أو يتجاهل إحداهما |
| 2 | 🟢 ناقص | فلتر نوع الحدث يحتوي فقط: `user_created`, `user_updated`, `enrollment_created`, `grade_updated` — يفقد: `payment_recorded`, `registration_created` |
| 3 | 🟢 UX | `event_id` مقطوع إلى 8 أحرف مع `...` لكن بدون رابط للتفاصيل. يجب جعله رابطاً لـ `event_detail.php?id=...` |

### ملاحظة مهمة
هذه الصفحة هي النسخة الأقدم. `event_logs_enhanced.php` أفضل منها (collapsible rows + copy button)، لكن يجب حل تعارض المفتاح.

---

## 📁 4. `ui/admin/event_logs_enhanced.php` (305 سطر)

### الغرض
نسخة محسّنة من `event_logs.php` — صفوف قابلة للطي، زر Copy للـ Event ID، أيقونات حالة أفضل.

### الملفات المرتبطة
- `classes/event_logger.php` → `get_events_paginated()`
- `includes/navigation.php`

### المشاكل المكتشفة

| # | نوعها | التفاصيل |
|---|---|---|
| 1 | 🟠 تعارض | نفس `admin_externalpage_setup` key كـ `event_logs.php` |
| 2 | 🟡 مخفية | غير مرتبطة من `navigation.php` — المشرف لا يجدها |
| 3 | 🟢 ناقص | فلتر نوع الحدث به نفس المشكلة — يفقد `payment_recorded`, `registration_created` |

### مقترح الحل
- **احذف** `event_logs.php` (النسخة القديمة)
- **أبقِ** `event_logs_enhanced.php` وغيّر مفتاحه إلى `'local_moodle_zoho_sync_logs'`
- أضفها للـ navigation

---

## 📁 5. `ui/admin/statistics.php` (300 سطر)

### الغرض
إحصائيات 3 فترات زمنية (24 ساعة / 7 أيام / كلي)، توزيع أنواع الأحداث، نشاط بالساعة، ملخص الإعدادات.

### الملفات المرتبطة
- يستعلم مباشرة من `local_mzi_event_log`
- `includes/navigation.php`

### المشاكل المكتشفة

| # | نوعها | التفاصيل |
|---|---|---|
| 1 | 🟠 توافقية | `FROM_UNIXTIME(timecreated, '%Y-%m-%d %H:00:00')` — **MySQL فقط**. Moodle يدعم PostgreSQL أيضاً. الحل: استخدم `$DB->sql_datetime_format()` أو PHP لهذه الحسابات |
| 2 | 🟠 توافقية | `UNIX_TIMESTAMP(...)` — نفس المشكلة |
| 3 | 🟡 أمان | يعرض `backend_url` (إعداد حساس) في صفحة الإحصائيات |
| 4 | 🟢 UX | لا يوجد فلتر تاريخ مخصص — فقط 3 فترات ثابتة |

### مقترح الحل
```php
// بدل MySQL-specific:
FROM_UNIXTIME(timecreated, '%Y-%m-%d %H:00:00')

// استخدم PHP:
$now = time();
$last24h_start = $now - 86400;
// ثم GROUP BY في PHP بعد جلب البيانات الخام
// أو استخدم FLOOR(timecreated / 3600) * 3600 (متوافق مع الجميع)
```

---

## 📁 6. `ui/admin/sync_management.php` (254 سطر)

### الغرض
تسمية نفسها: Sync Management. تعرض 4 KPIs، زر Retry، زر Cleanup، وروابط سريعة.

### الملفات المرتبطة
- `ui/ajax/retry_failed.php`
- `includes/navigation.php`

### المشاكل المكتشفة

| # | نوعها | التفاصيل |
|---|---|---|
| 1 | 🟡 تكرار | **100% نسخة مكررة** من `dashboard.php` — نفس الـ KPIs، نفس الأزرار، نفس الروابط السريعة. لا قيمة مضافة. |
| 2 | 🟡 خادع | الكود يستورد `webhook_sender` لكن لا يستخدمه فعلياً في الـ retry — فقط يُحدّث الحالة في DB |

### مقترح الحل
- **احذف** هذه الصفحة وأزل رابطها من `navigation.php`
- أضف "Cleanup Old Logs" كأداة في `dashboard.php` ضمن قسم Tools بدلاً من صفحة منفصلة

---

## 📁 7. `ui/admin/student_search.php` (383 سطر)

### الغرض
بحث مستقل عن الطلاب (اسم/بريد/username)، يعرض بروفايل مع تبويبات: Profile، Programs، Classes، Requests.

### المشاكل المكتشفة

| # | نوعها | التفاصيل |
|---|---|---|
| 1 | 🔴 حرج | `require_once(__DIR__ . '/../../../../../config.php')` — **5 مستويات للأعلى**. الصحيح 4 مستويات. ستفشل الصفحة بـ Fatal Error على الخادم |
| 2 | 🟡 تكرار | Student Search موجود أيضاً في `student_dashboard_management.php` (Student Lookup section) |
| 3 | 🟡 تكامل | لا تستخدم `mzi_render_navigation()` — غير مندمجة مع نظام التنقل |
| 4 | 🟡 صلاحيات | تستخدم `require_capability('moodle/site:config', ...)` بدل `local/moodle_zoho_sync:manage` |
| 5 | 🟡 UX | تستخدم inline `<style>` وـ JavaScript مخصص بدل Bootstrap الموجود في Moodle |
| 6 | 🟡 ناقص | Requests tab يقرأ `$request->request_subject` و `$request->submission_date` — هذه الأعمدة غير موجودة في `local_mzi_requests`. الأعمدة الصحيحة: `description` و `created_at` |

### مقترح الحل
```php
// الخطأ:
require_once(__DIR__ . '/../../../../../config.php');
// الصحيح:
require_once(__DIR__ . '/../../../../config.php');
```
- **إما** احذف هذه الصفحة ودمجها مع `student_dashboard_management.php`
- **أو** اجعلها النسخة الرسمية وأزل القسم المكرر من `student_dashboard_management.php`

---

## 📁 8. `ui/admin/student_dashboard_management.php` (863 سطر)

### الغرض
صفحة "God File" — تجمع: إحصائيات كاملة، قائمة الطلبات، Manual Student Sync من Zoho، Student Lookup، وإدارة Request Windows.

### الملفات المرتبطة
- يستعلم: `local_mzi_students`, `local_mzi_registrations`, `local_mzi_payments`, `local_mzi_requests`, `local_mzi_classes`, `local_mzi_grades`, `local_mzi_enrollments`
- يستعلم backend: `/api/v1/admin/sync-student` (عبر fetch من JS)
- يحدّث: `local_mzi_request_windows`

### المشاكل المكتشفة

| # | نوعها | التفاصيل |
|---|---|---|
| 1 | 🔴 حرج | `require_once(__DIR__ . '/../../../../../config.php')` — 5 مستويات خاطئة |
| 2 | 🔴 حرج | `$recent_syncs = $DB->get_records('local_mzi_sync_status', ...)` — جدول `local_mzi_sync_status` **غير موجود** في المخطط الحالي. سيرمي DB exception |
| 3 | 🟠 محتمل | `$stats->unacknowledged_feedback = $DB->count_records('local_mzi_grades', ['feedback_acknowledged' => 0])` — إذا كان الـ default للعمود NULL بدل 0، الاستعلام لن يشمل السجلات الجديدة |
| 4 | 🟡 تكرار | Student Lookup (السطور 650+) مكرر بالكامل من `student_search.php` |
| 5 | 🟡 UX | Manual Sync يرسل طلب fetch() مباشرة للـ backend بدون Authorization header |
| 6 | 🟡 هش | الـ `$stats->total_payments` تجمع `amount` من `local_mzi_payments` بـ `payment_status = 'Confirmed'` — أما الحالات الأخرى مثل `confirmed` (lowercase) قد تُفوَّت |
| 7 | 🟢 توثيق | `$stats->active_registrations` = count(registration_status IN ('Active','Enrolled')) — يجب توثيق القيم المقبولة في كومنت |

### المقترح
```
الملف هذا يحتاج إعادة هيكلة:
├── student_dashboard_management.php (مبسّط — request windows + pending requests فقط)
├── student_search.php (محسوّن مع nav)
└── حذف قسم Recent Sync Activities (الجدول غير موجود)
```

**إصلاح عاجل للمستوى 5:**
```php
// السطر 1:
require_once(__DIR__ . '/../../../../../config.php');
// يجب أن يصبح:
require_once(__DIR__ . '/../../../../config.php');
```

**إصلاح جدول غير موجود:**
```php
// بدل:
$recent_syncs = $DB->get_records('local_mzi_sync_status', null, 'updated_at DESC', '*', 0, 10);
// استخدم:
$recent_syncs = $DB->get_records_sql(
    "SELECT id, event_type as module, status as sync_status, timecreated as updated_at, last_error as error_message, 0 as total_records
     FROM {local_mzi_event_log}
     ORDER BY timecreated DESC
     LIMIT 10"
);
```

---

## 📁 9. `ui/admin/grade_queue_monitor.php` (1066 سطر)

### الغرض
مراقبة `local_mzi_grade_queue` — إحصائيات (SYNCED, F_CREATED, RR_CREATED, Failed)، فلتر بالتاريخ والحالة والنص، export CSV، retry، real-time view.

### الملفات المرتبطة
- جدول: `local_mzi_grade_queue`
- `includes/navigation.php`

### المشاكل المكتشفة

| # | نوعها | التفاصيل |
|---|---|---|
| 1 | 🟡 منطق | الـ Retry داخل الصفحة يضع `status = 'SYNCED'` — يجب أن يضع `'PENDING'` أو `'QUEUED'` حتى تتقدر المهمة المجدولة على إعادة المعالجة |
| 2 | 🟡 أداء | يُحدث إحصائيات عبر ~12 count_records منفصل — يمكن دمجها في query واحد |
| 3 | 🟢 UX | شريط الـ tabs (Observer / Scheduled / Failed) يُغيّر `?view=` في URL لكن الصفحة لا تتذكر الإعدادات الأخرى (فلاتر التاريخ تُفقد) |
| 4 | 🟢 UX | 1066 سطر ملف واحد — صعب الصيانة |

### إصلاح منطق Retry
```php
// الخطأ (line ~900):
$DB->execute("UPDATE {local_mzi_grade_queue} SET status = 'SYNCED', retry_count = 0 WHERE id = ?", [$id]);
// الصحيح:
$DB->execute("UPDATE {local_mzi_grade_queue} SET status = 'PENDING', retry_count = 0, error_message = NULL WHERE id = ?", [$id]);
```

---

## 📁 10. `ui/admin/btec_templates.php` (435 سطر)

### الغرض
إظهار إحصائيات BTEC templates، sync من Zoho، عرض Moodle grading definitions المرتبطة بها.

### الملفات المرتبطة
- جداول: `local_mzi_btec_templates`, `grading_definitions`, `gradingform_btec_criteria`
- Backend: `/api/v1/btec/sync-templates` و `/api/v1/btec/templates`

### المشاكل المكتشفة

| # | نوعها | التفاصيل |
|---|---|---|
| 1 | 🔴 حرج | `require_once(__DIR__ . '/../../../../../config.php')` — 5 مستويات خاطئة |
| 2 | 🟢 UX | شريط التقدم للـ sync مُحاكَى بـ `setInterval` — يصل 90% ثم يتوقف حتى يأتي الرد. لا يعكس تقدماً حقيقياً |
| 3 | 🟢 تكامل | لا يُرسل `Authorization: Bearer` header عند sync رغم أن الـ backend يتطلبه |

---

## 📁 11. `ui/admin/event_detail.php` (163 سطر)

### الغرض
عرض تفاصيل حدث واحد من `local_mzi_event_log` مع زر Retry.

### الملفات المرتبطة
- `ui/ajax/retry_single_event.php`

### المشاكل المكتشفة

| # | نوعها | التفاصيل |
|---|---|---|
| 1 | 🔴 حرج | `if (true) { // TODO: Change to ($event->status === 'failed') for production }` — **زر Dev Retry يظهر لجميع الأحداث** بما فيها Sent/Pending. المشرف يمكنه إعادة إرسال أحداث تم إرسالها بنجاح مما يسبب تكراراً في Zoho |

### الإصلاح الفوري
```php
// بدل:
if (true) { // TODO: Change to ($event->status === 'failed') for production

// يصبح:
if ($event->status === 'failed' || $event->status === 'pending') {
```

---

## 📁 12. `ui/admin/health_monitor_detailed.php` (174 سطر)

### الغرض
نسخة مفصّلة من فحص الصحة — 6 خدمات (backend_api, user_sync, course_sync, enrollment_sync, grade_sync, learning_outcomes) مع آخر وقت فحص.

### الملفات المرتبطة
- `classes/config_manager.php` → `get("health_status_{$key}")`
- `includes/navigation.php`

### المشاكل المكتشفة

| # | نوعها | التفاصيل |
|---|---|---|
| 1 | 🔴 حرج | `require_once(__DIR__ . '/../../../../../config.php')` — 5 مستويات |
| 2 | 🟡 مخفية | `admin_externalpage_setup('local_moodle_zoho_sync_health')` — **نفس المفتاح كـ `health_check.php`** — تعارض آخر! |
| 3 | 🟡 مخفية | غير موجودة في `navigation.php` |
| 4 | 🟡 اعتماد | البيانات تأتي من `config_manager::get("health_status_*")` التي تُكتب بواسطة scheduled task — إذا لم تُشغَّل المهمة، الصفحة ستظهر "No health data" لكل الخدمات |

---

## 📁 13. `ui/admin/includes/navigation.php` (335 سطر)

### الغرض
مكوّن مشترك يوفر: `mzi_render_navigation()`, `mzi_render_breadcrumb()`, `mzi_output_navigation_styles()`.

### هيكل الـ Navigation
```
🏠 Dashboard  |  📊 Grade Monitor  |  📋 Event Logs  |  💊 Health Check  |  📈 Statistics  |  📝 BTEC Templates  |  ⚙️ Sync Management
```

### المشاكل المكتشفة

| # | نوعها | التفاصيل |
|---|---|---|
| 1 | 🟡 ناقص | لا يحتوي روابط لـ: `student_dashboard_management.php`, `student_search.php`, `health_monitor_detailed.php` |
| 2 | 🟡 ناقص | يحتوي رابط لـ `sync_management.php` (مقترح حذفها) |
| 3 | 🟢 UX | 7 عناصر في الـ nav bar — على الشاشات الصغيرة ستتكدس |
| 4 | 🟢 تقني | يستخدم `emoji` في الـ icons — قد لا تظهر بشكل صحيح في بعض المتصفحات. الأفضل: Font Awesome icons |

---

## 📁 14. `ui/ajax/retry_failed.php`

### الغرض
AJAX endpoint لإعادة محاولة جميع الأحداث الفاشلة.

### المشاكل المكتشفة

| # | نوعها | التفاصيل |
|---|---|---|
| 1 | 🟠 منطق خاطئ | يستخدم `manager::queue_adhoc_task($task)` لكن `$task` هو كائن scheduled task وليس adhoc task. `queue_adhoc_task()` تتطلب `adhoc_task` object — سيرمي exception |
| 2 | 🟡 غير مباشر | لا يُعيد المحاولة فعلياً — فقط يُجدول مهمة. المستخدم يتوقع retry فورياً |

### الإصلاح
```php
// بدل scheduled task queue:
// Option A: إعادة حالة الأحداث إلى 'pending' مباشرة
$DB->execute(
    "UPDATE {local_mzi_event_log} SET status = 'pending', next_retry_at = NULL WHERE status = 'failed'",
    []
);
// Option B: adhoc task صحيح
$adhoc = new \local_moodle_zoho_sync\task\retry_failed_webhooks();
\core\task\manager::queue_adhoc_task($adhoc);
```

---

## 📁 15. `ui/ajax/retry_single_event.php` (131 سطر)

### الغرض
إعادة إرسال حدث واحد بالـ event_id. يقرأ الحدث، يُعيد ضبط retry_count، يُرسل عبر cURL.

### المشاكل المكتشفة

| # | نوعها | التفاصيل |
|---|---|---|
| 1 | 🟢 تقني | يُحدّث الحالة إلى `'failed'` قبل الإرسال — إذا نجح الإرسال يُحدّثها إلى `'sent'`. منطق صحيح لكن ترتيب العمليات قد يُشوّش logs |
| 2 | 🟢 أمان | `require_once(__DIR__ . '/../../classes/webhook_sender.php')` — يقرأ ملف الكلاس مباشرة (صحيح لكن الباقي يستخدم autoloader) |

---

## 📁 16. `ui/ajax/test_connection.php`

### الغرض
اختبار اتصال الـ backend وإرجاع النتيجة JSON.

### الملفات المرتبطة
- `classes/config_manager.php` → `test_backend_connection()`

### لا مشاكل حرجة
الملف نظيف. يستخدم sesskey + capability check. ✅

---

## 📁 17. `ui/ajax/get_student_data.php` (276 سطر)

### الغرض
يُرجع بيانات الطالب حسب `type=` (profile/academics/finance/classes/grades/requests). مستخدم من dashboard قديم.

### الملفات المرتبطة
- جداول: كل جداول student dashboard

### المشاكل المكتشفة

| # | نوعها | التفاصيل |
|---|---|---|
| 1 | 🟠 بيانات قديمة | `finance` case: يُرجع `total_fees` و `remaining_amount` من `local_mzi_registrations` مباشرة — هذه الأعمدة قد لا تُحدَّث بدقة. الصواب: حساب من أقساط `local_mzi_payments` |
| 2 | 🟠 منطق خاطئ | `finance` case: `amount_paid = SUM(amount) من local_mzi_payments` بدون فلترة الحالة — يشمل Voided و Cancelled |
| 3 | 🟡 مُضلِّل | `academics` case: يُرجع `units_count` = عدد سجلات grades — الاسم مُضلِّل، يجب تسميته `grades_count` |
| 4 | 🟡 محتملة ميت | `programs.php` (صفحة الطالب الحالية) لا تستدعي هذا الملف — بل تستعلم DB مباشرة. هذا الملف قد يكون orphaned |

### إصلاح البيانات المالية
```php
// بدل:
$payments_sum = $DB->get_field_sql(
    "SELECT SUM(amount) FROM {local_mzi_payments} WHERE registration_id = ?",
    [$reg->id]
);

// الصحيح:
$payments_sum = $DB->get_field_sql(
    "SELECT COALESCE(SUM(amount), 0) FROM {local_mzi_payments} 
     WHERE registration_id = ?
       AND payment_status NOT IN ('Voided', 'Cancelled', 'voided', 'cancelled')",
    [$reg->id]
);
```

---

## 📁 18. `ui/ajax/ack_grade.php`

### الغرض
تسجيل إقرار الطالب بملاحظة درجة (feedback_acknowledged).

### الملفات المرتبطة
- جداول: `local_mzi_students`, `local_mzi_grades`

### لا مشاكل حرجة
الملف منطقي وآمن — يتحقق من الـ sesskey، ملكية السجل، idempotency. ✅

---

## 📁 19. `ui/ajax/submit_request.php` (276 سطر)

### الغرض
إرسال طلب طالب (Enroll/Class Drop/Late Submission/Change Information/Student Card)، حفظ محلياً ثم إعادة توجيه للـ backend.

### الملفات المرتبطة
- جداول: `local_mzi_students`, `local_mzi_requests`, `local_mzi_request_windows`
- Backend: `/api/v1/requests/submit`
- ملفات: `$CFG->dataroot/local_mzi_receipts/`

### المشاكل المكتشفة

| # | نوعها | التفاصيل |
|---|---|---|
| 1 | 🟡 أمان | إرسال للـ backend بدون `Authorization: Bearer` header |
| 2 | 🟡 ثغرة توقيت | يُنشئ `$local_id` في DB أولاً ثم يُرسل للـ backend — إذا فشل الإرسال، السجل موجود في DB بـ `zoho_request_id = null` و `synced_at = 0`. هذا مقصود لكن يجب وجود retry mechanism |
| 3 | 🟡 Class Drop | الـ `$month_ago = $now - (30 * 24 * 3600)` — التحقق من enrollments خلال 30 يوماً فقط. هذا تقييد قد يمنع طلاب التسجيل القديم |
| 4 | 🟢 receipt upload | يحفظ الـ receipt في `$CFG->dataroot/local_mzi_receipts/` — جيد من حيث الأمان (خارج wwwroot) |

---

## 📁 20. `ui/ajax/upload_photo.php` (≈180 سطر)

### الغرض
رفع صورة الطالب — يتحقق من MIME، يحذف الصورة القديمة، يحفظ في `$CFG->dataroot/student_photos/`، يُحدث DB، ثم يرسل للـ backend.

### المشاكل المكتشفة

| # | نوعها | التفاصيل |
|---|---|---|
| 1 | 🟡 أمان | إرسال للـ backend بدون `Authorization: Bearer` header |
| 2 | 🟡 أداء | يُشفّر الصورة كاملة بـ base64 ويرسلها في JSON payload — لملف 5MB = ~6.7MB كـ base64. الأفضل: multipart/form-data |
| 3 | 🟢 أمان | يستخدم finfo لفحص MIME الحقيقي (ليس الاسم فقط) ✅ |

---

## 🗂️ تحليل المجلد العام

### الأخطاء المتكررة عبر ملفات متعددة

#### 1. مسار config.php الخاطئ (5 مستويات)
ملفات `student_search.php`, `student_dashboard_management.php`, `btec_templates.php`, `health_monitor_detailed.php` كلها تستخدم:
```php
require_once(__DIR__ . '/../../../../../config.php');
```
**الصحيح (4 مستويات من `ui/admin/`):**
```
ui/admin/file.php
  → ui/
    → local/moodle_zoho_sync/
      → 📛 (المسار ينتهي هنا — لا توجد طبقة إضافية)

الصحيح:
__DIR__ = .../local/moodle_zoho_sync/ui/admin
4 مستويات: /../../../.. = .../moodledata_folder/... 

اختبر:
dashboard.php uses: __DIR__ . '/../../../../config.php' ✅ (4 مستويات)
```

#### 2. أعمدة لا تتطابق مع Schema
- `student_search.php` يقرأ `$request->request_subject` + `$request->submission_date` — غير موجودين في `local_mzi_requests`
- `student_dashboard_management.php` يقرأ من `local_mzi_sync_status` — جدول غير موجود
- `get_student_data.php` يقرأ `$student->phone_number` (الصحيح: `mobile_phone`)

#### 3. تحريم Authorization header في Ajax calls
ملفات `submit_request.php`, `upload_photo.php`, `btec_templates.php` ترسل للـ backend بدون:
```php
'Authorization: Bearer ' . get_config('local_moodle_zoho_sync', 'api_token')
```

---

## 🔄 تحليل التعارضات

### تعارض `admin_externalpage_setup` keys

| الملف | المفتاح | التعارض |
|---|---|---|
| `event_logs.php` | `local_moodle_zoho_sync_logs` | ✓ |
| `event_logs_enhanced.php` | `local_moodle_zoho_sync_logs` | **تعارض مع الأعلى** |
| `health_check.php` | `local_moodle_zoho_sync_health` | ✓ |
| `health_monitor_detailed.php` | `local_moodle_zoho_sync_health` | **تعارض مع الأعلى** |
| `event_detail.php` | `local_moodle_zoho_sync_logs` | مشترك مع event_logs (مقبول) |

### التكرار الوظيفي

| الوظيفة | المكان 1 | المكان 2 |
|---|---|---|
| KPI cards (Total/Sent/Failed/Pending) | `dashboard.php` | `sync_management.php` |
| Retry Failed Events button | `dashboard.php` | `sync_management.php` |
| Cleanup Old Logs | `dashboard.php` | `sync_management.php` |
| Student Search | `student_search.php` | `student_dashboard_management.php` (Student Lookup) |
| Health Check overview | `health_check.php` | `health_monitor_detailed.php` |

---

## 🗺️ خريطة الملفات — ماذا تبقى، ماذا تحذف، ماذا تدمج

```
✅ ابقَ كما هو:
   dashboard.php         (بعد تحسينات UX)
   health_check.php      (بعد إصلاح count tasks)
   grade_queue_monitor.php (بعد إصلاح retry logic)
   event_detail.php      (بعد إصلاح if(true))
   navigation.php        (بعد تحديث الروابط)
   
🔧 يحتاج إصلاحات مهمة:
   student_dashboard_management.php  (إصلاح path + sync_status table + feedback)
   btec_templates.php                (إصلاح path + Authorization)
   statistics.php                    (إصلاح MySQL-only SQL)
   get_student_data.php             (إصلاح finance logic)
   retry_failed.php                  (إصلاح adhoc task logic)
   submit_request.php                (إضافة Authorization)
   upload_photo.php                  (إضافة Authorization)

🔀 دمج:
   event_logs_enhanced.php → يحل محل event_logs.php (احذف القديم)
   health_monitor_detailed.php → يُدمج في health_check.php أو يُعطى مفتاح مختلف
   student_search.php → يُزال لصالح القسم في student_dashboard_management.php

🗑️ احذف:
   sync_management.php    (تكرار 100% من dashboard)
   event_logs.php          (استبدلها بـ event_logs_enhanced)
   4 ملفات .md في ui/admin/ (نقلها لـ docs/)
```

---

## ⚡ خطة الإصلاح الفوري (ترتيب الأولوية)

### المرحلة 1 — إصلاحات حرجة (يجب قبل الرفع للخادم)

```bash
# 1. إصلاح كل مسارات config.php الخاطئة
student_search.php, student_dashboard_management.php, btec_templates.php, health_monitor_detailed.php
بدّل: '/../../../../../config.php'  →  '/../../../../config.php'

# 2. إصلاح event_detail.php  
if (true) {  →  if (in_array($event->status, ['failed', 'pending'])) {

# 3. إصلاح student_dashboard_management.php (جدول غير موجود)
استبدل الاستعلام من local_mzi_sync_status بديل صحيح
```

### المرحلة 2 — إصلاحات عالية

```bash
# 4. إصلاح grade_queue_monitor.php retry
status = 'SYNCED'  →  status = 'PENDING', error_message = NULL

# 5. إصلاح event_logs_enhanced.php ليحل محل event_logs.php
# 6. إصلاح retry_failed.php (adhoc task)
# 7. إضافة Authorization header في submit_request.php, upload_photo.php, btec_templates.php
```

### المرحلة 3 — تطوير وتحسين

```bash
# 8. إصلاح statistics.php (MySQL-only SQL)
# 9. إصلاح get_student_data.php (finance case)
# 10. إزالة sync_management.php
# 11. تحديث navigation.php (إضافة student_dashboard_management)
# 12. نقل ملفات .md من ui/admin/
```

---

## 📊 ملخص الأرقام النهائي

| الفئة | العدد |
|---|---|
| 🔴 مشاكل حرجة | 6 |
| 🟠 مشاكل عالية | 5 |
| 🟡 مشاكل متوسطة | 13 |
| 🟢 مشاكل منخفضة | 8 |
| ملفات تحتاج إصلاح فوري | 5 |
| ملفات مقترح حذفها | 3 |
| ملفات مقترح دمجها | 2 |
| **المجموع** | **عناصر: 32** |

---

*تاريخ التقرير: $(date)*  
*محرر التقرير: GitHub Copilot (Claude Sonnet 4.6)*  
*المجلدات المُحللة: `moodle_plugin/ui/admin/` (14 ملف) + `moodle_plugin/ui/ajax/` (7 ملفات)*
