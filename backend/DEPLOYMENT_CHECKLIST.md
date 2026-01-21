# Complete Phase 2 & 3 Deployment Checklist

## Pre-Deployment Verification

### 1. Code Quality Check

- [ ] All files exist:
  ```bash
  # Domain models
  ls -la app/domain/program.py app/domain/class_.py app/domain/enrollment.py
  
  # DB models
  ls -la app/infra/db/models/program.py app/infra/db/models/class_.py app/infra/db/models/enrollment.py
  
  # Parsers
  ls -la app/ingress/zoho/program_parser.py app/ingress/zoho/class_parser.py app/ingress/zoho/enrollment_parser.py
  
  # Services
  ls -la app/services/program_service.py app/services/class_service.py app/services/enrollment_service.py
  
  # Endpoints
  ls -la app/api/v1/endpoints/sync_programs.py app/api/v1/endpoints/sync_classes.py app/api/v1/endpoints/sync_enrollments.py
  ```

- [ ] No syntax errors:
  ```bash
  python -m py_compile app/domain/program.py
  python -m py_compile app/domain/class_.py
  python -m py_compile app/domain/enrollment.py
  python -m py_compile app/services/program_service.py
  python -m py_compile app/services/class_service.py
  python -m py_compile app/services/enrollment_service.py
  ```

### 2. Dependencies

- [ ] Python 3.9+:
  ```bash
  python --version
  ```

- [ ] Required packages installed:
  ```bash
  pip install fastapi uvicorn sqlalchemy psycopg2-binary pydantic pydantic-settings requests
  ```

- [ ] Or via requirements.txt:
  ```bash
  pip install -r requirements.txt
  ```

### 3. Database Setup

- [ ] PostgreSQL running:
  ```bash
  psql --version
  psql -c "SELECT 1"  # Quick connection test
  ```

- [ ] Database exists:
  ```bash
  createdb moodle_zoho_db
  ```

- [ ] Schema created:
  ```bash
  cd backend
  python setup_db.py
  ```

- [ ] Tables exist:
  ```bash
  psql moodle_zoho_db -c "\dt"
  ```

  Should show:
  ```
  program | ...
  class   | ...
  enrollment | ...
  student | ...
  ```

### 4. Environment Configuration

- [ ] .env file exists with required variables:
  ```bash
  cat .env | grep DATABASE_URL
  cat .env | grep LOG_LEVEL
  cat .env | grep DEFAULT_TENANT_ID
  ```

  Or create if missing:
  ```bash
  cat > .env << EOF
  DATABASE_URL=postgresql://user:password@localhost:5432/moodle_zoho_db
  APP_NAME=Moodle Zoho Integration
  ENV=development
  LOG_LEVEL=INFO
  MOODLE_ENABLED=false
  DEFAULT_TENANT_ID=default
  EOF
  ```

---

## Testing

### Unit Tests

- [ ] Test file exists:
  ```bash
  ls -la tests/test_sync_endpoints.py
  ```

- [ ] Pytest installed:
  ```bash
  pip install pytest pytest-asyncio
  ```

- [ ] Run all tests:
  ```bash
  pytest tests/ -v
  ```

  Expected: All tests PASSED ✅

- [ ] Run specific test suites:
  ```bash
  pytest tests/test_sync_endpoints.py::TestProgramsSync -v  # 6 tests
  pytest tests/test_sync_endpoints.py::TestClassesSync -v    # 5 tests
  pytest tests/test_sync_endpoints.py::TestEnrollmentsSync -v  # 5 tests
  ```

### Manual API Testing (curl)

- [ ] Health check:
  ```bash
  curl http://localhost:8000/v1/health
  # Expected: {"status": "healthy"}
  ```

- [ ] Create program:
  ```bash
  curl -X POST http://localhost:8000/v1/sync/programs \
    -H "Content-Type: application/json" \
    -d '{"data": [{"id": "test_prog", "Product_Name": "Test", "Price": "99", "status": "Active"}]}'
  # Expected: {"status": "success", "results": [{..., "status": "NEW"}]}
  ```

- [ ] Create class:
  ```bash
  curl -X POST http://localhost:8000/v1/sync/classes \
    -H "Content-Type: application/json" \
    -d '{"data": [{"id": "test_class", "BTEC_Class_Name": "Test", "Short_Name": "T", "status": "Active"}]}'
  # Expected: status NEW
  ```

- [ ] Try enrollment (should skip):
  ```bash
  curl -X POST http://localhost:8000/v1/sync/enrollments \
    -H "Content-Type: application/json" \
    -d '{"data": [{"id": "test_enr", "Student": {"id": "fake"}, "BTEC_Class": {"id": "fake"}, "status": "Active"}]}'
  # Expected: status SKIPPED
  ```

