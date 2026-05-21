# API Specification

**Base URL:** `http://localhost:8080/api`  
**Auth:** Session-based via Laravel Sanctum (cookie). Obtain a session by `POST /auth/login`.  
**Device Auth:** API key passed as `X-Device-Key` header (separate auth for IoT device endpoints).  
**Content-Type:** `application/json` unless noted (asset upload uses `multipart/form-data`).

---

## Table of Contents

1. [System](#1-system)
2. [Authentication](#2-authentication)
3. [Assets](#3-assets)
4. [Favorites](#4-favorites)
5. [Playlists](#5-playlists)
6. [Play History](#6-play-history)
7. [Recommendations](#7-recommendations)
8. [Admin — Users](#8-admin--users)
9. [Admin — Assets](#9-admin--assets)
10. [Admin — Dashboard](#10-admin--dashboard)
11. [Admin — Monitoring](#11-admin--monitoring)
12. [Admin — Device Replay Audits](#12-admin--device-replay-audits)
13. [Technician — Console](#13-technician--console)
14. [Device — Event Ingestion](#14-device--event-ingestion)

---

## Common Response Schemas

### Asset Object
```json
{
  "id": 1,
  "title": "string",
  "description": "string | null",
  "tags": ["string"],
  "mime_type": "audio/mpeg",
  "file_size": 2048000,
  "duration": 180,
  "status": "pending | approved | rejected",
  "play_count": 42,
  "thumbnail_160": "string | null",
  "thumbnail_480": "string | null",
  "thumbnail_960": "string | null",
  "uploaded_by": 1,
  "created_at": "2026-01-01T00:00:00+00:00",
  "updated_at": "2026-01-01T00:00:00+00:00"
}
```

### User Object
```json
{
  "id": 1,
  "name": "string",
  "username": "string",
  "email": "jo**@example.com",
  "role": "user | technician | admin",
  "account_status": "active | frozen | blacklisted",
  "frozen_until": "2026-01-01T00:00:00+00:00 | null",
  "created_at": "2026-01-01T00:00:00+00:00"
}
```

### Playlist Object
```json
{
  "id": 1,
  "user_id": 1,
  "name": "string",
  "description": "string | null",
  "share_code": "ABCD1234",
  "items": [
    {
      "id": 1,
      "position": 0,
      "asset": { "...Asset Object..." }
    }
  ],
  "created_at": "2026-01-01T00:00:00+00:00",
  "updated_at": "2026-01-01T00:00:00+00:00"
}
```

### Error Object
```json
{
  "message": "Human-readable description.",
  "errors": {
    "field": ["Validation message."]
  }
}
```

---

## 1. System

### `GET /health`
Public health check.

**Auth:** None

**Response `200`**
```json
{
  "status": "ok",
  "timestamp": "2026-01-01T00:00:00+00:00"
}
```

---

## 2. Authentication

### `POST /auth/login`
Authenticate a user and establish a session.

**Auth:** None

**Request Body**
| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `username` | string | Yes | |
| `password` | string | Yes | |

**Responses**

`200 OK`
```json
{
  "message": "Logged in.",
  "user": { "...User Object..." }
}
```

`401 Unauthorized` — invalid credentials, blacklisted account, or frozen account
```json
{
  "message": "Invalid credentials. | Account has been permanently suspended. | Account is temporarily frozen.",
  "errors": []
}
```

---

### `POST /auth/logout`
Invalidate the current session.

**Auth:** Sanctum (session)

**Response `200`**
```json
{ "message": "Logged out." }
```

---

### `GET /auth/me`
Return the authenticated user's profile.

**Auth:** Sanctum (session)

**Response `200`**
```json
{
  "user": { "...User Object..." }
}
```

---

## 3. Assets

### `GET /assets`
List approved assets with optional filtering and sorting.

**Auth:** Sanctum (session)

**Query Parameters**
| Parameter | Type | Notes |
|-----------|------|-------|
| `q` | string | Full-text search term (boolean mode). |
| `tags` | string[] | Filter by tags; pass multiple: `tags[]=jazz&tags[]=piano`. |
| `duration_max` | integer | Maximum duration in seconds. |
| `recency_days` | integer | Limit to assets uploaded within N days. |
| `sort` | string | `newest` (default), `most_played`, `recommended`. |

**Response `200`** — paginated, 20 per page
```json
{
  "data": [ { "...Asset Object..." } ],
  "links": { "...Laravel pagination links..." },
  "meta": { "...Laravel pagination meta..." }
}
```

---

### `POST /assets`
Upload a new media asset. Status is set to `pending` until an admin approves it.

**Auth:** Sanctum (session)  
**Content-Type:** `multipart/form-data`

**Request Fields**
| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `file` | file | Yes | Max 256 MB. MIME type validated server-side. |
| `title` | string | Yes | Max 255 characters. |
| `description` | string | No | Max 5000 characters. |
| `tags` | string[] | No | Each tag max 50 characters. |
| `duration` | integer | No | Duration in seconds, min 1. |

**Responses**

`201 Created`
```json
{ "data": { "...Asset Object..." } }
```

`409 Conflict` — file failed security scan
```json
{ "message": "File failed security scan.", "errors": { "file": ["..."] } }
```

`422 Unprocessable Entity` — invalid MIME type or validation failure
```json
{ "message": "...", "errors": { "file": ["..."] } }
```

---

### `GET /assets/{asset}`
Retrieve a single asset.

**Auth:** Sanctum (session)

**Notes:**
- Non-admin users can only view `approved` assets or assets they uploaded.
- Returns `404` for non-approved assets owned by other users.

**Response `200`**
```json
{ "data": { "...Asset Object..." } }
```

`404 Not Found`
```json
{ "message": "Resource not found.", "errors": [] }
```

---

### `DELETE /assets/{asset}`
Delete an asset. Owner or admin only.

**Auth:** Sanctum (session)

**Responses**

`204 No Content`

`403 Forbidden` — not the owner and not an admin
```json
{ "message": "Forbidden.", "errors": [] }
```

`409 Conflict` — asset is referenced by one or more playlists
```json
{ "message": "Asset is referenced by one or more playlists and cannot be deleted.", "errors": [] }
```

---

## 4. Favorites

### `GET /favorites`
List the authenticated user's favorited assets.

**Auth:** Sanctum (session)

**Response `200`**
```json
{
  "data": [
    {
      "favorite_id": 1,
      "asset": { "...Asset Object..." }
    }
  ]
}
```

---

### `POST /favorites`
Add an asset to favorites. Idempotent — re-favorites a previously removed entry.

**Auth:** Sanctum (session)

**Request Body**
| Field | Type | Required |
|-------|------|----------|
| `asset_id` | integer | Yes |

**Responses**

`201 Created` — new favorite or restored favorite
```json
{ "data": { "favorite_id": 1 } }
```

`200 OK` — already favorited (no change)
```json
{ "data": { "favorite_id": 1 } }
```

---

### `DELETE /favorites/{favorite}`
Remove a favorite. Soft-deletes the record.

**Auth:** Sanctum (session)

**Responses**

`204 No Content`

`403 Forbidden` — favorite belongs to another user
```json
{ "message": "Forbidden.", "errors": [] }
```

---

## 5. Playlists

### `GET /playlists`
List all playlists owned by the authenticated user, with their items.

**Auth:** Sanctum (session)

**Response `200`**
```json
{ "data": [ { "...Playlist Object..." } ] }
```

---

### `POST /playlists`
Create a new playlist. A unique 8-character `share_code` is auto-generated.

**Auth:** Sanctum (session)

**Request Body**
| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `name` | string | Yes | Max 255 characters. |
| `description` | string | No | Max 1000 characters. |

**Responses**

`201 Created`
```json
{ "data": { "...Playlist Object..." } }
```

`503 Service Unavailable` — could not generate a unique share code
```json
{ "message": "Could not generate a unique share code. Please try again.", "errors": [] }
```

---

### `POST /playlists/redeem`
Look up a playlist by share code (read-only; does not assign ownership).

**Auth:** Sanctum (session)

**Request Body**
| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `share_code` | string | Yes | Max 8 characters. |

**Responses**

`200 OK`
```json
{ "data": { "playlist": { "...Playlist Object..." } } }
```

`404 Not Found`
```json
{ "message": "Invalid share code.", "errors": [] }
```

---

### `GET /playlists/{playlist}`
Retrieve a single playlist with its items. Owner only.

**Auth:** Sanctum (session)

**Responses**

`200 OK`
```json
{ "data": { "...Playlist Object..." } }
```

`403 Forbidden`
```json
{ "message": "Forbidden.", "errors": [] }
```

---

### `PATCH /playlists/{playlist}`
Update a playlist's name or description. Owner only.

**Auth:** Sanctum (session)

**Request Body**
| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `name` | string | No | Max 255 characters. |
| `description` | string | No | Max 1000 characters, nullable. |

**Response `200`**
```json
{ "data": { "...Playlist Object..." } }
```

---

### `DELETE /playlists/{playlist}`
Delete a playlist. Owner only.

**Auth:** Sanctum (session)

**Response `204 No Content`**

---

### `POST /playlists/{playlist}/items`
Add an asset to a playlist. Owner only.

**Auth:** Sanctum (session)

**Request Body**
| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `asset_id` | integer | Yes | Must exist in `assets` table. |
| `position` | integer | No | Zero-based. Defaults to max position + 1. |

**Response `201 Created`**
```json
{
  "data": {
    "item_id": 1,
    "playlist_id": 1,
    "asset_id": 5,
    "position": 2
  }
}
```

---

### `DELETE /playlists/{playlist}/items/{item}`
Remove an item from a playlist. Owner only.

**Auth:** Sanctum (session)

**Responses**

`204 No Content`

`404 Not Found` — item does not belong to the specified playlist
```json
{ "message": "Resource not found.", "errors": [] }
```

---

## 6. Play History

### `GET /play-history`
Return the authenticated user's last 50 play history entries, newest first.

**Auth:** Sanctum (session)

**Response `200`**
```json
{
  "data": [
    {
      "id": 1,
      "played_at": "2026-01-01T00:00:00+00:00",
      "asset": { "...Asset Object..." }
    }
  ]
}
```

---

### `POST /play-history`
Record a play event for an asset. Increments the asset's `play_count` and triggers recommendation recomputation.

**Auth:** Sanctum (session)

**Request Body**
| Field | Type | Required |
|-------|------|----------|
| `asset_id` | integer | Yes |

**Response `201 Created`**
```json
{
  "data": {
    "id": 1,
    "played_at": "2026-01-01T00:00:00+00:00"
  }
}
```

---

## 7. Recommendations

### `GET /recommendations`
Return up to 20 personalized asset recommendations for the authenticated user.

**Auth:** Sanctum (session)

**Notes:**
- When the recommendation engine is in degraded state (high latency or low hit rate), falls back to top 20 most-played assets.
- When no scores exist for the user, falls back to most-played.

**Response `200`** — normal
```json
{
  "data": [
    {
      "...Asset Object fields...",
      "recommendation_reason": "Based on your favorites: Jazz, Piano",
      "recommendation_score": 0.87
    }
  ]
}
```

**Response `200`** — fallback mode
```json
{
  "data": [
    {
      "...Asset Object fields...",
      "recommendation_reason": "Popular in your facility",
      "recommendation_score": null
    }
  ],
  "fallback": true
}
```

---

## 8. Admin — Users

> All `/admin/*` endpoints require `role: admin`.

### `GET /admin/users`
List all users (including soft-deleted), paginated at 50 per page.

**Auth:** Sanctum + role:admin

**Response `200`**
```json
{
  "data": [ { "...User Object..." } ],
  "meta": {
    "total": 200,
    "page": 1,
    "per_page": 50
  }
}
```

---

### `PATCH /admin/users/{user}/freeze`
Temporarily freeze a user account.

**Auth:** Sanctum + role:admin

**Request Body**
| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `duration_hours` | integer | Yes | 1 – 8760 (1 year). |

**Response `200`**
```json
{ "data": { "...User Object..." } }
```

---

### `PATCH /admin/users/{user}/blacklist`
Permanently blacklist a user account.

**Auth:** Sanctum + role:admin

**Response `200`**
```json
{ "data": { "...User Object..." } }
```

---

### `DELETE /admin/users/{user}`
Soft-delete a user account.

**Auth:** Sanctum + role:admin

**Response `204 No Content`**

---

## 9. Admin — Assets

### `GET /admin/assets`
List all assets regardless of status, paginated at 50 per page.

**Auth:** Sanctum + role:admin

**Query Parameters**
| Parameter | Type | Notes |
|-----------|------|-------|
| `status` | string | Filter by `pending`, `approved`, or `rejected`. |

**Response `200`**
```json
{
  "data": [ { "...Asset Object..." } ],
  "meta": {
    "total": 100,
    "page": 1
  }
}
```

---

### `PATCH /admin/assets/{asset}/approve`
Approve a pending asset (sets `status` to `approved`).

**Auth:** Sanctum + role:admin

**Response `200`**
```json
{ "data": { "...Asset Object..." } }
```

---

### `PATCH /admin/assets/{asset}/reject`
Reject a pending asset (sets `status` to `rejected`).

**Auth:** Sanctum + role:admin

**Response `200`**
```json
{ "data": { "...Asset Object..." } }
```

---

## 10. Admin — Dashboard

### `GET /admin/dashboard`
Return aggregate platform statistics.

**Auth:** Sanctum + role:admin

**Response `200`**
```json
{
  "data": {
    "stats": {
      "users": {
        "total": 500,
        "active": 480,
        "frozen": 10,
        "blacklisted": 10
      },
      "assets": {
        "total": 1200,
        "pending": 30,
        "approved": 1150,
        "rejected": 20
      },
      "plays": {
        "total": 45000,
        "last_24h": 320,
        "last_7d": 2100
      },
      "devices": {
        "total": 15,
        "active_last_24h": 12
      }
    }
  }
}
```

---

## 11. Admin — Monitoring

### `GET /admin/monitoring`
Return real-time system health metrics.

**Auth:** Sanctum + role:admin

**Response `200`**
```json
{
  "data": {
    "health": {
      "database": "healthy | unhealthy",
      "queue": "healthy | degraded"
    },
    "queue": {
      "pending_jobs": 4,
      "failed_jobs": 0
    },
    "recommendations": {
      "engine_status": "active | disabled_fallback",
      "window_seconds": 300,
      "p95_latency_ms": 120.5,
      "hit_rate": 0.75,
      "p95_threshold_ms": 800.0,
      "hit_rate_threshold": 0.10
    },
    "api_errors": {
      "last_hour": 2
    },
    "device_ingestion": {
      "received_count": 980,
      "late_count": 5,
      "buffered_count": 12,
      "duplicate_count": 3
    },
    "timestamp": "2026-01-01T00:00:00+00:00"
  }
}
```

**Queue health rule:** `degraded` when `pending_jobs >= 1000`.  
**Recommendation degradation rules:** engine disables when `p95_latency_ms > 800` or `hit_rate < 0.10`.

---

## 12. Admin — Device Replay Audits

### `POST /admin/device-replays`
Trigger a replay audit for a device. Returns a unique `audit_key` used by the device when submitting replayed events.

**Auth:** Sanctum + role:admin

**Request Body**
| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `device_id` | integer | Yes | Must exist in `devices` table. |
| `scope` | string | No | Max 255 characters. |
| `reason` | string | No | Free-form description. |

**Response `201 Created`**
```json
{
  "data": {
    "id": 1,
    "audit_key": "ABCDEF1234567890",
    "device_id": 3,
    "triggered_by": 1,
    "scope": "last_24h",
    "reason": "Investigating missing sequence gap",
    "triggered_at": "2026-01-01T00:00:00+00:00"
  }
}
```

---

## 13. Technician — Console

> All `/technician/*` endpoints require `role: technician`.

### `GET /technician/devices`
List all registered devices, ordered by most recent event.

**Auth:** Sanctum + role:technician

**Response `200`**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Lobby Player A",
      "device_type": "media_player",
      "last_sequence": 412,
      "last_event_at": "2026-01-01T00:00:00+00:00",
      "created_at": "2025-06-01T00:00:00+00:00"
    }
  ]
}
```

---

### `GET /technician/events`
List device events, paginated at 50 per page, ordered by `received_at` descending.

**Auth:** Sanctum + role:technician

**Query Parameters**
| Parameter | Type | Notes |
|-----------|------|-------|
| `device_id` | integer | Filter by device. |
| `status` | string | Filter by `received`, `late`, `buffered`, or `duplicate`. |

**Response `200`**
```json
{
  "data": [
    {
      "id": 1,
      "device_id": 1,
      "device_name": "Lobby Player A",
      "event_type": "heartbeat",
      "sequence": 412,
      "status": "received",
      "replay_audit_id": null,
      "received_at": "2026-01-01T00:00:00+00:00"
    }
  ],
  "meta": {
    "total": 500,
    "page": 1,
    "per_page": 50
  }
}
```

---

## 14. Device — Event Ingestion

### `POST /device/events`
Ingest a telemetry event from a registered device.

**Auth:** `X-Device-Key` header (device API key — not session-based)

**Request Body**
| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `device_id` | integer | Yes | Must match the authenticated device's ID. |
| `event_type` | string | Yes | Max 100 characters. |
| `payload` | object | Yes | Arbitrary JSON payload. |
| `idempotency_key` | string | Yes | Max 255 characters. Deduplicates within 7 days. |
| `sequence` | integer | Yes | Monotonically increasing per device, min 0. |
| `replay_audit_id` | string | No | `audit_key` from a prior `POST /admin/device-replays`. |

**Event Status Logic**
| Condition | Status |
|-----------|--------|
| `replay_audit_id` is set | `buffered` |
| `sequence < device.last_sequence` | `late` |
| Otherwise | `received` |
| Duplicate `idempotency_key` within 7 days | `duplicate` (returns `200`) |

**Responses**

`201 Created` — new event accepted
```json
{
  "id": 1,
  "status": "received | late | buffered"
}
```

`200 OK` — duplicate detected
```json
{
  "id": 1,
  "status": "duplicate"
}
```

`422 Unprocessable Entity` — `device_id` mismatch or invalid `replay_audit_id`
```json
{
  "message": "device_id does not match authenticated device.",
  "errors": { "device_id": ["Mismatch with authenticated device."] }
}
```
