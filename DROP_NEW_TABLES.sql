-- ════════════════════════════════════════════════════════════════
-- SQL Script to DROP all Phase 4 Webhook Architecture Tables
-- Run this in Moodle database to clean up after checkpoint restore
-- ════════════════════════════════════════════════════════════════

-- WARNING: This will DELETE ALL DATA in these tables!
-- Only run if data is test/development data!

SET FOREIGN_KEY_CHECKS = 0;

-- Drop Zoho data mirror tables (Phase 4 - Webhook-driven architecture)
DROP TABLE IF EXISTS mdl_local_mzi_students;
DROP TABLE IF EXISTS mdl_local_mzi_registrations;
DROP TABLE IF EXISTS mdl_local_mzi_payments;
DROP TABLE IF EXISTS mdl_local_mzi_enrollments;
DROP TABLE IF EXISTS mdl_local_mzi_requests;

-- Drop monitoring tables
DROP TABLE IF EXISTS mdl_local_mzi_webhook_logs;
DROP TABLE IF EXISTS mdl_local_mzi_sync_status;
DROP TABLE IF EXISTS mdl_local_mzi_student_cache;
DROP TABLE IF EXISTS mdl_local_mzi_admin_notifications;
DROP TABLE IF EXISTS mdl_local_mzi_backend_health;

-- Drop grade-related tables
DROP TABLE IF EXISTS mdl_local_mzi_grade_queue;
DROP TABLE IF EXISTS mdl_local_mzi_grade_ack;

-- Drop BTEC templates table
DROP TABLE IF EXISTS mdl_local_mzi_btec_templates;

-- Keep core tables (existed before Phase 4)
-- mdl_local_mzi_event_log - KEEP (exists since Phase 1)
-- mdl_local_mzi_sync_history - KEEP
-- mdl_local_mzi_config - KEEP

SET FOREIGN_KEY_CHECKS = 1;

-- ════════════════════════════════════════════════════════════════
-- After running this script:
-- 1. Update version.php to match current codebase version
-- 2. Run: php admin/cli/uninstall_plugins.php --plugins=local_moodle_zoho_sync --run
-- 3. Then reinstall: Navigate to Site Administration
-- ════════════════════════════════════════════════════════════════

SELECT 'Tables dropped successfully!' AS status;
