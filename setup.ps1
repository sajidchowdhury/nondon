# Nondon ERP - Windows PowerShell Setup Script
# Run this script in PowerShell from the project root directory
# Usage: .\setup.ps1

Write-Host ""
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "  Nondon ERP - Docker Setup (Windows)" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""

# Check if Docker Desktop is running
Write-Host "[Check] Verifying Docker Desktop is running..." -ForegroundColor Yellow
$dockerStatus = docker info 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Host "  ERROR: Docker Desktop is not running." -ForegroundColor Red
    Write-Host "  Please start Docker Desktop and run this script again." -ForegroundColor Red
    exit 1
}
Write-Host "  Docker is running." -ForegroundColor Green
Write-Host ""

# Step 1: Build and start containers
Write-Host "[1/6] Building and starting Docker containers..." -ForegroundColor Yellow
docker compose up -d --build
if ($LASTEXITCODE -ne 0) {
    Write-Host "  ERROR: Failed to build containers." -ForegroundColor Red
    exit 1
}
Write-Host "  Containers started." -ForegroundColor Green
Write-Host ""

# Wait for PostgreSQL to be ready
Write-Host "[2/6] Waiting for PostgreSQL to be ready..." -ForegroundColor Yellow
Start-Sleep -Seconds 5
$retryCount = 0
$maxRetries = 10
while ($retryCount -lt $maxRetries) {
    $pgCheck = docker compose exec -T db pg_isready -U nondon_app -d nondon 2>&1
    if ($pgCheck -match "accepting connections") {
        Write-Host "  PostgreSQL is ready." -ForegroundColor Green
        break
    }
    $retryCount++
    Write-Host "  Waiting... ($retryCount/$maxRetries)"
    Start-Sleep -Seconds 2
}
if ($retryCount -eq $maxRetries) {
    Write-Host "  WARNING: PostgreSQL not ready after $maxRetries attempts." -ForegroundColor Yellow
    Write-Host "  Continuing anyway — migrations may fail if DB is not ready." -ForegroundColor Yellow
}
Write-Host ""

# Step 3: Install PHP dependencies and set up the application
Write-Host "[3/6] Installing PHP dependencies and setting up application..." -ForegroundColor Yellow
docker compose exec -T app bash /var/www/html/docker/init.sh
if ($LASTEXITCODE -ne 0) {
    Write-Host "  ERROR: Application setup failed." -ForegroundColor Red
    Write-Host "  Try running manually: docker compose exec app bash docker/init.sh" -ForegroundColor Yellow
    exit 1
}
Write-Host ""

# Step 4: Install Node dependencies and build assets
Write-Host "[4/6] Installing Node dependencies and building assets..." -ForegroundColor Yellow
docker compose run --rm --no-deps node sh -c "npm install && npm run build"
if ($LASTEXITCODE -ne 0) {
    Write-Host "  WARNING: Asset build had issues. You can build manually:" -ForegroundColor Yellow
    Write-Host "    docker compose run --rm node npm install" -ForegroundColor Yellow
    Write-Host "    docker compose run --rm node npm run build" -ForegroundColor Yellow
}
Write-Host ""

# Step 5: Final optimization
Write-Host "[5/6] Optimizing application..." -ForegroundColor Yellow
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache
docker compose exec -T app php artisan view:cache
Write-Host "  Done." -ForegroundColor Green
Write-Host ""

# Step 6: Show status
Write-Host "[6/6] Container status:" -ForegroundColor Yellow
docker compose ps
Write-Host ""

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
Write-Host "    docker compose exec app php artisan test" -ForegroundColor White
Write-Host ""
Write-Host "  To run a specific test file:" -ForegroundColor White
Write-Host "    docker compose exec app php artisan test tests/Feature/Phase1IntegrationTest.php" -ForegroundColor White
Write-Host ""
Write-Host "  To stop containers:" -ForegroundColor White
Write-Host "    docker compose down" -ForegroundColor White
Write-Host ""
Write-Host "  To restart containers:" -ForegroundColor White
Write-Host "    docker compose up -d" -ForegroundColor White
Write-Host ""
