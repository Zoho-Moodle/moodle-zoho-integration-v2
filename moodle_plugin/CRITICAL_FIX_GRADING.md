# 🚨 CRITICAL FIX - Grading Not Working

## المشكلة المكتشفة:

**Namespace Inconsistency في events.php**

```php
// ❌ WRONG (mixed formats):
'eventname' => '\core\event\user_created',           // single backslash
'eventname' => '\\mod_assign\\event\\submission_graded',  // double backslash

// ✅ CORRECT (consistent format):
'eventname' => '\\core\\event\\user_created',        // double backslash
'eventname' => '\\mod_assign\\event\\submission_graded',  // double backslash
```

**النتيجة:** Moodle ما قدر يسجل الـ observers صح بسبب الـ inconsistency.

---

## 🔧 الإصلاح المُطبق:

### **1. وحّدنا كل الـ namespaces لـ double backslash:**
```php
// ملف: db/events.php (v3.1.5 - Build 2026020605)

$observers = [
    [
        'eventname' => '\\core\\event\\user_created',
        'callback'  => '\\local_moodle_zoho_sync\\observer::user_created',
    ],
    [
        'eventname' => '\\core\\event\\user_updated',
        'callback'  => '\\local_moodle_zoho_sync\\observer::user_updated',
    ],
    [
        'eventname' => '\\core\\event\\user_enrolment_created',
        'callback'  => '\\local_moodle_zoho_sync\\observer::enrollment_created',
    ],
    [
        'eventname' => '\\core\\event\\user_enrolment_deleted',
        'callback'  => '\\local_moodle_zoho_sync\\observer::enrollment_deleted',
    ],
    [
        'eventname' => '\\mod_assign\\event\\submission_graded',
        'callback'  => '\\local_moodle_zoho_sync\\observer::submission_graded',
    ],
    [
        'eventname' => '\\core\\event\\user_graded',
        'callback'  => '\\local_moodle_zoho_sync\\observer::grade_updated',
    ],
];
```

---

## 🚀 الخطوات المطلوبة **فوراً:**

### **1. ارفع الملفات المُصلحة:**
```bash
# الملفات:
moodle_plugin/db/events.php       # namespace fix
moodle_plugin/version.php         # version 2026020605
```

### **2. Uninstall + Re-install Plugin (أضمن طريقة):**

#### **Option A: عبر UI (موصى به):**
```
Site administration
→ Plugins
→ Plugins overview
→ ابحث عن: Moodle-Zoho Integration
→ Uninstall

⚠️ Warning: سيظهر تحذير بحذف الـ tables
✅ اضغط Continue (البيانات ستُحذف مؤقت - عادي)

بعدين:
→ Site administration
→ Notifications
→ لازم يطلع "New plugin: local_moodle_zoho_sync"
→ Upgrade Moodle database now
```

#### **Option B: عبر CLI (أسرع):**
```bash
# SSH للسيرفر
cd /path/to/moodle

# Uninstall
php admin/cli/uninstall_plugins.php --plugins=local_moodle_zoho_sync --run

# Re-install
php admin/cli/upgrade.php --non-interactive

# Purge caches
php admin/cli/purge_caches.php
```

### **3. تحقق من Registration:**
```sql
-- على database السيرفر:
SELECT * FROM mdl_events_handlers 
WHERE component = 'local_moodle_zoho_sync';

-- لازم يطلع 6 rows:
-- user_created
-- user_updated  
-- user_enrolment_created
-- user_enrolment_deleted
-- submission_graded
-- user_graded
```

**أو عبر Moodle UI:**
```
Site administration
→ Reports
→ Event list
→ ابحث عن: local_moodle_zoho_sync
→ لازم تشوف 6 observers
```

### **4. Re-configure Settings:**
```
⚠️ بعد الـ uninstall، الإعدادات تنحذف!

Site administration 
→ Plugins 
→ Local plugins 
→ Moodle-Zoho Integration

أعد ضبط:
✅ Backend URL: http://YOUR_BACKEND:8001
✅ API Token: (if needed)
✅ Enable User Sync: ☑
✅ Enable Enrollment Sync: ☑
✅ Enable Grade Sync: ☑
✅ Enable Debug: ☑
```

---

## 🧪 اختبار فوري:

