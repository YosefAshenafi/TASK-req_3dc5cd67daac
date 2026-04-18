#!/usr/bin/env bash
# run_tests.sh — SmartPark full test suite runner
#
# All suites run inside Docker Compose (test profile). No host Node/PHP/Playwright required.
#
# Backend: three runs —
#   (1) tests/Feature with coverage scoped to app/Http (phpunit-api.xml), min 90%.
#   (2) tests/Unit with coverage scoped to unit-tested modules (phpunit-unit.xml), min 90%.
#   (3) full Pest project gate (phpunit.xml), min 90%.
# Frontend: Vitest unit (v8 coverage).
# E2E: Playwright pass rate must be ≥ 90% (no browser line coverage collected here).
# Final report prints per-suite test counts and coverage / pass rate.
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

# Normalize tool output (Docker / Vitest sometimes emit CRLF)
strip_crlf() { printf '%s' "$1" | tr -d '\r'; }

# Strip ANSI color codes so grep/sed can match Laravel/Pest output reliably.
strip_ansi() {
    if command -v perl >/dev/null 2>&1; then
        printf '%s' "$1" | perl -pe 's/\e\[[0-9;]*m//g'
    else
        printf '%s' "$1"
    fi
}

# Portable number extraction (BSD grep on macOS has no grep -P)
extract_tests_passed() {
    # Pest: "Tests:    12 passed" or "Tests:    1 skipped, 202 passed (...)"
    # Vitest v4: "Tests  28 passed (28)" (no colon after Tests)
    # Command substitution runs in a subshell; pipefail + grep miss would abort without set +e.
    local out n last_tests
    set +e
    out=$(strip_crlf "$1")
    out=$(strip_ansi "$out")
    last_tests=$(printf '%s' "$out" | grep 'Tests:' | tail -1 || true)
    n=$(printf '%s' "$last_tests" | grep -oE '[0-9]+ passed' | tail -1 | grep -oE '[0-9]+' | head -1 || true)
    [ -n "$n" ] && { echo "$n"; return; }
    n=$(printf '%s' "$out" | grep -oE 'Tests[[:space:]]+[0-9]+[[:space:]]+passed' | tail -1 | sed -E 's/.*Tests[[:space:]]+([0-9]+)[[:space:]]+passed.*/\1/' || true)
    [ -n "$n" ] && [[ "$n" =~ ^[0-9]+$ ]] && { echo "$n"; return; }
    n=$(printf '%s' "$out" | grep -E '[[:space:]]Tests[[:space:]]+[0-9]+[[:space:]]+passed' | tail -1 | sed -E 's/.*Tests[[:space:]]+([0-9]+)[[:space:]]+passed.*/\1/' || true)
    [ -n "$n" ] && echo "$n"
}

extract_backend_coverage_pct() {
    # Laravel `php artisan test --coverage` footer: "Total: 74.1 %"
    # PHPUnit text mode may instead print: "Lines:   80.00% (160 / 200)"
    local out line pct
    set +e
    out=$(strip_crlf "$1")
    out=$(strip_ansi "$out")
    line=$(printf '%s' "$out" | grep -E 'Total:[[:space:]]+[0-9]' | tail -1 || true)
    if [ -n "$line" ]; then
        pct=$(printf '%s' "$line" | grep -oE '[0-9]+\.[0-9]+' | head -1 || true)
        [ -n "$pct" ] && { echo "$pct"; return; }
        pct=$(printf '%s' "$line" | grep -oE '[0-9]+%' | head -1 | tr -d '%' || true)
        [ -n "$pct" ] && { echo "$pct"; return; }
    fi
    line=$(printf '%s' "$out" | grep -E 'Lines:[[:space:]]+[0-9]' | head -1 || true)
    if [ -n "$line" ]; then
        pct=$(printf '%s' "$line" | grep -oE '[0-9]+\.[0-9]+' | head -1 || true)
        [ -n "$pct" ] && { echo "$pct"; return; }
        pct=$(printf '%s' "$line" | grep -oE '[0-9]+%' | head -1 | tr -d '%' || true)
        [ -n "$pct" ] && { echo "$pct"; return; }
    fi
    line=$(printf '%s' "$out" | grep -oE 'Lines[[:space:]]+[0-9]+\.[0-9]+%' | head -1 || true)
    [ -n "$line" ] && printf '%s' "$line" | grep -oE '[0-9]+\.[0-9]+' | head -1
}

