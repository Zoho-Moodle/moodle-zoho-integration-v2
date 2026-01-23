# 🚀 Moodle Integration Widget - Quick Start (5 Minutes)

## Prerequisites
- ✅ Backend running (http://localhost:8001 or ngrok)
- ✅ Extension tables created (`create_extension_tables.py`)
- ✅ Configuration seeded (`seed_extension_config.py`)

---

## Step 1: Create Deluge Function (2 min)

1. **Zoho CRM** → Setup → Developer Space → **Functions**
2. **New Function**:
   - Name: `moodle_integration_api_proxy`
   - Type: Standalone
3. **Paste code** from `deluge_proxy_function.txt`
4. **Update these 3 lines**:
   ```deluge
   BACKEND_URL = "https://YOUR-NGROK-URL.ngrok.io";
   API_KEY = "ext_key_default";
   API_SECRET = "ext_secret_change_me_in_production";
   ```
5. **Save** → **Test**:
   ```json
   {
     "method": "GET",
     "path": "/v1/extension/settings",
     "body": "",
     "tenant_id": "default"
   }
   ```
   ✅ Should return: `{"code":"success",...}`

---

## Step 2: Install Widget (2 min)

1. **Zoho CRM** → Setup → Developer Space → **Widgets**
2. **New Widget**:
   - Name: `Moodle Integration Manager`
   - Type: Sigma
   - Hosting: Zoho
3. **Upload files**:
   - `widget.html`
   - `app.js`
   - `styles.css`
4. **Set permissions**:
   - ✅ `ZohoCRM.functions.execute`
   - ✅ `ZohoCRM.users.READ`
5. **Save & Publish**

---

## Step 3: Add to Dashboard (1 min)

1. Go to **Home** or any module
2. Click **+** → **Add Component** → **Widgets**
3. Select **Moodle Integration Manager**
4. Drag to position → **Save**

---

## Step 4: Test Widget

### Test Settings Tab
1. Click **Settings** tab
2. Should load integration settings
3. Toggle "Enable Moodle Integration"
4. Click **💾 Save Settings**
5. ✅ Should see "Settings saved successfully!"

### Test Modules Tab
1. Click **Modules** tab
2. Should see 8 modules (students, programs, etc.)
3. Click **Enable** on "students" module
4. ✅ Status should change to "Enabled"
5. Click **▶️ Sync Now**
6. ✅ Should see "Sync started! Run ID: ..."

### Test Mappings Tab
1. Click **Mappings** tab
2. Select **students** from dropdown
3. Should see canonical fields table
4. Enter Zoho fields:
   - `academic_email` → `Academic_Email`
   - `username` → `Academic_Email` (transform: `before_at`)
   - `display_name` → `Display_Name`
5. Click **💾 Save Mappings**
6. ✅ Should see "Mappings saved successfully!"

### Test Runs Tab
1. Click **Runs** tab
2. Should see sync run history
3. Click on a run to view details
4. ✅ Should see run details popup

---

## 🎉 Done!

Your widget is now fully functional. You can now:
- ✅ Configure integration settings
- ✅ Enable/disable modules
- ✅ Map Zoho fields to Moodle
- ✅ Trigger manual syncs
- ✅ Monitor sync runs

---

## 🐛 Quick Troubleshooting

### Widget shows error
**Check**: Browser console (F12) for errors

### API calls fail
**Check**: 
1. Deluge function test passes
2. Backend is running
3. ngrok tunnel is active

### 401 Unauthorized
**Check**: 
1. API_KEY matches database
2. API_SECRET matches database
3. Backend logs: `tail -f server.log`

### Module won't enable
**Check**: 
1. Module exists in database
2. Not the "grades" module (disabled by design)

---

## 📖 Full Documentation

See `README.md` for:
- Complete installation guide
- Configuration details
- Security best practices
- Troubleshooting guide
- API reference

---

## 🔗 Quick Links

- **Backend API**: http://localhost:8001/docs
- **Extension Endpoints**: http://localhost:8001/v1/extension/*
- **Deluge Functions**: Zoho CRM → Setup → Functions
- **Widgets**: Zoho CRM → Setup → Widgets

---

**Need Help?** Check `README.md` or backend documentation.
