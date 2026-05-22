# SmartPark API Specification

## Overview

All API endpoints are served under the `/api` prefix by the Laravel backend.
The frontend nginx reverse-proxy forwards requests matching `/api/*` from
`http://localhost:3000` to the internal backend at `http://backend:9000`.

**Base URL (internal):** `http://backend:9000/api`  
**Base URL (via frontend proxy):** `http://localhost:3000/api`

---

## Authentication

### Mechanism

Session-based authentication using **Laravel Sanctum** with database-backed
sessions. On login the server issues a session cookie (`laravel_session`) and
XSRF token cookie (`XSRF-TOKEN`). Subsequent requests must carry both cookies
and include the decoded XSRF value in an `X-XSRF-TOKEN` header (handled
automatically by Axios).

All protected endpoints require `auth:sanctum` middleware. Role-restricted
endpoints additionally require the `role:{admin|technician}` middleware alias
(implemented by `App\Http\Middleware\RequireRole`).

### Device API Authentication

Device ingestion endpoints use `DeviceApiKeyAuth` middleware.
Devices authenticate via an `Authorization: Bearer <api_key>` header.
The raw key is never stored; the server compares
`hash('sha256', $rawKey)` against `devices.api_key_hash`.

### Roles

| Role | Description |
|------|-------------|
| `user` | Regular authenticated user (default) |
| `admin` | Full access to admin console |
| `technician` | Access to technician device console |

---

## Error Format

All error responses follow this envelope:

```json
{
  "message": "Human-readable description",
  "errors": {
    "field": ["Validation message"]
  }
}
```

`errors` is only present on validation failures (HTTP 422).

### Common HTTP Status Codes

| Code | Meaning |
|------|---------|
| 200 | OK |
| 201 | Created |
| 204 | No Content (successful delete) |
| 401 | Unauthenticated |
| 403 | Forbidden (wrong role) |
| 404 | Not Found |
| 409 | Conflict (e.g. asset in use by a playlist) |
| 422 | Validation error |
| 503 | Service unavailable (e.g. share-code collision exhausted) |

---

## Health

### `GET /api/health`

Public. No authentication required.

**Response 200**
```json
{
  "status": "ok",
  "timestamp": "2026-05-23T09:00:00+00:00"
}
```

---

## Authentication Endpoints

### `POST /api/auth/login`

Authenticate a user and start a session.

**Request**
```json
{
  "username": "string (max 50, required)",
  "password": "string (required)"
}
```

**Response 200**
```json
{
  "message": "Logged in.",
  "user": {
    "id": 1,
    "name": "Regular User",
    "username": "user",
    "email": "u***@smartpark.local",
    "role": "user",
    "account_status": "active",
    "frozen_until": null,
    "created_at": "2026-01-01T00:00:00.000000Z"
  }
}
```

**Response 401** — invalid credentials, frozen account, or blacklisted account.
```json
{ "message": "Invalid credentials." }
```

---

### `POST /api/auth/logout`

**Auth:** `auth:sanctum`

Invalidates the current session.

**Response 200**
```json
{ "message": "Logged out." }
```

---

### `GET /api/auth/me`

**Auth:** `auth:sanctum`

Returns the currently authenticated user.

**Response 200**
```json
{
  "user": { /* UserResource — see schema below */ }
}
```

---

## Assets

### `GET /api/assets`

**Auth:** `auth:sanctum`

Search and filter the approved asset library.

**Query Parameters**

| Parameter | Type | Description |
|-----------|------|-------------|
| `q` | string | Full-text search against title, description, and tags |
| `tags[]` | string[] | Filter by one or more tags (intersection) |
| `duration_max` | integer | Max duration in seconds |
| `recency_days` | integer | Limit to assets uploaded within N days |
| `sort` | string | `newest` (default) \| `most_played` \| `recommended` |

