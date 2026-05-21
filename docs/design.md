# SmartPark Media Operations — Architecture & Design

## System Overview

SmartPark Media Operations is an on-premises, offline-first management system for parking facility operators. It is fully containerized with Docker Compose and requires zero internet connectivity at runtime.

---

## Architecture

### Component Map

```
┌─────────────────────────────────────────────────────────┐
│                     Docker Network (app)                │
│                                                         │
│  ┌─────────────┐   /api/*   ┌──────────────────────┐   │
│  │  frontend   │──────────▶│      backend          │   │
│  │  (nginx +   │◀──────────│  (PHP-FPM + nginx)    │   │
│  │   Vue SPA)  │           │  Laravel 11           │   │
│  │  port: 80   │           │  port: 9000           │   │
│  └─────────────┘           └──────────┬───────────┘   │
│       ▲                               │                │
│   port 8080                     ┌─────▼──────┐        │
│   (host)                        │    db      │        │
│                                 │  MySQL 8   │        │
│  ┌──────────┐  ┌───────────┐   │  port 3306 │        │
│  │  queue   │  │ scheduler │   └────────────┘        │
│  │ (worker) │  │ (cron)    │                          │
│  └──────────┘  └───────────┘                          │
└─────────────────────────────────────────────────────────┘
```

### Data Flow

1. **Browser → frontend (nginx)**: Vue SPA served from built assets.
2. **Browser → /api/ → backend**: nginx proxies all `/api/*` requests to Laravel.
3. **Backend → MySQL**: All persistence via Eloquent ORM.
4. **Backend → Queue (DB)**: Async jobs dispatched to `jobs` table; `queue` container processes them.
5. **Device → POST /api/device/events**: Authenticated via SHA-256 API key hash, never stored plaintext.

---

## Tech Stack

| Component | Technology | Rationale |
|---|---|---|
| Backend API | PHP 8.3 / Laravel 11 | Mature REST framework, Sanctum sessions, queue + scheduler built-in |
| Auth | Laravel Sanctum (SPA sessions) | Cookie + CSRF, no JWT complexity, secure with `http_only` |
| Database | MySQL 8 | Full-text indexes (MATCH/AGAINST), JSON columns, window functions |
| Queue | Laravel Database Queue | Zero extra infrastructure; jobs table in MySQL |
| Frontend | Vue 3 (Composition API) | Reactive, component-based, `<script setup>` concision |
| State | Pinia | Per-domain stores, devtools support |
| Routing | Vue Router 4 | Nested layouts, navigation guards |
| CSS | Tailwind CSS 3 | Utility-first, design tokens, no runtime overhead |
| Containers | Docker Compose v2 | Single command startup, service health checks |

---

## Key Design Decisions

### 1. Session Auth over JWT
Laravel Sanctum's SPA session mode was chosen over stateless JWTs. Rationale: this is an on-premises intranet app — cookie-based sessions are simpler, don't require token refresh logic, and pair naturally with CSRF protection. The `same_site=lax` cookie and CSRF token double-guard all state-mutating requests.

### 2. Encrypted Email at Rest
The `users.email` column uses Laravel's `encrypted` cast (AES-256-CBC). This prevents exposure in database dumps. The tradeoff: email lookups require decryption of all rows — addressed by decrypting in PHP and matching in application code for login, acceptable at facility scale.

### 3. Device API Key as SHA-256 Hash
Device API keys are stored as SHA-256 hashes only. The raw key is generated once (documented in README) and never re-readable from the database. This follows the same model as password hashing for service credentials.

### 4. Idempotent Event Ingestion
Device events use a 7-day dedup window keyed on `(device_id, idempotency_key)`. Late events (sequence < last_sequence) are saved with `status='late'` rather than rejected — field devices may send buffered events after reconnection. Replay events (with `replay_audit_id`) are marked `buffered` and do not advance the monotonic counter, preventing double-application of side effects.

### 5. Recommendation Degradation Guard
The `DegradationService` maintains a sliding 100-sample p95 latency window in Laravel cache. If p95 exceeds 800ms, the recommendation engine auto-disables for 5 minutes and falls back to Most Played. This prevents slow recommendation queries from degrading the user-facing asset list API.

