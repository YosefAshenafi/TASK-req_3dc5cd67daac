import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import PlaylistsView from '@/views/PlaylistsView.vue'

vi.mock('@/stores/playlist', () => ({
  usePlaylistStore: () => ({
    playlists: [
      { id: 1, name: 'Lobby Tunes', description: 'Morning set', items: [], share_code: 'ABCD1234' },
      { id: 2, name: 'Evening Mix', description: null, items: [{ id: 10 }], share_code: 'WXYZ5678' },
    ],
    isLoading: false,
    fetchAll: vi.fn(),
    remove: vi.fn(),
  }),
}))

vi.mock('@/components/CreatePlaylistModal.vue', () => ({
  default: { template: '<div data-testid="create-modal" />' },
}))

vi.mock('@/components/RedeemCodeModal.vue', () => ({
  default: { template: '<div data-testid="redeem-modal" />' },
}))

describe('PlaylistsView', () => {
  it('renders playlist names', () => {
    const wrapper = mount(PlaylistsView)
    expect(wrapper.text()).toContain('Lobby Tunes')
    expect(wrapper.text()).toContain('Evening Mix')
  })

  it('renders share codes for each playlist', () => {
    const wrapper = mount(PlaylistsView)
    expect(wrapper.text()).toContain('ABCD1234')
    expect(wrapper.text()).toContain('WXYZ5678')
  })

  it('shows create modal when New Playlist button clicked', async () => {
    const wrapper = mount(PlaylistsView)
    expect(wrapper.find('[data-testid="create-modal"]').exists()).toBe(false)
    const newBtn = wrapper.findAll('button').find(b => b.text().includes('New Playlist'))
    await newBtn.trigger('click')
    expect(wrapper.find('[data-testid="create-modal"]').exists()).toBe(true)
  })

  it('shows redeem modal when Redeem Code button clicked', async () => {
    const wrapper = mount(PlaylistsView)
    const redeemBtn = wrapper.findAll('button').find(b => b.text().includes('Redeem Code'))
    await redeemBtn.trigger('click')
    expect(wrapper.find('[data-testid="redeem-modal"]').exists()).toBe(true)
  })

  it('renders item count for each playlist', () => {
    const wrapper = mount(PlaylistsView)
    expect(wrapper.text()).toContain('0 items')
    expect(wrapper.text()).toContain('1 items')
  })

  it('share code button has aria-label with the code', () => {
    const wrapper = mount(PlaylistsView)
    const buttons = wrapper.findAll('button[aria-label]')
    const labels = buttons.map(b => b.attributes('aria-label'))
    expect(labels.some(l => l?.includes('ABCD1234'))).toBe(true)
  })
})
