# ============================================
# Database Pre-Upgrade Check Script
# Verifies database state before upgrading to version 2026021501
# ============================================

$dbHost = "195.35.25.188"
$dbUser = "moodle_user"
$dbPass = "BaBa112233@@"
$dbName = "moodle_db"

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "DATABASE PRE-UPGRADE CHECK" -ForegroundColor Cyan
Write-Host "Target Version: 2026021501" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

# Test MySQL connection first
Write-Host "[1/11] Testing MySQL connection..." -ForegroundColor Yellow
$testConnection = mysql -h $dbHost -u $dbUser -p"$dbPass" $dbName -e "SELECT 1;" 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Host "❌ Connection failed! Check credentials." -ForegroundColor Red
    exit 1
}
Write-Host "✓ Connection successful`n" -ForegroundColor Green

# 1. Check current version
Write-Host "[2/11] Checking current plugin version..." -ForegroundColor Yellow
$currentVersion = mysql -h $dbHost -u $dbUser -p"$dbPass" $dbName -N -e "SELECT value FROM mdl_config_plugins WHERE plugin = 'local_moodle_zoho_sync' AND name = 'version';"
Write-Host "Current Version: $currentVersion" -ForegroundColor White
if ($currentVersion -eq "2026020901") {
    Write-Host "✓ Version is correct (2026020901)" -ForegroundColor Green
} else {
    Write-Host "⚠ Unexpected version! Expected: 2026020901" -ForegroundColor Yellow
}
Write-Host ""

# 2. List existing tables
Write-Host "[3/11] Listing existing local_mzi_* tables..." -ForegroundColor Yellow
mysql -h $dbHost -u $dbUser -p"$dbPass" $dbName -t -e "
SELECT TABLE_NAME, TABLE_ROWS 
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = '$dbName' 
  AND TABLE_NAME LIKE 'mdl_local_mzi_%'
ORDER BY TABLE_NAME;"
Write-Host ""

# 3. Check if new tables already exist
Write-Host "[4/11] Checking for new tables (should NOT exist)..." -ForegroundColor Yellow

$studentsExists = mysql -h $dbHost -u $dbUser -p"$dbPass" $dbName -N -e "
SELECT COUNT(*) FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = 'mdl_local_mzi_students';"

$webhookExists = mysql -h $dbHost -u $dbUser -p"$dbPass" $dbName -N -e "
SELECT COUNT(*) FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = 'mdl_local_mzi_webhook_logs';"

$syncExists = mysql -h $dbHost -u $dbUser -p"$dbPass" $dbName -N -e "
SELECT COUNT(*) FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = 'mdl_local_mzi_sync_status';"

if ($studentsExists -eq "0") {
    Write-Host "✓ mdl_local_mzi_students: Not exists (will be created)" -ForegroundColor Green
} else {
    Write-Host "⚠ mdl_local_mzi_students: Already exists!" -ForegroundColor Red
}

if ($webhookExists -eq "0") {
    Write-Host "✓ mdl_local_mzi_webhook_logs: Not exists (will be created)" -ForegroundColor Green
} else {
    Write-Host "⚠ mdl_local_mzi_webhook_logs: Already exists!" -ForegroundColor Red
}

if ($syncExists -eq "0") {
    Write-Host "✓ mdl_local_mzi_sync_status: Not exists (will be created)" -ForegroundColor Green
} else {
    Write-Host "⚠ mdl_local_mzi_sync_status: Already exists!" -ForegroundColor Red
}
Write-Host ""

# 4. Verify grade_queue has workflow_state
Write-Host "[5/11] Verifying grade_queue has workflow_state field..." -ForegroundColor Yellow
$workflowStateExists = mysql -h $dbHost -u $dbUser -p"$dbPass" $dbName -N -e "
SELECT COUNT(*) FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = '$dbName' 
  AND TABLE_NAME = 'mdl_local_mzi_grade_queue' 
  AND COLUMN_NAME = 'workflow_state';"

if ($workflowStateExists -eq "1") {
    Write-Host "✓ workflow_state field exists" -ForegroundColor Green
} else {
    Write-Host "❌ workflow_state field MISSING! Version 2026020901 upgrade may have failed." -ForegroundColor Red
}
Write-Host ""

# 5. Check event_log structure
Write-Host "[6/11] Verifying event_log enhanced fields..." -ForegroundColor Yellow
mysql -h $dbHost -u $dbUser -p"$dbPass" $dbName -t -e "
SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = '$dbName' 
  AND TABLE_NAME = 'mdl_local_mzi_event_log'
  AND COLUMN_NAME IN ('student_name', 'course_name', 'assignment_name', 'grade_name', 'related_id')
ORDER BY ORDINAL_POSITION;"
Write-Host ""

