#!/bin/bash
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

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

docker compose up -d backend queue
echo "Waiting for backend to initialize..."
sleep 15

echo ""
echo "--- Running Backend (PHPUnit) Tests ---"
if docker compose run --rm --no-deps \
    -e APP_ENV=testing \
    -e DB_HOST=db \
    -e DB_PORT=3306 \
    -e DB_DATABASE=smartpark \
    -e DB_USERNAME=smartpark \
    -e DB_PASSWORD=smartpark \
    -e QUEUE_CONNECTION=sync \
    -e SESSION_DRIVER=array \
    -e CACHE_DRIVER=array \
    backend php artisan test --env=testing 2>&1; then
    echo "Backend tests: PASSED"
    passed=$((passed + 1))
else
    echo "Backend tests: FAILED"
    failed=$((failed + 1))
fi

echo ""
echo "--- Running Frontend (Vitest) Unit Tests ---"
if docker run --rm \
    -v "$SCRIPT_DIR/frontend:/app" \
    -w /app \
    node:20-alpine \
    sh -c "npm ci --silent && npm test -- --run 2>&1"; then
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
    if curl -sf http://localhost:8080 > /dev/null 2>&1; then
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
    bash -c "npm ci --silent && npx playwright test 2>&1"; then
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
