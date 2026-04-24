import { beforeEach, describe, expect, it, vi } from 'vitest'
import {
  ApiError,
  FrozenError,
  assetsApi,
  authApi,
  devicesApi,
  favoritesApi,
  getStoredToken,
  historyApi,
  monitoringApi,
  playlistsApi,
  recommendationsApi,
  searchApi,
  settingsApi,
  usersApi,
} from '@/services/api'

describe('services/api', () => {
  const fetchMock = vi.fn<typeof fetch>()

  beforeEach(() => {
    vi.restoreAllMocks()
    vi.stubGlobal('fetch', fetchMock)
    fetchMock.mockReset()
    localStorage.clear()
    document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/'
    window.history.pushState({}, '', '/')
  })

  it('auth login stores token and sends JSON payload', async () => {
    fetchMock.mockResolvedValueOnce(
      new Response(
        JSON.stringify({
          token: 'abc123',
          user: { id: 1, username: 'admin', role: 'admin' },
          csrf_token: null,
        }),
        { status: 200 },
      ),
    )

    await authApi.login({ username: 'admin', password: 'password' })

    expect(getStoredToken()).toBe('abc123')
    expect(fetchMock).toHaveBeenCalledWith(
      '/api/auth/login',
      expect.objectContaining({
        method: 'POST',
        credentials: 'include',
        headers: expect.objectContaining({ 'Content-Type': 'application/json' }),
      }),
    )
  })

  it('auth logout clears token and uses authorization header', async () => {
    localStorage.setItem('smartpark_token', 'tok-1')
    fetchMock.mockResolvedValueOnce(new Response(null, { status: 204 }))

    await authApi.logout()

    expect(getStoredToken()).toBeNull()
    expect(fetchMock).toHaveBeenCalledWith(
      '/api/auth/logout',
      expect.objectContaining({
        method: 'POST',
        headers: expect.objectContaining({ Authorization: 'Bearer tok-1' }),
      }),
    )
  })

  it('includes decoded XSRF token on mutating requests', async () => {
    document.cookie = 'XSRF-TOKEN=abc%20123; path=/'
    fetchMock.mockResolvedValueOnce(
      new Response(JSON.stringify({ site_name: 'SmartPark' }), { status: 200 }),
    )

    await settingsApi.update({ site_name: 'SmartPark' })

    expect(fetchMock).toHaveBeenCalledWith(
      '/api/settings',
      expect.objectContaining({
        method: 'PUT',
        headers: expect.objectContaining({ 'X-XSRF-TOKEN': 'abc 123' }),
      }),
    )
  })

  it('builds search query with tags[] and returns degraded=true from response header', async () => {
    fetchMock.mockResolvedValueOnce(
      new Response(JSON.stringify({ items: [], degraded: false }), {
        status: 200,
        headers: { 'X-Recommendation-Degraded': 'true' },
      }),
    )

    const result = await searchApi.search({
      q: 'gate',
      tags: ['safety', 'overnight'],
      duration_lt: 120,
      recent_days: 7,
      sort: 'recommended',
      per_page: 10,
    })

    const calledUrl = String(fetchMock.mock.calls[0]?.[0] ?? '')
    expect(calledUrl).toContain('/api/search?')
    expect(calledUrl).toContain('q=gate')
    expect(calledUrl).toContain('tags%5B%5D=safety')
    expect(calledUrl).toContain('tags%5B%5D=overnight')
    expect(calledUrl).toContain('duration_lt=120')
    expect(result.degraded).toBe(true)
  })

  it('recommendations falls back to empty items and reads degraded from payload', async () => {
    fetchMock.mockResolvedValueOnce(
      new Response(JSON.stringify({ degraded: true }), { status: 200 }),
    )

    const result = await recommendationsApi.get()

    expect(result.items).toEqual([])
    expect(result.degraded).toBe(true)
  })

  it('throws FrozenError on 423', async () => {
    fetchMock.mockResolvedValueOnce(
      new Response(JSON.stringify({ frozen_until: '2099-01-01T00:00:00Z' }), { status: 423 }),
    )

    await expect(settingsApi.get()).rejects.toBeInstanceOf(FrozenError)
  })

  it('throws ApiError with backend message for non-ok responses', async () => {
    fetchMock.mockResolvedValueOnce(
      new Response(JSON.stringify({ message: 'Validation failed' }), { status: 422 }),
    )

    await expect(settingsApi.update({ site_name: '' })).rejects.toMatchObject({
      name: 'ApiError',
      status: 422,
      message: 'Validation failed',
    })
  })

  it('throws unauthorized ApiError on 401 while on /login path', async () => {
    window.history.pushState({}, '', '/login')
    fetchMock.mockResolvedValueOnce(new Response(null, { status: 401 }))

    await expect(settingsApi.get()).rejects.toMatchObject({
      name: 'ApiError',
      status: 401,
      message: 'Unauthorized',
    })
  })

  it('assets upload sends form data with repeated tags[] entries', async () => {
    fetchMock.mockResolvedValueOnce(
      new Response(JSON.stringify({ id: 12, title: 'A', status: 'ready' }), { status: 200 }),
    )

    const file = new File(['abc'], 'a.jpg', { type: 'image/jpeg' })
    await assetsApi.upload(file, { title: 'A', tags: ['x', 'y'] })

    const options = fetchMock.mock.calls[0]?.[1] as RequestInit
    expect(options.method).toBe('POST')
    expect(options.body).toBeInstanceOf(FormData)
    const body = options.body as FormData
    expect(body.get('title')).toBe('A')
    expect(body.getAll('tags[]')).toEqual(['x', 'y'])
  })

  it('devices events and users list build expected query strings', async () => {
    fetchMock
      .mockResolvedValueOnce(new Response(JSON.stringify({ items: [] }), { status: 200 }))
      .mockResolvedValueOnce(new Response(JSON.stringify({ items: [] }), { status: 200 }))

    await devicesApi.events('gate-1', { status: 'accepted', cursor: 'c1', limit: 5 })
    await usersApi.list({ role: 'admin', status: 'active' })

    const first = String(fetchMock.mock.calls[0]?.[0] ?? '')
    const second = String(fetchMock.mock.calls[1]?.[0] ?? '')

    expect(first).toContain('/api/devices/gate-1/events?')
    expect(first).toContain('status=accepted')
    expect(first).toContain('cursor=c1')
    expect(first).toContain('limit=5')

    expect(second).toContain('/api/users?')
    expect(second).toContain('role=admin')
    expect(second).toContain('status=active')
  })

  it('covers remaining API wrappers with expected HTTP methods and URLs', async () => {
    localStorage.setItem('smartpark_token', 'tok-2')
    fetchMock.mockImplementation(async () => new Response(JSON.stringify({ ok: true }), { status: 200 }))

    await usersApi.create({ username: 'u2', password: 'password123', role: 'user', email: 'u2@example.com' })
    await usersApi.freeze(7, { duration_hours: 24 })
    await usersApi.unfreeze(7)
    await usersApi.blacklist(7)
    await usersApi.softDelete(7)

    await assetsApi.list({ cursor: 'c2', limit: 10, sort: 'newest', status: 'all' })
    await assetsApi.get(11)
    await assetsApi.delete(11)

    await favoritesApi.list('f-cursor')
    await favoritesApi.add(11)
    await favoritesApi.remove(11)

    await playlistsApi.list()
    await playlistsApi.get(5)
    await playlistsApi.create({ name: 'A' })
    await playlistsApi.update(5, { name: 'B' })
    await playlistsApi.delete(5)
    await playlistsApi.addItem(5, { asset_id: 11 })
    await playlistsApi.removeItem(5, 51)
    await playlistsApi.reorderItems(5, { item_ids: [2, 1] })
    await playlistsApi.share(5, { expires_in_hours: 4 })
    await playlistsApi.revokeShare(5, 99)
    await playlistsApi.redeem('ABCDEFGH')

    await historyApi.list('h-cursor')
    await historyApi.sessions(20)
    await historyApi.record(11, { session_id: 'sess-1', context: 'search' })

    await devicesApi.list()
    await devicesApi.get('gate-2')
    await devicesApi.initiateReplay('gate-2', { since_sequence_no: 1, until_sequence_no: 2, reason: 'test' })
    await devicesApi.replayAudits('gate-2')

    await monitoringApi.status()
    await monitoringApi.resetFlag('recommended_enabled')
    await settingsApi.get()

    const calls = fetchMock.mock.calls
    expect(calls.length).toBeGreaterThanOrEqual(32)

    const urls = calls.map((c) => String(c[0]))
    const methods = calls.map((c) => (c[1] as RequestInit).method)

    expect(urls).toContain('/api/users')
    expect(urls).toContain('/api/users/7/freeze')
    expect(urls).toContain('/api/users/7/unfreeze')
    expect(urls).toContain('/api/users/7/blacklist')
    expect(urls).toContain('/api/users/7')
    expect(urls).toContain('/api/assets?cursor=c2&limit=10&sort=newest&status=all')
    expect(urls).toContain('/api/favorites?cursor=f-cursor')
    expect(urls).toContain('/api/playlists/5/items/order')
    expect(urls).toContain('/api/playlists/redeem')
    expect(urls).toContain('/api/history?cursor=h-cursor')
    expect(urls).toContain('/api/history/sessions?limit=20')
    expect(urls).toContain('/api/devices/gate-2/replay')
    expect(urls).toContain('/api/monitoring/feature-flags/recommended_enabled/reset')
    expect(urls).toContain('/api/settings')

    expect(methods).toContain('GET')
    expect(methods).toContain('POST')
    expect(methods).toContain('PUT')
    expect(methods).toContain('PATCH')
    expect(methods).toContain('DELETE')
  })

  it('covers optional query branches when params are omitted', async () => {
    fetchMock.mockImplementation(async () => new Response(JSON.stringify({ items: [] }), { status: 200 }))

    await usersApi.list()
    await assetsApi.list()
    await searchApi.search({})
    await favoritesApi.list()
    await historyApi.list()
    await historyApi.sessions()
    await devicesApi.events('gate-3')
    await playlistsApi.share(10)

    const urls = fetchMock.mock.calls.map((c) => String(c[0]))
    expect(urls).toContain('/api/users?')
    expect(urls).toContain('/api/assets?')
    expect(urls).toContain('/api/search?')
    expect(urls).toContain('/api/favorites')
    expect(urls).toContain('/api/history')
    expect(urls).toContain('/api/history/sessions')
    expect(urls).toContain('/api/devices/gate-3/events?')
    expect(urls).toContain('/api/playlists/10/share')
  })

  it('covers JSON parse fallbacks on success and error responses', async () => {
    fetchMock
      .mockResolvedValueOnce(new Response('not-json', { status: 200 }))
      .mockResolvedValueOnce(new Response('not-json', { status: 500 }))
      .mockResolvedValueOnce(new Response('not-json', { status: 423 }))

    const me = await authApi.me()
    expect(me as unknown).toBeUndefined()

    await expect(settingsApi.get()).rejects.toBeInstanceOf(ApiError)
    await expect(settingsApi.get()).rejects.toBeInstanceOf(FrozenError)
  })
})
