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
})
