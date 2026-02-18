# ════════════════════════════════════════════════════════════════
# PowerShell Command to DROP Phase 4 Tables
# ════════════════════════════════════════════════════════════════

# Database credentials
$DB_USER = "moodle_user"
$DB_PASS = "BaBa112233@@"
$DB_NAME = "moodle_db"

# SQL DROP statements
$DROP_SQL = @"
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS mdl_local_mzi_students;
DROP TABLE IF EXISTS mdl_local_mzi_registrations;
DROP TABLE IF EXISTS mdl_local_mzi_payments;
DROP TABLE IF EXISTS mdl_local_mzi_enrollments;
DROP TABLE IF EXISTS mdl_local_mzi_requests;
DROP TABLE IF EXISTS mdl_local_mzi_webhook_logs;
DROP TABLE IF EXISTS mdl_local_mzi_sync_status;
DROP TABLE IF EXISTS mdl_local_mzi_student_cache;
DROP TABLE IF EXISTS mdl_local_mzi_admin_notifications;
DROP TABLE IF EXISTS mdl_local_mzi_backend_health;
DROP TABLE IF EXISTS mdl_local_mzi_grade_queue;
DROP TABLE IF EXISTS mdl_local_mzi_grade_ack;
DROP TABLE IF EXISTS mdl_local_mzi_btec_templates;

SET FOREIGN_KEY_CHECKS = 1;

SELECT 'Tables dropped successfully!' AS status;
"@

Write-Host "════════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "  Dropping Phase 4 Tables from Moodle Database" -ForegroundColor Cyan
Write-Host "════════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host ""

# Execute SQL
Write-Host "Executing DROP statements..." -ForegroundColor Yellow

# Method 1: Using mysql command (if available)
try {
    $DROP_SQL | mysql -u $DB_USER -p"$DB_PASS" $DB_NAME 2>&1
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host "✓ Tables dropped successfully!" -ForegroundColor Green
    } else {
        Write-Host "✗ Failed to drop tables" -ForegroundColor Red
        Write-Host "Error code: $LASTEXITCODE" -ForegroundColor Red
    }
} catch {
    Write-Host "✗ MySQL command not found or error occurred" -ForegroundColor Red
    Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host ""
    Write-Host "Alternative: Run this SQL in phpMyAdmin or MySQL Workbench:" -ForegroundColor Yellow
    Write-Host $DROP_SQL -ForegroundColor Gray
}

Write-Host ""
Write-Host "════════════════════════════════════════════════════════════" -ForegroundColor Cyan
