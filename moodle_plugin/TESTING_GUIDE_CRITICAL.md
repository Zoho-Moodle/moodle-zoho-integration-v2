# 🧪 دليل اختبار الميزات الحرجة - Moodle Plugin v3.1.4

**التحديثات الجديدة:**
- ✅ إضافة Unenrollment support
- ✅ Enhanced logging لجميع الـ observers
- ✅ Force logs حتى لو debug mode مطفي

---

## ⚙️ **التحضير الفوري**

### **1. ارفع الملفات المعدلة للسيرفر:**
```bash
# الملفات اللي تغيرت:
moodle_plugin/db/events.php              # أضفنا enrollment_deleted observer
moodle_plugin/classes/observer.php        # أضفنا enrollment_deleted method + logs
moodle_plugin/classes/webhook_sender.php  # أضفنا send_enrollment_deleted
moodle_plugin/version.php                 # version 2026020604
```

### **2. شغّل Upgrade على السيرفر:**
```bash
# SSH للسيرفر
cd /path/to/moodle

# Upgrade
php admin/cli/upgrade.php --non-interactive

# Purge caches
php admin/cli/purge_caches.php
```

### **3. اضبط Backend URL:**
```
⚠️ CRITICAL: لازم تضبط Backend URL صح

Site administration → Plugins → Local plugins → Moodle-Zoho Integration

❌ WRONG: http://localhost:8001
✅ RIGHT: http://YOUR_SERVER_IP:8001
✅ OR:    https://your-ngrok-url.ngrok-free.app
```

### **4. تأكد من Settings:**
```
✅ Backend URL: (صح - شوف فوق)
✅ Enable User Sync: ☑
✅ Enable Enrollment Sync: ☑  ← مهم لـ unenrollment
✅ Enable Grade Sync: ☑      ← مهم لـ grading
✅ Enable Debug Logging: ☑   ← مهم لرؤية المشاكل
```

### **5. شغّل Logs Monitoring:**

**Terminal 1 - Moodle PHP Logs:**
```bash
# حدد موقع PHP error log:
tail -f /var/log/apache2/error.log     # Apache
# أو
tail -f /var/log/php-fpm/error.log     # PHP-FPM
# أو
grep "===" /var/log/apache2/error.log  # بحث عن logs
```

**Terminal 2 - Backend Logs:**
```bash
cd backend
tail -f logs/app.log
# أو
python start_server.py  # ولاحظ console output
```

---

## 🧪 **اختبار 1: GRADING (Assignment Submission)**

### **المشكلة السابقة:**
- Observer مش شغال
- Backend URL غلط (localhost)

### **الحل المُطبق:**
- ✅ Enhanced logging بكل خطوة
- ✅ Force error_log (حتى لو debug مطفي)
- ✅ تفاصيل كاملة عن Config

### **خطوات الاختبار:**

#### **1. أنشئ Assignment:**
```
داخل أي Course:
→ Turn editing on
→ Add activity → Assignment
→ Name: Test Grading Assignment
→ Grade: 100
→ Save and display
```

#### **2. الطالب يرفع Submission:**
```
Login كـ Student
→ افتح Assignment
→ Add submission
→ ارفع أي ملف
→ Submit
```

#### **3. اعطي Grade:**
```
Login كـ Teacher/Admin
→ Assignment → View all submissions
→ اختر الطالب
→ Grade: 85
→ Save changes
```

#### **4. توقّع PHP Logs (فوري):**
```log
=== SUBMISSION_GRADED OBSERVER FIRED === Assignment: 5
=== SUBMISSION GRADE CONFIG === enable_grade_sync: YES, backend_url: http://...
=== SUBMISSION_GRADED DATA === assignmentid: 5, studentid: 123
=== GRADE ITEM FOUND === ID: 50
=== GRADE RECORD FOUND === ID: 789
=== GRADE DATA EXTRACTED === {"grade_id":789,"userid":123,"raw_grade":85,...}
=== WEBHOOK RESPONSE === {"success":true,"event_id":"uuid-...",...}
```

#### **5. توقّع Backend Logs:**
```log
INFO: POST /api/v1/webhooks HTTP/1.1 200 OK
INFO: Received webhook: grade_updated (ID: uuid-...)
INFO: Processing grade_updated for grade ID 789
```

### **❌ لو ما طلع أي log:**
```
المشكلة: Observer مش مسجل

الحل:
1. تأكد من upgrade:
   SELECT * FROM mdl_events_handlers 
   WHERE component = 'local_moodle_zoho_sync';
   -- لازم يطلع 6 rows (زادت واحدة)

2. Re-install plugin:
   Site administration → Plugins → Plugins overview
   → Moodle-Zoho Integration → Uninstall
   → Notifications → Install

3. Purge caches:
   php admin/cli/purge_caches.php
```

### **❌ لو طلع log بس "connection refused":**
```log
=== WEBHOOK RESPONSE === {"success":false,"error":"Connection refused"}
```

