import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { useNowPlayingStore } from '@/stores/nowPlaying'
import NowPlayingPanel from '@/components/NowPlayingPanel.vue'

vi.mock('@/api/axios', () => ({
  default: {
    get: vi.fn().mockResolvedValue({ data: { data: [] } }),
    post: vi.fn().mockResolvedValue({ data: {} }),
  },
}))

describe('NowPlayingPanel', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('renders collapsed state by default', () => {
    const wrapper = mount(NowPlayingPanel)
    expect(wrapper.find('button').exists()).toBe(true)
    expect(wrapper.text()).toContain('Now Playing')
  })

  it('expands when toggle button clicked', async () => {
    const wrapper = mount(NowPlayingPanel)
    await wrapper.find('button').trigger('click')
    expect(wrapper.vm.isExpanded).toBe(true)
  })

  it('shows empty state when no history', async () => {
    const wrapper = mount(NowPlayingPanel)
    await wrapper.find('button').trigger('click')
    await wrapper.vm.$nextTick()
    expect(wrapper.text()).toContain('No recent plays')
  })

  it('shows history items when populated', async () => {
    const pinia = createPinia()
    setActivePinia(pinia)
    const store = useNowPlayingStore()
    // Prevent onMounted fetchHistory from overwriting the test data
    vi.spyOn(store, 'fetchHistory').mockResolvedValue(undefined)
    store.history = [
      { id: 1, played_at: new Date().toISOString(), asset: { title: 'Test Track' } },
    ]
    const wrapper = mount(NowPlayingPanel, { global: { plugins: [pinia] } })
    await wrapper.find('button').trigger('click')
    await wrapper.vm.$nextTick()
    expect(wrapper.text()).toContain('Test Track')
  })

  it('surfaces the current track in the header even while collapsed', async () => {
    const pinia = createPinia()
    setActivePinia(pinia)
    const store = useNowPlayingStore()
    vi.spyOn(store, 'fetchHistory').mockResolvedValue(undefined)
    store.current = {
      id: 7,
      played_at: new Date().toISOString(),
      asset: { id: 1, title: 'Lobby Announcement' },
    }
    const wrapper = mount(NowPlayingPanel, { global: { plugins: [pinia] } })
    await wrapper.vm.$nextTick()
    // Header reflects the active track without expanding the panel.
    expect(wrapper.vm.isExpanded).toBe(false)
    expect(wrapper.text()).toContain('Lobby Announcement')
  })

  it('auto-expands the panel when a play is recorded', async () => {
    const pinia = createPinia()
    setActivePinia(pinia)
    const store = useNowPlayingStore()
    vi.spyOn(store, 'fetchHistory').mockResolvedValue(undefined)
    const wrapper = mount(NowPlayingPanel, { global: { plugins: [pinia] } })
    expect(wrapper.vm.isExpanded).toBe(false)
    // Simulate the signal emitted by recordPlay().
    store.playToken += 1
    await wrapper.vm.$nextTick()
    expect(wrapper.vm.isExpanded).toBe(true)
  })

  it('marks the current entry in the list', async () => {
    const pinia = createPinia()
    setActivePinia(pinia)
    const store = useNowPlayingStore()
    vi.spyOn(store, 'fetchHistory').mockResolvedValue(undefined)
    const entry = { id: 9, played_at: new Date().toISOString(), asset: { id: 2, title: 'Garage Clip' } }
    store.history = [entry]
    store.current = entry
    const wrapper = mount(NowPlayingPanel, { global: { plugins: [pinia] } })
    await wrapper.find('button').trigger('click')
    await wrapper.vm.$nextTick()
    expect(wrapper.text()).toContain('Current')
    expect(wrapper.text()).toContain('Garage Clip')
  })

  it('exposes a Stop control only while something is current', async () => {
    const pinia = createPinia()
    setActivePinia(pinia)
    const store = useNowPlayingStore()
    vi.spyOn(store, 'fetchHistory').mockResolvedValue(undefined)
    const wrapper = mount(NowPlayingPanel, { global: { plugins: [pinia] } })
    // Nothing playing → no Stop button.
    expect(wrapper.find('[data-testid="np-stop"]').exists()).toBe(false)
    store.current = { id: 3, played_at: new Date().toISOString(), asset: { id: 5, title: 'Gate Chime' } }
    await wrapper.vm.$nextTick()
    expect(wrapper.find('[data-testid="np-stop"]').exists()).toBe(true)
  })

  it('clears the current track when Stop is clicked', async () => {
    const pinia = createPinia()
    setActivePinia(pinia)
    const store = useNowPlayingStore()
    vi.spyOn(store, 'fetchHistory').mockResolvedValue(undefined)
    store.current = { id: 3, played_at: new Date().toISOString(), asset: { id: 5, title: 'Gate Chime' } }
    const wrapper = mount(NowPlayingPanel, { global: { plugins: [pinia] } })
    await wrapper.vm.$nextTick()
    expect(wrapper.text()).toContain('Gate Chime')
    await wrapper.find('[data-testid="np-stop"]').trigger('click')
    await wrapper.vm.$nextTick()
    expect(store.current).toBe(null)
    expect(wrapper.find('[data-testid="np-stop"]').exists()).toBe(false)
  })
})
