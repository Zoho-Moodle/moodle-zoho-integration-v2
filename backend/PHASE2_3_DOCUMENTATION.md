# Phase 2 & 3: Programs, Classes, and Enrollments Integration

## Overview

Phase 2 & 3 extends the Moodle-Zoho integration to support three additional entities:

- **Programs**: Zoho Products (courses offered by the institution)
- **Classes**: Zoho BTEC_Classes (specific class instances)
- **Enrollments**: Zoho BTEC_Enrollments (student class participation)

This documentation covers architecture, API endpoints, examples, and testing.

---

## Architecture

### Clean Architecture Pattern (5 Layers)

```
┌─────────────────────────────────────────────────────────────┐
│                    API Layer (FastAPI)                       │
│          POST /v1/sync/{programs|classes|enrollments}        │
└────────────────┬────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────┐
│              Ingress Layer (Orchestration)                   │
│  ingest_programs()  ingest_classes()  ingest_enrollments()   │
│  - Parse Zoho payload                                        │
│  - Map to canonical models                                   │
│  - Call service layer                                        │
│  - Return per-record results                                 │
└────────────────┬────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────┐
│           Domain Layer (Business Logic)                      │
│  ProgramService  ClassService  EnrollmentService             │
│  - Fingerprinting for change detection                       │
│  - State machine: NEW/UNCHANGED/UPDATED/INVALID/SKIPPED      │
│  - Dependency checks (for enrollments)                       │
│  - Moodle integration                                        │
└────────────────┬────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────┐
│              Infrastructure Layer (DB)                       │
│  Program  Class  Enrollment  Student  (SQLAlchemy ORM)       │
│  - Multi-tenant (tenant_id)                                 │
│  - Unique indexes (tenant_id, zoho_id)                      │
│  - Fingerprinting columns                                   │
│  - Audit fields (created_at, updated_at)                    │
└─────────────────────────────────────────────────────────────┘
```

### Request Flow

1. **Webhook arrives** → POST /v1/sync/programs
2. **Idempotency check** → Cache hit? Return cached result
3. **Parse payload** → Extract Zoho data, handle format variants
4. **Map to canonical** → Validate with Pydantic models
5. **Service logic** → Compute fingerprint, detect changes
6. **Database** → Insert/update, return results
7. **Response** → Per-record status (NEW/UNCHANGED/UPDATED/INVALID/SKIPPED)

---

## API Endpoints

### 1. Sync Programs

**Endpoint**: `POST /v1/sync/programs`

**Request**:
```json
{
  "data": [
    {
      "id": "prod_123",
      "Product_Name": "Python Certification",
      "Price": "299.99",
      "status": "Active"
    }
  ]
}
```

**Response**:
```json
{
  "status": "success",
  "idempotency_key": "a1b2c3d4e5f6...",
  "results": [
    {
      "zoho_program_id": "prod_123",
      "status": "NEW",
      "message": "Program created"
    }
  ]
}
```

**Status Values**:
- `NEW`: First time syncing this program
- `UNCHANGED`: Program exists, no changes detected
- `UPDATED`: Fields changed (returns `changes` dict)
- `INVALID`: Missing required fields
- `ERROR`: Exception during processing

---

### 2. Sync Classes

**Endpoint**: `POST /v1/sync/classes`

**Request**:
```json
{
  "data": [
    {
      "id": "class_456",
      "BTEC_Class_Name": "Advanced Python",
      "Short_Name": "PY301",
      "status": "Active",
      "Start_Date": "2024-02-01",
      "End_Date": "2024-06-30",
      "Teacher": { "id": "teacher_001" },
      "Unit": { "id": "unit_001" },
      "BTEC_Program": { "id": "prog_123" }
    }
  ]
}
```

**Response**:
```json
{
  "status": "success",
  "idempotency_key": "b2c3d4e5f6g7...",
  "results": [
    {
      "zoho_class_id": "class_456",
      "status": "NEW",
      "message": "Class created"
    }
  ]
}
```

**Field Mapping**:
- `id` → `zoho_id` (required)
- `BTEC_Class_Name` → `name` (required)
- `Short_Name` → `short_name`
- `Start_Date` / `End_Date` → ISO format dates
- `Teacher`, `Unit`, `BTEC_Program` → extract `.id` field

---

### 3. Sync Enrollments

**Endpoint**: `POST /v1/sync/enrollments`

**Request**:
```json
{
  "data": [
    {
      "id": "enr_789",
      "Student": { "id": "student_001" },
      "BTEC_Class": { "id": "class_456" },
      "BTEC_Program": { "id": "prog_123" },
      "status": "Active",
      "Start_Date": "2024-02-01"
    }
  ]
}
```

