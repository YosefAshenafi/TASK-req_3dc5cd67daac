# Clarification Questions

## Question 1
### Question
What recommendation algorithm should drive the "Recommended" sort — purely tag/category affinity from the user's favorites and play history, a collaborative-filtering model (similar users), or a simpler rule such as "frequently played by users who also favorited the same tags"?

### Assumption
A tag-affinity + play-frequency hybrid is used: candidate assets are scored by how many of the user's favorited tags they share, broken by tie using global play count. No cross-user collaborative model is built.

### Suggested Solution
Implement a pre-computed recommendation score per `(user_id, asset_id)` pair via a nightly (or queue-driven) job that calculates tag overlap and play-frequency weight, stores results in a `recommendation_scores` table, and serves ranking from there. The fallback to "Most Played" on degraded performance is then a simple query swap — no model inference path at runtime.

---

## Question 2
### Question
What is the full taxonomy of device event types (e.g., `gate_open`, `gate_close`, `vehicle_detected`, `sensor_occupied`, `sensor_cleared`, `camera_motion`) and are there required vs. optional payload fields beyond `device_id`, `event_type`, and `idempotency_key`?

### Assumption
Event types cover three device categories — gates (`gate_open`, `gate_close`), cameras (`camera_motion`, `camera_offline`), and geomagnetic sensors (`sensor_occupied`, `sensor_cleared`). A `timestamp` field is required; all other fields are optional metadata stored as a JSON blob.

### Suggested Solution
Define a strict JSON schema (or PHP `FormRequest` validation) for the ingestion endpoint that enforces `device_id`, `event_type` (enum), `idempotency_key`, and `occurred_at` (ISO-8601). Store additional device-specific payload in a `payload` JSON column. Validate `event_type` against an extensible enum seeded from a `device_event_types` table so new types can be added without a code deploy.

---

## Question 3
### Question
Does the system run at a single parking facility or across multiple geographically separate sites? If multi-site, is each site fully independent (no shared user accounts or content library) or does a central server synchronize data between sites?

### Assumption
Single facility, single server. All components — Laravel app, MySQL, queue workers, and the local device gateway — run on one on-premises machine or small LAN. No cross-site replication is required.

### Suggested Solution
If multi-site is needed now or soon, introduce a `site_id` foreign key on `users`, `devices`, `assets`, and `playlists` from day one, even if only one site is active initially. This avoids a painful schema migration later and keeps row-level scoping consistent.

---

## Question 4
### Question
What is the exact distinction between a "blacklisted" account and a "frozen" account — specifically, can a blacklisted account be un-blacklisted by an admin, and what happens to the user's playlists, favorites, and play history when either state is applied?

### Assumption
"Freeze" is a temporary, reversible suspension for a defined duration (e.g., 72 hours) after which the account automatically reactivates. "Blacklist" is a permanent, manually reversible ban. In both states the user cannot authenticate. Playlists and favorites are preserved in both states; shared playlist codes become inaccessible while the account is suspended.

### Suggested Solution
Add `account_status` ENUM (`active`, `frozen`, `blacklisted`), `frozen_until` NULLABLE TIMESTAMP, and `status_reason` TEXT to the `users` table. A scheduled job checks `frozen_until` and reverts `frozen` back to `active`. Blacklisted accounts pass through soft-delete logic only when the user explicitly requests account deletion.

---

## Question 5
### Question
What queue driver should back Laravel queues — the database (no extra infrastructure), Redis, or another broker — given that the system must operate fully offline without internet access and potentially on minimal hardware?

### Assumption
The database queue driver is used (`QUEUE_CONNECTION=database`) to avoid requiring a separate Redis or Beanstalkd installation. This is sufficient for the described workloads (thumbnail generation, indexing, recommendation pre-computation) at single-facility scale.

### Suggested Solution
Use the database driver for simplicity and zero extra dependencies on constrained on-prem hardware. If queue throughput becomes a bottleneck (e.g., rapid bulk uploads), introduce Redis as an optional upgrade path via a single `.env` change. Document the trade-off in the README so operators can evaluate it at deployment time.

