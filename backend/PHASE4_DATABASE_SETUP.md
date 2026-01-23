# Database Setup for Phase 4

## Adding New Models to Alembic

### Option 1: Auto-generate Migration (Recommended)

```bash
cd backend

# Generate migration automatically from models
alembic revision --autogenerate -m "Add Phase 4 modules: registrations, payments, units, grades"

# Review the generated migration file in alembic/versions/
# Then apply it:
alembic upgrade head
```

### Option 2: Manual SQL Migration

If Alembic is not set up, use this SQL directly:

```sql
-- Create registrations table
CREATE TABLE IF NOT EXISTS registrations (
    id VARCHAR PRIMARY KEY,
    tenant_id VARCHAR NOT NULL,
    source VARCHAR,
    zoho_id VARCHAR NOT NULL,
    student_zoho_id VARCHAR NOT NULL,
    program_zoho_id VARCHAR NOT NULL,
    enrollment_status VARCHAR NOT NULL,
    registration_date VARCHAR,
    completion_date VARCHAR,
    sync_status VARCHAR,
    data_hash VARCHAR,
    fingerprint VARCHAR,
    version VARCHAR,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_zoho_id) REFERENCES students(zoho_id),
    FOREIGN KEY (program_zoho_id) REFERENCES programs(zoho_id)
);

CREATE INDEX ix_registrations_tenant_zoho ON registrations(tenant_id, zoho_id);
CREATE INDEX ix_registrations_tenant_student_program ON registrations(tenant_id, student_zoho_id, program_zoho_id);

-- Create payments table
CREATE TABLE IF NOT EXISTS payments (
    id VARCHAR PRIMARY KEY,
    tenant_id VARCHAR NOT NULL,
    source VARCHAR,
    zoho_id VARCHAR NOT NULL,
    registration_zoho_id VARCHAR NOT NULL,
    amount FLOAT NOT NULL,
    payment_date VARCHAR,
    payment_method VARCHAR,
    payment_status VARCHAR NOT NULL,
    description VARCHAR,
    sync_status VARCHAR,
    data_hash VARCHAR,
    fingerprint VARCHAR,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (registration_zoho_id) REFERENCES registrations(zoho_id)
);

CREATE INDEX ix_payments_tenant_zoho ON payments(tenant_id, zoho_id);
CREATE INDEX ix_payments_tenant_registration ON payments(tenant_id, registration_zoho_id);

-- Create units table
CREATE TABLE IF NOT EXISTS units (
    id VARCHAR PRIMARY KEY,
    tenant_id VARCHAR NOT NULL,
    source VARCHAR,
    zoho_id VARCHAR NOT NULL,
    unit_code VARCHAR NOT NULL,
    unit_name VARCHAR NOT NULL,
    description VARCHAR,
    credit_hours FLOAT,
    level VARCHAR,
    status VARCHAR NOT NULL,
    sync_status VARCHAR,
    data_hash VARCHAR,
    fingerprint VARCHAR,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX ix_units_tenant_zoho ON units(tenant_id, zoho_id);
CREATE INDEX ix_units_tenant_code ON units(tenant_id, unit_code);

-- Create grades table
CREATE TABLE IF NOT EXISTS grades (
    id VARCHAR PRIMARY KEY,
    tenant_id VARCHAR NOT NULL,
    source VARCHAR,
    zoho_id VARCHAR NOT NULL,
    student_zoho_id VARCHAR NOT NULL,
    unit_zoho_id VARCHAR NOT NULL,
    grade_value VARCHAR NOT NULL,
    score FLOAT,
    grade_date VARCHAR,
    comments VARCHAR,
    sync_status VARCHAR,
    data_hash VARCHAR,
    fingerprint VARCHAR,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_zoho_id) REFERENCES students(zoho_id),
    FOREIGN KEY (unit_zoho_id) REFERENCES units(zoho_id)
);

CREATE INDEX ix_grades_tenant_zoho ON grades(tenant_id, zoho_id);
CREATE INDEX ix_grades_tenant_student_unit ON grades(tenant_id, student_zoho_id, unit_zoho_id);
```

## Verify Installation

```bash
# List all tables
psql -U admin -d moodle_zoho -c "\dt"

# Should show:
# - registrations
# - payments
# - units
# - grades
```

## Sync Order (Important!)

Always sync in this order to maintain referential integrity:

1. **Students** (Phase 1) - Base entity
2. **Programs** (Phase 2) - Base entity
3. **Classes** (Phase 2) - Base entity
4. **Units** (Phase 4) - Base entity
5. **Enrollments** (Phase 3) - Requires Student + Class
6. **Registrations** (Phase 4) - Requires Student + Program
7. **Payments** (Phase 4) - Requires Registration
8. **Grades** (Phase 4) - Requires Student + Unit

## Testing Database

```bash
# Check registrations
SELECT * FROM registrations WHERE tenant_id = 'default' LIMIT 5;

# Check payments for a registration
SELECT p.* FROM payments p
JOIN registrations r ON p.registration_zoho_id = r.zoho_id
WHERE r.tenant_id = 'default' LIMIT 5;

# Check grades for a student
SELECT g.* FROM grades g
WHERE g.student_zoho_id = 'stud_123' AND g.tenant_id = 'default';
```

---

**Note**: If using SQLite (development), these create statements work as-is.  
If using PostgreSQL, adjust data types as needed (VARCHAR → TEXT, FLOAT → NUMERIC, etc.)
