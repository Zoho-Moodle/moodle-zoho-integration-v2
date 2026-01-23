# Phase 4 - Quick Start Guide

## What Was Built?

Complete sync system for 4 Zoho modules:
- ✅ Strict parsers (no guessing)
- ✅ Domain models (Pydantic)
- ✅ Database models (SQLAlchemy)
- ✅ Services with fingerprinting
- ✅ API endpoints
- ✅ Comprehensive tests

## Files Summary

| Component | Files | Status |
|-----------|-------|--------|
| Domain Models | 4 files | ✅ |
| DB Models | 4 files | ✅ |
| Parsers | 4 files | ✅ |
| Mappers | 4 files | ✅ |
| Services | 4 files | ✅ |
| Ingress | 4 files | ✅ |
| Endpoints | 4 files | ✅ |
| Tests | 4 files | ✅ |
| Docs | 2 files | ✅ |
| **Total** | **38 files** | **✅** |

## Step 1: Set Up Database

```bash
cd backend

# Create tables (auto-migration)
alembic upgrade head

# OR manually run SQL from PHASE4_DATABASE_SETUP.md
```

## Step 2: Start Server

```bash
python start_server.py
```

Server will be available at: `http://localhost:8001`

## Step 3: Verify Endpoints

```bash
# Check health
curl http://localhost:8001/v1/health

# Should return: {"status": "ok"}
```

## Step 4: Sync Data from Zoho

### Option A: Using Direct HTTP

```bash
# Sync Registrations
curl -X POST http://localhost:8001/v1/sync/registrations \
  -H "Content-Type: application/json" \
  -d '{
    "data": [
      {
        "id": "reg_001",
        "Student": {"id": "stud_001", "name": "Ahmed"},
        "Program": {"id": "prog_001", "name": "Business"},
        "Enrollment_Status": "Active",
        "Registration_Date": "2026-01-15"
      }
    ]
  }'

# Response:
# {
#   "status": "success",
#   "results": [
#     {
#       "zoho_registration_id": "reg_001",
#       "status": "NEW",
#       "message": "Registration created"
#     }
#   ]
# }
```

### Option B: From Zoho Webhook

Update your Zoho Deluge function to POST to:
- `https://your-tunnel.ngrok.io/v1/sync/registrations`
- `https://your-tunnel.ngrok.io/v1/sync/payments`
- `https://your-tunnel.ngrok.io/v1/sync/units`
- `https://your-tunnel.ngrok.io/v1/sync/grades`

## Step 5: Monitor Results

Check database:

```bash
# Connect to database
psql -U admin -d moodle_zoho

# Query registrations
SELECT COUNT(*) as total_registrations FROM registrations WHERE tenant_id = 'default';

# Query by status
SELECT sync_status, COUNT(*) FROM registrations GROUP BY sync_status;
```

## Step 6: Run Tests

```bash
# Test registrations
pytest tests/test_sync_registrations.py -v

# Test all Phase 4 modules
pytest tests/test_sync_*.py -v
```

## API Reference

### POST /v1/sync/registrations
**Body**:
```json
{
  "data": [
    {
      "id": "reg_123",
      "Student": {"id": "stud_456", "name": "..."},
      "Program": {"id": "prog_789", "name": "..."},
      "Enrollment_Status": "Active",
      "Registration_Date": "2026-01-15",
      "Completion_Date": "2027-01-15"
    }
  ]
}
```

### POST /v1/sync/payments
**Body**:
```json
{
  "data": [
    {
      "id": "pay_123",
      "Registration": {"id": "reg_456", "name": "..."},
      "Amount": 500.00,
      "Payment_Status": "Completed",
      "Payment_Date": "2026-01-20",
      "Payment_Method": "Credit Card"
    }
  ]
}
```

### POST /v1/sync/units
**Body**:
```json
{
  "data": [
    {
      "id": "unit_123",
      "Unit_Code": "UNIT001",
      "Unit_Name": "Introduction to Business",
      "Credit_Hours": 30,
      "Level": "L3",
      "Status": "Active"
    }
  ]
}
```

### POST /v1/sync/grades
**Body**:
```json
{
  "data": [
    {
      "id": "grade_123",
      "Student": {"id": "stud_456", "name": "..."},
      "Unit": {"id": "unit_789", "name": "..."},
      "Grade_Value": "A",
      "Score": 95,
      "Grade_Date": "2026-06-15"
    }
  ]
}
```

## Response Format (All Endpoints)

```json
{
  "status": "success",
  "tenant_id": "default",
  "idempotency_key": "abc123...",
  "results": [
    {
      "zoho_registration_id": "reg_001",
      "status": "NEW|UNCHANGED|UPDATED|INVALID|ERROR",
      "message": "Human-readable message",
      "changed_fields": {...}  // Only for UPDATED
    }
  ]
}
```

## Error Handling

**Invalid Record** (e.g., missing dependencies):
```json
{
  "status": "INVALID",
  "message": "Student stud_999 not found. Create student first."
}
```

**Duplicate Request** (idempotency):
```json
{
  "status": "ignored",
  "reason": "duplicate_request",
  "idempotency_key": "..."
}
```

## Sync States Explained

| State | Meaning | Action |
|-------|---------|--------|
| NEW | Record created | Insert into DB |
| UNCHANGED | No changes detected | Skip update |
| UPDATED | Fields changed | Update DB |
| INVALID | Missing dependencies/bad data | Reject record |
| ERROR | Exception during sync | Log error |

## Troubleshooting

**404 Endpoint not found**
- Ensure database tables created
- Server may need restart

**500 Internal Server Error**
- Check server logs
- Verify payload format matches expected schema

**INVALID status in results**
- Check foreign key requirements
- Ensure parent records created first (Unit before Grade, etc.)

**Duplicate requests being ignored**
- This is expected behavior (idempotency protection)
- Same exact payload will be ignored for 1 hour

## Next Steps

1. Test with actual Zoho data
2. Monitor sync success rates
3. Add custom business rules as needed
4. Scale to production environment

---

For detailed documentation, see:
- `PHASE4_IMPLEMENTATION.md` - Architecture & design
- `PHASE4_DATABASE_SETUP.md` - Database setup

---
