# 🔧 تحديث قاعدة البيانات - Database Migration

## ❌ المشكلة

```
UndefinedColumn: column students.username does not exist
```

جدول `students` الحالي **لم يتم تحديثه** ليطابق النموذج الجديد الذي أنشأناه.

---

## ✅ الحل

### الخيار 1: استخدام Python Script (الأسهل)

```bash
cd backend
python migrate_db.py
```

هذا سيفحص قاعدة البيانات ويخبرك بما يحتاج إلى تحديثه.

---

### الخيار 2: تحديث يدوي باستخدام psql

```bash
# اتصل بقاعدة البيانات
psql -U admin -d moodle_zoho

# ثم قم بتشغيل الأوامر التالية:
```

#### إضافة الحقول الناقصة:

```sql
-- إضافة الحقول الجديدة
ALTER TABLE students ADD COLUMN IF NOT EXISTS username VARCHAR UNIQUE;
ALTER TABLE students ADD COLUMN IF NOT EXISTS display_name VARCHAR;
ALTER TABLE students ADD COLUMN IF NOT EXISTS moodle_userid INTEGER;
ALTER TABLE students ADD COLUMN IF NOT EXISTS fingerprint VARCHAR;
ALTER TABLE students ADD COLUMN IF NOT EXISTS last_sync INTEGER;
ALTER TABLE students ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE students ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- إنشاء indexes
CREATE INDEX IF NOT EXISTS idx_students_username ON students(username);
CREATE INDEX IF NOT EXISTS idx_students_moodle_userid ON students(moodle_userid);
```

#### أو: إعادة إنشاء الجدول من الصفر

إذا كنت تريد حذف الجدول وإنشاء جديد:

```sql
-- حذر: هذا سيحذف جميع البيانات!
DROP TABLE IF EXISTS students;

-- إنشاء جدول جديد
CREATE TABLE students (
    zoho_id VARCHAR PRIMARY KEY,
    username VARCHAR UNIQUE NOT NULL,
    academic_email VARCHAR UNIQUE NOT NULL,
    
    display_name VARCHAR,
    phone VARCHAR,
    status VARCHAR,
    
    moodle_userid INTEGER,
    fingerprint VARCHAR,
    last_sync INTEGER,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- إنشاء indexes
CREATE INDEX idx_students_username ON students(username);
CREATE INDEX idx_students_academic_email ON students(academic_email);
CREATE INDEX idx_students_moodle_userid ON students(moodle_userid);
```

---

### الخيار 3: استخدام ملف SQL مباشرة

```bash
psql -U admin -d moodle_zoho -f DATABASE_MIGRATION.sql
```

---

## 🔍 التحقق من الجدول الحالي

للتحقق من الحقول الموجودة:

```sql
SELECT column_name, data_type, is_nullable 
FROM information_schema.columns 
WHERE table_name = 'students';
```

---

## 📊 الحقول المطلوبة

الجدول الجديد يجب أن يحتوي على:

| الحقل | النوع | الملاحظات |
|------|------|---------|
| `zoho_id` | VARCHAR | Primary Key |
| `username` | VARCHAR | Unique, من Zoho email |
| `academic_email` | VARCHAR | Unique |
| `display_name` | VARCHAR | الاسم الكامل |
| `phone` | VARCHAR | رقم الهاتف |
| `status` | VARCHAR | حالة الطالب |
| `moodle_userid` | INTEGER | معرف مودل (nullable) |
| `fingerprint` | VARCHAR | SHA256 للـ change detection |
| `last_sync` | INTEGER | Unix timestamp آخر مزامنة |
| `created_at` | TIMESTAMP | وقت الإنشاء |
| `updated_at` | TIMESTAMP | وقت آخر تحديث |

---

## ⚠️ ملاحظات مهمة

### المشكلة الأساسية:

قاعدة البيانات الحالية تحتوي على جدول `students` بحقول قديمة:
- قد تحتوي على `name` بدلاً من `display_name`
- قد تحتوي على `email` بدلاً من `academic_email`
- قد تفتقد حقول مثل `username`, `moodle_userid`, `fingerprint`

### الحل:

يجب إما:
1. **إضافة الحقول الناقصة** (ترجع الحفاظ على البيانات الموجودة)
2. **إعادة إنشاء الجدول** (ستفقد البيانات الموجودة)

---

## 🚀 بعد التحديث

بعد تحديث قاعدة البيانات:

```bash
# اختبر الخادم
python -m uvicorn app.main:app --host 127.0.0.1 --port 8001

# أرسل طلب test
curl -X POST http://127.0.0.1:8001/v1/sync/students \
  -H "Content-Type: application/json" \
  -d '{"records":[{"id":"test123","Name":"Test","Academic_Email":"test@example.com"}]}'
```

---

## 🐛 استكشاف الأخطاء

### الخطأ: "relation does not exist"
- المشكلة: جدول `students` لم ينُشأ
- الحل: استخدم الأمر `CREATE TABLE`

### الخطأ: "duplicate key value"
- المشكلة: محاولة إدراج duplicate في unique column
- الحل: تأكد من uniqueness في البيانات

### الخطأ: "column does not exist"
- المشكلة: حقل مفقود في الجدول
- الحل: استخدم `ALTER TABLE ADD COLUMN`

---

## 📞 الدعم

إذا واجهت مشاكل:

1. تحقق من وجود PostgreSQL
2. تأكد من DATABASE_URL صحيح في `.env`
3. تحقق من وجود قاعدة البيانات: `CREATE DATABASE moodle_zoho;`
4. استخدم `migrate_db.py` لتشخيص المشكلة

---

**بعد إتمام التحديث، الخادم سيعمل بدون مشاكل! ✅**
