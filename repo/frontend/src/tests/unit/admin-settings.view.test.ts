import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AdminSettingsView from '@/views/admin/AdminSettingsView.vue'
import { useSettingsStore } from '@/stores/settings'
import { useUiStore } from '@/stores/ui'

const api = {
  get: vi.fn(),
  update: vi.fn(),
}

vi.mock('@/services/api', async () => {
  const actual = await vi.importActual<typeof import('@/services/api')>('@/services/api')
  return {
    ...actual,
    settingsApi: {
      get: (...a: unknown[]) => api.get(...a),
      update: (...a: unknown[]) => api.update(...a),
    },
  }
})

describe('AdminSettingsView.vue', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    api.get.mockReset()
    api.update.mockReset()
  })

  it('pre-fills the site identity inputs and tag list from the store after mount', async () => {
    api.get.mockResolvedValue({
      site_name: 'ParkCo',
      site_tagline: 'Roll tape',
      available_tags: ['Alpha', 'Beta'],
    })

    const wrapper = mount(AdminSettingsView)
    await flushPromises()

    const inputs = wrapper.findAll('input')
    // First input is Site Name, second is Tagline, third is the new-tag input.
    expect((inputs[0]!.element as HTMLInputElement).value).toBe('ParkCo')
    expect((inputs[1]!.element as HTMLInputElement).value).toBe('Roll tape')
    expect(wrapper.text()).toContain('Alpha')
    expect(wrapper.text()).toContain('Beta')
  })

  it('shows a helpful empty state when no tags are configured', async () => {
    const store = useSettingsStore()
    store.availableTags = []
    store.loaded = true // skip the API call path in the store
    api.get.mockResolvedValue({ site_name: '', site_tagline: '', available_tags: [] })

    const wrapper = mount(AdminSettingsView)
    await flushPromises()

    expect(wrapper.text()).toContain('No tags yet')
  })

  it('adds a tag via the Add button and renders it as a chip', async () => {
    api.get.mockResolvedValue({ site_name: 'X', site_tagline: 'Y', available_tags: [] })

    const wrapper = mount(AdminSettingsView)
    await flushPromises()

    // The new-tag input is the third <input> (site name, tagline, new-tag).
    const newTagInput = wrapper.findAll('input')[2]!
    await newTagInput.setValue('Safety')
    await wrapper.findAll('button').find((b) => b.text().includes('Add'))!.trigger('click')

    expect(wrapper.text()).toContain('Safety')
  })

  it('pressing Enter in the new-tag input also adds the tag', async () => {
    api.get.mockResolvedValue({ site_name: 'X', site_tagline: 'Y', available_tags: [] })

    const wrapper = mount(AdminSettingsView)
    await flushPromises()

    const newTagInput = wrapper.findAll('input')[2]!
    await newTagInput.setValue('Overnight')
    await newTagInput.trigger('keydown', { key: 'Enter' })

    expect(wrapper.text()).toContain('Overnight')
  })

  it('ignores blank or duplicate tag entries', async () => {
    api.get.mockResolvedValue({ site_name: 'X', site_tagline: 'Y', available_tags: ['Safety'] })

    const wrapper = mount(AdminSettingsView)
    await flushPromises()

    const newTagInput = wrapper.findAll('input')[2]!
    await newTagInput.setValue('   ')
    await wrapper.findAll('button').find((b) => b.text().includes('Add'))!.trigger('click')

    // Empty input must not add a blank chip.
    expect(wrapper.findAll('.bg-sky-50').length).toBe(1)

    await newTagInput.setValue('Safety')
    await wrapper.findAll('button').find((b) => b.text().includes('Add'))!.trigger('click')
    // Duplicate must not create a second chip.
    expect(wrapper.findAll('.bg-sky-50').length).toBe(1)
  })

  it('removes a tag when the X button on the chip is clicked', async () => {
    api.get.mockResolvedValue({ site_name: 'X', site_tagline: 'Y', available_tags: ['Safety', 'Event'] })

    const wrapper = mount(AdminSettingsView)
    await flushPromises()

    // The aria-label pattern `Remove ${tag}` lets us target the right X button.
    const removeSafety = wrapper
      .findAll('button')
      .find((b) => b.attributes('aria-label') === 'Remove Safety')
    expect(removeSafety).toBeDefined()
    await removeSafety!.trigger('click')

    expect(wrapper.text()).not.toContain('Safety')
    expect(wrapper.text()).toContain('Event')
  })

  it('persists updates and shows a success notification on save', async () => {
    api.get.mockResolvedValue({ site_name: 'Old', site_tagline: 'Old tagline', available_tags: ['A'] })
    api.update.mockResolvedValue({ site_name: 'New', site_tagline: 'New tagline', available_tags: ['A', 'B'] })

    const ui = useUiStore()
    const spy = vi.spyOn(ui, 'addNotification')

    const wrapper = mount(AdminSettingsView)
    await flushPromises()

    const inputs = wrapper.findAll('input')
    await inputs[0]!.setValue('New')
    await inputs[1]!.setValue('New tagline')

    const newTagInput = inputs[2]!
    await newTagInput.setValue('B')
    await wrapper.findAll('button').find((b) => b.text().includes('Add'))!.trigger('click')

    await wrapper.findAll('button').find((b) => b.text().includes('Save Settings'))!.trigger('click')
    await flushPromises()

    expect(api.update).toHaveBeenCalledWith({
      site_name: 'New',
      site_tagline: 'New tagline',
      available_tags: ['A', 'B'],
    })
    expect(spy).toHaveBeenCalledWith({ type: 'success', message: 'Settings saved' })
  })

  it('falls back to the default site name when the field is left blank on save', async () => {
    api.get.mockResolvedValue({ site_name: '', site_tagline: '', available_tags: [] })
    api.update.mockResolvedValue({ site_name: 'SmartPark', site_tagline: '', available_tags: [] })

    const wrapper = mount(AdminSettingsView)
    await flushPromises()

    await wrapper.findAll('button').find((b) => b.text().includes('Save Settings'))!.trigger('click')
    await flushPromises()

    expect(api.update).toHaveBeenCalledWith(
      expect.objectContaining({ site_name: 'SmartPark' }),
    )
  })

  it('shows an error notification when saving fails', async () => {
    api.get.mockResolvedValue({ site_name: 'X', site_tagline: 'Y', available_tags: [] })
    api.update.mockRejectedValue(new Error('server down'))

    const ui = useUiStore()
    const spy = vi.spyOn(ui, 'addNotification')

    const wrapper = mount(AdminSettingsView)
    await flushPromises()

    await wrapper.findAll('button').find((b) => b.text().includes('Save Settings'))!.trigger('click')
    await flushPromises()

    expect(spy).toHaveBeenCalledWith({ type: 'error', message: 'Failed to save settings' })
  })
})
