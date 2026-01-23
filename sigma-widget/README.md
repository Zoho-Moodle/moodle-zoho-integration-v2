# 🎓 Moodle Integration Sigma Widget - Installation Guide

## Overview

This Sigma Widget provides a UI for managing the Moodle-Zoho integration directly from Zoho CRM. It communicates with the FastAPI backend through a secure Deluge proxy function that handles HMAC authentication.

## Architecture

```
┌─────────────────┐
│  Sigma Widget   │  (HTML/CSS/JS in Zoho CRM)
│  (Browser)      │
└────────┬────────┘
         │ Calls Zoho Function
         ▼
┌─────────────────┐
│ Deluge Proxy    │  (Zoho CRM Function)
│ Function        │  - Generates HMAC signature
│                 │  - Keeps secrets safe
└────────┬────────┘
         │ HMAC-authenticated HTTP
         ▼
┌─────────────────┐
│  FastAPI        │  (Extension API Backend)
│  Backend        │  /v1/extension/*
└─────────────────┘
```

---

## 🚀 Installation Steps

### Step 1: Install Backend (if not done)

```bash
cd backend

# Create extension tables
python create_extension_tables.py

# Seed configuration
python seed_extension_config.py

# Start server
python start_server.py
# Server runs on http://0.0.0.0:8001
```

### Step 2: Expose Backend via ngrok (for testing)

```bash
ngrok http 8001
# Copy the HTTPS URL (e.g., https://abc123.ngrok.io)
```

> **For Production**: Deploy backend to a permanent server and use that URL

---

### Step 3: Create Zoho Deluge Function

1. **Go to Zoho CRM** → Setup → Developer Space → Functions
2. **Click "New Function"**:
   - **Function Name**: `moodle_integration_api_proxy`
   - **Display Name**: Moodle Integration API Proxy
   - **Category**: Standalone
   - **Description**: Secure proxy for calling Backend Extension API

3. **Copy code from**: `deluge_proxy_function.txt`

4. **Update Configuration** (in the function code):
   ```deluge
   BACKEND_URL = "https://your-ngrok-url.ngrok.io";  // Your backend URL
   API_KEY = "ext_key_default";
   API_SECRET = "ext_secret_change_me_in_production";  // From backend integration_settings
   ```

5. **Save and Test**:
   ```json
   {
     "method": "GET",
     "path": "/v1/extension/settings",
     "body": "",
     "tenant_id": "default"
   }
   ```
   Expected response: `{"code":"success","details":{...}}`

---

### Step 4: Install Sigma Widget

1. **Go to Zoho CRM** → Setup → Developer Space → Widgets

2. **Click "New Widget"**:
   - **Widget Name**: Moodle Integration Manager
   - **Type**: Sigma Widget
   - **Hosting**: Zoho

3. **Upload Files**:
   - Upload `widget.html`, `app.js`, `styles.css` to the widget folder

4. **Configure Widget**:
   - **Target Module**: Any (e.g., Dashboard, Contacts, or standalone)
   - **Size**: 
     - Width: 1200px (or 100%)
     - Height: 800px (or fit-to-content)

5. **Set Widget Scope**:
   - Add required permissions:
     - `ZohoCRM.functions.execute` (to call Deluge function)
     - `ZohoCRM.users.READ` (to get current user)

---

### Step 5: Update Widget Configuration

In `app.js`, verify configuration:

```javascript
const CONFIG = {
    tenantId: 'default', // Change if using different tenant
    delugeFunction: 'moodle_integration_api_proxy', // Must match function name
    apiVersion: 'v1'
};
```

---

### Step 6: Add Widget to CRM

1. **Go to desired module** (e.g., Home Dashboard)
2. **Add Widget**:
   - Click "+" or "Add Component"
   - Select "Moodle Integration Manager"
3. **Position and save**

---

## 🔐 Security Configuration

### Backend API Credentials

The API key and secret are stored in the `integration_settings` table:

```sql
SELECT extension_api_key, extension_api_secret 
FROM integration_settings 
WHERE tenant_id = 'default';
```

**Default Values** (CHANGE IN PRODUCTION):
- API Key: `ext_key_default`
- Secret: `ext_secret_change_me_in_production`

### Updating Credentials

**Option 1: Via SQL**
```sql
UPDATE integration_settings 
SET extension_api_key = 'new_key',
    extension_api_secret = 'new_very_long_secure_secret'
WHERE tenant_id = 'default';
```

**Option 2: Via Backend Script**
```python
from app.services.extension_service import ExtensionService
from app.infra.db.session import SessionLocal

db = SessionLocal()
service = ExtensionService(db)

service.update_integration_settings('default', {
    'extension_api_key': 'new_key',
    'extension_api_secret': 'new_very_long_secure_secret'
})
db.close()
```

**Don't forget to update the Deluge function with new credentials!**

---

## 📋 Usage Guide

### Settings Tab
- **Enable/Disable Integrations**: Toggle Moodle and Zoho
- **Moodle Configuration**: Set Moodle URL and API token
- **Save**: Persist changes to backend

### Modules Tab
- **View All Modules**: See status of all 8 modules
- **Enable/Disable**: Toggle module sync
- **Sync Now**: Trigger manual sync for enabled modules
- **Last Run Info**: See when module last synced

### Mappings Tab
1. **Select Module** from dropdown
2. **View Canonical Fields**: See all fields with types/descriptions
3. **Map Zoho Fields**: Enter Zoho API field names (e.g., `Academic_Email`)
4. **Set Required**: Mark required fields
5. **Add Transforms**: Specify transform rules (e.g., `before_at` for username)
6. **Save Mappings**: Persist to backend

