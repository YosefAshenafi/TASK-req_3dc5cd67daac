import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import AdminUsersView from '@/views/admin/AdminUsersView.vue'

const mockUsers = [
  { id: 1, name: 'Alice Admin', email: 'alice@example.com', role: 'admin', account_status: 'active' },
  { id: 2, name: 'Bob Frozen', email: 'bob@example.com', role: 'user', account_status: 'frozen' },
  { id: 3, name: 'Carol Banned', email: 'carol@example.com', role: 'technician', account_status: 'blacklisted' },
]

const apiMock = vi.hoisted(() => ({
  get: vi.fn(),
  patch: vi.fn(),
  delete: vi.fn(),
}))

vi.mock('@/api/axios', () => ({ default: apiMock }))

describe('AdminUsersView', () => {
  beforeEach(() => {
    apiMock.get.mockClear()
    apiMock.patch.mockClear()
    apiMock.delete.mockClear()
    apiMock.get.mockResolvedValue({ data: { data: mockUsers } })
    apiMock.patch.mockResolvedValue({})
    apiMock.delete.mockResolvedValue({})
  })

  describe('heading', () => {
    it('renders the User Management heading', () => {
      const wrapper = mount(AdminUsersView)
      expect(wrapper.text()).toContain('User Management')
    })
  })

  describe('loading state', () => {
    it('shows skeleton loaders while loading', () => {
      apiMock.get.mockReturnValue(new Promise(() => {}))
      const wrapper = mount(AdminUsersView)
      expect(wrapper.find('.skeleton').exists()).toBe(true)
    })

    it('hides skeleton loaders after data loads', async () => {
      const wrapper = mount(AdminUsersView)
      await flushPromises()
      expect(wrapper.find('.skeleton').exists()).toBe(false)
    })
  })

  describe('user table', () => {
    it('renders all user names', async () => {
      const wrapper = mount(AdminUsersView)
      await flushPromises()
      expect(wrapper.text()).toContain('Alice Admin')
      expect(wrapper.text()).toContain('Bob Frozen')
      expect(wrapper.text()).toContain('Carol Banned')
    })

    it('renders user emails', async () => {
      const wrapper = mount(AdminUsersView)
      await flushPromises()
      expect(wrapper.text()).toContain('alice@example.com')
    })
  })

  describe('role badge classes', () => {
    it('admin role returns purple badge class', async () => {
      const wrapper = mount(AdminUsersView)
      await flushPromises()
      expect(wrapper.vm.roleBadge('admin')['bg-purple-100 text-purple-700']).toBe(true)
    })

    it('technician role returns green badge class', async () => {
      const wrapper = mount(AdminUsersView)
      await flushPromises()
      expect(wrapper.vm.roleBadge('technician')['bg-green-100 text-green-700']).toBe(true)
    })

    it('user role returns blue badge class', async () => {
      const wrapper = mount(AdminUsersView)
      await flushPromises()
      expect(wrapper.vm.roleBadge('user')['bg-blue-100 text-blue-700']).toBe(true)
    })
  })

  describe('status badge classes', () => {
    it('active status returns green badge class', async () => {
      const wrapper = mount(AdminUsersView)
      await flushPromises()
      expect(wrapper.vm.statusBadge('active')['bg-green-100 text-green-700']).toBe(true)
    })

    it('frozen status returns yellow badge class', async () => {
      const wrapper = mount(AdminUsersView)
      await flushPromises()
      expect(wrapper.vm.statusBadge('frozen')['bg-yellow-100 text-yellow-700']).toBe(true)
    })

    it('blacklisted status returns red badge class', async () => {
      const wrapper = mount(AdminUsersView)
      await flushPromises()
      expect(wrapper.vm.statusBadge('blacklisted')['bg-red-100 text-red-700']).toBe(true)
    })
  })

  describe('conditional action buttons', () => {
    it('does not show Freeze button for frozen users', async () => {
      const wrapper = mount(AdminUsersView)
      await flushPromises()
      const rows = wrapper.findAll('tbody tr')
      const bobRow = rows[1]
      const freezeBtns = bobRow.findAll('button').filter(b => b.text() === 'Freeze')
      expect(freezeBtns).toHaveLength(0)
    })

    it('shows Freeze button for non-frozen users', async () => {
      const wrapper = mount(AdminUsersView)
      await flushPromises()
      const rows = wrapper.findAll('tbody tr')
      const aliceRow = rows[0]
      const freezeBtns = aliceRow.findAll('button').filter(b => b.text() === 'Freeze')
      expect(freezeBtns.length).toBeGreaterThan(0)
    })

    it('does not show Blacklist button for blacklisted users', async () => {
      const wrapper = mount(AdminUsersView)
      await flushPromises()
      const rows = wrapper.findAll('tbody tr')
      const carolRow = rows[2]
      const blacklistBtns = carolRow.findAll('button').filter(b => b.text() === 'Blacklist')
      expect(blacklistBtns).toHaveLength(0)
    })

    it('shows Blacklist button for non-blacklisted users', async () => {
      const wrapper = mount(AdminUsersView)
      await flushPromises()
      const rows = wrapper.findAll('tbody tr')
      const aliceRow = rows[0]
      const blacklistBtns = aliceRow.findAll('button').filter(b => b.text() === 'Blacklist')
      expect(blacklistBtns.length).toBeGreaterThan(0)
    })
  })

  describe('freeze modal', () => {
    it('opens modal with user name when Freeze is clicked', async () => {
      const wrapper = mount(AdminUsersView)
      await flushPromises()
      const freezeBtn = wrapper.findAll('button').find(b => b.text() === 'Freeze')
      await freezeBtn.trigger('click')
      expect(wrapper.text()).toContain('Freeze Alice Admin')
    })

    it('calls PATCH with correct id and default duration_hours on confirm', async () => {
      const wrapper = mount(AdminUsersView)
      await flushPromises()
      const freezeBtn = wrapper.findAll('button').find(b => b.text() === 'Freeze')
      await freezeBtn.trigger('click')
      const confirmBtn = wrapper.find('button.btn-primary')
      await confirmBtn.trigger('click')
      await flushPromises()
      expect(apiMock.patch).toHaveBeenCalledWith('/admin/users/1/freeze', { duration_hours: 72 })
    })

    it('modal closes after confirming freeze', async () => {
      const wrapper = mount(AdminUsersView)
      await flushPromises()
      const freezeBtn = wrapper.findAll('button').find(b => b.text() === 'Freeze')
      await freezeBtn.trigger('click')
      expect(wrapper.find('input[type="number"]').exists()).toBe(true)
      await wrapper.find('button.btn-primary').trigger('click')
      await flushPromises()
      expect(wrapper.find('input[type="number"]').exists()).toBe(false)
    })

    it('cancel button closes the modal without calling PATCH', async () => {
      const wrapper = mount(AdminUsersView)
      await flushPromises()
      const freezeBtn = wrapper.findAll('button').find(b => b.text() === 'Freeze')
      await freezeBtn.trigger('click')
      const cancelBtn = wrapper.find('button.btn-secondary')
      await cancelBtn.trigger('click')
      expect(wrapper.find('input[type="number"]').exists()).toBe(false)
      expect(apiMock.patch).not.toHaveBeenCalled()
    })
  })
})
