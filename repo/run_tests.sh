#!/bin/bash
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

trap 'docker compose down 2>/dev/null || true' EXIT

passed=0
failed=0

echo "=== SmartPark Test Suite ==="
echo ""

echo "--- Building images ---"
docker compose build --quiet

echo "--- Starting services ---"
docker compose up -d db
echo "Waiting for database to be healthy..."
for i in $(seq 1 40); do
    if docker compose exec -T db mysqladmin ping -h localhost -u smartpark -psmartpark --silent 2>/dev/null; then
        echo "Database ready."
        break
    fi
    if [ "$i" -eq 40 ]; then
        echo "ERROR: Database did not become healthy in time."
        docker compose logs db
        exit 1
    fi
    sleep 3
done

echo "Ensuring test database exists..."
docker compose exec -T db mysql -u root -prootpassword -e \
    "CREATE DATABASE IF NOT EXISTS smartpark_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON smartpark_testing.* TO 'smartpark'@'%'; FLUSH PRIVILEGES;" 2>/dev/null || true

docker compose up -d backend queue
echo "Waiting for backend to initialize..."
for i in $(seq 1 60); do
    if docker compose exec -T backend curl -sf http://localhost:9000/api/health > /dev/null 2>&1; then
        echo "Backend ready."
        break
    fi
    if [ "$i" -eq 60 ]; then
        echo "ERROR: Backend did not become healthy in time."
        docker compose logs backend
        exit 1
    fi
    sleep 3
done

echo "Seeding demo data..."
docker compose exec -T backend php artisan db:seed --force 2>&1 || true

echo ""
echo "--- Running Backend (PHPUnit) Tests ---"
if docker compose run --rm --no-deps \
    --entrypoint "" \
    -e APP_ENV=testing \
    -e APP_KEY="base64:57ueSKkT4IzVySIdTKGpyRtL8W3blINIsCgxjnf5VQw=" \
    -e DB_HOST=db \
    -e DB_PORT=3306 \
    -e DB_DATABASE=smartpark_testing \
    -e DB_USERNAME=smartpark \
    -e DB_PASSWORD=smartpark \
    -e QUEUE_CONNECTION=sync \
    -e SESSION_DRIVER=array \
    -e CACHE_DRIVER=array \
    backend sh -c "php vendor/bin/phpunit 2>&1"; then
    echo "Backend tests: PASSED"
    passed=$((passed + 1))
else
    echo "Backend tests: FAILED"
    failed=$((failed + 1))
fi

echo ""
echo "--- Running Frontend (Vitest) Unit Tests ---"
docker build --target builder -q -t smartpark-frontend-test "$SCRIPT_DIR/frontend"
if docker run --rm \
    smartpark-frontend-test \
    sh -c "npm test -- --run 2>&1"; then
    echo "Frontend tests: PASSED"
    passed=$((passed + 1))
else
    echo "Frontend tests: FAILED"
    failed=$((failed + 1))
fi

echo ""
echo "--- Running E2E (Playwright) Tests ---"
docker compose up -d frontend
echo "Waiting for frontend to be ready..."
for i in $(seq 1 20); do
    if curl -sf http://localhost:3000 > /dev/null 2>&1; then
        echo "Frontend ready."
        break
    fi
    sleep 3
done

if docker run --rm \
    --network "$(basename "$SCRIPT_DIR")_app" \
    -v "$SCRIPT_DIR/tests/e2e:/tests" \
    -w /tests \
    -e BASE_URL="http://frontend:80" \
    mcr.microsoft.com/playwright:v1.44.0-jammy \
    bash -c "playwright test 2>&1"; then
    echo "E2E tests: PASSED"
    passed=$((passed + 1))
else
    echo "E2E tests: FAILED"
    failed=$((failed + 1))
fi

echo ""
echo "--- Cleaning up ---"
docker compose down

echo ""
echo "passed=$passed failed=$failed"