---

## Question 6
### Question
How are playlist sharing codes generated, how long do they remain valid, and is access to a shared playlist restricted to authenticated users on the same local network or can it be viewed unauthenticated?

### Assumption
A share code is a short, random alphanumeric string (e.g., 8 characters, base-36). Codes do not expire but are invalidated when the owner deletes the playlist. Only authenticated users on the local network can redeem a code; unauthenticated requests to the share-code endpoint return 401.

### Suggested Solution
Store `share_code` as a unique, nullable column on the `playlists` table. Generate on demand with `Str::random(8)`. The redeem endpoint (`POST /playlists/redeem`) accepts the code and returns a read-only view of the playlist for the authenticated caller. No external URL is generated; the code is entered manually on-device.

---

## Question 7
### Question
Who can access the local monitoring page (API error rate, queue backlog, device ingestion health), and should it be integrated into the existing admin console or served as a standalone endpoint that does not require the full app session (e.g., accessible by ops tooling via a shared secret header)?

### Assumption
The monitoring page is accessible to Administrators only through the existing admin console, protected by the same session-based auth as the rest of the app. No standalone unauthenticated endpoint is exposed.

### Suggested Solution
Add a `/admin/monitoring` route protected by the `role:admin` middleware. Serve aggregated metrics (p95 latency, queue depth, ingestion error count, last-seen timestamps per device) from a dedicated `MonitoringController` that reads from the MySQL `job_batches`, `failed_jobs`, and `device_events` tables. Add an optional `X-Monitor-Token` header auth path for future ops-tooling integration without disrupting the UI flow.

---

## Question 8
### Question
Should session duration differ by device context — for example, shorter timeouts on shared kiosk tablets versus longer persistent sessions on front-desk desktops — and is a "remember me" or auto-login mechanism needed for kiosk use?

### Assumption
All sessions use the same Laravel session lifetime (configurable in `config/session.php`, defaulting to 120 minutes of inactivity). No "remember me" token is issued. Kiosk operators must re-authenticate after the session expires.

### Suggested Solution
Introduce a `device_mode` query parameter (`kiosk` vs `desktop`) on the login form that sets session lifetime accordingly (e.g., 60 min for kiosk, 480 min for desktop). Store the chosen mode in the session so the frontend can display a visible timeout warning. Do not issue long-lived remember-me tokens on shared kiosk hardware.

---

## Question 9
### Question
What is the expected volume of device events per day and what is the maximum acceptable database size before archiving or pruning older events? The 7-day deduplication window implies events are retained at least 7 days, but there is no upper retention bound specified.

### Assumption
Event volume is moderate (< 50,000 events/day per site). Events are retained for 90 days in the hot `device_events` table; older records are archived to a `device_events_archive` table or purged by a scheduled job, mirroring the 30-day soft-delete purge pattern used for accounts.

### Suggested Solution
Add a `purge:device-events` Artisan command that runs nightly via the Laravel scheduler. Default retention is 90 days and is configurable via a `DEVICE_EVENT_RETENTION_DAYS` environment variable. Log the purge count to the application log at INFO level so the monitoring page can surface it.

---

## Question 10
### Question
Are there role-transition rules — for example, can a Field Technician be promoted to Administrator without creating a new account, and can a Regular User be granted Field Technician access temporarily (e.g., during an on-call shift)?

### Assumption
Roles are mutually exclusive and assigned per account at creation or by an Administrator. A single account holds exactly one role. Role changes require Admin action and take effect immediately on the next authenticated request.

### Suggested Solution
Store roles in a `roles` table with a `role` ENUM (`user`, `technician`, `admin`) on the `users` table (single-role model). If overlapping roles become necessary, migrate to a `user_roles` pivot table. Gate each API route with a dedicated `RoleMiddleware` that reads from the `users.role` column so a role change is effective without a cache flush.
