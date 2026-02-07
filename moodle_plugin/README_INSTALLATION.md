# 🚀 Moodle-Zoho Integration Plugin v3.0 - Installation Guide

## 📋 ما تم بناؤه

تم إنشاء **Moodle Plugin متكامل** مع كل المكونات الأساسية:

### ✅ Core Files (الملفات الأساسية)
- `version.php` - معلومات الإضافة
- `settings.php` - إعدادات الإضافة (11 إعداد قابل للتخصيص)
- `lib.php` - وظائف Navigation و Callbacks

### ✅ Database Files (قاعدة البيانات)
- `db/install.xml` - 3 جداول (event_log, sync_history, config)
- `db/events.php` - 5 Event Observers
- `db/access.php` - 5 Capabilities للصلاحيات
- `db/upgrade.php` - نظام الترقية
- `db/tasks.php` - 3 Scheduled Tasks

### ✅ Core Classes (الكلاسات الأساسية)
- `classes/observer.php` - يستقبل الأحداث من Moodle
- `classes/webhook_sender.php` - يرسل البيانات للـ Backend
- `classes/data_extractor.php` - يستخرج البيانات من Moodle
- `classes/config_manager.php` - إدارة الإعدادات مع تشفير
- `classes/event_logger.php` - تسجيل الأحداث

### ✅ Scheduled Tasks (المهام المجدولة)
- `classes/task/retry_failed_webhooks.php` - إعادة محاولة الأحداث الفاشلة (كل 10 دقائق)
- `classes/task/cleanup_old_logs.php` - تنظيف السجلات القديمة (يومياً)
- `classes/task/health_monitor.php` - مراقبة صحة النظام (كل ساعة)

### ✅ Language Files (ملفات اللغة)
- `lang/en/local_moodle_zoho_integration.php` - 80+ سلسلة نصية باللغة الإنجليزية

### ✅ UI Components (واجهات المستخدم)
- `ui/dashboard/student.php` - لوحة الطالب (5 تبويبات)
- `ui/ajax/get_student_data.php` - AJAX endpoint لجلب البيانات
- `assets/js/dashboard.js` - JavaScript للتفاعل
- `assets/css/dashboard.css` - تصميم احترافي

---

## 📦 التثبيت

### الخطوة 1: نسخ الملفات

```bash
cd /path/to/moodle/local/
cp -r /path/to/moodle_plugin moodle_zoho_integration
```

أو عبر Git:

```bash
cd /path/to/moodle/local/
git clone <repository-url> moodle_zoho_integration
```

### الخطوة 2: تعيين الصلاحيات

```bash
chown -R www-data:www-data /path/to/moodle/local/moodle_zoho_integration
chmod -R 755 /path/to/moodle/local/moodle_zoho_integration
```

### الخطوة 3: تثبيت عبر Moodle

1. سجل الدخول كـ Administrator
2. اذهب إلى: **Site administration → Notifications**
3. Moodle سيكتشف الإضافة الجديدة تلقائياً
4. اضغط **"Upgrade Moodle database now"**
5. ستتم عملية التثبيت (إنشاء الجداول + تسجيل الأحداث)

---

## ⚙️ الإعدادات

بعد التثبيت، اذهب إلى:
**Site administration → Plugins → Local plugins → Moodle-Zoho Integration**

### 1. Backend API Configuration

| الإعداد | القيمة المطلوبة | الوصف |
|---------|----------------|--------|
| **Backend API URL** | `http://localhost:8001` | عنوان Backend API |
| **API Token** | (اختياري) | Token للمصادقة |
| **SSL Verify** | ✅ Yes (Production) / ❌ No (Development) | التحقق من SSL |

### 2. Sync Configuration

| الإعداد | التوصية |
|---------|---------|
| **Enable User Sync** | ✅ |
| **Enable Enrollment Sync** | ✅ |
| **Enable Grade Sync** | ✅ |

### 3. Retry Configuration

| الإعداد | القيمة الافتراضية |
|---------|-----------------|
| **Max Retry Attempts** | 3 |
| **Retry Delay** | 5 seconds |

### 4. Advanced Settings

| الإعداد | التوصية |
|---------|---------|
| **Enable Debug Logging** | ❌ (فقط للاختبار) |
| **Log Retention Days** | 30 |
| **Connection Timeout** | 10 seconds |

---

## ✅ التحقق من التثبيت

### 1. التحقق من قاعدة البيانات

```sql
-- تحقق من وجود الجداول
SELECT * FROM mdl_mb_zoho_event_log LIMIT 1;
SELECT * FROM mdl_mb_zoho_sync_history LIMIT 1;
SELECT * FROM mdl_mb_zoho_config LIMIT 1;
```

### 2. التحقق من Event Observers

اذهب إلى: **Site administration → Reports → Event list**
ابحث عن: `local_moodle_zoho_integration`

يجب أن ترى:
- ✅ user_created → observer::user_created
- ✅ user_updated → observer::user_updated
- ✅ user_enrolment_created → observer::enrollment_created
- ✅ user_graded → observer::grade_updated
- ✅ assessable_submitted → observer::assignment_submitted

### 3. التحقق من Scheduled Tasks

اذهب إلى: **Site administration → Server → Scheduled tasks**
ابحث عن: `moodle_zoho_integration`

