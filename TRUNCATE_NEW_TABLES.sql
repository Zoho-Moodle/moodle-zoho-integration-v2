-- ════════════════════════════════════════════════════════════════
-- SQL Script to TRUNCATE (clear data) Phase 4 Tables
-- Run this to keep table structure but remove all data
-- ════════════════════════════════════════════════════════════════

SET FOREIGN_KEY_CHECKS = 0;

-- Truncate Zoho data mirror tables
TRUNCATE TABLE mdl_local_mzi_students;
TRUNCATE TABLE mdl_local_mzi_registrations;
TRUNCATE TABLE mdl_local_mzi_payments;
TRUNCATE TABLE mdl_local_mzi_enrollments;
TRUNCATE TABLE mdl_local_mzi_requests;

-- Truncate monitoring tables
TRUNCATE TABLE mdl_local_mzi_webhook_logs;
TRUNCATE TABLE mdl_local_mzi_sync_status;
TRUNCATE TABLE mdl_local_mzi_student_cache;
TRUNCATE TABLE mdl_local_mzi_admin_notifications;
TRUNCATE TABLE mdl_local_mzi_backend_health;

-- Truncate grade tables
TRUNCATE TABLE mdl_local_mzi_grade_queue;
TRUNCATE TABLE mdl_local_mzi_grade_ack;

SET FOREIGN_KEY_CHECKS = 1;

SELECT 'All tables truncated successfully!' AS status;
