import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import PlaylistsView from '@/views/PlaylistsView.vue'
import { useUiStore } from '@/stores/ui'

const api = {
  list: vi.fn(),
  create: vi.fn(),
  delete: vi.fn(),
  share: vi.fn(),
}

vi.mock('@/services/api', () => ({
  playlistsApi: {
    list: (...a: unknown[]) => api.list(...a),
    create: (...a: unknown[]) => api.create(...a),
    delete: (...a: unknown[]) => api.delete(...a),
    share: (...a: unknown[]) => api.share(...a),
  },
}))

const push = vi.fn()
vi.mock('vue-router', () => ({ useRouter: () => ({ push }) }))

// Swap the real dialogs for marker components so we can assert they are
// mounted/unmounted without pulling in their internal networking.
vi.mock('@/components/ShareDialog.vue', () => ({
  default: {
    name: 'ShareDialog',
    props: ['share', 'playlistId'],
    emits: ['close', 'revoked'],
    template: '<div class="share-dialog" :data-code="share.code" />',
  },
}))

vi.mock('@/components/RedeemDialog.vue', () => ({
  default: {
    name: 'RedeemDialog',
    emits: ['close', 'redeemed'],
    template: '<div class="redeem-dialog" />',
  },
}))

function playlist(id: number, overrides: Record<string, unknown> = {}) {
  return {
    id,
    owner_id: 1,
    name: `Playlist ${id}`,
    items_count: 0,
    created_at: new Date().toISOString(),
    updated_at: new Date().toISOString(),
    ...overrides,
  }
}

