-- ============================================
-- Final Verification: Check Complete Status
-- ============================================

-- 1. Check plugin version
SELECT 'CURRENT VERSION:' as info, value as version
FROM mdl_config_plugins 
WHERE plugin = 'local_moodle_zoho_sync' AND name = 'version';

-- 2. List ALL local_mzi tables
SELECT 
    TABLE_NAME, 
    TABLE_ROWS, 
    ROUND(DATA_LENGTH/1024, 2) as 'Size_KB',
    CREATE_TIME
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'moodle_db' 
  AND TABLE_NAME LIKE 'mdl_local_mzi_%'
ORDER BY TABLE_NAME;

-- 3. Check grade_queue structure (verify workflow_state exists)
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'moodle_db' 
  AND TABLE_NAME = 'mdl_local_mzi_grade_queue'
  AND COLUMN_NAME IN ('workflow_state', 'composite_key', 'status')
ORDER BY ORDINAL_POSITION;

-- 4. Check existing data in grade_queue
SELECT 
    COUNT(*) as total_records,
    SUM(CASE WHEN status = 'BASIC_SENT' THEN 1 ELSE 0 END) as basic_sent,
    SUM(CASE WHEN status = 'ENRICHED' THEN 1 ELSE 0 END) as enriched,
    SUM(CASE WHEN needs_enrichment = 1 THEN 1 ELSE 0 END) as needs_enrichment
FROM mdl_local_mzi_grade_queue;

-- 5. Summary
SELECT 'READY FOR STUDENT DASHBOARD!' as status;
