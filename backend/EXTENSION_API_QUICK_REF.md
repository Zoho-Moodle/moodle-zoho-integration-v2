# Extension API - Quick Reference

## 🔐 Authentication

All `/v1/extension/*` endpoints require HMAC-SHA256 signature authentication.

### Required Headers
```
X-Ext-Key: ext_key_default
X-Ext-Timestamp: 1737566400.123
X-Ext-Nonce: unique_nonce_123
X-Ext-Signature: <hmac_sha256_signature>
X-Tenant-ID: default
```

### Signature Generation (Python)
```python
import hmac, hashlib, time

def generate_signature(method, path, body, timestamp, nonce, secret):
    body_hash = hashlib.sha256(body.encode()).hexdigest()
    message = f"{timestamp}.{nonce}.{method}.{path}.{body_hash}"
    return hmac.new(secret.encode(), message.encode(), hashlib.sha256).hexdigest()

# Example
timestamp = str(time.time())
nonce = f"nonce_{int(time.time() * 1000)}"
signature = generate_signature("GET", "/v1/extension/settings", "", timestamp, nonce, "ext_secret_change_me_in_production")
```

---

## 📡 Endpoints

### Tenants
```bash
# List tenants
GET /v1/extension/tenants

# Create tenant
POST /v1/extension/tenants
Body: {"tenant_id": "tenant1", "name": "Tenant 1", "status": "active"}
```

### Settings
```bash
# Get integration settings
GET /v1/extension/settings

# Update settings
PUT /v1/extension/settings
Body: {
  "moodle_enabled": true,
  "moodle_base_url": "https://moodle.example.com",
  "zoho_enabled": true
}
```

### Modules
```bash
# List all modules
GET /v1/extension/modules

# Update module
PUT /v1/extension/modules/students
Body: {
  "enabled": true,
  "schedule_mode": "cron",
  "schedule_cron": "0 */6 * * *"
}
```

### Field Mappings
```bash
# Get mappings
GET /v1/extension/mappings/students

# Update mappings
PUT /v1/extension/mappings/students
Body: {
  "mappings": [
    {
      "canonical_field": "academic_email",
      "zoho_field_api_name": "Academic_Email",
      "required": true
    },
    {
      "canonical_field": "username",
      "zoho_field_api_name": "Academic_Email",
      "required": true,
      "transform_rules": {"type": "before_at"}
    }
  ]
}
```

### Sync Execution
```bash
# Trigger manual sync
POST /v1/extension/sync/students/run
Body: {"triggered_by": "admin@example.com"}

# Get run history
GET /v1/extension/runs?module=students&limit=50

# Get run details
GET /v1/extension/runs/{run_id}

# Retry failed items
POST /v1/extension/runs/{run_id}/retry-failed
```

### Metadata
```bash
# Get canonical schema
GET /v1/extension/metadata/canonical-schema

# Get Moodle adapter info
GET /v1/extension/metadata/moodle-adapter
```

---

## 🗄️ Database Tables

### tenant_profiles
```sql
tenant_id (PK), name, status, created_at, updated_at
```

### integration_settings
```sql
id (PK), tenant_id (FK), moodle_enabled, moodle_base_url, 
zoho_enabled, extension_api_key, extension_api_secret
```

### module_settings
```sql
id (PK), tenant_id (FK), module_name, enabled, 
schedule_mode, schedule_cron, last_run_at, last_run_status
```

### field_mappings
```sql
id (PK), tenant_id (FK), module_name, canonical_field, 
zoho_field_api_name, required, default_value, transform_rules_json
```

### sync_runs
```sql
run_id (PK), tenant_id (FK), module_name, trigger_source, 
started_at, finished_at, status, counts_json, error_summary
```

### sync_run_items
```sql
id (PK), run_id (FK), zoho_id, status, message, diff_json
```

---

## 🎯 Module Names

Available modules:
- `students` - Student records
- `programs` - Academic programs
- `classes` - Course classes
- `enrollments` - Class enrollments
- `units` - Course units
- `registrations` - Program registrations
- `payments` - Payment records
- `grades` - Student grades (⚠️ **Disabled**: Moodle → Zoho direction not implemented)

---

## ⚙️ Schedule Modes

- `manual` - Manual trigger only (default)
- `cron` - Cron-based scheduling (requires `schedule_cron`)
- `webhook` - Triggered by Zoho webhooks

---

## 🔄 Sync Run Status

- `running` - Sync in progress
- `completed` - Successfully completed
- `failed` - Failed with errors
- `partial` - Completed with some failures

---

## 📊 Sync Item Status

- `NEW` - New record created
- `UNCHANGED` - No changes detected
- `UPDATED` - Record updated
- `FAILED` - Sync failed for this item
- `SKIPPED` - Skipped due to dependencies

---

## 🛠️ Setup Commands

```bash
# Create tables
python create_extension_tables.py

# Seed configuration
python seed_extension_config.py

# Start server
python start_server.py

# Run tests
python -m pytest tests/test_extension_api.py -v
```

---

## 🔒 Default Credentials

**⚠️ CHANGE IN PRODUCTION!**

- **Tenant**: `default`
- **API Key**: `ext_key_default`
- **Secret**: `ext_secret_change_me_in_production`

---

## 📖 Documentation

- **Full Changelog**: [EXTENSION_API_CHANGELOG.md](EXTENSION_API_CHANGELOG.md)
- **Implementation Summary**: [EXTENSION_IMPLEMENTATION_SUMMARY.md](EXTENSION_IMPLEMENTATION_SUMMARY.md)
- **Main README**: [README.md](README.md)
- **Swagger UI**: http://localhost:8001/docs

---

## 🐛 Common Issues

### 401 Unauthorized
- Check signature generation
- Verify timestamp is within 5 minutes
- Ensure nonce is unique
- Confirm API key/secret are correct

### 404 Not Found
- Verify endpoint path
- Check if routes are loaded (restart server)
- Confirm tenant_id exists

### 400 Bad Request (Grades)
- Grades module cannot be enabled
- Manual sync blocked for grades
- Use Moodle → Zoho direction (not implemented yet)

---

## 💡 Tips

1. **Generate fresh nonce** for each request
2. **Use current timestamp** (within 5 min window)
3. **URL-encode paths** if they contain special chars
4. **Include body hash** even for GET requests (empty body = empty hash)
5. **Store secrets securely** (never in git, use environment variables)

---

**Quick Test:**
```bash
# Verify extension tables exist
python -c "from app.infra.db.models.extension import *; print('✅ Models loaded')"

# Check default tenant
psql -d moodle_zoho_v2 -c "SELECT * FROM tenant_profiles WHERE tenant_id='default';"
```