# 6. Check grade_ack table
Write-Host "[7/11] Checking grade_ack table..." -ForegroundColor Yellow
$gradeAckExists = mysql -h $dbHost -u $dbUser -p"$dbPass" $dbName -N -e "
SELECT COUNT(*) FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = 'mdl_local_mzi_grade_ack';"

if ($gradeAckExists -eq "1") {
    Write-Host "✓ grade_ack table exists" -ForegroundColor Green
    $gradeAckCount = mysql -h $dbHost -u $dbUser -p"$dbPass" $dbName -N -e "SELECT COUNT(*) FROM mdl_local_mzi_grade_ack;"
    Write-Host "  Records: $gradeAckCount" -ForegroundColor White
} else {
    Write-Host "⚠ grade_ack table MISSING!" -ForegroundColor Yellow
}
Write-Host ""

# 7. Check foreign keys
Write-Host "[8/11] Checking foreign key constraints..." -ForegroundColor Yellow
mysql -h $dbHost -u $dbUser -p"$dbPass" $dbName -t -e "
SELECT 
    TABLE_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = '$dbName'
  AND TABLE_NAME LIKE 'mdl_local_mzi_%'
  AND REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY TABLE_NAME;"
Write-Host ""

# 8. Count records
Write-Host "[9/11] Counting records in existing tables..." -ForegroundColor Yellow
mysql -h $dbHost -u $dbUser -p"$dbPass" $dbName -t -e "
SELECT 'event_log' as table_name, COUNT(*) as record_count FROM mdl_local_mzi_event_log
UNION ALL SELECT 'sync_history', COUNT(*) FROM mdl_local_mzi_sync_history
UNION ALL SELECT 'config', COUNT(*) FROM mdl_local_mzi_config
UNION ALL SELECT 'btec_templates', COUNT(*) FROM mdl_local_mzi_btec_templates
UNION ALL SELECT 'grade_queue', COUNT(*) FROM mdl_local_mzi_grade_queue
UNION ALL SELECT 'grade_ack', COUNT(*) FROM mdl_local_mzi_grade_ack;"
Write-Host ""

# 9. Check for students with academic emails
Write-Host "[10/11] Checking Moodle users with academic emails..." -ForegroundColor Yellow
$studentCount = mysql -h $dbHost -u $dbUser -p"$dbPass" $dbName -N -e "
SELECT COUNT(*) FROM mdl_user 
WHERE username LIKE '%@abchorizon.com' AND deleted = 0;"
Write-Host "Students with @abchorizon.com email: $studentCount" -ForegroundColor White
Write-Host ""

# 10. Check for old/conflicting tables
Write-Host "[11/11] Checking for old/conflicting table names..." -ForegroundColor Yellow
$oldTables = mysql -h $dbHost -u $dbUser -p"$dbPass" $dbName -N -e "
SELECT COUNT(*) FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = '$dbName' 
  AND (TABLE_NAME LIKE 'mb_zoho_%' OR TABLE_NAME LIKE 'mdl_mb_zoho_%');"

if ($oldTables -eq "0") {
    Write-Host "✓ No old table names found" -ForegroundColor Green
} else {
    Write-Host "⚠ Found $oldTables old tables!" -ForegroundColor Yellow
    mysql -h $dbHost -u $dbUser -p"$dbPass" $dbName -t -e "
    SELECT TABLE_NAME FROM information_schema.TABLES 
    WHERE TABLE_SCHEMA = '$dbName' 
      AND (TABLE_NAME LIKE 'mb_zoho_%' OR TABLE_NAME LIKE 'mdl_mb_zoho_%');"
}
Write-Host ""

# Summary
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "SUMMARY" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

$canUpgrade = $true

if ($currentVersion -ne "2026020901") {
    Write-Host "❌ Wrong version ($currentVersion)" -ForegroundColor Red
    $canUpgrade = $false
}

if ($studentsExists -ne "0" -or $webhookExists -ne "0" -or $syncExists -ne "0") {
    Write-Host "❌ New tables already exist" -ForegroundColor Red
    $canUpgrade = $false
}

if ($workflowStateExists -ne "1") {
    Write-Host "❌ Missing workflow_state field" -ForegroundColor Red
    $canUpgrade = $false
}

if ($canUpgrade) {
    Write-Host "`n✅ ALL CHECKS PASSED!" -ForegroundColor Green
    Write-Host "✅ Safe to upgrade to version 2026021501" -ForegroundColor Green
    Write-Host "`nNext steps:" -ForegroundColor Yellow
    Write-Host "1. Upload updated files to server" -ForegroundColor White
    Write-Host "2. Go to: Site Administration → Notifications" -ForegroundColor White
    Write-Host "3. Moodle will run upgrade automatically" -ForegroundColor White
} else {
    Write-Host "`n⚠ ISSUES FOUND! Review output above." -ForegroundColor Red
    Write-Host "Fix issues before upgrading." -ForegroundColor Red
}

Write-Host "`n========================================`n" -ForegroundColor Cyan
