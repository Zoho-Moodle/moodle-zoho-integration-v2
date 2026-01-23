# 📊 Zoho Data Format Specifications

## تم اكتشافه من الاختبار الفعلي ✅

---

## 📋 Students Format

### Structure:
```json
{
  "source": "zoho_students_debug",
  "module": "BTEC_Students",
  "data": [
    {
      "id": "test_zoho_004",
      "Name": "A01B9999C",
      "Academic_Email": "mahmoud@test.com",
      "Phone_Number": "+201234567894",
      "Status": "Active"
    }
  ]
}
```

### Fields الموجودة:
| Field | Type | مثال | ملاحظات |
|-------|------|------|--------|
| `id` | string | `test_zoho_004` | معرّف فريد من Zoho |
| `Name` | string | `A01B9999C` أو `Ahmed Ali` | اسم الطالب |
| `Academic_Email` | string | `mahmoud@test.com` | البريد الأكاديمي |
| `Phone_Number` | string | `+201234567894` | رقم الهاتف |
| `Status` | string | `Active` | حالة الطالب |

### Metadata:
| Field | القيمة | الهدف |
|-------|--------|-------|
| `source` | `zoho_students_debug` | تحديد نوع الـ data |
| `module` | `BTEC_Students` | اسم الـ module في Zoho |

---

## 🔍 Detection Logic

الكود يتعرف على Students من خلال:

```python
# 1. شيك على source أولاً
if "student" in source:
    return "students"

# 2. أو شيك على module
if "btec_student" in module:
    return "students"

# 3. أو شيك على الـ fields
if any of ["Name", "Academic_Email", "Phone_Number"] in record:
    return "students"
```

---

## 📝 Zoho Code Reference

### الـ Function الكاملة:
```javascript
string standalone.sendStudentDebug()
{
    // الـ base URL (ngrok)
    baseUrl = "https://noncorrespondingly-tractile-ava.ngrok-free.dev";
    webhookUrl = baseUrl + "/v1/debug/webhook/zoho";

    tenantId = "default";

    // إنشاء student record
    student = Map();
    student.put("id", "test_zoho_004");
    student.put("Name", "A01B9999C");
    student.put("Academic_Email", "mahmoud@test.com");
    student.put("Phone_Number", "+201234567894");
    student.put("Status", "Active");

    // ضعه في list
    dataList = List();
    dataList.add(student);

    // أنشئ payload
    payload = Map();
    payload.put("source", "zoho_students_debug");
    payload.put("module", "BTEC_Students");
    payload.put("data", dataList);

    // أرسل الـ request
    response = invokeurl
    [
        url : webhookUrl
        type : POST
        headers : {"Content-Type": "application/json", "X-Tenant-ID": tenantId}
        parameters : payload.toString()
        connection : "moodlebackend"
    ];

    return response.toString();
}
```

---

## ✅ الـ Response الناجح:

```json
{
  "status": "received",
  "type": "students",
  "message": "✅ تم استقبال students webhook",
  "timestamp": "2026-01-21T11:55:51.822715",
  "records_count": {
    "products": 0,
    "classes": 0,
    "enrollments": 0,
    "students": 1,
    "other": 0
  }
}
```

---

## 🎯 الخطوات التالية:

### 1. جرّب أنواع بيانات أخرى:
- Products
- Classes
- Enrollments

### 2. اجمع الـ format specifications:
```
GET /v1/debug/format-analysis
```

### 3. اكتب parsers محددة بناءً على الـ format

### 4. اختبر مع البيانات الحقيقية

---

## 📌 ملاحظات:

- **ID في Zoho:** معرّف فريد في Zoho (مش نفس مودل ID)
- **Email:** اسمه `Academic_Email` (مش `email`)
- **Name:** قد يكون رقم أو اسم
- **Status:** حالة النشاط
- **Source:** مهم جداً لـ detection

---

## 🚀 Ready للـ Real Data؟

استعد للـ format الفعلي من Zoho بـ:
1. Products من قسم Sales
2. Classes من Custom Module
3. Enrollments
4. Real Students Data

**كل واحد قد يكون format مختلف قليلاً!** 📊
