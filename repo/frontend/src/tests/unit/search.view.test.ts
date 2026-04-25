import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import SearchView from '@/views/SearchView.vue'
import { useUiStore } from '@/stores/ui'

const searchMock = vi.fn()

vi.mock('@/services/api', () => ({
  searchApi: { search: (...args: unknown[]) => searchMock(...args) },
}))

// AssetTile pulls in favoritesApi/usePlayerStore; stub it out so this view test
// can focus on filter/search orchestration instead of tile internals.
vi.mock('@/components/AssetTile.vue', () => ({
  default: {
    name: 'AssetTile',
    props: ['asset', 'showReasonTags'],
    emits: ['addToPlaylist'],
    template: '<div class="asset-tile" :data-id="asset.id" :data-show-reasons="showReasonTags" @click="$emit(\'addToPlaylist\', asset)">{{ asset.title }}</div>',
  },
}))

vi.mock('@/components/AddToPlaylistDialog.vue', () => ({
  default: {
    name: 'AddToPlaylistDialog',
    props: ['asset'],
    emits: ['close'],
    template: '<div class="add-to-playlist-dialog" :data-asset-id="asset.id"></div>',
  },
}))

function makeAsset(id: number, overrides: Record<string, unknown> = {}) {
  return {
    id,
    title: `Asset ${id}`,
    mime: 'audio/mpeg',
    size_bytes: 100,
    status: 'ready',
    tags: [],
    created_at: new Date().toISOString(),
    ...overrides,
  }
}

