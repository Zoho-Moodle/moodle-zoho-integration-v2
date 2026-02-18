# ============================================
# RESTORE CRITICAL TABLES SCRIPT
# Restores grade_queue, btec_templates, grade_ack
# ============================================

$dbHost = "195.35.25.188"
$dbUser = "moodle_user"
$dbPass = "BaBa112233@@"
$dbName = "moodle_db"

Write-Host "`n========================================" -ForegroundColor Red
Write-Host "RESTORING DELETED CRITICAL TABLES" -ForegroundColor Red
Write-Host "========================================`n" -ForegroundColor Red

# Test connection
Write-Host "[1/4] Testing database connection..." -ForegroundColor Yellow
$testConn = mysql -h $dbHost -u $dbUser -p"$dbPass" $dbName -e "SELECT 1;" 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Host "❌ Connection failed!" -ForegroundColor Red
    exit 1
}
Write-Host "✓ Connected successfully`n" -ForegroundColor Green

# Check what tables are missing
Write-Host "[2/4] Checking missing tables..." -ForegroundColor Yellow

$gradeQueueExists = mysql -h $dbHost -u $dbUser -p"$dbPass" $dbName -N -e "
SELECT COUNT(*) FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = 'mdl_local_mzi_grade_queue';"

$btecExists = mysql -h $dbHost -u $dbUser -p"$dbPass" $dbName -N -e "
SELECT COUNT(*) FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = 'mdl_local_mzi_btec_templates';"

$gradeAckExists = mysql -h $dbHost -u $dbUser -p"$dbPass" $dbName -N -e "
SELECT COUNT(*) FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = 'mdl_local_mzi_grade_ack';"

if ($gradeQueueExists -eq "0") {
    Write-Host "❌ mdl_local_mzi_grade_queue: MISSING" -ForegroundColor Red
} else {
    Write-Host "✓ mdl_local_mzi_grade_queue: EXISTS" -ForegroundColor Green
}

if ($btecExists -eq "0") {
    Write-Host "❌ mdl_local_mzi_btec_templates: MISSING" -ForegroundColor Red
} else {
    Write-Host "✓ mdl_local_mzi_btec_templates: EXISTS" -ForegroundColor Green
}

if ($gradeAckExists -eq "0") {
    Write-Host "❌ mdl_local_mzi_grade_ack: MISSING" -ForegroundColor Red
} else {
    Write-Host "✓ mdl_local_mzi_grade_ack: EXISTS" -ForegroundColor Green
}

Write-Host ""

# Ask for confirmation
if ($gradeQueueExists -eq "1" -and $btecExists -eq "1" -and $gradeAckExists -eq "1") {
    Write-Host "✅ All tables already exist! No restoration needed." -ForegroundColor Green
    Write-Host "========================================`n" -ForegroundColor Cyan
    exit 0
}

Write-Host "⚠️  WARNING: This will recreate missing tables." -ForegroundColor Yellow
$confirmation = Read-Host "Type 'RESTORE' to continue"

if ($confirmation -ne "RESTORE") {
    Write-Host "`n❌ Restoration cancelled." -ForegroundColor Red
    exit 1
}

# Execute restoration
Write-Host "`n[3/4] Restoring tables..." -ForegroundColor Yellow

$sqlScript = @"
SET FOREIGN_KEY_CHECKS = 0;

