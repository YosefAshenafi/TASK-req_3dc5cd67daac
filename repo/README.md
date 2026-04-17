# SmartPark Media Operations

Local-first, on-prem parking site media + device-ingestion platform.

- **Stack:** Vue 3 + TypeScript (SPA) · Laravel / PHP 8.3 (API) · MySQL 8 · Redis 7 · Local disk (media)
- **Runtime:** everything in Docker Compose. No host-installed toolchains.
- **Docs:** see [`../docs/design.md`](../docs/design.md), [`../docs/api-specs.md`](../docs/api-specs.md), [`../docs/questions.md`](../docs/questions.md).
- **Plan:** see [`PLAN.md`](PLAN.md) for phase-by-phase implementation with gates.

## Start in 3 commands

```bash
cp .env.example .env
docker compose up -d mysql redis backend nginx
open http://localhost:8080
```

## Dev profile (with hot-reload frontend and device gateway)

```bash
docker compose --profile dev --profile devices up -d
```

## Run tests

```bash
# Unit + API (PHP/Pest)
docker compose --profile test run --rm test-runner php artisan test

# Frontend unit (Vitest)
docker compose --profile dev run --rm frontend-dev npx vitest run

# End-to-end (Playwright)
docker compose --profile test up -d nginx backend mysql redis queue-worker
docker compose --profile test run --rm e2e-runner
```

## Per-phase gate

```bash
./scripts/check-phase.sh <phase-number>   # 0..12
```

## Directory layout

```
repo/
├── PLAN.md
├── docker-compose.yml
├── docker/                  # Dockerfiles, nginx, mysql, secrets
├── backend/                 # Laravel (scaffolded Phase 0)
├── frontend/                # Vue 3 + TS (scaffolded Phase 0)
├── device-gateway/inbox/    # bind-mounted file drop for sensors
├── api-tests/               # Pest HTTP tests
├── unit-tests/              # Pest + Vitest
├── e2e-tests/               # Playwright specs
└── scripts/
    └── check-phase.sh
```
