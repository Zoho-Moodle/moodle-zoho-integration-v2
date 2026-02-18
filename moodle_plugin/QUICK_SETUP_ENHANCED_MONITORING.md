# Quick Setup Guide - Enhanced Monitoring

## تفعيل التحديثات الجديدة

### 1. ترقية قاعدة البيانات
```bash
# في مجلد Moodle الرئيسي
php admin/cli/upgrade.php
```

**الناتج المتوقع:**
```
Upgrading local_moodle_zoho_sync from version 2026020606 to 2026020801
... upgrade step: 2026020801
... done
```

---

### 2. التحقق من الأعمدة الجديدة

#### عبر phpMyAdmin أو SQL:
```sql
DESCRIBE mdl_local_mzi_event_log;
```

**يجب أن ترى الأعمدة:**
- `student_name` (varchar 255)
- `course_name` (varchar 255)
- `assignment_name` (varchar 255)
- `grade_name` (varchar 100)
- `related_id` (int 10)

---

### 3. تشغيل Health Monitor يدوياً (اختياري)

```bash
# تشغيل مهمة المراقبة
php admin/cli/scheduled_task.php --execute=\\local_moodle_zoho_sync\\task\\health_monitor
```

**الناتج المتوقع:**
```
Running detailed health check...
=== Health Check Summary ===
✓ Backend Api: ok - Backend API is reachable
✓ User Sync: ok - Success rate: 100%
✓ Course Sync: ok - No events in last 24 hours
✓ Enrollment Sync: ok - Success rate: 95.5%
✓ Grade Sync: ok - Success rate: 98.2%
✓ Learning Outcomes: ok - LO sync healthy: 97.5%
Health check complete.
```

---

## استخدام الميزات الجديدة

### 1. Health Monitor Dashboard

#### الوصول:
1. اذهب إلى: **Site administration → Plugins → Local plugins → Moodle-Zoho Integration**
2. اختر: **Health check**

#### ما ستراه:
- **Overall Status Badge**: (Healthy / Warning / Critical)
- **6 Service Cards**:
  - Backend API Connection
  - User Synchronization
  - Course Synchronization
  - Enrollment Synchronization
  - Grade Synchronization
  - Learning Outcomes Sync

#### قراءة الحالات:
- ✓ **Green (OK)**: Success rate ≥ 95% - كل شيء يعمل بشكل ممتاز
- ⚠ **Yellow (Warning)**: Success rate 80-94% - هناك بعض المشاكل البسيطة
- ✗ **Red (Error)**: Success rate < 80% - مشكلة خطيرة تحتاج تدخل فوري

---

### 2. Event Logs - الأعمدة الجديدة

#### الوصول:
1. اذهب إلى: **Site administration → Plugins → Local plugins → Moodle-Zoho Integration**
2. اختر: **Event logs**

#### الأعمدة الجديدة:
- **Student**: اسم الطالب (مثال: John Doe)
- **Course**: اسم الكورس (مثال: BTEC Level 3 IT)
- **Assignment**: اسم المهمة (مثال: Unit 1 Assignment)
- **Grade**: قيمة الدرجة (مثال: Pass / 85.50)

#### مثال على سطر في الجدول:
| Event ID | Event Type | Student | Course | Assignment | Grade | Status | Actions |
|----------|------------|---------|--------|------------|-------|--------|---------|
| a3b4c5d... | grade_updated | John Doe | BTEC Level 3 IT | Unit 1 Assignment | Pass | sent | View Details |

---

### 3. Retry Button - إعادة محاولة الأحداث الفاشلة

#### متى يظهر زر Retry:
- فقط للأحداث التي حالتها `failed` أو `retrying`
- يظهر بجانب زر "View Details"

#### كيفية الاستخدام:
1. ابحث عن حدث فاشل في Event Logs
2. اضغط على زر **Retry** (لونه أصفر)
3. أكّد عملية إعادة المحاولة
4. سيتم إعادة محاولة إرسال الحدث في الدورة القادمة للـ scheduled task

#### ملاحظات:
- الحدث سيتم وضعه في قائمة الانتظار للإرسال مرة أخرى
- إذا كان الباكند متوقف، سيفشل مرة أخرى
- يمكنك إعادة المحاولة عدة مرات بدون حد أقصى

---