---

## Server Start Verification

### Development Mode

- [ ] Start server:
  ```bash
  cd backend
  python -m uvicorn app.main:app --reload --host 0.0.0.0 --port 8000
  ```

  Expected output:
  ```
  INFO:     Uvicorn running on http://0.0.0.0:8000
  INFO:     Application startup complete
  ```

- [ ] Server accessible:
  ```bash
  curl http://localhost:8000/v1/health
  ```

- [ ] API docs accessible:
  Open browser: `http://localhost:8000/docs`
  Should show Swagger UI with all endpoints

- [ ] Endpoints listed:
  - [x] POST /v1/sync/programs
  - [x] POST /v1/sync/classes
  - [x] POST /v1/sync/enrollments
  - [x] POST /v1/sync/students (Phase 1)
  - [x] GET /v1/health

### Production Mode (Optional)

- [ ] Build for production:
  ```bash
  pip install gunicorn
  gunicorn app.main:app --workers 4 --worker-class uvicorn.workers.UvicornWorker --bind 0.0.0.0:8000
  ```

- [ ] Server starts without errors

---

## Integration Tests

### Test Workflow: Complete Sync Chain

**Step 1: Sync Programs**
```bash
curl -X POST http://localhost:8000/v1/sync/programs \
  -H "Content-Type: application/json" \
  -d '{
    "data": [{
      "id": "prog_integration_test",
      "Product_Name": "Integration Test Program",
      "Price": "199.99",
      "status": "Active"
    }]
  }'
```
Expected: `"status": "NEW"`

- [ ] ✅ Passed

**Step 2: Sync Classes**
```bash
curl -X POST http://localhost:8000/v1/sync/classes \
  -H "Content-Type: application/json" \
  -d '{
    "data": [{
      "id": "class_integration_test",
      "BTEC_Class_Name": "Integration Test Class",
      "Short_Name": "ITC",
      "status": "Active",
      "Start_Date": "2024-01-15",
      "End_Date": "2024-06-30",
      "BTEC_Program": {"id": "prog_integration_test"}
    }]
  }'
```
Expected: `"status": "NEW"`

- [ ] ✅ Passed

**Step 3: Sync Student**
```bash
curl -X POST http://localhost:8000/v1/sync/students \
  -H "Content-Type: application/json" \
  -d '{
    "data": [{
      "id": "stu_integration_test",
      "email": "integration@test.com",
      "name": "Integration Test"
    }]
  }'
```
Expected: `"status": "NEW"`

- [ ] ✅ Passed

**Step 4: Sync Enrollment**
```bash
curl -X POST http://localhost:8000/v1/sync/enrollments \
  -H "Content-Type: application/json" \
  -d '{
    "data": [{
      "id": "enr_integration_test",
      "Student": {"id": "stu_integration_test"},
      "BTEC_Class": {"id": "class_integration_test"},
      "BTEC_Program": {"id": "prog_integration_test"},
      "status": "Active",
      "Start_Date": "2024-01-15"
    }]
  }'
```
Expected: `"status": "NEW"` (not SKIPPED because deps exist)

- [ ] ✅ Passed

### Test Workflow: Idempotency

**Same request twice, 1 second apart:**

First:
```bash
curl -X POST http://localhost:8000/v1/sync/programs \
  -H "Content-Type: application/json" \
  -d '{"data": [{"id": "idem_test", "Product_Name": "Idem", "Price": "99", "status": "Active"}]}'
```

Save `idempotency_key` from response.

Second (duplicate):
```bash
curl -X POST http://localhost:8000/v1/sync/programs \
  -H "Content-Type: application/json" \
  -d '{"data": [{"id": "idem_test", "Product_Name": "Idem", "Price": "99", "status": "Active"}]}'
```

- [ ] Both return same `idempotency_key`
- [ ] Second is faster (cached)
- [ ] Results are identical

### Test Workflow: Multi-Tenancy

**Sync for tenant_001:**
```bash
curl -X POST http://localhost:8000/v1/sync/programs \
  -H "Content-Type: application/json" \
  -H "X-Tenant-ID: tenant_001" \
  -d '{"data": [{"id": "prog_t1", "Product_Name": "Tenant 1", "Price": "99", "status": "Active"}]}'
```

**Sync for tenant_002:**
```bash
curl -X POST http://localhost:8000/v1/sync/programs \
  -H "Content-Type: application/json" \
  -H "X-Tenant-ID: tenant_002" \
  -d '{"data": [{"id": "prog_t2", "Product_Name": "Tenant 2", "Price": "99", "status": "Active"}]}'
```

