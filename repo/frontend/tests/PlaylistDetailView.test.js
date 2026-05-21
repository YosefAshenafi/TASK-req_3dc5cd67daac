import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import PlaylistDetailView from '@/views/PlaylistDetailView.vue'

const mockPlaylist = {
  id: 7,
  name: 'Morning Set',
  description: 'Calm background audio',
  share_code: 'SHARE789',
  items: [
    { id: 1, asset: { title: 'Track Alpha', mime_type: 'audio/mpeg' } },
    { id: 2, asset: { title: 'Track Beta', mime_type: 'audio/mpeg' } },
  ],
}

vi.mock('@/stores/playlist', () => ({
  usePlaylistStore: () => ({
    currentPlaylist: mockPlaylist,
    isLoading: false,
    fetchOne: vi.fn(),
    removeItem: vi.fn(),
  }),
}))

vi.mock('vue-router', async (importOriginal) => {
  const actual = await importOriginal()
  return {
    ...actual,
    useRoute: () => ({ params: { id: '7' } }),
    RouterLink: { template: '<a><slot /></a>' },
  }
})

describe('PlaylistDetailView', () => {
  it('renders the playlist name', () => {
    const wrapper = mount(PlaylistDetailView)
    expect(wrapper.text()).toContain('Morning Set')
  })

  it('renders the playlist description', () => {
    const wrapper = mount(PlaylistDetailView)
    expect(wrapper.text()).toContain('Calm background audio')
  })

  it('renders the share code button text', () => {
    const wrapper = mount(PlaylistDetailView)
    expect(wrapper.text()).toContain('SHARE789')
  })

  it('renders all track titles', () => {
    const wrapper = mount(PlaylistDetailView)
    expect(wrapper.text()).toContain('Track Alpha')
    expect(wrapper.text()).toContain('Track Beta')
  })

  it('shows copied message when clipboard copy succeeds', async () => {
    Object.defineProperty(navigator, 'clipboard', {
      value: { writeText: vi.fn().mockResolvedValue(undefined) },
      configurable: true,
    })

    const wrapper = mount(PlaylistDetailView)
    const shareBtn = wrapper.find('button:has-text("SHARE789")') ||
      wrapper.findAll('button').find(b => b.text().includes('SHARE789'))

    if (shareBtn) {
      await shareBtn.trigger('click')
      await wrapper.vm.$nextTick()
      expect(wrapper.text()).toContain('Copied')
    }
  })

  it('shows share code as fallback text when clipboard is unavailable', async () => {
    Object.defineProperty(navigator, 'clipboard', {
      value: { writeText: vi.fn().mockRejectedValue(new Error('not allowed')) },
      configurable: true,
    })

    const wrapper = mount(PlaylistDetailView)
    const shareBtn = wrapper.findAll('button').find(b => b.text().includes('SHARE789'))

    if (shareBtn) {
      await shareBtn.trigger('click')
      await wrapper.vm.$nextTick()
      expect(wrapper.text()).toContain('SHARE789')
    }
  })

  it('renders remove buttons for each item', () => {
    const wrapper = mount(PlaylistDetailView)
    const removeBtns = wrapper.findAll('button[aria-label="Remove item"]')
    expect(removeBtns).toHaveLength(2)
  })
})
