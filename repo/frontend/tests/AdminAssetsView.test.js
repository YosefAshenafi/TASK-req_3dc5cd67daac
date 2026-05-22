import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { nextTick } from 'vue'
import AdminAssetsView from '@/views/admin/AdminAssetsView.vue'

const mockAssets = [
  { id: 10, title: 'Pending Audio', mime_type: 'audio/mpeg', status: 'pending', created_at: '2026-05-01T00:00:00Z' },
  { id: 11, title: 'Approved Video', mime_type: 'video/mp4', status: 'approved', created_at: '2026-05-02T00:00:00Z' },
  { id: 12, title: 'Rejected PDF', mime_type: 'application/pdf', status: 'rejected', created_at: '2026-05-03T00:00:00Z' },
]

const apiMock = vi.hoisted(() => ({
  get: vi.fn(),
  patch: vi.fn(),
}))

vi.mock('@/api/axios', () => ({ default: apiMock }))

describe('AdminAssetsView', () => {
  beforeEach(() => {
    apiMock.get.mockClear()
    apiMock.patch.mockClear()
    apiMock.get.mockResolvedValue({ data: { data: mockAssets } })
    apiMock.patch.mockResolvedValue({})
  })

  describe('heading', () => {
    it('renders Asset Review heading', () => {
      const wrapper = mount(AdminAssetsView)
      expect(wrapper.text()).toContain('Asset Review')
    })
  })

  describe('loading state', () => {
    it('shows skeleton loaders while loading', async () => {
      apiMock.get.mockReturnValue(new Promise(() => {}))
      const wrapper = mount(AdminAssetsView)
      await nextTick()
      expect(wrapper.find('.skeleton').exists()).toBe(true)
    })

    it('hides skeleton after data loads', async () => {
      const wrapper = mount(AdminAssetsView)
      await flushPromises()
      expect(wrapper.find('.skeleton').exists()).toBe(false)
    })
  })

  describe('empty state', () => {
    it('shows No assets found when list is empty', async () => {
      apiMock.get.mockResolvedValue({ data: { data: [] } })
      const wrapper = mount(AdminAssetsView)
      await flushPromises()
      expect(wrapper.text()).toContain('No assets found')
    })

    it('does not show table when list is empty', async () => {
      apiMock.get.mockResolvedValue({ data: { data: [] } })
      const wrapper = mount(AdminAssetsView)
      await flushPromises()
      expect(wrapper.find('table').exists()).toBe(false)
    })
  })

  describe('asset table', () => {
    it('renders all asset titles', async () => {
      const wrapper = mount(AdminAssetsView)
      await flushPromises()
      expect(wrapper.text()).toContain('Pending Audio')
      expect(wrapper.text()).toContain('Approved Video')
      expect(wrapper.text()).toContain('Rejected PDF')
    })

    it('renders asset mime types', async () => {
      const wrapper = mount(AdminAssetsView)
      await flushPromises()
      expect(wrapper.text()).toContain('audio/mpeg')
      expect(wrapper.text()).toContain('video/mp4')
    })
  })

  describe('status badge classes', () => {
    it('pending status returns yellow badge class', async () => {
      const wrapper = mount(AdminAssetsView)
      await flushPromises()
      expect(wrapper.vm.statusBadge('pending')['bg-yellow-100 text-yellow-700']).toBe(true)
    })

    it('approved status returns green badge class', async () => {
      const wrapper = mount(AdminAssetsView)
      await flushPromises()
      expect(wrapper.vm.statusBadge('approved')['bg-green-100 text-green-700']).toBe(true)
    })

    it('rejected status returns red badge class', async () => {
      const wrapper = mount(AdminAssetsView)
      await flushPromises()
      expect(wrapper.vm.statusBadge('rejected')['bg-red-100 text-red-700']).toBe(true)
    })
  })

  describe('conditional action buttons', () => {
    it('does not show Approve button for already-approved assets', async () => {
      const wrapper = mount(AdminAssetsView)
      await flushPromises()
      const rows = wrapper.findAll('tbody tr')
      const approvedRow = rows[1]
      const approveBtns = approvedRow.findAll('button').filter(b => b.text() === 'Approve')
      expect(approveBtns).toHaveLength(0)
    })

    it('shows Approve button for pending assets', async () => {
      const wrapper = mount(AdminAssetsView)
      await flushPromises()
      const rows = wrapper.findAll('tbody tr')
      const pendingRow = rows[0]
      const approveBtns = pendingRow.findAll('button').filter(b => b.text() === 'Approve')
      expect(approveBtns.length).toBeGreaterThan(0)
    })

    it('does not show Reject button for already-rejected assets', async () => {
      const wrapper = mount(AdminAssetsView)
      await flushPromises()
      const rows = wrapper.findAll('tbody tr')
      const rejectedRow = rows[2]
      const rejectBtns = rejectedRow.findAll('button').filter(b => b.text() === 'Reject')
      expect(rejectBtns).toHaveLength(0)
    })

    it('shows Reject button for pending assets', async () => {
      const wrapper = mount(AdminAssetsView)
      await flushPromises()
      const rows = wrapper.findAll('tbody tr')
      const pendingRow = rows[0]
      const rejectBtns = pendingRow.findAll('button').filter(b => b.text() === 'Reject')
      expect(rejectBtns.length).toBeGreaterThan(0)
    })

    it('shows both Approve and Reject buttons for pending assets', async () => {
      const wrapper = mount(AdminAssetsView)
      await flushPromises()
      const rows = wrapper.findAll('tbody tr')
      const pendingRow = rows[0]
      const buttons = pendingRow.findAll('button')
      const texts = buttons.map(b => b.text())
      expect(texts).toContain('Approve')
      expect(texts).toContain('Reject')
    })
  })

  describe('approve action', () => {
    it('calls PATCH /admin/assets/:id/approve when Approve is clicked', async () => {
      const wrapper = mount(AdminAssetsView)
      await flushPromises()
      const pendingRow = wrapper.findAll('tbody tr')[0]
      const approveBtn = pendingRow.findAll('button').find(b => b.text() === 'Approve')
      await approveBtn.trigger('click')
      await flushPromises()
      expect(apiMock.patch).toHaveBeenCalledWith('/admin/assets/10/approve')
    })

    it('reloads list after approve', async () => {
      apiMock.get
        .mockResolvedValueOnce({ data: { data: mockAssets } })
        .mockResolvedValueOnce({ data: { data: [] } })
      const wrapper = mount(AdminAssetsView)
      await flushPromises()
      const approveBtn = wrapper.findAll('tbody tr')[0]
        .findAll('button').find(b => b.text() === 'Approve')
      await approveBtn.trigger('click')
      await flushPromises()
      expect(apiMock.get).toHaveBeenCalledTimes(2)
    })
  })

  describe('reject action', () => {
    it('calls PATCH /admin/assets/:id/reject when Reject is clicked', async () => {
      const wrapper = mount(AdminAssetsView)
      await flushPromises()
      const pendingRow = wrapper.findAll('tbody tr')[0]
      const rejectBtn = pendingRow.findAll('button').find(b => b.text() === 'Reject')
      await rejectBtn.trigger('click')
      await flushPromises()
      expect(apiMock.patch).toHaveBeenCalledWith('/admin/assets/10/reject')
    })

    it('reloads list after reject', async () => {
      apiMock.get
        .mockResolvedValueOnce({ data: { data: mockAssets } })
        .mockResolvedValueOnce({ data: { data: [] } })
      const wrapper = mount(AdminAssetsView)
      await flushPromises()
      const rejectBtn = wrapper.findAll('tbody tr')[0]
        .findAll('button').find(b => b.text() === 'Reject')
      await rejectBtn.trigger('click')
      await flushPromises()
      expect(apiMock.get).toHaveBeenCalledTimes(2)
    })
  })

  describe('status filter', () => {
    it('defaults statusFilter to pending on mount', async () => {
      const wrapper = mount(AdminAssetsView)
      await flushPromises()
      expect(apiMock.get).toHaveBeenCalledWith('/admin/assets?status=pending')
    })

    it('select element has pending as default selected value', async () => {
      const wrapper = mount(AdminAssetsView)
      await flushPromises()
      const select = wrapper.find('select')
      expect(select.element.value).toBe('pending')
    })
  })
})
