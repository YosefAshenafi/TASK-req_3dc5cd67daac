# SmartPark Media Operations — API Specification

All endpoints are served by the Laravel backend over the parking-site LAN under the base path `/api`. Authentication is via Laravel Sanctum (HTTP-only cookie for the Vue + TypeScript SPA; Bearer token for device gateways and tooling). All request/response bodies are `application/json` unless noted. Dates are ISO-8601 UTC.

A matching set of TypeScript interfaces for every payload below lives in `frontend/src/types/api.ts` and is shared by the SPA's generated API client.

---

## Conventions

- **Success**: `2xx` with a JSON body.
- **Validation errors**: `422` with `{ "message": "...", "errors": { "field": ["..."] } }`.
- **Auth errors**: `401` (not authenticated), `403` (authenticated but not authorized), `423` (account frozen).
- **Rate-limit**: `429` with `Retry-After` header.
- **Pagination**: cursor-based on collection endpoints; response includes `next_cursor` (nullable) and `items`.

### Headers

| Header                        | Purpose                                                         |
|-------------------------------|-----------------------------------------------------------------|
| `Authorization: Bearer <tok>` | For device gateways and tooling (SPA uses cookies).             |
| `X-Idempotency-Key`           | Required on device-event POSTs; optional on POST/PUT others.    |
| `X-Recommendation-Degraded`   | Response-only flag: `true` when `sort=recommended` was rewritten. |

---

## 1. Authentication

### `POST /api/auth/login`
Authenticates a user.

**Request:**
```json
{ "username": "jsmith", "password": "••••••••" }
```

**Responses:**
- `200 OK` → `{ "user": { "id": 12, "username": "jsmith", "role": "user" }, "csrf_token": "..." }`
- `401 Unauthorized` → `{ "message": "Invalid credentials", "attempts_remaining": 4 }`
- `423 Locked` → `{ "message": "Account frozen", "frozen_until": "2026-04-20T12:00:00Z" }`
- `429 Too Many Requests` after 5 failures → `Retry-After: 900`.

### `POST /api/auth/logout`
Invalidates the current session. Returns `204`.

### `GET /api/auth/me`
Returns the currently authenticated user.
`200 OK` → `{ "id": 12, "username": "jsmith", "role": "user", "favorites_count": 7 }`

---

## 2. Users (Admin-only)

### `POST /api/users` — create account
```json
{ "username": "amurphy", "password": "…", "role": "technician", "email": "a@site.local" }
```
`201 Created` → user object.

### `GET /api/users?query=&role=&status=&cursor=`
Paginated user list.

### `PATCH /api/users/{id}/freeze`
```json
{ "duration_hours": 72, "reason": "Policy violation" }
```
`200 OK` → `{ "id": 12, "frozen_until": "2026-04-20T12:00:00Z" }`.

### `PATCH /api/users/{id}/unfreeze`
Clears `frozen_until`. `200 OK`.

### `PATCH /api/users/{id}/blacklist`
```json
{ "reason": "Repeat abuse" }
```
`200 OK`.

### `DELETE /api/users/{id}`
Soft-delete; purge runs at day 30. `204 No Content`.

---

## 3. Media Library

### `GET /api/search`
Full-text search against the media library.

**Query parameters:**
| Param          | Type         | Description                                                       |
|----------------|--------------|-------------------------------------------------------------------|
| `q`            | string       | Free-text query.                                                  |
| `tags[]`       | string[]     | Filter — asset must carry all supplied tags.                      |
| `duration_lt`  | integer (s)  | E.g., `120` → "under 2 minutes."                                  |
| `recent_days`  | integer      | Restrict to assets updated within the last N days (e.g., `30`).   |
| `sort`         | enum         | `most_played` \| `newest` \| `recommended`.                       |
| `cursor`       | string       | Pagination cursor.                                                |

**Response:**
```json
{
  "items": [
    {
      "id": 417,
      "title": "Overnight Safety Reminder",
      "duration_seconds": 95,
      "tags": ["safety", "overnight"],
      "thumbnail_urls": { "160": "...", "480": "...", "960": "..." },
      "played_count": 284,
      "created_at": "2026-03-02T09:14:00Z",
      "reason_tags": ["safety", "overnight", "gate issues"]
    }
  ],
  "next_cursor": "eyJpZCI6NDE3fQ==",
  "degraded": false
}
```

Sets `X-Recommendation-Degraded: true` when the circuit breaker is open and the request asked for `sort=recommended`.

### `POST /api/assets` — upload (Admin only, multipart/form-data)
Fields: `file`, `title`, `tags[]`, `description`.
- `201 Created` → asset object with `status: "processing"` while thumbnails/indexing queue.
- `422` per-file error list:
```json
{
  "message": "Upload validation failed",
  "errors": [
    { "filename": "broken.mp4", "reason_code": "magic_mismatch", "reason": "Declared MP4 but magic bytes do not match" }
  ]
}
```

### `GET /api/assets/{id}`
Single asset detail, including size/MIME/duration.

### `DELETE /api/assets/{id}` (Admin only)
- `409 Conflict` → `{ "message": "Asset is referenced by N playlists", "playlist_ids": [3, 8] }` when referenced.
- `204 No Content` when free.

### `POST /api/assets/{id}/play`
Records a play event and appends to play history. `202 Accepted`.

---

## 4. Favorites

