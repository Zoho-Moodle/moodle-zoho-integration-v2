# Zoho Functions Setup Guide

## 🎯 الهدف
إنشاء functions في Zoho تبعت test data لنا عشان نحلل الـ format الفعلي

---

## 📋 الـ Debug Endpoints

### استقبال الـ Data (Raw)
```
POST http://YOUR_SERVER:8000/v1/debug/webhook/zoho
Content-Type: application/json
```

### عرض الـ Data اللي استقبلناها
```
GET http://YOUR_SERVER:8000/v1/debug/data
GET http://YOUR_SERVER:8000/v1/debug/data/products
GET http://YOUR_SERVER:8000/v1/debug/data/classes
GET http://YOUR_SERVER:8000/v1/debug/data/enrollments
GET http://YOUR_SERVER:8000/v1/debug/data/students
```

### آخر Record من نوع معين
```
GET http://YOUR_SERVER:8000/v1/debug/data/products/latest?count=1
```

### تحليل الـ Format
```
POST http://YOUR_SERVER:8000/v1/debug/format-analysis
```

### مسح الـ Data
```
DELETE http://YOUR_SERVER:8000/v1/debug/data
DELETE http://YOUR_SERVER:8000/v1/debug/data/products
```

---

## 🔧 Zoho Functions

### 1️⃣ Zoho Function - Send Products

```javascript
// Function.js في Zoho

function sendProductsToWebhook() {
  // احصل على كل الـ Products
  url = "https://www.zohoapis.com/crm/v2/Products";
  
  response = invokeurl(
    [
      url: url,
      type: "GET",
      headers: map(),
      connection: "zoho_crm"
    ]
  );
  
  if (response.get("code") == 200) {
    products = response.get("data");
    
    // أرسل إلى الـ debug endpoint
    webhookUrl = "http://YOUR_SERVER:8000/v1/debug/webhook/zoho";
    
    payload = {
      "data": products,
      "source": "zoho_products",
      "timestamp": now
    };
    
    webhookResponse = invokeurl(
      [
        url: webhookUrl,
        type: "POST",
        headers: {
          "Content-Type": "application/json"
        },
        body: payload
      ]
    );
    
    info("Products sent: " + products.size());
    return webhookResponse;
  }
}
```

### 2️⃣ Zoho Function - Send Classes

```javascript
function sendClassesToWebhook() {
  // احصل على Custom Module اللي فيه الـ Classes
  url = "https://www.zohoapis.com/crm/v2/BTEC_Classes";
  
  response = invokeurl(
    [
      url: url,
      type: "GET",
      headers: map(),
      connection: "zoho_crm"
    ]
  );
  
  if (response.get("code") == 200) {
    classes = response.get("data");
    
    webhookUrl = "http://YOUR_SERVER:8000/v1/debug/webhook/zoho";
    
    payload = {
      "data": classes,
      "source": "zoho_classes",
      "timestamp": now
    };
    
    webhookResponse = invokeurl(
      [
        url: webhookUrl,
        type: "POST",
        headers: {
          "Content-Type": "application/json"
        },
        body: payload
      ]
    );
    
    info("Classes sent: " + classes.size());
    return webhookResponse;
  }
}
```

### 3️⃣ Zoho Function - Send Contacts (Students)

```javascript
function sendContactsToWebhook() {
  url = "https://www.zohoapis.com/crm/v2/Contacts";
  
  response = invokeurl(
    [
      url: url,
      type: "GET",
      headers: map(),
      connection: "zoho_crm"
    ]
  );
  
  if (response.get("code") == 200) {
    contacts = response.get("data");
    
    webhookUrl = "http://YOUR_SERVER:8000/v1/debug/webhook/zoho";
    
    payload = {
      "data": contacts,
      "source": "zoho_contacts",
      "timestamp": now
    };
    
    webhookResponse = invokeurl(
      [
        url: webhookUrl,
        type: "POST",
        headers: {
          "Content-Type": "application/json"
        },
        body: payload
      ]
    );
    
    info("Contacts sent: " + contacts.size());
    return webhookResponse;
  }
}
```

### 4️⃣ Zoho Function - Send Enrollments

```javascript
function sendEnrollmentsToWebhook() {
  // إذا كان في Custom Module للـ Enrollments
  url = "https://www.zohoapis.com/crm/v2/Enrollments";
  
  response = invokeurl(
    [
      url: url,
      type: "GET",
      headers: map(),
      connection: "zoho_crm"
    ]
  );
  
  if (response.get("code") == 200) {
    enrollments = response.get("data");
    
    webhookUrl = "http://YOUR_SERVER:8000/v1/debug/webhook/zoho";
    
    payload = {
      "data": enrollments,
      "source": "zoho_enrollments",
      "timestamp": now
    };
    
    webhookResponse = invokeurl(
      [
        url: webhookUrl,
        type: "POST",
        headers: {
          "Content-Type": "application/json"
        },
        body: payload
      ]
    );
    
    info("Enrollments sent: " + enrollments.size());
    return webhookResponse;
  }
}
```

---

## 📝 الخطوات:

1. **في Zoho Creator/CRM:**
   - افتح Functions
   - انسخ الـ functions أعلاه
   - غيّر `YOUR_SERVER` للـ server بتاعك

2. **شغّل الـ Functions:**
   ```
   sendProductsToWebhook();
   sendClassesToWebhook();
   sendContactsToWebhook();
   sendEnrollmentsToWebhook();
   ```

3. **تحقق من الـ Data:**
   ```
   GET http://YOUR_SERVER:8000/v1/debug/data
   ```

4. **حلل الـ Format:**
   ```
   POST http://YOUR_SERVER:8000/v1/debug/format-analysis
   ```

5. **انسخ الـ Format وابني الـ Parsers عليها**

---

## 🎯 الـ Expected Output

### Products Format (من Zoho):
```json
{
  "data": [
    {
      "id": "...",
      "Product_Name": "...",
      "Price": "...",
      "status": "...",
      "created_time": "...",
      ...
    }
  ]
}
```

### Classes Format:
```json
{
  "data": [
    {
      "id": "...",
      "BTEC_Class_Name": "...",
      "Short_Name": "...",
      "Start_Date": "...",
      ...
    }
  ]
}
```

### Enrollments Format:
```json
{
  "data": [
    {
      "id": "...",
      "Student": {
        "id": "..."
      },
      "BTEC_Class": {
        "id": "..."
      },
      "status": "...",
      ...
    }
  ]
}
```

---

## 🔍 مرة تستقبل الـ Data:

1. انظر إلى الـ fields اللي فيها
2. ركز على الـ required fields
3. لاحظ الـ data types
4. ابني الـ parsers على أساس الـ format الفعلي

**هذا أفضل من التخمين! 🎯**
