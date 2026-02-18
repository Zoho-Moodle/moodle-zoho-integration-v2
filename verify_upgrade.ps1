# ============================================
# Post-Upload Verification & Upgrade
# ============================================

$dbHost = "195.35.25.188"
$dbUser = "moodle_user"
$dbPass = "BaBa112233@@"
$dbName = "moodle_db"

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "POST-UPLOAD VERIFICATION & UPGRADE" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

# 1. Check database version before upgrade
Write-Host "[1/5] Checking current database version..." -ForegroundColor Yellow
$currentVersion = mysql -h $dbHost -u $dbUser -p"$dbPass" $dbName -N -e "
SELECT value FROM mdl_config_plugins 
WHERE plugin = 'local_moodle_zoho_sync' AND name = 'version';"

Write-Host "Current Version: $currentVersion" -ForegroundColor White
if ($currentVersion -eq "2026020901") {
    Write-Host "✓ Ready for upgrade to 2026021501`n" -ForegroundColor Green
} else {
    Write-Host "⚠ Unexpected version!`n" -ForegroundColor Yellow
}

# 2. Check existing tables before upgrade
Write-Host "[2/5] Checking existing tables..." -ForegroundColor Yellow
mysql -h $dbHost -u $dbUser -p"$dbPass" $dbName -t -e "
SELECT TABLE_NAME, TABLE_ROWS
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = '$dbName' 
  AND TABLE_NAME LIKE 'mdl_local_mzi_%'
ORDER BY TABLE_NAME;"
Write-Host ""

# 3. Check if new tables already exist
Write-Host "[3/5] Checking if new tables exist..." -ForegroundColor Yellow

$studentsExists = mysql -h $dbHost -u $dbUser -p"$dbPass" $dbName -N -e "
SELECT COUNT(*) FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = 'mdl_local_mzi_students';"

$webhookLogsExists = mysql -h $dbHost -u $dbUser -p"$dbPass" $dbName -N -e "
SELECT COUNT(*) FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = 'mdl_local_mzi_webhook_logs';"

$syncStatusExists = mysql -h $dbHost -u $dbUser -p"$dbPass" $dbName -N -e "
SELECT COUNT(*) FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = 'mdl_local_mzi_sync_status';"

if ($studentsExists -eq "0") {
    Write-Host "✓ mdl_local_mzi_students: Not exists (will be created)" -ForegroundColor Green
} else {
    Write-Host "⚠ mdl_local_mzi_students: Already exists" -ForegroundColor Yellow
}

if ($webhookLogsExists -eq "0") {
    Write-Host "✓ mdl_local_mzi_webhook_logs: Not exists (will be created)" -ForegroundColor Green
} else {
    Write-Host "⚠ mdl_local_mzi_webhook_logs: Already exists" -ForegroundColor Yellow
}

if ($syncStatusExists -eq "0") {
    Write-Host "✓ mdl_local_mzi_sync_status: Not exists (will be created)" -ForegroundColor Green
} else {
    Write-Host "⚠ mdl_local_mzi_sync_status: Already exists" -ForegroundColor Yellow
}

Write-Host ""

# 4. Show upgrade commands
Write-Host "[4/5] Upgrade Commands:" -ForegroundColor Yellow
Write-Host ""
Write-Host "Option A: Web Interface (Easiest)" -ForegroundColor Cyan
Write-Host "  1. Go to: https://lms.abchorizon.com" -ForegroundColor White
Write-Host "  2. Login as admin" -ForegroundColor White
Write-Host "  3. Moodle will show: 'Database upgrade required'" -ForegroundColor White
Write-Host "  4. Click: 'Upgrade Moodle database now'" -ForegroundColor White
Write-Host "  5. Wait for completion" -ForegroundColor White
Write-Host ""

