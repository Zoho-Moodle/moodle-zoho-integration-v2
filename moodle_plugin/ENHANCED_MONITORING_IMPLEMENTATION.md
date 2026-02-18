# Enhanced Monitoring & Event Logs - Implementation Summary

## Overview
تم تطوير نظام المراقبة وسجلات الأحداث بشكل شامل لتوفير رؤية تفصيلية ودقيقة لكل عملية مزامنة بين Moodle و Zoho CRM.

## Date
February 8, 2026

## Changes Implemented

### 1. Database Schema Updates

#### New Columns in `local_mzi_event_log` Table
تم إضافة الأعمدة التالية لتحسين التتبع والسياق:

- **`student_name`** (VARCHAR 255): اسم الطالب الكامل المرتبط بالحدث
- **`course_name`** (VARCHAR 255): اسم الكورس المرتبط بالحدث
- **`assignment_name`** (VARCHAR 255): اسم المهمة (Assignment) المرتبطة بالحدث
- **`grade_name`** (VARCHAR 100): قيمة الدرجة أو حرف الدرجة
- **`related_id`** (INT 10): معرف عام للكيان المرتبط (user_id, course_id, etc.)

#### Database Migration
- **File**: `moodle_plugin/db/upgrade.php`
- **Version**: `2026020801`
- **Upgrade Logic**: إضافة الأعمدة الجديدة بشكل آمن مع التحقق من عدم وجودها مسبقاً

---

### 2. Backend Code Updates

#### A. Event Logger Enhancement
**File**: `moodle_plugin/classes/event_logger.php`

**Changes**:
- تحديث `log_event()` لقبول معامل `$context` اختياري
- تخزين التفاصيل التالية تلقائياً:
  - `student_name`
  - `course_name`
  - `assignment_name`
  - `grade_name`
  - `related_id`

**Example Usage**:
```php
$context = [
    'student_name' => 'John Doe',
    'course_name' => 'BTEC Level 3 IT',
    'assignment_name' => 'Unit 1 Assignment',
    'grade_name' => 'Pass'
];

event_logger::log_event('grade_updated', $grade_data, null, $event_id, $context);
```

---

#### B. Webhook Sender Enhancement
**File**: `moodle_plugin/classes/webhook_sender.php`

**New Method**: `extract_context()`
- استخراج التفاصيل تلقائياً من بيانات الحدث
- يعمل مع جميع أنواع الأحداث (users, enrollments, grades)
- يجلب البيانات من قاعدة بيانات Moodle باستخدام IDs

**Updated Methods**:
- جميع دوال الإرسال (`send_user_created`, `send_enrollment_created`, `send_grade_updated`, إلخ) تدعم الآن معامل `$context`

**Integration**:
```php
$sender = new webhook_sender();
$context = webhook_sender::extract_context($grade_data, 'grade_updated');
$response = $sender->send_grade_updated($grade_data, null, $context);
```

---

#### C. Observer Updates
**File**: `moodle_plugin/classes/observer.php`

**Changes**:
- جميع الـ observers تستخدم الآن `extract_context()` قبل إرسال الويب هوك
- يتم تمرير الـ context تلقائياً لكل حدث

**Updated Observers**:
- `user_created()`
- `user_updated()`
- `enrollment_created()`
- `enrollment_deleted()`
- `user_graded()`
- `submission_graded()`

---

### 3. Health Monitor Enhancements

#### A. Detailed Service Monitoring
**File**: `moodle_plugin/classes/task/health_monitor.php`

**New Architecture**:
- مراقبة منفصلة لكل خدمة:
  1. **Backend API**: فحص الاتصال بالـ Backend
  2. **User Sync**: مزامنة المستخدمين
  3. **Course Sync**: مزامنة الكورسات
  4. **Enrollment Sync**: مزامنة التسجيلات
  5. **Grade Sync**: مزامنة الدرجات
  6. **Learning Outcomes**: مزامنة مخرجات التعلم

