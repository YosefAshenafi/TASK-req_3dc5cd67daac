import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AssetTile from '@/components/AssetTile.vue'
import type { Asset } from '@/types/api'

const addFavorite = vi.fn()
const removeFavorite = vi.fn()
const play = vi.fn()
const addNotification = vi.fn()
const playerState: { currentAsset: Asset | null; isPlaying: boolean } = {
  currentAsset: null,
  isPlaying: false,
}

vi.mock('@/services/api', () => ({
  favoritesApi: {
    add: (...args: unknown[]) => addFavorite(...args),
    remove: (...args: unknown[]) => removeFavorite(...args),
  },
}))

vi.mock('@/stores/player', () => ({
  usePlayerStore: () => ({
    currentAsset: playerState.currentAsset,
    isPlaying: playerState.isPlaying,
    play: (...args: unknown[]) => play(...args),
  }),
}))

vi.mock('@/stores/ui', () => ({
  useUiStore: () => ({
    addNotification: (...args: unknown[]) => addNotification(...args),
  }),
}))

function makeAsset(overrides: Partial<Asset> = {}): Asset {
  return {
    id: 77,
    title: 'Safety Clip',
    description: 'Parking safety clip',
    mime: 'video/mp4',
    duration_seconds: 125,
    size_bytes: 1024,
    status: 'ready',
    thumbnail_urls: { '160': '/thumb-160.jpg', '480': '/thumb-480.jpg', '960': '/thumb-960.jpg' },
    tags: ['safety', 'parking'],
    created_at: new Date().toISOString(),
    reason_tags: ['recommended'],
    ...overrides,
  }
}

describe('AssetTile.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    playerState.currentAsset = null
    playerState.isPlaying = false
  })

  it('renders duration in mm:ss format and emits addToPlaylist', async () => {
    const asset = makeAsset({ duration_seconds: 125 })
    const wrapper = mount(AssetTile, {
      props: { asset, showReasonTags: true },
    })

    expect(wrapper.text()).toContain('2:05')
    expect(wrapper.text()).toContain('recommended')

    await wrapper.get('button[aria-label="Add to playlist"]').trigger('click')
    expect(wrapper.emitted('addToPlaylist')?.[0]).toEqual([asset])
  })

  it('renders placeholder when thumbnail is missing and hides reason tags by default', () => {
    const asset = makeAsset({
      thumbnail_urls: null,
      duration_seconds: undefined,
      reason_tags: ['recommended'],
      tags: [],
    })
    const wrapper = mount(AssetTile, { props: { asset } })

    expect(wrapper.find('img').exists()).toBe(false)
    expect(wrapper.find('svg').exists()).toBe(true)
    expect(wrapper.text()).not.toContain('recommended')
    expect(wrapper.text()).not.toContain('0:00')
  })

  it('shows now playing indicator and extra-tags counter', () => {
    const asset = makeAsset({
      tags: ['a', 'b', 'c', 'd'],
      reason_tags: [],
    })
    playerState.currentAsset = asset
    playerState.isPlaying = true

    const wrapper = mount(AssetTile, {
      props: { asset, showReasonTags: true },
    })

    expect(wrapper.text()).toContain('Playing')
    expect(wrapper.text()).toContain('+1')
  })

  it('handles hover enter/leave events', async () => {
    const asset = makeAsset()
    const wrapper = mount(AssetTile, { props: { asset } })
    const root = wrapper.get('.group')

    await root.trigger('mouseenter')
    await root.trigger('mouseleave')

    expect(wrapper.exists()).toBe(true)
  })

  it('calls play action when overlay play button is clicked', async () => {
    const asset = makeAsset()
    const wrapper = mount(AssetTile, { props: { asset } })

    await wrapper.get(`button[aria-label="Play ${asset.title}"]`).trigger('click')
    expect(play).toHaveBeenCalledWith(asset)
  })

  it('toggles favorite add/remove and emits corresponding events', async () => {
    const asset = makeAsset()
    const wrapper = mount(AssetTile, { props: { asset } })

    await wrapper.get('button[aria-label="Add to favorites"]').trigger('click')
    expect(addFavorite).toHaveBeenCalledWith(asset.id)
    expect(wrapper.emitted('favorited')?.[0]).toEqual([asset.id])

    await wrapper.get('button[aria-label="Remove from favorites"]').trigger('click')
    expect(removeFavorite).toHaveBeenCalledWith(asset.id)
    expect(wrapper.emitted('unfavorited')?.[0]).toEqual([asset.id])
  })

  it('shows UI notification when favorite request fails', async () => {
    addFavorite.mockRejectedValueOnce(new Error('network'))
    const asset = makeAsset()
    const wrapper = mount(AssetTile, { props: { asset } })

    await wrapper.get('button[aria-label="Add to favorites"]').trigger('click')

    expect(addNotification).toHaveBeenCalledWith({
      type: 'error',
      message: 'Failed to update favorite',
    })
  })
})
