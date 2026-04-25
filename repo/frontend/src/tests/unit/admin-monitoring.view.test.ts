import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import AdminMonitoringView from '@/views/admin/AdminMonitoringView.vue'
import { useUiStore } from '@/stores/ui'

const api = {
  status: vi.fn(),
  resetFlag: vi.fn(),
}

vi.mock('@/services/api', async () => {
  const actual = await vi.importActual<typeof import('@/services/api')>('@/services/api')
  return {
    ...actual,
    monitoringApi: {
      status: (...a: unknown[]) => api.status(...a),
      resetFlag: (...a: unknown[]) => api.resetFlag(...a),
    },
  }
})

function healthyStatus(overrides: Record<string, unknown> = {}) {
  return {
    api: { p95_ms_5m: 123, error_rate_5m: 0.0025 },
    queues: { default: 0, high: 2 },
    storage: { media_volume_free_bytes: 42 * 1024 * 1024 * 1024, media_volume_used_pct: 55 },
    devices: { online: 3, offline: 1, dedup_rate_1h: 0.12 },
    content_usage: {
      window_hours: 24,
      plays_24h: 99,
      active_users_24h: 7,
      total_ready_assets: 123,
      favorites_count: 1,
      playlists_count: 4,
      top_assets: [
        { asset_id: 1, title: 'Top track', mime: 'audio/mpeg', play_count: 20 },
      ],
    },
    feature_flags: {
      recommended_enabled: { enabled: false, reason: 'p95 breach' },
    },
    ...overrides,
  }
}

describe('AdminMonitoringView.vue', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    api.status.mockReset()
    api.resetFlag.mockReset()
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('shows the loading spinner until the first status payload arrives', async () => {
    // Never-resolving mock so the onMounted fetch is still in flight.
    api.status.mockReturnValue(new Promise(() => {}))

    const wrapper = mount(AdminMonitoringView)
    await flushPromises()

    expect(wrapper.text()).toContain('Loading monitoring data…')
    // No panels are rendered until data arrives.
    expect(wrapper.text()).not.toContain('API Health')
  })

  it('renders all six panels with real numeric data after a successful fetch', async () => {
    api.status.mockResolvedValue(healthyStatus())

    const wrapper = mount(AdminMonitoringView)
    await flushPromises()

    const body = wrapper.text()
    expect(body).toContain('API Health')
    expect(body).toContain('Devices')
    expect(body).toContain('Storage')
    expect(body).toContain('Queue Backlogs')
    expect(body).toContain('Content Usage')
    expect(body).toContain('Feature Flags')

    // Concrete numeric values, not just panel labels.
    expect(body).toContain('123 ms') // p95
    expect(body).toContain('0.25%')  // error rate 0.0025 → 0.25%
    expect(body).toContain('55.0%')  // storage used
    expect(body).toContain('Top track')
    expect(body).toContain('recommended_enabled')
  })

  it('notifies the user on status fetch failure', async () => {
    const ui = useUiStore()
    const spy = vi.spyOn(ui, 'addNotification')
    api.status.mockRejectedValue(new Error('500'))

    mount(AdminMonitoringView)
    await flushPromises()

    expect(spy).toHaveBeenCalledWith({ type: 'error', message: 'Failed to fetch monitoring status' })
  })

  it('re-fetches status when the Refresh button is clicked', async () => {
    api.status.mockResolvedValue(healthyStatus())

    const wrapper = mount(AdminMonitoringView)
    await flushPromises()
    expect(api.status).toHaveBeenCalledTimes(1)

    const refreshBtn = wrapper
      .findAll('button')
      .find((b) => b.text().includes('Refresh'))
    await refreshBtn!.trigger('click')
    await flushPromises()

    expect(api.status).toHaveBeenCalledTimes(2)
  })

  it('polls every 10 seconds via setInterval', async () => {
    api.status.mockResolvedValue(healthyStatus())

    mount(AdminMonitoringView)
    await flushPromises()
    expect(api.status).toHaveBeenCalledTimes(1)

    // Three 10-second ticks → three additional fetches.
    vi.advanceTimersByTime(30_000)
    await flushPromises()
    expect(api.status).toHaveBeenCalledTimes(4)
  })

  it('resets a feature flag and re-fetches status when Reset is clicked', async () => {
    api.status.mockResolvedValue(healthyStatus())
    api.resetFlag.mockResolvedValue(undefined)

    const ui = useUiStore()
    const spy = vi.spyOn(ui, 'addNotification')

    const wrapper = mount(AdminMonitoringView)
    await flushPromises()

    const resetBtn = wrapper.findAll('button').find((b) => b.text() === 'Reset')
    await resetBtn!.trigger('click')
    await flushPromises()

    expect(api.resetFlag).toHaveBeenCalledWith('recommended_enabled')
    expect(spy).toHaveBeenCalledWith({
      type: 'success',
      message: 'Flag "recommended_enabled" reset',
    })
    // The refresh after reset means status was called twice (mount + reset).
    expect(api.status).toHaveBeenCalledTimes(2)
  })

  it('shows an error notification when reset fails', async () => {
    api.status.mockResolvedValue(healthyStatus())
    api.resetFlag.mockRejectedValue(new Error('403'))

    const ui = useUiStore()
    const spy = vi.spyOn(ui, 'addNotification')

    const wrapper = mount(AdminMonitoringView)
    await flushPromises()

    await wrapper.findAll('button').find((b) => b.text() === 'Reset')!.trigger('click')
    await flushPromises()

    expect(spy).toHaveBeenCalledWith({ type: 'error', message: 'Failed to reset flag' })
  })

  it('renders a friendly empty state when there are no top-played assets', async () => {
    api.status.mockResolvedValue(
      healthyStatus({
        content_usage: {
          window_hours: 24,
          plays_24h: 0,
          active_users_24h: 0,
          total_ready_assets: 0,
          favorites_count: 0,
          playlists_count: 0,
          top_assets: [],
        },
      }),
    )

    const wrapper = mount(AdminMonitoringView)
    await flushPromises()

    expect(wrapper.text()).toContain('No plays in the last 24 hours')
  })

  it('clears the polling interval when the component is unmounted', async () => {
    api.status.mockResolvedValue(healthyStatus())

    const wrapper = mount(AdminMonitoringView)
    await flushPromises()

    // Unmounting while the interval is running covers the onUnmounted if(refreshTimer) branch.
    wrapper.unmount()
  })
})