### 6. MIME Sniffing + Magic Bytes
`MimeValidationService` uses PHP's `finfo_open(FILEINFO_MIME_TYPE)` to detect MIME from file content (not the browser-supplied filename extension). It also checks magic bytes for ELF binaries (`\x7FELF`), Windows PE executables (`MZ`), and Mach-O binaries — rejecting them even if disguised as audio/video.

### 7. Soft Deletes + 30-day Purge
All user-owned models use `SoftDeletes`. Records remain queryable for 30 days after deletion, then `PurgeDeletedRecords` command hard-deletes them. This satisfies GDPR-style "right to deletion" while providing an audit window.

---

## Database Schema Summary

| Table | Purpose | Notes |
|---|---|---|
| `users` | User accounts | email encrypted, role ENUM, account_status ENUM |
| `assets` | Media assets | MIME, size, thumbnails, play_count, soft delete |
| `asset_search_index` | Full-text search | FULLTEXT index on search_text |
| `playlists` | User playlists | Optional share_code (8 chars, unique) |
| `playlist_items` | Playlist contents | position-ordered |
| `favorites` | User favorites | unique(user_id, asset_id) |
| `play_history` | Play log | drives recommendation scoring |
| `recommendation_scores` | Pre-computed recs | score = tag_affinity×2 + play_count×0.1 |
| `devices` | IoT devices | api_key_hash (SHA-256), last_sequence |
| `device_events` | Ingested events | status: received/late/buffered/duplicate |
| `jobs` / `failed_jobs` | Queue storage | Laravel database queue driver |
| `sessions` | Auth sessions | Laravel database session driver |

---

## Security Design

| Concern | Implementation |
|---|---|
| Authentication | Sanctum SPA sessions (CSRF + cookie) |
| Authorization | `RequireRole` middleware (401/403), object-level checks in controllers |
| Password storage | Bcrypt (Laravel `Hash::make`) |
| Email privacy | AES-256 encrypted column; masked in API responses (`jo****@example.com`) |
| Log masking | `StructuredLogger` masks `password`, `email`, `token` fields → `[REDACTED]` |
| File uploads | MIME sniffing + magic byte rejection + per-type size caps |
| Device auth | SHA-256 hashed API keys; Bearer token header only |
| No debug output | `dd()`, `dump()`, `console.log` banned from production code |
| No telemetry | Zero outbound network calls; no third-party SDKs with call-home behavior |

---

## Async Processing

| Job | Trigger | Effect |
|---|---|---|
| `GenerateThumbnailsJob` | Asset upload | Creates 160/480/960px JPEG crops for images |
| `IndexAssetJob` | Asset upload | Writes full-text search index entry |
| `ComputeUserRecommendationsJob` | Play recorded | Recomputes top-100 scores for the user (unique per user) |

All jobs are dispatched to the `default` queue, processed by the `queue` container, and are idempotent (safe to re-run after failure).

---

## Scheduled Tasks

| Command | Schedule | Effect |
|---|---|---|
| `app:purge-deleted` | Daily | Hard-deletes soft-deleted records older than 30 days |
| `app:unfreeze-accounts` | Every 5 min | Resets `frozen` accounts whose `frozen_until` has passed |
| `app:process-file-drop` | Every minute | Processes JSON event files from optional file-drop folder |

---

## Frontend Architecture

The Vue 3 SPA is structured around domain-specific Pinia stores and lazy-loaded route components:

- `useAuthStore` — session state, login/logout, role checks
- `useSearchStore` — asset search state, filters, sort
- `usePlaylistStore` — playlist CRUD, share codes
- `useNowPlayingStore` — play history, session tracker

The nginx container serves the built SPA and proxies all `/api/*` to the backend container, eliminating CORS entirely.

---

## Trade-offs

| Decision | Chosen | Alternative | Why not alternative |
|---|---|---|---|
| Queue driver | MySQL `jobs` table | Redis | Redis requires an extra container; DB queue is sufficient for facility-scale throughput |
| Session storage | MySQL `sessions` | File | File sessions don't work well in multi-container setups |
| Auth | Sanctum SPA | JWT | JWT adds token refresh complexity, no benefit for same-origin SPA |
| Image processing | GD (via Intervention Image 3) | Imagick | GD available in Alpine PHP image without additional packages |
| Search | MySQL FULLTEXT | Elasticsearch | Elasticsearch requires a separate cluster; FULLTEXT is sufficient for <100k assets |