**Response 200**
```json
{
  "data": [ /* AssetResource[] */ ],
  "meta": {
    "total": 42,
    "per_page": 20,
    "current_page": 1
  }
}
```

When `sort=recommended`, each item includes `recommendation_reason` and
`recommendation_score`. If the recommendation engine is degraded, the response
falls back to `most_played` ordering.

---

### `POST /api/assets`

**Auth:** `auth:sanctum`

Upload a new asset. Submitted as `multipart/form-data`.

**Request Fields**

| Field | Type | Rules |
|-------|------|-------|
| `file` | file | Required. Max 262 MB. Allowed MIME: `image/jpeg`, `image/png`, `application/pdf`, `audio/mpeg`, `video/mp4`. MIME-sniffed and fingerprint-validated. |
| `title` | string | Required. Max 255 characters. |
| `description` | string | Optional. Max 5,000 characters. |
| `tags[]` | string[] | Optional. Array of tag strings. |
| `duration` | integer | Optional. Duration in seconds. |

**Response 201**
```json
{ "data": { /* AssetResource */ } }
```

Triggers async jobs: `GenerateThumbnailsJob`, `IndexAssetJob`.
Newly uploaded assets have `status: "pending"` until approved by an admin.

**Response 422** — validation error (invalid MIME, file too large, etc.)

---

### `GET /api/assets/{asset}`

**Auth:** `auth:sanctum`

Retrieve a single asset. Non-approved assets return 404 unless the requester
is the uploader or an admin.

**Response 200**
```json
{ "data": { /* AssetResource */ } }
```

---

### `DELETE /api/assets/{asset}`

**Auth:** `auth:sanctum`

Delete an asset. Fails with 409 if the asset is referenced by any playlist.

**Response 204** — no body.

**Response 409**
```json
{ "message": "Asset is referenced by one or more playlists." }
```

---

## Favorites

### `GET /api/favorites`

**Auth:** `auth:sanctum`

List the authenticated user's favorited assets.

**Response 200**
```json
{
  "data": [ /* AssetResource[] */ ]
}
```

---

### `POST /api/favorites`

**Auth:** `auth:sanctum`

Add an asset to favorites. Idempotent — returns the existing record if already
favorited (restores soft-deleted records).

**Request**
```json
{ "asset_id": 7 }
```

**Response 201**
```json
{ "data": { "favorite_id": 14 } }
```

**Response 200** — already favorited (returns existing).

---

### `DELETE /api/favorites/{favorite}`

**Auth:** `auth:sanctum`

Remove an asset from favorites (soft-delete).

**Response 204** — no body.

---

## Playlists

### `GET /api/playlists`

**Auth:** `auth:sanctum`

List all playlists owned by the authenticated user.

**Response 200**
```json
{ "data": [ /* PlaylistResource[] */ ] }
```

---

### `POST /api/playlists`

**Auth:** `auth:sanctum`

Create a new playlist. Automatically generates a unique 8-character
alphanumeric share code.

**Request**
```json
{
  "name": "Morning Announcements",
  "description": "Optional description up to 1000 chars"
}
```

**Response 201**
```json
{ "data": { /* PlaylistResource */ } }
```

**Response 503** — share code generation failed after 10 retries (collision exhaustion).

---

### `POST /api/playlists/redeem`

**Auth:** `auth:sanctum`

Import a playlist by entering its share code. Creates a copy of the playlist
in the authenticated user's account.

**Request**
```json
{ "share_code": "ABCD1234" }
```

**Response 200**
```json
{ "data": { "playlist": { /* PlaylistResource */ } } }
```

**Response 404** — share code not found.

---

### `GET /api/playlists/{playlist}`

**Auth:** `auth:sanctum`

Retrieve a single playlist with its items and associated asset data.

**Response 200**
```json
{ "data": { /* PlaylistResource with items */ } }
```

---

### `PATCH /api/playlists/{playlist}`

**Auth:** `auth:sanctum`

