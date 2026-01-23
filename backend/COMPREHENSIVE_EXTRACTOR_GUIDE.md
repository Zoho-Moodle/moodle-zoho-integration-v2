# 📚 دليل استخدام الكود الشامل لاستخراج البيانات من Zoho

## 🎯 ماذا يفعل الكود؟

هذا الكود يقوم بـ:

1. **جلب البيانات من 4 موديولات:**
   - BTEC_Students (الطلاب)
   - Products (المنتجات)
   - BTEC_Classes (الفصول)
   - BTEC_Enrollments (الالتحاقات)

2. **التعامل مع جميع أنواع الحقول:**
   - ✅ نصوص عادية (Text, LongText)
   - ✅ أرقام (Number, Currency, Decimal)
   - ✅ تواريخ (Date)
   - ✅ تاريخ ووقت (DateTime, Timestamp)
   - ✅ قوائم الاختيار (Picklist, MultiSelect)
   - ✅ الروابط البسيطة (Lookup)
   - ✅ الروابط المتعددة (Multi-Lookup)
   - ✅ منطقيات (Boolean, Checkbox)
   - ✅ بريد إلكتروني (Email)
   - ✅ هاتف (Phone)
   - ✅ ملفات (File, Attachment)

3. **إرسال البيانات كاملة إلى الـ webhook:**
   - كل سجل مع جميع حقوله ومعالجتها
   - معلومات وصفية عن كل حقل (النوع، القيمة، البيانات الإضافية)

---

## 🔧 خطوات الإعداد

### 1️⃣ احصل على API Token من Zoho

```
Zoho CRM → Settings → Developer Space → API → Generate Token
```

**ملاحظة:** يجب أن تكون لديك Developer Account أو أن يكون لديك أذونات عالية

### 2️⃣ جهز رابط الـ Webhook

```
https://نفس-الرابط-السابق.ngrok-free.dev/v1/debug/webhook/zoho
```

اتأكد أن الـ ngrok tunnel شغال:

```bash
ngrok http 8000
```

### 3️⃣ انسخ الكود إلى Zoho

اتبع الخطوات:

1. اذهب إلى **Zoho CRM**
2. اضغط على **Settings** (⚙️ أيقونة العجلة)
3. اختر **Developer Space** → **Functions**
4. اضغط على **Create Function**
5. اختر **Deluge**
6. اسم الدالة: `extractComprehensiveData`

### 4️⃣ عدّل البيانات الثلاث الأساسية

في السطور الأولى من الكود:

```javascript
// 👉 استبدل هذه الثلاثة فقط:

string API_TOKEN = "YOUR_API_TOKEN_HERE";
// ↑ الـ token الي حصلت عليه من Zoho

string WEBHOOK_URL = "https://your-ngrok-url.ngrok-free.dev/v1/debug/webhook/zoho";
// ↑ رابط الـ ngrok tunnel بتاعك

string ORG_ID = "org_id_here";
// ↑ اختياري - معرف المؤسسة
```

### 5️⃣ اختبر الكود

```javascript
// اضغط على Execute في Zoho
// يجب أن تشوف في الـ logs:
// ✅ جاري جلب: BTEC_Students
// ✅ جاري جلب: Products
// ✅ جاري جلب: BTEC_Classes
// ✅ جاري جلب: BTEC_Enrollments
// ✅ تم الإرسال بنجاح!
```

---

## 📊 البيانات المرسلة

### الهيكل العام:

