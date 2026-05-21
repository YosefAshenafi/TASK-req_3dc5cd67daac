import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import TechnicianConsoleView from '@/views/TechnicianConsoleView.vue'

const mockDevices = [
  { id: 1, name: 'Gate A', device_type: 'gate', last_sequence: 42, last_event_at: '2026-05-21T09:00:00Z' },
]

const mockEvents = [
  { id: 1, device_name: 'Gate A', event_type: 'gate_open', sequence: 42, status: 'received', replay_audit_id: null, received_at: '2026-05-21T09:00:00Z' },
  { id: 2, device_name: 'Gate A', event_type: 'gate_close', sequence: 41, status: 'late', replay_audit_id: null, received_at: '2026-05-21T08:55:00Z' },
  { id: 3, device_name: 'Camera B', event_type: 'motion', sequence: 10, status: 'buffered', replay_audit_id: null, received_at: '2026-05-21T08:50:00Z' },
  { id: 4, device_name: 'Gate A', event_type: 'gate_open', sequence: 40, status: 'duplicate', replay_audit_id: null, received_at: '2026-05-21T08:45:00Z' },
]

vi.mock('@/api/axios', () => ({
  default: {
    get: vi.fn((path) => {
      if (path.includes('/devices')) {
        return Promise.resolve({ data: { data: mockDevices } })
      }
      return Promise.resolve({ data: { data: mockEvents } })
    }),
  },
}))

describe('TechnicianConsoleView', () => {
  it('renders device names in the grid', async () => {
    const wrapper = mount(TechnicianConsoleView)
    await flushPromises()
    expect(wrapper.text()).toContain('Gate A')
  })

  it('renders received status badge with green class', async () => {
    const wrapper = mount(TechnicianConsoleView)
    await flushPromises()
    const badges = wrapper.findAll('.badge')
    const receivedBadge = badges.find(b => b.text() === 'received')
    expect(receivedBadge).toBeTruthy()
    expect(receivedBadge?.classes()).toContain('bg-green-100')
  })

  it('renders late status badge with yellow class', async () => {
    const wrapper = mount(TechnicianConsoleView)
    await flushPromises()
    const badges = wrapper.findAll('.badge')
    const lateBadge = badges.find(b => b.text() === 'late')
    expect(lateBadge).toBeTruthy()
    expect(lateBadge?.classes()).toContain('bg-yellow-100')
  })

  it('renders buffered status badge with blue class', async () => {
    const wrapper = mount(TechnicianConsoleView)
    await flushPromises()
    const badges = wrapper.findAll('.badge')
    const bufferedBadge = badges.find(b => b.text() === 'buffered')
    expect(bufferedBadge).toBeTruthy()
    expect(bufferedBadge?.classes()).toContain('bg-blue-100')
  })

  it('renders duplicate status badge with surface class', async () => {
    const wrapper = mount(TechnicianConsoleView)
    await flushPromises()
    const badges = wrapper.findAll('.badge')
    const dupBadge = badges.find(b => b.text() === 'duplicate')
    expect(dupBadge).toBeTruthy()
    expect(dupBadge?.classes()).toContain('bg-surface-100')
  })

  it('renders event type column values', async () => {
    const wrapper = mount(TechnicianConsoleView)
    await flushPromises()
    expect(wrapper.text()).toContain('gate_open')
  })

  it('shows status filter select', async () => {
    const wrapper = mount(TechnicianConsoleView)
    await flushPromises()
    expect(wrapper.find('select').exists()).toBe(true)
  })

  it('shows auto-refresh indicator', () => {
    const wrapper = mount(TechnicianConsoleView)
    expect(wrapper.text()).toContain('Auto-refresh')
  })
})
