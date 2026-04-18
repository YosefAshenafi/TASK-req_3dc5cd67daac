#!/usr/bin/env bash
set -e

cd /var/www/html

# Bind mounts (e.g. ./backend in docker-compose) often arrive as root:root with 755 dirs,
# so user www cannot create .env or vendor/. Fix ownership at startup when running as root.
if [ "$(id -u)" = "0" ]; then
    chown -R www:www /var/www/html /var/spool/smartpark 2>/dev/null || true
fi

run_as_www() {
    if [ "$(id -u)" = "0" ]; then
        su-exec www "$@"
    else
        "$@"
    fi
}

# Only run first-time setup when launching the main FPM process.
# Worker/scheduler/gateway services override the command ("php artisan ..."),
# so they get $1 = "php" and skip this block entirely.
if [ "$1" = "php-fpm" ]; then
    # Bind-mounted ./backend may stay root-owned; chown can fail silently above.
    # Create .env and vendor/ as root so composer and healthchecks succeed in CI.
    if [ "$(id -u)" = "0" ]; then
        cd /var/www/html
        if [ ! -f .env ] && [ -f .env.example ]; then
            echo "[entrypoint] Creating .env from .env.example"
            cp .env.example .env
        fi
        if [ ! -f vendor/autoload.php ]; then
            echo "[entrypoint] Installing Composer dependencies..."
            composer install --no-interaction --prefer-dist --optimize-autoloader
        fi
        chown -R www:www /var/www/html /var/spool/smartpark 2>/dev/null || true
    fi

    run_as_www env \
        APP_ENV="${APP_ENV:-local}" \
        SEED_DEFAULT_ACCOUNTS="${SEED_DEFAULT_ACCOUNTS:-false}" \
        bash -c '
        set -e
        cd /var/www/html

        echo "[entrypoint] Running database migrations..."
        php artisan migrate --force

        APP_ENV_LOWER="$(echo "${APP_ENV:-local}" | tr "[:upper:]" "[:lower:]")"
        case "$APP_ENV_LOWER" in
            local|development)
                echo "[entrypoint] Seeding default data (env=${APP_ENV_LOWER})..."
                php artisan db:seed --force
                ;;
            testing)
                echo "[entrypoint] Skipping seed in testing env."
                ;;
            *)
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
        '
fi

if [ "$(id -u)" = "0" ]; then
    exec su-exec www "$@"
else
    exec "$@"
fi
