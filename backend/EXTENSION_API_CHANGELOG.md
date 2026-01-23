# Extension API Changelog

## [Extension Backend Support] - 2026-01-22

### Added - Database Tables
- **tenant_profiles**: Tenant metadata and status management
- **integration_settings**: Moodle/Zoho connection configuration per tenant
- **module_settings**: Per-module sync configuration (enabled, schedule, last run)
- **field_mappings**: Zoho -> Canonical field mappings with transform rules
- **sync_runs**: Manual/scheduled sync execution history
- **sync_run_items**: Individual record sync results with diffs

### Added - API Endpoints

#### Tenant Management (`/v1/extension/tenants`)
- `GET /v1/extension/tenants` - List all tenants
- `POST /v1/extension/tenants` - Create new tenant

#### Settings Management (`/v1/extension/settings`)
- `GET /v1/extension/settings` - Get integration settings
- `PUT /v1/extension/settings` - Update Moodle/Zoho connection settings

#### Module Configuration (`/v1/extension/modules`)
- `GET /v1/extension/modules` - List all module settings
- `PUT /v1/extension/modules/{module_name}` - Update module (enable/disable, schedule)

#### Field Mappings (`/v1/extension/mappings`)
- `GET /v1/extension/mappings/{module_name}` - Get field mappings
- `PUT /v1/extension/mappings/{module_name}` - Replace field mappings

#### Sync Execution (`/v1/extension/sync`)
- `POST /v1/extension/sync/{module_name}/run` - Trigger manual sync
- `GET /v1/extension/runs` - Get sync run history (with filters)
- `GET /v1/extension/runs/{run_id}` - Get detailed run with items
- `POST /v1/extension/runs/{run_id}/retry-failed` - Retry failed items

#### Metadata (`/v1/extension/metadata`)
- `GET /v1/extension/metadata/canonical-schema` - Get canonical field definitions
- `GET /v1/extension/metadata/moodle-adapter` - Get Moodle adapter constraints

### Added - Authentication
- **HMAC-SHA256 signature authentication** for all extension endpoints
- Required headers: `X-Ext-Key`, `X-Ext-Timestamp`, `X-Ext-Nonce`, `X-Ext-Signature`, `X-Tenant-ID`
- Signature format: `HMAC_SHA256(secret, "{timestamp}.{nonce}.{method}.{path}.{body_hash}")`
- Protection against replay attacks (nonce validation)
- 5-minute timestamp window
- Per-tenant API keys and secrets

### Added - Services
- `ExtensionService`: Business logic layer for config management
- CRUD operations for all extension entities
- Sync run orchestration and tracking
- Integration with existing sync pipelines (stub for MVP)

### Added - Scripts
- `create_extension_tables.py`: Database schema creation
- `seed_extension_config.py`: Seed default tenant with module settings and sample mappings

### Added - Tests
- `test_extension_api.py`: Comprehensive test suite
  - HMAC signature validation tests
  - Settings CRUD tests
  - Module configuration tests
  - Field mapping tests
  - Sync run tests
  - Grades module blocking tests

### Security Features
- HMAC signature verification prevents unauthorized access
- Nonce storage prevents replay attacks
- Timestamp validation prevents stale requests
- API secrets stored per tenant (encrypted in production)
- No secrets stored in Zoho CRM

### Business Rules Implemented
- **Grades module blocked**: Cannot enable or trigger sync (Moodle -> Zoho direction not implemented)
- **Default tenant**: Pre-configured with all 8 modules
- **Sample mappings**: Students module has 6 field mappings configured
- **Scheduling stub**: Module settings include schedule_mode/schedule_cron (APScheduler integration ready)

### Configuration Defaults
- **Default Tenant ID**: `default`
- **Default API Key**: `ext_key_default`
- **Default Secret**: `ext_secret_change_me_in_production`
- **Grades Module**: Enabled=false, cannot be changed via API

### Next Steps (Not in MVP)
- Integrate manual sync trigger with actual sync services
- Implement APScheduler for cron-based sync
- Add Redis for nonce storage (replace in-memory dict)
- Add secrets encryption at rest
- Build Zoho Sigma widget frontend
- Add bulk operation support
- Add webhook trigger support
- Add Moodle -> Zoho sync for Grades

---

## Database Migration
```bash
python create_extension_tables.py
python seed_extension_config.py
```

## Testing
```bash
pytest tests/test_extension_api.py -v
```

## Sample Request
```bash
# Generate signature (Python example)
import hmac, hashlib, time, json

timestamp = str(time.time())
nonce = "unique_nonce_12345"
method = "GET"
path = "/v1/extension/settings"
body = ""
secret = "ext_secret_change_me_in_production"

body_hash = hashlib.sha256(body.encode()).hexdigest()
message = f"{timestamp}.{nonce}.{method}.{path}.{body_hash}"
signature = hmac.new(secret.encode(), message.encode(), hashlib.sha256).hexdigest()

# Make request
curl -X GET "http://localhost:8001/v1/extension/settings" \
  -H "X-Ext-Key: ext_key_default" \
  -H "X-Ext-Timestamp: $timestamp" \
  -H "X-Ext-Nonce: $nonce" \
  -H "X-Ext-Signature: $signature" \
  -H "X-Tenant-ID: default"
```
