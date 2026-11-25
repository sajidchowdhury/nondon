#!/bin/bash
# Nondon ERP - Docker initialization script
# Runs inside the app container to set up the Laravel application

set -e

echo "=========================================="
echo "  Nondon ERP - Docker Setup"
echo "=========================================="
echo ""

# Check if .env exists, create from .env.example
if [ ! -f ".env" ]; then
    echo "[1/7] Creating .env from .env.example..."
    cp .env.example .env
    # Update DB_HOST for Docker (container name, not localhost)
    sed -i 's/DB_HOST=127.0.0.1/DB_HOST=db/' .env
    sed -i 's/DB_PORT=5432/DB_PORT=5432/' .env
    sed -i 's/DB_DATABASE=nondon/DB_DATABASE=nondon/' .env
    sed -i 's/DB_USERNAME=nondon_app/DB_USERNAME=nondon_app/' .env
    sed -i 's/DB_PASSWORD=secret/DB_PASSWORD=secret/' .env
    echo "      Done. .env created with Docker settings."
else
    echo "[1/7] .env already exists."
fi

# Install PHP dependencies
echo "[2/7] Installing PHP dependencies (composer install)..."
composer install --no-interaction --prefer-dist --optimize-autoloader
echo "      Done."

# Generate application key
echo "[3/7] Generating application key..."
php artisan key:generate --force
echo "      Done."

# Publish Spatie permission migrations
echo "[4/7] Publishing Spatie permission migrations..."
php artisan vendor:publish --tag="permission-migrations" --force 2>/dev/null || true
echo "      Done."

# Run database migrations
echo "[5/7] Running database migrations..."
php artisan migrate --force
echo "      Done."

# Run database seeders
echo "[6/7] Running database seeders..."
php artisan db:seed --force
echo "      Done."

# Clear and cache config
echo "[7/7] Optimizing application..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear
echo "      Done."

echo ""
echo "=========================================="
echo "  Setup Complete!"
echo "=========================================="
echo ""
echo "  Default admin credentials:"
echo "    Username: admin"
echo "    Password: Nondon@2025"
echo ""
echo "  Application URL: http://localhost:8080"
echo "  PostgreSQL port: 5433 (host) -> 5432 (container)"
echo ""
echo "  To run tests:"
echo "    docker compose exec app php artisan test"
echo ""
echo "=========================================="
