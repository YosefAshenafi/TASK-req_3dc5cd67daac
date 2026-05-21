import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import HistoryView from '@/views/HistoryView.vue'

const mockHistory = [
  { id: 1, played_at: '2026-05-21T09:00:00+00:00', asset: { id: 10, title: 'Morning Clip' } },
  { id: 2, played_at: '2026-05-21T08:00:00+00:00', asset: { id: 11, title: 'Safety Brief' } },
]

function makeStore(overrides = {}) {
  return {
    history: mockHistory,
    isLoading: false,
    fetchHistory: vi.fn().mockResolvedValue(undefined),
    recordPlay: vi.fn(),
    ...overrides,
  }
}

vi.mock('@/stores/nowPlaying', () => ({
  useNowPlayingStore: () => makeStore(),
}))

describe('HistoryView', () => {
  describe('heading', () => {
    it('renders the page heading', async () => {
      const wrapper = mount(HistoryView)
      await flushPromises()
      expect(wrapper.text()).toContain('Play History')
    })
  })

  describe('loading state', () => {
    it('shows skeleton loaders while loading', () => {
      vi.doMock('@/stores/nowPlaying', () => ({
        useNowPlayingStore: () =>
          makeStore({
            fetchHistory: vi.fn(() => new Promise(() => {})),
          }),
      }))
      const wrapper = mount(HistoryView)
      expect(wrapper.find('.skeleton').exists()).toBe(true)
    })
  })

  describe('populated history', () => {
    it('renders an entry row for each history item', async () => {
      const wrapper = mount(HistoryView)
      await flushPromises()
      const entries = wrapper.findAll('.card')
      expect(entries.length).toBeGreaterThanOrEqual(2)
    })

    it('renders asset titles from history', async () => {
      const wrapper = mount(HistoryView)
      await flushPromises()
      expect(wrapper.text()).toContain('Morning Clip')
      expect(wrapper.text()).toContain('Safety Brief')
    })

    it('renders played_at timestamp for each entry', async () => {
      const wrapper = mount(HistoryView)
      await flushPromises()
      // formatDate converts ISO to locale string — just assert something date-like is rendered
      const text = wrapper.text()
      expect(text).toMatch(/\d{1,2}\/\d{1,2}\/\d{4}|\d{4}-\d{2}-\d{2}|am|pm/i)
    })

    it('hides skeleton loaders after load completes', async () => {
      const wrapper = mount(HistoryView)
      await flushPromises()
      expect(wrapper.find('.skeleton').exists()).toBe(false)
    })
  })

  describe('empty state', () => {
    it('shows empty message when history is empty', async () => {
      vi.doMock('@/stores/nowPlaying', () => ({
        useNowPlayingStore: () =>
          makeStore({ history: [] }),
      }))
      const wrapper = mount(HistoryView)
      await flushPromises()
      // history ref starts as [] then gets populated by fetchHistory side effect
      // We need to simulate the store setting history to []
      wrapper.vm.history = []
      await wrapper.vm.$nextTick()
      expect(wrapper.text()).toContain('No play history yet')
    })
  })

  describe('formatDate helper', () => {
    it('returns empty string for null/undefined date', async () => {
      const wrapper = mount(HistoryView)
      await flushPromises()
      // Directly access the function via the component instance
      const result = wrapper.vm.formatDate(null)
      expect(result).toBe('')
    })

    it('returns empty string for undefined', async () => {
      const wrapper = mount(HistoryView)
      await flushPromises()
      expect(wrapper.vm.formatDate(undefined)).toBe('')
    })

    it('returns a non-empty string for a valid ISO date', async () => {
      const wrapper = mount(HistoryView)
      await flushPromises()
      const result = wrapper.vm.formatDate('2026-05-21T09:00:00+00:00')
      expect(result).toBeTruthy()
      expect(typeof result).toBe('string')
    })
  })
})
