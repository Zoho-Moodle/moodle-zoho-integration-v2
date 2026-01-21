# 📚 أمثلة الاستخدام - Moodle Zoho Integration API

## 🚀 البدء السريع

### تشغيل الخادم
```bash
cd backend
python -m uvicorn app.main:app --host 127.0.0.1 --port 8001
```

الخادم يعمل الآن على: `http://127.0.0.1:8001`

---

## 🔍 الـ Endpoints المتاحة

### 1. Health Check
```bash
# الطلب:
GET /v1/health

# الاستجابة:
{
  "status": "ok",
  "message": "API is healthy"
}
```

### 2. مزامنة الطلاب من Zoho
```bash
POST /v1/sync/students
```

---

## 📋 أمثلة على الطلبات

### مثال 1: طلب JSON بسيط

```bash
curl -X POST http://127.0.0.1:8001/v1/sync/students \
  -H "Content-Type: application/json" \
  -d '{
    "records": [
      {
        "id": "zoho_student_001",
        "Name": "أحمد محمد علي",
        "Academic_Email": "ahmed.ali@university.edu",
        "Phone": "+966501234567",
        "Status": "active"
      }
    ]
  }'
```

**الاستجابة:**
```json
{
  "status": "success",
  "idempotency_key": "a1b2c3d4e5f6...",
  "results": [
    {
      "zoho_student_id": "zoho_student_001",
      "status": "NEW",
      "message": "Student created"
    }
  ]
}
```

---

### مثال 2: طلب متعدد الطلاب

```bash
curl -X POST http://127.0.0.1:8001/v1/sync/students \
  -H "Content-Type: application/json" \
  -d '{
    "records": [
      {
        "id": "zoho_001",
        "Name": "فاطمة محمود",
        "Academic_Email": "fatima@university.edu",
        "Phone": "+966551234567",
        "Status": "active"
      },
      {
        "id": "zoho_002",
        "Name": "محمد سالم",
        "Academic_Email": "mohammad@university.edu",
        "Phone": "+966561234567",
        "Status": "active"
      },
      {
        "id": "zoho_003",
        "Name": "نور خالد",
        "Academic_Email": "noor@university.edu",
        "Status": "inactive"
      }
    ]
  }'
```

**الاستجابة:**
```json
{
  "status": "success",
  "idempotency_key": "xyz123abc456...",
  "results": [
    {
      "zoho_student_id": "zoho_001",
      "status": "NEW",
      "message": "Student created"
    },
    {
      "zoho_student_id": "zoho_002",
      "status": "NEW",
      "message": "Student created"
    },
    {
      "zoho_student_id": "zoho_003",
      "status": "NEW",
      "message": "Student created"
    }
  ]
}
```

---

### مثال 3: تحديث طالب موجود

```bash
# الطلب الأول (إنشاء):
curl -X POST http://127.0.0.1:8001/v1/sync/students \
  -H "Content-Type: application/json" \
  -d '{
    "records": [{
      "id": "zoho_update_test",
      "Name": "سارة أحمد",
      "Academic_Email": "sarah@university.edu",
      "Phone": "+966501111111"
    }]
  }'

# الاستجابة الأولى (NEW):
{
  "zoho_student_id": "zoho_update_test",
  "status": "NEW",
  "message": "Student created"
}

# الطلب الثاني بنفس البيانات (محاولة إدراج مكررة):
curl -X POST http://127.0.0.1:8001/v1/sync/students \
  -H "Content-Type: application/json" \
  -d '{
    "records": [{
      "id": "zoho_update_test",
      "Name": "سارة أحمد",
      "Academic_Email": "sarah@university.edu",
      "Phone": "+966501111111"
    }]
  }'

# الاستجابة الثانية (UNCHANGED):
{
  "zoho_student_id": "zoho_update_test",
  "status": "UNCHANGED",
  "message": "No changes detected"
}

# الطلب الثالث مع بيانات محدثة:
curl -X POST http://127.0.0.1:8001/v1/sync/students \
  -H "Content-Type: application/json" \
  -d '{
    "records": [{
      "id": "zoho_update_test",
      "Name": "سارة أحمد",
      "Academic_Email": "sarah@university.edu",
      "Phone": "+966502222222"  # رقم جديد
    }]
  }'

# الاستجابة الثالثة (UPDATED):
{
  "zoho_student_id": "zoho_update_test",
  "status": "UPDATED",
  "message": "Student data updated",
  "changed": {
    "phone": ["+966501111111", "+966502222222"]
  }
}
```

---

### مثال 4: طلب مع بيانات ناقصة (سيفشل)

```bash
curl -X POST http://127.0.0.1:8001/v1/sync/students \
  -H "Content-Type: application/json" \
  -d '{
    "records": [
      {
        "Name": "عبد الله محمد",
        "Academic_Email": "abdullah@university.edu"
        # ❌ مفقود: id (zoho_id)
      }
    ]
  }'
```

**الاستجابة:**
```json
{
  "status": "success",
  "idempotency_key": "...",
  "results": [
    {
      "zoho_student_id": "unknown",
      "status": "INVALID",
      "message": "Failed to parse record"
    }
  ]
}
```

