# 🎯 خطوات الاستخدام - Zoho Format Discovery

## الخطوة 1️⃣: شغّل الـ Server

افتح PowerShell وشغّل:

```powershell
cd "c:\Users\MohyeddineFarhat\Documents\GitHub\moodle-zoho-integration-v2\backend"
python start_server.py
```

ستشوف:
```
INFO:     Application startup complete.
INFO:     Uvicorn running on http://0.0.0.0:8000 (Press CTRL+C to quit)
```

---

## الخطوة 2️⃣: استخدم Postman

### المجموعة المتاحة:
في الـ Postman Collection الجديدة، اختر:
- **Debug - Zoho Format Analysis** (الفئة الجديدة)

### الـ Endpoints:

| Endpoint | الهدف |
|----------|------|
| `POST /v1/debug/webhook/zoho` | استقبل data من Zoho |
| `GET /v1/debug/data` | شوف كل الـ data |
| `GET /v1/debug/data/{type}` | شوف نوع معين |
| `GET /v1/debug/data/{type}/latest` | آخر record |
| `POST /v1/debug/format-analysis` | حلل الـ format |
| `DELETE /v1/debug/data` | امسح كل الـ data |

---

## الخطوة 3️⃣: في Zoho، شغّل Functions

انسخ الـ functions من ملف `ZOHO_DEBUG_SETUP.md`:

```javascript
sendProductsToWebhook();
sendClassesToWebhook();
sendContactsToWebhook();
sendEnrollmentsToWebhook();
```

---

## الخطوة 4️⃣: شوف الـ Data اللي استقبلتها

### في Postman:
- اختر: **View All Collected Data**
- اضغط: **Send**

ستحصل على كل الـ data اللي استقبلتها الـ API

---

## الخطوة 5️⃣: حلل الـ Format

### في Postman:
- اختر: **Analyze Format**
- اضغط: **Send**

ستشوف:
```json
{
  "products": {
    "count": 5,
    "fields": ["id", "Product_Name", "Price", "status", ...],
    "sample": { ... }
  },
  "classes": {
    "count": 3,
    "fields": ["id", "BTEC_Class_Name", ...],
    "sample": { ... }
  }
}
```

---

## الخطوة 6️⃣: بناء الـ Parsers

بناءً على الـ fields والـ format اللي استقبلتها:

1. اقرأ الـ format بحذر
2. لاحظ:
   - الـ required fields
   - الـ data types
   - الـ nested objects
   - الـ field names بالضبط

3. اكتب parsers محددة وبسيطة

---

## 📝 مثال - Format اللي قد تستقبلها:

### Products من Zoho:
```json
{
  "id": "111111111111111111",
  "Product_Name": "Python Programming",
  "Price": "299.99",
  "status": "Active",
  "created_time": "2024-01-20T10:30:00Z",
  "updated_time": "2024-01-20T15:45:00Z",
  "Product_Code": "PYTHON101",
  "Description": "Learn Python from scratch"
}
```

### Classes من Zoho:
```json
{
  "id": "222222222222222222",
  "BTEC_Class_Name": "Python 101 - Basics",
  "Class_Short_Name": "PY101",
  "Start_Date": "2024-02-01",
  "End_Date": "2024-06-30",
  "Class_Status": "Active",
  "BTEC_Program": {
    "id": "111111111111111111"
  },
  "Teacher": {
    "id": "333333333333333333"
  }
}
```

### Contacts (Students) من Zoho:
```json
{
  "id": "444444444444444444",
  "First_Name": "Ahmed",
  "Last_Name": "Ali",
  "Email": "ahmed@example.com",
  "Phone": "+966512345678",
  "Mailing_Street": "123 Main St",
  "Mailing_City": "Riyadh",
  "Mailing_Country": "Saudi Arabia",
  "Created_Time": "2024-01-20T10:30:00Z"
}
```

### Enrollments من Zoho:
```json
{
  "id": "555555555555555555",
  "Student": {
    "id": "444444444444444444"
  },
  "BTEC_Class": {
    "id": "222222222222222222"
  },
  "Enrollment_Status": "Active",
  "Enrollment_Date": "2024-02-01",
  "Completion_Status": "In Progress"
}
```

---

## ✅ الفائدة من هذا النهج:

1. **دقة 100%** - تشتغل مع الـ format الفعلي
2. **بدون تخمين** - الـ data تتكلم عن نفسها
3. **parsers بسيطة** - mapping مباشر بدون معالجة معقدة
4. **أقل bugs** - بناء على الحقيقة مش التوقعات

---

## 🚀 الخطوة الأخيرة:

بعد ما تشوف الـ format:
1. انسخ الـ JSON example
2. اكتب parser محدد ومباشر
3. اختبر مع الـ data الفعلية
4. اكمل!

**جاهز تبدأ؟** 🎯