```json
{
  "source": "zoho_comprehensive_extractor",
  "module": "all",
  "timestamp": "2026-01-21T10:30:00+00:00",
  "total_modules": 4,
  "data": [
    {
      "module": "BTEC_Students",
      "records": [
        {
          "id": "123456789",
          "fields": {
            "Name": {
              "label": "Name",
              "type": "text",
              "processed": {
                "value": "أحمد محمد",
                "type": "text"
              }
            },
            "Academic_Email": {
              "label": "Academic Email",
              "type": "email",
              "processed": {
                "value": "ahmed@example.com",
                "type": "email"
              }
            },
            "Phone_Number": {
              "label": "Phone Number",
              "type": "phone",
              "processed": {
                "value": "+201001234567",
                "type": "phone"
              }
            },
            "Status": {
              "label": "Status",
              "type": "picklist",
              "processed": {
                "value": ["Active"],
                "type": "picklist",
                "count": 1
              }
            },
            "GPA": {
              "label": "GPA",
              "type": "decimal",
              "processed": {
                "value": 3.85,
                "type": "number"
              }
            },
            "Enrollment_Date": {
              "label": "Enrollment Date",
              "type": "date",
              "processed": {
                "value": "2026-01-15",
                "type": "date"
              }
            },
            "Last_Login": {
              "label": "Last Login",
              "type": "datetime",
              "processed": {
                "value": "2026-01-21 10:15:30",
                "type": "datetime"
              }
            },
            "Program": {
              "label": "Program",
              "type": "lookup",
              "processed": {
                "value": {
                  "id": "prog_123",
                  "name": "Software Engineering"
                },
                "type": "lookup"
              }
            }
          }
        }
      ],
      "status": "success",
      "count": 50
    }
  ]
}
```

---

## 🔍 أنواع الحقول المعالجة

### 1. النصوص (Text Fields)
```
API Name: name, description, address, etc.
Processed: {"value": "النص هنا", "type": "text"}
```

### 2. الأرقام (Number Fields)
```
API Name: amount, quantity, price, etc.
Processed: {"value": 123.45, "type": "number"}
للعملات: {"value": 999.99, "type": "number", "is_currency": true}
```

### 3. التواريخ (Date)
```
API Name: date_of_birth, registration_date
Processed: {"value": "2000-05-15", "type": "date"}
```

### 4. التاريخ والوقت (DateTime)
```
API Name: created_time, modified_time
Processed: {"value": "2026-01-21 10:30:45", "type": "datetime"}
```

### 5. قوائم الاختيار (Picklist)
```
API Name: status, category, priority
Single: {"value": ["Active"], "type": "picklist", "count": 1}
Multi:  {"value": ["Active", "Verified"], "type": "picklist", "count": 2}
```

### 6. الروابط البسيطة (Lookup)
```
API Name: program, department
Processed: {
  "value": {
    "id": "prog_123",
    "name": "Software Engineering"
  },
  "type": "lookup"
}
```

### 7. الروابط المتعددة (Multi-Lookup)
```
API Name: related_programs, courses
Processed: {
  "value": [
    {"id": "prog_123", "name": "Program 1"},
    {"id": "prog_456", "name": "Program 2"}
  ],
  "type": "multi_lookup",
  "count": 2
}
```

### 8. المنطقيات (Boolean)
```
API Name: is_active, is_verified
Processed: {"value": true, "type": "boolean"}
```

### 9. البريد الإلكتروني (Email)
```
API Name: email, contact_email
Processed: {"value": "user@example.com", "type": "email"}
```

### 10. الهاتف (Phone)
```
API Name: phone, mobile
Processed: {"value": "+201001234567", "type": "phone"}
```

### 11. الملفات (Attachments)
```
API Name: documents, certificates
Processed: {
  "value": [
    {"file_name": "cert.pdf", "file_size": 12345}
  ],
  "type": "attachment",
  "count": 1
}
```

---

## 🚀 خطوات التشغيل

### الخطوة 1: اختبر الاتصال
```
تأكد أن:
✅ ngrok tunnel شغال
✅ الـ API token صحيح
✅ الـ webhook URL صحيح
```

### الخطوة 2: شغّل الدالة
```
في Zoho → Developer Space → Functions
اختر الدالة → اضغط Execute
```

### الخطوة 3: راقب الـ Logs
```
يجب أن تشوف:
🔍 جاري جلب: BTEC_Students
📊 BTEC_Students: 50 سجلات، 25 حقول
🔍 جاري جلب: Products
...
✅ تم الإرسال بنجاح!
```

