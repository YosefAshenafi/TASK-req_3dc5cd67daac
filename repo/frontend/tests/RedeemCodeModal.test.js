import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import RedeemCodeModal from '@/components/RedeemCodeModal.vue'

const mockRedeem = vi.fn()
vi.mock('@/stores/playlist', () => ({
  usePlaylistStore: () => ({ redeem: mockRedeem }),
}))

describe('RedeemCodeModal', () => {
  beforeEach(() => {
    mockRedeem.mockResolvedValue({ name: 'Shared Playlist', items: [1, 2, 3] })
  })

  describe('rendering', () => {
    it('displays Redeem Share Code heading', () => {
      const wrapper = mount(RedeemCodeModal)
      expect(wrapper.text()).toContain('Redeem Share Code')
    })

    it('renders the share code input', () => {
      const wrapper = mount(RedeemCodeModal)
      expect(wrapper.find('input#share-code').exists()).toBe(true)
    })

    it('renders Cancel and Redeem buttons', () => {
      const wrapper = mount(RedeemCodeModal)
      const texts = wrapper.findAll('button').map(b => b.text())
      expect(texts).toContain('Cancel')
      expect(texts.some(t => t.includes('Redeem'))).toBe(true)
    })
  })

  describe('close events', () => {
    it('emits close when X button is clicked', async () => {
      const wrapper = mount(RedeemCodeModal)
      await wrapper.find('button[aria-label="Close"]').trigger('click')
      expect(wrapper.emitted('close')).toBeTruthy()
    })

    it('emits close when Cancel button is clicked', async () => {
      const wrapper = mount(RedeemCodeModal)
      const cancelBtn = wrapper.findAll('button').find(b => b.text() === 'Cancel')
      await cancelBtn.trigger('click')
      expect(wrapper.emitted('close')).toBeTruthy()
    })
  })

  describe('submit button state', () => {
    it('Redeem button is disabled when code is empty', () => {
      const wrapper = mount(RedeemCodeModal)
      expect(wrapper.find('button[type="submit"]').attributes('disabled')).toBeDefined()
    })

    it('Redeem button is enabled once code is entered', async () => {
      const wrapper = mount(RedeemCodeModal)
      await wrapper.find('input#share-code').setValue('ABCD1234')
      expect(wrapper.find('button[type="submit"]').attributes('disabled')).toBeUndefined()
    })
  })

  describe('successful redemption', () => {
    it('calls store.redeem with uppercased code', async () => {
      const wrapper = mount(RedeemCodeModal)
      await wrapper.find('input#share-code').setValue('abcd1234')
      await wrapper.find('form').trigger('submit')
      await flushPromises()
      expect(mockRedeem).toHaveBeenCalledWith('ABCD1234')
    })

    it('shows playlist name after success', async () => {
      const wrapper = mount(RedeemCodeModal)
      await wrapper.find('input#share-code').setValue('TESTCODE')
      await wrapper.find('form').trigger('submit')
      await flushPromises()
      expect(wrapper.text()).toContain('Shared Playlist')
    })

    it('shows item count after success', async () => {
      const wrapper = mount(RedeemCodeModal)
      await wrapper.find('input#share-code').setValue('TESTCODE')
      await wrapper.find('form').trigger('submit')
      await flushPromises()
      expect(wrapper.text()).toContain('3 items')
    })

    it('shows 0 items when playlist has no items', async () => {
      mockRedeem.mockResolvedValue({ name: 'Empty Playlist', items: [] })
      const wrapper = mount(RedeemCodeModal)
      await wrapper.find('input#share-code').setValue('EMPTY000')
      await wrapper.find('form').trigger('submit')
      await flushPromises()
      expect(wrapper.text()).toContain('0 items')
    })
  })

  describe('error handling', () => {
    it('shows api error message on failure', async () => {
      mockRedeem.mockRejectedValue({ response: { data: { message: 'Code expired' } } })
      const wrapper = mount(RedeemCodeModal)
      await wrapper.find('input#share-code').setValue('EXPCODE1')
      await wrapper.find('form').trigger('submit')
      await flushPromises()
      expect(wrapper.text()).toContain('Code expired')
    })

    it('shows fallback error message when no message in response', async () => {
      mockRedeem.mockRejectedValue(new Error('network'))
      const wrapper = mount(RedeemCodeModal)
      await wrapper.find('input#share-code').setValue('BADCODE1')
      await wrapper.find('form').trigger('submit')
      await flushPromises()
      expect(wrapper.text()).toContain('Invalid or expired share code')
    })
  })

  describe('loading state', () => {
    it('disables Redeem button during submission', async () => {
      let resolve
      mockRedeem.mockReturnValue(new Promise(r => { resolve = r }))
      const wrapper = mount(RedeemCodeModal)
      await wrapper.find('input#share-code').setValue('ABCD1234')
      wrapper.find('form').trigger('submit')
      await wrapper.vm.$nextTick()
      expect(wrapper.find('button[type="submit"]').attributes('disabled')).toBeDefined()
      resolve({ name: 'x', items: [] })
    })

    it('shows Redeeming... text while submitting', async () => {
      let resolve
      mockRedeem.mockReturnValue(new Promise(r => { resolve = r }))
      const wrapper = mount(RedeemCodeModal)
      await wrapper.find('input#share-code').setValue('ABCD1234')
      wrapper.find('form').trigger('submit')
      await wrapper.vm.$nextTick()
      expect(wrapper.find('button[type="submit"]').text()).toContain('Redeeming...')
      resolve({ name: 'x', items: [] })
    })
  })
})