Update a playlist's name or description.

**Request** (all fields optional)
```json
{
  "name": "Updated Name",
  "description": "Updated description"
}
```

**Response 200**
```json
{ "data": { /* PlaylistResource */ } }
```

---

### `DELETE /api/playlists/{playlist}`

**Auth:** `auth:sanctum`

Delete a playlist (soft-delete).

**Response 204** — no body.

---

### `POST /api/playlists/{playlist}/items`

**Auth:** `auth:sanctum`

Append an asset to a playlist at an optional position.

**Request**
```json
{
  "asset_id": 5,
  "position": 2
}
```

`position` is optional; omitting it appends to the end.

**Response 201**
```json
{
  "data": {
    "item_id": 12,
    "playlist_id": 3,
    "asset_id": 5,
    "position": 2
  }
}
```

---

### `DELETE /api/playlists/{playlist}/items/{item}`

**Auth:** `auth:sanctum`

Remove an item from a playlist.

**Response 204** — no body.

---

## Play History

### `GET /api/play-history`

**Auth:** `auth:sanctum`

Retrieve the authenticated user's 50 most recent plays.

**Response 200**
```json
{ "data": [ /* AssetResource[] with played_at timestamps */ ] }
```

---

### `POST /api/play-history`

**Auth:** `auth:sanctum`

Record a play event for an asset.

**Request**
```json
{ "asset_id": 7 }
```

**Response 201**
```json
{
  "data": {
    "id": 99,
    "played_at": "2026-05-23T09:15:00.000000Z"
  }
}
```

Side effects:
- Increments `assets.play_count`
- Dispatches `ComputeUserRecommendationsJob` (unique per user)
- Records recommendation hit/miss metric if sort was `recommended`

---

## Recommendations

### `GET /api/recommendations`

**Auth:** `auth:sanctum`

Return personalized recommended assets for the authenticated user (up to 20).

**Response 200**
```json
{
  "data": [
    {
      "asset": { /* AssetResource */ },
      "recommendation_reason": "Based on your favorites: Safety, Training",
      "recommendation_score": 0.8750
    }
  ],
  "fallback": false
}
```

`fallback: true` indicates the recommendation engine is degraded and results
are sorted by `most_played` instead. Degradation triggers when API p95 latency
exceeds 800 ms for 5 minutes, or recommendation hit rate drops below 10%.

**Recommendation Reason Strings**

| Reason | Condition |
|--------|-----------|
| `"Based on your favorites: Tag1, Tag2"` | User has favorited assets sharing tags with the recommended asset |
| `"Based on your listening history"` | Recommended asset shares tags with recently played assets |
| `"Popular in your facility"` | Fallback reason when no personal signal is available |

---

## Admin — Users

All admin endpoints require `auth:sanctum` + `role:admin`.

### `GET /api/admin/users`

List all users (including soft-deleted), paginated 50 per page.

**Response 200**
```json
{
  "data": [ /* UserResource[] */ ],
  "meta": { "total": 3, "page": 1, "per_page": 50 }
}
```

---

### `PATCH /api/admin/users/{user}/freeze`

Freeze a user account for a defined period.

**Request**
```json
{ "duration_hours": 72 }
```

`duration_hours`: integer, 1–8760 (1 hour to 1 year).

**Response 200**
```json
{ "data": { /* UserResource with account_status: "frozen", frozen_until populated */ } }
```

---

### `PATCH /api/admin/users/{user}/blacklist`

Permanently blacklist a user account.

**Response 200**
```json
{ "data": { /* UserResource with account_status: "blacklisted" */ } }
```

---

### `DELETE /api/admin/users/{user}`

Soft-delete a user. The record is retained for 30 days before hard purge.

**Response 204** — no body.

---

## Admin — Assets

### `GET /api/admin/assets`

List all assets, optionally filtered by approval status, paginated 50 per page.

**Query Parameters**

