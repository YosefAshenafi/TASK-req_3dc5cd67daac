import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import AdminDashboardView from '@/views/admin/AdminDashboardView.vue'

const mockStats = {
  users: { total: 42, active: 38, frozen: 3, blacklisted: 1 },
  assets: { total: 120, pending: 5, approved: 110, rejected: 5 },
  plays: { total: 5000, last_24h: 200, last_7d: 1400 },
  devices: { active_last_24h: 12 },
}

vi.mock('@/components/StatCard.vue', () => ({
  default: {
    props: ['label', 'value', 'color'],
    template: '<div data-testid="stat-card"><span class="label">{{ label }}</span><span class="value">{{ value }}</span></div>',
  },
}))

const apiMock = { get: vi.fn() }
vi.mock('@/api/axios', () => ({ default: apiMock }))

describe('AdminDashboardView', () => {
  beforeEach(() => {
    apiMock.get.mockResolvedValue({ data: { data: { stats: mockStats } } })
  })

  describe('heading', () => {
    it('renders Dashboard heading', () => {
      const wrapper = mount(AdminDashboardView)
      expect(wrapper.text()).toContain('Dashboard')
    })
  })

  describe('loading state', () => {
    it('shows skeleton loaders while loading', () => {
      apiMock.get.mockReturnValue(new Promise(() => {}))
      const wrapper = mount(AdminDashboardView)
      expect(wrapper.find('.skeleton').exists()).toBe(true)
    })

    it('hides skeleton after data loads', async () => {
      const wrapper = mount(AdminDashboardView)
      await flushPromises()
      expect(wrapper.find('.skeleton').exists()).toBe(false)
    })
  })

  describe('stat cards rendered', () => {
    it('renders 12 stat cards after data loads', async () => {
      const wrapper = mount(AdminDashboardView)
      await flushPromises()
      expect(wrapper.findAll('[data-testid="stat-card"]')).toHaveLength(12)
    })

    it('renders Total Users label', async () => {
      const wrapper = mount(AdminDashboardView)
      await flushPromises()
      expect(wrapper.text()).toContain('Total Users')
    })

    it('renders Active Users label', async () => {
      const wrapper = mount(AdminDashboardView)
      await flushPromises()
      expect(wrapper.text()).toContain('Active Users')
    })

    it('renders Frozen label', async () => {
      const wrapper = mount(AdminDashboardView)
      await flushPromises()
      expect(wrapper.text()).toContain('Frozen')
    })

    it('renders Blacklisted label', async () => {
      const wrapper = mount(AdminDashboardView)
      await flushPromises()
      expect(wrapper.text()).toContain('Blacklisted')
    })

    it('renders Total Assets label', async () => {
      const wrapper = mount(AdminDashboardView)
      await flushPromises()
      expect(wrapper.text()).toContain('Total Assets')
    })

    it('renders Pending Review label', async () => {
      const wrapper = mount(AdminDashboardView)
      await flushPromises()
      expect(wrapper.text()).toContain('Pending Review')
    })

    it('renders Total Plays label', async () => {
      const wrapper = mount(AdminDashboardView)
      await flushPromises()
      expect(wrapper.text()).toContain('Total Plays')
    })

    it('renders Active Devices label', async () => {
      const wrapper = mount(AdminDashboardView)
      await flushPromises()
      expect(wrapper.text()).toContain('Active Devices')
    })
  })

  describe('stat values', () => {
    it('renders total users count', async () => {
      const wrapper = mount(AdminDashboardView)
      await flushPromises()
      const cards = wrapper.findAll('[data-testid="stat-card"]')
      expect(cards[0].text()).toContain('42')
    })

    it('renders active users count', async () => {
      const wrapper = mount(AdminDashboardView)
      await flushPromises()
      const cards = wrapper.findAll('[data-testid="stat-card"]')
      expect(cards[1].text()).toContain('38')
    })

    it('renders pending assets count', async () => {
      const wrapper = mount(AdminDashboardView)
      await flushPromises()
      const cards = wrapper.findAll('[data-testid="stat-card"]')
      expect(cards[5].text()).toContain('5')
    })

    it('renders active devices count', async () => {
      const wrapper = mount(AdminDashboardView)
      await flushPromises()
      const cards = wrapper.findAll('[data-testid="stat-card"]')
      expect(cards[11].text()).toContain('12')
    })
  })

  describe('API call', () => {
    it('calls GET /admin/dashboard on mount', async () => {
      const wrapper = mount(AdminDashboardView)
      await flushPromises()
      expect(apiMock.get).toHaveBeenCalledWith('/admin/dashboard')
    })
  })
})
