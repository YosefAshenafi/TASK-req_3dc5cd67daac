import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import FavoritesView from '@/views/FavoritesView.vue'
import { useUiStore } from '@/stores/ui'

const listMock = vi.fn()
vi.mock('@/services/api', () => ({
  favoritesApi: { list: (...a: unknown[]) => listMock(...a) },
}))

// Stub out AssetTile to watch for 'unfavorited' handling without needing the
// real favoritesApi binding inside the tile.
vi.mock('@/components/AssetTile.vue', () => ({
  default: {
    name: 'AssetTile',
    props: ['asset', 'isFavorited'],
    emits: ['unfavorited', 'addToPlaylist'],
    template: `
      <div class="asset-tile" :data-id="asset.id">
        {{ asset.title }}
        <button class="unfav" @click="$emit('unfavorited', asset.id)">unfav</button>
        <button class="atp" @click="$emit('addToPlaylist', asset)">add</button>
      </div>
    `,
  },
}))

vi.mock('@/components/AddToPlaylistDialog.vue', () => ({
  default: { name: 'AddToPlaylistDialog', props: ['asset'], template: '<div class="add-to-playlist" />' },
}))

function favorite(assetId: number, asset?: Record<string, unknown> | null) {
  return {
    asset_id: assetId,
    created_at: new Date().toISOString(),
    asset:
      asset === null
        ? null
        : {
            id: assetId,
            title: `Fav ${assetId}`,
            mime: 'audio/mpeg',
            size_bytes: 0,
            status: 'ready',
            tags: [],
            created_at: new Date().toISOString(),
            ...(asset ?? {}),
          },
  }
}

describe('FavoritesView.vue', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    listMock.mockReset()
  })

  it('renders favorites and removes one locally when the tile emits unfavorited', async () => {
    listMock.mockResolvedValue({ items: [favorite(1), favorite(2)], next_cursor: null })

    const wrapper = mount(FavoritesView)
    await flushPromises()

    expect(wrapper.findAll('.asset-tile')).toHaveLength(2)

    // Click the unfavorite control on the first tile.
    await wrapper.get('.asset-tile[data-id="1"] .unfav').trigger('click')
    // Local filter removes the row without a network round-trip.
    expect(wrapper.findAll('.asset-tile')).toHaveLength(1)
    expect(wrapper.find('.asset-tile[data-id="1"]').exists()).toBe(false)
  })

  it('renders the missing-asset placeholder when a favorite has asset:null', async () => {
    listMock.mockResolvedValue({ items: [favorite(1, null)], next_cursor: null })
    const wrapper = mount(FavoritesView)
    await flushPromises()

    expect(wrapper.text()).toContain('Asset unavailable')
    expect(wrapper.findAll('.asset-tile')).toHaveLength(0)
  })

  it('renders the empty-state when no favorites exist', async () => {
    listMock.mockResolvedValue({ items: [], next_cursor: null })
    const wrapper = mount(FavoritesView)
    await flushPromises()

    expect(wrapper.text()).toContain('No favorites yet')
  })

  it('paginates via Load more', async () => {
    listMock
      .mockResolvedValueOnce({ items: [favorite(1)], next_cursor: 'cur-2' })
      .mockResolvedValueOnce({ items: [favorite(2)], next_cursor: null })

    const wrapper = mount(FavoritesView)
    await flushPromises()

    const loadMore = wrapper.findAll('button').find((b) => b.text().includes('Load more'))!
    await loadMore.trigger('click')
    await flushPromises()

    expect(listMock.mock.calls[1]![0]).toBe('cur-2')
    expect(wrapper.findAll('.asset-tile')).toHaveLength(2)
  })

  it('opens AddToPlaylistDialog when a tile emits addToPlaylist, then closes it', async () => {
    listMock.mockResolvedValue({ items: [favorite(1)], next_cursor: null })
    const wrapper = mount(FavoritesView)
    await flushPromises()

    // Trigger addToPlaylist from the stub tile.
    await wrapper.get('.asset-tile[data-id="1"] .atp').trigger('click')
    expect(wrapper.find('.add-to-playlist').exists()).toBe(true)

    // Close the dialog.
    await wrapper.findComponent({ name: 'AddToPlaylistDialog' }).vm.$emit('close')
    await flushPromises()
    expect(wrapper.find('.add-to-playlist').exists()).toBe(false)
  })

  it('notifies on fetch failure', async () => {
    const ui = useUiStore()
    const spy = vi.spyOn(ui, 'addNotification')
    listMock.mockRejectedValue(new Error('boom'))

    mount(FavoritesView)
    await flushPromises()

    expect(spy).toHaveBeenCalledWith({ type: 'error', message: 'Failed to load favorites' })
  })
})
