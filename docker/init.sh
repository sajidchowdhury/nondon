#!/bin/bash
# Nondon ERP - Docker initialization script
# Runs inside the app container to set up the Laravel application
# Safe to rerun — all operations are idempotent

set -e

echo "=========================================="
echo "  Nondon ERP - Docker Application Setup"
echo "=========================================="
echo ""

# Step 0: Ensure required directories exist and are writable
echo "[0/8] Ensuring directories exist..."
mkdir -p bootstrap/cache
mkdir -p storage/app
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs
chmod -R 777 bootstrap/cache storage
echo "      Done."

# Step 0b: Ensure test database exists (separate from development database)
echo "[0b/8] Ensuring test database (nondon_test) exists..."
php -r "
\$pdo = new PDO('pgsql:host=db;port=5432;dbname=nondon', 'nondon_app', 'secret');
\$result = \$pdo->query(\"SELECT 1 FROM pg_database WHERE datname = 'nondon_test'\");
if (\$result->fetchColumn() === false) {
    \$pdo->exec('CREATE DATABASE nondon_test');
    \$pdo->exec('GRANT ALL PRIVILEGES ON DATABASE nondon_test TO nondon_app');
    echo 'Created nondon_test database.';
} else {
    echo 'nondon_test database already exists.';
}
" 2>/dev/null || echo "Could not create test database (will be created by Docker init script on fresh volume)."
echo "      Done."

# Step 1: Create .env if it does not exist
if [ ! -f ".env" ]; then
    echo "[1/8] Creating .env from .env.example..."
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
echo "[2/8] Installing PHP dependencies (composer install)..."
composer install --no-interaction --prefer-dist --optimize-autoloader
echo "      Done."

# Step 3: Generate application key (only if not set)
echo "[3/8] Generating application key..."
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
echo "[4/8] Publishing Spatie permission migrations..."
php artisan vendor:publish --tag="permission-migrations" --force 2>/dev/null || true
echo "      Done."

# Step 5: Run database migrations
echo "[5/8] Running database migrations..."
php artisan migrate --force
echo "      Done."

# Note: The 9999_12_31_235959 migration adds display_name, module, description
# columns to the Spatie tables. It runs automatically as part of migrate.
# No manual ALTER TABLE needed.

# Step 6: Run database seeders (idempotent — uses firstOrCreate)
echo "[6/8] Running database seeders..."
php artisan db:seed --force
echo "      Done."

# Step 7: Clear stale caches (safe to rerun)
echo "[7/8] Clearing caches..."
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
