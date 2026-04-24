# SmartPark Media Operations — API Specification

All endpoints are served by the Laravel backend over the parking-site LAN under the base path `/api`. Authentication is via Laravel Sanctum (HTTP-only cookie for the Vue + TypeScript SPA; Bearer token for device gateways and tooling). All request/response bodies are `application/json` unless noted. Dates are ISO-8601 UTC.

A matching set of TypeScript interfaces for every payload below lives in `frontend/src/types/api.ts` and is shared by the SPA's generated API client.

---

## Conventions

- **Success**: `2xx` with a JSON body.
- **Validation errors**: `422` with `{ "message": "...", "errors": { "field": ["..."] } }`.
- **Auth errors**: `401` (not authenticated / blacklisted), `403` (authenticated but not authorized), `423` (account frozen).
- **Rate-limit**: `429` with `Retry-After` header.
- **Pagination**: cursor-based on collection endpoints; response includes `next_cursor` (nullable) and `items`.

### Headers

| Header                        | Purpose                                                         |
|-------------------------------|-----------------------------------------------------------------|
| `Authorization: Bearer <tok>` | For device gateways and tooling (SPA uses cookies).             |
| `X-Gateway-Token: <secret>`   | Alternative auth for `POST /api/gateway/events` (shared secret).|
| `X-Idempotency-Key`           | Required on device-event POSTs; optional on POST/PUT others.    |
| `X-Recommendation-Degraded`   | Response-only flag: `true` when `sort=recommended` was rewritten. |

---

## 1. Authentication

### `POST /api/auth/login`
Authenticates a user. Returns a Sanctum Bearer token in the response body.

**Request:**
```json
{ "username": "jsmith", "password": "••••••••" }
```

**Responses:**
- `200 OK` → `{ "user": { "id": 12, "username": "jsmith", "role": "user" }, "token": "<sanctum-bearer-token>", "csrf_token": "..." }`
- `401 Unauthorized` → `{ "message": "Invalid credentials.", "attempts_remaining": 4 }`
- `423 Locked` → `{ "message": "Account is temporarily frozen.", "frozen_until": "2026-04-20T12:00:00Z" }`
- `429 Too Many Requests` after 5 failures within 15 minutes → `{ "message": "Too many login attempts.", "retry_after": <seconds> }`.

Note: Blacklisted accounts also return `401 { "message": "This account has been disabled." }` with no `attempts_remaining`.

### `POST /api/auth/logout`
Invalidates the current session token. Returns `204 No Content`.

### `GET /api/auth/me`
Returns the currently authenticated user.
`200 OK` → `{ "id": 12, "username": "jsmith", "role": "user", "frozen_until": null, "blacklisted_at": null, "favorites_count": 7, "created_at": "2026-01-01T00:00:00Z" }`

---

## 2. Health & Settings

### `GET /api/health` (public)
Service liveness check.
`200 OK` → `{ "status": "ok" }`

### `GET /api/settings` (public)
Returns application-level settings used by the SPA to populate tag pickers and branding.
`200 OK`:
```json
{
  "site_name": "SmartPark",
  "site_tagline": "Find and discover media assets",
  "available_tags": ["Safety", "Overnight", "Gate Issues", "Parking", "Event", "General", "Emergency"]
}
```

### `PUT /api/settings` (Admin only)
Updates one or more settings fields.

**Request** (all fields optional):
```json
{
  "site_name": "SmartPark LAN",
  "site_tagline": "Internal media library",
  "available_tags": ["Safety", "Overnight", "Emergency"]
}
```
`200 OK` → updated settings object (same shape as `GET /api/settings`).

---

## 3. Users (Admin-only)

### `POST /api/users` — create account
```json
{ "username": "amurphy", "password": "…", "role": "technician", "email": "a@site.local" }
```
`201 Created` → `{ "id": 5, "username": "amurphy", "role": "technician" }`.

### `GET /api/users?query=&role=&status=`
Paginated user list. Returns all matching users (cursor pagination is not yet implemented — `next_cursor` is always `null`).

