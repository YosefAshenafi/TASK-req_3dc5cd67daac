# SmartPark Media Operations — Implementation Plan

This plan is scoped strictly to the original prompt and cross-checked against:

- `../metadata.json` — stack (Vue.js + TypeScript, Laravel/PHP, MySQL primary, Redis cache/queues, local disk).
- `../docs/questions.md` — 9 major ambiguities, assumptions, and agreed solutions.
- `../docs/design.md` — components, data model, key flows.
- `../docs/api-specs.md` — REST contract the frontend and gateway consume.

Every phase has **Deliverables** and **Docker notes** (all work happens inside the compose stack). Tests are written during each phase but **only evaluated in Phase 12**. Phase gates check infrastructure only — no test suite runs until the final phase.

---

## Ground rules

1. **Everything runs in Docker.** No host-installed PHP, Node, MySQL, or Redis is required or permitted. `docker compose up` is the one command to start, `docker compose down` to stop.
2. **No internet dependencies at runtime.** Images are pulled once during the build; application behavior must not reach the internet.
3. **Test folders live at repo root** (`api-tests/`, `unit-tests/`, `e2e-tests/`) and grow phase by phase. Every new feature adds at least one test in the tier that owns its risk.
4. **Tests are written during each phase but run only in Phase 12.** Phase gates check Docker/infrastructure only.

---

## Repository layout after scaffolding

```
repo/
├── docker-compose.yml            # one-command local stack
├── .env.example                  # template (copy to .env)
├── .gitignore
├── docker/
│   ├── php/Dockerfile            # Laravel + Horizon + queue worker
│   ├── frontend/Dockerfile       # Vite dev + Playwright runner
│   ├── nginx/default.conf
│   ├── mysql/my.cnf
│   └── secrets/field_encryption.key
├── scripts/
│   └── check-phase.sh            # runs the gate for phase N
├── backend/                      # Laravel (scaffolded in Phase 0)
├── frontend/                     # Vue 3 + TypeScript (scaffolded in Phase 0)
├── device-gateway/
│   └── inbox/                    # file-drop folder bind-mounted into the gateway container
├── api-tests/                    # Pest HTTP tests
├── unit-tests/                   # Pest + Vitest unit tests
└── e2e-tests/                    # Playwright specs
```

---

## Phase 0 — Scaffolding & Docker baseline

**Goal:** a reproducible, container-only dev environment with empty Laravel and Vue projects wired to MySQL, Redis, and nginx. No business logic yet.

**Deliverables**
- `docker-compose.yml` with services: `mysql`, `redis`, `backend` (PHP-FPM), `queue-worker` (Horizon), `scheduler`, `nginx`, `frontend-dev` (dev profile), `gateway` (devices profile), `test-runner` + `e2e-runner` (test profile).
- PHP 8.3 image with `pdo_mysql`, `redis`, `gd`, `bcmath`, `pcntl`, `intl`, `opcache`, `ffmpeg` CLI for probes, Composer.
- Node 20 image with Vite and Playwright.
- `backend/` scaffolded with `laravel/laravel`, `laravel/sanctum`, `laravel/horizon`, `intervention/image`, `pestphp/pest`.
- `frontend/` scaffolded with `create-vue@latest --ts --router --pinia`, plus Vitest + Playwright + Tailwind or Vuetify.
- `.env.example`, `.gitignore`, `README.md` with "Start in 3 commands."
- `scripts/check-phase.sh` wired.

**Docker notes**
- `backend/` and `frontend/` are bind-mounted so edits on the host flow immediately into containers.
- `media-storage` volume holds uploaded files (decoupled from source tree).
- `field_encryption.key` mounted as a Docker secret at `/run/secrets/field_key`.

**Phase gate (`./scripts/check-phase.sh 0`)**
- `docker compose config` validates.
- `docker compose up -d mysql redis backend nginx` brings all four healthy.
- `curl http://localhost:8080/api/health` → `200 {"status":"ok"}` (one trivial route).
- `docker compose run --rm test-runner php -v` and `vitest --version` succeed.
- Three test folders exist with their README seed files.

---

## Phase 1 — Database schema & models

**Goal:** MySQL schema for every table in `design.md §4`, Eloquent models, and an encrypted-field cast for sensitive columns.

**Deliverables**
- Migrations (in this order, so foreign keys resolve):
  1. `users` (with `role`, `email_enc`, `frozen_until`, `blacklisted_at`, soft deletes).
  2. `assets`, `asset_tags`.
  3. `playlists`, `playlist_items`, `playlist_shares`.
  4. `favorites`, `play_history`.
  5. `recommendation_candidates`, `feature_flags`, `search_index`.
  6. `devices`, `device_events` (unique index on `(device_id, idempotency_key)`), `replay_audits`.
