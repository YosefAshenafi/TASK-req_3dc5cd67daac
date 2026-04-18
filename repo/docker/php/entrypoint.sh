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

    # Default-account seeding is gated by environment. In local/development/testing the
    # seeder creates the documented demo accounts; in any other environment the seeder
    # no-ops unless SEED_DEFAULT_ACCOUNTS=true is explicitly set. Feature flags are seeded
    # unconditionally because they are non-credential config rows.
    APP_ENV_LOWER="$(echo "${APP_ENV:-local}" | tr '[:upper:]' '[:lower:]')"
    case "$APP_ENV_LOWER" in
        local|development)
            echo "[entrypoint] Seeding default data (env=${APP_ENV_LOWER})..."
            php artisan db:seed --force
            ;;
        testing)
            # Tests run their own RefreshDatabase + factories; skip the global seed.
            echo "[entrypoint] Skipping seed in testing env."
            ;;
        *)
            # DatabaseSeeder::shouldSeedAccounts() short-circuits unless
            # SEED_DEFAULT_ACCOUNTS=true, so it is safe to always invoke: in production it
            # creates feature flags and skips accounts unless the operator opts in.
            if [ "${SEED_DEFAULT_ACCOUNTS:-false}" = "true" ]; then
                echo "[entrypoint] SEED_DEFAULT_ACCOUNTS=true; running full seed in env=${APP_ENV_LOWER}."
            else
                echo "[entrypoint] Seeding feature flags only in env=${APP_ENV_LOWER}."
                echo "[entrypoint] Set SEED_DEFAULT_ACCOUNTS=true and ADMIN_BOOTSTRAP_PASSWORD "
                echo "[entrypoint] (plus USER_/TECH_BOOTSTRAP_PASSWORD) to seed accounts safely."
            fi
            php artisan db:seed --force
            ;;
    esac

fi

exec "$@"
