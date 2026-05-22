# SmartPark System Design

## 1. System Overview

SmartPark is a full-stack media operations management platform for parking
facilities. It combines:

- A **consumer-grade content experience** — kiosk/tablet-optimised Vue.js
  frontend through which drivers and onsite staff browse audio announcements,
  short video clips, and documents; build playlists; and share them on-site
  via copyable codes.
- An **offline-first device event pipeline** — HTTP endpoints that ingest
  idempotent, sequenced events from parking hardware (gates, cameras,
  geomagnetic sensors) and buffer/replay them safely when connectivity is
  intermittent.
- An **admin and operations console** — content moderation, user account
  management, device health dashboards, and a local monitoring page that
  surfaces metrics with no external telemetry.

---

## 2. High-Level Architecture

```
┌───────────────────────────────────────────────────────┐
│                     Docker network: app                │
│                                                        │
│  ┌──────────────────────────────────────────────────┐ │
│  │  frontend (nginx:alpine)                         │ │
│  │  • Serves Vue.js SPA on port 80                  │ │
│  │  • Reverse-proxies /api/* → backend:9000          │ │
│  └──────────────────────────┬───────────────────────┘ │
│                             │ HTTP                     │
│  ┌──────────────────────────▼───────────────────────┐ │
│  │  backend (php:8.3-fpm-alpine + nginx)            │ │
│  │  • nginx listens on :9000                        │ │
│  │  • FastCGI → php-fpm on 127.0.0.1:9001           │ │
│  │  • Laravel 11 REST API                           │ │
│  │  • Session auth (Sanctum) + Device Bearer auth   │ │
│  └────────────┬─────────────────────────────────────┘ │
│               │ MySQL                                  │
│  ┌────────────▼─────────────────────────────────────┐ │
│  │  db (mysql:8.0)                                  │ │
│  │  • Primary data store: users, assets, playlists, │ │
│  │    play history, device events, sessions, jobs   │ │
│  └──────────────────────────────────────────────────┘ │
│                                                        │
│  ┌──────────────────────────────────────────────────┐ │
│  │  queue (same image as backend)                   │ │
│  │  `php artisan queue:work`                        │ │
│  │  • GenerateThumbnailsJob                         │ │
│  │  • IndexAssetJob                                 │ │
│  │  • ComputeUserRecommendationsJob                 │ │
│  │  • FlushBufferedDeviceEventsJob                  │ │
│  └──────────────────────────────────────────────────┘ │
│                                                        │
│  ┌──────────────────────────────────────────────────┐ │
│  │  scheduler (same image as backend)               │ │
│  │  `php artisan schedule:run` every 60 s           │ │
│  └──────────────────────────────────────────────────┘ │
└───────────────────────────────────────────────────────┘
          │ port 3000:80
    ┌─────┴─────┐
    │  Browser  │  (kiosk, front-desk desktop)
    └───────────┘

Parking devices (gates, cameras, sensors)
    │ Bearer token  POST /api/device/events
    └──────────────────────────────────────▶ backend
```

**External exposure:** Only port `3000` is published to the host.
The database and backend are reachable only within the Docker `app` network.

---

## 3. Technology Stack

| Layer | Technology |
|-------|-----------|
| Frontend SPA | Vue 3 (Composition API), Pinia, Vue Router, Axios |
| Frontend build | Vite, node:20-alpine |
| Frontend server | nginx:alpine (port 80 inside container) |
| Backend framework | Laravel 11 (PHP 8.3) |
| Backend server | nginx + php-fpm (supervisord, port 9000 internal) |
| Auth | Laravel Sanctum (session-based for browser; SHA-256 Bearer for devices) |
| Database | MySQL 8.0 |
| Queue driver | `database` (jobs table) |
| Cache driver | `database` (cache table) |
| Session driver | `database` (sessions table) |
| File storage | Local disk (`storage/app/media`) |
| Container runtime | Docker Compose |

---

## 4. Main Components

### 4.1 Frontend (Vue 3 SPA)

**Router (`src/router/index.js`)**

