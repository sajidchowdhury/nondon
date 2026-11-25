# Nondon ERP - Test Runner Script (Windows PowerShell)
# Run this script to execute all Phase 1 tests
# Usage: .\run-tests.ps1

Write-Host ""
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "  Nondon ERP - Phase 1 Test Runner" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""

# Check if containers are running
$containerStatus = docker compose ps --format json 2>&1
if ($LASTEXITCODE -ne 0 -or $containerStatus -eq "[]") {
    Write-Host "  ERROR: Docker containers are not running." -ForegroundColor Red
    Write-Host "  Run setup first: .\setup.ps1" -ForegroundColor Yellow
    exit 1
}

Write-Host "Running all tests..." -ForegroundColor Yellow
Write-Host ""

docker compose exec app php artisan test

Write-Host ""
Write-Host "==========================================" -ForegroundColor Cyan

if ($LASTEXITCODE -eq 0) {
    Write-Host "  ALL TESTS PASSED" -ForegroundColor Green
} else {
    Write-Host "  SOME TESTS FAILED" -ForegroundColor Red
}

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