| Param    | Description                                                         |
|----------|---------------------------------------------------------------------|
| `query`  | Username substring search.                                          |
| `role`   | Filter by role: `user` \| `admin` \| `technician`.                  |
| `status` | Filter by account state: `active` \| `frozen` \| `blacklisted`.    |

`200 OK`:
```json
{
  "items": [
    {
      "id": 12,
      "username": "jsmith",
      "role": "user",
      "frozen_until": null,
      "blacklisted_at": null,
      "deleted_at": null,
      "created_at": "2026-01-01T00:00:00Z"
    }
  ],
  "next_cursor": null
}
```

### `GET /api/users/{id}`
Single user detail.
`200 OK` → `{ "id": 12, "username": "jsmith", "role": "user", "frozen_until": null, "blacklisted_at": null, "created_at": "..." }`.

### `PUT /api/users/{id}` / `PATCH /api/users/{id}` — update user
All fields optional.
```json
{ "username": "jsmith2", "role": "admin", "password": "newpass", "email": "new@site.local" }
```
`200 OK` → `{ "id": 12, "username": "jsmith2", "role": "admin" }`.

### `PATCH /api/users/{id}/freeze`
```json
{ "duration_hours": 72 }
```
`200 OK` → `{ "id": 12, "username": "jsmith", "role": "user", "frozen_until": "2026-04-20T12:00:00Z", "blacklisted_at": null, "deleted_at": null, "created_at": "..." }`.

### `PATCH /api/users/{id}/unfreeze`
No request body. Clears `frozen_until`.
`200 OK` → user object (same shape as freeze response).

### `PATCH /api/users/{id}/blacklist`
No request body. Sets `blacklisted_at` to now and revokes all tokens immediately.
`200 OK` → user object (same shape as freeze response).

### `DELETE /api/users/{id}`
Soft-delete; cascading purge runs at day 30. `204 No Content`.

---

## 4. Media Library

### `GET /api/assets`
Paginated asset listing.

**Query parameters:**
| Param    | Description                                                                      |
|----------|----------------------------------------------------------------------------------|
| `cursor` | Pagination cursor (asset id).                                                    |
| `limit`  | Results per page (default 25, max 100).                                          |
| `sort`   | `newest` (default) \| `most_played`.                                             |
| `status` | Admin only: `ready` (default) \| `processing` \| `failed` \| `all`.             |

Non-admin callers always see only `status=ready` assets.

`200 OK`:
```json
{
  "items": [
    {
      "id": 417,
      "title": "Overnight Safety Reminder",
      "description": "...",
      "mime": "video/mp4",
      "duration_seconds": 95,
      "size_bytes": 14205952,
      "status": "ready",
      "thumbnail_urls": { "160": "...", "480": "...", "960": "..." },
      "tags": ["safety", "overnight"],
      "played_count": 284,
      "created_at": "2026-03-02T09:14:00Z"
    }
  ],
  "next_cursor": "eyJpZCI6NDE3fQ=="
}
```

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
| `per_page`     | integer      | Results per page.                                                 |

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
- `201 Created` → `{ "id": 418, "title": "...", "status": "processing", "mime": "video/mp4", "duration_seconds": 120 }` while thumbnails/indexing queue.
- `422` per-file error:
```json
{
  "message": "Declared MP4 but magic bytes do not match",
  "reason_code": "magic_mismatch"
}
```

### `GET /api/assets/{id}`
Single asset detail, including size/MIME/duration.

Non-admins receive `404` for assets not in `status=ready` (presence is not leaked). Admins additionally receive `file_path` and `fingerprint_sha256` fields.

### `DELETE /api/assets/{id}` (Admin only)
- `409 Conflict` → `{ "message": "Asset is referenced in one or more playlists and cannot be deleted.", "reference_count": 2 }` when referenced.
- `204 No Content` when free.

### `POST /api/assets/{id}/play`
Records a play event and appends to play history.

**Request** (optional body):
```json
{ "session_id": "sess-abc123", "context": "search" }
```

`202 Accepted` → play history entry:
```json
{
  "id": 9001,
  "user_id": 12,
  "asset_id": 417,
  "played_at": "2026-04-24T10:00:00Z",
  "session_id": "sess-abc123",
  "context": "search"
}
```

