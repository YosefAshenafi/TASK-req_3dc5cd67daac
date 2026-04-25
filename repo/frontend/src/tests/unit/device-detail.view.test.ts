import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import DeviceDetailView from '@/views/devices/DeviceDetailView.vue'
import { useUiStore } from '@/stores/ui'

const api = {
  get: vi.fn(),
  events: vi.fn(),
  initiateReplay: vi.fn(),
  replayAudits: vi.fn(),
}

vi.mock('@/services/api', () => ({
  devicesApi: {
    get: (...a: unknown[]) => api.get(...a),
    events: (...a: unknown[]) => api.events(...a),
    initiateReplay: (...a: unknown[]) => api.initiateReplay(...a),
    replayAudits: (...a: unknown[]) => api.replayAudits(...a),
  },
}))

vi.mock('vue-router', () => ({
  useRoute: () => ({ params: { id: 'gate-9' } }),
}))

function event(overrides: Record<string, unknown> = {}) {
  return {
    id: 1,
    device_id: 'gate-9',
    event_type: 'gate_open',
    sequence_no: 1,
    idempotency_key: 'key-1',
    status: 'accepted',
    occurred_at: new Date('2026-04-01T10:00:00Z').toISOString(),
    received_at: new Date('2026-04-01T10:00:01Z').toISOString(),
    payload_json: null,
    buffered_by_gateway: false,
    buffered_at: null,
    is_out_of_order: false,
    ...overrides,
  }
}

