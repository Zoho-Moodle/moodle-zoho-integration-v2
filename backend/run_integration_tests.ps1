# Integration Test Runner
# Starts server in separate window and runs tests

Write-Host "=" * 80 -ForegroundColor Cyan
Write-Host "EVENT ROUTER INTEGRATION TEST RUNNER" -ForegroundColor Cyan
Write-Host "=" * 80 -ForegroundColor Cyan

# Check if server is already running
$serverRunning = $false
try {
    $response = Invoke-WebRequest -Uri "http://localhost:8001/api/v1/events/health" -Method GET -UseBasicParsing -TimeoutSec 2 -ErrorAction SilentlyContinue
    if ($response.StatusCode -eq 200) {
        $serverRunning = $true
        Write-Host "`n✅ Server is already running" -ForegroundColor Green
    }
} catch {
    Write-Host "`n⚠️  Server is not running" -ForegroundColor Yellow
}

# Start server if not running
if (-not $serverRunning) {
    Write-Host "🚀 Starting server in new window..." -ForegroundColor Cyan
    
    # Kill any existing python processes
    Stop-Process -Name python -Force -ErrorAction SilentlyContinue
    Start-Sleep -Seconds 2
    
    # Start server in new PowerShell window
    $serverProcess = Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd '$PSScriptRoot'; python start_server.py" -PassThru
    
    Write-Host "⏳ Waiting for server to start..." -ForegroundColor Yellow
    Start-Sleep -Seconds 5
    
    # Verify server started
    $attempts = 0
    $maxAttempts = 10
    while ($attempts -lt $maxAttempts) {
        try {
            $response = Invoke-WebRequest -Uri "http://localhost:8001/api/v1/events/health" -Method GET -UseBasicParsing -TimeoutSec 2 -ErrorAction SilentlyContinue
            if ($response.StatusCode -eq 200) {
                Write-Host "✅ Server started successfully!" -ForegroundColor Green
                break
            }
        } catch {
            $attempts++
            Write-Host "." -NoNewline
            Start-Sleep -Seconds 1
        }
    }
    
    if ($attempts -eq $maxAttempts) {
        Write-Host "`n❌ Failed to start server!" -ForegroundColor Red
        exit 1
    }
}

Write-Host "`n" + "=" * 80 -ForegroundColor Cyan
Write-Host "RUNNING INTEGRATION TESTS" -ForegroundColor Cyan
Write-Host "=" * 80 -ForegroundColor Cyan

# Run integration tests
$env:PYTHONIOENCODING = "utf-8"
python examples/test_event_router_integration.py

Write-Host "`n" + "=" * 80 -ForegroundColor Cyan
Write-Host "TESTS COMPLETED" -ForegroundColor Cyan
Write-Host "=" * 80 -ForegroundColor Cyan

Write-Host "`n💡 Server is still running in separate window" -ForegroundColor Yellow
Write-Host "   To stop it, close the server window or run: Stop-Process -Name python -Force" -ForegroundColor Yellow
