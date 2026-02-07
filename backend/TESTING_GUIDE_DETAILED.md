# 🧪 اختبار Zoho Webhook - دليل خطوة بخطوة

## ✅ المتطلبات الأولية
- [x] Backend server شغال
- [ ] ngrok مثبت وشغال
- [ ] Zoho CRM account with admin access
- [ ] Internet connection

---

## 🚀 الخطوة 1: تشغيل Backend Server

### افتح PowerShell نافذة جديدة:
```powershell
cd C:\Users\MohyeddineFarhat\Documents\GitHub\moodle-zoho-integration-v2\backend
python start_server.py
```

**انتظر حتى تشوف:**
```
INFO:     Uvicorn running on http://0.0.0.0:8001 (Press CTRL+C to quit)
```

✅ **اختبار:** في نافذة ثانية:
```powershell
Invoke-WebRequest -Uri "http://localhost:8001/api/v1/events/health" -UseBasicParsing
```
**المتوقع:** StatusCode = 200

---

## 🌐 الخطوة 2: تشغيل ngrok

### 1. تحميل ngrok (إذا ما عندك)
- اذهب إلى: https://ngrok.com/download
- حمل Windows 64-bit version
- فك الضغط في مجلد سهل (مثلاً: `C:\ngrok`)

### 2. تسجيل في ngrok (مجاني)
- اذهب إلى: https://dashboard.ngrok.com/signup
- سجل حساب مجاني
- احصل على authtoken من: https://dashboard.ngrok.com/get-started/your-authtoken

### 3. ربط ngrok بالحساب
```powershell
cd C:\ngrok
.\ngrok config add-authtoken YOUR_AUTH_TOKEN_HERE
```

### 4. تشغيل ngrok (نافذة جديدة)
```powershell
cd C:\ngrok
.\ngrok http 8001
```

**ستشوف شاشة مثل:**
```
ngrok                                                                                      

Session Status                online
Account                       your-email@example.com
Version                       3.x.x
Region                        United States (us)
Latency                       45ms
Web Interface                 http://127.0.0.1:4040
Forwarding                    https://abc123-xyz.ngrok-free.app -> http://localhost:8001

Connections                   ttl     opn     rt1     rt5     p50     p90
                              0       0       0.00    0.00    0.00    0.00
```

**🎯 احفظ الـ Forwarding URL:** `https://abc123-xyz.ngrok-free.app`

⚠️ **مهم:** هذا الـ URL يتغير كل مرة تشغل ngrok (في النسخة المجانية)

---

## 🔧 الخطوة 3: اختبار ngrok

```powershell
# اختبر من الإنترنت
Invoke-WebRequest -Uri "https://YOUR-NGROK-URL.ngrok-free.app/api/v1/events/health" -UseBasicParsing
```

**المتوقع:** StatusCode = 200

---

## 🎛️ الخطوة 4: إعداد Webhook في Zoho CRM

### 1. تسجيل دخول Zoho CRM
- اذهب إلى: https://crm.zoho.com
- سجل دخول بحسابك

### 2. اذهب لإعدادات Webhooks
```
اضغط على أيقونة Settings (⚙️) في الزاوية العليا اليمنى
↓
Developer Space (في القائمة الجانبية)
↓
Actions
↓
Webhooks
↓
اضغط "Configure Webhook"
```

### 3. املأ تفاصيل Webhook

**Basic Details:**
```
Name: BTEC Student Sync - Test
Description: Webhook for syncing student data to backend
Module: BTEC_Students
```

**URL Configuration:**
```
URL to Notify: https://YOUR-NGROK-URL.ngrok-free.app/api/v1/events/zoho/student
Method: POST
```

**When to Trigger:** (اختر الأحداث)
- ☑️ Create
- ☑️ Edit  
- ☑️ Delete

**Request Format:**

اختر **Custom** من القائمة، ثم اضغط "Customize"

**في محرر JSON، احذف كل شي والصق هذا:**

```json
{
  "notification_id": "${CRMID}_${TIMESTAMP}",
  "timestamp": "${CURRENT_TIME}",
  "module": "BTEC_Students",
  "operation": "${OPERATION}",
  "record_id": "${CRMID}",
  "data": {
    "Student_ID_Number": "${BTEC_Students.Student_ID_Number}",
    "Academic_Email": "${BTEC_Students.Academic_Email}",
    "Name": "${BTEC_Students.Name}",
    "Phone": "${BTEC_Students.Phone}",
    "Moodle_User_ID": "${BTEC_Students.Moodle_User_ID}",
    "Date_of_Birth": "${BTEC_Students.Date_of_Birth}",
    "Gender": "${BTEC_Students.Gender}",
    "Address": "${BTEC_Students.Address}",
    "City": "${BTEC_Students.City}",
    "Country": "${BTEC_Students.Country}",
    "Postal_Code": "${BTEC_Students.Postal_Code}",
    "Emergency_Contact_Name": "${BTEC_Students.Emergency_Contact_Name}",
    "Emergency_Contact_Phone": "${BTEC_Students.Emergency_Contact_Phone}",
    "Student_Status": "${BTEC_Students.Student_Status}"
  }
}
```

**Headers:** (Optional - للاختبار نتركها فارغة)
```
(Leave empty for now - HMAC will be disabled for testing)
```

### 4. احفظ Webhook
اضغط **Save**

---

## 🧪 الخطوة 5: اختبار Webhook

### Scenario 1: اختبار يدوي من Zoho