```
المشكلة: Backend URL غلط

الحل:
1. تحقق من Backend URL في Settings
2. Test من terminal:
   curl -X POST http://YOUR_BACKEND:8001/api/v1/webhooks \
     -H "Content-Type: application/json" \
     -d '{"event_type":"test","event_data":{}}'

3. إذا curl فشل:
   - Backend مش شغال → شغّله
   - Firewall blocking → فتح port 8001
   - URL غلط → صححه
```

---

## 🧪 **اختبار 2: GRADING (Manual Grade Entry)**

### **خطوات الاختبار:**

#### **1. اعطي Grade يدوي:**
```
داخل Course:
→ Grades (من menu)
→ Turn editing on
→ اختر الطالب
→ اضغط على خانة Grade item
→ اكتب: 92
→ Enter
→ Save changes
```

#### **2. توقّع PHP Logs:**
```log
=== GRADE OBSERVER FIRED === Event: user_graded, ID: 790
=== GRADE SYNC CONFIG === enable_grade_sync: YES, backend_url: http://...
=== GRADE DATA EXTRACTED === {"grade_id":790,"userid":123,"raw_grade":92,...}
=== WEBHOOK RESPONSE === {"success":true,...}
```

#### **3. توقّع Backend Logs:**
```log
INFO: POST /api/v1/webhooks HTTP/1.1 200 OK
INFO: Received webhook: grade_updated (ID: uuid-...)
```

### **التحقق من Database:**
```sql
-- Moodle
SELECT e.*, u.username, gi.itemname, gg.finalgrade 
FROM mdl_local_mzi_event_log e
JOIN mdl_user u ON e.userid = u.id
JOIN mdl_grade_grades gg ON gg.id = e.grade_id
JOIN mdl_grade_items gi ON gi.id = gg.itemid
WHERE e.event_type = 'grade_updated'
ORDER BY e.timecreated DESC LIMIT 5;

-- توقّع:
-- status = 'sent'
-- response_code = 200
-- event_id موجود
```

---

## 🧪 **اختبار 3: UNENROLLMENT (الميزة الجديدة)**

### **الحالة السابقة:**
- ❌ ما كان في observer لـ unenrollment
- ❌ لما تشيل طالب من كورس، ما كان يرسل webhook

### **الحل المُطبق:**
- ✅ أضفنا observer لـ `user_enrolment_deleted`
- ✅ أضفنا method `enrollment_deleted` في observer.php
- ✅ أضفنا method `send_enrollment_deleted` في webhook_sender.php
- ✅ Enhanced logging

### **خطوات الاختبار:**

#### **1. سجّل طالب بكورس أولاً:**
```
داخل Course:
→ Participants
→ Enrol users
→ اختر طالب: test_student_1
→ Role: Student
→ Enrol
```

#### **2. شيله من الكورس (Unenrol):**
```
نفس الصفحة (Participants):
→ ابحث عن test_student_1
→ اضغط على icon التسجيل (enrollment)
→ Unenrol (أو Edit enrolment → Status: Suspended)
→ Confirm
```

#### **3. توقّع PHP Logs (فوري):**
```log
=== ENROLLMENT DELETED OBSERVER FIRED === Enrolment ID: 456
=== ENROLLMENT DELETE CONFIG === enable_enrollment_sync: YES, backend_url: http://...
=== ENROLLMENT DATA EXTRACTED === {"enrollment_id":456,"userid":123,"courseid":10,...}
=== WEBHOOK RESPONSE === {"success":true,"event_id":"uuid-...",...}
```

#### **4. توقّع Backend Logs:**
```log
INFO: POST /api/v1/webhooks HTTP/1.1 200 OK
INFO: Received webhook: enrollment_deleted (ID: uuid-...)
INFO: Processing enrollment_deleted: User 123 unenrolled from course 10
```

### **التحقق من Database:**
```sql
-- Moodle
SELECT * FROM mdl_local_mzi_event_log 
WHERE event_type = 'enrollment_deleted'
ORDER BY timecreated DESC LIMIT 1;

-- توقّع:
-- status = 'sent'
-- userid = 123
-- courseid = 10
-- response_code = 200

-- Backend
SELECT * FROM moodle_events 
WHERE event_type = 'enrollment_deleted'
ORDER BY created_at DESC LIMIT 1;

-- توقّع:
-- processing_status = 'completed'
-- event_data يحتوي enrollment info
```

---

## 🧪 **اختبار 4: ENROLLMENT CREATED (للمقارنة)**

### **خطوات الاختبار:**

#### **1. سجّل طالب بكورس:**
```
Course → Participants → Enrol users
→ test_student_2
→ Role: Student
→ Enrol
```

