import type {
  User,
  Asset,
  Playlist,
  PlaylistItem,
  PlaylistShare,
  Favorite,
  PlayHistoryEntry,
  PlayHistorySessionsResponse,
  RecommendationCandidate,
  RecommendationsResponse,
  Device,
  DeviceEvent,
  ReplayAudit,
  MonitoringStatus,
  PaginatedResponse,
  SearchResponse,
  LoginRequest,
  LoginResponse,
  CreateUserRequest,
  FreezeUserRequest,
  CreatePlaylistRequest,
  UpdatePlaylistRequest,
  AddPlaylistItemRequest,
  ReorderPlaylistItemsRequest,
  CreateShareRequest,
  SearchParams,
  UploadAssetMetadata,
  InitiateReplayRequest,
} from '@/types/api'

const TOKEN_KEY = 'smartpark_token'

export function getStoredToken(): string | null {
  return localStorage.getItem(TOKEN_KEY)
}

function storeToken(token: string): void {
  localStorage.setItem(TOKEN_KEY, token)
}

function clearToken(): void {
  localStorage.removeItem(TOKEN_KEY)
}

const BASE_URL = '/api'

export class ApiError extends Error {
  constructor(
    public status: number,
    public body: any,
    message?: string,
  ) {
    super(message ?? `HTTP ${status}`)
    this.name = 'ApiError'
  }
}

export class FrozenError extends ApiError {
  constructor(body: any) {
    super(423, body, 'Account is frozen')
    this.name = 'FrozenError'
  }
}

function getCookie(name: string): string | undefined {
  const match = document.cookie.split('; ').find((row) => row.startsWith(name + '='))
  return match?.split('=')[1]
}

async function request<T>(
  method: string,
  path: string,
  body?: unknown,
  options?: { signal?: AbortSignal; rawResponse?: false },
): Promise<T>
async function request<T>(
  method: string,
  path: string,
  body?: unknown,
  options?: { signal?: AbortSignal; rawResponse: true },
): Promise<{ data: T; response: Response }>
async function request<T>(
  method: string,
  path: string,
  body?: unknown,
  options?: { signal?: AbortSignal; rawResponse?: boolean },
): Promise<T | { data: T; response: Response }> {
  const isStateMutating = !['GET', 'HEAD', 'OPTIONS'].includes(method.toUpperCase())

  const headers: Record<string, string> = {}

  if (body !== undefined && !(body instanceof FormData)) {
    headers['Content-Type'] = 'application/json'
  }

  const token = getStoredToken()
  if (token) {
    headers['Authorization'] = `Bearer ${token}`
  }

  if (isStateMutating) {
    const xsrf = getCookie('XSRF-TOKEN')
    if (xsrf) {
      headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrf)
    }
  }

  const res = await fetch(`${BASE_URL}${path}`, {
    method,
    credentials: 'include',
    headers,
    body: body instanceof FormData ? body : body !== undefined ? JSON.stringify(body) : undefined,
    signal: options?.signal,
  })

  if (res.status === 401) {
    if (window.location.pathname !== '/login') {
      window.location.href = '/login'
    }
    throw new ApiError(401, null, 'Unauthorized')
  }

  if (res.status === 423) {
    let errBody: any = null
    try {
      errBody = await res.json()
    } catch {
      /* noop */
    }
    throw new FrozenError(errBody)
  }

  if (!res.ok) {
    let errBody: any = null
    try {
      errBody = await res.json()
    } catch {
      /* noop */
    }
    throw new ApiError(res.status, errBody, errBody?.message ?? `HTTP ${res.status}`)
  }

  if (res.status === 204) {
    if (options?.rawResponse) return { data: undefined as T, response: res }
    return undefined as T
  }

  let data: T
  try {
    data = await res.json()
  } catch {
    data = undefined as T
  }

  if (options?.rawResponse) return { data, response: res }
  return data
}

function get<T>(path: string, signal?: AbortSignal): Promise<T> {
  return request<T>('GET', path, undefined, { signal })
}

function getWithResponse<T>(
  path: string,
  signal?: AbortSignal,
): Promise<{ data: T; response: Response }> {
  return request<T>('GET', path, undefined, { rawResponse: true, signal })
}

function post<T>(path: string, body?: unknown): Promise<T> {
  return request<T>('POST', path, body)
}

function put<T>(path: string, body?: unknown): Promise<T> {
  return request<T>('PUT', path, body)
}

