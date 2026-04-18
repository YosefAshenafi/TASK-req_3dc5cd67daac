# SmartPark Media Operations

Local-first, on-prem parking site media + device-ingestion platform.

- **Stack:** Vue 3 + TypeScript (SPA) · Laravel / PHP 8.3 (API) · MySQL 8 · Redis 7 · Local disk (media)
- **Runtime:** Everything in Docker Compose — no host-installed toolchains required.
- **Architecture:** see [`CLAUDE.md`](CLAUDE.md) for stack, data model, queues, circuit breaker, and device ingestion notes.
- **Plan:** see [`PLAN.md`](PLAN.md) for phase-by-phase implementation with gates.

---

## Quick start — single command

```bash
docker compose up
```

That's it. Docker Compose will:

1. Start **MySQL 8** and **Redis 7**
2. Build and start the **Laravel backend** — automatically runs `composer install`, database migrations, and seeds default accounts
3. Start the **queue worker** (Laravel Horizon) and **scheduler**
4. Start **Nginx** (serves API + SPA on port 8090)

Once all containers are healthy, open:

```
http://localhost:8090
```

> **Note:** On the very first run, image builds and Composer installs take a few minutes. Subsequent starts are fast.

### Default credentials (local / development only)

The seeder creates three demo accounts with the well-known password `password` **only**
when `APP_ENV` is `local`, `development`, or `testing`. In any other environment the
seeder skips account creation unless the operator sets `SEED_DEFAULT_ACCOUNTS=true`; in
that case each account's password is taken from `ADMIN_BOOTSTRAP_PASSWORD`,
`USER_BOOTSTRAP_PASSWORD`, `TECH_BOOTSTRAP_PASSWORD`, or falls back to a random 32-char
string that is written to `storage/app/bootstrap-secrets/<username>.txt` (mode 0600) so
the operator can read it out-of-band and rotate it via the admin console. Bootstrap
passwords are never emitted to the application log.

| Role         | Username | Password (local/dev only) |
|--------------|----------|---------------------------|
| Admin        | `admin`  | `password`                |
| Regular user | `user1`  | `password`                |
| Technician   | `tech1`  | `password`                |

For production deployments, create accounts explicitly with `php artisan tinker` or an
operator-run migration — never with the well-known password above.

### Database connection (host access)

| Setting  | Value           |
|----------|-----------------|
| Host     | `127.0.0.1`     |
| Port     | `3306`          |
| Database | `smartpark`     |
| Username | `smartpark`     |
| Password | `smartpark_dev` |

---

## Dev profile (hot-reload frontend + device gateway)

```bash
docker compose --profile dev --profile devices up
```

- Vite dev server with HMR: `http://localhost:5173`
- Device gateway watches `device-gateway/inbox/*.json`

---

## Running tests

### All suites (PHP + Vitest + Playwright)

```bash
./run_tests.sh
```

`run_tests.sh` starts core services automatically, runs **every suite inside Docker** (`test-runner`, `vitest-runner`, `frontend-build`, `e2e-runner`), and prints a summary:

```
  Suite                           Status  Tests       Coverage
  ──────────────────────────────  ──────  ──────────  ────────
  Backend (PHP / Pest)            PASS    45          82.50%
  Frontend Unit (Vitest)          PASS    12          76.00%
  E2E (Playwright)                PASS    18          N/A
  ──────────────────────────────  ──────  ──────────
  TOTAL                                  75 tests

  ✔  All active test suites PASSED.
```

### Selective runs

```bash
./run_tests.sh --backend-only    # PHP/Pest tests only
./run_tests.sh --frontend-only   # Vitest + Playwright
./run_tests.sh --no-e2e          # skip Playwright E2E
```

### After pulling Docker/PHP changes

If backend tests report **“No code coverage driver available”**, rebuild the PHP image so the **PCOV** extension is present:

```bash
docker compose build backend queue-worker scheduler test-runner
```

### Manual per-suite commands

All of these use Docker Compose (no local PHP/Node/Playwright install required).

```bash
# Backend unit + feature (PHP/Pest) with coverage
docker compose --profile test run --rm test-runner \
    php artisan test --coverage-text

# Single test file
docker compose --profile test run --rm test-runner \
    php artisan test --filter=EncryptedFieldCastTest

# Frontend unit (Vitest + coverage) — test profile
docker compose --profile test run --rm vitest-runner

# Production build for nginx (E2E serves ./frontend/dist via port 8090)
docker compose --profile test run --rm frontend-build

# E2E (Playwright — requires `docker compose up` so nginx + API are running, and dist/ built as above)
docker compose --profile test run --rm e2e-runner
```

Interactive Vitest watch (optional, dev profile):

```bash
docker compose --profile dev run --rm frontend-dev npx vitest
```

---

## Per-phase infrastructure gate

```bash
./scripts/check-phase.sh <phase-number>   # 0 .. 12
```

---

## Directory layout

```
repo/
├── docker-compose.yml
├── run_tests.sh             ← runs all test suites, prints summary
├── docker/
│   ├── php/
│   │   ├── Dockerfile       ← PHP 8.3-fpm + PCOV + entrypoint
│   │   └── entrypoint.sh    ← auto composer install / migrate / seed
│   ├── frontend/Dockerfile
│   ├── nginx/default.conf
│   ├── mysql/
│   │   ├── my.cnf
│   │   └── init/            ← SQL scripts run on first MySQL start
│   └── secrets/
├── backend/                 ← Laravel API (executable tests live in `backend/tests/`)
├── frontend/                ← Vue 3 + TS SPA (executable tests live in `frontend/src/tests/` and `frontend/e2e/`)
├── device-gateway/inbox/    ← file-drop for device events
├── api-tests/               ← Guide only — see `backend/tests/Feature/` for the real Pest HTTP tests
├── unit-tests/              ← Guide only — see `backend/tests/Unit/` and `frontend/src/tests/unit/` for real tests
├── e2e-tests/               ← Guide only — see `frontend/e2e/` for the real Playwright specs
└── scripts/
    └── check-phase.sh
```

> **Heads up on test locations.** The top-level `api-tests/`, `unit-tests/`, and
> `e2e-tests/` directories contain narrative guides and suite READMEs; they do **not**
> hold the executable suites themselves. The actual tests run by `./run_tests.sh` and
> CI live under `backend/tests/` (Pest) and `frontend/src/tests/` + `frontend/e2e/`
> (Vitest + Playwright).
