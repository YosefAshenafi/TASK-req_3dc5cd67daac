import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import AdminMonitoringView from '@/views/admin/AdminMonitoringView.vue'

const mockData = {
  health: { database: 'healthy', queue: 'healthy' },
  queue: { pending_jobs: 3, failed_jobs: 0 },
  recommendations: {
    engine_status: 'active',
    window_seconds: 300,
    p95_latency_ms: 120.5,
    hit_rate: 0.32,
    p95_threshold_ms: 800.0,
    hit_rate_threshold: 0.10,
  },
  api_errors: { last_hour: 2 },
  device_ingestion: { received_count: 10, late_count: 1, buffered_count: 0, duplicate_count: 0 },
  timestamp: '2026-05-21T10:00:00+00:00',
}

vi.mock('@/api/axios', () => ({
  default: {
    get: vi.fn().mockResolvedValue({ data: { data: mockData } }),
  },
}))

describe('AdminMonitoringView', () => {
  it('renders database health status', async () => {
    const wrapper = mount(AdminMonitoringView)
    await flushPromises()
    expect(wrapper.text()).toContain('healthy')
  })

  it('renders queue health status', async () => {
    const wrapper = mount(AdminMonitoringView)
    await flushPromises()
    expect(wrapper.text()).toContain('healthy')
  })

  it('renders recommendation engine status', async () => {
    const wrapper = mount(AdminMonitoringView)
    await flushPromises()
    expect(wrapper.text()).toContain('active')
  })

  it('renders p95 latency value', async () => {
    const wrapper = mount(AdminMonitoringView)
    await flushPromises()
    expect(wrapper.text()).toContain('120.5ms')
  })

  it('renders pending jobs count', async () => {
    const wrapper = mount(AdminMonitoringView)
    await flushPromises()
    expect(wrapper.text()).toContain('3')
  })

  it('renders failed jobs count', async () => {
    const wrapper = mount(AdminMonitoringView)
    await flushPromises()
    expect(wrapper.text()).toContain('0')
  })

  it('shows loading state while fetching', () => {
    const wrapper = mount(AdminMonitoringView)
    expect(wrapper.find('.skeleton').exists()).toBe(true)
  })

  it('hides skeleton after data loads', async () => {
    const wrapper = mount(AdminMonitoringView)
    await flushPromises()
    expect(wrapper.find('.skeleton').exists()).toBe(false)
  })

  it('refresh button is visible', async () => {
    const wrapper = mount(AdminMonitoringView)
    await flushPromises()
    const btn = wrapper.find('button')
    expect(btn.text()).toContain('Refresh')
  })
})
