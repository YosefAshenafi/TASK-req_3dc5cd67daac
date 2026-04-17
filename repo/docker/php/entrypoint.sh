#!/usr/bin/env bash
set -e

cd /var/www/html

# Only run first-time setup when launching the main FPM process.
# Worker/scheduler/gateway services override the command ("php artisan ..."),
# so they get $1 = "php" and skip this block entirely.
if [ "$1" = "php-fpm" ]; then

    # Ensure a .env file exists so Laravel can resolve config values
    if [ ! -f .env ] && [ -f .env.example ]; then
        echo "[entrypoint] Creating .env from .env.example"
        cp .env.example .env
    fi

    # Install Composer dependencies if vendor is missing (bind-mount fresh clone)
    if [ ! -f vendor/autoload.php ]; then
        echo "[entrypoint] Installing Composer dependencies..."
        composer install --no-interaction --prefer-dist --optimize-autoloader
    fi

    # Run pending database migrations (idempotent)
    echo "[entrypoint] Running database migrations..."
    php artisan migrate --force

    # Seed default accounts and feature flags (skip in test environment)
    if [ "${APP_ENV:-local}" != "testing" ]; then
        echo "[entrypoint] Seeding default data..."
        php artisan db:seed --force
    fi

fi

exec "$@"