- Eloquent models + factories + seeders (admin, user, technician).
- `App\Casts\EncryptedField` wrapping the field-key secret.
- `feature_flags` seeded with `recommended_enabled=true`.

**Unit tests (`unit-tests/backend/`)**
- `EncryptedFieldCastTest` — round-trips email; log output does not contain plaintext.
- Factory smoke tests confirming every relationship.

**Phase gate (`./scripts/check-phase.sh 1`)**
- `php artisan migrate:fresh --seed` inside the backend container succeeds.
- `php artisan db:show smartpark` lists all expected tables.
- *(Unit tests written; evaluated in Phase 12.)*

---

## Phase 2 — Authentication, roles, account lifecycle

**Goal:** endpoints from `api-specs.md §1` and `§2`; role-based middleware; freeze/blacklist/purge.

**Deliverables**
- Sanctum SPA auth; HTTP-only cookie + CSRF.
- `POST /api/auth/login`, `/logout`, `GET /me`.
- Admin user CRUD (`POST /users`, `GET /users`, `PATCH freeze/unfreeze/blacklist`, `DELETE /users/{id}` soft delete).
- `role:admin`, `role:technician` middleware.
- `PurgeSoftDeletedUsers` job scheduled daily.
- 5-failure/15-min login lockout via Redis limiter.

**API tests (`api-tests/Auth/`, `api-tests/Users/`)**
- Login success + wrong-password decrement + lockout after 5.
- Frozen user returns `423` with `frozen_until`.
- Blacklisted user returns generic `401`.
- Role guard: `user` hitting `/admin/*` returns `403`.
- Soft-deleted + 31-day clock-forward → record is purged by the scheduled job.

**Phase gate (`./scripts/check-phase.sh 2`)**
- Auth and User management endpoints reachable (manual `curl` smoke).
- *(API tests written; evaluated in Phase 12.)*

---

## Phase 3 — Media assets, favorites, playlists, sharing

**Goal:** the Librarian/Player core from `api-specs.md §3–§5`.

**Deliverables**
- `POST /api/assets` (multipart) with:
  - Size caps (25 MB images/docs, 250 MB video).
  - MIME sniff + magic-byte fingerprint + SHA-256 (`fingerprint_sha256`).
  - Format allowlist: JPEG / PNG / PDF / MP3 / MP4.
  - Queued jobs: `GenerateThumbnails` (160/480/960 px), `IndexAsset`, `MediaScanRequested` stub event.
- `GET /api/assets/{id}`, `DELETE /api/assets/{id}` blocked with `409` when referenced by any playlist.
- Favorites `PUT/DELETE` idempotent endpoints.
- Playlists CRUD.
- Share codes: 8-char unambiguous alphanumeric, 24 h TTL, 5/h/user rate-limit (Redis).
- Redeem endpoint clones playlist rows into recipient's library.

**Unit tests**
- `MediaValidatorTest` — declared MP4 with PNG bytes → `magic_mismatch`.
- Share code generator never emits `0/O/1/I`.

**API tests**
- Upload happy path returns asset with `status: processing`; after queue drain, thumbnails exist.
- Double-submit with same `X-Idempotency-Key` returns original asset.
- Delete-referenced returns `409` + `playlist_ids`.
- Share → redeem on a different user creates a distinct playlist with identical items.
- Redemption refused when owner is blacklisted.

**Phase gate (`./scripts/check-phase.sh 3`)**
- Upload endpoint reachable; thumbnail files appear on the `media-storage` volume after queue drain.
- *(Unit + API tests written; evaluated in Phase 12.)*

---

## Phase 4 — Search, recommendations, play history

**Goal:** search + rank + reasons + degradation flag from `api-specs.md §3`, §7.

**Deliverables**
- `search_index` table populated by `IndexAsset` (title + body tokenization, tags, weighted).
- `GET /api/search?q=&tags[]=&duration_lt=&recent_days=&sort=` returning ranked results.
- Sort modes: `most_played`, `newest`, `recommended`.
- `GenerateRecommendationCandidates` scheduled job (tag-cosine similarity against user favorites + popularity prior).
- `reason_tags` = top 3 overlapping tags; rendered by the SPA later.
- `POST /api/assets/{id}/play` appends `play_history`.
- `GET /api/history`, `GET /api/now-playing`, `GET /api/recommendations`.

