import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import DevicesView from '@/views/devices/DevicesView.vue'
import { useUiStore } from '@/stores/ui'

const listMock = vi.fn()
vi.mock('@/services/api', () => ({
  devicesApi: { list: (...a: unknown[]) => listMock(...a) },
}))

const push = vi.fn()
vi.mock('vue-router', () => ({ useRouter: () => ({ push }) }))

function device(id: string, overrides: Record<string, unknown> = {}) {
  return {
    id,
    kind: 'gate',
    label: null,
    last_sequence_no: 0,
    last_seen_at: null,
    ...overrides,
  }
}

describe('DevicesView.vue', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    listMock.mockReset()
    push.mockReset()
  })

  it('renders an Online badge for a device seen within the last 5 minutes', async () => {
    const recent = new Date(Date.now() - 60_000).toISOString()
    listMock.mockResolvedValue([device('gate-1', { last_seen_at: recent, label: 'Main Gate' })])

    const wrapper = mount(DevicesView)
    await flushPromises()

    expect(wrapper.text()).toContain('gate-1')
    expect(wrapper.text()).toContain('Main Gate')
    expect(wrapper.text()).toContain('Online')
    expect(wrapper.text()).not.toContain('Offline')
  })

  it('renders an Offline badge for a device never seen or seen too long ago', async () => {
    const stale = new Date(Date.now() - 10 * 60_000).toISOString()
    listMock.mockResolvedValue([
      device('gate-2'), // never seen → Offline
      device('gate-3', { last_seen_at: stale }),
    ])

    const wrapper = mount(DevicesView)
    await flushPromises()

    const matches = wrapper.text().match(/Offline/g) ?? []
    expect(matches.length).toBe(2)
    expect(wrapper.text()).toContain('Never')
  })

  it('navigates to device detail on row click', async () => {
    listMock.mockResolvedValue([device('gate-1')])
    const wrapper = mount(DevicesView)
    await flushPromises()

    await wrapper.get('tbody tr').trigger('click')
    expect(push).toHaveBeenCalledWith('/devices/gate-1')
  })

  it('renders the empty state when no devices are registered', async () => {
    listMock.mockResolvedValue([])
    const wrapper = mount(DevicesView)
    await flushPromises()

    expect(wrapper.text()).toContain('No devices registered')
  })

  it('notifies on list failure', async () => {
    const ui = useUiStore()
    const spy = vi.spyOn(ui, 'addNotification')
    listMock.mockRejectedValue(new Error('boom'))

    mount(DevicesView)
    await flushPromises()

    expect(spy).toHaveBeenCalledWith({ type: 'error', message: 'Failed to load devices' })
  })
})