| Path | Component | Auth |
|------|-----------|------|
| `/login` | `LoginView` | Public |
| `/library` | `LibraryView` | `requiresAuth` |
| `/favorites` | `FavoritesView` | `requiresAuth` |
| `/playlists` | `PlaylistsView` | `requiresAuth` |
| `/playlists/:id` | `PlaylistDetailView` | `requiresAuth` |
| `/history` | `HistoryView` | `requiresAuth` |
| `/admin/*` | Admin console | `requiresRole: admin` |
| `/technician/*` | Tech console | `requiresRole: technician` |

Route guards check `useAuthStore().user` and redirect to `/login` when
unauthenticated, or to `/library` when role is insufficient.

**Pinia Stores**

| Store | Responsibility |
|-------|---------------|
| `useAuthStore` | Session state, login/logout, `isAdmin`, `isTechnician` computed |
| `useSearchStore` | Asset search filters, paginated results, full-text + tag + sort state |
| `usePlaylistStore` | CRUD for playlists and playlist items, share-code redeem |
| `useNowPlayingStore` | Play history fetch, record play event |

**Key Components**

| Component | Purpose |
|-----------|---------|
| `AssetCard` | Tile for a single media asset; play button, favorite toggle, add-to-playlist |
| `NowPlayingPanel` | Recent plays and session history shown in the sidebar |
| `CreatePlaylistModal` | Modal form — name + description |
| `RedeemCodeModal` | Modal form — 8-char share code input |
| `StatCard` | Reusable dashboard statistic tile |

### 4.2 Backend (Laravel 11)

**Middleware chain (all API routes)**

1. `EncryptCookies` → `AddQueuedCookiesToResponse` → `StartSession` (session management)
2. `StructuredLogger` — emits JSON log lines for every request/response (no PII in plain text)
3. `auth:sanctum` — session authentication (browser) or Sanctum token
4. `RequireRole` alias — role gate for admin/technician routes
5. `DeviceApiKeyAuth` — SHA-256 Bearer token check for device ingestion

**Exception handling**

All API errors are rendered as `{"message": "...", "errors": {...}}` JSON by
the custom `Handler::renderApiException` method registered in
`bootstrap/app.php`.

**Resource serialization**

- `UserResource` — masks email (log-safe), omits password hash
- `AssetResource` — includes thumbnails (null for non-image), optional recommendation fields
- `PlaylistResource` — embeds items with their full `AssetResource`

---

## 5. Database Schema

All tables use MySQL 8.0. The session, cache, and job tables use the
`database` driver (no Redis dependency).

### Core Tables

#### `users`
| Column | Type | Notes |
|--------|------|-------|
| `id` | PK bigint | |
| `name` | varchar | |
| `username` | varchar(50) | unique |
| `email` | text | At-rest encryption recommended for PII compliance |
| `password` | varchar | bcrypt-hashed |
| `role` | enum | `user` \| `admin` \| `technician` |
| `account_status` | enum | `active` \| `frozen` \| `blacklisted` |
| `frozen_until` | timestamp? | Set on freeze, cleared on expiry |
| `deleted_at` | timestamp? | Soft-delete; hard-purged after 30 days |

#### `assets`
| Column | Type | Notes |
|--------|------|-------|
| `id` | PK bigint | |
| `title` | varchar | |
| `description` | text? | |
| `tags` | json? | Array of strings |
| `mime_type` | varchar(100) | Validated and MIME-sniffed |
| `file_path` | varchar(500) | Relative to storage root |
| `file_size` | unsigned bigint | Bytes |
| `duration` | unsigned int? | Seconds |
| `status` | enum | `pending` \| `approved` \| `rejected` |
| `uploaded_by` | FK → users | |
| `play_count` | unsigned bigint | Incremented on each play |
| `thumbnail_160/480/960` | varchar(500)? | Generated by queue job, images only |
| `deleted_at` | timestamp? | Soft-delete; blocked if referenced by playlist |
- Indexes: `(status, created_at)`, `(play_count)`

#### `asset_search_index`
| Column | Type | Notes |
|--------|------|-------|
| `asset_id` | unique FK → assets | Cascades on delete |
| `search_text` | text | Concatenated title + description + tags |
- **FULLTEXT index** on `search_text` — used for `?q=` full-text queries