**Unit tests**
- `RecommendationScorerTest` — deterministic ordering given fixed favorites.
- Tokenizer strips punctuation and folds case.

**API tests**
- Full-text query surfaces exact title matches before body matches (per ranking rule).
- Duration and recency filters exclude correctly.
- `sort=recommended` returns reason tags; when `feature_flags.recommended_enabled=false`, results fall back to `most_played` and header `X-Recommendation-Degraded: true` is set.

**Phase gate (`./scripts/check-phase.sh 4`)**
- `GET /api/search?q=test` returns a `200` JSON response.
- *(Unit + API tests written; evaluated in Phase 12.)*

---

## Phase 5 — Device ingestion, dedup, replay

**Goal:** `api-specs.md §8` plus the offline gateway and 10 k buffer from `questions.md §4`, `§6`.

**Deliverables**
- `POST /api/devices/events` with required `X-Idempotency-Key`; unique `(device_id, idempotency_key)` ensures dedup.
- Statuses: `accepted` / `duplicate` (200) / `out_of_order` (202, reconciliation queued) / `too_old` (410, > 7 days).
- Per-device `last_sequence_no` advanced to max.
- `POST /api/devices/{id}/replay` creates `replay_audits` row; re-emits events to downstream consumers without duplicating side effects.
- `device-gateway` container:
  - Accepts HTTP + file-drop (`/var/spool/smartpark/inbox/*.json`).
  - SQLite FIFO buffer capped at 10 000 events.
  - Exponential backoff 1s → 2s → 4s → … → 300 s.
  - Drops FIFO with warning when buffer is full.

**Unit tests**
- `DeviceEventDeduplicatorTest` — repeated idempotency key is a no-op.
- Out-of-order sample sets `is_out_of_order=true`.

**API tests**
- Buffer drain scenario: 50 events submitted while backend is stopped; after backend comes up they arrive in order and none duplicates.
- Replay creates audit row; subsequent submissions of the same range return `duplicate`.

**Phase gate (`./scripts/check-phase.sh 5`)**
- `docker compose --profile devices up gateway` + backend down → buffer grows; backend up → drains without duplicates (manual verification).
- *(Unit + API tests written; evaluated in Phase 12.)*

---

## Phase 6 — Frontend foundations (auth, routing, shared types)

**Goal:** a Vue 3 + TypeScript SPA that logs in, enforces role-based routing, and shares types with the API.

**Deliverables**
- Vite + TS strict mode, ESLint + Prettier.
- Pinia stores (`auth`, `ui`) with typed state.
- `src/types/api.ts` with DTOs mirroring every response from `api-specs.md`.
- Axios/Fetch client that reads the Sanctum CSRF cookie.
- Routes: `/login`, `/search`, `/library`, `/favorites`, `/playlists`, `/now-playing`, `/admin/*`, `/devices/*`.
- `RouterGuard` redirects by role.
- Kiosk-friendly base layout (44 px tap targets, large type, offline-looking header badge).

**Unit tests (`unit-tests/frontend/`)**
- Auth store: login → me → logout.
- Route guard blocks `user` from `/admin`.

**E2E (`e2e-tests/auth/login-by-role.spec.ts`)**
- Each role logs in and lands on its designated landing page.

**Phase gate (`./scripts/check-phase.sh 6`)**
- `http://localhost:5173/login` renders and the Pinia auth store is wired (no console errors on load).
- *(Unit + E2E tests written; evaluated in Phase 12.)*

---

## Phase 7 — Frontend: Library, Search, Favorites, Playlists, Now Playing

**Goal:** the Regular User experience.

**Deliverables**
- **Search page:** query box (debounced 300 ms), filter chips (tags, duration `<2 min`, last 30 days), sort toggle (Most Played / Newest / Recommended). Shows `Reason` chips under recommended items.
- **Degradation indicator:** subtle badge when `X-Recommendation-Degraded: true` is returned.
- **Library page:** all assets paginated; thumbnails from the multi-size crops.
- **Asset tile:** tap to play, heart to favorite, `+` to add to playlist.
- **Favorites page:** list + remove.
- **Playlists page:** create, rename, reorder (drag/keyboard), delete.
- **Share dialog:** shows on-screen code + countdown; Revoke button.
- **Redeem dialog:** code-entry keypad (tablet-friendly).
- **Now Playing panel:** current, up-next, recent plays, on-screen reasons.

