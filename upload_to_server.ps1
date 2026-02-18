# ============================================
# Upload Plugin Files to Server
# Student Dashboard Upgrade - Version 2026021501
# ============================================

param(
    [string]$ServerHost = "195.35.25.188",
    [string]$ServerUser = "root",
    [switch]$DryRun = $false
)

$localBasePath = "c:\Users\MohyeddineFarhat\Documents\GitHub\moodle-zoho-integration-v2\moodle_plugin"
$serverBasePath = "/var/www/html/moodle/local/moodle_zoho_sync"

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "UPLOAD PLUGIN FILES TO SERVER" -ForegroundColor Cyan
Write-Host "Version: 2026021501 → 4.0.0" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

# Check if running in dry-run mode
if ($DryRun) {
    Write-Host "🔍 DRY RUN MODE - No files will be uploaded`n" -ForegroundColor Yellow
}

# Files to upload
$filesToUpload = @(
    @{
        Local = "$localBasePath\version.php"
        Remote = "$serverBasePath/version.php"
        Description = "Plugin version file (2026021501)"
    },
    @{
        Local = "$localBasePath\db\upgrade.php"
        Remote = "$serverBasePath/db/upgrade.php"
        Description = "Database upgrade script"
    },
    @{
        Local = "$localBasePath\db\install.xml"
        Remote = "$serverBasePath/db/install.xml"
        Description = "Database schema definition"
    }
)

# Verify local files exist
Write-Host "[1/4] Verifying local files..." -ForegroundColor Yellow
$allFilesExist = $true

foreach ($file in $filesToUpload) {
    if (Test-Path $file.Local) {
        $size = (Get-Item $file.Local).Length
        Write-Host "  ✓ $($file.Description)" -ForegroundColor Green
        Write-Host "    Path: $($file.Local)" -ForegroundColor Gray
        Write-Host "    Size: $size bytes`n" -ForegroundColor Gray
    } else {
        Write-Host "  ❌ MISSING: $($file.Local)" -ForegroundColor Red
        $allFilesExist = $false
    }
}

if (-not $allFilesExist) {
    Write-Host "`n❌ Some files are missing. Aborting upload.`n" -ForegroundColor Red
    exit 1
}

Write-Host ""

# Check if pscp (PuTTY SCP) is available
$pscpPath = "C:\Program Files\PuTTY\pscp.exe"
$usePscp = Test-Path $pscpPath

if (-not $usePscp) {
    Write-Host "⚠️  PuTTY pscp.exe not found at: $pscpPath" -ForegroundColor Yellow
    Write-Host "   Using manual upload instructions instead.`n" -ForegroundColor Yellow
}

# Show upload instructions
Write-Host "[2/4] Upload Instructions:" -ForegroundColor Yellow
Write-Host ""

if ($usePscp -and -not $DryRun) {
    Write-Host "Using automated upload via pscp...`n" -ForegroundColor Cyan
    
    foreach ($file in $filesToUpload) {
        Write-Host "Uploading: $($file.Description)" -ForegroundColor White
        
        # Create backup on server first
        $backupCmd = "cp $($file.Remote) $($file.Remote).backup_$(Get-Date -Format 'yyyyMMdd_HHmmss') 2>/dev/null || true"
        Write-Host "  Creating backup..." -ForegroundColor Gray
        & "C:\Program Files\PuTTY\plink.exe" -batch "$ServerUser@$ServerHost" $backupCmd
        
        # Upload file
        $uploadCmd = "& `"$pscpPath`" -batch `"$($file.Local)`" `"${ServerUser}@${ServerHost}:$($file.Remote)`""
        Write-Host "  Uploading..." -ForegroundColor Gray
        Invoke-Expression $uploadCmd
        
        if ($LASTEXITCODE -eq 0) {
            Write-Host "  ✓ Uploaded successfully`n" -ForegroundColor Green
        } else {
            Write-Host "  ❌ Upload failed!`n" -ForegroundColor Red
        }
    }
} else {
    Write-Host "📋 Manual Upload via FileZilla/WinSCP:`n" -ForegroundColor Cyan
    
    foreach ($file in $filesToUpload) {
        Write-Host "File: $($file.Description)" -ForegroundColor White
        Write-Host "  Local:  $($file.Local)" -ForegroundColor Yellow
        Write-Host "  Remote: ${ServerHost}:$($file.Remote)" -ForegroundColor Yellow
        Write-Host ""
    }
    
    Write-Host "Steps:" -ForegroundColor Cyan
    Write-Host "1. Connect to: $ServerHost" -ForegroundColor White
    Write-Host "2. Navigate to: $serverBasePath" -ForegroundColor White
    Write-Host "3. Backup existing files (right-click → Rename → add .backup)" -ForegroundColor White
    Write-Host "4. Upload new files" -ForegroundColor White
    Write-Host ""
}

