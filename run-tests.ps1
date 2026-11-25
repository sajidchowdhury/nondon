# Nondon ERP - Test Runner Script (Windows PowerShell 5.1)
# Compatible with Windows PowerShell 5.1
# Usage: .\run-tests.ps1
# Exit codes: 0 = all tests passed, 1 = tests failed or services unavailable

$ErrorActionPreference = 'Continue'

Write-Host ""
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "  Nondon ERP - Test Runner" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""

# ----------------------------------------------------------
# Check 1: Verify Docker is running
# ----------------------------------------------------------
Write-Host "[Check] Verifying Docker Desktop is running..." -ForegroundColor Yellow
docker info > $null 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Host "  ERROR: Docker Desktop is not running." -ForegroundColor Red
    Write-Host "  Start Docker Desktop first, then run this script." -ForegroundColor Red
    exit 1
}
Write-Host "  Docker is running." -ForegroundColor Green
Write-Host ""

# ----------------------------------------------------------
# Check 2: Verify the app container exists and is running
# ----------------------------------------------------------
Write-Host "[Check] Verifying nondon-app container..." -ForegroundColor Yellow
$appRunning = docker inspect -f '{{.State.Running}}' nondon-app 2>&1
if ($LASTEXITCODE -ne 0 -or $appRunning -ne 'true') {
    Write-Host "  ERROR: nondon-app container is not running." -ForegroundColor Red
    Write-Host "  Run setup first: .\setup.ps1" -ForegroundColor Yellow
    exit 1
}
Write-Host "  nondon-app is running." -ForegroundColor Green
Write-Host ""

# ----------------------------------------------------------
# Check 3: Verify PostgreSQL is healthy
# ----------------------------------------------------------
Write-Host "[Check] Verifying PostgreSQL health..." -ForegroundColor Yellow
$pgCheck = docker compose exec -T db pg_isready -U nondon_app -d nondon 2>&1
if ($pgCheck -notmatch 'accepting connections') {
    Write-Host "  ERROR: PostgreSQL is not ready." -ForegroundColor Red
    Write-Host "  Check logs: docker compose logs db" -ForegroundColor Yellow
    exit 1
}
Write-Host "  PostgreSQL is healthy." -ForegroundColor Green
Write-Host ""

# ----------------------------------------------------------
# Run the full Laravel test suite
# ----------------------------------------------------------
Write-Host "Running all tests..." -ForegroundColor Yellow
Write-Host "-------------------------------------------" -ForegroundColor DarkGray
Write-Host ""

docker compose exec -T app php artisan test
$testExitCode = $LASTEXITCODE

Write-Host ""
Write-Host "-------------------------------------------" -ForegroundColor DarkGray
Write-Host ""

if ($testExitCode -eq 0) {
    Write-Host "  ALL TESTS PASSED" -ForegroundColor Green
} else {
    Write-Host "  SOME TESTS FAILED (exit code: $testExitCode)" -ForegroundColor Red
}

Write-Host ""
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "To run a specific test suite:" -ForegroundColor White
Write-Host "  docker compose exec app php artisan test --testsuite=Feature" -ForegroundColor White
Write-Host ""
Write-Host "To run a specific test file:" -ForegroundColor White
Write-Host "  docker compose exec app php artisan test tests/Feature/Phase1IntegrationTest.php" -ForegroundColor White
Write-Host ""
Write-Host "To run tests with coverage:" -ForegroundColor White
Write-Host "  docker compose exec app php artisan test --coverage" -ForegroundColor White
Write-Host ""

# Return the test exit code so callers can detect failure
exit $testExitCode
