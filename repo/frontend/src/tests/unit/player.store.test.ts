import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { usePlayerStore } from '@/stores/player'
import type { Asset } from '@/types/api'

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
})