### `POST /api/admin/assets/{id}/replace` (Admin only, multipart/form-data)
Atomically replaces an asset file and remaps all references (playlist items, favorites, history, recommendation candidates) from the old asset to the new one. The old asset is soft-deleted.

Fields: `file` (required), `title`, `description`, `tags[]` (all optional; defaults to old asset values).

`201 Created`:
```json
{
  "old_asset_id": 417,
  "new_asset_id": 418,
  "remapped_playlists": 3,
  "remapped_favorites": 7,
  "remapped_history": 284,
  "remapped_candidates": 2
}
```

---

## 5. Favorites

### `GET /api/favorites?cursor=`
Returns the current user's favorited assets (cursor-paginated).

### `PUT /api/favorites/{asset_id}`
Idempotent add. `204 No Content`.

### `DELETE /api/favorites/{asset_id}`
Idempotent remove. `204 No Content`.

---

## 6. Playlists

### `GET /api/playlists`
Current user's playlists (includes `items_count`).

### `POST /api/playlists`
Creates an empty playlist. Items are added individually via `POST /api/playlists/{id}/items`.
```json
{ "name": "Morning Gate Checks" }
```
`201 Created` → `{ "id": 91, "name": "Morning Gate Checks", "owner_id": 12, "created_at": "..." }`.

### `GET /api/playlists/{id}` — full playlist with items.
Items whose underlying asset is not `status=ready` have `title`/`mime` scrubbed to `null` for non-admin callers and `status` collapsed to `"unavailable"`.

### `PATCH /api/playlists/{id}`
Updates name and/or reorders items. `item_order` is an array of **playlist item IDs** in the desired order.
```json
{ "name": "Updated name", "item_order": [23, 21, 22] }
```
`200 OK` → `{ "id": 91, "name": "Updated name" }`.

### `DELETE /api/playlists/{id}` — `204 No Content`.

### `POST /api/playlists/{id}/items`
Adds a single asset to the end of a playlist. Asset must be `status=ready` for non-admin callers.
```json
{ "asset_id": 417 }
```
`201 Created`:
```json
{ "id": 23, "playlist_id": 91, "asset_id": 417, "position": 1 }
```
- `404` if asset not found.
- `422` with `reason_code: "asset_not_ready"` if asset is not ready (non-admin).
- `403` if caller does not own the playlist.

### `DELETE /api/playlists/{id}/items/{itemId}`
Removes a playlist item. `204 No Content`.
- `403` if caller does not own the playlist.
- `404` if item not found in this playlist.

### `PUT /api/playlists/{id}/items/order`
Reorders all items in a playlist. Accepts an ordered array of **playlist item IDs**; position is derived from array index.
```json
{ "item_ids": [23, 21, 22] }
```
`200 OK` → array of all items in new order:
```json
[
  { "id": 23, "playlist_id": 91, "asset_id": 417, "position": 1 },
  { "id": 21, "playlist_id": 91, "asset_id": 502, "position": 2 },
  { "id": 22, "playlist_id": 91, "asset_id": 611, "position": 3 }
]
```

### `POST /api/playlists/{id}/share`
Generates a share code (LAN-internal redemption). Expires in 7 days by default.
Optional body: `{ "expires_in_hours": 48 }`.
`201 Created`:
```json
{ "id": 5, "code": "X7QK4N2P", "expires_at": "2026-04-18T12:00:00Z" }
```
Rate-limited to 5/hour/user (Redis-backed). Returns `429` with `{ "message": "...", "retry_after": <seconds> }`.

### `POST /api/playlists/redeem`
```json
{ "code": "X7QK4N2P" }
```
`201 Created` → cloned playlist object:
```json
{ "id": 92, "name": "Morning Gate Checks (shared)", "owner_id": 12, "created_at": "..." }
```
- `404` if code unknown.
- `410 Gone` if code expired or revoked.
- `403` if the original owner is blacklisted, frozen, or deleted.

### `DELETE /api/playlists/shares/{id}` — revoke share.
`200 OK` → `{ "message": "Share revoked." }`.