### **Test 1: Grade Assignment**
```
1. اعطي grade لأي assignment
2. شوف PHP error log فوراً:
   tail -f /var/log/apache2/error.log | grep "==="
   
3. توقّع:
   === SUBMISSION_GRADED OBSERVER FIRED === Assignment: X
   === SUBMISSION GRADE CONFIG === enable_grade_sync: YES
   === GRADE DATA EXTRACTED === {...}
   === WEBHOOK RESPONSE === {"success":true,...}
```

### **Test 2: Manual Grade**
```
1. Grades → Turn editing on → أعطي grade يدوي
2. شوف log:
   === GRADE OBSERVER FIRED === Event: user_graded, ID: X
```

### **Test 3: Unenroll Student**
```
1. Participants → Unenrol student
2. شوف log:
   === ENROLLMENT DELETED OBSERVER FIRED === Enrolment ID: X
```

---

## ✅ التحقق من النجاح:

### **1. PHP Logs تطلع:**
```bash
tail -f /var/log/apache2/error.log

# لو طلع أي من هاي الـ logs = شغال ✅
=== SUBMISSION_GRADED OBSERVER FIRED ===
=== GRADE OBSERVER FIRED ===
=== ENROLLMENT DELETED OBSERVER FIRED ===
```

### **2. Backend يستقبل:**
```bash
cd backend
tail -f logs/app.log

# لازم تشوف:
INFO: POST /api/v1/webhooks HTTP/1.1 200 OK
INFO: Received webhook: grade_updated
```

### **3. Database يسجل:**
```sql
SELECT * FROM mdl_local_mzi_event_log 
WHERE event_type = 'grade_updated'
ORDER BY timecreated DESC LIMIT 5;

-- لازم status = 'sent', response_code = 200
```

---

## 🐛 لو لسا مش شغال:

### **Scenario 1: ما في logs بتطلع أبداً**

**المشكلة:** Observer مش مسجل

**الحل:**
```sql
-- تحقق:
SELECT * FROM mdl_events_handlers 
WHERE component = 'local_moodle_zoho_sync';

-- لو ما في نتائج أو أقل من 6:
-- معناها الـ uninstall ما صار صح

-- الحل:
1. Delete الـ plugin folder يدوي:
   rm -rf /path/to/moodle/local/moodle_zoho_sync

2. Drop الـ tables يدوي:
   DROP TABLE mdl_local_mzi_event_log;
   DROP TABLE mdl_local_mzi_sync_history;
   DROP TABLE mdl_local_mzi_config;

3. Delete من mdl_config_plugins:
   DELETE FROM mdl_config_plugins 
   WHERE plugin = 'local_moodle_zoho_sync';

4. ارفع الـ plugin من جديد وثبته
```

### **Scenario 2: Logs تطلع لكن "Connection refused"**

**المشكلة:** Backend URL غلط

**الحل:**
```bash
# Test من سيرفر Moodle:
curl -X POST http://YOUR_BACKEND:8001/api/v1/webhooks \
  -H "Content-Type: application/json" \
  -d '{"event_type":"test","event_data":{}}'

# لو فشل:
- تأكد Backend شغال
- تأكد firewall مفتوح (port 8001)
- تأكد URL صح (مش localhost!)
```

### **Scenario 3: Logs تطلع + Backend يستقبل لكن مش مسجل بالـ Event Logs**

**المشكلة:** event_logger مش شغال

**الحل:**
```sql
-- تحقق من الـ table:
SHOW TABLES LIKE 'mdl_local_mzi_event_log';

-- لو مش موجودة:
-- Install الـ plugin مرة ثانية
```

---

## 📋 Quick Checklist:

- [ ] ارفعت events.php + version.php الجديدة
- [ ] عملت Uninstall Plugin
- [ ] عملت Re-install Plugin
- [ ] Purge caches
- [ ] تحققت من 6 observers في mdl_events_handlers
- [ ] ضبطت Backend URL صح (مش localhost)
- [ ] ضبطت Enable Grade Sync = ☑
- [ ] جربت Grade → شفت logs
- [ ] Backend استقبل webhook
- [ ] Database سجل event

---

## 🎯 السبب الجذري:

**PHP Namespace Escaping:**
- Single backslash `\` في strings بتُعامل كـ escape character
- لازم double backslash `\\` عشان تمثل backslash واحد حقيقي
- الـ inconsistency خلت Moodle يفشل يسجل بعض الـ observers

**الدرس:** دايماً استخدم `\\` في namespace strings بـ PHP.

---

**Version:** 3.1.5 (Build 2026020605)  
**Fix Date:** 6 فبراير 2026  
**Status:** ✅ Critical Namespace Fix Applied  
**Priority:** P0 - Must apply immediately
