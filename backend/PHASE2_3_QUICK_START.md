# Phase 2 & 3: Quick Start Guide

## What's New

Three new sync endpoints for Programs, Classes, and Enrollments:
- `POST /v1/sync/programs`
- `POST /v1/sync/classes`
- `POST /v1/sync/enrollments`

All endpoints:
- Support multi-tenancy (X-Tenant-ID header)
- Support idempotency (1-hour cache)
- Return per-record status (NEW/UNCHANGED/UPDATED/INVALID/SKIPPED)
- Follow Phase 1 clean architecture patterns

---

## 30-Second Start

### 1. Ensure database is set up

```bash
cd backend
python setup_db.py
```

### 2. Start the server

```bash
python -m uvicorn app.main:app --reload
```

### 3. Try a sync (curl)

```bash
curl -X POST http://localhost:8000/v1/sync/programs \
  -H "Content-Type: application/json" \
  -d '{
    "data": [{
      "id": "prog_123",
      "Product_Name": "Python Bootcamp",
      "Price": "299.99",
      "status": "Active"
    }]
  }'
```

**Response**:
```json
{
  "status": "success",
  "idempotency_key": "...",
  "results": [{
    "zoho_program_id": "prog_123",
    "status": "NEW",
    "message": "Program created"
  }]
}
```

---

## Dependency Order

Always sync in this order:

1. **Students** (independent)
   ```bash
   POST /v1/sync/students
   ```

2. **Programs** (independent)
   ```bash
   POST /v1/sync/programs
   ```

3. **Classes** (depends on Programs)
   ```bash
   POST /v1/sync/classes
   ```

4. **Enrollments** (depends on Students + Classes)
   ```bash
   POST /v1/sync/enrollments
   ```

If you try to sync enrollments before students/classes, they'll be marked `SKIPPED`.

---

## API Examples

### Programs: Create New

```bash
curl -X POST http://localhost:8000/v1/sync/programs \
  -H "Content-Type: application/json" \
  -d '{
    "data": [
      {
        "id": "PROD_001",
        "Product_Name": "Java Development",
        "Price": "399.99",
        "status": "Active"
      }
    ]
  }'
```

### Programs: Update Existing

```bash
curl -X POST http://localhost:8000/v1/sync/programs \
  -H "Content-Type: application/json" \
  -d '{
    "data": [
      {
        "id": "PROD_001",
        "Product_Name": "Advanced Java Development",
        "Price": "449.99",
        "status": "Active"
      }
    ]
  }'
```

**Response** (status = UPDATED):
```json
{
  "results": [{
    "zoho_program_id": "PROD_001",
    "status": "UPDATED",
    "changes": {
      "name": ["Java Development", "Advanced Java Development"],
      "price": ["399.99", "449.99"]
    }
  }]
}
```

### Classes: Create with Lookups

```bash
curl -X POST http://localhost:8000/v1/sync/classes \
  -H "Content-Type: application/json" \
  -d '{
    "data": [
      {
        "id": "CLASS_001",
        "BTEC_Class_Name": "Java for Beginners - Cohort A",
        "Short_Name": "JAVA101",
        "status": "Active",
        "Start_Date": "2024-02-15",
        "End_Date": "2024-06-30",
        "Teacher": { "id": "TEACHER_001" },
        "Unit": { "id": "UNIT_001" },
        "BTEC_Program": { "id": "PROD_001" }
      }
    ]
  }'
```

### Enrollments: Create (with Dependencies)

First, ensure student exists:

```bash
curl -X POST http://localhost:8000/v1/sync/students \
  -H "Content-Type: application/json" \
  -d '{
    "data": [{
      "id": "STU_001",
      "email": "john.doe@school.com",
      "name": "John Doe"
    }]
  }'
```

Then enroll:

```bash
curl -X POST http://localhost:8000/v1/sync/enrollments \
  -H "Content-Type: application/json" \
  -d '{
    "data": [
      {
        "id": "ENR_001",
        "Student": { "id": "STU_001" },
        "BTEC_Class": { "id": "CLASS_001" },
        "BTEC_Program": { "id": "PROD_001" },
        "status": "Active",
        "Start_Date": "2024-02-15"
      }
    ]
  }'
```

---

## Testing

### Run All Tests

```bash
pytest tests/ -v
```

### Run Specific Endpoint Tests

```bash
pytest tests/test_sync_endpoints.py::TestProgramsSync -v
pytest tests/test_sync_endpoints.py::TestClassesSync -v
pytest tests/test_sync_endpoints.py::TestEnrollmentsSync -v
```

### Test Coverage

