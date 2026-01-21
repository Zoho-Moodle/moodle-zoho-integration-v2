# 🚀 البدء السريع - Quick Start

## المشكلة الحالية

الخادم يرجع خطأ:
```
column students.username does not exist
```

---

## الحل في 3 خطوات

### ✅ خطوة 1: إصلاح قاعدة البيانات

```bash
cd backend
python setup_db.py
```

**ما يفعله هذا الأمر:**
- ✅ يفحص قاعدة البيانات
- ✅ يضيف الحقول الناقصة
- ✅ ينشئ الـ indexes المطلوبة

**النتيجة المتوقعة:**
```
✅ متصل بقاعدة البيانات
✅ تم إنشاء الجداول بنجاح
✅ انتهى إعداد قاعدة البيانات
```

---

### ✅ خطوة 2: تشغيل الخادم

```bash
python -m uvicorn app.main:app --host 127.0.0.1 --port 8001
```

**النتيجة المتوقعة:**
```
INFO:     Started server process
INFO:     Application startup complete
INFO:     Uvicorn running on http://127.0.0.1:8001
```

---

### ✅ خطوة 3: اختبار الـ API

```bash
curl -X POST http://127.0.0.1:8001/v1/sync/students \
  -H "Content-Type: application/json" \
  -d '{
    "records": [{
      "id": "123456789",
      "Name": "أحمد محمد",
      "Academic_Email": "ahmed@university.edu",
      "Phone": "+966501234567"
    }]
  }'
```

**النتيجة المتوقعة:**
```json
{
  "status": "success",
  "idempotency_key": "...",
  "results": [
    {
      "zoho_student_id": "123456789",
      "status": "NEW",
      "message": "Student created"
    }
  ]
}
```

---

## 🎉 إذا نجح!

```
✅ status: "success"
✅ results[0].status: "NEW" أو "UNCHANGED" أو "UPDATED"
✅ لا توجد أخطاء
```

---

## ❌ إذا فشل؟

### الخطأ: "connection refused"
```bash
# تأكد من تشغيل الخادم
python -m uvicorn app.main:app --host 127.0.0.1 --port 8001
```

### الخطأ: "column does not exist"
```bash
# قم بتشغيل setup_db.py مجددًا
python setup_db.py
```

### الخطأ: "Could not connect to database"
```bash
# تأكد من:
# 1. PostgreSQL يعمل
# 2. DATABASE_URL صحيح في .env
# 3. قاعدة البيانات موجودة
```

---

## 📊 الحقول المضافة

الـ `setup_db.py` سيضيف:
- `username` - VARCHAR UNIQUE
- `display_name` - VARCHAR
- `moodle_userid` - INTEGER
- `fingerprint` - VARCHAR
- `last_sync` - INTEGER
- `created_at` - TIMESTAMP
- `updated_at` - TIMESTAMP

---

## ✨ بعد النجاح

```
📊 Database: ✅ محدث
🔧 API: ✅ يعمل
✅ Ready to use!
```

---

**تم! الآن استمتع بـ API! 🚀**
