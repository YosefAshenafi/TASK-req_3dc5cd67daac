#!/usr/bin/env bash
# run_tests.sh — SmartPark full test suite runner
#
# All suites run inside Docker Compose (test profile). No host Node/PHP/Playwright required.
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

# Portable number extraction (BSD grep on macOS has no grep -P)
extract_tests_passed() {
    # Pest: "Tests:    12 passed" / Vitest summary: "      Tests  5 passed (5)"
    local n
    n=$(printf '%s' "$1" | grep -oE 'Tests:[[:space:]]+[0-9]+ passed' | tail -1 | grep -oE '[0-9]+' | head -1)
    [ -n "$n" ] && { echo "$n"; return; }
    printf '%s' "$1" | grep -E '[[:space:]]Tests[[:space:]]+[0-9]+ passed' | tail -1 | grep -oE '[0-9]+' | head -1 || true
}

extract_backend_coverage_pct() {
    # Pest PCOV: "Lines:   80.00% (160 / 200)"
    printf '%s' "$1" | grep -oE 'Lines:[[:space:]]+[0-9]+\.[0-9]+%' | head -1 | grep -oE '[0-9]+\.[0-9]+' | head -1 || true
}

extract_frontend_coverage_pct() {
    # Vitest v8 text table: "All files      |   85.00   | ..."
    printf '%s' "$1" | grep 'All files' | head -1 | grep -oE '[0-9]+\.[0-9]+' | head -1 || true
}

extract_e2e_passed() {
    # Playwright: "53 passed" / "15 passed (15)"
    printf '%s' "$1" | grep -oE '[0-9]+ passed' | tail -1 | grep -oE '[0-9]+' | head -1 || true
}

# ── Result accumulators ──────────────────────────────────────────────────────
BACKEND_TESTS=0;  BACKEND_COV="N/A";  BACKEND_STATUS="SKIP"
FRONTEND_TESTS=0; FRONTEND_COV="N/A"; FRONTEND_STATUS="SKIP"
E2E_TESTS=0;                          E2E_STATUS="SKIP"

# ── 1. Start core services ────────────────────────────────────────────────────
section "Starting core services"
docker compose up -d --remove-orphans

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
    section "Backend Tests  (Pest / PHPUnit + PCOV)  [Docker: test-runner]"
    OUTFILE=$(mktemp)
    set +e
    docker compose --profile test run --rm -T test-runner \
        php artisan test --coverage-text --min=0 2>&1 | tee "$OUTFILE"
    ST=${PIPESTATUS[0]}
    set -e
    BACKEND_OUT=$(cat "$OUTFILE")
    rm -f "$OUTFILE"
    if [ "$ST" -eq 0 ]; then BACKEND_STATUS="PASS"; else BACKEND_STATUS="FAIL"; fi

    RAW=$(extract_tests_passed "$BACKEND_OUT")
    BACKEND_TESTS="${RAW:-0}"
    COV=$(extract_backend_coverage_pct "$BACKEND_OUT")
    [ -n "$COV" ] && BACKEND_COV="${COV}%"
fi

# ── 3. Frontend Unit Tests — Vitest with v8 coverage ─────────────────────────
if [ "$RUN_FRONTEND" = true ]; then
    section "Frontend Unit Tests  (Vitest + @vitest/coverage-v8)  [Docker: vitest-runner]"
    OUTFILE=$(mktemp)
    set +e
    docker compose --profile test run --rm -T vitest-runner 2>&1 | tee "$OUTFILE"
    ST=${PIPESTATUS[0]}
    set -e
    FRONTEND_OUT=$(cat "$OUTFILE")
    rm -f "$OUTFILE"
    if [ "$ST" -eq 0 ]; then FRONTEND_STATUS="PASS"; else FRONTEND_STATUS="FAIL"; fi

    RAW=$(extract_tests_passed "$FRONTEND_OUT")
    FRONTEND_TESTS="${RAW:-0}"
    COV=$(extract_frontend_coverage_pct "$FRONTEND_OUT")
    [ -n "$COV" ] && FRONTEND_COV="${COV}%"
fi

# ── 4. E2E Tests — Playwright / Chromium (needs nginx + built SPA in dist/) ──
if [ "$RUN_E2E" = true ]; then
    section "Building frontend for E2E  (Docker: frontend-build → ./frontend/dist)"
    OUTFILE=$(mktemp)
    set +e
    docker compose --profile test run --rm -T frontend-build 2>&1 | tee "$OUTFILE"
    ST=${PIPESTATUS[0]}
    set -e
    BUILD_OUT=$(cat "$OUTFILE")
    rm -f "$OUTFILE"
    if [ "$ST" -ne 0 ]; then
        echo "  ✖  Frontend build failed; skipping E2E." >&2
        E2E_STATUS="FAIL"
    else
        section "E2E Tests  (Playwright / Chromium)  [Docker: e2e-runner → http://nginx]"
        OUTFILE=$(mktemp)
        set +e
        docker compose --profile test run --rm -T e2e-runner 2>&1 | tee "$OUTFILE"
        ST=${PIPESTATUS[0]}
        set -e
        E2E_OUT=$(cat "$OUTFILE")
        rm -f "$OUTFILE"
        if [ "$ST" -eq 0 ]; then E2E_STATUS="PASS"; else E2E_STATUS="FAIL"; fi

        RAW=$(extract_e2e_passed "$E2E_OUT")
        E2E_TESTS="${RAW:-0}"
    fi
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
