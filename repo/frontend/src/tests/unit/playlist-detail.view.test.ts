import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import PlaylistDetailView from '@/views/PlaylistDetailView.vue'
import { useUiStore } from '@/stores/ui'

const api = {
  get: vi.fn(),
  update: vi.fn(),
  delete: vi.fn(),
  removeItem: vi.fn(),
  reorderItems: vi.fn(),
  share: vi.fn(),
}

vi.mock('@/services/api', () => ({
  playlistsApi: {
    get: (...a: unknown[]) => api.get(...a),
    update: (...a: unknown[]) => api.update(...a),
    delete: (...a: unknown[]) => api.delete(...a),
    removeItem: (...a: unknown[]) => api.removeItem(...a),
    reorderItems: (...a: unknown[]) => api.reorderItems(...a),
    share: (...a: unknown[]) => api.share(...a),
  },
}))

const push = vi.fn()
vi.mock('vue-router', () => ({
  useRoute: () => ({ params: { id: '42' } }),
  useRouter: () => ({ push }),
}))

vi.mock('@/components/ShareDialog.vue', () => ({
  default: {
    name: 'ShareDialog',
    props: ['share', 'playlistId'],
    emits: ['close', 'revoked'],
    template: '<div class="share-dialog" />',
  },
}))

function item(id: number, overrides: Record<string, unknown> = {}) {
  return {
    id,
    playlist_id: 42,
    asset_id: id,
    position: id,
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

function playlist(items: ReturnType<typeof item>[] = []) {
  return {
    id: 42,
    owner_id: 1,
    name: 'Playlist 42',
    items,
    items_count: items.length,
    created_at: new Date().toISOString(),
    updated_at: new Date().toISOString(),
  }
}

describe('PlaylistDetailView.vue', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    Object.values(api).forEach((m) => m.mockReset())
    push.mockReset()
  })

  it('loads and renders playlist items in order', async () => {
    api.get.mockResolvedValue(playlist([item(1), item(2), item(3)]))

    const wrapper = mount(PlaylistDetailView)
    await flushPromises()

    expect(wrapper.text()).toContain('Playlist 42')
    expect(wrapper.text()).toContain('3 items')
    const titles = wrapper.findAll('p.text-sm.font-medium.text-slate-900').map((n) => n.text())
    expect(titles).toContain('Track 1')
    expect(titles).toContain('Track 2')
    expect(titles).toContain('Track 3')
  })

  it('empty items renders the no-items placeholder', async () => {
    api.get.mockResolvedValue(playlist([]))
    const wrapper = mount(PlaylistDetailView)
    await flushPromises()

    expect(wrapper.text()).toContain('No items in this playlist yet')
  })

  it('redirects to /playlists on fetch failure and shows a toast', async () => {
    const ui = useUiStore()
    const spy = vi.spyOn(ui, 'addNotification')
    api.get.mockRejectedValue(new Error('missing'))

    mount(PlaylistDetailView)
    await flushPromises()

    expect(spy).toHaveBeenCalledWith({ type: 'error', message: 'Failed to load playlist' })
    expect(push).toHaveBeenCalledWith('/playlists')
  })

  it('renames the playlist and updates the header', async () => {
    api.get.mockResolvedValue(playlist([item(1)]))
    api.update.mockResolvedValue({ ...playlist([item(1)]), name: 'Renamed' })
    const wrapper = mount(PlaylistDetailView)
    await flushPromises()

    await wrapper.get('button[aria-label="Edit name"]').trigger('click')
    const input = wrapper.get('input')
    await input.setValue('Renamed')
    await wrapper.findAll('button').find((b) => b.text().includes('Save'))!.trigger('click')
    await flushPromises()

    expect(api.update).toHaveBeenCalledWith(42, { name: 'Renamed' })
    expect(wrapper.find('h1').text()).toBe('Renamed')
  })

  it('deletes the playlist and navigates to /playlists', async () => {
    api.get.mockResolvedValue(playlist([item(1)]))
    api.delete.mockResolvedValue(undefined)
    const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(true)

    const wrapper = mount(PlaylistDetailView)
    await flushPromises()

    await wrapper.findAll('button').find((b) => b.text().includes('Delete'))!.trigger('click')
    await flushPromises()

    expect(api.delete).toHaveBeenCalledWith(42)
    expect(push).toHaveBeenCalledWith('/playlists')
    confirmSpy.mockRestore()
  })

  it('removes an item and drops it from the rendered list', async () => {
    api.get.mockResolvedValue(playlist([item(1), item(2)]))
    api.removeItem.mockResolvedValue(undefined)

    const wrapper = mount(PlaylistDetailView)
    await flushPromises()

    // Remove (×) button is the last button in the per-item action cluster.
    const deleteButtons = wrapper
      .findAll('button')
      .filter((b) => b.find('svg path').exists() && b.html().includes('M6 18L18 6'))
    await deleteButtons[0]!.trigger('click')
    await flushPromises()

    expect(api.removeItem).toHaveBeenCalledWith(42, 1)
    // Only the second item remains.
    expect(wrapper.text()).not.toContain('Track 1')
    expect(wrapper.text()).toContain('Track 2')
  })

  it('reorders items and sends the new ordering to the API', async () => {
    api.get.mockResolvedValue(playlist([item(1), item(2), item(3)]))
    api.reorderItems.mockResolvedValue([])

    const wrapper = mount(PlaylistDetailView)
    await flushPromises()

    // Find the "down" arrows (second svg icon per item); click on index 0 to
    // swap items 1 and 2.
    const downArrows = wrapper
      .findAll('button')
      .filter((b) => b.html().includes('M19 9l-7 7-7-7'))
    await downArrows[0]!.trigger('click')
    await flushPromises()

    expect(api.reorderItems).toHaveBeenCalledWith(42, { item_ids: [2, 1, 3] })
  })

  it('opens ShareDialog after successful share()', async () => {
    api.get.mockResolvedValue(playlist([item(1)]))
    api.share.mockResolvedValue({
      id: 99,
      playlist_id: 42,
      code: 'SHARECODE',
      expires_at: new Date(Date.now() + 60_000).toISOString(),
    })

    const wrapper = mount(PlaylistDetailView)
    await flushPromises()

    await wrapper.findAll('button').find((b) => b.text().includes('Share'))!.trigger('click')
    await flushPromises()

    expect(api.share).toHaveBeenCalledWith(42, { expires_in_hours: 24 })
    expect(wrapper.find('.share-dialog').exists()).toBe(true)
  })
})