**E2E (`e2e-tests/library/`, `favorites-and-playlists/`, `now-playing/`)**
- `search-filter-sort.spec.ts` — all sort modes return expected order.
- `favorite-and-unfavorite.spec.ts`.
- `playlist-build-edit.spec.ts`.
- `share-redeem-on-second-kiosk.spec.ts` — user A generates code, browser context B enters it, sees cloned playlist.
- `recommended-degradation.spec.ts` — backend forces the breaker → badge appears.

**Phase gate (`./scripts/check-phase.sh 7`)**
- Search, Library, Playlists, and Now Playing views render without JS errors.
- *(E2E specs written; evaluated in Phase 12.)*

---

## Phase 8 — Admin Console

**Goal:** operations + content governance UI.

**Deliverables**
- **User management:** list w/ filters (role/status), create user, freeze (duration picker, default 72 h), blacklist, soft-delete.
- **Upload review:** drag-and-drop upload, per-file progress, per-file error rows surfacing reason codes from the validator.
- **Asset detail:** shows which playlists reference it; `Delete` disabled with tooltip when referenced.
- **Monitoring dashboard:** API p95/error rate, queue backlog, device ingestion health (dedup rate, out-of-order count, last-seen per device), storage free bytes, feature-flag state + manual reset button.
- **Dashboards auto-refresh every 10 s** via `/api/monitoring/status`.

**E2E (`e2e-tests/admin/`)**
- `user-freeze-blacklist.spec.ts`.
- `upload-validation.spec.ts` (disguised-executable rejection).
- `delete-referenced-asset.spec.ts` (`409` surfaces `playlist_ids`).
- `monitoring-dashboard.spec.ts` (numbers tick after injected load).

**Phase gate (`./scripts/check-phase.sh 8`)**
- Admin Console routes render; monitoring dashboard shows live data.
- *(E2E specs written; evaluated in Phase 12.)*

---

## Phase 9 — Field Technician Device Console

**Goal:** technician-facing UI for ingestion diagnostics.

**Deliverables**
- **Device list:** rows with last-seen, online/offline badge, dedup rate, out-of-order count.
- **Event inspector:** timeline filter by status (`accepted`/`duplicate`/`out_of_order`/`too_old`), sequence_no column, `payload` JSON drawer.
- **Replay panel:** pick `since/until` sequence range, reason textarea, confirmation dialog, audit trail viewer.
- **Buffered-transmission indicator:** shows when the gateway is buffering (reads a gateway health endpoint).

**E2E (`e2e-tests/devices/`)**
- `duplicate-and-out-of-order.spec.ts`.
- `buffered-retransmission.spec.ts`.
- `replay-with-audit.spec.ts`.

**Phase gate (`./scripts/check-phase.sh 9`)**
- Device Console renders; event timeline lists events and replay panel submits without error.
- *(E2E specs written; evaluated in Phase 12.)*

---

## Phase 10 — Observability & automatic degradation

**Goal:** close the loop on the SLO from `design.md §7` and `questions.md §7`.

**Deliverables**
- API middleware records latency samples (histogram in Redis).
- Rolling-window evaluator (every 30 s, scheduled) that:
  - Trips `recommended_enabled=false` when p95 > 800 ms for 5 min **OR** hit rate < 10 %.
  - Recovers automatically after 15 min of healthy metrics **or** on admin reset.
- `AdminAlertCreated` event → toast + entry in the Monitoring page.
- Storage health (disk free) included in `/api/monitoring/status`.

**API + Unit tests**
- `CircuitBreakerTest` (unit) — state machine on synthetic samples.
- `DegradationFlagTest` (api) — force latency samples → flag flips; recovery path verified.

**Phase gate (`./scripts/check-phase.sh 10`)**
- Circuit-breaker flag flips to `false` when synthetic latency samples are injected via `tinker`.
- *(Unit + API tests written; evaluated in Phase 12.)*

---

## Phase 11 — Security hardening & purge

**Goal:** enforce the controls in `design.md §6` and `questions.md §9`.

**Deliverables**
- Monolog `MaskSensitiveFields` processor (config-driven list: `password`, `email`, `token`, `payload.plate`).
- Argon2id hashing verified (Laravel default).
- Field-key rotation command `php artisan field-keys:rotate` with 30-day envelope grace.
- Rate limits: 60/min writes per user, 5/h share codes, 5/15 min login attempts.
- `PurgeSoftDeletedUsers` scheduled daily; cascades to playlists/favorites/play_history; anonymizes device_events FK only.
- Log verification: secrets never appear in `storage/logs`.

**Unit + API tests**
- `SensitiveLogMaskingTest` — logs after login do not contain password.
- `FieldKeyRotationTest` — old envelopes still decrypt within grace, break after.
- `RateLimitTest` (API) — 6th share-code request in an hour returns `429` with `Retry-After`.