-- 1. RESTORE: local_mzi_grade_queue
CREATE TABLE IF NOT EXISTS mdl_local_mzi_grade_queue (
    id BIGINT(10) NOT NULL AUTO_INCREMENT,
    grade_id BIGINT(10) NOT NULL COMMENT 'assign_grades.id (0 for F grades)',
    student_id BIGINT(10) NOT NULL COMMENT 'user.id',
    assignment_id BIGINT(10) NOT NULL COMMENT 'assign.id',
    course_id BIGINT(10) NOT NULL COMMENT 'course.id',
    zoho_record_id VARCHAR(50) DEFAULT NULL COMMENT 'Zoho CRM record ID',
    composite_key VARCHAR(255) NOT NULL COMMENT 'studentid_courseid_assignmentid',
    workflow_state VARCHAR(50) DEFAULT NULL COMMENT 'Assignment workflow state from assign_user_flags',
    status VARCHAR(20) NOT NULL DEFAULT 'BASIC_SENT' COMMENT 'BASIC_SENT, ENRICHED, RR_UPDATED, F_CREATED, FAILED',
    basic_sent_at BIGINT(10) DEFAULT NULL COMMENT 'When observer sent basic data',
    enriched_at BIGINT(10) DEFAULT NULL COMMENT 'When task added learning outcomes',
    failed_at BIGINT(10) DEFAULT NULL COMMENT 'When enrichment failed',
    needs_enrichment TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 if needs learning outcomes',
    needs_rr_check TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 if grade is R and needs RR check',
    error_message TEXT DEFAULT NULL COMMENT 'Last error message',
    retry_count SMALLINT(2) NOT NULL DEFAULT 0 COMMENT 'Number of enrichment retry attempts',
    timecreated BIGINT(10) NOT NULL COMMENT 'Unix timestamp',
    timemodified BIGINT(10) NOT NULL COMMENT 'Unix timestamp',
    PRIMARY KEY (id),
    UNIQUE KEY mdl_locagrad_com_uix (composite_key),
    KEY mdl_locagrad_sta_ix (status),
    KEY mdl_locagrad_nee_ix (needs_enrichment),
    KEY mdl_locagrad_nee2_ix (needs_rr_check),
    KEY mdl_locagrad_gra_ix (grade_id),
    KEY mdl_locagrad_stuass_ix (student_id, assignment_id),
    KEY mdl_locagrad_tim_ix (timecreated)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Queue for grade enrichment and RR/F detection';

-- 2. RESTORE: local_mzi_btec_templates
CREATE TABLE IF NOT EXISTS mdl_local_mzi_btec_templates (
    id BIGINT(10) NOT NULL AUTO_INCREMENT,
    definition_id BIGINT(10) NOT NULL COMMENT 'Moodle grading_definitions.id',
    zoho_unit_id VARCHAR(50) NOT NULL COMMENT 'Zoho BTEC record ID',
    unit_name VARCHAR(255) NOT NULL COMMENT 'Unit/template name',
    synced_at BIGINT(10) NOT NULL COMMENT 'Unix timestamp of last sync',
    PRIMARY KEY (id),
    UNIQUE KEY mdl_locabtec_zoh_uix (zoho_unit_id),
    KEY mdl_locabtec_def_ix (definition_id),
    KEY mdl_locabtec_syn_ix (synced_at),
    CONSTRAINT mdl_locabtec_def_fk FOREIGN KEY (definition_id) 
        REFERENCES mdl_grading_definitions (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Tracks synced BTEC grading templates from Zoho';

-- 3. RESTORE: local_mzi_grade_ack
CREATE TABLE IF NOT EXISTS mdl_local_mzi_grade_ack (
    id BIGINT(10) NOT NULL AUTO_INCREMENT,
    userid BIGINT(10) NOT NULL COMMENT 'Moodle user.id',
    assignmentid BIGINT(10) NOT NULL COMMENT 'Assignment ID',
    courseid BIGINT(10) NOT NULL COMMENT 'Course ID',
    acknowledged_at BIGINT(10) NOT NULL COMMENT 'Unix timestamp',
    ip_address VARCHAR(45) DEFAULT NULL COMMENT 'Client IP address',
    PRIMARY KEY (id),
    UNIQUE KEY mdl_locagrad_useass_uix (userid, assignmentid),
    KEY mdl_locagrad_use_ix (userid),
    KEY mdl_locagrad_cou_ix (courseid),
    CONSTRAINT mdl_locagrad_use_fk FOREIGN KEY (userid) 
        REFERENCES mdl_user (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Student grade receipt acknowledgement tracking';

SET FOREIGN_KEY_CHECKS = 1;
"@

# Write SQL to temp file
$tempSqlFile = "restore_tables_temp.sql"
$sqlScript | Out-File -FilePath $tempSqlFile -Encoding UTF8

# Execute SQL
mysql -h $dbHost -u $dbUser -p"$dbPass" $dbName < $tempSqlFile 2>&1

if ($LASTEXITCODE -eq 0) {
    Write-Host "✓ Tables restored successfully`n" -ForegroundColor Green
} else {
    Write-Host "❌ Error restoring tables!`n" -ForegroundColor Red
    Remove-Item $tempSqlFile -ErrorAction SilentlyContinue
    exit 1
}

# Clean up temp file
Remove-Item $tempSqlFile -ErrorAction SilentlyContinue

# Verify restoration
Write-Host "[4/4] Verifying restored tables..." -ForegroundColor Yellow

mysql -h $dbHost -u $dbUser -p"$dbPass" $dbName -t -e "
SELECT TABLE_NAME, TABLE_ROWS, CREATE_TIME
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = '$dbName' 
  AND TABLE_NAME IN (
    'mdl_local_mzi_grade_queue',
    'mdl_local_mzi_btec_templates',
    'mdl_local_mzi_grade_ack'
  )
ORDER BY TABLE_NAME;"

Write-Host ""

# Final check
$gradeQueueExists = mysql -h $dbHost -u $dbUser -p"$dbPass" $dbName -N -e "
SELECT COUNT(*) FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = 'mdl_local_mzi_grade_queue';"

$btecExists = mysql -h $dbHost -u $dbUser -p"$dbPass" $dbName -N -e "
SELECT COUNT(*) FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = 'mdl_local_mzi_btec_templates';"

$gradeAckExists = mysql -h $dbHost -u $dbUser -p"$dbPass" $dbName -N -e "
SELECT COUNT(*) FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = 'mdl_local_mzi_grade_ack';"

if ($gradeQueueExists -eq "1" -and $btecExists -eq "1" -and $gradeAckExists -eq "1") {
    Write-Host "========================================" -ForegroundColor Green
    Write-Host "✅ SUCCESS: All tables restored!" -ForegroundColor Green
    Write-Host "========================================" -ForegroundColor Green
    Write-Host ""
    Write-Host "Restored tables:" -ForegroundColor Cyan
    Write-Host "  1. mdl_local_mzi_grade_queue" -ForegroundColor White
    Write-Host "  2. mdl_local_mzi_btec_templates" -ForegroundColor White
    Write-Host "  3. mdl_local_mzi_grade_ack" -ForegroundColor White
    Write-Host ""
} else {
    Write-Host "========================================" -ForegroundColor Red
    Write-Host "⚠️  PARTIAL SUCCESS: Some tables missing!" -ForegroundColor Yellow
    Write-Host "========================================" -ForegroundColor Red
}

Write-Host ""
