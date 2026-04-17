# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Stack

SmartPark Media Operations — local-first, on-premises parking media + device-ingestion system.

- **Backend:** Laravel 13 / PHP 8.3 (REST API, Horizon queues, scheduled jobs)
- **Frontend:** Vue 3 + TypeScript SPA (Vite, Vue Router, Pinia, Tailwind CSS 4)
- **Data:** MySQL 8.0 (primary), Redis 7 (cache/queues/sessions), local disk (`media-storage` volume)
- **Runtime:** Everything in Docker Compose — no host-installed PHP, Node, MySQL, or Redis.

## Starting the stack

```bash
# Core services (API + nginx)
docker compose up -d mysql redis backend nginx

# Add hot-reload dev server
docker compose --profile dev up -d

# Add device gateway
docker compose --profile devices up -d gateway

# Bring everything down
docker compose down
```

`backend/` and `frontend/` are bind-mounted, so edits on the host are immediately live inside containers.

The SPA (after build) and API are both served through nginx on **port 8090**:
- `/api/*` → PHP-FPM (backend:9000)
- Everything else → `frontend/dist/index.html`

During development use the Vite dev server on **port 5173** for hot-reload.

## Running tests

Tests are written during Phases 1–11 but only evaluated together in Phase 12. Phase gates (`check-phase.sh`) verify Docker/infrastructure only — they do not run test suites until Phase 12.

```bash
# Phase infrastructure gate
./scripts/check-phase.sh <0-12>

# Backend unit tests
docker compose --profile test run --rm test-runner php artisan test --testsuite=Unit

# Backend API (HTTP contract) tests — uses smartpark_test DB, refreshed per run
docker compose --profile test run --rm test-runner php artisan test --testsuite=Api

# Single backend test file
docker compose --profile test run --rm test-runner php artisan test --filter=EncryptedFieldCastTest

# Frontend unit tests (Vitest)
docker compose --profile dev run --rm frontend-dev npx vitest run

# E2E (Playwright) — backend must be up first
docker compose --profile test run --rm e2e-runner

# All suites (Phase 12)
./scripts/check-phase.sh 12
```

E2E tests run in two Playwright browser profiles: **kiosk** (Chromium 1024×1366, touch) and **desktop** (Chromium standard).

## Backend architecture

```
backend/
├── routes/api.php              # All REST routes (see role guards below)
├── app/
│   ├── Http/
│   │   ├── Controllers/        # Thin controllers — business logic in Services/
│   │   └── Middleware/
│   │       └── RoleMiddleware.php   # role:admin | role:technician
│   ├── Models/                 # 12 Eloquent models (see Data model below)
│   ├── Services/               # Business logic
│   ├── Jobs/                   # Async (Horizon): GenerateThumbnails, IndexAsset, etc.
│   ├── Casts/
│   │   └── EncryptedField.php  # AES field-level encryption via /run/secrets/field_key
│   └── Console/Commands/       # Artisan commands (gateway:run, field-keys:rotate, etc.)
├── config/
│   └── smartpark.php           # Custom config: circuit_breaker_threshold, media size caps
└── database/
    ├── migrations/             # 6 migration files (ordered — FKs must resolve in sequence)
    ├── factories/
    └── seeders/                # Seeds admin + user + technician accounts; feature_flags row
```

**Route access tiers:**
- Public: `GET /health`, `POST /auth/login`
- Authenticated (any role): search, assets (read), favorites, playlists, play history, recommendations
- Admin only: user CRUD, freeze/blacklist, asset delete, monitoring
- Admin + Technician: device event ingestion, device listing, replay

## Data model (key relationships)

```
users → favorites → assets
users → playlists → playlist_items → assets
users → play_history → assets
devices → device_events (unique: device_id + idempotency_key, 7-day dedup window)
devices → replay_audits
assets → asset_tags
assets → search_index (denormalized; populated by IndexAsset job)
assets → recommendation_candidates (scored; populated by GenerateRecommendationCandidates job)
feature_flags (single row: recommended_enabled; toggled by circuit breaker)
```

