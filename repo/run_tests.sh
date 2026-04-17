#!/usr/bin/env bash
# run_tests.sh — SmartPark full test suite runner
#
# Usage:
#   ./run_tests.sh                 # all suites
#   ./run_tests.sh --backend-only  # PHP/Pest tests only
#   ./run_tests.sh --frontend-only # Vitest + E2E only
#   ./run_tests.sh --no-e2e        # skip Playwright E2E

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$REPO_ROOT"

# ── Flags ────────────────────────────────────────────────────────────────────
RUN_BACKEND=true
RUN_FRONTEND=true
RUN_E2E=true

for arg in "$@"; do
    case $arg in
        --backend-only)  RUN_FRONTEND=false; RUN_E2E=false ;;
        --frontend-only) RUN_BACKEND=false ;;
        --no-e2e)        RUN_E2E=false ;;
    esac
done

HR="══════════════════════════════════════════════════════"

section() { printf "\n%s\n  %s\n%s\n" "$HR" "$1" "$HR"; }

# ── Result accumulators ──────────────────────────────────────────────────────
BACKEND_TESTS=0;  BACKEND_COV="N/A";  BACKEND_STATUS="SKIP"
FRONTEND_TESTS=0; FRONTEND_COV="N/A"; FRONTEND_STATUS="SKIP"
E2E_TESTS=0;                          E2E_STATUS="SKIP"

# ── 1. Start core services ────────────────────────────────────────────────────
section "Starting core services"
docker compose up -d

echo "  Waiting for MySQL..."
until docker compose exec -T mysql \
    mysqladmin ping -h localhost -uroot \
    -p"${MYSQL_ROOT_PASSWORD:-root_dev}" --silent 2>/dev/null; do
    sleep 2
done
echo "  MySQL ready."

echo "  Ensuring test database exists..."
docker compose exec -T mysql \
    mysql -uroot -p"${MYSQL_ROOT_PASSWORD:-root_dev}" 2>/dev/null <<'SQL' || true
CREATE DATABASE IF NOT EXISTS `smartpark_test`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON `smartpark_test`.* TO 'smartpark'@'%';
FLUSH PRIVILEGES;
SQL

echo "  Waiting for backend vendor to be ready (composer install may be running)..."
WAIT=0
until docker compose exec -T backend test -f /var/www/html/vendor/autoload.php 2>/dev/null; do
    if [ "$WAIT" -ge 120 ]; then
        echo "  ERROR: Backend setup timed out after 120s." >&2
        exit 1
    fi
    sleep 3
    WAIT=$((WAIT + 3))
done
echo "  Backend ready."

# ── 2. Backend Tests — Pest / PHPUnit with PCOV coverage ─────────────────────
if [ "$RUN_BACKEND" = true ]; then
    section "Backend Tests  (Pest / PHPUnit + PCOV)"
    BACKEND_OUT=$(docker compose --profile test run --rm test-runner \
        php artisan test --coverage-text --min=0 2>&1) \
        && BACKEND_STATUS="PASS" || BACKEND_STATUS="FAIL"
    echo "$BACKEND_OUT"

    # "Tests:  12 passed (35 assertions)"  or  "Tests:  10 passed, 2 failed"
    RAW=$(printf '%s' "$BACKEND_OUT" | grep -oP 'Tests:\s+\K\d+' | tail -1) \
        && BACKEND_TESTS="${RAW:-0}"
    # "Lines:       80.00% (160 / 200)"
    COV=$(printf '%s' "$BACKEND_OUT" | grep -oP 'Lines:\s+\K[\d.]+' | head -1) \
        && [ -n "$COV" ] && BACKEND_COV="${COV}%"
fi

# ── 3. Frontend Unit Tests — Vitest with v8 coverage ─────────────────────────
if [ "$RUN_FRONTEND" = true ]; then
    section "Frontend Unit Tests  (Vitest + @vitest/coverage-v8)"
    FRONTEND_OUT=$(docker compose --profile dev run --rm frontend-dev sh -c \
        'npm install --save-dev @vitest/coverage-v8 --no-save --silent 2>/dev/null; \
         npx vitest run --coverage --reporter=verbose' 2>&1) \
        && FRONTEND_STATUS="PASS" || FRONTEND_STATUS="FAIL"
    echo "$FRONTEND_OUT"

    # "Tests  5 passed (5)"
    RAW=$(printf '%s' "$FRONTEND_OUT" | grep -oP 'Tests\s+\K\d+' | tail -1) \
        && FRONTEND_TESTS="${RAW:-0}"
    # coverage table row:  "All files  |  85.00  | ..."
    COV=$(printf '%s' "$FRONTEND_OUT" | grep -oP 'All files\s*\|\s*\K[\d.]+' | head -1) \
        && [ -n "$COV" ] && FRONTEND_COV="${COV}%"
fi

# ── 4. E2E Tests — Playwright / Chromium ─────────────────────────────────────
if [ "$RUN_E2E" = true ]; then
    section "E2E Tests  (Playwright / Chromium)"
    E2E_OUT=$(docker compose --profile test run --rm e2e-runner 2>&1) \
        && E2E_STATUS="PASS" || E2E_STATUS="FAIL"
    echo "$E2E_OUT"

    # "15 passed (15)"
    RAW=$(printf '%s' "$E2E_OUT" | grep -oP '\d+ passed' | grep -oP '\d+' | tail -1) \
        && E2E_TESTS="${RAW:-0}"
fi

# ── Summary ───────────────────────────────────────────────────────────────────
TOTAL=$((BACKEND_TESTS + FRONTEND_TESTS + E2E_TESTS))

section "SUMMARY REPORT"
printf "  %-30s  %-6s  %-10s  %s\n" "Suite"                    "Status" "Tests"      "Coverage"
printf "  %-30s  %-6s  %-10s  %s\n" "──────────────────────────────" "──────" "──────────" "────────"
printf "  %-30s  %-6s  %-10s  %s\n" "Backend (PHP / Pest)"     "$BACKEND_STATUS"  "$BACKEND_TESTS"  "$BACKEND_COV"
printf "  %-30s  %-6s  %-10s  %s\n" "Frontend Unit (Vitest)"   "$FRONTEND_STATUS" "$FRONTEND_TESTS" "$FRONTEND_COV"
printf "  %-30s  %-6s  %-10s  %s\n" "E2E (Playwright)"         "$E2E_STATUS"      "$E2E_TESTS"      "N/A"
printf "  %-30s  %-6s  %-10s\n"     "──────────────────────────────" "──────" "──────────"
printf "  %-30s         %s\n"       "TOTAL"                    "$TOTAL tests"
echo ""

if [[ "$BACKEND_STATUS" == "FAIL" ]] || [[ "$FRONTEND_STATUS" == "FAIL" ]] || [[ "$E2E_STATUS" == "FAIL" ]]; then
    echo "  ✖  One or more test suites FAILED."
    exit 1
else
    echo "  ✔  All active test suites PASSED."
fi