| Parameter | Type | Values |
|-----------|------|--------|
| `status` | string | `pending` \| `approved` \| `rejected` (optional; omit for all) |

**Response 200**
```json
{
  "data": [ /* AssetResource[] */ ],
  "meta": { "total": 10, "page": 1, "per_page": 50 }
}
```

---

### `PATCH /api/admin/assets/{asset}/approve`

Approve a pending asset, making it visible in the library.

**Response 200**
```json
{ "data": { /* AssetResource with status: "approved" */ } }
```

---

### `PATCH /api/admin/assets/{asset}/reject`

Reject a pending asset.

**Response 200**
```json
{ "data": { /* AssetResource with status: "rejected" */ } }
```

---

## Admin — Dashboard

### `GET /api/admin/dashboard`

Aggregate operational statistics.

**Response 200**
```json
{
  "data": {
    "stats": {
      "users": {
        "total": 3,
        "active": 2,
        "frozen": 1,
        "blacklisted": 0
      },
      "assets": {
        "total": 10,
        "pending": 2,
        "approved": 7,
        "rejected": 1
      },
      "plays": {
        "total": 512,
        "last_24h": 38,
        "last_7d": 210
      },
      "devices": {
        "total": 2,
        "active_last_24h": 2
      }
    }
  }
}
```

---

## Admin — Monitoring

### `GET /api/admin/monitoring`

Real-time system health metrics. No third-party telemetry — all data is
derived from the local database and in-memory counters.

**Response 200**
```json
{
  "data": {
    "health": "ok",
    "queue": {
      "pending_jobs": 0,
      "failed_jobs": 0
    },
    "recommendations": {
      "engine_status": "active",
      "window_seconds": 300,
      "p95_latency_ms": 120,
      "hit_rate": 0.45,
      "thresholds": {
        "max_p95_latency_ms": 800,
        "min_hit_rate": 0.10
      }
    },
    "api_errors": {
      "rate_per_minute": 0.2
    },
    "device_ingestion": {
      "received_last_hour": 145,
      "duplicates_last_hour": 3,
      "late_last_hour": 1,
      "buffered_last_hour": 0
    },
    "timestamp": "2026-05-23T09:00:00+00:00"
  }
}
```

---

## Admin — Device Replays

### `POST /api/admin/device-replays`

Create a replay audit record, authorizing a device to retransmit a range of
events. The returned `audit_key` must be included in replayed device events as
`replay_audit_id` to prevent double-applying side effects.

**Request**
```json
{
  "device_id": 1,
  "scope": "2026-05-20T00:00:00Z/2026-05-21T00:00:00Z",
  "reason": "Gateway outage recovery"
}
```

**Response 201**
```json
{
  "data": {
    "id": 5,
    "audit_key": "A1B2C3D4E5F6G7H8",
    "device_id": 1,
    "triggered_by": 1,
    "scope": "2026-05-20T00:00:00Z/2026-05-21T00:00:00Z",
    "reason": "Gateway outage recovery",
    "triggered_at": "2026-05-23T09:00:00.000000Z"
  }
}
```

---

## Technician — Device Console

All technician endpoints require `auth:sanctum` + `role:technician`.

### `GET /api/technician/devices`

List all registered parking devices.