`User.email_enc` is stored encrypted via `EncryptedField` cast. Logs must never contain plaintext `password`, `email`, `token`, or `payload.plate` — enforced by `MaskSensitiveFields` Monolog processor.

## Frontend architecture

```
frontend/src/
├── router/index.ts         # Vue Router — guards redirect by role before each navigation
├── stores/
│   ├── auth.ts             # Current user, Bearer token (localStorage), login/logout
│   ├── player.ts           # Now-playing state, playback position
│   └── ui.ts               # Sidebar state, theme
├── services/api.ts         # Centralized Axios/fetch client — all HTTP calls go here
├── types/api.ts            # TypeScript DTOs mirroring every API response shape
├── views/                  # One component per route
└── components/             # Shared: AssetTile, ShareDialog, RedeemDialog, AppLayout
```

**Route → role mapping:**
| Path | Allowed roles |
|------|--------------|
| `/login` | public |
| `/search`, `/library`, `/favorites`, `/playlists`, `/now-playing` | all authenticated |
| `/admin/*` | admin |
| `/devices/*` | admin, technician |

All API calls go through `src/services/api.ts`. The Bearer token is read from the `auth` Pinia store, which persists to localStorage. CSRF cookies are managed by Sanctum SPA mode.

## Queued jobs & background workers

`queue-worker` runs `php artisan horizon` and processes all async work:

| Job | Trigger |
|-----|---------|
| `GenerateThumbnails` | Asset upload (160/480/960 px crops via GD/ffmpeg) |
| `IndexAsset` | Asset upload / update (populates `search_index`) |
| `MediaScanRequested` | Asset upload (stub hook for future on-prem AV scanner) |
| `GenerateRecommendationCandidates` | Scheduled — tag-cosine similarity against favorites |

`scheduler` runs `php artisan schedule:work`:
- Recommendation candidate generation
- Rolling-window circuit-breaker evaluator (every 30 s)
- `PurgeSoftDeletedUsers` (daily — cascades to playlists/favorites/history)

## Circuit breaker / degradation

When API p95 latency > 800 ms for 5 min **or** recommendation hit rate < 10 %, the scheduler flips `feature_flags.recommended_enabled = false`. The `GET /recommendations` and `GET /search?sort=recommended` endpoints fall back to `most_played` and set `X-Recommendation-Degraded: true`. The flag recovers after 15 min of healthy metrics or via the Admin monitoring reset button. Config in `config/smartpark.php`.

## Device ingestion

`POST /api/devices/events` accepts events with a required `X-Idempotency-Key`. The unique index `(device_id, idempotency_key)` enforces deduplication within a 7-day window. Response statuses:
- `accepted` (200) — first occurrence
- `duplicate` (200) — already stored
- `out_of_order` (202) — sequence gap, reconciliation queued
- `too_old` (410) — beyond 7-day window

The `gateway` service buffers up to 10 000 events in SQLite when the backend is unreachable, retransmitting with exponential backoff (1 s → 300 s max). File-drop ingestion watches `device-gateway/inbox/*.json` (bind-mounted into the container).

## Test layout

```
api-tests/     # Pest HTTP tests — Auth/, Media/, Playlists/, Search/, Devices/, Monitoring/
unit-tests/    # backend/ (Pest), frontend/ (Vitest)
e2e-tests/     # Playwright TS — auth/, library/, favorites-and-playlists/, admin/, devices/
```

API tests use `RefreshDatabase` and hit `smartpark_test` (separate DB in compose). Each new feature must add at least one test in the tier that owns its risk (unit for algorithms, API for contracts, E2E for user flows).

## Implementation phases

See `PLAN.md` for the full 13-phase roadmap (Phases 0–12). Infrastructure gates are in `scripts/check-phase.sh`. All test suites are evaluated together in Phase 12 — earlier phases only check Docker/service health.
