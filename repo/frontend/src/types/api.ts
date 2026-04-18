export interface User {
  id: number
  username: string
  role: 'user' | 'admin' | 'technician'
  favorites_count?: number
  frozen_until?: string | null
  blacklisted_at?: string | null
  deleted_at?: string | null
}

export interface Asset {
  id: number
  title: string
  description?: string
  mime: string
  duration_seconds?: number
  size_bytes: number
  file_path?: string
  fingerprint_sha256?: string
  status: 'processing' | 'ready' | 'failed'
  thumbnail_urls?: { '160': string; '480': string; '960': string } | null
  tags: string[]
  played_count?: number
  created_at: string
  reason_tags?: string[]
}

export interface Playlist {
  id: number
  owner_id: number
  name: string
  items?: PlaylistItem[]
  items_count?: number
  created_at: string
  updated_at: string
}

export interface PlaylistItem {
  id: number
  playlist_id: number
  asset_id: number
  asset?: Asset
  position: number
}

export interface PlaylistShare {
  id: number
  playlist_id: number
  code: string
  expires_at: string
  revoked_at?: string | null
}

export interface Favorite {
  user_id: number
  asset_id: number
  asset?: Asset
  created_at: string
}

export interface PlayHistoryEntry {
  id: number
  user_id: number
  asset_id: number
  asset?: Asset
  played_at: string
  session_id?: string
  context?: string
}

export interface RecommendationCandidate {
  asset_id: number
  asset?: Asset
  score: number
  reason_tags: string[]
}

export interface PlayHistorySession {
  session_id: string | null
  started_at: string | null
  ended_at: string | null
  play_count: number
  context?: string | null
  items: PlayHistoryEntry[]
}

export interface PlayHistorySessionsResponse {
  sessions: PlayHistorySession[]
}

export interface RecommendationsResponse {
  items: RecommendationCandidate[]
  degraded: boolean
  fallback?: 'most_played' | null
}

export interface Device {
  id: string
  kind: string
  label?: string
  last_sequence_no: number
  last_seen_at?: string
}

export interface DeviceEvent {
  id: number
  device_id: string
  event_type: string
  sequence_no: number
  idempotency_key: string
  occurred_at: string
  received_at: string
  is_out_of_order: boolean
  buffered_by_gateway: boolean
  buffered_at?: string | null
  payload_json?: any
  status?: 'accepted' | 'duplicate' | 'out_of_order' | 'too_old'
}

export interface ReplayAudit {
  id: number
  device_id: string
  initiated_by: number
  since_sequence_no: number
  until_sequence_no?: number
  reason?: string
  created_at: string
}

export interface MonitoringStatus {
  api: { p95_ms_5m: number; error_rate_5m: number }
  queues: Record<string, number>
  storage: { media_volume_free_bytes: number; media_volume_used_pct: number }
  devices: { online: number; offline: number; dedup_rate_1h: number }
  content_usage: {
    window_hours: number
    plays_24h: number
    active_users_24h: number
    total_ready_assets: number
    favorites_count: number
    playlists_count: number
    top_assets: Array<{
      asset_id: number
      title: string | null
      mime: string | null
      play_count: number
    }>
  }
  feature_flags: Record<string, { enabled: boolean; last_transition_at?: string; reason?: string | null }>
}

export interface PaginatedResponse<T> {
  items: T[]
  next_cursor: string | null
}

export interface SearchResponse extends PaginatedResponse<Asset> {
  degraded: boolean
}

export interface LoginRequest {
  username: string
  password: string
}

export interface LoginResponse {
  user: User
  token: string
  csrf_token: string | null
}

export interface CreateUserRequest {
  username: string
  password: string
  role: 'user' | 'admin' | 'technician'
}

export interface FreezeUserRequest {
  duration_hours: number
}

export interface CreatePlaylistRequest {
  name: string
}

export interface UpdatePlaylistRequest {
  name: string
}

export interface AddPlaylistItemRequest {
  asset_id: number
  position?: number
}

export interface ReorderPlaylistItemsRequest {
  item_ids: number[]
}

export interface CreateShareRequest {
  expires_in_hours?: number
}

export interface RedeemShareRequest {
  code: string
}

export interface SearchParams {
  q?: string
  tags?: string[]
  duration_lt?: number
  recent_days?: number
  sort?: 'most_played' | 'newest' | 'recommended'
  cursor?: string
  per_page?: number
}

export interface UploadAssetMetadata {
  title: string
  description?: string
  tags?: string[]
}

export interface InitiateReplayRequest {
  since_sequence_no: number
  until_sequence_no?: number
  reason?: string
}
