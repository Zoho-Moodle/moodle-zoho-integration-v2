# 🎯 الخطوة الأولى: استخرج العينات الآن!

## اتبع هذه الأوامر مباشرة:

```bash
# 1. انتقل للـ backend folder
cd c:\Users\MohyeddineFarhat\Documents\GitHub\moodle-zoho-integration-v2\backend

# 2. استخرج البيانات (انسخ والصق الأوامر هذي):

curl "http://localhost:8001/v1/debug/module/BTEC_Enrollments/sample?count=3" > enrollments_sample.json && echo "✅ Enrollments"

curl "http://localhost:8001/v1/debug/module/BTEC_Classes/sample?count=3" > classes_sample.json && echo "✅ Classes"

curl "http://localhost:8001/v1/debug/module/BTEC_Registrations/sample?count=3" > registrations_sample.json && echo "✅ Registrations"

curl "http://localhost:8001/v1/debug/module/BTEC_Payments/sample?count=3" > payments_sample.json && echo "✅ Payments"

curl "http://localhost:8001/v1/debug/module/BTEC_Grades/sample?count=3" > grades_sample.json && echo "✅ Grades"

curl "http://localhost:8001/v1/debug/module/Products/sample?count=3" > products_sample.json && echo "✅ Products"

curl "http://localhost:8001/v1/debug/module/Contacts/sample?count=3" > contacts_sample.json && echo "✅ Contacts"
```

## بعد تشغيل الأوامر:

### 1. تأكد أن الملفات موجودة:
```bash
dir *.json
```

### 2. افتح الملفات في VS Code:

```
File → Open Folder → backend
ثم افتح enrollments_sample.json
```

### 3. ادرس كل ملف:

لكل ملف JSON، اكتب:

```
# BTEC_Enrollments
الحقول المكتشفة:
- ...

هل يطابق الكود الحالي؟
- ...
```

---

## ✅ بعد ما تخلص:

قول لي:
- **كم حقل فيه في BTEC_Enrollments؟**
- **ما أسماء الحقول الرئيسية؟**
- **هل فيه حقول جديدة ما كنا نتوقعها؟**

وأنا سأساعدك مع التعديلات!