extract_frontend_coverage_pct() {
    # Vitest v8 summary: "Lines        : 100% ( 61/61 )" — often integer %, no decimals
    local out line pct
    set +e
    out=$(strip_crlf "$1")
    out=$(strip_ansi "$out")
    line=$(printf '%s' "$out" | grep -E '^[[:space:]]*Lines[[:space:]]*:' | head -1 || true)
    if [ -n "$line" ]; then
        pct=$(printf '%s' "$line" | grep -oE '[0-9]+\.[0-9]+' | head -1 || true)
        [ -n "$pct" ] && { echo "$pct"; return; }
        pct=$(printf '%s' "$line" | grep -oE '[0-9]+%' | head -1 | tr -d '%' || true)
        [ -n "$pct" ] && { echo "$pct"; return; }
    fi
    # Fallback: v8 table row "All files  | %Stmts | %Branch | %Funcs | %Lines |"
    line=$(printf '%s' "$out" | grep 'All files' | head -1 || true)
    if [ -n "$line" ]; then
        printf '%s' "$line" | awk -F'|' '{print $5}' | grep -oE '[0-9]+\.[0-9]+|[0-9]+' | head -1 || true
    fi
}

extract_e2e_passed() {
    # Playwright: "53 passed" / "15 passed (15)"
    local out
    set +e
    out=$(strip_crlf "$1")
    out=$(strip_ansi "$out")
    printf '%s' "$out" | grep -oE '[0-9]+ passed' | tail -1 | grep -oE '[0-9]+' | head -1 || true
}

extract_e2e_failed() {
    # Playwright: "2 failed" in the summary line
    local out
    set +e
    out=$(strip_crlf "$1")
    out=$(strip_ansi "$out")
    printf '%s' "$out" | grep -oE '[0-9]+ failed' | tail -1 | grep -oE '[0-9]+' | head -1 || true
}

# Pass rate as an integer-rounded percentage (e.g., 97). Empty if totals are zero.
compute_pass_rate() {
    local passed="${1:-0}" failed="${2:-0}" total
    total=$((passed + failed))
    [ "$total" -le 0 ] && return
    awk -v p="$passed" -v t="$total" 'BEGIN { printf "%.1f", (p / t) * 100 }'
}

# ── Result accumulators ──────────────────────────────────────────────────────
API_TESTS=0;       API_COV="N/A";       API_STATUS="SKIP"
PHP_UNIT_TESTS=0;  PHP_UNIT_COV="N/A";  PHP_UNIT_STATUS="SKIP"
BACKEND_GATE_TESTS=0; BACKEND_GATE_COV="N/A"; BACKEND_GATE_STATUS="SKIP"
FRONTEND_TESTS=0;  FRONTEND_COV="N/A"; FRONTEND_STATUS="SKIP"
E2E_TESTS=0;       E2E_PASS_RATE="N/A"; E2E_STATUS="SKIP"

# Per-suite minimum thresholds (line coverage for backend/frontend; pass rate for E2E).
MIN_API_COV=90
MIN_UNIT_COV=90
MIN_E2E_PASS_RATE=90

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

