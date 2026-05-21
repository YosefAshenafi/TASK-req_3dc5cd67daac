import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import CreatePlaylistModal from '@/components/CreatePlaylistModal.vue'

const mockCreate = vi.fn()
vi.mock('@/stores/playlist', () => ({
  usePlaylistStore: () => ({ create: mockCreate }),
}))

describe('CreatePlaylistModal', () => {
  beforeEach(() => {
    mockCreate.mockResolvedValue({})
  })

  describe('rendering', () => {
    it('displays New Playlist heading', () => {
      const wrapper = mount(CreatePlaylistModal)
      expect(wrapper.text()).toContain('New Playlist')
    })

    it('renders the name input', () => {
      const wrapper = mount(CreatePlaylistModal)
      expect(wrapper.find('input#pl-name').exists()).toBe(true)
    })

    it('renders the description textarea', () => {
      const wrapper = mount(CreatePlaylistModal)
      expect(wrapper.find('textarea#pl-desc').exists()).toBe(true)
    })

    it('renders Cancel and Create buttons', () => {
      const wrapper = mount(CreatePlaylistModal)
      const buttonTexts = wrapper.findAll('button').map(b => b.text())
      expect(buttonTexts).toContain('Cancel')
      expect(buttonTexts.some(t => t.includes('Create'))).toBe(true)
    })
  })

  describe('close events', () => {
    it('emits close when X button is clicked', async () => {
      const wrapper = mount(CreatePlaylistModal)
      await wrapper.find('button[aria-label="Close"]').trigger('click')
      expect(wrapper.emitted('close')).toBeTruthy()
    })

    it('emits close when Cancel button is clicked', async () => {
      const wrapper = mount(CreatePlaylistModal)
      const cancelBtn = wrapper.findAll('button').find(b => b.text() === 'Cancel')
      await cancelBtn.trigger('click')
      expect(wrapper.emitted('close')).toBeTruthy()
    })
  })

  describe('validation', () => {
    it('shows name required error when submitted with empty name', async () => {
      const wrapper = mount(CreatePlaylistModal)
      await wrapper.find('form').trigger('submit')
      expect(wrapper.text()).toContain('Name is required')
    })

    it('does not call store.create when name is empty', async () => {
      const wrapper = mount(CreatePlaylistModal)
      await wrapper.find('form').trigger('submit')
      expect(mockCreate).not.toHaveBeenCalled()
    })

    it('shows name required error when name is only whitespace', async () => {
      const wrapper = mount(CreatePlaylistModal)
      await wrapper.find('input#pl-name').setValue('   ')
      await wrapper.find('form').trigger('submit')
      expect(wrapper.text()).toContain('Name is required')
    })
  })

  describe('successful submission', () => {
    it('calls store.create with the form name', async () => {
      const wrapper = mount(CreatePlaylistModal)
      await wrapper.find('input#pl-name').setValue('My Playlist')
      await wrapper.find('form').trigger('submit')
      await flushPromises()
      expect(mockCreate).toHaveBeenCalledWith(expect.objectContaining({ name: 'My Playlist' }))
    })

    it('passes description to store.create when provided', async () => {
      const wrapper = mount(CreatePlaylistModal)
      await wrapper.find('input#pl-name').setValue('Named')
      await wrapper.find('textarea#pl-desc').setValue('A description')
      await wrapper.find('form').trigger('submit')
      await flushPromises()
      expect(mockCreate).toHaveBeenCalledWith(expect.objectContaining({ description: 'A description' }))
    })

    it('emits created event on success', async () => {
      const wrapper = mount(CreatePlaylistModal)
      await wrapper.find('input#pl-name').setValue('Test')
      await wrapper.find('form').trigger('submit')
      await flushPromises()
      expect(wrapper.emitted('created')).toBeTruthy()
    })

    it('emits close event on success', async () => {
      const wrapper = mount(CreatePlaylistModal)
      await wrapper.find('input#pl-name').setValue('Test')
      await wrapper.find('form').trigger('submit')
      await flushPromises()
      expect(wrapper.emitted('close')).toBeTruthy()
    })
  })

  describe('error handling', () => {
    it('shows api error message when store.create fails', async () => {
      mockCreate.mockRejectedValue({ response: { data: { message: 'Name already taken' } } })
      const wrapper = mount(CreatePlaylistModal)
      await wrapper.find('input#pl-name').setValue('Dupe')
      await wrapper.find('form').trigger('submit')
      await flushPromises()
      expect(wrapper.text()).toContain('Name already taken')
    })

    it('shows fallback error message when no message in response', async () => {
      mockCreate.mockRejectedValue(new Error('network'))
      const wrapper = mount(CreatePlaylistModal)
      await wrapper.find('input#pl-name').setValue('Test')
      await wrapper.find('form').trigger('submit')
      await flushPromises()
      expect(wrapper.text()).toContain('Failed to create playlist')
    })

    it('does not emit created on failure', async () => {
      mockCreate.mockRejectedValue(new Error('fail'))
      const wrapper = mount(CreatePlaylistModal)
      await wrapper.find('input#pl-name').setValue('Test')
      await wrapper.find('form').trigger('submit')
      await flushPromises()
      expect(wrapper.emitted('created')).toBeFalsy()
    })
  })

  describe('loading state', () => {
    it('disables submit button while submitting', async () => {
      let resolve
      mockCreate.mockReturnValue(new Promise(r => { resolve = r }))
      const wrapper = mount(CreatePlaylistModal)
      await wrapper.find('input#pl-name').setValue('Test')
      wrapper.find('form').trigger('submit')
      await wrapper.vm.$nextTick()
      expect(wrapper.find('button[type="submit"]').attributes('disabled')).toBeDefined()
      resolve({})
    })

    it('shows Creating... text while submitting', async () => {
      let resolve
      mockCreate.mockReturnValue(new Promise(r => { resolve = r }))
      const wrapper = mount(CreatePlaylistModal)
      await wrapper.find('input#pl-name').setValue('Test')
      wrapper.find('form').trigger('submit')
      await wrapper.vm.$nextTick()
      expect(wrapper.find('button[type="submit"]').text()).toContain('Creating...')
      resolve({})
    })
  })
})