function patch<T>(path: string, body?: unknown): Promise<T> {
  return request<T>('PATCH', path, body)
}

function del<T = void>(path: string, body?: unknown): Promise<T> {
  return request<T>('DELETE', path, body)
}

// ─── Auth ─────────────────────────────────────────────────────────────────────

export const authApi = {
  login: async (payload: LoginRequest): Promise<LoginResponse> => {
    const res = await post<LoginResponse>('/auth/login', payload)
    storeToken(res.token)
    return res
  },
  logout: async (): Promise<void> => {
    await post<void>('/auth/logout')
    clearToken()
  },
  me: (): Promise<User> => get<User>('/auth/me'),
}

// ─── Users ────────────────────────────────────────────────────────────────────

export const usersApi = {
  list: (params?: { role?: string; status?: string }): Promise<PaginatedResponse<User>> => {
    const qs = new URLSearchParams()
    if (params?.role) qs.set('role', params.role)
    if (params?.status) qs.set('status', params.status)
    return get<PaginatedResponse<User>>(`/users?${qs}`)
  },
  create: (payload: CreateUserRequest): Promise<User> => post<User>('/users', payload),
  freeze: (id: number, payload: FreezeUserRequest): Promise<User> =>
    patch<User>(`/users/${id}/freeze`, payload),
  unfreeze: (id: number): Promise<User> => patch<User>(`/users/${id}/unfreeze`),
  blacklist: (id: number): Promise<User> => patch<User>(`/users/${id}/blacklist`),
  softDelete: (id: number): Promise<void> => del(`/users/${id}`),
}

// ─── Assets ───────────────────────────────────────────────────────────────────

export const assetsApi = {
  list: (params?: {
    cursor?: string
    limit?: number
    sort?: string
    status?: 'ready' | 'processing' | 'failed' | 'all'
  }): Promise<PaginatedResponse<Asset>> => {
    const qs = new URLSearchParams()
    if (params?.cursor) qs.set('cursor', params.cursor)
    if (params?.limit) qs.set('limit', String(params.limit))
    if (params?.sort) qs.set('sort', params.sort)
    if (params?.status) qs.set('status', params.status)
    return get<PaginatedResponse<Asset>>(`/assets?${qs}`)
  },
  get: (id: number): Promise<Asset> => get<Asset>(`/assets/${id}`),
  delete: (id: number): Promise<void> => del(`/assets/${id}`),
  upload: (file: File, metadata: UploadAssetMetadata): Promise<Asset> => {
    const fd = new FormData()
    fd.append('file', file)
    fd.append('title', metadata.title)
    if (metadata.description) fd.append('description', metadata.description)
    // Laravel expects tags as an array — send one `tags[]` entry per tag.
    if (metadata.tags) metadata.tags.forEach((t) => fd.append('tags[]', t))
    return post<Asset>('/assets', fd)
  },
}

// ─── Search ───────────────────────────────────────────────────────────────────

export const searchApi = {
  search: async (
    params: SearchParams,
    signal?: AbortSignal,
  ): Promise<{ data: SearchResponse; degraded: boolean }> => {
    const qs = new URLSearchParams()
    if (params.q) qs.set('q', params.q)
    // Laravel parses repeated `tags[]` query params into an array. Plain `?tags=a&tags=b`
    // would collapse to the last value only — use the bracketed form to match the
    // server-side `(array) $request->input('tags')` contract in SearchController.
    if (params.tags?.length) params.tags.forEach((t) => qs.append('tags[]', t))
    if (params.duration_lt != null)
      qs.set('duration_lt', String(params.duration_lt))
    if (params.recent_days != null) qs.set('recent_days', String(params.recent_days))
    if (params.sort) qs.set('sort', params.sort)
    if (params.cursor) qs.set('cursor', params.cursor)
    if (params.per_page) qs.set('per_page', String(params.per_page))

    const { data, response } = await getWithResponse<SearchResponse>(
      `/search?${qs}`,
      signal,
    )
    const degraded =
      response.headers.get('X-Recommendation-Degraded') === 'true' || data.degraded === true
    return { data, degraded }
  },
}

// ─── Favorites ────────────────────────────────────────────────────────────────

export const favoritesApi = {
  list: (cursor?: string): Promise<PaginatedResponse<Favorite>> =>
    get<PaginatedResponse<Favorite>>(`/favorites${cursor ? `?cursor=${cursor}` : ''}`),
  add: (assetId: number): Promise<Favorite> => put<Favorite>(`/favorites/${assetId}`),
  remove: (assetId: number): Promise<void> => del(`/favorites/${assetId}`),
}

