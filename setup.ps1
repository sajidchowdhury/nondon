# Nondon ERP - Windows PowerShell 5.1 Setup Script
# Compatible with Windows PowerShell 5.1 (not just PowerShell 7+)
# Usage: .\setup.ps1

$ErrorActionPreference = 'Continue'

Write-Host ""
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "  Nondon ERP - Docker Setup (Windows)" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""

# ----------------------------------------------------------
# Check 1: Verify Docker Desktop is running
# ----------------------------------------------------------
Write-Host "[Check] Verifying Docker Desktop is running..." -ForegroundColor Yellow
docker info > $null 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Host "  ERROR: Docker Desktop is not running." -ForegroundColor Red
    Write-Host "  Please start Docker Desktop and run this script again." -ForegroundColor Red
    exit 1
}
Write-Host "  Docker is running." -ForegroundColor Green
Write-Host ""

# ----------------------------------------------------------
# Step 1: Build and start containers (app, web, db only)
# ----------------------------------------------------------
Write-Host "[1/6] Building and starting Docker containers..." -ForegroundColor Yellow
# Start only app, web, db - NOT node (node is run separately later)
docker compose up -d --build app web db
if ($LASTEXITCODE -ne 0) {
    Write-Host "  ERROR: Failed to build or start containers." -ForegroundColor Red
    Write-Host "  Check Docker Desktop and try again." -ForegroundColor Red
    exit 1
}
Write-Host "  Containers started (app, web, db)." -ForegroundColor Green
Write-Host ""

# ----------------------------------------------------------
# Step 2: Wait for PostgreSQL to be healthy
# ----------------------------------------------------------
Write-Host "[2/6] Waiting for PostgreSQL to be ready..." -ForegroundColor Yellow
Start-Sleep -Seconds 5
$retryCount = 0
$maxRetries = 15
$dbReady = $false
while ($retryCount -lt $maxRetries) {
    $pgCheck = docker compose exec -T db pg_isready -U nondon_app -d nondon 2>&1
    if ($pgCheck -match 'accepting connections') {
        Write-Host "  PostgreSQL is ready." -ForegroundColor Green
        $dbReady = $true
        break
    }
    $retryCount++
    Write-Host "  Waiting... ($retryCount/$maxRetries)"
    Start-Sleep -Seconds 2
}
if (-not $dbReady) {
    Write-Host "  ERROR: PostgreSQL did not become ready after $maxRetries attempts." -ForegroundColor Red
    Write-Host "  Check container logs: docker compose logs db" -ForegroundColor Yellow
    exit 1
}
Write-Host ""

# ----------------------------------------------------------
# Step 3: Install PHP dependencies and set up the application
# ----------------------------------------------------------
Write-Host "[3/6] Running application setup (composer, key, migrations, seeders)..." -ForegroundColor Yellow
docker compose exec -T app bash /var/www/html/docker/init.sh
$initExitCode = $LASTEXITCODE
if ($initExitCode -ne 0) {
    Write-Host "  ERROR: Application setup failed (exit code: $initExitCode)." -ForegroundColor Red
    Write-Host "  Try running manually:" -ForegroundColor Yellow
    Write-Host "    docker compose exec app bash docker/init.sh" -ForegroundColor Yellow
    exit 1
}
Write-Host ""

# ----------------------------------------------------------
# Step 4: Install Node dependencies and build Vite assets
# ----------------------------------------------------------
# Uses the default command from docker-compose.yml: sh -c "npm install && npm run build"
# We do NOT pass the command inline to avoid PowerShell 5.1 && issues
Write-Host "[4/6] Installing Node dependencies and building frontend assets..." -ForegroundColor Yellow
docker compose run --rm --no-deps node
$buildExitCode = $LASTEXITCODE
if ($buildExitCode -ne 0) {
    Write-Host "  WARNING: Asset build had issues (exit code: $buildExitCode)." -ForegroundColor Yellow
    Write-Host "  You can retry manually:" -ForegroundColor Yellow
    Write-Host "    docker compose run --rm node" -ForegroundColor Yellow
}
Write-Host ""

# ----------------------------------------------------------
# Step 5: Optimize Laravel application
# ----------------------------------------------------------
Write-Host "[5/6] Optimizing application..." -ForegroundColor Yellow
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache
docker compose exec -T app php artisan view:cache
Write-Host "  Optimization done." -ForegroundColor Green
Write-Host ""

# ----------------------------------------------------------
# Step 6: Verify all services are running
# ----------------------------------------------------------
Write-Host "[6/6] Verifying services..." -ForegroundColor Yellow
docker compose ps
Write-Host ""

# Verify app container is running
$appRunning = docker inspect -f '{{.State.Running}}' nondon-app 2>&1
if ($appRunning -ne 'true') {
    Write-Host "  WARNING: nondon-app container is not running." -ForegroundColor Red
} else {
    Write-Host "  nondon-app: Running" -ForegroundColor Green
}

# Verify web container is running
$webRunning = docker inspect -f '{{.State.Running}}' nondon-web 2>&1
if ($webRunning -ne 'true') {
    Write-Host "  WARNING: nondon-web container is not running." -ForegroundColor Red
} else {
    Write-Host "  nondon-web: Running" -ForegroundColor Green
}

# Verify db container is running
$dbRunning = docker inspect -f '{{.State.Running}}' nondon-db 2>&1
if ($dbRunning -ne 'true') {
    Write-Host "  WARNING: nondon-db container is not running." -ForegroundColor Red
} else {
    Write-Host "  nondon-db: Running" -ForegroundColor Green
}
Write-Host ""

# ----------------------------------------------------------
# Final output
# ----------------------------------------------------------
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "  Setup Complete!" -ForegroundColor Green
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "  Application URL:  http://localhost:8080" -ForegroundColor White
Write-Host "  PostgreSQL port:  localhost:5433" -ForegroundColor White
Write-Host ""
Write-Host "  Default admin credentials:" -ForegroundColor White
Write-Host "    Username: admin" -ForegroundColor White
Write-Host "    Password: Nondon@2025" -ForegroundColor White
Write-Host ""
Write-Host "  To run tests:" -ForegroundColor White
Write-Host "    .\run-tests.ps1" -ForegroundColor White
Write-Host ""
Write-Host "  Or run tests manually:" -ForegroundColor White
Write-Host "    docker compose exec app php artisan test" -ForegroundColor White
Write-Host ""
Write-Host "  To stop containers:" -ForegroundColor White
Write-Host "    docker compose down" -ForegroundColor White
Write-Host ""
Write-Host "  To restart containers:" -ForegroundColor White
Write-Host "    docker compose up -d app web db" -ForegroundColor White
Write-Host ""