# ── 2. Backend Tests — API (Feature), PHP Unit, then full gate (≥90% lines) ───
# Split runs use Pest + coverage-text for per-suite counts and line % (subset execution).
# Full run enforces project-wide line coverage (same app/ source as phpunit.xml).
if [ "$RUN_BACKEND" = true ]; then
    section "Backend API  (Pest tests/Feature — HTTP / integration, coverage scoped to app/Http, min ${MIN_API_COV}%)  [Docker: test-runner]"
    OUTFILE=$(mktemp)
    set +e
    docker compose --profile test run --rm -T test-runner \
        php -d memory_limit=512M ./vendor/bin/pest \
        -c phpunit-api.xml tests/Feature \
        --coverage --coverage-text --min="${MIN_API_COV}" 2>&1 | tee "$OUTFILE"
    ST_API=${PIPESTATUS[0]}
    set -e
    API_OUT=$(cat "$OUTFILE")
    rm -f "$OUTFILE"
    if [ "$ST_API" -eq 0 ]; then API_STATUS="PASS"; else API_STATUS="FAIL"; fi
    RAW=$(extract_tests_passed "$API_OUT")
    API_TESTS="${RAW:-0}"
    COV=$(extract_backend_coverage_pct "$API_OUT")
    [ -n "$COV" ] && API_COV="${COV}%"

    section "Backend Unit  (Pest tests/Unit, coverage scoped to unit-tested modules, min ${MIN_UNIT_COV}%)  [Docker: test-runner]"
    OUTFILE=$(mktemp)
    set +e
    docker compose --profile test run --rm -T test-runner \
        php -d memory_limit=512M ./vendor/bin/pest \
        -c phpunit-unit.xml tests/Unit \
        --coverage --coverage-text --min="${MIN_UNIT_COV}" 2>&1 | tee "$OUTFILE"
    ST_PU=${PIPESTATUS[0]}
    set -e
    PHP_UNIT_OUT=$(cat "$OUTFILE")
    rm -f "$OUTFILE"
    if [ "$ST_PU" -eq 0 ]; then PHP_UNIT_STATUS="PASS"; else PHP_UNIT_STATUS="FAIL"; fi
    RAW=$(extract_tests_passed "$PHP_UNIT_OUT")
    PHP_UNIT_TESTS="${RAW:-0}"
    COV=$(extract_backend_coverage_pct "$PHP_UNIT_OUT")
    [ -n "$COV" ] && PHP_UNIT_COV="${COV}%"

    section "Backend full suite  (Pest + PCOV gate ≥90% project lines)  [Docker: test-runner]"
    OUTFILE=$(mktemp)
    set +e
    docker compose --profile test run --rm -T test-runner \
        php -d memory_limit=512M artisan test --coverage --min=90 2>&1 | tee "$OUTFILE"
    ST_GATE=${PIPESTATUS[0]}
    set -e
    BACKEND_GATE_OUT=$(cat "$OUTFILE")
    rm -f "$OUTFILE"
    if [ "$ST_GATE" -eq 0 ]; then BACKEND_GATE_STATUS="PASS"; else BACKEND_GATE_STATUS="FAIL"; fi
    RAW=$(extract_tests_passed "$BACKEND_GATE_OUT")
    BACKEND_GATE_TESTS="${RAW:-0}"
    COV=$(extract_backend_coverage_pct "$BACKEND_GATE_OUT")
    [ -n "$COV" ] && BACKEND_GATE_COV="${COV}%"
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
        section "E2E Tests  (Playwright / Chromium — min ${MIN_E2E_PASS_RATE}% pass rate)  [Docker: e2e-runner → http://nginx]"
        OUTFILE=$(mktemp)
        set +e
        docker compose --profile test run --rm -T e2e-runner 2>&1 | tee "$OUTFILE"
        ST=${PIPESTATUS[0]}
        set -e
        E2E_OUT=$(cat "$OUTFILE")
        rm -f "$OUTFILE"

        E2E_PASSED=$(extract_e2e_passed "$E2E_OUT")
        E2E_PASSED="${E2E_PASSED:-0}"
        E2E_FAILED=$(extract_e2e_failed "$E2E_OUT")
        E2E_FAILED="${E2E_FAILED:-0}"
        E2E_TESTS=$((E2E_PASSED + E2E_FAILED))
        RATE=$(compute_pass_rate "$E2E_PASSED" "$E2E_FAILED")
        if [ -n "$RATE" ]; then
            E2E_PASS_RATE="${RATE}%"
            if awk -v r="$RATE" -v m="$MIN_E2E_PASS_RATE" 'BEGIN { exit !(r + 0 >= m + 0) }'; then
                E2E_STATUS="PASS"
            else
                E2E_STATUS="FAIL"
            fi
        else
            # No pass/fail counts parsed — fall back to process exit code.
            if [ "$ST" -eq 0 ]; then E2E_STATUS="PASS"; else E2E_STATUS="FAIL"; fi
        fi
    fi
