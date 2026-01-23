# 📝 تعليمات الاختبار - بعد التحديث

## 🔄 الخطوات:

### 1. في Zoho Creator - شغّل الـ Function مرة تانية:

```javascript
string standalone.sendStudentDebug()
{
    baseUrl = "https://noncorrespondingly-tractile-ava.ngrok-free.dev";
    webhookUrl = baseUrl + "/v1/debug/webhook/zoho";

    tenantId = "default";

    student = Map();
    student.put("id", "test_zoho_005");  // غيّر الـ ID
    student.put("Name", "Ahmed Ali");
    student.put("Academic_Email", "ahmed.ali@test.com");
    student.put("Phone_Number", "+966512345678");
    student.put("Status", "Active");

    dataList = List();
    dataList.add(student);

    payload = Map();
    payload.put("source", "zoho_students_debug");
    payload.put("module", "BTEC_Students");
    payload.put("data", dataList);

    headersMap = Map();
    headersMap.put("Content-Type", "application/json");
    headersMap.put("X-Tenant-ID", tenantId);

    response = invokeurl
    [
        url : webhookUrl
        type : POST
        headers : headersMap
        parameters : payload.toString()
        connection : "moodlebackend"
    ];

    info response;
    return response.toString();
}
```

### 2. في Postman - اختبر:

```
GET http://localhost:8000/v1/debug/data/students
```

ستشوف:
```json
{
  "type": "students",
  "count": 1,
  "records": [
    {
      "timestamp": "2026-01-21T...",
      "body": {
        "source": "zoho_students_debug",
        "module": "BTEC_Students",
        "data": [
          {
            "id": "test_zoho_005",
            "Name": "Ahmed Ali",
            "Academic_Email": "ahmed.ali@test.com",
            "Phone_Number": "+966512345678",
            "Status": "Active"
          }
        ]
      }
    }
  ]
}
```

### 3. حلل الـ Format:

```
POST http://localhost:8000/v1/debug/format-analysis
```

---

## ✅ ما اللي تم تصحيحه:

1. ✅ الآن يتعرف على `Academic_Email` (مش `email`)
2. ✅ يتعرف على `Phone_Number`
3. ✅ يتعرف على `Name`
4. ✅ يشيك على `source` و `module` أولاً
5. ✅ يدعم multiple field name variants

---

## 🎯 الـ Output الصحيح الآن:

```json
{
  "status": "received",
  "type": "students",  ← ✅ تصحيح!
  "message": "✅ تم استقبال students webhook",
  "timestamp": "2026-01-21T...",
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

## 📊 الـ Format الفعلي:

```json
{
  "source": "zoho_students_debug",
  "module": "BTEC_Students",
  "data": [
    {
      "id": "string",
      "Name": "string",
      "Academic_Email": "string",
      "Phone_Number": "string",
      "Status": "string"
    }
  ]
}
```

**جرّب الآن!** ✅