### Runs Tab
- **View History**: See all sync runs
- **Filter by Module**: Focus on specific module
- **View Details**: Click run to see full details
- **Retry Failed**: Retry failed items from a run

---

## 🔧 Configuration Guide

### Module Configuration

**Enable a Module**:
1. Go to **Modules** tab
2. Find module (e.g., "students")
3. Click **Enable**
4. Module is now ready for sync

**Disable a Module**:
1. Click **Disable** on any enabled module
2. Sync will no longer run for this module

**Schedule Mode** (backend only for now):
- `manual`: Manual trigger only (default)
- `cron`: Scheduled via backend (requires APScheduler)
- `webhook`: Triggered by Zoho webhooks

### Field Mapping Configuration

**Example: Students Module**

| Canonical Field | Zoho Field | Required | Transform |
|----------------|------------|----------|-----------|
| academic_email | Academic_Email | ✓ | |
| username | Academic_Email | ✓ | before_at |
| display_name | Display_Name | | |
| phone | Phone_Number | | |
| status | Status | ✓ | |
| profile_image_url | Profile_Image | | image_url_resolver |

**Transform Types**:
- `before_at`: Extract text before @ (e.g., email → username)
- `image_url_resolver`: Resolve image field to URL
- Custom transforms can be added in backend

---

## 🐛 Troubleshooting

### Widget Not Loading
1. Check browser console for errors
2. Verify Zoho SDK initialized: `ZOHO.embeddedApp.init()`
3. Check widget has required permissions

### API Calls Failing
1. **Check Deluge Function**:
   - Verify function name matches `CONFIG.delugeFunction`
   - Test function with sample input
   - Check BACKEND_URL is correct

2. **Check Backend**:
   - Is server running? `curl http://localhost:8001/v1/health`
   - Is ngrok tunnel active?
   - Check backend logs for errors

3. **Check Authentication**:
   - Verify API_KEY and API_SECRET in Deluge function
   - Check they match `integration_settings` table
   - Verify HMAC signature generation

### HMAC Signature Errors (401 Unauthorized)
- **Timestamp issue**: Check server time is synchronized
- **Nonce issue**: Ensure nonce is unique per request
- **Secret mismatch**: Verify API_SECRET in function matches backend
- **Body hash**: Ensure body is JSON-stringified correctly

### Module Sync Not Working
1. **Check Module is Enabled**: Modules tab should show "Enabled"
2. **Check Field Mappings**: Ensure required fields are mapped
3. **Check Backend Logs**: Look for sync errors
4. **Check Zoho Data**: Verify Zoho records have required fields

### Grades Module Error
- Grades module is **intentionally disabled**
- Error message: "Grades sync direction is Moodle → Zoho (not implemented)"
- This is expected behavior (future feature)

---

## 🔒 Security Best Practices

### Production Deployment

1. **Change Default Credentials**:
   ```sql
   UPDATE integration_settings 
   SET extension_api_key = 'prod_key_xyz',
       extension_api_secret = 'very_long_random_secure_secret_min_32_chars'
   WHERE tenant_id = 'default';
   ```

2. **Use HTTPS Backend**: Never use HTTP in production

3. **Restrict Deluge Function Access**:
   - Set function to "Private"
   - Only allow calls from widget

4. **Enable IP Whitelisting**: In backend firewall, allow only Zoho IPs

5. **Rotate Secrets Regularly**: Change API secrets every 90 days

6. **Monitor Access Logs**: Check backend logs for suspicious activity

---

## 📊 Monitoring & Maintenance

### Health Checks

**Backend Health**:
```bash
curl https://your-backend.com/v1/health
```

**Widget Status**:
- Check browser console: `ZOHO.embeddedApp.SDK.getConnectionName()`
- Verify API calls: Network tab in DevTools

### Logs

**Backend Logs** (FastAPI):
- Check server console output
- Set up logging to file: `uvicorn app.main:app --log-config=logging.yaml`

**Zoho Deluge Logs**:
- Go to Function → Execution Logs
- Check for errors in API calls

### Database Maintenance

**Check Sync Run History**:
```sql
SELECT module_name, status, COUNT(*) 
FROM sync_runs 
WHERE tenant_id = 'default' 
GROUP BY module_name, status;
```

**Clean Old Runs** (optional):
```sql
DELETE FROM sync_runs 
WHERE created_at < NOW() - INTERVAL '30 days';
```

---

## 🆘 Support

### Documentation
- **Extension API**: `backend/EXTENSION_API_CHANGELOG.md`
- **Quick Reference**: `backend/EXTENSION_API_QUICK_REF.md`
- **Implementation Summary**: `backend/EXTENSION_IMPLEMENTATION_SUMMARY.md`

### Testing
```bash
# Test backend
python -m pytest tests/test_extension_api.py -v

# Test Deluge function
# Use Zoho CRM → Functions → Test with sample input
```

### Contact
For issues or questions, refer to project documentation or contact the development team.

---

## 📝 Changelog

### Version 1.0.0 (2026-01-22)
- ✅ Initial release
- ✅ Settings management
- ✅ Module configuration
- ✅ Field mappings editor
- ✅ Sync run monitoring
- ✅ HMAC authentication via Deluge proxy
- ✅ Zoho native UI design

---

## 🎯 Future Enhancements

- [ ] Grades module (Moodle → Zoho direction)
- [ ] Bulk operations
- [ ] Advanced analytics dashboard
- [ ] Custom workflow automation
- [ ] Multi-language support (Arabic + English)
- [ ] Mobile responsive design
- [ ] Real-time sync status updates
- [ ] Export reports (CSV/Excel)

---

**🎉 Installation Complete!**

Your Moodle Integration Manager widget is now ready to use. Go to Zoho CRM and start managing your integration!