### الخطوة 4: شوف النتائج في API
```
GET /v1/debug/data
GET /v1/debug/data/students
GET /v1/debug/data/students/latest
```

---

## ⚙️ معالجة الأخطاء

### إذا حصل خطأ:

#### ❌ "Authorization failed"
```
✓ تحقق من صحة API Token
✓ تأكد أنه ما انتهى (صلاحيته ساعة واحدة فقط!)
✓ توليد token جديد إذا لزم الحال
```

#### ❌ "Connection timeout"
```
✓ تأكد أن ngrok شغال: ngrok http 8000
✓ انسخ الـ URL الصحيح من ngrok
✓ تأكد أن الـ webhook URL محدث
```

#### ❌ "Module not found"
```
✓ تحقق من اسم الموديول في Zoho (حالة الأحرف مهمة!)
✓ تأكد أن لديك أذونات الوصول للموديول
✓ في الأخطاء، الكود يستمر ويرسل البيانات المتاحة
```

---

## 💡 نصائح مهمة

### 1. الأداء
- الكود يجلب حتى 100 سجل لكل موديول
- إذا أردت أكثر، اطلب صفحات إضافية
- التأخير بين الطلبات 0.5 ثانية (تجنب حد المعدل)

### 2. الأمان
- لا تشارك API Token مع أحد!
- الـ token يصلح لمدة ساعة فقط
- لكل توليد token جديد، تحتاج تشغيل الدالة مجددا

### 3. التطوير
- يمكنك تعديل الموديولات: `list modules_to_fetch = {...}`
- يمكنك إضافة شروط على الحقول
- يمكنك تغيير عدد الحقول في الطلب: `fields=*`

### 4. الموثوقية
- إذا حصل خطأ في موديول، الكود يستمر
- الأخطاء تُسجل في الـ logs
- البيانات المتاحة ترسل حتى لو حصل خطأ

---

## 📞 استكشاف الأخطاء

### شوف الـ Logs:
```
Settings → Developer Space → Functions → [Your Function] → Logs
```

### معلومات للمراقبة:
```
✅ كم سجل جُلب من كل موديول؟
✅ كم حقل معالج؟
✅ هل الـ webhook استقبل البيانات؟
✅ هل المعالجة صحيحة لكل نوع حقل؟
```

---

## 🎓 مثال عملي كامل

### 1. نسخ الكود المختصر (COMPREHENSIVE_ZOHO_FUNCTION_SIMPLE.zdeluge)

### 2. عدّل السطور الثلاث:

```javascript
string API_TOKEN = "1000.abcdef123456..."; // من Zoho Settings
string WEBHOOK_URL = "https://noncorrespondingly-tractile-ava.ngrok-free.dev/v1/debug/webhook/zoho"; // من ngrok
string ORG_ID = "org_123456"; // اختياري
```

### 3. شغّل الدالة في Zoho

### 4. ادخل على الـ API:
```
GET /v1/debug/data
```

### 5. شوف جميع الموديولات والحقول!

---

## 🔗 الروابط المفيدة

- **Zoho CRM API Docs:** https://www.zoho.com/crm/developer/docs/api/v2/
- **Zoho Deluge Docs:** https://www.zoho.com/deluge/docs/
- **Your Webhook:** https://your-ngrok-url.ngrok-free.dev/v1/debug/webhook/zoho
- **API Debug Endpoints:** /v1/debug/data

---

## ✅ Checklist قبل التشغيل

- [ ] API Token من Zoho (صحيح وسارٍ)
- [ ] ngrok tunnel شغال على port 8000
- [ ] الـ webhook URL محدث من ngrok
- [ ] الكود منسوخ إلى Zoho Developer Space
- [ ] البيانات الثلاث الأساسية محدثة
- [ ] لديك access للموديولات المطلوبة
- [ ] الـ server (FastAPI) شغال

**بعدها اضغط Execute! 🚀**

