import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import LibraryView from '@/views/LibraryView.vue'
import { useUiStore } from '@/stores/ui'

const listMock = vi.fn()
vi.mock('@/services/api', () => ({
  assetsApi: { list: (...a: unknown[]) => listMock(...a) },
}))

vi.mock('@/components/AssetTile.vue', () => ({
  default: {
    name: 'AssetTile',
    props: ['asset'],
    emits: ['addToPlaylist'],
    template: '<div class="asset-tile" :data-id="asset.id">{{ asset.title }}</div>',
  },
}))

vi.mock('@/components/AddToPlaylistDialog.vue', () => ({
  default: { name: 'AddToPlaylistDialog', props: ['asset'], template: '<div class="add-to-playlist" />' },
}))

function asset(id: number, overrides: Record<string, unknown> = {}) {
  return {
    id,
    title: `Track ${id}`,
    mime: 'audio/mpeg',
    size_bytes: 10,
    status: 'ready',
    tags: [],
    created_at: new Date().toISOString(),
    ...overrides,
  }
}

describe('LibraryView.vue', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    listMock.mockReset()
  })

  it('renders assets from the initial list call with sort=newest', async () => {
    listMock.mockResolvedValue({ items: [asset(1), asset(2)], next_cursor: null })

    const wrapper = mount(LibraryView)
    await flushPromises()

    expect(listMock).toHaveBeenCalledTimes(1)
    expect(listMock.mock.calls[0]![0]).toMatchObject({ sort: 'newest', limit: 24 })
    expect(wrapper.findAll('.asset-tile')).toHaveLength(2)
  })

  it('switches to most_played and refetches when the sort toggle is clicked', async () => {
    listMock.mockResolvedValue({ items: [asset(1)], next_cursor: null })
    const wrapper = mount(LibraryView)
    await flushPromises()

    const mostPlayed = wrapper.findAll('button').find((b) => b.text() === 'Most Played')!
    await mostPlayed.trigger('click')
    await flushPromises()

    expect(listMock).toHaveBeenCalledTimes(2)
    expect(listMock.mock.calls[1]![0].sort).toBe('most_played')
  })

  it('renders the empty state when the API returns no items', async () => {
    listMock.mockResolvedValue({ items: [], next_cursor: null })
    const wrapper = mount(LibraryView)
    await flushPromises()

    expect(wrapper.text()).toContain('Library is empty')
  })

  it('notifies on fetch failure without crashing', async () => {
    const ui = useUiStore()
    const spy = vi.spyOn(ui, 'addNotification')
    listMock.mockRejectedValue(new Error('boom'))

    mount(LibraryView)
    await flushPromises()

    expect(spy).toHaveBeenCalledWith({ type: 'error', message: 'Failed to load library' })
  })

  it('appends results from next page when Load more is clicked', async () => {
    listMock
      .mockResolvedValueOnce({ items: [asset(1)], next_cursor: 'cur-2' })
      .mockResolvedValueOnce({ items: [asset(2)], next_cursor: null })

    const wrapper = mount(LibraryView)
    await flushPromises()

    const loadMore = wrapper.findAll('button').find((b) => b.text().includes('Load more'))!
    await loadMore.trigger('click')
    await flushPromises()

    expect(listMock.mock.calls[1]![0].cursor).toBe('cur-2')
    expect(wrapper.findAll('.asset-tile')).toHaveLength(2)
  })

  it('opens AddToPlaylistDialog when an asset tile emits addToPlaylist', async () => {
    listMock.mockResolvedValue({ items: [asset(7)], next_cursor: null })

    const wrapper = mount(LibraryView)
    await flushPromises()

    expect(wrapper.find('.add-to-playlist').exists()).toBe(false)
    wrapper.findComponent({ name: 'AssetTile' }).vm.$emit('addToPlaylist', asset(7))
    await flushPromises()

    expect(wrapper.find('.add-to-playlist').exists()).toBe(true)
  })

  it('closes the AddToPlaylistDialog when it emits close', async () => {
    listMock.mockResolvedValue({ items: [asset(7)], next_cursor: null })

    const wrapper = mount(LibraryView)
    await flushPromises()

    wrapper.findComponent({ name: 'AssetTile' }).vm.$emit('addToPlaylist', asset(7))
    await flushPromises()
    expect(wrapper.find('.add-to-playlist').exists()).toBe(true)

    wrapper.findComponent({ name: 'AddToPlaylistDialog' }).vm.$emit('close')
    await flushPromises()
    expect(wrapper.find('.add-to-playlist').exists()).toBe(false)
  })
})