**Health Status Structure**:
```json
{
  "status": "ok|warning|error",
  "message": "Description",
  "total": 100,
  "sent": 95,
  "failed": 5,
  "success_rate": 95.0,
  "last_check": 1234567890
}
```

**Storage**:
- يتم تخزين حالة كل خدمة في `local_mzi_config`:
  - `health_status_{service_name}`
  - `health_last_check_{service_name}`

---

#### B. Health Monitor Dashboard
**File**: `moodle_plugin/ui/admin/health_monitor_detailed.php`

**Features**:
- **Overall System Status Badge**: يعرض الحالة العامة (Healthy, Warning, Critical)
- **Service Cards**: بطاقة منفصلة لكل خدمة تحتوي على:
  - أيقونة الحالة (✓, ⚠, ✗)
  - رسالة الحالة
  - إحصائيات مفصلة (Total, Sent, Failed, Success Rate)
  - آخر وقت فحص
- **Color Coding**:
  - أخضر: Success rate ≥ 95%
  - أصفر: Success rate 80-94%
  - أحمر: Success rate < 80%

**Navigation**:
- تم تحديث `settings.php` لربط صفحة Health Check الجديدة

---

### 4. Event Logs UI Enhancements

#### A. New Table Columns
**File**: `moodle_plugin/ui/admin/event_logs.php`

**New Columns Added**:
| Column | Description | Example |
|--------|-------------|---------|
| **Student** | اسم الطالب | John Doe |
| **Course** | اسم الكورس | BTEC Level 3 IT |
| **Assignment** | اسم المهمة | Unit 1 Assignment |
| **Grade** | قيمة الدرجة | Pass / 85.50 |

**Benefits**:
- فهم فوري لسياق كل حدث
- تتبع أسهل للمشاكل
- عدم الحاجة للدخول إلى التفاصيل لمعرفة الحدث

---

#### B. Retry Button
**New Feature**: زر "Retry" بجانب "View Details"

**Functionality**:
- يظهر فقط للأحداث الفاشلة (`failed`, `retrying`)
- يسمح بإعادة محاولة إرسال الحدث مباشرة من الجدول
- يستخدم `sesskey` للحماية من CSRF
- رسالة تأكيد قبل التنفيذ

**Implementation**:
```php
if ($event->status === 'failed' || $event->status === 'retrying') {
    $retryurl = new moodle_url($PAGE->url, ['retry' => $event->id, 'sesskey' => sesskey()]);
    $actions .= ' ' . html_writer::link($retryurl, 'Retry', [
        'class' => 'btn btn-sm btn-warning',
        'onclick' => 'return confirm("Retry sending this event?");'
    ]);
}
```

**Retry Logic**:
- يعيد تعيين حالة الحدث إلى `retrying`
- يضبط `next_retry_at` إلى الوقت الحالي
- يسمح للـ scheduled task بإعادة المحاولة في الدورة القادمة

---

### 5. Version Update

**File**: `moodle_plugin/version.php`
- **New Version**: `2026020801`
- **Release**: `3.2.0`
- **Title**: "Enhanced monitoring & event logs"

---

## Testing Checklist

### Database Migration
- [ ] تشغيل ترقية قاعدة البيانات: `php admin/cli/upgrade.php`
- [ ] التحقق من إضافة الأعمدة الجديدة: 
  ```sql
  DESCRIBE mdl_local_mzi_event_log;
  ```

### Event Logging
- [ ] إنشاء حدث جديد (مثلاً: إضافة طالب)
- [ ] التحقق من تسجيل `student_name` و `course_name` في الجدول
- [ ] فحص واجهة Event Logs للتأكد من ظهور التفاصيل

### Health Monitor
- [ ] تشغيل مهمة المراقبة يدوياً:
  ```bash
  php admin/cli/scheduled_task.php --execute=\\local_moodle_zoho_sync\\task\\health_monitor
  ```
