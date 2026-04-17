# Open Questions, Assumptions & Solutions

The SmartPark Media Operations prompt leaves several areas unspecified. Below are the major ambiguities identified, the assumptions we will adopt, and the proposed solution for each. All solutions target the stack defined in `metadata.json`: Vue.js + TypeScript frontend, Laravel (PHP) backend, MySQL as the system of record, Redis for cache/queues, and local disk for media assets.

---

## 1. How are user roles differentiated at sign-in, and can a user hold multiple roles?

The prompt mentions three roles — Regular User, Administrator, and Field Technician — but does not specify whether role assignment is exclusive, how it is provisioned, or how the UI routes users post-login.

**Assumption:**
- A user account holds exactly one primary role (`user`, `admin`, or `technician`), assigned at account creation by an Administrator.
- There is no self-registration; Administrators create accounts via the admin console.
- Role is embedded in the authentication session and the SPA uses it to gate routes.

**Solution:**
- The `users` table includes a `role` enum column (`user`, `admin`, `technician`).
- Laravel middleware (`role:admin`, `role:technician`) protects the corresponding REST endpoints.
- The Vue Router (with TypeScript-typed `RouteMeta`) uses a global navigation guard that reads the role from the Pinia auth store and redirects unauthorized access to a 403 view.
- Login lands each role on a role-appropriate landing page: Users → `/search`, Admins → `/admin`, Technicians → `/devices`.

---

## 2. How is the playlist share code generated, validated, and scoped?

The prompt describes sharing via a "copyable on-screen code" entered on another device but does not describe length, lifetime, uniqueness, or whether it grants read or write access.

**Assumption:**
- The code is a short human-typable token (8 alphanumeric characters, uppercase, no ambiguous chars like `0/O/1/I`).
- The code is LAN-internal only, grants read-only clone access (the recipient receives a personal copy they can then edit independently), and is valid for 24 hours or until revoked.
- The original owner can regenerate or revoke the code at any time.

**Solution:**
- `playlist_shares` table: `id, playlist_id, code, created_by, expires_at, revoked_at`.
- `POST /api/playlists/{id}/share` generates a code; `POST /api/playlists/redeem` with a code clones the playlist into the caller's library.
- Codes have a unique constraint and a Redis-backed rate limit of 5 generations per hour per user to prevent enumeration abuse.

---

## 3. What is the exact recommendation algorithm, and what does "recommendation hit rate" mean?

The prompt requires a "Recommended" ranking with an on-screen reason and a degradation fallback when hit rate drops below 10%, but does not define the algorithm or the hit-rate metric.

**Assumption:**
- Recommendations are content-based: tag-similarity scoring between a user's favorites/recent plays and candidate assets, combined with a popularity prior.
- "Hit rate" = (plays of recommended items) / (impressions of recommended items) over a rolling 60-minute window.
- Candidate generation is asynchronous (Laravel queues); ranking is cheap and computed at request time from pre-materialized candidates.

**Solution:**
- A scheduled + on-event job (`GenerateRecommendationCandidates`) writes to `recommendation_candidates(user_id, asset_id, score, reason_tags_json, refreshed_at)`.
- The `/api/search?sort=recommended` endpoint joins against this table.
- A metrics collector records impressions and plays in MySQL; a scheduled task compares the rolling ratio to the 10% threshold and flips a `feature_flags.recommended_enabled` row off when breached.
- Reasons are the top 3 overlapping tags between user favorites and the candidate asset, rendered by the frontend as "Based on your favorites: X, Y, Z."

---

## 4. How exactly is device-event deduplication and out-of-order correction performed?

The prompt requires a 7-day dedup window, per-device monotonic counters, controlled replay with audit trails, and offline buffering of up to 10,000 events — but does not specify the data model, replay UX, or how counters reset.

**Assumption:**
- Each event carries `{device_id, event_type, sequence_no, idempotency_key, occurred_at, payload}`.
- `idempotency_key` is a UUIDv4 generated at the device and is globally unique.
- `sequence_no` is a per-device monotonic `BIGINT` that never resets; the device gateway persists the last issued counter locally.
- Events with `sequence_no < last_accepted_sequence_no - 1000` are rejected as "too old."
- Replay is a privileged action initiated by a Field Technician from the device console, always recorded in an audit log.

**Solution:**
- `device_events` has a unique index on `(device_id, idempotency_key)` — repeated inserts are no-ops returning `200 OK` with the original record.
- Out-of-order events are accepted but flagged `is_out_of_order=true`; a scheduled reconciliation job reorders the derived state without duplicating side effects.
- Replay endpoint `POST /api/devices/{id}/replay` takes a `since_sequence_no` and is logged in `replay_audits`.

---

## 5. What happens to a blacklisted or frozen account's content (favorites, playlists, play history)?

The prompt mentions blacklisting and 72-hour freezes but does not specify whether content is hidden, retained, or re-enabled on unfreeze.

**Assumption:**
- **Freeze** is a temporary reversible state: the user cannot log in; their data is untouched and restored on thaw.
- **Blacklist** is effectively a permanent ban: the user cannot log in and their playlists are hidden from share-redemption, but their record is kept per the soft-delete + 30-day purge rules.
- Neither action hard-deletes favorites or play history immediately.

