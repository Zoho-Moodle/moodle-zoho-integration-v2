# 📊 دليل الـ Debug Endpoints المحسّنة

## 🚀 نظرة عامة

الـ debug endpoints الجديدة مصممة للتعامل مع البيانات الضخمة من Zoho (أكثر من 12,000 سجل):

- ✅ استقبال البيانات الخام من 8 موديولات
- ✅ تحليل شامل للحقول والأنواع
- ✅ إحصائيات تفصيلية
- ✅ بحث وتصفية متقدم
- ✅ مقارنة بين الموديولات

---

## 📍 Base URL

```
http://localhost:8001/v1/debug
```

---

## 🔗 الـ Endpoints المتاحة

### 1️⃣ استقبال البيانات (Webhook)

**POST** `/webhook/zoho`

استقبل البيانات الخام من Zoho Deluge function

```bash
curl -X POST http://localhost:8001/v1/debug/webhook/zoho \
  -H "Content-Type: application/json" \
  -d '{
    "source": "zoho_discovery",
    "module": "BTEC_Enrollments",
    "records": [...],
    "records_count": 1855
  }'
```

**Response:**
```json
{
  "status": "received",
  "module": "BTEC_Enrollments",
  "type": "enrollments",
  "records_received": 1855,
  "timestamp": "2026-01-21T10:30:00"
}
```

---

### 2️⃣ الإحصائيات العامة

**GET** `/stats`

احصائيات شاملة عن جميع البيانات المستقبلة

```bash
curl http://localhost:8001/v1/debug/stats
```

**Response:**
```json
{
  "total_records": 12185,
  "total_modules": 8,
  "last_update": "2026-01-21T10:30:00",
  "modules": [
    {
      "name": "BTEC_Payments",
      "records": 4000,
      "fields": 15,
      "timestamp": "2026-01-21T10:30:00"
    },
    {
      "name": "BTEC_Registrations",
      "records": 3026,
      "fields": 20,
      "timestamp": "2026-01-21T10:30:00"
    },
    ...
  ]
}
```

---

### 3️⃣ قائمة الموديولات

**GET** `/modules`

قائمة بجميع الموديولات المستقبلة

```bash
curl http://localhost:8001/v1/debug/modules
```

**Response:**
```json
[
  {
    "name": "BTEC_Enrollments",
    "type": "enrollments",
    "record_count": 1855,
    "field_count": 25,
    "status": "received",
    "timestamp": "2026-01-21T10:30:00"
  },
  {
    "name": "Contacts",
    "type": "contacts",
    "record_count": 1378,
    "field_count": 30,
    "status": "received",
    "timestamp": "2026-01-21T10:30:00"
  }
]
```

---

### 4️⃣ تفاصيل موديول معين

**GET** `/module/{module_name}?limit=10&offset=0`

تفاصيل كاملة عن موديول معين مع عينات من السجلات

```bash
# احصل على أول 100 سجل من BTEC_Enrollments
curl "http://localhost:8001/v1/debug/module/BTEC_Enrollments?limit=100&offset=0"

# احصل على السجلات 200-300
curl "http://localhost:8001/v1/debug/module/BTEC_Enrollments?limit=100&offset=200"
```

**Response:**
```json
{
  "module": "BTEC_Enrollments",
  "summary": {
    "total_records": 1855,
    "total_fields": 25,
    "status": "received",
    "received_at": "2026-01-21T10:30:00"
  },
  "fields": {
    "id": {
      "name": "id",
      "type": "text",
      "types_seen": ["text"],
      "coverage": "100.0%",
      "null_percentage": "0.0%",
      "sample_values": ["123456", "123457", "123458"]
    },
    "Student": {
      "name": "Student",
      "type": "object",
      "types_seen": ["object"],
      "coverage": "98.5%",
      "null_percentage": "1.5%",
      "sample_values": [
        "{'id': 'stud_001', 'name': 'Ahmed Mohamed'}",
        "{'id': 'stud_002', 'name': 'Fatima Ali'}"
      ]
    },
    "Status": {
      "name": "Status",
      "type": "text",
      "types_seen": ["text"],
      "coverage": "100.0%",
      "null_percentage": "0.0%",
      "sample_values": ["Active", "Pending", "Completed"]
    }
  },
  "records_sample": {
    "offset": 0,
    "limit": 10,
    "count": 10,
    "data": [
      {
        "id": "123456",
        "Student": {"id": "stud_001", "name": "Ahmed Mohamed"},
        "Class": {"id": "cls_001", "name": "BIS201"},
        "Status": "Active",
        "Enrollment_Date": "2026-01-15"
      }
    ]
  }
}
```

