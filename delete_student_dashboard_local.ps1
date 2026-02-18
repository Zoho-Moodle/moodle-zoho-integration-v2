# ════════════════════════════════════════════════════════════════
# PowerShell Script - Delete Student Dashboard (Local Git Repo)
# ════════════════════════════════════════════════════════════════

Write-Host "════════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "  Deleting Student Dashboard Files from Git Repo" -ForegroundColor Cyan
Write-Host "════════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host ""

$FilesToDelete = @(
    "moodle_plugin\ui\dashboard\student.php",
    "moodle_plugin\ui\dashboard\js\student_dashboard.js",
    "moodle_plugin\ui\dashboard\css\dashboard.css",
    "moodle_plugin\ui\ajax\load_profile.php",
    "moodle_plugin\ui\ajax\load_academics.php",
    "moodle_plugin\ui\ajax\load_finance.php",
    "moodle_plugin\ui\ajax\load_classes.php",
    "moodle_plugin\ui\ajax\load_grades.php",
    "moodle_plugin\ui\ajax\load_requests.php",
    "moodle_plugin\ui\ajax\acknowledge_grade.php",
    "moodle_plugin\ui\ajax\submit_request.php",
    "backend\app\api\v1\endpoints\student_dashboard.py"
)

$Deleted = 0
$NotFound = 0

foreach ($file in $FilesToDelete) {
    $fullPath = Join-Path $PSScriptRoot $file
    
    if (Test-Path $fullPath) {
        Remove-Item $fullPath -Force
        Write-Host "✓ Deleted: $file" -ForegroundColor Green
        $Deleted++
    } else {
        Write-Host "○ Not found: $file" -ForegroundColor Yellow
        $NotFound++
    }
}

Write-Host ""
Write-Host "════════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "  Summary:" -ForegroundColor Cyan
Write-Host "  - Deleted: $Deleted files" -ForegroundColor Green
Write-Host "  - Not found: $NotFound files" -ForegroundColor Yellow
Write-Host "════════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host ""
Write-Host "Next: Run delete_student_dashboard.sh on the server!" -ForegroundColor Yellow