describe('PlaylistsView.vue', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    Object.values(api).forEach((m) => m.mockReset())
    push.mockReset()
  })

  it('renders playlists loaded from the API with their item counts', async () => {
    api.list.mockResolvedValue([playlist(1, { items_count: 5 }), playlist(2, { items_count: 0 })])
    const wrapper = mount(PlaylistsView)
    await flushPromises()

    expect(wrapper.text()).toContain('Playlist 1')
    expect(wrapper.text()).toContain('5 items')
    expect(wrapper.text()).toContain('0 items')
  })

  it('shows the empty state when the API returns no playlists', async () => {
    api.list.mockResolvedValue([])
    const wrapper = mount(PlaylistsView)
    await flushPromises()

    expect(wrapper.text()).toContain('No playlists yet')
  })

  it('creates a playlist and appends it to the list', async () => {
    api.list.mockResolvedValue([])
    api.create.mockResolvedValue(playlist(3, { name: 'Morning Drive' }))

    const wrapper = mount(PlaylistsView)
    await flushPromises()

    await wrapper.findAll('button').find((b) => b.text().includes('New Playlist'))!.trigger('click')
    await wrapper.get('input[placeholder="Enter playlist name…"]').setValue('Morning Drive')
    await wrapper.findAll('button').find((b) => b.text() === 'Create')!.trigger('click')
    await flushPromises()

    expect(api.create).toHaveBeenCalledWith({ name: 'Morning Drive' })
    expect(wrapper.text()).toContain('Morning Drive')
  })

  it('navigates to detail view when a playlist row is clicked', async () => {
    api.list.mockResolvedValue([playlist(7, { name: 'My List' })])
    const wrapper = mount(PlaylistsView)
    await flushPromises()

    await wrapper.findAll('button').find((b) => b.text().includes('My List'))!.trigger('click')
    expect(push).toHaveBeenCalledWith('/playlists/7')
  })

  it('opens ShareDialog after successful share()', async () => {
    api.list.mockResolvedValue([playlist(9)])
    api.share.mockResolvedValue({
      id: 100,
      playlist_id: 9,
      code: 'ABCDWXYZ',
      expires_at: new Date(Date.now() + 60_000).toISOString(),
    })

    const wrapper = mount(PlaylistsView)
    await flushPromises()

    await wrapper.get('button[aria-label="Share playlist"]').trigger('click')
    await flushPromises()

    expect(api.share).toHaveBeenCalledWith(9, { expires_in_hours: 24 })
    expect(wrapper.find('.share-dialog').attributes('data-code')).toBe('ABCDWXYZ')
  })

  it('deletes a playlist after confirm and removes it from the list', async () => {
    api.list.mockResolvedValue([playlist(5, { name: 'Gone' })])
    api.delete.mockResolvedValue(undefined)
    const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(true)

    const wrapper = mount(PlaylistsView)
    await flushPromises()

    await wrapper.get('button[aria-label="Delete playlist"]').trigger('click')
    await flushPromises()

    expect(api.delete).toHaveBeenCalledWith(5)
    expect(wrapper.text()).not.toContain('Gone')

    confirmSpy.mockRestore()
  })

  it('does NOT delete when confirm() is denied', async () => {
    api.list.mockResolvedValue([playlist(6, { name: 'Keep' })])
    const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(false)

    const wrapper = mount(PlaylistsView)
    await flushPromises()

    await wrapper.get('button[aria-label="Delete playlist"]').trigger('click')
    await flushPromises()

    expect(api.delete).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Keep')

    confirmSpy.mockRestore()
  })

  it('opens the redeem dialog and adds a playlist on redeemed', async () => {
    api.list.mockResolvedValue([])
    const wrapper = mount(PlaylistsView)
    await flushPromises()

    await wrapper.findAll('button').find((b) => b.text().includes('Redeem'))!.trigger('click')
    expect(wrapper.find('.redeem-dialog').exists()).toBe(true)

    // Simulate the dialog emitting redeemed.
    const dialog = wrapper.findComponent({ name: 'RedeemDialog' })
    dialog.vm.$emit('redeemed', playlist(11, { name: 'Shared' }))
    await flushPromises()

    expect(wrapper.text()).toContain('Shared')
    expect(wrapper.find('.redeem-dialog').exists()).toBe(false)
  })

  it('notifies on list load failure', async () => {
    const ui = useUiStore()
    const spy = vi.spyOn(ui, 'addNotification')
    api.list.mockRejectedValue(new Error('boom'))

    mount(PlaylistsView)
    await flushPromises()

    expect(spy).toHaveBeenCalledWith({ type: 'error', message: 'Failed to load playlists' })
  })

  it('revokes a share and clears the ShareDialog', async () => {
    api.list.mockResolvedValue([playlist(9)])
    api.share.mockResolvedValue({
      id: 100,
      playlist_id: 9,
      code: 'ABCDWXYZ',
      expires_at: new Date(Date.now() + 60_000).toISOString(),
    })
    const ui = useUiStore()
    const spy = vi.spyOn(ui, 'addNotification')

    const wrapper = mount(PlaylistsView)
    await flushPromises()

    await wrapper.get('button[aria-label="Share playlist"]').trigger('click')
    await flushPromises()
    expect(wrapper.find('.share-dialog').exists()).toBe(true)

    wrapper.findComponent({ name: 'ShareDialog' }).vm.$emit('revoked')
    await flushPromises()

    expect(wrapper.find('.share-dialog').exists()).toBe(false)
    expect(spy).toHaveBeenCalledWith({ type: 'success', message: 'Share revoked' })
  })

  it('notifies when create playlist fails', async () => {
    api.list.mockResolvedValue([])
    api.create.mockRejectedValue(new Error('create fail'))
    const ui = useUiStore()
    const spy = vi.spyOn(ui, 'addNotification')

    const wrapper = mount(PlaylistsView)
    await flushPromises()

    await wrapper.findAll('button').find((b) => b.text().includes('New Playlist'))!.trigger('click')
    await wrapper.get('input[placeholder="Enter playlist name…"]').setValue('Failed List')
    await wrapper.findAll('button').find((b) => b.text() === 'Create')!.trigger('click')
    await flushPromises()

    expect(spy).toHaveBeenCalledWith({ type: 'error', message: 'Failed to create playlist' })
  })

  it('notifies when share fails', async () => {
    api.list.mockResolvedValue([playlist(9)])
    api.share.mockRejectedValue(new Error('share fail'))
    const ui = useUiStore()
    const spy = vi.spyOn(ui, 'addNotification')

    const wrapper = mount(PlaylistsView)
    await flushPromises()

    await wrapper.get('button[aria-label="Share playlist"]').trigger('click')
    await flushPromises()

    expect(spy).toHaveBeenCalledWith({ type: 'error', message: 'Failed to create share link' })
  })

  it('notifies when delete fails', async () => {
    api.list.mockResolvedValue([playlist(5, { name: 'Sticky' })])
    api.delete.mockRejectedValue(new Error('delete fail'))
    const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(true)
    const ui = useUiStore()
    const spy = vi.spyOn(ui, 'addNotification')

    const wrapper = mount(PlaylistsView)
    await flushPromises()

    await wrapper.get('button[aria-label="Delete playlist"]').trigger('click')
    await flushPromises()

    expect(spy).toHaveBeenCalledWith({ type: 'error', message: 'Failed to delete playlist' })
    confirmSpy.mockRestore()
  })

  it('cancel hides the create input without calling API', async () => {
    api.list.mockResolvedValue([])
    const wrapper = mount(PlaylistsView)
    await flushPromises()

    await wrapper.findAll('button').find((b) => b.text().includes('New Playlist'))!.trigger('click')
    expect(wrapper.find('input[placeholder="Enter playlist name…"]').exists()).toBe(true)

    await wrapper.findAll('button').find((b) => b.text() === 'Cancel')!.trigger('click')
    expect(wrapper.find('input[placeholder="Enter playlist name…"]').exists()).toBe(false)
    expect(api.create).not.toHaveBeenCalled()
  })

  it('renders 1 item (singular) count for a playlist with one item', async () => {
    api.list.mockResolvedValue([playlist(1, { items_count: 1 })])
    const wrapper = mount(PlaylistsView)
    await flushPromises()

    expect(wrapper.text()).toContain('1 item')
    expect(wrapper.text()).not.toContain('1 items')
  })
})
