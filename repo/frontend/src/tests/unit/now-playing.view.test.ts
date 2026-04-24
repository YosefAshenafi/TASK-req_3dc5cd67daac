import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import NowPlayingView from '@/views/NowPlayingView.vue'
import { usePlayerStore } from '@/stores/player'

const listMock = vi.fn()
const sessionsMock = vi.fn()

vi.mock('@/services/api', () => ({
  historyApi: {
    list: (...a: unknown[]) => listMock(...a),
    sessions: (...a: unknown[]) => sessionsMock(...a),
  },
}))

function historyEntry(id: number, overrides: Record<string, unknown> = {}) {
  return {
    id,
    user_id: 1,
    asset_id: id,
    session_id: null,
    context: null,
    played_at: new Date('2026-04-01T10:00:00Z').toISOString(),
    asset: {
      id,
      title: `Track ${id}`,
      mime: 'audio/mpeg',
      size_bytes: 0,
      status: 'ready',
      tags: [],
      created_at: new Date().toISOString(),
    },
    ...overrides,
  }
}

describe('NowPlayingView.vue', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    listMock.mockReset()
    sessionsMock.mockReset()
  })

  it('renders the nothing-playing empty state when the player has no current asset', async () => {
    listMock.mockResolvedValue({ items: [], next_cursor: null })
    sessionsMock.mockResolvedValue({ sessions: [] })

    const wrapper = mount(NowPlayingView)
    await flushPromises()

    expect(wrapper.text()).toContain('Nothing playing')
    expect(wrapper.text()).toContain('Queue is empty')
    expect(wrapper.text()).toContain('No recent plays')
    expect(wrapper.text()).toContain('No sessions recorded yet')
  })

  it('renders the active asset, recommendation reasons, and a populated queue', async () => {
    listMock.mockResolvedValue({ items: [], next_cursor: null })
    sessionsMock.mockResolvedValue({ sessions: [] })

    const player = usePlayerStore()
    player.currentAsset = {
      id: 42,
      title: 'Main Gate Announce',
      mime: 'audio/mpeg',
      size_bytes: 0,
      duration_seconds: 75,
      status: 'ready',
      tags: [],
      created_at: new Date().toISOString(),
    } as never
    player.isPlaying = true
    player.nowPlayingReasons = ['safety', 'overnight']
    player.queue = [
      { id: 2, title: 'Next Up A', mime: 'audio/mpeg', size_bytes: 0, status: 'ready', tags: [], created_at: new Date().toISOString() } as never,
    ]

    const wrapper = mount(NowPlayingView)
    await flushPromises()

    expect(wrapper.text()).toContain('Main Gate Announce')
    expect(wrapper.text()).toContain('Playing')
    expect(wrapper.text()).toContain('1:15') // 75 seconds formatted mm:ss
    expect(wrapper.text()).toContain('safety, overnight')
    expect(wrapper.text()).toContain('Next Up A')
  })

  it('populates recent plays from historyApi.list on mount', async () => {
    listMock.mockResolvedValue({
      items: [historyEntry(1), historyEntry(2, { session_id: 'abcd1234xyz' })],
      next_cursor: null,
    })
    sessionsMock.mockResolvedValue({ sessions: [] })

    const wrapper = mount(NowPlayingView)
    await flushPromises()

    expect(wrapper.text()).toContain('Track 1')
    expect(wrapper.text()).toContain('Track 2')
  })

  it('renders session cards from historyApi.sessions, including contextual label', async () => {
    listMock.mockResolvedValue({ items: [], next_cursor: null })
    sessionsMock.mockResolvedValue({
      sessions: [
        {
          session_id: 'session-123-xxxxxxxx-xxxx',
          context: 'search',
          play_count: 3,
          items: [
            historyEntry(10),
            historyEntry(11),
            historyEntry(12),
            historyEntry(13),
            historyEntry(14),
            historyEntry(15),
          ],
        },
        {
          session_id: null,
          context: null,
          play_count: 1,
          items: [historyEntry(20)],
        },
      ],
    })

    const wrapper = mount(NowPlayingView)
    await flushPromises()

    // First session uses the truncated session_id as label.
    expect(wrapper.text()).toContain('Session session-')
    expect(wrapper.text()).toContain('3 plays')
    expect(wrapper.text()).toContain('Context: search')

    // Session with >5 items shows the "+N more" overflow hint.
    expect(wrapper.text()).toMatch(/\+1 more/)

    // Anonymous session falls back to positional label.
    expect(wrapper.text()).toContain('Session 2')
    expect(wrapper.text()).toContain('1 play')
  })

  it('still renders when both history and sessions fail — both are swallowed', async () => {
    listMock.mockRejectedValue(new Error('hx down'))
    sessionsMock.mockRejectedValue(new Error('sessions down'))

    const wrapper = mount(NowPlayingView)
    await flushPromises()

    // View should not throw; session loader resolves and renders the empty state.
    expect(wrapper.text()).toContain('Nothing playing')
    expect(wrapper.text()).toContain('No sessions recorded yet')
  })
})