#### `playlists`
| Column | Type | Notes |
|--------|------|-------|
| `id` | PK bigint | |
| `user_id` | FK → users | |
| `name` | varchar | |
| `description` | text? | |
| `share_code` | varchar(8)? | Unique, auto-generated on create |
| `deleted_at` | timestamp? | Soft-delete |

#### `playlist_items`
| Column | Type | Notes |
|--------|------|-------|
| `playlist_id` | FK → playlists | Cascades on delete |
| `asset_id` | FK → assets | |
| `position` | unsigned int | 0-based ordering |
- Index: `(playlist_id, position)`

#### `favorites`
| Column | Type | Notes |
|--------|------|-------|
| `user_id` | FK → users | |
| `asset_id` | FK → assets | |
| `deleted_at` | timestamp? | Soft-delete (restore on re-favorite) |
- Unique: `(user_id, asset_id)`

#### `play_history`
| Column | Type | Notes |
|--------|------|-------|
| `user_id` | FK → users | |
| `asset_id` | FK → assets | |
| `played_at` | timestamp | Defaults to `NOW()` |
- Index: `(user_id, played_at)`

#### `recommendation_scores`
| Column | Type | Notes |
|--------|------|-------|
| `user_id` | FK → users | |
| `asset_id` | FK → assets | |
| `score` | decimal(10,4) | Recomputed by `ComputeUserRecommendationsJob` |
| `computed_at` | timestamp | |
- Unique: `(user_id, asset_id)`, Index: `(user_id, score)`

### Device Ingestion Tables

#### `devices`
| Column | Type | Notes |
|--------|------|-------|
| `name` | varchar | Human-readable |
| `device_type` | varchar(100) | `gate` \| `camera` \| sensor type |
| `api_key_hash` | varchar(64) | `hash('sha256', rawKey)` — never stores plaintext |
| `last_sequence` | unsigned bigint | Monotonic counter; updated on each accepted event |
| `last_event_at` | timestamp? | Timestamp of most recent event |

#### `device_events`
| Column | Type | Notes |
|--------|------|-------|
| `device_id` | FK → devices | |
| `event_type` | varchar(100) | |
| `payload` | json | Arbitrary device payload |
| `idempotency_key` | varchar(255) | Unique per device, dedup window 7 days |
| `sequence` | unsigned bigint | Per-device monotonic counter value |
| `status` | enum | `received` \| `late` \| `buffered` \| `duplicate` |
| `replay_audit_id` | varchar(255)? | FK → `device_replay_audits.audit_key` |
| `received_at` | timestamp | |
- Indexes: `(device_id, idempotency_key)`, `(device_id, sequence)`, `(device_id, received_at)`

#### `device_replay_audits`
| Column | Type | Notes |
|--------|------|-------|
| `audit_key` | varchar(255) unique | 16-char key generated server-side; sent to device as replay authorization |
| `device_id` | FK → devices | |
| `triggered_by` | FK → users? | Admin who created the replay; null on device-initiated |
| `scope` | varchar(255)? | Time range or event range being replayed |
| `reason` | text? | Human-readable justification |
| `triggered_at` | timestamp | |

#### `device_event_buffer`
| Column | Type | Notes |
|--------|------|-------|
| `device_id` | FK → devices | |
| `event_type` | varchar(100) | |
| `event_payload` | json | |
| `idempotency_key` | varchar(255) | |
| `sequence` | unsigned int | |
| `replay_audit_id` | varchar(255)? | |
| `delivery_state` | enum | `pending` \| `sending` \| `sent` \| `failed` |
| `attempt_count` | unsigned int | Incremented on each retry |
| `next_retry_at` | timestamp? | Exponential backoff scheduling |
| `last_error` | text? | |
- Indexes: `(device_id, delivery_state, next_retry_at)` for dequeue, `(device_id, id)` for eviction (10,000-event cap)

### Infrastructure Tables

| Table | Purpose |
|-------|---------|
| `sessions` | Database-backed session storage (Sanctum) |
| `jobs` / `failed_jobs` / `job_batches` | Queue driver tables |
| `cache` / `cache_locks` | Database cache driver |
| `personal_access_tokens` | Sanctum token table (retained for API token compatibility) |

---

## 6. Authentication & Authorization

### Session Auth (Browser Clients)