**Response**:
```json
{
  "status": "success",
  "idempotency_key": "c3d4e5f6g7h8...",
  "results": [
    {
      "zoho_enrollment_id": "enr_789",
      "status": "NEW",
      "message": "Enrollment created"
    }
  ]
}
```

**Special Status Values**:
- `SKIPPED` (with `reason`):
  - `"student_not_synced_yet"` - Student hasn't been synced yet
  - `"class_not_synced_yet"` - Class hasn't been synced yet

**Why?** Enrollments depend on Students and Classes existing first.

---

## Idempotency

All endpoints support **request-level idempotency**:

```
Request 1: POST /v1/sync/programs {"data": [...]}
Response: {"results": [...], "idempotency_key": "hash123"}

Request 2 (identical): POST /v1/sync/programs {"data": [...]}
Response: {"results": [...], "idempotency_key": "hash123"}  ← CACHED
```

**Cache TTL**: 1 hour (configurable)

**Key Header**: `X-Idempotency-Key` (optional - auto-generated from request body)

---

## Multi-Tenancy

All endpoints support multi-tenancy via the `X-Tenant-ID` header:

```bash
curl -X POST http://localhost:8000/v1/sync/programs \
  -H "X-Tenant-ID: customer_123" \
  -H "Content-Type: application/json" \
  -d '{"data": [...]}'
```

If not provided, defaults to `DEFAULT_TENANT_ID` from config (usually `"default"`).

**Database Isolation**: All queries filter by `(tenant_id, zoho_id)` tuple.

---

## Field Validation & Fingerprinting

### Program Fingerprinting

Fields included in fingerprint (for change detection):
```
SHA256(name + price + moodle_id + status)
```

If any of these change, the program is marked as `UPDATED`.

### Class Fingerprinting

```
SHA256(name + short_name + start_date + end_date + status + program_zoho_id)
```

### Enrollment Fingerprinting

```
SHA256(student_zoho_id + class_zoho_id + program_zoho_id + status + start_date)
```

---

## Moodle Integration (Optional)

When `MOODLE_ENABLED=true`, the system:

1. **Creates users** in Moodle (if not exist)
2. **Gets courses** by idnumber
3. **Enrols students** in courses
4. **Tracks moodle_user_id** and **moodle_enrollment_id** in DB

**Configuration** (.env):
```
MOODLE_ENABLED=true
MOODLE_BASE_URL=https://moodle.example.com
MOODLE_TOKEN=abcd1234efgh5678ijkl9012mnop3456
```

**When disabled**: System persists data to DB only, returns `"moodle_status": "PENDING_MOODLE"`.

---

## Examples

### Curl: Create Programs

```bash
curl -X POST http://localhost:8000/v1/sync/programs \
  -H "Content-Type: application/json" \
  -H "X-Tenant-ID: school_001" \
  -d '{
    "data": [
      {
        "id": "PROD_001",
        "Product_Name": "Python Essentials",
        "Price": "199.99",
        "status": "Active"
      },
      {
        "id": "PROD_002",
        "Product_Name": "Web Development",
        "Price": "299.99",
        "status": "Active"
      }
    ]
  }'
```

**Response**:
```json
{
  "status": "success",
  "idempotency_key": "d4e5f6g7h8i9...",
  "results": [
    {
      "zoho_program_id": "PROD_001",
      "status": "NEW",
      "message": "Program created"
    },
    {
      "zoho_program_id": "PROD_002",
      "status": "NEW",
      "message": "Program created"
    }
  ]
}
```

### Curl: Create Classes (with Dependencies)

```bash
curl -X POST http://localhost:8000/v1/sync/classes \
  -H "Content-Type: application/json" \
  -H "X-Tenant-ID: school_001" \
  -d '{
    "data": [
      {
        "id": "CLASS_001",
        "BTEC_Class_Name": "Python Fundamentals - Cohort A",
        "Short_Name": "PY101-A",
        "status": "Active",
        "Start_Date": "2024-02-01",
        "End_Date": "2024-06-30",
        "Teacher": { "id": "TEACHER_001" },
        "Unit": { "id": "UNIT_001" },
        "BTEC_Program": { "id": "PROD_001" }
      }
    ]
  }'
```

### Curl: Create Enrollments (Dependency Check)

```bash
curl -X POST http://localhost:8000/v1/sync/enrollments \
  -H "Content-Type: application/json" \
  -H "X-Tenant-ID: school_001" \
  -d '{
    "data": [
      {
        "id": "ENR_001",
        "Student": { "id": "STUDENT_001" },
        "BTEC_Class": { "id": "CLASS_001" },
        "BTEC_Program": { "id": "PROD_001" },
        "status": "Active",
        "Start_Date": "2024-02-01"
      }
    ]
  }'
```