1. اذهب إلى **Webhooks** في Zoho
2. اختر الـ webhook اللي عملته
3. اضغط **Test Webhook**
4. اختر student record موجود
5. اضغط **Send**

**راقب:**
- في نافذة السيرفر: ستشوف log للـ request
- في ngrok web interface (http://127.0.0.1:4040): ستشوف الـ request details

### Scenario 2: تعديل Student حقيقي

1. اذهب إلى **BTEC_Students** module في Zoho
2. افتح أي student record
3. عدل أي field (مثلاً Phone number)
4. احفظ التعديل

**المتوقع:**
- Webhook يرسل تلقائياً
- السيرفر يستقبل الـ event
- تشوف في logs:
  ```
  INFO: 127.0.0.1:xxxxx - "POST /api/v1/events/zoho/student HTTP/1.1" 200 OK
  ```

### Scenario 3: إنشاء Student جديد

1. اذهب إلى **BTEC_Students** module
2. اضغط **+ New Student**
3. املأ البيانات:
   ```
   Student ID Number: TEST001
   Academic Email: test@example.com
   Name: Test Student
   Phone: +1234567890
   ```
4. احفظ

**المتوقع:** Webhook يرسل event "create"

---

## 📊 الخطوة 6: التحقق من النتائج

### 1. شوف Event Statistics
```powershell
Invoke-WebRequest -Uri "http://localhost:8001/api/v1/events/stats" -UseBasicParsing | Select-Object Content
```

**المتوقع:**
```json
{
  "total_events": 5,
  "by_status": {
    "completed": 3,
    "failed": 2
  },
  "by_source": {
    "zoho": 5
  }
}
```

### 2. شوف Database Records
```powershell
cd backend
python -c "from app.infra.db.connection import engine; from sqlalchemy import text; with engine.connect() as conn: result = conn.execute(text('SELECT id, event_id, module, event_type, status, created_at FROM integration_events_log ORDER BY created_at DESC LIMIT 5')); print('\nRecent Events:'); for row in result: print(f'  [{row.status}] {row.module}.{row.event_type} - {row.event_id[:20]}... at {row.created_at}')"
```

### 3. شوف ngrok Web Interface
- افتح browser: http://127.0.0.1:4040
- شوف كل الـ requests
- اضغط على أي request لشوف:
  - Request body
  - Response
  - Headers
  - Timing

---

## 🐛 استكشاف المشاكل

### Problem 1: Webhook returns 404
**الحل:**
```powershell
# تأكد من الـ URL صح
# يجب أن يكون:
https://YOUR-NGROK-URL.ngrok-free.app/api/v1/events/zoho/student
#                                      ^^^^^^^^^^^^^^^^^^^^^^^^^^^^
#                                      لا تنسى /api/v1 prefix!
```

### Problem 2: ngrok session expired
```powershell
# في النسخة المجانية، ngrok ينتهي بعد 2 ساعة
# الحل: شغله من جديد
.\ngrok http 8001

# احصل على URL الجديد وحدث الـ webhook في Zoho
```

### Problem 3: Events marked as "failed"
**الحل:**
```powershell
# شوف الـ error message في database
python -c "from app.infra.db.connection import engine; from sqlalchemy import text; with engine.connect() as conn: result = conn.execute(text('SELECT event_id, error_message FROM integration_events_log WHERE status=\\'failed\\' ORDER BY created_at DESC LIMIT 3')); for row in result: print(f'{row.event_id}: {row.error_message}')"
```

**أسباب شائعة:**
- Student data ناقصة (Academic_Email مطلوب)
- Student ID Number مكرر
- Moodle User ID مش موجود

### Problem 4: HMAC signature error
**الحل:**
```
للاختبار فقط، تأكد إن .env فيه:
ZOHO_WEBHOOK_SECRET=

(فارغ = يتخطى التحقق)

للإنتاج، ضع secret قوي
```

---

## ✅ Checklist

قبل ما تبدأ، تأكد:
- [ ] Backend server شغال (port 8001)
- [ ] Health endpoint يستجيب (200 OK)
- [ ] ngrok شغال ويعرض URL
- [ ] ngrok URL يستجيب من الإنترنت
- [ ] Zoho CRM webhook created
- [ ] Webhook URL صحيح (with /api/v1 prefix)
- [ ] Test student record موجود في Zoho

---

## 📝 نموذج Test Scenarios

### Test 1: Update existing student
1. Edit student phone number
2. Check server logs → 200 OK
3. Check database → event logged
4. Check stats → total_events increased

### Test 2: Create new student
1. Create new student with all fields
2. Check server logs → 200 OK
3. Check database → event logged with operation='insert'
4. Verify student synced to backend

### Test 3: Delete student
1. Delete student record
2. Check server logs → 200 OK
3. Check database → event logged with operation='delete'

### Test 4: Duplicate event (optional)
1. Send same webhook twice manually
2. Check database → second event marked as DUPLICATE
3. Verify deduplication works

---

## 🎉 النجاح يعني:

✅ Webhook يرسل من Zoho بنجاح  
✅ Backend يستقبل ويسجل في database  
✅ Events تظهر في `/api/v1/events/stats`  
✅ No errors في server logs  
✅ ngrok web interface يظهر requests  

---

## 📞 تواصل معي إذا:

- Webhook يرجع 404 أو 500
- Events كلها "failed"
- ngrok ما يشتغل
- أي error غريب في logs

**الآن جاهز للاختبار! 🚀**
