import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AddToPlaylistDialog from '@/components/AddToPlaylistDialog.vue'
import { useUiStore } from '@/stores/ui'
import type { Asset } from '@/types/api'

const api = {
  list: vi.fn(),
  addItem: vi.fn(),
}

vi.mock('@/services/api', () => ({
  playlistsApi: {
    list: (...a: unknown[]) => api.list(...a),
    addItem: (...a: unknown[]) => api.addItem(...a),
  },
}))

function asset(): Asset {
  return {
    id: 77,
    title: 'Target Asset',
    mime: 'audio/mpeg',
    size_bytes: 0,
    status: 'ready',
    tags: [],
    created_at: new Date().toISOString(),
  } as Asset
}

describe('AddToPlaylistDialog.vue', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    Object.values(api).forEach((m) => m.mockReset())
  })

  it('loads playlists and renders one button per playlist', async () => {
    api.list.mockResolvedValue([
      { id: 1, name: 'Morning', items_count: 3 },
      { id: 2, name: 'Evening', items_count: 0 },
    ])

    const wrapper = mount(AddToPlaylistDialog, { props: { asset: asset() } })
    await flushPromises()

    expect(wrapper.text()).toContain('Morning')
    expect(wrapper.text()).toContain('Evening')
    expect(wrapper.text()).toContain('3 items')
  })

  it('falls back to items.length when items_count is absent, then to 0', async () => {
    api.list.mockResolvedValue([
      { id: 1, name: 'WithItems', items: [{ id: 10 }, { id: 11 }] },
      { id: 2, name: 'Empty' },
    ])

    const wrapper = mount(AddToPlaylistDialog, { props: { asset: asset() } })
    await flushPromises()

    expect(wrapper.text()).toContain('2 items')
    expect(wrapper.text()).toContain('0 items')
  })

  it('renders an empty-state when no playlists exist', async () => {
    api.list.mockResolvedValue([])
    const wrapper = mount(AddToPlaylistDialog, { props: { asset: asset() } })
    await flushPromises()

    expect(wrapper.text()).toContain('No playlists yet')
  })

  it('adds the asset to the chosen playlist and emits close', async () => {
    api.list.mockResolvedValue([{ id: 5, name: 'Target', items_count: 0 }])
    api.addItem.mockResolvedValue({ id: 100, playlist_id: 5, asset_id: 77, position: 1 })
    const ui = useUiStore()
    const notify = vi.spyOn(ui, 'addNotification')

    const wrapper = mount(AddToPlaylistDialog, { props: { asset: asset() } })
    await flushPromises()

    await wrapper.findAll('button').find((b) => b.text().includes('Target'))!.trigger('click')
    await flushPromises()

    expect(api.addItem).toHaveBeenCalledWith(5, { asset_id: 77 })
    expect(notify).toHaveBeenCalledWith({ type: 'success', message: 'Added to "Target"' })
    expect(wrapper.emitted('close')).toBeTruthy()
  })

  it('notifies on add failure and does NOT emit close', async () => {
    api.list.mockResolvedValue([{ id: 5, name: 'Target', items_count: 0 }])
    api.addItem.mockRejectedValue(new Error('boom'))
    const ui = useUiStore()
    const notify = vi.spyOn(ui, 'addNotification')

    const wrapper = mount(AddToPlaylistDialog, { props: { asset: asset() } })
    await flushPromises()

    await wrapper.findAll('button').find((b) => b.text().includes('Target'))!.trigger('click')
    await flushPromises()

    expect(notify).toHaveBeenCalledWith({ type: 'error', message: 'Failed to add to playlist' })
    expect(wrapper.emitted('close')).toBeFalsy()
  })

  it('emits close when the backdrop is clicked', async () => {
    api.list.mockResolvedValue([])
    const wrapper = mount(AddToPlaylistDialog, { props: { asset: asset() } })
    await flushPromises()

    // The root element is the backdrop; @click.self fires on direct clicks.
    await wrapper.trigger('click')
    expect(wrapper.emitted('close')).toBeTruthy()
  })

  it('emits close when the Cancel button is clicked', async () => {
    api.list.mockResolvedValue([])
    const wrapper = mount(AddToPlaylistDialog, { props: { asset: asset() } })
    await flushPromises()

    await wrapper.findAll('button').find((b) => b.text() === 'Cancel')!.trigger('click')
    expect(wrapper.emitted('close')).toBeTruthy()
  })

  it('shows an error toast when the initial list load fails', async () => {
    api.list.mockRejectedValue(new Error('offline'))
    const ui = useUiStore()
    const notify = vi.spyOn(ui, 'addNotification')

    mount(AddToPlaylistDialog, { props: { asset: asset() } })
    await flushPromises()

    expect(notify).toHaveBeenCalledWith({ type: 'error', message: 'Failed to load playlists' })
  })
})