---

## 7. Now Playing / History

### `GET /api/history?cursor=&per_page=`
Last played items for the current user (cursor-paginated, most recent first).

| Param      | Description                                  |
|------------|----------------------------------------------|
| `cursor`   | Pagination cursor (play history entry id).   |
| `per_page` | Results per page (default 25, max 100).      |

`200 OK`:
```json
{
  "items": [
    {
      "id": 9001,
      "asset_id": 417,
      "played_at": "2026-04-24T10:00:00Z",
      "session_id": "sess-abc123",
      "context": "search",
      "asset": { "id": 417, "title": "Overnight Safety Reminder", "mime": "video/mp4" }
    }
  ],
  "next_cursor": "9000"
}
```

### `GET /api/history/sessions?limit=`
Groups the caller's play history by `session_id`. Sessions are ordered by most recent activity.

| Param   | Description                              |
|---------|------------------------------------------|
| `limit` | Max sessions returned (default 20, max 100). |

`200 OK`:
```json
{
  "sessions": [
    {
      "session_id": "sess-abc123",
      "started_at": "2026-04-24T09:50:00Z",
      "ended_at": "2026-04-24T10:05:00Z",
      "play_count": 3,
      "context": "search",
      "items": [
        {
          "id": 9001,
          "asset_id": 417,
          "played_at": "2026-04-24T10:00:00Z",
          "context": "search",
          "asset": { "id": 417, "title": "Overnight Safety Reminder", "mime": "video/mp4" }
        }
      ]
    }
  ]
}
```
Plays with `session_id = null` are grouped into a single `"__unassigned__"` bucket (returned as `session_id: null`).

### `GET /api/now-playing`
Returns the current session's most recent play and up to 9 prior plays.

`200 OK`:
```json
{
  "current": {
    "id": 9001,
    "asset_id": 417,
    "played_at": "2026-04-24T10:00:00Z",
    "session_id": "sess-abc123",
    "context": "search",
    "asset": { "id": 417, "title": "Overnight Safety Reminder", "mime": "video/mp4" }
  },
  "recent": [...],
  "current_session_id": "sess-abc123"
}
```
`current` is `null` when no play history exists. `recent` follows the same item shape (excludes the current item).

---

## 8. Recommendations

### `GET /api/recommendations`
Returns up to 25 pre-computed candidates with `score` and `reason_tags`.
- Empty + `degraded: true` when the circuit breaker is open.

---

## 9. Device Events (Technician / Admin)

### `POST /api/devices/events` (Sanctum Bearer — technician or admin role)
### `POST /api/gateway/events` (X-Gateway-Token shared secret)

Both routes invoke the same handler. The gateway path uses an `X-Gateway-Token` shared-secret header instead of a Bearer token and is intended for machine-to-machine ingestion by the physical gateway service.

**Required header:**
- `X-Idempotency-Key: <uuid-v4>` — deduplication key (required on both paths).

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

**Optional gateway headers:**
- `X-Buffered: true` — set by the gateway when the event was buffered offline.
- `X-Buffered-At: <ISO-8601>` — timestamp when the gateway buffered the event.

**Responses:**
- `201 Created` → `{ "status": "accepted", "event_id": 930145 }` — first occurrence.
- `200 OK` → `{ "status": "duplicate", "message": "Event already processed.", "event_id": <original_id>, "audit_event_id": <new_audit_row_id>, "original_event_id": <original_id> }` — idempotency key seen within the 7-day window.
- `202 Accepted` → `{ "status": "out_of_order", "message": "...", "event_id": 930146, "expected_next": 184591 }` — sequence gap or regression detected; reconciliation dispatched.
- `410 Gone` → `{ "message": "Event is too old and will not be accepted.", "status": "too_old", "event_id": <audit_row_id> }` — `occurred_at` older than the 7-day acceptance window.

### `GET /api/devices`
Device roster. Returns array of device objects:
```json
[
  {
    "id": "gate-west-01",
    "kind": "gate-controller",
    "label": "West Gate",
    "last_sequence_no": 184592,
    "last_seen_at": "2026-04-17T09:42:11Z",
    "created_at": "2026-01-01T00:00:00Z"
  }
]
```