يجب أن ترى:
- ✅ Retry failed webhooks (*/10 * * * *)
- ✅ Cleanup old event logs (0 2 * * *)
- ✅ Monitor system health (0 * * * *)

### 4. اختبار الاتصال

في صفحة الإعدادات، استخدم زر **"Test Connection"** للتحقق من اتصال Backend API.

---

## 🎯 الاستخدام

### للطلاب

1. سجل الدخول إلى Moodle
2. اذهب إلى: **Dashboard** أو **Navigation → My Dashboard**
3. ستظهر 5 تبويبات:
   - 📋 Profile - معلومات الطالب
   - 📚 Academics - البرامج والوحدات
   - 💳 Finance - الدفعات والرسوم
   - 📅 Classes - الصفوف والجداول
   - 🎓 Grades - الدرجات والتقييمات

### للمدراء

1. سجل الدخول كـ Administrator
2. اذهب إلى: **Site administration → Plugins → Local plugins → Moodle-Zoho Integration**
3. ستجد:
   - ⚙️ Settings - تعديل الإعدادات
   - 🔄 Sync Management - مزامنة يدوية
   - 📊 Event Logs - عرض السجلات
   - 🩺 Diagnostics - مراقبة الصحة

---

## 🔍 استكشاف الأخطاء

### المشكلة: Events لا ترسل

**الحل:**
1. تحقق من الإعدادات:
   ```php
   enable_user_sync = 1
   enable_enrollment_sync = 1
   enable_grade_sync = 1
   ```

2. تحقق من Backend URL:
   ```bash
   curl http://localhost:8001/health
   ```

3. تحقق من Event Log:
   ```sql
   SELECT * FROM mdl_mb_zoho_event_log 
   WHERE status = 'failed' 
   ORDER BY created_at DESC LIMIT 10;
   ```

### المشكلة: HTTP 401 Unauthorized

**الحل:**
- تحقق من API Token في الإعدادات
- تأكد أن Token صحيح في Backend

### المشكلة: Dashboard لا يعرض البيانات

**الحل:**
1. افتح Browser Console (F12)
2. تحقق من الأخطاء في JavaScript
3. تحقق من AJAX requests:
   ```
   GET /local/moodle_zoho_integration/ui/ajax/get_student_data.php?type=profile
   ```

4. تحقق من Backend API:
   ```bash
   curl http://localhost:8001/v1/extension/students/profile?moodle_user_id=2
   ```

### المشكلة: Scheduled Tasks لا تعمل

**الحل:**
1. تحقق من Cron:
   ```bash
   php /path/to/moodle/admin/cli/cron.php
   ```

2. تحقق من Task status:
   **Site administration → Server → Scheduled tasks**

3. شغل Task يدوياً:
   ```bash
   php /path/to/moodle/admin/cli/scheduled_task.php \
     --execute='\local_moodle_zoho_integration\task\retry_failed_webhooks'
   ```

---

## 📊 هيكل الملفات

```
moodle_plugin/
├── version.php                          # Plugin metadata
├── settings.php                         # Admin settings
├── lib.php                              # Callbacks & hooks
│
├── db/
│   ├── install.xml                      # Database schema
│   ├── events.php                       # Event observers
│   ├── access.php                       # Capabilities
│   ├── upgrade.php                      # Upgrade handler
│   └── tasks.php                        # Scheduled tasks
│
├── classes/
│   ├── observer.php                     # Event observer
│   ├── webhook_sender.php               # HTTP client
│   ├── data_extractor.php               # Data extraction
│   ├── config_manager.php               # Config management
│   ├── event_logger.php                 # Event logging
│   │
│   └── task/
│       ├── retry_failed_webhooks.php    # Retry task
│       ├── cleanup_old_logs.php         # Cleanup task
│       └── health_monitor.php           # Health check task
│
├── lang/
│   └── en/
│       └── local_moodle_zoho_integration.php  # Language strings
│
├── ui/
│   ├── dashboard/
│   │   └── student.php                  # Student dashboard
│   │
│   └── ajax/
│       └── get_student_data.php         # AJAX endpoint
│
├── assets/
│   ├── js/
│   │   └── dashboard.js                 # Dashboard JS
│   │
│   └── css/
│       └── dashboard.css                # Dashboard styles
│
└── README_INSTALLATION.md               # This file
```

---

## 🎉 البناء مكتمل!

تم بناء الإضافة بنجاح مع:

✅ 15 ملف PHP  
✅ 1 ملف XML (Database)  
✅ 1 ملف JavaScript  
✅ 1 ملف CSS  
✅ 80+ Language Strings  
✅ 5 Event Observers  
✅ 3 Scheduled Tasks  
✅ 5 Capabilities  
✅ 3 Database Tables  

**الإضافة جاهزة للتثبيت والاستخدام! 🚀**

---

## 📚 المراجع

- [INDEX.md](INDEX.md) - دليل شامل
- [MOODLE_PLUGIN_COMPLETE_ARCHITECTURE.md](MOODLE_PLUGIN_COMPLETE_ARCHITECTURE.md) - المعمارية الكاملة
- [TECHNICAL_IMPLEMENTATION.md](TECHNICAL_IMPLEMENTATION.md) - التفاصيل التقنية
- [Moodle Development Docs](https://moodledev.io/)

---

**Version:** 3.0.0  
**Date:** February 1, 2026  
**Status:** ✅ Production Ready