Write-Host "Option B: CLI (if you have SSH access)" -ForegroundColor Cyan
Write-Host "  SSH Command:" -ForegroundColor Gray
Write-Host "  ssh root@195.35.25.188`n" -ForegroundColor White
Write-Host "  Upgrade Command:" -ForegroundColor Gray
Write-Host "  sudo -u www-data php /var/www/html/moodle/admin/cli/upgrade.php`n" -ForegroundColor White
Write-Host "  Clear Cache:" -ForegroundColor Gray
Write-Host "  sudo -u www-data php /var/www/html/moodle/admin/cli/purge_caches.php`n" -ForegroundColor White

Write-Host "Option C: Notifications Page" -ForegroundColor Cyan
Write-Host "  Go to: Site Administration → Notifications`n" -ForegroundColor White

# 5. Wait for user confirmation
Write-Host "[5/5] After running upgrade, press any key to verify..." -ForegroundColor Yellow
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")

# Verification after upgrade
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "POST-UPGRADE VERIFICATION" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

# Check new version
Write-Host "[1/3] Checking new version..." -ForegroundColor Yellow
$newVersion = mysql -h $dbHost -u $dbUser -p"$dbPass" $dbName -N -e "
SELECT value FROM mdl_config_plugins 
WHERE plugin = 'local_moodle_zoho_sync' AND name = 'version';"

if ($newVersion -eq "2026021501") {
    Write-Host "✓ Version upgraded successfully: $newVersion`n" -ForegroundColor Green
} else {
    Write-Host "❌ Version not updated! Still: $newVersion`n" -ForegroundColor Red
}

# Check new tables
Write-Host "[2/3] Verifying new tables created..." -ForegroundColor Yellow
mysql -h $dbHost -u $dbUser -p"$dbPass" $dbName -t -e "
SELECT TABLE_NAME, TABLE_ROWS, CREATE_TIME
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = '$dbName' 
  AND TABLE_NAME IN (
    'mdl_local_mzi_students',
    'mdl_local_mzi_webhook_logs',
    'mdl_local_mzi_sync_status'
  )
ORDER BY TABLE_NAME;"
Write-Host ""

# Check students table structure
Write-Host "[3/3] Verifying students table structure..." -ForegroundColor Yellow
mysql -h $dbHost -u $dbUser -p"$dbPass" $dbName -e "
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = '$dbName' 
  AND TABLE_NAME = 'mdl_local_mzi_students'
ORDER BY ORDINAL_POSITION
LIMIT 10;" -t
Write-Host ""

# Final summary
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "SUMMARY" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

$finalCheck = mysql -h $dbHost -u $dbUser -p"$dbPass" $dbName -N -e "
SELECT COUNT(*) FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = '$dbName' 
  AND TABLE_NAME IN ('mdl_local_mzi_students', 'mdl_local_mzi_webhook_logs', 'mdl_local_mzi_sync_status');"

if ($finalCheck -eq "3" -and $newVersion -eq "2026021501") {
    Write-Host "✅ UPGRADE SUCCESSFUL!" -ForegroundColor Green
    Write-Host "✅ Version: 2026021501" -ForegroundColor Green
    Write-Host "✅ New tables: 3/3 created" -ForegroundColor Green
    Write-Host ""
    Write-Host "🎉 Ready for Student Dashboard development!" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Next steps:" -ForegroundColor Yellow
    Write-Host "  1. Build Backend Webhook Handler" -ForegroundColor White
    Write-Host "  2. Build Bulk Sync Script" -ForegroundColor White
    Write-Host "  3. Build Frontend Dashboard" -ForegroundColor White
} else {
    Write-Host "⚠️  UPGRADE INCOMPLETE" -ForegroundColor Yellow
    Write-Host "Tables created: $finalCheck/3" -ForegroundColor White
    Write-Host "Version: $newVersion" -ForegroundColor White
    Write-Host ""
    Write-Host "Check Moodle admin dashboard for errors." -ForegroundColor Yellow
}

Write-Host "========================================`n" -ForegroundColor Cyan