**Response (if student not synced)**:
```json
{
  "status": "success",
  "idempotency_key": "e5f6g7h8i9j0...",
  "results": [
    {
      "zoho_enrollment_id": "ENR_001",
      "status": "SKIPPED",
      "reason": "student_not_synced_yet",
      "message": "Student STUDENT_001 not synced yet"
    }
  ]
}
```

### Postman Collection (JSON)

Save as `Moodle-Zoho-Phase2-3.postman_collection.json`:

```json
{
  "info": {
    "name": "Moodle-Zoho Phase 2-3",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "item": [
    {
      "name": "Sync Programs",
      "event": [
        {
          "listen": "test",
          "script": {
            "exec": [
              "pm.test('Status is 200', () => pm.response.code === 200);",
              "pm.test('Response has results', () => pm.response.json().results.length > 0);"
            ]
          }
        }
      ],
      "request": {
        "method": "POST",
        "header": [
          {
            "key": "Content-Type",
            "value": "application/json"
          },
          {
            "key": "X-Tenant-ID",
            "value": "school_001"
          }
        ],
        "body": {
          "mode": "raw",
          "raw": "{\n  \"data\": [\n    {\n      \"id\": \"PROD_{{$randomInt(1000, 9999)}}\",\n      \"Product_Name\": \"Python Course\",\n      \"Price\": \"199.99\",\n      \"status\": \"Active\"\n    }\n  ]\n}"
        },
        "url": {
          "raw": "{{base_url}}/v1/sync/programs",
          "host": ["{{base_url}}"],
          "path": ["v1", "sync", "programs"]
        }
      }
    },
    {
      "name": "Sync Classes",
      "request": {
        "method": "POST",
        "header": [
          {
            "key": "Content-Type",
            "value": "application/json"
          },
          {
            "key": "X-Tenant-ID",
            "value": "school_001"
          }
        ],
        "body": {
          "mode": "raw",
          "raw": "{\n  \"data\": [\n    {\n      \"id\": \"CLASS_{{$randomInt(1000, 9999)}}\",\n      \"BTEC_Class_Name\": \"Advanced Python\",\n      \"Short_Name\": \"PY301\",\n      \"status\": \"Active\",\n      \"Start_Date\": \"2024-02-01\",\n      \"End_Date\": \"2024-06-30\",\n      \"BTEC_Program\": { \"id\": \"PROD_1234\" }\n    }\n  ]\n}"
        },
        "url": {
          "raw": "{{base_url}}/v1/sync/classes",
          "host": ["{{base_url}}"],
          "path": ["v1", "sync", "classes"]
        }
      }
    },
    {
      "name": "Sync Enrollments",
      "request": {
        "method": "POST",
        "header": [
          {
            "key": "Content-Type",
            "value": "application/json"
          },
          {
            "key": "X-Tenant-ID",
            "value": "school_001"
          }
        ],
        "body": {
          "mode": "raw",
          "raw": "{\n  \"data\": [\n    {\n      \"id\": \"ENR_{{$randomInt(1000, 9999)}}\",\n      \"Student\": { \"id\": \"STU_001\" },\n      \"BTEC_Class\": { \"id\": \"CLASS_001\" },\n      \"BTEC_Program\": { \"id\": \"PROD_001\" },\n      \"status\": \"Active\",\n      \"Start_Date\": \"2024-02-01\"\n    }\n  ]\n}"
        },
        "url": {
          "raw": "{{base_url}}/v1/sync/enrollments",
          "host": ["{{base_url}}"],
          "path": ["v1", "sync", "enrollments"]
        }
      }
    }
  ],
  "variable": [
    {
      "key": "base_url",
      "value": "http://localhost:8000"
    }
  ]
}
```

---

## Environment Setup

### .env Configuration

```bash
# Database
DATABASE_URL=postgresql://user:password@localhost:5432/moodle_zoho_db

# Application
APP_NAME=Moodle Zoho Integration
ENV=development
LOG_LEVEL=INFO

# Moodle Integration
MOODLE_ENABLED=false
MOODLE_BASE_URL=https://moodle.example.com
MOODLE_TOKEN=your_moodle_api_token_here

# Multi-tenancy
DEFAULT_TENANT_ID=default

# Zoho (optional)
ZOHO_API_KEY=your_zoho_key_here
```

### Database Setup

```bash
# Create database
createdb moodle_zoho_db

# Run migrations (automatic on startup via setup_db.py)
cd backend
python setup_db.py
```

---

## Running the Application

### 1. Start the Server

```bash
cd backend
python -m uvicorn app.main:app --reload --host 0.0.0.0 --port 8000
```

**Output**:
```
INFO:     Uvicorn running on http://0.0.0.0:8000
INFO:     Application startup complete
```