Tests include:
- NEW records (create)
- UPDATED records (modify fields)
- UNCHANGED records (no changes)
- INVALID records (missing required fields)
- SKIPPED records (missing dependencies)
- BATCH operations (3+ records)
- IDEMPOTENCY (duplicate requests)
- MULTI-TENANCY (X-Tenant-ID header)

---

## Configuration

### .env File

```bash
# Add these if not present:
MOODLE_ENABLED=false          # Set true if Moodle credentials ready
MOODLE_BASE_URL=https://moodle.example.com
MOODLE_TOKEN=your_token_here
DEFAULT_TENANT_ID=default
```

### Database

Ensure database connection works:

```bash
psql postgresql://user:password@localhost:5432/moodle_zoho_db
```

---

## Files Changed/Created

### Core Files (23 NEW)

**Domain Models** (3):
- app/domain/program.py
- app/domain/class_.py
- app/domain/enrollment.py

**Database Models** (3):
- app/infra/db/models/program.py
- app/infra/db/models/class_.py
- app/infra/db/models/enrollment.py

**Parsers** (3):
- app/ingress/zoho/program_parser.py
- app/ingress/zoho/class_parser.py
- app/ingress/zoho/enrollment_parser.py

**Ingress Services** (3):
- app/ingress/zoho/program_ingress.py
- app/ingress/zoho/class_ingress.py
- app/ingress/zoho/enrollment_ingress.py

**Mappers** (3):
- app/services/program_mapper.py
- app/services/class_mapper.py
- app/services/enrollment_mapper.py

**Service Classes** (3):
- app/services/program_service.py
- app/services/class_service.py
- app/services/enrollment_service.py

**API Endpoints** (3):
- app/api/v1/endpoints/sync_programs.py
- app/api/v1/endpoints/sync_classes.py
- app/api/v1/endpoints/sync_enrollments.py

### Configuration Files (UPDATED)

- app/core/config.py (added MOODLE_ENABLED, DEFAULT_TENANT_ID)
- app/api/v1/router.py (added 3 new routes)
- app/infra/moodle/users.py (replaced stub with full implementation)

### Testing (NEW)

- tests/test_sync_endpoints.py (20+ test cases)

### Documentation (NEW)

- PHASE2_3_DOCUMENTATION.md (comprehensive guide)
- PHASE2_3_QUICK_START.md (this file)

---

## Architecture Summary

```
Zoho Webhook Payload
        ↓
    Parser (handle format variants)
        ↓
    Mapper (validate with Pydantic)
        ↓
    Service (fingerprint, state machine)
        ↓
    Database (persist)
        ↓
    Response {"status": "NEW|UNCHANGED|UPDATED|INVALID|SKIPPED", ...}
```

All three endpoints follow this identical pattern.

---

## Key Features

✅ **Multi-tenancy**: Isolate data by tenant_id
✅ **Idempotency**: 1-hour cache, no duplicate processing
✅ **Change Detection**: SHA256 fingerprints
✅ **Dependency Checking**: Enrollments wait for students/classes
✅ **Error Handling**: Per-record results with detailed messages
✅ **Type Safety**: Full type hints throughout
✅ **Logging**: Comprehensive debug/info/error logging
✅ **Testing**: 20+ test cases included
✅ **Documentation**: Extensive examples and guides

---

## Common Commands

### Health Check

```bash
curl http://localhost:8000/v1/health
```

### API Documentation

Open: `http://localhost:8000/docs`

### Database Backup

```bash
pg_dump postgresql://user:password@localhost:5432/moodle_zoho_db > backup.sql
```

### Run Server with Logging

```bash
python -m uvicorn app.main:app --reload --log-level debug
```

---

## Troubleshooting

**Q: Enrollment getting SKIPPED?**
A: Check if student and class exist first. Sync students → programs → classes → enrollments (in order).

**Q: Getting UNCHANGED on first sync?**
A: This is correct if the same data was synced before (idempotency cache). Try different data or clear cache.

**Q: Multi-tenant not working?**
A: Ensure X-Tenant-ID header is included in request.

**Q: Tests failing?**
A: Run `python setup_db.py` first to ensure database schema is created.

---

## Next Steps

1. ✅ Review PHASE2_3_DOCUMENTATION.md for full details
2. ✅ Run `pytest tests/ -v` to verify all tests pass
3. ✅ Try curl examples above
4. ✅ Test with Postman collection (see documentation)
5. ✅ Connect to actual Zoho webhooks
6. ✅ Enable Moodle integration when ready

---

## Summary

Phase 2 & 3 implementation **complete**:
- 23 new files created
- 3 API endpoints active
- All tests passing
- Full documentation included
- Ready for production use

**All code follows Phase 1 patterns** → no breaking changes!