**Solution:**
- `users` adds `frozen_until TIMESTAMP NULL` and `blacklisted_at TIMESTAMP NULL`.
- Login middleware checks both; frozen users receive a `423` with the unfreeze time; blacklisted users receive a generic `401`.
- A `ShareRedemptionGuard` refuses to clone playlists whose owner is blacklisted.
- The 30-day purge job cascades to playlists, favorites, and play history for rows where `deleted_at` is older than 30 days.

---

## 6. How is the "offline-first" behavior realized given that the stack is Laravel + MySQL + Redis on the local network?

The prompt says the system is offline-first but the stack implies a local server. The distinction between "offline from the internet" and "offline from the server" is not clearly drawn.

**Assumption:**
- The entire stack (Laravel, MySQL, Redis, Vue SPA) is deployed on the **parking-site LAN** — there is no dependency on the public internet.
- Device gateways may be intermittently disconnected from the site server and must buffer locally (the 10,000-event buffer).
- Browsers on kiosks are assumed to have a reliable LAN path to the Laravel server; the SPA itself is not a PWA offline client in v1.

**Solution:**
- Deploy Laravel + MySQL + Redis on an on-prem Linux server; serve the SPA build as static files from nginx.
- Device gateway is a small service on each device host that persists events to SQLite locally and drains to the server over HTTP with exponential backoff (1s, 2s, 4s, … capped at 5 min), up to 10,000 buffered events (FIFO drop when full with a warning log).
- All external services (scanning tools, telemetry) are disabled; the codebase contains TypeScript-typed stub interfaces and Laravel contracts with clearly marked "hook" extension points.

---

## 7. What are the exact rules for the automatic-degradation circuit breaker?

The prompt says "Recommended" ranking is disabled when p95 latency > 800 ms for 5 minutes or hit rate < 10%, but does not describe the recovery path or who is notified.

**Assumption:**
- Degradation is automatic and one-way on trip; recovery requires either a cooldown period of 15 minutes of healthy metrics or an explicit admin override.
- The trip and recovery events are surfaced on the local monitoring page and raise an in-app notification for Administrators.
- The fallback ("Most Played") is applied uniformly to all users; there is no per-user fallback.

**Solution:**
- A scheduled task (every 30 s) evaluates the rolling windows of `api_latency_samples` and `recommendation_impressions/plays`.
- On trip, it writes `feature_flags.recommended_enabled=false` and emits an `AdminAlertCreated` event.
- The monitoring page polls `/api/monitoring/status` every 10 s and shows the current flag state, trip reason, and last transition timestamp.
- Admins can force-reset via `POST /api/monitoring/feature-flags/recommended/reset`.

---

## 8. How are uploaded media files validated beyond MIME sniffing, and what happens to rejected uploads?

The prompt calls for format allowlists, size limits, fingerprint validation, and MIME sniffing, but does not define what a "fingerprint" is or what feedback the uploader receives.

**Assumption:**
- "Fingerprint validation" means magic-byte inspection against the declared MIME type (e.g., MP4 must start with an `ftyp` box) plus an SHA-256 digest stored for future deduplication/integrity checks.
- Rejected uploads are never written to the final storage directory; they are discarded after the validation response.
- Per-file errors are returned in a structured response so the admin UI can show per-file failure reasons in a batch upload.

**Solution:**
- Use a finfo-backed service `MediaValidator::validate($tempPath, $declaredMime)` that:
  1. Checks file size against the type-specific cap (25 MB or 250 MB).
  2. Sniffs the first 512 bytes against the expected magic signature.
  3. Rejects any file whose extension, declared MIME, and sniffed MIME disagree.
  4. Computes SHA-256 and stores it in `assets.fingerprint_sha256`.
  5. Emits a `MediaScanRequested` event consumed by a stub listener (on-prem scan hook) — currently a no-op but wired so it can be replaced later.
- Returns `422` with a `{filename, reason_code, reason}` entry per rejected file.

---

## 9. What is the field-level encryption key lifecycle for at-rest encryption of sensitive fields (e.g., email)?

The prompt requires at-rest encryption but does not define key management, rotation, or whether the key is per-record, per-tenant, or global.

**Assumption:**
- A single symmetric AES-256-GCM application key is used, stored outside the database (on the server's local filesystem with `0600` permissions).
- Keys can be rotated; rotation is an admin-triggered background job that re-encrypts affected columns under the new key while retaining the old key ID in the ciphertext envelope for a 30-day grace period.
- Logs never contain ciphertext or plaintext of sensitive fields — only masked previews (e.g., `a***@***.com`).

**Solution:**
- Use Laravel's native `Crypt` facade with a dedicated key separate from `APP_KEY`, loaded from `/etc/smartpark/field-encryption.key`.
- A custom Eloquent cast `EncryptedField` wraps columns like `users.email`.
- Key rotation job: `php artisan field-keys:rotate` streams through affected tables in batches and rewrites envelopes tagged with the new `kid`.
- A custom Monolog processor strips/masks any field listed in a `config('logging.sensitive_fields')` array before a line is written.