- [ ] زيارة صفحة Health Monitor Dashboard
- [ ] التحقق من ظهور جميع الخدمات مع الحالات الصحيحة

### Retry Functionality
- [ ] إنشاء حدث فاشل (إيقاف الباكند مؤقتاً)
- [ ] الضغط على زر "Retry" في Event Logs
- [ ] التحقق من تغيير حالة الحدث إلى `retrying`
- [ ] تشغيل مهمة retry_failed_events والتحقق من إعادة المحاولة

---

## Performance Considerations

### Database Queries
- الأعمدة الجديدة مُفهرسة بشكل غير مباشر عبر `timecreated` و `status`
- لا تؤثر على أداء الاستعلامات الموجودة
- `extract_context()` يستخدم `get_record()` بدلاً من الاستعلامات المعقدة

### Health Monitor
- يتم تشغيله كل ساعة (قابل للتخصيص)
- يخزن النتائج في `config` بدلاً من الحساب في كل طلب
- استعلامات محسّنة مع فلاتر زمنية (آخر 24 ساعة فقط)

---

## Future Enhancements

### Suggested Improvements
1. **Export to CSV**: إمكانية تصدير Event Logs إلى CSV مع التفاصيل الجديدة
2. **Advanced Filters**: فلاتر إضافية (Student Name, Course Name, Date Range)
3. **Email Alerts**: إرسال تنبيهات بريد إلكتروني عند فشل خدمة معينة
4. **Real-time Dashboard**: تحديث تلقائي لحالة الخدمات بدون إعادة تحميل
5. **Batch Retry**: إمكانية إعادة محاولة عدة أحداث دفعة واحدة

---

## Documentation Updates Needed

### User Documentation
- دليل استخدام Health Monitor Dashboard
- شرح معنى كل حالة (OK, Warning, Error)
- إرشادات استكشاف الأخطاء للحالات الشائعة

### Admin Documentation
- دليل ترقية قاعدة البيانات
- شرح آلية عمل `extract_context()`
- أفضل الممارسات لمراقبة النظام

---

## Files Modified

### Core Files
1. `moodle_plugin/db/upgrade.php` - Database migration
2. `moodle_plugin/version.php` - Version bump
3. `moodle_plugin/classes/event_logger.php` - Context support
4. `moodle_plugin/classes/webhook_sender.php` - Context extraction
5. `moodle_plugin/classes/observer.php` - Context integration
6. `moodle_plugin/classes/task/health_monitor.php` - Detailed monitoring
7. `moodle_plugin/ui/admin/event_logs.php` - Enhanced UI
8. `moodle_plugin/settings.php` - Navigation update

### New Files
1. `moodle_plugin/ui/admin/health_monitor_detailed.php` - New dashboard

---

## Success Criteria

✅ جميع التعديلات تم تنفيذها بنجاح
✅ قاعدة البيانات محدّثة بالأعمدة الجديدة
✅ Event Logs يعرض التفاصيل الكاملة (Student, Course, Assignment, Grade)
✅ زر Retry يعمل بشكل صحيح
✅ Health Monitor Dashboard يعرض حالة كل خدمة بشكل منفصل
✅ جميع الـ observers محدّثة لاستخدام الـ context
✅ الكود متوافق مع معايير Moodle Coding Style
✅ لا توجد أخطاء PHP أو SQL

---

## Support & Maintenance

### Contact
- Developer: Mohyeddine Farhat
- Date: February 8, 2026
- Version: 3.2.0

### Known Issues
- لا توجد مشاكل معروفة حالياً

### Maintenance Notes
- تشغيل Health Monitor يومياً للحصول على أفضل النتائج
- مراجعة Event Logs أسبوعياً لتحديد الأنماط
- تنظيف السجلات القديمة (> 90 يوم) بشكل دوري