1. `POST /api/auth/login` validates username + password against bcrypt hash.
2. Checks `account_status`: rejects if `frozen` (and `frozen_until` has not
   expired) or `blacklisted`.
3. On success: Laravel regenerates the session ID, stores the user in the
   session, and sets `laravel_session` + `XSRF-TOKEN` cookies.
4. All subsequent SPA requests send both cookies; Axios automatically reads
   `XSRF-TOKEN` and injects `X-XSRF-TOKEN` header for CSRF protection.
5. `RequireRole` middleware checks `auth()->user()->role` against the required
   role and returns 403 if insufficient.

### Device Auth (Parking Hardware)

1. Device sends `Authorization: Bearer <api_key>`.
2. `DeviceApiKeyAuth` middleware computes `hash('sha256', $rawKey)` and
   queries `devices` table for a matching `api_key_hash`.
3. The resolved `Device` model is attached to the request attributes for use
   by the ingestion controller.

### Security Practices

- Passwords stored with `bcrypt` (Laravel `Hash::make`).
- Email field uses `text` type; at-rest encryption is the recommended
  deployment practice for fields designated PII.
- `StructuredLogger` middleware masks email and password from log output.
- Soft-delete with 30-day purge job for account deletion (GDPR-style).

---

## 7. Device Event Ingestion Pipeline

### Normal Flow

```
Device  →  POST /api/device/events
              │
              ├─ Verify Bearer token (DeviceApiKeyAuth)
              ├─ Validate request fields
              ├─ Check idempotency_key in device_events
              │     ↳ if found → return 200 {status: "duplicate"}
              ├─ Compare sequence vs device.last_sequence
              │     ↳ if sequence < last_sequence → status = "late"
              │     ↳ if replay_audit_id present → status = "buffered"
              │     ↳ otherwise → status = "received"
              ├─ INSERT into device_events
              ├─ UPDATE devices SET last_sequence, last_event_at
              └─ Return 201 {id, status}
```

### Offline Buffering

When the device gateway cannot reach the server, it stores up to **10,000
events** in `device_event_buffer` with `delivery_state = pending`.
`FlushBufferedDeviceEventsJob` drains the buffer using exponential backoff:

1. Selects rows where `delivery_state IN ('pending', 'failed')` AND
   `next_retry_at <= NOW()`.
2. Applies the same idempotency/sequence/replay logic as the HTTP endpoint.
3. Marks row `sent` on success, `failed` (with updated `next_retry_at`) on
   error.
4. On eviction (>10,000 events), oldest entries are dropped.

### Replay Authorization

Before replaying historical events, an admin creates a
`device_replay_audit` record via `POST /api/admin/device-replays`.
The returned `audit_key` is supplied to the device, which includes it as
`replay_audit_id` in each replayed event. The server:

1. Validates `replay_audit_id` references a real `device_replay_audits`
   record for the authenticated device.
2. Tags the stored event with `status = "buffered"`.
3. Does **not** re-apply side effects (no double-counting, no second score
   computation).

---

## 8. Media Asset Pipeline

```
Upload (POST /api/assets)
    │
    ├─ StoreAssetRequest validates MIME, size limits
    │       image/jpeg, image/png: max 25 MB
    │       application/pdf:       max 25 MB
    │       audio/mpeg:            max 25 MB
    │       video/mp4:             max 250 MB
    │
    ├─ ScanHookService (reserved hook for on-prem scanner)
    ├─ Store file to storage/app/media
    ├─ Insert Asset (status: pending)
    ├─ Dispatch GenerateThumbnailsJob → widths 160 / 480 / 960 px (images only)
    └─ Dispatch IndexAssetJob → upserts asset_search_index row
                                (title + description + tags → FULLTEXT)

Admin approves → status: approved → asset visible in library

Delete (DELETE /api/assets/{asset})
    ├─ Check playlist_items for any reference
    │     ↳ if found → 409 Conflict
    └─ Soft-delete asset record
```

**MIME Validation:** The `MimeValidationService` (used by
`StoreAssetRequest`) performs both header-declared MIME checking and
magic-byte sniffing to reject disguised executables.

---

## 9. Recommendation Engine

### Score Computation (`ComputeUserRecommendationsJob`)