---

### 5️⃣ قائمة الحقول

**GET** `/module/{module_name}/fields`

قائمة تفصيلية بجميع الحقول في الموديول

```bash
curl "http://localhost:8001/v1/debug/module/BTEC_Enrollments/fields"
```

**Response:**
```json
{
  "module": "BTEC_Enrollments",
  "total_fields": 25,
  "fields": [
    {
      "name": "id",
      "api_name": "id",
      "type": "text",
      "types_observed": ["text"],
      "coverage": 100.0,
      "null_percentage": 0.0,
      "example_values": ["eno_001", "eno_002"]
    },
    {
      "name": "Student",
      "api_name": "Student",
      "type": "object",
      "types_observed": ["object", "null"],
      "coverage": 98.5,
      "null_percentage": 1.5,
      "example_values": [
        "{'id': 'stud_001', 'name': 'Student 1'}",
        "{'id': 'stud_002', 'name': 'Student 2'}"
      ]
    }
  ]
}
```

---

### 6️⃣ عينات من السجلات

**GET** `/module/{module_name}/sample?count=5`

احصل على عينات من السجلات بسرعة

```bash
# احصل على أول 10 سجلات
curl "http://localhost:8001/v1/debug/module/BTEC_Enrollments/sample?count=10"

# احصل على أول 5 سجلات (افتراضي)
curl "http://localhost:8001/v1/debug/module/BTEC_Enrollments/sample"
```

**Response:**
```json
{
  "module": "BTEC_Enrollments",
  "total_records": 1855,
  "sample_count": 5,
  "records": [
    {
      "id": "eno_001",
      "Student": {"id": "stud_001", "name": "Ahmed Mohamed"},
      "Class": {"id": "cls_001", "name": "BIS201"},
      "Status": "Active"
    }
  ]
}
```

---

### 7️⃣ البحث والتصفية

**GET** `/search?module=BTEC_Enrollments&field=Status&value=Active&limit=50`

بحث متقدم في البيانات

```bash
# ابحث عن الالتحاقات النشطة
curl "http://localhost:8001/v1/debug/search?module=BTEC_Enrollments&field=Status&value=Active&limit=50"

# ابحث في جميع الموديولات
curl "http://localhost:8001/v1/debug/search?field=id&value=123&limit=100"

# ابحث في موديول معين فقط
curl "http://localhost:8001/v1/debug/search?module=Contacts&field=name&value=Ahmed"
```

**Response:**
```json
{
  "query": {
    "module": "BTEC_Enrollments",
    "field": "Status",
    "value": "Active"
  },
  "results_count": 1200,
  "results": [
    {
      "module": "BTEC_Enrollments",
      "record": {
        "id": "eno_001",
        "Status": "Active",
        "Student": {"id": "stud_001"}
      }
    }
  ]
}
```

---

### 8️⃣ مقارنة الموديولات

**GET** `/comparison`

مقارنة شاملة بين جميع الموديولات

```bash
curl "http://localhost:8001/v1/debug/comparison"
```

**Response:**
```json
{
  "timestamp": "2026-01-21T10:30:00",
  "modules": [
    {
      "name": "BTEC_Payments",
      "records": 4000,
      "fields": 20,
      "status": "received"
    },
    {
      "name": "BTEC_Registrations",
      "records": 3026,
      "fields": 18,
      "status": "received"
    },
    {
      "name": "BTEC_Enrollments",
      "records": 1855,
      "fields": 25,
      "status": "received"
    }
  ],
  "totals": {
    "total_records": 12185,
    "total_modules": 8,
    "total_fields": 180
  }
}
```