**Phase gate (`./scripts/check-phase.sh 11`)**
- `storage/logs/laravel.log` contains no plaintext passwords after a login attempt.
- *(Unit + API tests written; evaluated in Phase 12.)*

---

## Phase 12 — Full test evaluation, production build, runbook

**Goal:** run every test suite written during Phases 1–11, produce the production build, and document the runbook.

**Deliverables**
- `docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d` produces the production stack (SPA built, PHP opcache warmed, Horizon supervised).
- `README.md` top-level **Start / Stop / Backup / Restore / Rotate keys / Trigger replay** runbook.
- Backup cron (`docker compose exec mysql mysqldump …` → `/var/backups/smartpark`, 14-day retention).
- Smoke test script `./scripts/smoke.sh` that:
  - Logs in as each role.
  - Uploads a valid MP3.
  - Runs a search.
  - Generates and redeems a share code.
  - Submits a deduplicated device event.

**Test evaluation (all suites run here for the first time)**
- `unit-tests/` — Pest PHP unit tests + Vitest frontend tests (Phases 1–6, 10–11).
- `api-tests/` — Pest HTTP integration tests (Phases 2–5, 10–11).
- `e2e-tests/` — Playwright specs in kiosk + desktop profiles (Phases 6–9, 10).

**Exit criteria (`./scripts/check-phase.sh 12`)**
- Full unit + api + e2e suites green.
- `./scripts/smoke.sh` green end-to-end.

---

## UI element inventory (for Phases 6–9)

Pulled from the prompt and api-specs so nothing is missed during frontend phases:

| Area            | Elements                                                                                 |
|-----------------|------------------------------------------------------------------------------------------|
| Login           | Username, Password, Submit, Error banner, Lockout countdown                             |
| Top bar         | Role badge, Search icon, Notifications bell, Session menu (logout, change password)     |
| Search          | Query input, Tag chip multi-select, Duration filter (`<2m`, `<5m`, any), Recency filter (30/90/all), Sort toggle (Most Played/Newest/Recommended), Degraded badge |
| Asset tile      | Thumbnail (160/480/960), Title, Duration, Tags, Play button, Favorite heart, Add-to-playlist |
| Now Playing     | Current item, Progress, Up-next queue, Recent plays list, "Based on your favorites: …" |
| Favorites       | List, Unfavorite action                                                                  |
| Playlists       | Create button, Name field, Item list (drag/reorder/remove), Save, Delete, Share, Revoke |
| Share dialog    | Code block (copy), TTL countdown, Regenerate, Close                                     |
| Redeem dialog   | Code keypad, Submit, Error states (expired/unknown/blacklisted)                         |
| Admin users     | Search, Role/Status filters, Create, Freeze (duration picker), Blacklist, Soft-delete   |
| Admin uploads   | Drop zone, Progress bars, Per-file error rows, Metadata form (title/tags/description)   |
| Admin monitoring| API panel (p95/error), Queues panel, Devices panel, Storage panel, Feature flag row w/ Reset |
| Device console  | Device table (status badge, last-seen, dedup%), Event timeline (status chips), Replay panel (range picker, reason, confirm), Gateway buffering indicator |

Every element above must be exercised by at least one Playwright selector in `e2e-tests/`.

---

## Phase completion matrix

Tests are **written** (W) during each phase and **evaluated** (E) only in Phase 12.

| Phase | Unit  | API   | E2E   | Infra gate |
|-------|:-----:|:-----:|:-----:|:----------:|
| 0     |  —    |  —    |  —    |     ✓      |
| 1     |  W    |  —    |  —    |     ✓      |
| 2     |  W    |  W    |  —    |     ✓      |
| 3     |  W    |  W    |  —    |     ✓      |
| 4     |  W    |  W    |  —    |     ✓      |
| 5     |  W    |  W    |  —    |     ✓      |
| 6     |  W    |  —    |  W    |     ✓      |
| 7     |  —    |  —    |  W    |     ✓      |
| 8     |  —    |  —    |  W    |     ✓      |
| 9     |  —    |  —    |  W    |     ✓      |
| 10    |  W    |  W    |  —    |     ✓      |
| 11    |  W    |  W    |  —    |     ✓      |
| 12    |  E ✓  |  E ✓  |  E ✓  |     ✓      |

The build is complete only when every Phase 12 test suite is green, `./scripts/check-phase.sh 12` passes, and `./scripts/smoke.sh` runs end-to-end inside the containers without host-side intervention.