---

### مثال 5: Idempotency (منع التكرار)

```bash
# نفس الطلب مرتين:
PAYLOAD='{
  "records": [{
    "id": "zoho_idem_test",
    "Name": "اختبار",
    "Academic_Email": "test@university.edu"
  }]
}'

# الطلب الأول:
curl -X POST http://127.0.0.1:8001/v1/sync/students \
  -H "Content-Type: application/json" \
  -d "$PAYLOAD"

# النتيجة: "status": "NEW"

# الطلب الثاني (نفس الـ payload):
curl -X POST http://127.0.0.1:8001/v1/sync/students \
  -H "Content-Type: application/json" \
  -d "$PAYLOAD"

# النتيجة: "status": "ignored", "reason": "duplicate_request"
# لأن الـ idempotency_key نفسه
```

---

## 🔄 حالات الـ Response المختلفة

### Status: NEW
```json
{
  "zoho_student_id": "123",
  "status": "NEW",
  "message": "Student created"
}
```
- الطالب لم يكن موجوداً، تم إنشاؤه الآن

### Status: UNCHANGED
```json
{
  "zoho_student_id": "123",
  "status": "UNCHANGED",
  "message": "No changes detected"
}
```
- الطالب موجود والبيانات نفسها

### Status: UPDATED
```json
{
  "zoho_student_id": "123",
  "status": "UPDATED",
  "message": "Student data updated",
  "changed": {
    "phone": ["+966501111111", "+966502222222"],
    "status": ["active", "inactive"]
  }
}
```
- الطالب موجود وتم تحديث بعض البيانات

### Status: INVALID
```json
{
  "zoho_student_id": "unknown",
  "status": "INVALID",
  "message": "Missing required fields"
}
```
- البيانات غير صحيحة، لم يتم حفظها

### Status: ERROR
```json
{
  "zoho_student_id": "123",
  "status": "ERROR",
  "message": "Database error: ..."
}
```
- حدث خطأ في المعالجة

---

## 🧪 اختبار باستخدام Python

### بدون مكتبات خارجية:

```python
import json
import urllib.request

url = "http://127.0.0.1:8001/v1/sync/students"
data = {
    "records": [
        {
            "id": "python_test_001",
            "Name": "اختبار بايثون",
            "Academic_Email": "python@test.edu",
            "Phone": "+966501234567"
        }
    ]
}

headers = {"Content-Type": "application/json"}
req = urllib.request.Request(
    url,
    data=json.dumps(data).encode('utf-8'),
    headers=headers,
    method='POST'
)

with urllib.request.urlopen(req) as response:
    result = json.loads(response.read())
    print(json.dumps(result, indent=2, ensure_ascii=False))
```

### باستخدام requests:

```python
import requests

url = "http://127.0.0.1:8001/v1/sync/students"
data = {
    "records": [
        {
            "id": "py_requests_001",
            "Name": "اختبار requests",
            "Academic_Email": "requests@test.edu"
        }
    ]
}

response = requests.post(url, json=data)
print(response.json())
```

---

## 🔧 اختبار باستخدام curl (في Windows PowerShell)

```powershell
$uri = "http://127.0.0.1:8001/v1/sync/students"
$body = @{
    records = @(
        @{
            id = "ps_test_001"
            Name = "اختبار باوور شيل"
            Academic_Email = "ps@test.edu"
            Phone = "+966501234567"
        }
    )
} | ConvertTo-Json

Invoke-WebRequest -Uri $uri -Method Post `
  -ContentType "application/json" `
  -Body $body
```

---

## 📊 اختبار الأداء

### كم عدد الطلاب في طلب واحد؟

```python
# يمكنك إرسال مئات الطلاب في طلب واحد:
import json
import urllib.request

students = [
    {
        "id": f"zoho_{i:04d}",
        "Name": f"الطالب رقم {i}",
        "Academic_Email": f"student{i}@university.edu",
        "Phone": f"+966{5000000000 + i}",
        "Status": "active"
    }
    for i in range(1, 101)  # 100 طالب
]

data = {"records": students}

# إرسال الطلب...
```

---

## 🐛 استكشاف الأخطاء

### 1. "Connection refused"
```
❌ المشكلة: الخادم لم يبدأ
✅ الحل: تأكد من تشغيل:
   python -m uvicorn app.main:app --host 127.0.0.1 --port 8001
```

### 2. "404 Not Found"
```
❌ المشكلة: المسار خاطئ
✅ الحل: استخدم /v1/sync/students (كامل المسار)
```

### 3. "400 Bad Request"
```
❌ المشكلة: بيانات JSON غير صحيحة
✅ الحل: تحقق من صيغة JSON والـ Content-Type header
```

### 4. "500 Internal Server Error"
```
❌ المشكلة: خطأ في المعالجة
✅ الحل: تحقق من سجلات الخادم (LOG_LEVEL=DEBUG)
```

---

## 📝 الـ HTTP Headers المهمة

```bash
Content-Type: application/json       # نوع البيانات
Accept: application/json             # نوع الاستجابة المطلوب
User-Agent: MyClient/1.0             # معرف العميل
```

---

**تم! استمتع باستخدام API 🚀**
