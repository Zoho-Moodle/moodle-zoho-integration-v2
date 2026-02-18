# 📸 دليل رفع الصور الأوتوماتيكي

## ✨ المزايا الجديدة

تم إضافة **رفع تلقائي للصور** من Zoho attachments إلى Moodle!

### كيف تعمل:

```
Zoho CRM (Personal_photo attachment)
    ↓ تحميل
Backend (تحويل لـ base64)
    ↓ إرسال
Moodle (حفظ تلقائي في student_photos/)
    ↓ عرض
Profile Page (صورة الطالب)
```

---

## 🔧 ما تم تحديثه

### 1️⃣ Backend (`initial_sync.py`):
✅ تحميل الصور من Zoho attachments
✅ تحويل الصور لـ base64
✅ إرسال الصورة مع بيانات الطالب

### 2️⃣ Moodle Web Service (`student_dashboard.php`):
✅ استقبال photo_data (base64)
✅ فك تشفير الصورة
✅ حفظ في `$CFG->dataroot/student_photos/`
✅ تحديث photo_url في قاعدة البيانات

### 3️⃣ UI Pages:
✅ [profile.php](moodle_plugin/ui/student/profile.php) - عرض الصورة
✅ [student_card.php](moodle_plugin/ui/student/student_card.php) - استخدام student_id

---

## 🚀 طريقة الاستخدام

### خيار 1: مزامنة واحد (Manual Sync) ⭐

```bash
cd backend

# 1. حمّل الصورة وأرسلها
python test_auto_photo_upload.py
```

**النتيجة**:
- ✅ الصورة تُحمّل من Zoho
- ✅ تُحوّل لـ base64
- ✅ تُرسل لـ Moodle
- ✅ Moodle يحفظها في `student_photos/`
- ✅ Profile يعرضها تلقائياً

### خيار 2: مزامنة جماعية (Bulk Sync)

```bash
cd backend

# مزامنة كل الطلاب مع صورهم
python initial_sync.py
```

**ما يحدث**:
```
For each student:
1. جلب البيانات من Zoho
2. البحث عن Personal_photo attachment
3. تحميل الصورة
4. تحويلها لـ base64
5. إرسال كل شي لـ Moodle
6. Moodle يحفظ الصورة والبيانات
```

### خيار 3: Webhooks (Real-time)

عند إضافة/تعديل student في Zoho:
```
Zoho Workflow → Webhook → Backend → تحميل الصورة → إرسال لـ Moodle
```

---

## 📁 هيكل الملفات

### على السيرفر:

```
/home/moodledata/lms.abchorizon.com/
├── moodledata/
│   └── student_photos/        ← الصور تُحفظ هنا تلقائياً
│       ├── A01B3660C.jpg
│       ├── A01B3660D.jpg
│       └── ...
└── moodle/
    └── local/
        └── moodle_zoho_sync/
            ├── classes/
            │   └── external/
            │       └── student_dashboard.php  ← يستقبل ويحفظ الصور
            └── ui/
                └── student/
                    └── profile.php  ← يعرض الصور
```

### في قاعدة البيانات:

```sql
mdl_local_mzi_students:
- student_id: 'A01B3660C'
- photo_url: '/student_photos/A01B3660C.jpg'
```

---

## 🧪 الاختبار

### 1. تحميل صورة Omar:
```bash
python download_omar_photo.py
```
✅ النتيجة: `student_photos/A01B3660C.jpg`

### 2. رفع تلقائي لـ Moodle:
```bash
# شغّل البيك إند (terminal 1)
python -m uvicorn app.main:app --reload --port 8001

# اختبر الرفع (terminal 2)
python test_auto_photo_upload.py
```

✅ النتيجة: الصورة موجودة في Moodle تلقائياً

### 3. التحقق:
1. افتح: `https://lms.abchorizon.com/local/moodle_zoho_sync/ui/student/profile.php`
2. راح تشوف صورة Omar
3. افتح Student Card: صورة موجودة في الـ PDF أيضاً

---

## ⚙️ الإعدادات المطلوبة

### 1. صلاحيات المجلد:

```bash
# على السيرفر
sudo mkdir -p /home/moodledata/lms.abchorizon.com/moodledata/student_photos
sudo chmod 755 -R /home/moodledata/lms.abchorizon.com/moodledata/student_photos
sudo chown -R www-data:www-data /home/moodledata/lms.abchorizon.com/moodledata/student_photos
```

### 2. Web Server Config:

تأكد أن `$CFG->dataroot` صحيح في `config.php`:
```php
$CFG->dataroot = '/home/moodledata/lms.abchorizon.com/moodledata';
```

### 3. Upload الملفات المحدثة:

```bash
# الملفات المحدثة:
moodle_plugin/
├── classes/external/student_dashboard.php  ← محدّث (يستقبل الصور)
└── ui/student/
    ├── profile.php                         ← محدّث (يعرض الصور)
    └── student_card.php                    ← محدّث (يستخدم student_id)
```

---

## 🔍 تتبع الأخطاء

### إذا الصورة ما ظهرت:

1. **تحقق من الصلاحيات**:
```bash
ls -la /home/moodledata/lms.abchorizon.com/moodledata/student_photos/
```

2. **تحقق من القاعدة**:
```sql
SELECT student_id, photo_url FROM mdl_local_mzi_students WHERE student_id = 'A01B3660C';
```

3. **تحقق من Moodle logs**:
```php
// في Moodle: Site administration → Reports → Logs
// ابحث عن: Web service (local_moodle_zoho_sync_update_student)
```

4. **تحقق من Backend logs**:
```bash
cd backend
# راح تشوف: "✅ Photo encoded and ready to send"
```

---

## 📊 الإحصائيات

بعد المزامنة الكاملة، راح تشوف:

```
============================================================
📊 SYNC STATISTICS
============================================================
Students:
  - Fetched: 150
  - Synced: 150
  - Failed: 0
  - Photos Downloaded: 145  ← 97% من الطلاب عندهم صور!
============================================================
```

---

## 🎯 الخلاصة

### قبل:
❌ تحميل يدوي للصور
❌ رفع يدوي عبر FTP
❌ تحديث يدوي للقاعدة

### بعد:
✅ **كل شي أوتوماتيكي!**
✅ الصور تُحمّل وتُرفع تلقائياً
✅ Profile يعرضها فوراً
✅ يعمل مع bulk sync و webhooks

---

## 📞 الدعم

إذا واجهتك مشكلة:
1. تحقق من الصلاحيات
2. تحقق من Moodle logs
3. تحقق من Backend logs
4. تأكد أن `$CFG->dataroot` صحيح