### `GET /api/devices/{id}`
Single device detail. Returns device object (same shape as list item).
- `404` if device not found.

### `GET /api/devices/{id}/events?status=&cursor=&limit=`
Audit-trail-style event listing, ordered by most recent first.

| Param    | Description                                                                     |
|----------|---------------------------------------------------------------------------------|
| `status` | Filter: `accepted` \| `duplicate` \| `out_of_order` \| `too_old`.              |
| `cursor` | Pagination cursor (event id). Next page returns events with id < cursor.        |
| `limit`  | Results per page (default 50, max 200).                                         |

`200 OK`:
```json
{
  "items": [
    {
      "id": 930145,
      "device_id": "gate-west-01",
      "idempotency_key": "550e8400-e29b-41d4-a716-446655440000",
      "sequence_no": 184592,
      "event_type": "gate.opened",
      "status": "accepted",
      "is_out_of_order": false,
      "buffered_by_gateway": false,
      "buffered_at": null,
      "occurred_at": "2026-04-17T09:42:11Z",
      "received_at": "2026-04-17T09:42:12Z",
      "payload_json": { "plate": "7K-VV-842", "lane": "W2" }
    }
  ],
  "next_cursor": "930100"
}
```

### `GET /api/devices/{id}/replay/audits`
Lists replay audit records for a device (most recent first, up to 100).
```json
[
  {
    "id": 1,
    "device_id": "gate-west-01",
    "initiated_by": 3,
    "since_sequence_no": 184500,
    "until_sequence_no": 184700,
    "reason": "Gateway resync",
    "created_at": "2026-04-17T10:00:00Z"
  }
]
```

### `POST /api/devices/{id}/replay` (Technician / Admin)
```json
{ "since_sequence_no": 184500, "until_sequence_no": 184700, "reason": "Gateway resync" }
```
`until_sequence_no` is optional. Creates a `replay_audits` record and re-emits events to downstream consumers **without** duplicating side effects (dedup by idempotency key still applies).

`201 Created` → replay audit object (same shape as items in the audits list above).

---

## 10. Monitoring (Admin only)

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

### `POST /api/monitoring/feature-flags/{flag}/reset`
Re-enables the named feature flag after a manual review. Currently the only valid value for `{flag}` is `recommended`.
`200 OK`.

---

## 11. Error Codes

| Code  | Meaning                                              |
|-------|------------------------------------------------------|
| 400   | Malformed request.                                   |
| 401   | Not authenticated / blacklisted (generic).           |
| 403   | Role or ownership check failed.                      |
| 404   | Resource not found / share code invalid.             |
| 409   | Conflict (e.g., asset still in a playlist).          |
| 410   | Resource no longer available (event too old; share code expired or revoked). |
| 422   | Validation errors.                                   |
| 423   | Account frozen.                                      |
| 429   | Rate limited.                                        |
| 500   | Server error (logged; reason masked in response).    |

---

## 12. Example End-to-End: Kiosk Search → Play

1. `GET /api/auth/me` — confirm session.
2. `GET /api/search?q=overnight&tags[]=safety&duration_lt=120&sort=recommended`.
3. Response `X-Recommendation-Degraded: false` — display "Recommended" chips.
4. User taps an item → `POST /api/assets/417/play { "session_id": "sess-abc123", "context": "search" }` (fire-and-forget; `202`).
5. `GET /api/now-playing` refreshes the panel.

---

## 13. Example End-to-End: Device Replay

1. Technician opens `/devices/gate-west-01`.
2. `GET /api/devices/gate-west-01` — confirm device exists and check `last_sequence_no`.
3. `GET /api/devices/gate-west-01/events?status=out_of_order` — inspects the gap.
4. `POST /api/devices/gate-west-01/replay { "since_sequence_no": 184500, "reason": "LAN outage 09:42" }` → `201`.
5. `GET /api/devices/gate-west-01/replay/audits` — confirms the audit row was recorded.
6. Subsequent events arrive with `status: duplicate` proving idempotency.