#### **2. توقّع PHP Logs:**
```log
=== ENROLLMENT CREATED OBSERVER FIRED === Enrolment ID: 457
=== ENROLLMENT CONFIG === enable_enrollment_sync: YES, backend_url: http://...
=== ENROLLMENT DATA EXTRACTED === {"enrollment_id":457,"userid":124,...}
=== WEBHOOK RESPONSE === {"success":true,...}
```

#### **3. توقّع Backend Logs:**
```log
INFO: POST /api/v1/webhooks HTTP/1.1 200 OK
INFO: Received webhook: enrollment_created (ID: uuid-...)
```

---

## 📊 **Checklist التحقق السريع**

### **✅ Grading:**
- [ ] Assignment submission graded → webhook sent
- [ ] Manual grade entry → webhook sent
- [ ] PHP logs تظهر جميع الخطوات
- [ ] Backend logs تظهر receipt
- [ ] Database: status = 'sent', response_code = 200

### **✅ Unenrollment:**
- [ ] Unenrol student → webhook sent
- [ ] PHP logs تظهر enrollment_deleted
- [ ] Backend logs تظهر receipt
- [ ] Database: event_type = 'enrollment_deleted'

### **✅ Enrollment (للمقارنة):**
- [ ] Enrol student → webhook sent
- [ ] PHP logs تظهر enrollment_created
- [ ] Backend logs تظهر receipt

---

## 🐛 **Troubleshooting السريع**

### **مشكلة: ما في أي logs بتطلع**

```bash
# 1. تأكد من PHP error_log شغال:
php -i | grep error_log

# 2. تأكد من permissions:
ls -la /var/log/apache2/error.log

# 3. شغّل PHP من CLI:
php -r "error_log('TEST LOG');"
# بعدين شوف الـ log:
tail /var/log/apache2/error.log

# 4. إذا ما زال ما في شي، شوف PHP-FPM:
tail -f /var/log/php-fpm/www-error.log
```

### **مشكلة: Logs تطلع لكن "Connection refused"**

```bash
# 1. Test Backend من السيرفر نفسه:
curl -X POST http://localhost:8001/api/v1/webhooks \
  -H "Content-Type: application/json" \
  -d '{"event_type":"test","event_data":{}}'

# 2. إذا localhost شغال لكن external IP لأ:
# معناها firewall blocking
sudo ufw allow 8001/tcp    # Ubuntu
sudo firewall-cmd --add-port=8001/tcp --permanent  # CentOS

# 3. تأكد Backend شغال:
ps aux | grep python
netstat -tulpn | grep 8001
```

### **مشكلة: Backend logs ما تظهر أي شي**

```bash
# 1. تأكد من Backend شغال:
curl http://localhost:8001/health

# 2. شوف Backend logs:
cd backend
tail -f logs/app.log

# 3. إذا ما في logs folder:
mkdir -p logs
chmod 755 logs

# 4. شغّل Backend بـ debug mode:
export LOG_LEVEL=DEBUG
python start_server.py
```

---

## 📝 **ملاحظات مهمة**

### **1. Backend URL:**
```
❌ NEVER use: http://localhost:8001
   (localhost = Moodle server itself, NOT Backend server)

✅ ALWAYS use:
   - http://BACKEND_SERVER_IP:8001  (same network)
   - https://your-ngrok.ngrok-free.app  (tunneling)
   - http://backend.yourdomain.com:8001  (DNS)
```

### **2. Force Logs:**
```php
// الكود الجديد يستخدم error_log() مباشرة
// هاد يعمل log حتى لو enable_debug = 0
error_log('=== OBSERVER FIRED ===');

// بدل:
self::log_debug()  // هاد بس يشتغل لو enable_debug = 1
```

### **3. Event Types:**
```
Moodle Plugin يرسل:
- user_created
- user_updated
- enrollment_created
- enrollment_deleted  ← جديد!
- grade_updated

Backend يستقبل:
- كل الأنواع فوق ✅
```

---

## ✅ **الخطوات التالية بعد الاختبار الناجح**

### **1. نظف Logs:**
```php
// بعد ما تتأكد كل شي شغال، احذف force logs:
// من observer.php - احذف كل سطر فيه:
error_log('=== ... ===');

// وخلي بس:
self::log_debug()  // هاد يشتغل بس لما enable_debug = true
```

### **2. Disable Debug Mode:**
```
Settings → Enable Debug Logging: ☐
(بس بعد ما تتأكد كل شي شغال 100%)
```

### **3. Monitor Production:**
```sql
-- شوف success rate يومياً:
SELECT 
    DATE(FROM_UNIXTIME(timecreated)) as date,
    COUNT(*) as total,
    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as success,
    ROUND(SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as rate
FROM mdl_local_mzi_event_log
WHERE timecreated > UNIX_TIMESTAMP(NOW() - INTERVAL 7 DAY)
GROUP BY DATE(FROM_UNIXTIME(timecreated))
ORDER BY date DESC;
```

---

**Version:** 3.1.4 (Build 2026020604)  
**Date:** 6 فبراير 2026  
**Status:** ✅ Ready for Testing
