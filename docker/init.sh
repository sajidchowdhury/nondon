#!/bin/bash
# Nondon ERP - Docker initialization script
# Runs inside the app container to set up the Laravel application
# Safe to rerun — all operations are idempotent

set -e

echo "=========================================="
echo "  Nondon ERP - Docker Application Setup"
echo "=========================================="
echo ""

# Step 1: Create .env if it does not exist
if [ ! -f ".env" ]; then
    echo "[1/7] Creating .env from .env.example..."
    cp .env.example .env
    # Only DB_HOST needs changing for Docker (container name, not localhost)
    sed -i 's/DB_HOST=127.0.0.1/DB_HOST=db/' .env
    echo "      Done. .env created with Docker settings (DB_HOST=db)."
else
    echo "[1/7] .env already exists. Verifying DB_HOST..."
    # Ensure DB_HOST points to the db container
    if grep -q "DB_HOST=127.0.0.1" .env; then
        sed -i 's/DB_HOST=127.0.0.1/DB_HOST=db/' .env
        echo "      Fixed: DB_HOST updated to 'db'."
    else
        echo "      OK: DB_HOST already set correctly."
    fi
fi

# Step 2: Install PHP dependencies
echo "[2/7] Installing PHP dependencies (composer install)..."
composer install --no-interaction --prefer-dist --optimize-autoloader
echo "      Done."

# Step 3: Generate application key (only if not set)
echo "[3/7] Generating application key..."
if grep -q "APP_KEY=" .env | grep -v "^APP_KEY=$"; then
    php artisan key:generate --force
    echo "      Done."
else
    # Check if key is empty
    APP_KEY_VALUE=$(grep "^APP_KEY=" .env | cut -d'=' -f2)
    if [ -z "$APP_KEY_VALUE" ]; then
        php artisan key:generate --force
        echo "      Done."
    else
        echo "      Skipped: APP_KEY already set."
    fi
fi

# Step 4: Publish Spatie permission migrations (if not already published)
echo "[4/7] Publishing Spatie permission migrations..."
php artisan vendor:publish --tag="permission-migrations" --force 2>/dev/null || true
echo "      Done."

# Step 5: Run database migrations
echo "[5/7] Running database migrations..."
php artisan migrate --force
echo "      Done."

# Step 6: Run database seeders (idempotent — uses firstOrCreate)
echo "[6/7] Running database seeders..."
php artisan db:seed --force
echo "      Done."

# Step 7: Clear stale caches (safe to rerun)
echo "[7/7] Clearing caches..."
php artisan config:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
php artisan cache:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true
echo "      Done."

echo ""
echo "=========================================="
echo "  Application Setup Complete!"
echo "=========================================="
echo ""
echo "  Default admin credentials:"
echo "    Username: admin"
echo "    Password: Nondon@2025"
echo ""
echo "  Application URL: http://localhost:8080"
echo ""
echo "=========================================="