Triggered asynchronously after every play event. The job is **unique per
user** — a second dispatch for the same user while the first is still queued
is dropped.

Algorithm (simplified):
1. Collect the user's favorite asset tags and recently played asset tags.
2. Score all approved assets by tag overlap with the user's signals.
3. Upsert up to 100 `recommendation_scores` rows for the user.

### Degradation Guard

The recommendation engine monitors two metrics over a rolling **5-minute
window**:

| Metric | Threshold | Action on breach |
|--------|-----------|-----------------|
| API p95 latency | > 800 ms | Disable `recommended` sort; fall back to `most_played` |
| Recommendation hit rate | < 10% | Same fallback |

When degraded, `GET /api/assets?sort=recommended` returns assets sorted by
`play_count DESC` and includes `"fallback": true` in the response.
`GET /api/recommendations` also sets `"fallback": true`.

### Recommendation Reasons (UI copy)

| Signal | Reason shown |
|--------|-------------|
| Favorites tag overlap | `"Based on your favorites: Tag1, Tag2"` |
| Play-history tag overlap | `"Based on your listening history"` |
| No personal signal | `"Popular in your facility"` |

---

## 10. Queue Jobs

| Job | Trigger | Tries | Timeout | Notes |
|-----|---------|-------|---------|-------|
| `GenerateThumbnailsJob` | Asset upload | 3 | 120 s | JPEG/PNG only; writes 160/480/960 px thumbnails |
| `IndexAssetJob` | Asset upload | 3 | 30 s | Upserts `asset_search_index` |
| `ComputeUserRecommendationsJob` | Play recorded | 2 | 60 s | Unique per user; no duplicate queuing |
| `FlushBufferedDeviceEventsJob` | Scheduler / on-demand | — | — | Drains `device_event_buffer` with exponential backoff |

---

## 11. Port Configuration

| Component | Internal Port | External Port | Where set |
|-----------|--------------|---------------|-----------|
| Frontend nginx | 80 | **3000** | `docker-compose.yml` `ports: "3000:80"` |
| Backend nginx (+ php-fpm) | 9000 | — (internal only) | `backend/nginx-backend.conf` |
| Database (MySQL) | 3306 | — (internal only) | `docker-compose.yml` |

The application is exposed at `http://localhost:3000` by default.
To override: set the `PORT` environment variable before running
`docker compose up` and change the mapping accordingly, or update
`docker-compose.yml` to `"${PORT:-3000}:80"`.

`APP_URL` in the backend environment is set to `http://localhost:3000`
so generated asset URLs resolve correctly from the browser.

---

## 12. Key Design Decisions & Tradeoffs

### No external services
All data (sessions, queue, cache, monitoring metrics) lives in MySQL.
This simplifies on-premises deployment (single external dependency: Docker)
and avoids data leaving the facility network. The tradeoff is that high-
throughput queue workloads would benefit from Redis in larger deployments.

### Soft-delete everywhere
Users, assets, favorites, and playlists use soft-delete. This provides a
30-day recovery window before the purge scheduler permanently removes records.
The `play_count` aggregate on `assets` is a denormalized counter updated
directly on play, trading strict consistency for query performance.

### Asset deletion protection
Deleting an asset that is referenced by any playlist returns 409. This
prevents broken playlist items. The correct flow is to remove the item from
all playlists first, or replace the asset.

### Idempotent device ingestion
Every device event carries an `idempotency_key`. The server deduplicates
within a 7-day window against `device_events.(device_id, idempotency_key)`.
This means retries — including replays — are safe: duplicate keys return
200 without re-inserting. The sequence counter provides a secondary
out-of-order signal but does not block ingestion; late events are stored
with `status = "late"` for diagnostic visibility.

### Recommendation degradation
The engine proactively degrades to `most_played` rather than returning errors
or empty results. The 5-minute sliding window prevents a single slow request
from permanently disabling recommendations. The UI displays a reason string
for each recommendation rather than a score, keeping the experience intuitive
for non-technical staff.

### Local-only monitoring
`GET /api/admin/monitoring` returns queue depth, failed job count, device
ingestion counts, and recommendation engine metrics — all sourced from the
local database. No external APM or analytics SDK is used, which is a hard
requirement for air-gapped or network-restricted parking facilities.