// ─── Playlists ────────────────────────────────────────────────────────────────

export const playlistsApi = {
  list: (): Promise<Playlist[]> => get<Playlist[]>('/playlists'),
  get: (id: number): Promise<Playlist> => get<Playlist>(`/playlists/${id}`),
  create: (payload: CreatePlaylistRequest): Promise<Playlist> =>
    post<Playlist>('/playlists', payload),
  update: (id: number, payload: UpdatePlaylistRequest): Promise<Playlist> =>
    put<Playlist>(`/playlists/${id}`, payload),
  delete: (id: number): Promise<void> => del(`/playlists/${id}`),
  addItem: (playlistId: number, payload: AddPlaylistItemRequest): Promise<PlaylistItem> =>
    post<PlaylistItem>(`/playlists/${playlistId}/items`, payload),
  removeItem: (playlistId: number, itemId: number): Promise<void> =>
    del(`/playlists/${playlistId}/items/${itemId}`),
  reorderItems: (
    playlistId: number,
    payload: ReorderPlaylistItemsRequest,
  ): Promise<PlaylistItem[]> => put<PlaylistItem[]>(`/playlists/${playlistId}/items/order`, payload),
  share: (playlistId: number, payload?: CreateShareRequest): Promise<PlaylistShare> =>
    post<PlaylistShare>(`/playlists/${playlistId}/share`, payload),
  revokeShare: (playlistId: number, shareId: number): Promise<void> =>
    del(`/playlists/shares/${shareId}`),
  redeem: (code: string): Promise<Playlist> => post<Playlist>('/playlists/redeem', { code }),
}

// ─── Play History ─────────────────────────────────────────────────────────────

export const historyApi = {
  list: (cursor?: string): Promise<PaginatedResponse<PlayHistoryEntry>> =>
    get<PaginatedResponse<PlayHistoryEntry>>(
      `/history${cursor ? `?cursor=${cursor}` : ''}`,
    ),
  sessions: (limit?: number): Promise<PlayHistorySessionsResponse> =>
    get<PlayHistorySessionsResponse>(
      `/history/sessions${limit ? `?limit=${limit}` : ''}`,
    ),
  record: (
    assetId: number,
    payload?: { session_id?: string; context?: string },
  ): Promise<PlayHistoryEntry> =>
    post<PlayHistoryEntry>(`/assets/${assetId}/play`, payload ?? {}),
}

// ─── Recommendations ──────────────────────────────────────────────────────────

export const recommendationsApi = {
  get: async (): Promise<{ items: RecommendationCandidate[]; degraded: boolean }> => {
    const { data, response } = await getWithResponse<RecommendationsResponse>(
      '/recommendations',
    )
    const degraded =
      response.headers.get('X-Recommendation-Degraded') === 'true' ||
      data.degraded === true
    return { items: data.items ?? [], degraded }
  },
}

// ─── Devices ──────────────────────────────────────────────────────────────────

export const devicesApi = {
  list: (): Promise<Device[]> => get<Device[]>('/devices'),
  get: (id: string): Promise<Device> => get<Device>(`/devices/${id}`),
  events: (
    deviceId: string,
    params?: { status?: string; cursor?: string; limit?: number },
  ): Promise<PaginatedResponse<DeviceEvent>> => {
    const qs = new URLSearchParams()
    if (params?.status) qs.set('status', params.status)
    if (params?.cursor) qs.set('cursor', params.cursor)
    if (params?.limit) qs.set('limit', String(params.limit))
    return get<PaginatedResponse<DeviceEvent>>(`/devices/${deviceId}/events?${qs}`)
  },
  initiateReplay: (deviceId: string, payload: InitiateReplayRequest): Promise<ReplayAudit> =>
    post<ReplayAudit>(`/devices/${deviceId}/replay`, payload),
  replayAudits: (deviceId: string): Promise<ReplayAudit[]> =>
    get<ReplayAudit[]>(`/devices/${deviceId}/replay/audits`),
}

// ─── Monitoring ───────────────────────────────────────────────────────────────

export const monitoringApi = {
  status: (): Promise<MonitoringStatus> => get<MonitoringStatus>('/monitoring/status'),
  resetFlag: (flag: string): Promise<void> =>
    post<void>(`/monitoring/feature-flags/${flag}/reset`),
}
