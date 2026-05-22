#!/bin/bash
set -e

export APP_KEY="${APP_KEY:-base64:$(openssl rand -base64 32)}"
export APP_ENV="${APP_ENV:-local}"
export APP_DEBUG="${APP_DEBUG:-false}"
export DB_HOST="${DB_HOST:-db}"
export DB_PORT="${DB_PORT:-3306}"
export DB_DATABASE="${DB_DATABASE:-smartpark}"
export DB_USERNAME="${DB_USERNAME:-smartpark}"
export DB_PASSWORD="${DB_PASSWORD:-smartpark}"
export QUEUE_CONNECTION="${QUEUE_CONNECTION:-database}"
export SESSION_DRIVER="${SESSION_DRIVER:-database}"
export SESSION_LIFETIME="${SESSION_LIFETIME:-120}"
export FILESYSTEM_DISK="${FILESYSTEM_DISK:-local}"
export LOG_CHANNEL="${LOG_CHANNEL:-stderr}"
export LOG_LEVEL="${LOG_LEVEL:-debug}"

wait_for_db() {
    echo "Waiting for database..."
    for i in $(seq 1 30); do
        if php -r "new PDO('mysql:host=${DB_HOST};port=${DB_PORT};dbname=${DB_DATABASE}', '${DB_USERNAME}', '${DB_PASSWORD}');" 2>/dev/null; then
            echo "Database ready."
            return 0
        fi
        echo "Attempt $i/30 failed, retrying..."
        sleep 2
    done
    echo "Database not ready after 60s, aborting."
    exit 1
}

if [[ "${1}" != "php" || "${2}" != "artisan" ]] || [[ "${3}" == "migrate" || "${3}" == "serve" || "${3}" == "queue:work" || "${3}" == "schedule:run" ]]; then
    wait_for_db
fi

if [[ "${1}" != "php" || "${2}" != "artisan" ]]; then
    php artisan migrate --force --no-interaction 2>&1 || true

    # Seed demo data on first startup only (when users table is empty).
    # Guard against seeding before migrations complete by checking users table exists.
    users_table=$(php -r "
        \$pdo = new PDO('mysql:host=${DB_HOST};port=${DB_PORT};dbname=${DB_DATABASE}', '${DB_USERNAME}', '${DB_PASSWORD}');
        echo \$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=\"${DB_DATABASE}\" AND table_name=\"users\"')->fetchColumn();
    " 2>/dev/null || echo "0")
    if [ "$users_table" = "1" ]; then
        user_count=$(php -r "
            \$pdo = new PDO('mysql:host=${DB_HOST};port=${DB_PORT};dbname=${DB_DATABASE}', '${DB_USERNAME}', '${DB_PASSWORD}');
            echo \$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        " 2>/dev/null || echo "1")
        if [ "$user_count" = "0" ]; then
            echo "First startup — seeding demo data..."
            php artisan db:seed --force 2>&1 || true
        else
            echo "Database already has data ($user_count users) — skipping seed."
        fi
    else
        echo "Migrations not yet complete — skipping seed."
    fi

    php artisan storage:link --force 2>&1 || true
    php artisan config:cache 2>&1 || true
    php artisan route:cache 2>&1 || true
fi

exec "$@"
