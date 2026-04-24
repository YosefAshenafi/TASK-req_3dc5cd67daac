import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useSettingsStore } from '@/stores/settings'

vi.mock('@/services/api', () => ({
  settingsApi: {
    get: vi.fn(),
  },
}))

describe('Settings Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('load applies backend settings and marks store as loaded', async () => {
    const { settingsApi } = await import('@/services/api')
    vi.mocked(settingsApi.get).mockResolvedValue({
      site_name: 'SmartPark Ops',
      site_tagline: 'Operate with confidence',
      available_tags: ['Safety', 'Urgent'],
    })

    const store = useSettingsStore()
    await store.load()

    expect(settingsApi.get).toHaveBeenCalledTimes(1)
    expect(store.siteName).toBe('SmartPark Ops')
    expect(store.siteTagline).toBe('Operate with confidence')
    expect(store.availableTags).toEqual(['Safety', 'Urgent'])
    expect(store.loaded).toBe(true)
  })

  it('load falls back to defaults when backend returns empty values', async () => {
    const { settingsApi } = await import('@/services/api')
    vi.mocked(settingsApi.get).mockResolvedValue({
      site_name: '',
      site_tagline: '',
      available_tags: [],
    })

    const store = useSettingsStore()
    await store.load()

    expect(store.siteName).toBe('SmartPark')
    expect(store.siteTagline).toBe('Find and discover media assets')
    expect(store.availableTags).toEqual([
      'Safety',
      'Overnight',
      'Gate Issues',
      'Parking',
      'Event',
      'General',
      'Emergency',
    ])
    expect(store.loaded).toBe(true)
  })

  it('load does not re-fetch after store is loaded', async () => {
    const { settingsApi } = await import('@/services/api')
    vi.mocked(settingsApi.get).mockResolvedValue({
      site_name: 'One-time load',
      site_tagline: 'No refetch',
      available_tags: ['Tag'],
    })

    const store = useSettingsStore()
    await store.load()
    await store.load()

    expect(settingsApi.get).toHaveBeenCalledTimes(1)
  })

  it('load keeps defaults and stays not-loaded when API fails', async () => {
    const { settingsApi } = await import('@/services/api')
    vi.mocked(settingsApi.get).mockRejectedValue(new Error('network'))

    const store = useSettingsStore()
    await store.load()

    expect(store.siteName).toBe('SmartPark')
    expect(store.siteTagline).toBe('Find and discover media assets')
    expect(store.loaded).toBe(false)
  })

  it('applyUpdate updates all fields', () => {
    const store = useSettingsStore()

    store.applyUpdate({
      site_name: 'Updated Name',
      site_tagline: 'Updated Tagline',
      available_tags: ['A', 'B'],
    })

    expect(store.siteName).toBe('Updated Name')
    expect(store.siteTagline).toBe('Updated Tagline')
    expect(store.availableTags).toEqual(['A', 'B'])
  })
})