**Verify isolation in database:**
```bash
psql moodle_zoho_db -c "SELECT tenant_id, zoho_id, name FROM program"
```

Expected output:
```
 tenant_id | zoho_id | name
-----------+---------+----------
 tenant_001| prog_t1 | Tenant 1
 tenant_002| prog_t2 | Tenant 2
```

- [ ] ✅ Data properly isolated by tenant

---

## Documentation Review

- [ ] README.md exists and updated
- [ ] PHASE2_3_DOCUMENTATION.md exists (technical guide)
- [ ] PHASE2_3_QUICK_START.md exists (quick examples)
- [ ] IMPLEMENTATION_SUMMARY.md exists (overview)
- [ ] API_DOCUMENTATION.md exists (Phase 1)

---

## Git & Version Control

- [ ] Commit all changes:
  ```bash
  git add .
  git commit -m "Phase 2 & 3: Programs, Classes, Enrollments implementation"
  ```

- [ ] Check status:
  ```bash
  git status  # Should be clean
  ```

- [ ] Review changes:
  ```bash
  git log --oneline -10
  ```

---

## Monitoring & Logs

- [ ] Check application logs:
  ```bash
  tail -f app.log
  ```

- [ ] Check database logs (if available):
  ```bash
  # PostgreSQL logs location varies
  # Typically: /var/lib/postgresql/log/ or systemctl logs
  ```

- [ ] No errors in logs:
  ```bash
  grep ERROR app.log
  # Should return nothing or only expected error logs
  ```

---

## Security Checklist

- [ ] .env file in .gitignore:
  ```bash
  grep -q ".env" .gitignore && echo "✅ OK" || echo "❌ MISSING"
  ```

- [ ] No secrets in code:
  ```bash
  grep -r "password\|token\|secret" app/ | grep -v ".env.example" | grep -v "# " || echo "✅ OK"
  ```

- [ ] Database credentials secure:
  ```bash
  # Ensure DATABASE_URL only in .env (not in code)
  grep -r "DATABASE_URL=" app/ && echo "❌ FOUND IN CODE" || echo "✅ OK"
  ```

- [ ] Moodle credentials not committed:
  ```bash
  grep -r "MOODLE_TOKEN" app/ && echo "❌ FOUND IN CODE" || echo "✅ OK"
  ```

---

## Final Verification

### Checklist Complete?

- [ ] All 26 files created/updated
- [ ] No syntax errors
- [ ] Database schema created
- [ ] Tests passing (20+)
- [ ] Server starts cleanly
- [ ] API endpoints responding
- [ ] Documentation complete
- [ ] Git committed
- [ ] Logs clean
- [ ] Security checks passed

### Ready for Staging?

If ALL checkboxes above are checked ✅:

**YES** → Proceed to staging deployment
**NO** → Review failed items and fix

---

## Staging Deployment

```bash
# 1. Pull latest code
git pull origin main

# 2. Install dependencies
pip install -r requirements.txt

# 3. Setup database
python backend/setup_db.py

# 4. Run tests
pytest tests/ -v

# 5. Start server
python -m uvicorn app.main:app --host 0.0.0.0 --port 8000

# 6. Verify endpoints
curl http://your-staging-url:8000/v1/health
curl http://your-staging-url:8000/docs
```

---

## Rollback Plan

If issues occur:

```bash
# 1. Stop server
# (Ctrl+C or systemctl stop)

# 2. Restore previous version
git checkout HEAD~1

# 3. Restart server
python -m uvicorn app.main:app --host 0.0.0.0 --port 8000

# 4. Verify Phase 1 still works
curl http://localhost:8000/v1/sync/students  # Should work
```

---

## Success Criteria

✅ **All items below must be TRUE:**

- All 26 files exist and contain valid Python code
- All 20+ tests pass (pytest)
- Server starts without errors
- Health endpoint responds
- All 3 new endpoints (programs, classes, enrollments) respond
- Idempotency works (duplicate requests cached)
- Multi-tenancy works (X-Tenant-ID isolation)
- Dependency checking works (enrollments skip when deps missing)
- Database schema correct (all tables created)
- Documentation complete (3 comprehensive guides)
- Phase 1 still fully functional (students endpoint works)
- No breaking changes
- Git history clean

---

## Sign-Off

Date: ___________
Reviewed by: ___________
Approved for production: ___________

---

## Next Steps (Post-Deployment)

1. Monitor production logs for 24 hours
2. Configure Zoho webhooks to point to production
3. Enable Moodle integration when Moodle instance ready
4. Plan Phase 4 (Registrations, Payments, Units, Grades)
5. Schedule capacity planning review

---

**All systems GO for Phase 2 & 3 deployment!** 🚀