describe('SearchView.vue', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    searchMock.mockReset()
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  async function runDebounce() {
    // Advance past the 300ms debounce AND flush the resulting promise so the
    // search call's state (items, nextCursor, degraded) reaches the template.
    await vi.advanceTimersByTimeAsync(350)
    await flushPromises()
  }

  it('loads the initial result set on mount with sort=recommended', async () => {
    searchMock.mockResolvedValue({
      data: { items: [makeAsset(1), makeAsset(2)], next_cursor: null },
      degraded: false,
    })

    const wrapper = mount(SearchView)
    await flushPromises() // onMounted → initial search

    expect(searchMock).toHaveBeenCalledTimes(1)
    const firstCall = searchMock.mock.calls[0]![0]
    expect(firstCall.sort).toBe('recommended')
    expect(firstCall.per_page).toBe(24)

    // Tiles for both assets are rendered and reason tags are enabled because
    // sort is 'recommended'.
    expect(wrapper.findAll('.asset-tile')).toHaveLength(2)
    expect(wrapper.find('.asset-tile').attributes('data-show-reasons')).toBe('true')
  })

  it('debounces typing in the query input before firing a new search', async () => {
    searchMock.mockResolvedValue({ data: { items: [], next_cursor: null }, degraded: false })
    const wrapper = mount(SearchView)
    await flushPromises()
    searchMock.mockClear()

    const input = wrapper.get('input[type="search"]')
    await input.setValue('ga')
    await input.setValue('gate')
    // Before the debounce fires, no additional search has happened.
    expect(searchMock).not.toHaveBeenCalled()

    await runDebounce()
    // Only one search fires for the latest value.
    expect(searchMock).toHaveBeenCalledTimes(1)
    expect(searchMock.mock.calls[0]![0].q).toBe('gate')
  })

  it('re-runs search with the new sort when a sort option is clicked', async () => {
    searchMock.mockResolvedValue({ data: { items: [], next_cursor: null }, degraded: false })
    const wrapper = mount(SearchView)
    await flushPromises()
    searchMock.mockClear()

    const newestBtn = wrapper.findAll('button').find((b) => b.text() === 'Newest')!
    await newestBtn.trigger('click')
    await flushPromises()
    await runDebounce()

    expect(searchMock).toHaveBeenCalledTimes(1)
    expect(searchMock.mock.calls[0]![0].sort).toBe('newest')
  })

  it('renders tag chips for every available tag', async () => {
    searchMock.mockResolvedValue({ data: { items: [], next_cursor: null }, degraded: false })
    const wrapper = mount(SearchView)
    await flushPromises()

    // Default tags from the settings store seed — verify they all render so
    // the user has something to click even before settings.load() runs.
    for (const tag of ['Safety', 'Overnight', 'Gate Issues', 'Parking']) {
      const btn = wrapper.findAll('button').find((b) => b.text() === tag)
      expect(btn, `expected to find tag chip for "${tag}"`).toBeDefined()
    }
  })

  it('surfaces degraded=true with a banner when the API reports degraded recommendations', async () => {
    searchMock.mockResolvedValue({ data: { items: [], next_cursor: null }, degraded: true })
    const wrapper = mount(SearchView)
    await flushPromises()

    expect(wrapper.text()).toContain('Recommendations degraded')
  })

  it('pushes an error notification when the search call rejects', async () => {
    const ui = useUiStore()
    const spy = vi.spyOn(ui, 'addNotification')
    searchMock.mockRejectedValue(new Error('boom'))

    mount(SearchView)
    await flushPromises()

    expect(spy).toHaveBeenCalledWith({ type: 'error', message: 'Search failed' })
  })

  it('opens the AddToPlaylistDialog when a tile emits addToPlaylist', async () => {
    searchMock.mockResolvedValue({ data: { items: [makeAsset(42)], next_cursor: null }, degraded: false })
    const wrapper = mount(SearchView)
    await flushPromises()

    expect(wrapper.find('.add-to-playlist-dialog').exists()).toBe(false)
    await wrapper.get('.asset-tile').trigger('click')
    expect(wrapper.find('.add-to-playlist-dialog').attributes('data-asset-id')).toBe('42')
  })

  it('paginates via Load more using the next_cursor returned by the first page', async () => {
    searchMock
      .mockResolvedValueOnce({ data: { items: [makeAsset(1)], next_cursor: 'cur-2' }, degraded: false })
      .mockResolvedValueOnce({ data: { items: [makeAsset(2)], next_cursor: null }, degraded: false })

    const wrapper = mount(SearchView)
    await flushPromises()

    const loadMore = wrapper.findAll('button').find((b) => b.text().includes('Load more results'))!
    await loadMore.trigger('click')
    await flushPromises()

    expect(searchMock).toHaveBeenCalledTimes(2)
    expect(searchMock.mock.calls[1]![0].cursor).toBe('cur-2')
    expect(wrapper.findAll('.asset-tile')).toHaveLength(2)
  })

  it('silently ignores AbortError without showing a notification', async () => {
    const ui = useUiStore()
    const spy = vi.spyOn(ui, 'addNotification')
    const abortErr = Object.assign(new Error('aborted'), { name: 'AbortError' })
    searchMock.mockRejectedValue(abortErr)

    mount(SearchView)
    await flushPromises()

    expect(spy).not.toHaveBeenCalled()
  })

  it('deselects a tag when clicking it a second time', async () => {
    searchMock.mockResolvedValue({ data: { items: [], next_cursor: null }, degraded: false })
    const wrapper = mount(SearchView)
    await flushPromises()
    searchMock.mockClear()

    const safetyBtn = wrapper.findAll('button').find((b) => b.text() === 'Safety')!
    await safetyBtn.trigger('click')
    await runDebounce()
    const callWithTag = searchMock.mock.calls[0]![0]
    expect(callWithTag.tags).toContain('Safety')

    searchMock.mockClear()
    await safetyBtn.trigger('click')
    await runDebounce()
    const callWithoutTag = searchMock.mock.calls[0]![0]
    expect(callWithoutTag.tags).toBeUndefined()
  })

  it('applies duration and recency filters when buttons are clicked', async () => {
    searchMock.mockResolvedValue({ data: { items: [], next_cursor: null }, degraded: false })
    const wrapper = mount(SearchView)
    await flushPromises()
    searchMock.mockClear()

    const dur2min = wrapper.findAll('button').find((b) => b.text() === '< 2 min')!
    await dur2min.trigger('click')
    await runDebounce()
    expect(searchMock.mock.calls[0]![0].duration_lt).toBe(120)

    searchMock.mockClear()
    const days30 = wrapper.findAll('button').find((b) => b.text() === '30 days')!
    await days30.trigger('click')
    await runDebounce()
    expect(searchMock.mock.calls[0]![0].recent_days).toBe(30)
  })

  it('hides reason tags when sort is not recommended', async () => {
    searchMock.mockResolvedValue({ data: { items: [makeAsset(1)], next_cursor: null }, degraded: false })
    const wrapper = mount(SearchView)
    await flushPromises()

    const mostPlayedBtn = wrapper.findAll('button').find((b) => b.text() === 'Most Played')!
    await mostPlayedBtn.trigger('click')
    await flushPromises()
    await runDebounce()

    expect(wrapper.find('.asset-tile').attributes('data-show-reasons')).toBe('false')
  })
})