fi

# ── Summary ───────────────────────────────────────────────────────────────────
TOTAL=$((API_TESTS + PHP_UNIT_TESTS + FRONTEND_TESTS + E2E_TESTS))

section "FINAL REPORT — test counts and coverage / pass rate"
printf "  %-38s  %-6s  %-8s  %s\n" "Suite" "Status" "Tests" "Coverage / pass rate"
printf "  %-38s  %-6s  %-8s  %s\n" "────────────────────────────────────────" "──────" "────────" "─────────────────────"
if [ "$RUN_BACKEND" = true ]; then
    printf "  %-38s  %-6s  %-8s  %s\n" "API (PHP Feature / HTTP)" "$API_STATUS" "$API_TESTS" "$API_COV (min ${MIN_API_COV}%, scope app/Http)"
    printf "  %-38s  %-6s  %-8s  %s\n" "Unit (PHP, tests/Unit)" "$PHP_UNIT_STATUS" "$PHP_UNIT_TESTS" "$PHP_UNIT_COV (min ${MIN_UNIT_COV}%, scope app/{Casts,Jobs,Logging,…})"
    printf "  %-38s  %-6s  %-8s  %s\n" "Backend gate (full Pest, min 90%)" "$BACKEND_GATE_STATUS" "$BACKEND_GATE_TESTS" "$BACKEND_GATE_COV"
fi
if [ "$RUN_FRONTEND" = true ]; then
    printf "  %-38s  %-6s  %-8s  %s\n" "Unit (JS / Vitest)" "$FRONTEND_STATUS" "$FRONTEND_TESTS" "$FRONTEND_COV"
fi
if [ "$RUN_E2E" = true ]; then
    printf "  %-38s  %-6s  %-8s  %s\n" "E2E (Playwright)" "$E2E_STATUS" "$E2E_TESTS" "$E2E_PASS_RATE pass rate (min ${MIN_E2E_PASS_RATE}%)"
fi
printf "  %-38s  %-6s  %-8s\n" "────────────────────────────────────────" "──────" "────────"
printf "  %-38s          %s\n" "Sum of suite test counts (API+PHP+JS+E2E)" "$TOTAL"
echo ""
echo "  Notes:"
echo "    • API row: line % is scoped to app/Http (see phpunit-api.xml); each Feature run must reach ≥${MIN_API_COV}%."
echo "    • Unit row: line % is scoped to unit-tested modules (see phpunit-unit.xml); must reach ≥${MIN_UNIT_COV}%."
echo "    • Backend gate: single full run; must reach ≥90% of included lines (see phpunit.xml)."
echo "    • JS (Vitest): coverage is only for modules loaded during unit tests (see Vitest output)."
echo "    • E2E (Playwright): no browser line coverage; gate is pass rate (passed / (passed + failed)) ≥ ${MIN_E2E_PASS_RATE}%."
echo ""

if [ "$RUN_BACKEND" = true ]; then
    if [[ "$API_STATUS" == "FAIL" ]] || [[ "$PHP_UNIT_STATUS" == "FAIL" ]] || [[ "$BACKEND_GATE_STATUS" == "FAIL" ]]; then
        BACKEND_ANY_FAIL=1
    else
        BACKEND_ANY_FAIL=0
    fi
else
    BACKEND_ANY_FAIL=0
fi

if [[ "${BACKEND_ANY_FAIL:-0}" -eq 1 ]] || [[ "$FRONTEND_STATUS" == "FAIL" ]] || [[ "$E2E_STATUS" == "FAIL" ]]; then
    echo "  ✖  One or more test suites FAILED."
    exit 1
else
    echo "  ✔  All active test suites PASSED."
fi