**Response 200**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Gate-001",
      "device_type": "gate",
      "last_sequence": 412,
      "last_event_at": "2026-05-23T08:55:00.000000Z",
      "created_at": "2026-01-01T00:00:00.000000Z"
    }
  ]
}
```

---

### `GET /api/technician/events`

Retrieve device events, optionally filtered, paginated 50 per page.

**Query Parameters**

| Parameter | Type | Description |
|-----------|------|-------------|
| `device_id` | integer | Filter by device |
| `status` | string | `received` \| `late` \| `buffered` \| `duplicate` |

**Response 200**
```json
{
  "data": [
    {
      "id": 991,
      "device_id": 1,
      "event_type": "gate_open",
      "payload": {},
      "idempotency_key": "uuid-abc-123",
      "sequence": 412,
      "status": "received",
      "replay_audit_id": null,
      "received_at": "2026-05-23T08:55:00.000000Z"
    }
  ],
  "meta": { "total": 991, "page": 1, "per_page": 50 }
}
```

---

## Device Ingestion

### `POST /api/device/events`

**Auth:** Device API key via `Authorization: Bearer <api_key>` header.

Ingest a single event from a parking device (gate, camera, or geomagnetic
sensor). Implements idempotency, out-of-order sequence correction, and
controlled replay.

**Request**
```json
{
  "device_id": 1,
  "event_type": "gate_open",
  "payload": { "vehicle_id": "ABC123", "lane": 2 },
  "idempotency_key": "550e8400-e29b-41d4-a716-446655440000",
  "sequence": 413,
  "replay_audit_id": null
}
```

| Field | Type | Rules |
|-------|------|-------|
| `device_id` | integer | Required. Must match the authenticated device. |
| `event_type` | string | Required. Max 100 characters. |
| `payload` | object | Required. Arbitrary JSON payload. |
| `idempotency_key` | string | Required. Max 255 characters. Unique per device within a 7-day window. |
| `sequence` | integer | Required. Monotonically increasing per-device counter. |
| `replay_audit_id` | string | Optional. Must reference an existing `device_replay_audits.audit_key` for this device when replaying. |

**Response 201** — new event accepted
```json
{ "id": 992, "status": "received" }
```

**Response 200** — duplicate (idempotency key already seen within 7-day window)
```json
{ "id": 980, "status": "duplicate" }
```

**Event Status Values**

| Status | Condition |
|--------|-----------|
| `received` | Normal, in-order event |
| `late` | `sequence` < `device.last_sequence` (out-of-order, stored but flagged) |
| `buffered` | Event arrives with a valid `replay_audit_id` (controlled replay) |
| `duplicate` | Idempotency key already exists for this device within 7 days |

---

## Resource Schemas

### UserResource

```json
{
  "id": 1,
  "name": "Regular User",
  "username": "user",
  "email": "u***@smartpark.local",
  "role": "user",
  "account_status": "active",
  "frozen_until": null,
  "created_at": "2026-01-01T00:00:00.000000Z"
}
```

Email is masked in log output. `frozen_until` is a UTC timestamp ISO 8601
string when `account_status` is `"frozen"`, otherwise `null`.

---

### AssetResource

```json
{
  "id": 5,
  "title": "Parking Lot A Announcement",
  "description": "Welcome message for Lot A visitors",
  "tags": ["announcement", "welcome", "lot-a"],
  "mime_type": "audio/mpeg",
  "file_size": 524288,
  "duration": 30,
  "status": "approved",
  "play_count": 45,
  "thumbnail_160": "media/thumbs/asset-5-160.jpg",
  "thumbnail_480": null,
  "thumbnail_960": null,
  "uploaded_by": 1,
  "recommendation_reason": null,
  "recommendation_score": null,
  "created_at": "2026-01-01T00:00:00.000000Z",
  "updated_at": "2026-01-15T00:00:00.000000Z"
}
```

Thumbnails are only generated for `image/jpeg` and `image/png` assets.
`recommendation_reason` and `recommendation_score` are populated only when
the asset is returned as part of a `?sort=recommended` query.

---

### PlaylistResource

```json
{
  "id": 3,
  "user_id": 2,
  "name": "Morning Announcements",
  "description": null,
  "share_code": "MORN1234",
  "items": [
    {
      "id": 12,
      "asset_id": 5,
      "position": 0,
      "asset": { /* AssetResource */ }
    }
  ],
  "created_at": "2026-05-01T00:00:00.000000Z",
  "updated_at": "2026-05-15T00:00:00.000000Z"
}
```
