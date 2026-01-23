-- Complete database schema for all phases (1-4)
-- PostgreSQL compatible

-- Phase 1: Students
CREATE TABLE IF NOT EXISTS students (
    id TEXT PRIMARY KEY,
    tenant_id TEXT NOT NULL,
    source TEXT,
    zoho_id TEXT UNIQUE NOT NULL,
    moodle_user_id TEXT,
    userid TEXT,
    username TEXT UNIQUE,
    display_name TEXT,
    academic_email TEXT NOT NULL,
    birth_date TEXT,
    phone TEXT,
    address TEXT,
    city TEXT,
    country TEXT,
    record_image TEXT,
    status TEXT,
    sync_status TEXT,
    last_sync INTEGER,
    data_hash TEXT,
    fingerprint TEXT,
    moodle_userid INTEGER,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS ix_students_id ON students(id);
CREATE INDEX IF NOT EXISTS ix_students_zoho_id ON students(zoho_id);
CREATE INDEX IF NOT EXISTS ix_students_username ON students(username);
CREATE INDEX IF NOT EXISTS ix_students_moodle_userid ON students(moodle_userid);

-- Phase 2: Programs
CREATE TABLE IF NOT EXISTS programs (
    id TEXT PRIMARY KEY,
    tenant_id TEXT NOT NULL,
    source TEXT,
    zoho_id TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    price DOUBLE PRECISION,
    moodle_id TEXT,
    status TEXT,
    fingerprint TEXT,
    last_sync TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS ix_programs_id ON programs(id);
CREATE INDEX IF NOT EXISTS ix_programs_zoho_id ON programs(zoho_id);
CREATE INDEX IF NOT EXISTS ix_programs_moodle_id ON programs(moodle_id);
CREATE UNIQUE INDEX IF NOT EXISTS idx_program_tenant_zoho_id ON programs(tenant_id, zoho_id);

-- Phase 2: Classes
CREATE TABLE IF NOT EXISTS classes (
    id TEXT PRIMARY KEY,
    tenant_id TEXT NOT NULL,
    source TEXT,
    zoho_id TEXT NOT NULL,
    name TEXT NOT NULL,
    short_name TEXT,
    status TEXT,
    start_date DATE,
    end_date DATE,
    moodle_class_id TEXT,
    ms_teams_id TEXT,
    teacher_zoho_id TEXT,
    unit_zoho_id TEXT,
    program_zoho_id TEXT,
    fingerprint TEXT,
    last_sync TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS ix_classes_id ON classes(id);
CREATE INDEX IF NOT EXISTS ix_classes_zoho_id ON classes(zoho_id);
CREATE INDEX IF NOT EXISTS ix_classes_moodle_class_id ON classes(moodle_class_id);
CREATE INDEX IF NOT EXISTS ix_classes_teacher_zoho_id ON classes(teacher_zoho_id);
CREATE INDEX IF NOT EXISTS ix_classes_program_zoho_id ON classes(program_zoho_id);
CREATE UNIQUE INDEX IF NOT EXISTS idx_class_tenant_zoho_id ON classes(tenant_id, zoho_id);
CREATE INDEX IF NOT EXISTS idx_class_program_zoho_id ON classes(tenant_id, program_zoho_id);

-- Phase 3: Enrollments
CREATE TABLE IF NOT EXISTS enrollments (
    id TEXT PRIMARY KEY,
    tenant_id TEXT NOT NULL,
    source TEXT,
    zoho_id TEXT NOT NULL,
    enrollment_name TEXT,
    student_zoho_id TEXT NOT NULL,
    student_name TEXT,
    class_zoho_id TEXT NOT NULL,
    class_name TEXT,
    program_zoho_id TEXT,
    start_date DATE,
    status TEXT,
    moodle_course_id TEXT,
    moodle_user_id INTEGER,
    moodle_enrollment_id INTEGER,
    last_sync_date TIMESTAMP,
    fingerprint TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS ix_enrollments_id ON enrollments(id);
CREATE INDEX IF NOT EXISTS ix_enrollments_zoho_id ON enrollments(zoho_id);
CREATE INDEX IF NOT EXISTS ix_enrollments_student_zoho_id ON enrollments(student_zoho_id);
CREATE INDEX IF NOT EXISTS ix_enrollments_class_zoho_id ON enrollments(class_zoho_id);
CREATE INDEX IF NOT EXISTS ix_enrollments_program_zoho_id ON enrollments(program_zoho_id);
CREATE INDEX IF NOT EXISTS ix_enrollments_moodle_course_id ON enrollments(moodle_course_id);
CREATE UNIQUE INDEX IF NOT EXISTS idx_enrollment_tenant_zoho_id ON enrollments(tenant_id, zoho_id);
CREATE INDEX IF NOT EXISTS idx_enrollment_student_class ON enrollments(tenant_id, student_zoho_id, class_zoho_id);
CREATE INDEX IF NOT EXISTS idx_enrollment_student ON enrollments(tenant_id, student_zoho_id);
CREATE INDEX IF NOT EXISTS idx_enrollment_class ON enrollments(tenant_id, class_zoho_id);

-- Phase 4: Units
CREATE TABLE IF NOT EXISTS units (
    id TEXT PRIMARY KEY,
    tenant_id TEXT NOT NULL,
    source TEXT,
    zoho_id TEXT UNIQUE NOT NULL,
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
CREATE INDEX IF NOT EXISTS ix_units_id ON units(id);
CREATE INDEX IF NOT EXISTS ix_units_zoho_id ON units(zoho_id);
CREATE INDEX IF NOT EXISTS ix_units_tenant_zoho ON units(tenant_id, zoho_id);
CREATE INDEX IF NOT EXISTS ix_units_tenant_code ON units(tenant_id, unit_code);

-- Phase 4: Registrations
CREATE TABLE IF NOT EXISTS registrations (
    id TEXT PRIMARY KEY,
    tenant_id TEXT NOT NULL,
    source TEXT,
    zoho_id TEXT UNIQUE NOT NULL,
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
CREATE INDEX IF NOT EXISTS ix_registrations_id ON registrations(id);
CREATE INDEX IF NOT EXISTS ix_registrations_zoho_id ON registrations(zoho_id);
CREATE INDEX IF NOT EXISTS ix_registrations_student_zoho_id ON registrations(student_zoho_id);
CREATE INDEX IF NOT EXISTS ix_registrations_program_zoho_id ON registrations(program_zoho_id);
CREATE INDEX IF NOT EXISTS ix_registrations_tenant_student_program ON registrations(tenant_id, student_zoho_id, program_zoho_id);
CREATE INDEX IF NOT EXISTS ix_registrations_tenant_zoho ON registrations(tenant_id, zoho_id);

-- Phase 4: Payments
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
CREATE INDEX IF NOT EXISTS ix_payments_id ON payments(id);
CREATE INDEX IF NOT EXISTS ix_payments_zoho_id ON payments(zoho_id);
CREATE INDEX IF NOT EXISTS ix_payments_registration_zoho_id ON payments(registration_zoho_id);
CREATE INDEX IF NOT EXISTS ix_payments_tenant_registration ON payments(tenant_id, registration_zoho_id);
CREATE INDEX IF NOT EXISTS ix_payments_tenant_zoho ON payments(tenant_id, zoho_id);

-- Phase 4: Grades
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
CREATE INDEX IF NOT EXISTS ix_grades_id ON grades(id);
CREATE INDEX IF NOT EXISTS ix_grades_zoho_id ON grades(zoho_id);
CREATE INDEX IF NOT EXISTS ix_grades_student_zoho_id ON grades(student_zoho_id);
CREATE INDEX IF NOT EXISTS ix_grades_unit_zoho_id ON grades(unit_zoho_id);
CREATE INDEX IF NOT EXISTS ix_grades_tenant_student_unit ON grades(tenant_id, student_zoho_id, unit_zoho_id);
CREATE INDEX IF NOT EXISTS ix_grades_tenant_zoho ON grades(tenant_id, zoho_id);