# Show verification commands
Write-Host "[3/4] Server Verification Commands:" -ForegroundColor Yellow
Write-Host ""
Write-Host "# SSH to server" -ForegroundColor Gray
Write-Host "ssh $ServerUser@$ServerHost`n" -ForegroundColor White

Write-Host "# Verify version.php" -ForegroundColor Gray
Write-Host "cat $serverBasePath/version.php | grep 'version'" -ForegroundColor White
Write-Host "# Expected: `$plugin->version   = 2026021501;`n" -ForegroundColor Green

Write-Host "# Check file permissions" -ForegroundColor Gray
Write-Host "ls -lh $serverBasePath/version.php" -ForegroundColor White
Write-Host "ls -lh $serverBasePath/db/upgrade.php" -ForegroundColor White
Write-Host "ls -lh $serverBasePath/db/install.xml`n" -ForegroundColor White

Write-Host "# Fix permissions if needed" -ForegroundColor Gray
Write-Host "sudo chown -R www-data:www-data $serverBasePath/" -ForegroundColor White
Write-Host "sudo chmod -R 755 $serverBasePath/`n" -ForegroundColor White

# Show upgrade commands
Write-Host "[4/4] Run Moodle Upgrade:" -ForegroundColor Yellow
Write-Host ""
Write-Host "Option A: Web Interface (Recommended)" -ForegroundColor Cyan
Write-Host "  1. Go to: https://lms.abchorizon.com" -ForegroundColor White
Write-Host "  2. Login as admin" -ForegroundColor White
Write-Host "  3. Moodle will auto-detect upgrade" -ForegroundColor White
Write-Host "  4. Click 'Upgrade Moodle database now'" -ForegroundColor White
Write-Host ""

Write-Host "Option B: Command Line" -ForegroundColor Cyan
Write-Host "sudo -u www-data php /var/www/html/moodle/admin/cli/upgrade.php`n" -ForegroundColor White

Write-Host "Option C: Check Notifications Page" -ForegroundColor Cyan
Write-Host "  Go to: Site Administration → Notifications`n" -ForegroundColor White

# Summary
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "SUMMARY" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Files to upload: 3" -ForegroundColor White
Write-Host "  • version.php (2026021501)" -ForegroundColor White
Write-Host "  • upgrade.php (with new tables)" -ForegroundColor White
Write-Host "  • install.xml (full schema)" -ForegroundColor White
Write-Host ""
Write-Host "New tables to create: 3" -ForegroundColor White
Write-Host "  • mdl_local_mzi_students" -ForegroundColor White
Write-Host "  • mdl_local_mzi_webhook_logs" -ForegroundColor White
Write-Host "  • mdl_local_mzi_sync_status" -ForegroundColor White
Write-Host ""
Write-Host "Next: Run Moodle upgrade (web or CLI)" -ForegroundColor Yellow
Write-Host "========================================`n" -ForegroundColor Cyan

# Wait for confirmation
if (-not $DryRun) {
    Write-Host "Press any key to open FileZilla or continue..." -ForegroundColor Yellow
    $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
}
