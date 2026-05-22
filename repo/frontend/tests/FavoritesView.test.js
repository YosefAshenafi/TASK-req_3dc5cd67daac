import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import FavoritesView from '@/views/FavoritesView.vue'

vi.mock('@/components/AssetCard.vue', () => ({
  default: {
    props: ['asset'],
    template: '<div data-testid="asset-card">{{ asset.title }}</div>',
    emits: ['play'],
  },
}))

const mockNowPlaying = vi.hoisted(() => ({ recordPlay: vi.fn() }))
vi.mock('@/stores/nowPlaying', () => ({
  useNowPlayingStore: () => mockNowPlaying,
}))

const apiGet = vi.hoisted(() => vi.fn())
const apiDelete = vi.hoisted(() => vi.fn())
vi.mock('@/api/axios', () => ({ default: { get: apiGet, delete: apiDelete } }))

const mockFavorites = [
  { favorite_id: 1, asset: { id: 10, title: 'Safety Announcement', mime_type: 'audio/mpeg' } },
  { favorite_id: 2, asset: { id: 11, title: 'Evening Playlist', mime_type: 'audio/mpeg' } },
]

describe('FavoritesView', () => {
  beforeEach(() => {
    apiGet.mockResolvedValue({ data: { data: mockFavorites } })
    apiDelete.mockResolvedValue({})
    mockNowPlaying.recordPlay.mockReset()
  })

  describe('initial render', () => {
    it('renders the page heading', async () => {
      const wrapper = mount(FavoritesView)
      expect(wrapper.text()).toContain('My Favorites')
    })

    it('shows skeleton loaders while loading', () => {
      apiGet.mockReturnValue(new Promise(() => {}))
      const wrapper = mount(FavoritesView)
      expect(wrapper.find('.skeleton').exists()).toBe(true)
    })
  })

  describe('successful load', () => {
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
      apiGet.mockResolvedValue({ data: { data: [] } })
      const wrapper = mount(FavoritesView)
      await flushPromises()
      expect(wrapper.text()).toContain('No favorites yet')
    })
  })

  describe('error state', () => {
    it('shows error message when API fails', async () => {
      apiGet.mockRejectedValue({
        response: { data: { message: 'Server error' } },
      })
      const wrapper = mount(FavoritesView)
      await flushPromises()
      expect(wrapper.text()).toContain('Server error')
    })

    it('shows fallback error text when error has no message', async () => {
      apiGet.mockRejectedValue(new Error('Network error'))
      const wrapper = mount(FavoritesView)
      await flushPromises()
      expect(wrapper.text()).toContain('Failed to load favorites')
    })

    it('shows retry button on error', async () => {
      apiGet.mockRejectedValue({ response: { data: { message: 'oops' } } })
      const wrapper = mount(FavoritesView)
      await flushPromises()
      const retryBtn = wrapper.find('button')
      expect(retryBtn.text()).toContain('Retry')
    })
  })

  describe('remove favorite', () => {
    it('calls DELETE and removes item from list', async () => {
      const wrapper = mount(FavoritesView)
      await flushPromises()

      const removeBtn = wrapper.find('button[title="Remove favorite"]')
      await removeBtn.trigger('click')
      await flushPromises()

      expect(apiDelete).toHaveBeenCalledWith('/favorites/1')
      const cards = wrapper.findAll('[data-testid="asset-card"]')
      expect(cards).toHaveLength(1)
    })

    it('shows error message when remove fails', async () => {
      apiDelete.mockRejectedValue({ response: { data: { message: 'Cannot remove' } } })

      const wrapper = mount(FavoritesView)
      await flushPromises()

      const removeBtn = wrapper.find('button[title="Remove favorite"]')
      await removeBtn.trigger('click')
      await flushPromises()

      expect(wrapper.text()).toContain('Cannot remove')
    })
  })
})
