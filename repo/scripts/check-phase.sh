#!/usr/bin/env bash
# Phase exit-criteria checker. Usage: ./scripts/check-phase.sh <phase-number>
# Runs the checks defined for the given phase in PLAN.md and exits non-zero on failure.

set -euo pipefail
PHASE="${1:-}"
if [[ -z "$PHASE" ]]; then
  echo "Usage: $0 <phase-number>"; exit 2
fi

ok()   { printf "\033[32m  ✓ %s\033[0m\n" "$1"; }
fail() { printf "\033[31m  ✗ %s\033[0m\n" "$1"; exit 1; }

case "$PHASE" in
  0)
    echo "Phase 0: Scaffolding & Docker"
    docker compose config >/dev/null && ok "docker-compose.yml is valid"
    docker compose ps --services | grep -q mysql && ok "mysql service declared"
    docker compose ps --services | grep -q redis && ok "redis service declared"
    docker compose ps --services | grep -q backend && ok "backend service declared"
    [[ -d api-tests && -d unit-tests && -d e2e-tests ]] && ok "three test folders present" || fail "test folders missing"
    ;;
  1)
    echo "Phase 1: Database"
    docker compose exec -T backend php artisan migrate:status >/dev/null && ok "migrations run"
    docker compose exec -T backend php artisan db:show >/dev/null && ok "DB reachable"
    ;;
  2)
    echo "Phase 2: Auth & Identity"
    docker compose --profile test run --rm -T test-runner php artisan test --filter=Auth
    ;;
  3)
    echo "Phase 3: Media & Playlists"
    docker compose --profile test run --rm -T test-runner php artisan test --filter="Media|Playlists|Favorites"
    ;;
  4)
    echo "Phase 4: Search + Recommendations"
    docker compose --profile test run --rm -T test-runner php artisan test --filter="Search|Recommend"
    ;;
  5)
    echo "Phase 5: Device Ingestion"
    docker compose --profile test run --rm -T test-runner php artisan test --filter="Device|Replay|Dedup"
    ;;
  6|7|8|9)
    echo "Phase $PHASE: Frontend / E2E"
    docker compose --profile test run --rm -T frontend-build
    docker compose --profile test run --rm -T e2e-runner
    ;;
  10)
    echo "Phase 10: Observability + Degradation"
    docker compose --profile test run --rm -T test-runner php artisan test --filter="Monitoring|Degradation"
    ;;
  11)
    echo "Phase 11: Security Hardening"
    docker compose --profile test run --rm -T test-runner php artisan test --filter="Security|RateLimit|Purge"
    ;;
  12)
    echo "Phase 12: Final Integration"
    docker compose --profile test run --rm -T test-runner php artisan test
    docker compose --profile test run --rm -T frontend-build
    docker compose --profile test run --rm -T e2e-runner
    ;;
  *)
    echo "Unknown phase: $PHASE"; exit 2 ;;
esac

ok "phase $PHASE passed"
