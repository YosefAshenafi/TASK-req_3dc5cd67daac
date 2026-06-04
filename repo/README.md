# SmartPark Media Operations

Project Type: Fullstack Web Application

SmartPark Media Operations is an on-premises management system for parking facility operators. It combines a Vue.js 3 content experience (audio announcements, short clips, playlists, favorites, recommendations) for Regular Users with an admin console for Administrators and a device validation console for Field Technicians. The Laravel 11 backend handles offline-first device event ingestion from gates, cameras, and sensors.

---

## Architecture & Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.3 / Laravel 11 (Sanctum sessions) |
| Frontend | Vue.js 3 (Composition API, Pinia, Vue Router 4) |
| CSS | Tailwind CSS 3 |
| Database | MySQL 8 |
| Queue | Laravel database queue |
| Containers | Docker + Docker Compose v2 |
| Backend Tests | PHPUnit 11 via `php artisan test` |
| Frontend Tests | Vitest + Vue Test Utils |
| E2E Tests | Playwright |

---

## Project Structure

```
repo/
├── backend/           Laravel 11 API
│   ├── app/           Application code
│   ├── database/      Migrations, seeders, factories
│   ├── routes/        API route definitions
│   └── tests/         PHPUnit feature and unit tests
├── frontend/          Vue.js 3 SPA
│   ├── src/           Components, views, stores, router
│   └── tests/         Vitest unit tests
├── tests/
│   └── e2e/           Playwright end-to-end tests
├── db/                MySQL init scripts
├── docker-compose.yml
├── run_tests.sh
└── README.md
```

---

## Prerequisites

- Docker 24+ with Docker Compose v2
- No other host-local tooling required

---

## Running the Application

```bash
# From the repo/ directory:
docker-compose up --build -d
```

> Docker Compose V2 users may also run `docker compose up --build -d` (space, no hyphen) — both forms are equivalent.

Wait for services to be ready (~30 seconds on first run for DB migrations):

```bash
docker-compose logs -f backend
```

Access the application at: **http://localhost:3000**

> **Note:** Database migrations run automatically on container startup. Demo data (accounts, assets, playlists) is seeded automatically on the first startup when the database is empty — no manual seeding step is required.

To stop:

```bash
docker compose down
```

To reset data:

```bash
docker compose down -v
docker compose up --build -d
```

---

## Testing

All tests execute exclusively inside Docker containers — no host-local PHP, Node, or other runtime tooling is required or used. **Test containers and images must already contain all required dependencies**; no dependency installation is performed at runtime. `./run_tests.sh` builds all images first (baking in every dependency), then runs each suite against those pre-built images.

Run all test suites (PHPUnit + Vitest + Playwright):

```bash
./run_tests.sh
```

The final line of output will be:

```
passed=N failed=M
```

To run the backend suite individually:

```bash
# Backend PHP tests only
docker compose run --rm backend php artisan test --env=testing
```

---

## Seeded Credentials

Demo accounts are created automatically on first startup. No manual seeding is required.

| Role | Email | Username | Password |
|---|---|---|---|
| Administrator | `admin@smartpark.local` | `admin` | `Password123!` |
| Regular User | `user@smartpark.local` | `user` | `Password123!` |
| Field Technician | `tech@smartpark.local` | `tech` | `Password123!` |

Login uses **username** (not email address).

**Device API key** (for POST /api/device/events):

```
Authorization: Bearer smartpark-device-key-abc123
```

---

## Verification

After starting the application, verify key flows:

### API health check

```bash
curl http://localhost:3000/api/health
# Expected: {"status":"ok","timestamp":"..."}
```

### Login and get session

```bash
curl -c cookies.txt -X POST http://localhost:3000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"user","password":"Password123!"}'
# Expected: {"data":{"id":...,"username":"user","role":"user",...}}
```

### Fetch recommendations (use the session cookie from login)

```bash
curl -b cookies.txt -H "X-XSRF-TOKEN: $(grep XSRF cookies.txt | awk '{print $7}')" \
  http://localhost:3000/api/recommendations
# Expected: {"data":[{"id":...,"recommendation_reason":"..."},...]}
```

### Device event ingestion