---

### 9️⃣ تصدير البيانات

**GET** `/export/{module_name}`

تصدير جميع بيانات الموديول كـ JSON

```bash
curl "http://localhost:8001/v1/debug/export/BTEC_Enrollments" > enrollments.json
```

**Response:**
```json
{
  "module": "BTEC_Enrollments",
  "export_timestamp": "2026-01-21T10:30:00",
  "record_count": 1855,
  "field_count": 25,
  "records": [...],
  "fields_schema": {...}
}
```

---

### 🔟 فحص صحة النظام

**GET** `/health`

فحص صحة نظام الـ debug

```bash
curl "http://localhost:8001/v1/debug/health"
```

**Response:**
```json
{
  "status": "ok",
  "timestamp": "2026-01-21T10:30:00",
  "modules_loaded": 8,
  "total_records": 12185,
  "last_update": "2026-01-21T10:30:00"
}
```

---

### حذف البيانات

**DELETE** `/clear/{module_name}`

حذف بيانات موديول معين

```bash
curl -X DELETE "http://localhost:8001/v1/debug/clear/BTEC_Enrollments"
```

---

**DELETE** `/clear`

حذف جميع البيانات

```bash
curl -X DELETE "http://localhost:8001/v1/debug/clear"
```

---

## 📊 أمثلة عملية

### مثال 1: فهم هيكل البيانات

```bash
# 1. احصل على الإحصائيات
curl http://localhost:8001/v1/debug/stats

# 2. اختر موديول
# مثلاً: BTEC_Enrollments

# 3. احصل على الحقول
curl "http://localhost:8001/v1/debug/module/BTEC_Enrollments/fields"

# 4. احصل على عينات
curl "http://localhost:8001/v1/debug/module/BTEC_Enrollments/sample?count=20"

# 5. احصل على التفاصيل الكاملة
curl "http://localhost:8001/v1/debug/module/BTEC_Enrollments?limit=100"
```

### مثال 2: البحث عن سجل محدد

```bash
# ابحث عن student معين في جميع الموديولات
curl "http://localhost:8001/v1/debug/search?field=Student&value=Ahmed&limit=20"

# ابحث في موديول واحد فقط
curl "http://localhost:8001/v1/debug/search?module=BTEC_Enrollments&field=id&value=eno_001"
```

### مثال 3: المقارنة والإحصائيات

```bash
# قارن جميع الموديولات
curl http://localhost:8001/v1/debug/comparison

# شوف الإحصائيات
curl http://localhost:8001/v1/debug/stats
```

---

## 🎯 حالات الاستخدام

### 1. فهم البيانات الجديدة
```
GET /stats → اختر موديول → GET /module/{name}/fields → GET /module/{name}/sample
```

### 2. التحقق من البيانات
```
GET /search → تحقق من النتائج → GET /export/{module}
```

### 3. المقارنة والتحليل
```
GET /comparison → GET /module/{name} → تحليل الحقول
```

---

## 💾 ملاحظات مهمة

1. **البيانات في الذاكرة**: جميع البيانات محفوظة في الـ RAM فقط
   - تُحذف عند إعادة تشغيل الـ server
   - احفظ النتائج المهمة عبر `/export`

2. **الأداء**: 
   - الـ limit الأقصى: 1000 سجل لكل طلب
   - للبيانات الضخمة، استخدم pagination

3. **البحث**:
   - حساس لحالة الأحرف (case-sensitive للبحث الدقيق)
   - يبحث عن جزء من القيمة (partial match)

4. **التصفية**:
   - استخدم offset و limit للـ pagination
   - offset = عدد السجلات المراد تخطيها
   - limit = عدد السجلات المراد إرجاعها

---

## 🔗 Postman Collection

ستجد جميع الـ endpoints مع أمثلة في:
```
backend/Postman_Collection.json
```

استورد الملف في Postman للحصول على جميع الطلبات جاهزة!

