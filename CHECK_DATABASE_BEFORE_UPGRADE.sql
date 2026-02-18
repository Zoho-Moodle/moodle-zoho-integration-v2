-- ============================================
-- Database Check Before Upgrade to 2026021501
-- Student Dashboard Tables Verification
-- ============================================

-- 1. Check current plugin version
-- ============================================
SELECT name, value 
FROM mdl_config_plugins 
WHERE plugin = 'local_moodle_zoho_sync' AND name = 'version';
-- Expected: 2026020901 (current) → Will upgrade to 2026021501


-- 2. List all existing local_mzi_* tables
-- ============================================
SELECT TABLE_NAME, TABLE_ROWS, CREATE_TIME 
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'moodle_db' 
  AND TABLE_NAME LIKE 'mdl_local_mzi_%'
ORDER BY TABLE_NAME;
-- Expected tables:
-- ✓ mdl_local_mzi_event_log
-- ✓ mdl_local_mzi_sync_history
-- ✓ mdl_local_mzi_config
-- ✓ mdl_local_mzi_btec_templates
-- ✓ mdl_local_mzi_grade_queue
-- ✓ mdl_local_mzi_grade_ack
-- ❌ mdl_local_mzi_students (NEW - will be created)
-- ❌ mdl_local_mzi_webhook_logs (NEW - will be created)
-- ❌ mdl_local_mzi_sync_status (NEW - will be created)


-- 3. Check if new tables already exist (should be empty)
-- ============================================

-- Check students table
SELECT COUNT(*) as students_table_exists
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'moodle_db' 
  AND TABLE_NAME = 'mdl_local_mzi_students';
-- Expected: 0 (table doesn't exist)

-- Check webhook_logs table
SELECT COUNT(*) as webhook_logs_table_exists
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'moodle_db' 
  AND TABLE_NAME = 'mdl_local_mzi_webhook_logs';
-- Expected: 0 (table doesn't exist)

-- Check sync_status table
SELECT COUNT(*) as sync_status_table_exists
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'moodle_db' 
  AND TABLE_NAME = 'mdl_local_mzi_sync_status';
-- Expected: 0 (table doesn't exist)


-- 4. Verify grade_queue table structure (should have workflow_state)
-- ============================================
DESCRIBE mdl_local_mzi_grade_queue;
-- Should see workflow_state column (added in 2026020901)


-- 5. Check event_log table structure
-- ============================================
SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE, COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'moodle_db' 
  AND TABLE_NAME = 'mdl_local_mzi_event_log'
ORDER BY ORDINAL_POSITION;
-- Verify all enhanced fields exist


-- 6. Check grade_ack table (used by student dashboard grades)
-- ============================================
SELECT COUNT(*) as grade_ack_exists
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'moodle_db' 
  AND TABLE_NAME = 'mdl_local_mzi_grade_ack';
-- Expected: 1 (should exist from previous versions)

DESCRIBE mdl_local_mzi_grade_ack;


-- 7. Verify foreign key constraints on existing tables
-- ============================================
SELECT 
    TABLE_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = 'moodle_db'
  AND TABLE_NAME LIKE 'mdl_local_mzi_%'
  AND REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY TABLE_NAME;


-- 8. Check indexes on grade_queue table
-- ============================================
SHOW INDEXES FROM mdl_local_mzi_grade_queue;
-- Should see indexes for:
-- - composite_key (UNIQUE)
-- - status, needs_enrichment, needs_rr_check
-- - student_id + assignment_id


-- 9. Count records in existing tables
-- ============================================
SELECT 
    'event_log' as table_name, 
    COUNT(*) as record_count 
FROM mdl_local_mzi_event_log
UNION ALL
SELECT 
    'sync_history', 
    COUNT(*) 
FROM mdl_local_mzi_sync_history
UNION ALL
SELECT 
    'config', 
    COUNT(*) 
FROM mdl_local_mzi_config
UNION ALL
SELECT 
    'btec_templates', 
    COUNT(*) 
FROM mdl_local_mzi_btec_templates
UNION ALL
SELECT 
    'grade_queue', 
    COUNT(*) 
FROM mdl_local_mzi_grade_queue
UNION ALL
SELECT 
    'grade_ack', 
    COUNT(*) 
FROM mdl_local_mzi_grade_ack;


-- 10. Check Moodle users table for academic emails
-- ============================================
-- Verify we can match students by username (academic_email)
SELECT 
    id as moodle_user_id,
    username,
    email,
    firstname,
    lastname,
    deleted
FROM mdl_user 
WHERE username LIKE '%@abchorizon.com'
  AND deleted = 0
LIMIT 5;
-- These are potential students to sync


-- 11. Check if any old/conflicting tables exist
-- ============================================
SELECT TABLE_NAME 
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'moodle_db' 
  AND (
    TABLE_NAME LIKE 'mb_zoho_%' OR
    TABLE_NAME LIKE 'mdl_mb_zoho_%'
  );
-- Should be empty (old tables renamed in 2026020101)


-- ============================================
-- SAFETY CHECK SUMMARY
-- ============================================
-- Run all queries above and verify:
-- 
-- ✅ Current version = 2026020901
-- ✅ 6 existing local_mzi tables present
-- ✅ 0 new tables exist (students, webhook_logs, sync_status)
-- ✅ grade_queue has workflow_state field
-- ✅ No conflicting/old table names
-- ✅ Foreign keys properly set
-- 
-- If ALL checks pass → Safe to upgrade!
-- ============================================
