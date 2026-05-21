import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import FavoritesView from '@/views/FavoritesView.vue'

// Stub child component
vi.mock('@/components/AssetCard.vue', () => ({
  default: {
    props: ['asset'],
    template: '<div data-testid="asset-card">{{ asset.title }}</div>',
    emits: ['play'],
  },
}))

const mockNowPlaying = { recordPlay: vi.fn() }
vi.mock('@/stores/nowPlaying', () => ({
  useNowPlayingStore: () => mockNowPlaying,
}))

const mockFavorites = [
  { favorite_id: 1, asset: { id: 10, title: 'Safety Announcement', mime_type: 'audio/mpeg' } },
  { favorite_id: 2, asset: { id: 11, title: 'Evening Playlist', mime_type: 'audio/mpeg' } },
]

function makeApi(overrides = {}) {
  return {
    get: vi.fn().mockResolvedValue({ data: { data: mockFavorites } }),
    delete: vi.fn().mockResolvedValue({}),
    ...overrides,
  }
}

describe('FavoritesView', () => {
  describe('initial render', () => {
    it('renders the page heading', async () => {
      vi.mock('@/api/axios', () => ({ default: makeApi() }))
      const wrapper = mount(FavoritesView)
      expect(wrapper.text()).toContain('My Favorites')
    })

    it('shows skeleton loaders while loading', () => {
      vi.mock('@/api/axios', () => ({
        default: { get: vi.fn(() => new Promise(() => {})) },
      }))
      const wrapper = mount(FavoritesView)
      expect(wrapper.find('.skeleton').exists()).toBe(true)
    })
  })

  describe('successful load', () => {
    let api

    beforeEach(() => {
      api = makeApi()
      vi.doMock('@/api/axios', () => ({ default: api }))
    })

    it('renders an asset card for each favorite', async () => {
      const wrapper = mount(FavoritesView)
      await flushPromises()
      expect(wrapper.findAll('[data-testid="asset-card"]')).toHaveLength(2)
    })

    it('renders favorite asset titles', async () => {
      const wrapper = mount(FavoritesView)
      await flushPromises()
      expect(wrapper.text()).toContain('Safety Announcement')
      expect(wrapper.text()).toContain('Evening Playlist')
    })

    it('renders a remove button per favorite with aria-label', async () => {
      const wrapper = mount(FavoritesView)
      await flushPromises()
      const removeBtns = wrapper.findAll('button[title="Remove favorite"]')
      expect(removeBtns).toHaveLength(2)
      expect(removeBtns[0].attributes('aria-label')).toContain('Safety Announcement')
    })

    it('hides skeleton loaders after data loads', async () => {
      const wrapper = mount(FavoritesView)
      await flushPromises()
      expect(wrapper.find('.skeleton').exists()).toBe(false)
    })
  })

  describe('empty state', () => {
    it('shows empty state message when no favorites', async () => {
      vi.doMock('@/api/axios', () => ({
        default: { get: vi.fn().mockResolvedValue({ data: { data: [] } }) },
      }))
      const wrapper = mount(FavoritesView)
      await flushPromises()
      expect(wrapper.text()).toContain('No favorites yet')
    })
  })

  describe('error state', () => {
    it('shows error message when API fails', async () => {
      vi.doMock('@/api/axios', () => ({
        default: {
          get: vi.fn().mockRejectedValue({
            response: { data: { message: 'Server error' } },
          }),
        },
      }))
      const wrapper = mount(FavoritesView)
      await flushPromises()
      expect(wrapper.text()).toContain('Server error')
    })

    it('shows fallback error text when error has no message', async () => {
      vi.doMock('@/api/axios', () => ({
        default: { get: vi.fn().mockRejectedValue(new Error('Network error')) },
      }))
      const wrapper = mount(FavoritesView)
      await flushPromises()
      expect(wrapper.text()).toContain('Failed to load favorites')
    })

    it('shows retry button on error', async () => {
      vi.doMock('@/api/axios', () => ({
        default: { get: vi.fn().mockRejectedValue({ response: { data: { message: 'oops' } } }) },
      }))
      const wrapper = mount(FavoritesView)
      await flushPromises()
      const retryBtn = wrapper.find('button')
      expect(retryBtn.text()).toContain('Retry')
    })
  })

  describe('remove favorite', () => {
    it('calls DELETE and removes item from list', async () => {
      const deleteApi = vi.fn().mockResolvedValue({})
      const getApi = vi.fn().mockResolvedValue({ data: { data: mockFavorites } })
      vi.doMock('@/api/axios', () => ({ default: { get: getApi, delete: deleteApi } }))

      const wrapper = mount(FavoritesView)
      await flushPromises()

      const removeBtn = wrapper.find('button[title="Remove favorite"]')
      await removeBtn.trigger('click')
      await flushPromises()

      expect(deleteApi).toHaveBeenCalledWith('/favorites/1')
      // After removal the first favorite should be gone from the rendered list
      const cards = wrapper.findAll('[data-testid="asset-card"]')
      expect(cards).toHaveLength(1)
    })

    it('shows error message when remove fails', async () => {
      vi.doMock('@/api/axios', () => ({
        default: {
          get: vi.fn().mockResolvedValue({ data: { data: mockFavorites } }),
          delete: vi.fn().mockRejectedValue({ response: { data: { message: 'Cannot remove' } } }),
        },
      }))

      const wrapper = mount(FavoritesView)
      await flushPromises()

      const removeBtn = wrapper.find('button[title="Remove favorite"]')
      await removeBtn.trigger('click')
      await flushPromises()

      expect(wrapper.text()).toContain('Cannot remove')
    })
  })
})