describe('DeviceDetailView.vue', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    Object.values(api).forEach((m) => m.mockReset())
    api.get.mockResolvedValue({
      id: 'gate-9',
      kind: 'gate',
      label: 'Gate 9',
      last_sequence_no: 42,
      last_seen_at: new Date().toISOString(),
    })
    api.events.mockResolvedValue({ items: [], next_cursor: null })
    api.replayAudits.mockResolvedValue([])
  })

  it('renders device header and events tab by default', async () => {
    api.events.mockResolvedValue({
      items: [event({ id: 1, status: 'accepted' }), event({ id: 2, status: 'duplicate', sequence_no: 2 })],
      next_cursor: null,
    })
    const wrapper = mount(DeviceDetailView)
    await flushPromises()

    expect(wrapper.text()).toContain('gate-9')
    expect(wrapper.text()).toContain('Gate 9')
    expect(wrapper.text()).toContain('accepted')
    expect(wrapper.text()).toContain('duplicate')
  })

  it('filters events by status when a chip is clicked', async () => {
    const wrapper = mount(DeviceDetailView)
    await flushPromises()
    api.events.mockClear()

    await wrapper.findAll('button').find((b) => b.text() === 'Accepted')!.trigger('click')
    await flushPromises()

    expect(api.events).toHaveBeenCalledWith('gate-9', expect.objectContaining({ status: 'accepted' }))
  })

  it('expands an event row to reveal its payload panel', async () => {
    api.events.mockResolvedValue({
      items: [event({ id: 7, payload_json: { plate: 'ABC123' } })],
      next_cursor: null,
    })
    const wrapper = mount(DeviceDetailView)
    await flushPromises()

    // Initially collapsed: payload text not visible.
    expect(wrapper.text()).not.toContain('"plate"')

    await wrapper.findAll('button').find((b) => b.text().includes('gate_open'))!.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('"plate"')
    expect(wrapper.text()).toContain('ABC123')
  })

  it('loads audits only once when the Audit Trail tab is first opened', async () => {
    api.replayAudits.mockResolvedValue([
      { id: 1, device_id: 'gate-9', initiated_by: 2, since_sequence_no: 0, until_sequence_no: 10, reason: 'test', created_at: new Date().toISOString() },
    ])

    const wrapper = mount(DeviceDetailView)
    await flushPromises()

    await wrapper.findAll('button').find((b) => b.text().includes('Audit Trail'))!.trigger('click')
    await flushPromises()

    expect(api.replayAudits).toHaveBeenCalledTimes(1)
    expect(wrapper.text()).toContain('Seq# 0')
    expect(wrapper.text()).toContain('By user #2')

    // Switch away and back — no second fetch because audits are cached locally.
    await wrapper.findAll('button').find((b) => b.text() === 'Events')!.trigger('click')
    await wrapper.findAll('button').find((b) => b.text().includes('Audit Trail'))!.trigger('click')
    await flushPromises()

    expect(api.replayAudits).toHaveBeenCalledTimes(1)
  })

  it('requires a confirm step before initiating replay, then calls the API with the form values', async () => {
    const ui = useUiStore()
    const notify = vi.spyOn(ui, 'addNotification')
    api.initiateReplay.mockResolvedValue({
      id: 10,
      device_id: 'gate-9',
      initiated_by: 2,
      since_sequence_no: 5,
      until_sequence_no: 20,
      reason: 'Incident #42',
      created_at: new Date().toISOString(),
    })

    const wrapper = mount(DeviceDetailView)
    await flushPromises()

    await wrapper.findAll('button').find((b) => b.text() === 'Replay')!.trigger('click')
    await flushPromises()

    // Fill the replay form.
    const inputs = wrapper.findAll('input[type="number"]')
    await inputs[0]!.setValue(5)
    await inputs[1]!.setValue(20)
    await wrapper.get('textarea').setValue('Incident #42')

    // First click reveals the confirm step — no network call yet.
    await wrapper.findAll('button').find((b) => b.text() === 'Initiate Replay')!.trigger('click')
    expect(api.initiateReplay).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Confirm Replay')

    // Second click actually initiates.
    await wrapper.findAll('button').find((b) => b.text() === 'Confirm')!.trigger('click')
    await flushPromises()

    expect(api.initiateReplay).toHaveBeenCalledWith('gate-9', {
      since_sequence_no: 5,
      until_sequence_no: 20,
      reason: 'Incident #42',
    })
    expect(notify).toHaveBeenCalledWith({ type: 'success', message: 'Replay initiated' })
  })

  it('renders the events empty state when no events are returned', async () => {
    api.events.mockResolvedValue({ items: [], next_cursor: null })
    const wrapper = mount(DeviceDetailView)
    await flushPromises()

    expect(wrapper.text()).toContain('No events found')
  })

  it('renders out_of_order and too_old status chips covering all getStatusTitle branches', async () => {
    api.events.mockResolvedValue({
      items: [
        event({ id: 1, status: 'out_of_order' }),
        event({ id: 2, status: 'too_old' }),
        event({ id: 3, status: undefined }),
      ],
      next_cursor: null,
    })
    const wrapper = mount(DeviceDetailView)
    await flushPromises()

    expect(wrapper.text()).toContain('out_of_order')
    expect(wrapper.text()).toContain('too_old')
  })

  it('loads next page when Load More is clicked with a non-null next_cursor', async () => {
    api.events
      .mockResolvedValueOnce({ items: [event({ id: 1 })], next_cursor: 'cur-2' })
      .mockResolvedValueOnce({ items: [event({ id: 2 })], next_cursor: null })

    const wrapper = mount(DeviceDetailView)
    await flushPromises()

    const loadMore = wrapper.findAll('button').find((b) => b.text().includes('Load More'))!
    await loadMore.trigger('click')
    await flushPromises()

    expect(api.events).toHaveBeenCalledTimes(2)
    expect(api.events.mock.calls[1]![1]).toMatchObject({ cursor: 'cur-2' })
  })

  it('Cancel button in replay confirmation hides the confirm dialog', async () => {
    const wrapper = mount(DeviceDetailView)
    await flushPromises()

    await wrapper.findAll('button').find((b) => b.text() === 'Replay')!.trigger('click')
    await flushPromises()

    await wrapper.findAll('button').find((b) => b.text() === 'Initiate Replay')!.trigger('click')
    expect(wrapper.text()).toContain('Confirm Replay')

    await wrapper.findAll('button').find((b) => b.text() === 'Cancel')!.trigger('click')
    expect(wrapper.text()).not.toContain('Confirm Replay')
  })
})