```bash
curl -X POST http://localhost:3000/api/device/events \
  -H "Authorization: Bearer smartpark-device-key-abc123" \
  -H "Content-Type: application/json" \
  -d '{"device_id":1,"event_type":"gate_open","sequence":1,"event_payload":{}}'
# Expected: {"data":{"id":...,"status":"received"}}
```

### Web UI flow

1. Open **http://localhost:3000** in a browser.
2. Log in with `user` / `Password123!`.
3. Click **Browse All** → confirm asset cards load.
4. Change the sort to **Recommended** → confirm `recommendation_reason` text appears on cards.
5. **Click any asset card** → a detail drawer opens showing every field (description, tags, type, size, duration, status, plays), not just the title.
6. Click the **♥ heart** on a card (or the **Favorite** button in the drawer) → open **Favorites** and confirm the asset appears; toggle it off to remove it.
7. Navigate to **My Playlists** → create a playlist → confirm the 8-character share code appears on the card.
8. Log out and log in as `admin` / `Password123!` → open any asset's detail drawer → click **Edit**, change the **Title**, click **Save** → the detail view immediately shows the new value (regular users do not see the Edit control).
9. Open **Admin → Monitoring** → confirm health blocks and p95 latency render.

---

## Configuration

All configuration is provided via environment variables. The `entrypoint.sh` script generates secrets at runtime using `openssl rand`. No `.env` files are required or created.

Key environment variables (set in `docker-compose.yml` for local development):

| Variable | Default | Description |
|---|---|---|
| `APP_KEY` | auto-generated | Laravel application key |
| `DB_HOST` | `db` | MySQL host (Docker service name) |
| `DB_DATABASE` | `smartpark` | Database name |
| `DB_USERNAME` | `smartpark` | Database user |
| `DB_PASSWORD` | `smartpark` | Database password |
| `QUEUE_CONNECTION` | `database` | Queue driver |
| `SESSION_DRIVER` | `database` | Session storage |

---

## API Overview

The backend exposes a RESTful JSON API at `/api/`. Authentication uses Laravel Sanctum (session + CSRF cookie). See `backend/routes/api.php` for the full endpoint list.

Key endpoints:
- `POST /api/auth/login` — authenticate by username and create session
- `GET /api/assets` — search/filter/sort media library (tag filter via `tags[]` query params)
- `GET /api/assets/{id}` — fetch single asset (non-approved assets hidden from non-owners)
- `POST /api/playlists` — create playlist (auto-generates unique 8-char share code; returns 503 if all 10 retry attempts collide)
- `POST /api/playlists/redeem` — look up playlist by share code
- `POST /api/device/events` — ingest device events (Bearer API key; `device_id` required; `replay_audit_id` must reference a valid `device_replay_audits.audit_key` for this device; stored with relational FK `replay_audit_fk`)
- `POST /api/admin/device-replays` — create device replay audit record (admin only)
- `PATCH /api/admin/assets/{id}` — update an asset's title, description, or tags (admin only)
- `GET /api/recommendations` — personalized recommendations; each item includes `recommendation_reason` describing the affinity rationale (e.g., "Based on your favorites: Safety, Training")
- `GET /api/admin/monitoring` — system health including API error rate, device ingestion counts, and degradation window metrics (admin only)

---

## Recommendation Engine

`GET /api/recommendations` returns pre-computed scores ordered by relevance. Each item carries a `recommendation_reason` string derived from actual user data:

- **"Based on your favorites: Tag1, Tag2"** — when the recommended asset shares tags with assets the user has favorited. Up to 3 matching tags are listed.
- **"Based on your listening history"** — when there is play-history affinity but no favorite-tag overlap.
- **"Popular in your facility"** — when the engine is in fallback mode (Most Played).

---

## Recommendation Degradation Guard

The engine monitors two conditions over a **5-minute rolling window**:

| Condition | Threshold | Action |
|---|---|---|
| API p95 latency (recommendation queries) | > 800 ms | Disable engine for 5 minutes |
| Recommendation hit rate (plays of recommended items / total recommendations served) | < 10% | Disable engine for 5 minutes |

When either condition is met, `GET /api/recommendations` falls back to the **Most Played** list and includes `"fallback": true` in the response. The engine re-enables automatically after 5 minutes.

The `/api/admin/monitoring` endpoint exposes window-aligned metrics: `window_seconds` (300), `p95_latency_ms`, `hit_rate`, `p95_threshold_ms` (800), and `hit_rate_threshold` (0.10).