### `GET /api/favorites?cursor=`
Returns the current user's favorited assets.

### `PUT /api/favorites/{asset_id}`
Idempotent. `204`.

### `DELETE /api/favorites/{asset_id}`
Idempotent. `204`.

---

## 5. Playlists

### `GET /api/playlists`
Current user's playlists.

### `POST /api/playlists`
```json
{ "name": "Morning Gate Checks", "asset_ids": [417, 502, 611] }
```
`201 Created`.

### `GET /api/playlists/{id}` — full with items.

### `PATCH /api/playlists/{id}`
```json
{ "name": "Updated name", "asset_ids_ordered": [611, 502, 417] }
```

### `DELETE /api/playlists/{id}` — `204`.

### `POST /api/playlists/{id}/share`
Generates a share code (LAN-internal redemption).
`201 Created`:
```json
{ "code": "X7QK4N2P", "expires_at": "2026-04-18T12:00:00Z" }
```
Rate-limited to 5/hour/user (Redis-backed).

### `POST /api/playlists/redeem`
```json
{ "code": "X7QK4N2P" }
```
`201 Created` → `{ "cloned_playlist_id": 91 }`
- `404` if code unknown/expired/revoked.
- `403` if owner is blacklisted.

### `DELETE /api/playlists/shares/{id}` — revoke share. `204`.

---

## 6. Now Playing / History

### `GET /api/history?cursor=`
Last played items for the current user.

### `GET /api/now-playing`
Returns a lightweight summary of current session + recently-played.

---

## 7. Recommendations

### `GET /api/recommendations`
Returns up to 25 pre-computed candidates with `score` and `reason_tags`.
- Empty + `degraded: true` when the circuit breaker is open.

---

## 8. Device Events (Technician / Gateway)

### `POST /api/devices/events`
Submitted by device gateways. Requires a Bearer token tied to a `technician`-role service account.

**Headers:**
- `X-Idempotency-Key: <uuid-v4>` — required.

**Body:**
```json
{
  "device_id": "gate-west-01",
  "event_type": "gate.opened",
  "sequence_no": 184592,
  "occurred_at": "2026-04-17T09:42:11.123Z",
  "payload": { "plate": "7K-VV-842", "lane": "W2" }
}
```

**Responses:**
- `201 Created` → `{ "id": 930145, "status": "accepted" }`.
- `200 OK` (already processed) → `{ "id": 930145, "status": "duplicate" }`.
- `202 Accepted` → `{ "status": "out_of_order", "reconciliation_queued": true }`.
- `410 Gone` → `{ "status": "too_old" }` when older than the 7-day dedup window.

### `GET /api/devices`
Device roster + last-seen + current `last_sequence_no`.

### `GET /api/devices/{id}/events?cursor=&since=`
Audit-trail-style event listing.

### `POST /api/devices/{id}/replay` (Technician)
```json
{ "since_sequence_no": 184500, "until_sequence_no": 184700, "reason": "Gateway resync" }
```
Creates a `replay_audits` record and re-emits the events to downstream consumers **without** duplicating side effects (dedup by idempotency key still applies).
`202 Accepted`.

---

## 9. Monitoring (Admin only)

### `GET /api/monitoring/status`
```json
{
  "api": { "p95_ms_5m": 612, "error_rate_5m": 0.008 },
  "queues": { "default": 3, "indexing": 0, "thumbnails": 12 },
  "storage": { "media_volume_free_bytes": 128849018880, "media_volume_used_pct": 42.7 },
  "devices": { "online": 18, "offline": 1, "dedup_rate_1h": 0.031 },
  "feature_flags": {
    "recommended_enabled": {
      "enabled": true,
      "last_transition_at": "2026-04-16T22:11:00Z",
      "reason": null
    }
  }
}
```

### `POST /api/monitoring/feature-flags/recommended/reset`
Re-enables the flag after a manual review. `200 OK`.

---

## 10. Error Codes

| Code  | Meaning                                              |
|-------|------------------------------------------------------|
| 400   | Malformed request.                                   |
| 401   | Not authenticated / blacklisted (generic).           |
| 403   | Role or ownership check failed.                      |
| 404   | Resource not found / share code invalid.             |
| 409   | Conflict (e.g., asset still in a playlist).          |
| 410   | Resource no longer available (event too old).        |
| 422   | Validation errors.                                   |
| 423   | Account frozen.                                      |
| 429   | Rate limited.                                        |
| 500   | Server error (logged; reason masked in response).    |

---

## 11. Example End-to-End: Kiosk Search → Play

1. `GET /api/auth/me` — confirm session.
2. `GET /api/search?q=overnight&tags[]=safety&duration_lt=120&sort=recommended`.
3. Response `X-Recommendation-Degraded: false` — display "Recommended" chips.
4. User taps an item → `POST /api/assets/417/play` (fire-and-forget; `202`).
5. `GET /api/now-playing` refreshes the panel.

---

## 12. Example End-to-End: Device Replay

1. Technician opens `/devices/gate-west-01`.
2. `GET /api/devices/gate-west-01/events?since=184500` — inspects the gap.
3. `POST /api/devices/gate-west-01/replay { "since_sequence_no": 184500, "reason": "LAN outage 09:42" }` → `202`.
4. `replay_audits` row recorded; subsequent events arrive with `status: duplicate` proving idempotency.
