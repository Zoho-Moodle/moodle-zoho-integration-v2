-- Phase 4 core tables: units, registrations, payments, grades
-- Aligns with SQLAlchemy models in app/infra/db/models/

-- Create units first (no external FKs)
CREATE TABLE IF NOT EXISTS units (
    id TEXT PRIMARY KEY,
    tenant_id TEXT NOT NULL,
    source TEXT,
    zoho_id TEXT NOT NULL,
    unit_code TEXT NOT NULL,
    unit_name TEXT NOT NULL,
    description TEXT,
    credit_hours DOUBLE PRECISION,
    level TEXT,
    status TEXT NOT NULL,
    sync_status TEXT,
    data_hash TEXT,
    fingerprint TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS ix_units_tenant_zoho ON units(tenant_id, zoho_id);
CREATE INDEX IF NOT EXISTS ix_units_tenant_code ON units(tenant_id, unit_code);

-- Create registrations (depends on students + programs)
CREATE TABLE IF NOT EXISTS registrations (
    id TEXT PRIMARY KEY,
    tenant_id TEXT NOT NULL,
    source TEXT,
    zoho_id TEXT NOT NULL,
    student_zoho_id TEXT NOT NULL,
    program_zoho_id TEXT NOT NULL,
    enrollment_status TEXT NOT NULL,
    registration_date TEXT,
    completion_date TEXT,
    sync_status TEXT,
    data_hash TEXT,
    fingerprint TEXT,
    version TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reg_student FOREIGN KEY (student_zoho_id) REFERENCES students(zoho_id),
    CONSTRAINT fk_reg_program FOREIGN KEY (program_zoho_id) REFERENCES programs(zoho_id)
);
CREATE INDEX IF NOT EXISTS ix_registrations_tenant_student_program ON registrations(tenant_id, student_zoho_id, program_zoho_id);
CREATE INDEX IF NOT EXISTS ix_registrations_tenant_zoho ON registrations(tenant_id, zoho_id);

-- Create payments (depends on registrations)
CREATE TABLE IF NOT EXISTS payments (
    id TEXT PRIMARY KEY,
    tenant_id TEXT NOT NULL,
    source TEXT,
    zoho_id TEXT NOT NULL,
    registration_zoho_id TEXT NOT NULL,
    amount DOUBLE PRECISION NOT NULL,
    payment_date TEXT,
    payment_method TEXT,
    payment_status TEXT NOT NULL,
    description TEXT,
    sync_status TEXT,
    data_hash TEXT,
    fingerprint TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pay_registration FOREIGN KEY (registration_zoho_id) REFERENCES registrations(zoho_id)
);
CREATE INDEX IF NOT EXISTS ix_payments_tenant_registration ON payments(tenant_id, registration_zoho_id);
CREATE INDEX IF NOT EXISTS ix_payments_tenant_zoho ON payments(tenant_id, zoho_id);

-- Create grades (depends on students + units)
CREATE TABLE IF NOT EXISTS grades (
    id TEXT PRIMARY KEY,
    tenant_id TEXT NOT NULL,
    source TEXT,
    zoho_id TEXT NOT NULL,
    student_zoho_id TEXT NOT NULL,
    unit_zoho_id TEXT NOT NULL,
    grade_value TEXT NOT NULL,
    score DOUBLE PRECISION,
    grade_date TEXT,
    comments TEXT,
    sync_status TEXT,
    data_hash TEXT,
    fingerprint TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_grade_student FOREIGN KEY (student_zoho_id) REFERENCES students(zoho_id),
    CONSTRAINT fk_grade_unit FOREIGN KEY (unit_zoho_id) REFERENCES units(zoho_id)
);
CREATE INDEX IF NOT EXISTS ix_grades_tenant_student_unit ON grades(tenant_id, student_zoho_id, unit_zoho_id);
CREATE INDEX IF NOT EXISTS ix_grades_tenant_zoho ON grades(tenant_id, zoho_id);