### 2. Check Health

```bash
curl http://localhost:8000/v1/health
# Response: {"status": "healthy"}
```

### 3. Check API Docs

Open browser: `http://localhost:8000/docs` (Swagger UI)

---

## Running Tests

### Setup

```bash
cd backend
pip install pytest pytest-asyncio

# Or from requirements.txt
pip install -r requirements.txt
```

### Run All Tests

```bash
pytest tests/ -v
```

### Run Specific Test File

```bash
pytest tests/test_sync_endpoints.py -v
```

### Run Single Test

```bash
pytest tests/test_sync_endpoints.py::TestProgramsSync::test_new_program -v
```

### Test Output Example

```
tests/test_sync_endpoints.py::TestProgramsSync::test_new_program PASSED
tests/test_sync_endpoints.py::TestProgramsSync::test_duplicate_request PASSED
tests/test_sync_endpoints.py::TestClassesSync::test_new_class PASSED
tests/test_sync_enrollments.py::TestEnrollmentsSync::test_enrollment_skipped_no_student PASSED
...
```

---

## File Structure

```
backend/
├── app/
│   ├── api/
│   │   └── v1/
│   │       ├── endpoints/
│   │       │   ├── sync_programs.py       ← NEW
│   │       │   ├── sync_classes.py        ← NEW
│   │       │   ├── sync_enrollments.py    ← NEW
│   │       │   ├── sync_students.py
│   │       │   └── health.py
│   │       └── router.py                  ← UPDATED
│   ├── core/
│   │   ├── config.py                      ← UPDATED (MOODLE_ENABLED, DEFAULT_TENANT_ID)
│   │   ├── idempotency.py
│   │   └── logging.py
│   ├── domain/
│   │   ├── program.py                     ← NEW
│   │   ├── class_.py                      ← NEW
│   │   ├── enrollment.py                  ← NEW
│   │   └── student.py
│   ├── infra/
│   │   ├── db/
│   │   │   ├── models/
│   │   │   │   ├── program.py             ← NEW
│   │   │   │   ├── class_.py              ← NEW
│   │   │   │   ├── enrollment.py          ← NEW
│   │   │   │   └── student.py
│   │   │   └── session.py
│   │   └── moodle/
│   │       └── users.py                   ← UPDATED
│   ├── ingress/
│   │   └── zoho/
│   │       ├── program_ingress.py         ← NEW
│   │       ├── class_ingress.py           ← NEW
│   │       ├── enrollment_ingress.py      ← NEW
│   │       ├── program_parser.py          ← NEW
│   │       ├── class_parser.py            ← NEW
│   │       ├── enrollment_parser.py       ← NEW
│   │       └── parser.py
│   ├── services/
│   │   ├── program_service.py             ← NEW
│   │   ├── class_service.py               ← NEW
│   │   ├── enrollment_service.py          ← NEW
│   │   ├── program_mapper.py              ← NEW
│   │   ├── class_mapper.py                ← NEW
│   │   ├── enrollment_mapper.py           ← NEW
│   │   └── student_mapper.py
│   └── main.py
├── tests/
│   └── test_sync_endpoints.py             ← NEW
├── requirements.txt
├── setup_db.py
└── .env.example
```

---

## Common Issues & Solutions

### Issue: `class_not_synced_yet` errors

**Cause**: Trying to sync enrollments before syncing classes.

**Solution**: Sync in order:
1. Students
2. Programs
3. Classes (depends on Programs)
4. Enrollments (depends on Students + Classes)

### Issue: Moodle integration returns mock data

**Cause**: `MOODLE_ENABLED=false` in config.

**Solution**: Set `MOODLE_ENABLED=true` and configure Moodle credentials.

### Issue: Idempotency key not matching

**Cause**: Request body slightly different (order, whitespace, etc).

**Solution**: Client library (like Postman) should handle this. If using `curl`, ensure exact JSON formatting.

### Issue: Multi-tenant queries returning wrong data

**Cause**: Not passing `X-Tenant-ID` header.

**Solution**: Always include header or ensure `DEFAULT_TENANT_ID` is correctly set.

---

## Next Steps (Phase 4+)

Future phases will extend this pattern to:

- **Phase 4**: Registrations, Payments, Units, Grades
- **Phase 5**: Grade syncing, reporting dashboards
- **Phase 6**: Advanced integrations (LMS-specific features)

All will follow the same 5-layer architecture and can be implemented independently.

---

## Support & Questions

For issues or questions:

1. Check logs: `tail -f backend.log`
2. Review test cases: `tests/test_sync_endpoints.py`
3. Check database: `psql moodle_zoho_db`
4. Review API docs: `http://localhost:8000/docs`
