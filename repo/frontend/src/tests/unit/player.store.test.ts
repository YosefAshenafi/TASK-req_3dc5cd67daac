import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { usePlayerStore } from '@/stores/player'
import type { Asset, PlayHistoryEntry } from '@/types/api'

vi.mock('@/services/api', () => ({
  historyApi: {
    record: vi.fn(),
  },
}))

function makeAsset(overrides: Partial<Asset> = {}): Asset {
  return {
    id: 101,
    title: 'Gate Safety Briefing',
    description: 'Daily safety briefing asset',
    mime: 'video/mp4',
    duration_seconds: 42,
    size_bytes: 1024,
    status: 'ready',
    thumbnail_urls: null,
    tags: ['safety'],
    created_at: new Date().toISOString(),
    reason_tags: [],
    ...overrides,
  }
}

describe('Player Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('play clears nowPlayingReasons when asset has no reason tags', async () => {
    const { historyApi } = await import('@/services/api')
    vi.mocked(historyApi.record).mockResolvedValue({
      id: 8001,
      asset_id: 101,
      user_id: 1,
      played_at: new Date().toISOString(),
    })

    const store = usePlayerStore()
    const asset = makeAsset({ id: 101, reason_tags: [] })

    await store.play(asset)

    expect(store.nowPlayingReasons).toEqual([])
  })

  it('play sets current asset, marks playing, and prepends recorded history', async () => {
    const { historyApi } = await import('@/services/api')
    vi.mocked(historyApi.record).mockResolvedValue({
      id: 9001,
      asset_id: 101,
      user_id: 1,
      played_at: new Date().toISOString(),
    })

    const store = usePlayerStore()
    const asset = makeAsset({ id: 101, reason_tags: ['most_played'] })

    await store.play(asset)

    expect(historyApi.record).toHaveBeenCalledWith(101)
    expect(store.currentAsset?.id).toBe(101)
    expect(store.isPlaying).toBe(true)
    expect(store.nowPlayingReasons).toEqual(['most_played'])
    expect(store.recentPlays[0]?.asset_id).toBe(101)
  })

  it('play truncates recentPlays to 20 entries', async () => {
    const { historyApi } = await import('@/services/api')
    const existing: PlayHistoryEntry[] = Array.from({ length: 20 }, (_, i) => ({
      id: 5000 + i,
      asset_id: 500 + i,
      user_id: 1,
      played_at: new Date().toISOString(),
    }))
    vi.mocked(historyApi.record).mockResolvedValue({
      id: 9100,
      asset_id: 101,
      user_id: 1,
      played_at: new Date().toISOString(),
    })

    const store = usePlayerStore()
    store.setRecentPlays(existing)
    await store.play(makeAsset({ id: 101 }))

    expect(store.recentPlays).toHaveLength(20)
    expect(store.recentPlays[0]?.id).toBe(9100)
  })

  it('play ignores history API errors', async () => {
    const { historyApi } = await import('@/services/api')
    vi.mocked(historyApi.record).mockRejectedValue(new Error('network'))

    const store = usePlayerStore()
    const asset = makeAsset({ id: 404 })

    await expect(store.play(asset)).resolves.toBeUndefined()
    expect(store.currentAsset?.id).toBe(404)
    expect(store.isPlaying).toBe(true)
  })

  it('markPlayed de-duplicates existing recent play entries by entry id', async () => {
    const { historyApi } = await import('@/services/api')
    const existing = {
      id: 123,
      asset_id: 202,
      user_id: 1,
      played_at: new Date().toISOString(),
    }
    vi.mocked(historyApi.record).mockResolvedValue(existing)

    const store = usePlayerStore()
    store.currentAsset = makeAsset({ id: 202 })
    store.setRecentPlays([existing])

    await store.markPlayed()

    expect(historyApi.record).toHaveBeenCalledWith(202)
    expect(store.recentPlays).toHaveLength(1)
    expect(store.recentPlays[0]?.id).toBe(123)
  })

  it('markPlayed prepends new entry and truncates list to 20', async () => {
    const { historyApi } = await import('@/services/api')
    const existing: PlayHistoryEntry[] = Array.from({ length: 20 }, (_, i) => ({
      id: 6000 + i,
      asset_id: 600,
      user_id: 1,
      played_at: new Date().toISOString(),
    }))
    vi.mocked(historyApi.record).mockResolvedValue({
      id: 7777,
      asset_id: 600,
      user_id: 1,
      played_at: new Date().toISOString(),
    })

    const store = usePlayerStore()
    store.setRecentPlays(existing)
    store.currentAsset = makeAsset({ id: 600 })

    await store.markPlayed()

    expect(store.recentPlays).toHaveLength(20)
    expect(store.recentPlays[0]?.id).toBe(7777)
  })

  it('markPlayed prepends without truncating when list stays at 20 or fewer', async () => {
    const { historyApi } = await import('@/services/api')
    const existing: PlayHistoryEntry[] = Array.from({ length: 19 }, (_, i) => ({
      id: 7000 + i,
      asset_id: 700,
      user_id: 1,
      played_at: new Date().toISOString(),
    }))
    vi.mocked(historyApi.record).mockResolvedValue({
      id: 7890,
      asset_id: 700,
      user_id: 1,
      played_at: new Date().toISOString(),
    })

    const store = usePlayerStore()
    store.setRecentPlays(existing)
    store.currentAsset = makeAsset({ id: 700 })

    await store.markPlayed()

    expect(store.recentPlays).toHaveLength(20)
    expect(store.recentPlays[0]?.id).toBe(7890)
  })

  it('markPlayed ignores history API errors', async () => {
    const { historyApi } = await import('@/services/api')
    vi.mocked(historyApi.record).mockRejectedValue(new Error('offline'))

    const store = usePlayerStore()
    store.currentAsset = makeAsset({ id: 999 })

    await expect(store.markPlayed()).resolves.toBeUndefined()
  })

  it('markPlayed does nothing when no current asset', async () => {
    const { historyApi } = await import('@/services/api')
    const store = usePlayerStore()
    store.currentAsset = null

    await store.markPlayed()

    expect(historyApi.record).not.toHaveBeenCalled()
  })

  it('markPlayed does not duplicate when API returns existing entry id', async () => {
    const { historyApi } = await import('@/services/api')
    const entry = {
      id: 4242,
      asset_id: 303,
      user_id: 1,
      played_at: new Date().toISOString(),
    }
    vi.mocked(historyApi.record).mockResolvedValue(entry)

    const store = usePlayerStore()
    store.currentAsset = makeAsset({ id: 303 })
    store.setRecentPlays([entry])

    await store.markPlayed()

    expect(historyApi.record).toHaveBeenCalledWith(303)
    expect(store.recentPlays).toHaveLength(1)
    expect(store.recentPlays[0]?.id).toBe(4242)
  })

  it('queue operations enqueue, dequeue, clear, and playNext transitions state', async () => {
    const { historyApi } = await import('@/services/api')
    vi.mocked(historyApi.record).mockResolvedValue({
      id: 2001,
      asset_id: 301,
      user_id: 1,
      played_at: new Date().toISOString(),
    })

    const store = usePlayerStore()
    const first = makeAsset({ id: 301 })
    const second = makeAsset({ id: 302 })

    store.enqueue(first)
    store.enqueue(second)
    expect(store.queue.map((a) => a.id)).toEqual([301, 302])

    const removed = store.dequeue()
    expect(removed?.id).toBe(301)
    expect(store.queue.map((a) => a.id)).toEqual([302])

    store.clearQueue()
    expect(store.queue).toHaveLength(0)

    store.playNext()
    expect(store.isPlaying).toBe(false)
  })

  it('playNext dequeues and plays next asset', async () => {
    const { historyApi } = await import('@/services/api')
    vi.mocked(historyApi.record).mockResolvedValue({
      id: 3001,
      asset_id: 401,
      user_id: 1,
      played_at: new Date().toISOString(),
    })

    const store = usePlayerStore()
    store.enqueue(makeAsset({ id: 401 }))

    store.playNext()

    await vi.waitFor(() => {
      expect(historyApi.record).toHaveBeenCalledWith(401)
    })
    expect(store.currentAsset?.id).toBe(401)
    expect(store.isPlaying).toBe(true)
  })
})