## استكشاف الأخطاء

### المشكلة: الأعمدة الجديدة لا تظهر في Event Logs

**الحل:**
```bash
# 1. تحقق من نسخة الإضافة
SELECT * FROM mdl_config_plugins WHERE plugin = 'local_moodle_zoho_sync' AND name = 'version';
# يجب أن تكون: 2026020801

# 2. إذا كانت أقل، شغّل الترقية يدوياً
php admin/cli/upgrade.php

# 3. امسح الكاش
php admin/cli/purge_caches.php
```

---

### المشكلة: Health Monitor يعرض "No health data available"

**الحل:**
```bash
# شغّل مهمة المراقبة يدوياً
php admin/cli/scheduled_task.php --execute=\\local_moodle_zoho_sync\\task\\health_monitor

# أو انتظر حتى يتم تشغيلها تلقائياً (كل ساعة)
```

---

### المشكلة: زر Retry لا يعمل

**الأسباب المحتملة:**
1. **CSRF Token غير صحيح**: تأكد أنك مسجل دخول بشكل صحيح
2. **الحدث غير موجود**: تحقق من ID الحدث في قاعدة البيانات
3. **Permissions**: تأكد أن لديك صلاحية `local/moodle_zoho_sync:manage`

**الحل:**
```bash
# تحقق من سجلات PHP
tail -f /path/to/moodle/error_log

# تحقق من حالة الحدث
SELECT id, event_id, status, retry_count FROM mdl_local_mzi_event_log WHERE id = YOUR_EVENT_ID;
```

---

## الأسئلة الشائعة (FAQ)

### س: كم مرة يتم تحديث Health Monitor؟
**ج:** يتم تشغيل مهمة المراقبة كل ساعة بشكل افتراضي. يمكنك تعديل التردد من:
- **Site administration → Server → Scheduled tasks**
- ابحث عن: "Health monitor"

---

### س: هل الأعمدة الجديدة تؤثر على الأداء؟
**ج:** لا، التأثير على الأداء ضئيل جداً:
- الأعمدة يتم ملؤها عند تسجيل الحدث فقط (مرة واحدة)
- لا توجد استعلامات إضافية عند عرض Event Logs
- البيانات مخزّنة مباشرة في الجدول (لا JOINs)

---

### س: ماذا لو كان الحدث لا يحتوي على Student أو Course؟
**ج:** ستظهر علامة `-` في الخلية. مثلاً:
- حدث `user_created` سيعرض اسم المستخدم فقط
- حدث `enrollment_created` سيعرض الطالب والكورس
- حدث `grade_updated` سيعرض الطالب والكورس والمهمة والدرجة

---

### س: هل يمكنني تصدير Event Logs مع التفاصيل الجديدة؟
**ج:** حالياً لا، لكن يمكنك استخدام استعلام SQL مباشر:
```sql
SELECT 
    event_id,
    event_type,
    student_name,
    course_name,
    assignment_name,
    grade_name,
    status,
    retry_count,
    FROM_UNIXTIME(timecreated) as created_at
FROM mdl_local_mzi_event_log
WHERE timecreated >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 7 DAY))
ORDER BY timecreated DESC;
```

---

## الدعم الفني

إذا واجهت أي مشاكل:
1. تحقق من `moodle_plugin/ENHANCED_MONITORING_IMPLEMENTATION.md` للتفاصيل الفنية
2. راجع سجلات Moodle: `admin/reports/logs`
3. راجع سجلات PHP: `/var/log/apache2/error.log` أو `/var/log/nginx/error.log`
4. تواصل مع المطور: Mohyeddine Farhat

---

## ملخص سريع

✅ **تم تنفيذه:**
1. قاعدة بيانات محدّثة بـ 5 أعمدة جديدة
2. Event Logs يعرض Student, Course, Assignment, Grade
3. Health Monitor مفصّل لكل خدمة على حدة
4. زر Retry لإعادة محاولة الأحداث الفاشلة
5. Context يتم استخراجه تلقائياً لكل حدث

✅ **جاهز للاستخدام:**
- ارفع الترقية: `php admin/cli/upgrade.php`
- زر صفحة Health Monitor
- استمتع بالمراقبة المحسّنة!

🎉 **نسخة 3.2.0 - Enhanced Monitoring & Event Logs**
