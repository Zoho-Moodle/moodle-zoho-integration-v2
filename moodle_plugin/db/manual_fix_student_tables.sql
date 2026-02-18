-- Manual fix for missing Student Dashboard tables
-- Run this directly on PostgreSQL database if upgrade.php fails

-- 1. local_mzi_students table
CREATE TABLE IF NOT EXISTS mdl_local_mzi_students (
    id BIGSERIAL PRIMARY KEY,
    moodle_user_id BIGINT NOT NULL,
    zoho_student_id VARCHAR(20) NOT NULL,
    student_id VARCHAR(120),
    registration_number VARCHAR(255),
    first_name VARCHAR(255),
    last_name VARCHAR(255),
    email VARCHAR(100),
    academic_email VARCHAR(100),
    phone_number VARCHAR(30),
    date_of_birth VARCHAR(20),
    nationality VARCHAR(120),
    address TEXT,
    city VARCHAR(255),
    status VARCHAR(120),
    photo_url VARCHAR(512),
    academic_program VARCHAR(255),
    registration_date VARCHAR(20),
    study_language VARCHAR(50),
    created_at BIGINT NOT NULL DEFAULT 0,
    updated_at BIGINT NOT NULL DEFAULT 0,
    synced_at BIGINT NOT NULL DEFAULT 0,
    zoho_created_time VARCHAR(30),
    zoho_modified_time VARCHAR(30),
    
    CONSTRAINT mdl_local_mzi_students_moodle_user_fk FOREIGN KEY (moodle_user_id) REFERENCES mdl_user(id)
);

CREATE UNIQUE INDEX IF NOT EXISTS mdl_local_mzi_students_zoho_student_id_idx ON mdl_local_mzi_students(zoho_student_id);
CREATE INDEX IF NOT EXISTS mdl_local_mzi_students_academic_email_idx ON mdl_local_mzi_students(academic_email);
CREATE INDEX IF NOT EXISTS mdl_local_mzi_students_status_idx ON mdl_local_mzi_students(status);
CREATE INDEX IF NOT EXISTS mdl_local_mzi_students_synced_at_idx ON mdl_local_mzi_students(synced_at);

-- 2. local_mzi_webhook_logs table
CREATE TABLE IF NOT EXISTS mdl_local_mzi_webhook_logs (
    id BIGSERIAL PRIMARY KEY,
    module VARCHAR(100) NOT NULL,
    zoho_record_id VARCHAR(20),
    operation VARCHAR(20) NOT NULL,
    payload TEXT,
    processed SMALLINT NOT NULL DEFAULT 0,
    error_message TEXT,
    created_at BIGINT NOT NULL DEFAULT 0,
    processed_at BIGINT
);

CREATE INDEX IF NOT EXISTS mdl_local_mzi_webhook_logs_module_idx ON mdl_local_mzi_webhook_logs(module);
CREATE INDEX IF NOT EXISTS mdl_local_mzi_webhook_logs_zoho_record_id_idx ON mdl_local_mzi_webhook_logs(zoho_record_id);
CREATE INDEX IF NOT EXISTS mdl_local_mzi_webhook_logs_processed_idx ON mdl_local_mzi_webhook_logs(processed);
CREATE INDEX IF NOT EXISTS mdl_local_mzi_webhook_logs_created_at_idx ON mdl_local_mzi_webhook_logs(created_at);

-- 3. local_mzi_sync_status table
CREATE TABLE IF NOT EXISTS mdl_local_mzi_sync_status (
    id BIGSERIAL PRIMARY KEY,
    module VARCHAR(100) NOT NULL,
    last_full_sync BIGINT,
    last_incremental_sync BIGINT,
    total_records BIGINT NOT NULL DEFAULT 0,
    sync_status VARCHAR(20) NOT NULL DEFAULT 'pending',
    error_message TEXT,
    updated_at BIGINT NOT NULL DEFAULT 0
);

CREATE UNIQUE INDEX IF NOT EXISTS mdl_local_mzi_sync_status_module_idx ON mdl_local_mzi_sync_status(module);
CREATE INDEX IF NOT EXISTS mdl_local_mzi_sync_status_sync_status_idx ON mdl_local_mzi_sync_status(sync_status);

-- Note: The other 7 tables (registrations, installments, payments, classes, enrollments, grades, requests)
-- should already exist from version 2026021601. If they don't, run full upgrade again.
