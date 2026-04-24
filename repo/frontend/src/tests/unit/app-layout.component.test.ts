import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { useAuthStore } from '@/stores/auth'
import { useSettingsStore } from '@/stores/settings'
import { useUiStore } from '@/stores/ui'

const push = vi.fn()

vi.mock('vue-router', () => ({
  useRouter: () => ({ push }),
  RouterLink: defineComponent({
    name: 'RouterLink',
    props: { to: { type: [String, Object], required: false } },
    template: '<a><slot /></a>',
  }),
}))

describe('AppLayout.vue', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('renders admin navigation links for admin users', () => {
    const auth = useAuthStore()
    const settings = useSettingsStore()
    auth.user = { id: 1, username: 'admin', role: 'admin' }
    settings.siteName = 'SmartPark'

    const wrapper = mount(AppLayout, {
      slots: { default: '<div>Content</div>' },
    })

    expect(wrapper.text()).toContain('Dashboard')
    expect(wrapper.text()).toContain('Users')
    expect(wrapper.text()).not.toContain('Search')
  })

  it('renders user navigation links for regular users', () => {
    const auth = useAuthStore()
    auth.user = { id: 2, username: 'user1', role: 'user' }

    const wrapper = mount(AppLayout, {
      slots: { default: '<div>Content</div>' },
    })

    expect(wrapper.text()).toContain('Search')
    expect(wrapper.text()).toContain('Playlists')
    expect(wrapper.text()).not.toContain('Dashboard')
  })

  it('logs out and redirects to login', async () => {
    const auth = useAuthStore()
    auth.user = { id: 1, username: 'admin', role: 'admin' }
    const logoutSpy = vi.spyOn(auth, 'logout').mockResolvedValue(undefined)

    const wrapper = mount(AppLayout, {
      slots: { default: '<div>Content</div>' },
    })

    const signOut = wrapper.findAll('button').find((b) => b.text().includes('Sign out'))
    expect(signOut).toBeDefined()
    await signOut!.trigger('click')

    expect(logoutSpy).toHaveBeenCalledTimes(1)
    expect(push).toHaveBeenCalledWith('/login')
  })

  it('dismisses notifications via UI store action', async () => {
    const auth = useAuthStore()
    const ui = useUiStore()
    auth.user = { id: 1, username: 'admin', role: 'admin' }
    const id = ui.addNotification({ type: 'info', message: 'Hello', timeout: 0 })

    const wrapper = mount(AppLayout, {
      slots: { default: '<div>Content</div>' },
    })

    const dismissButton = wrapper.find('button[aria-label="Dismiss"]')
    expect(dismissButton.exists()).toBe(true)
    await dismissButton.trigger('click')

    expect(ui.notifications.find((n) => n.id === id)).toBeUndefined()
  })

  it('toggles the mobile sidebar when the menu button and overlay are clicked', async () => {
    const auth = useAuthStore()
    auth.user = { id: 1, username: 'user', role: 'user' }

    const wrapper = mount(AppLayout, { slots: { default: '<div>Content</div>' } })

    // The hamburger button (lg:hidden) has an unnamed Menu icon; find by its
    // "Sign out"-adjacent role: it's the only <button> without text content
    // inside the top bar. We trigger a click and then verify the backdrop
    // overlay appears (sidebarOpen=true).
    const menuBtn = wrapper.findAll('button').find((b) => b.element.querySelector('svg.lucide-menu'))
      ?? wrapper.findAll('button').find((b) => b.classes().join(' ').includes('lg:hidden'))
    expect(menuBtn).toBeDefined()
    await menuBtn!.trigger('click')

    // The overlay div uses bg-black/60 and inset-0 — easy to locate.
    const overlay = wrapper.find('div.bg-black\\/60')
    expect(overlay.exists()).toBe(true)

    // Clicking the overlay closes the sidebar, removing the overlay from DOM.
    await overlay.trigger('click')
    expect(wrapper.find('div.bg-black\\/60').exists()).toBe(false)
  })

  it('surfaces the offline indicator when the UI store reports offline', () => {
    const auth = useAuthStore()
    const ui = useUiStore()
    auth.user = { id: 1, username: 'u', role: 'user' }
    ui.offline = true

    const wrapper = mount(AppLayout, { slots: { default: '<div>Content</div>' } })
    expect(wrapper.text()).toContain('Offline mode')
  })

  it('renders the site name from the settings store in the sidebar brand', () => {
    const auth = useAuthStore()
    const settings = useSettingsStore()
    auth.user = { id: 1, username: 'u', role: 'user' }
    settings.siteName = 'Operations HQ'

    const wrapper = mount(AppLayout, { slots: { default: '<div>Content</div>' } })
    expect(wrapper.text()).toContain('Operations HQ')
  })

  it('renders technician-scoped navigation for technician users', () => {
    const auth = useAuthStore()
    auth.user = { id: 9, username: 'tech', role: 'technician' }

    const wrapper = mount(AppLayout, { slots: { default: '<div>Content</div>' } })
    // Technicians see the Devices link but none of the user-only library pages.
    expect(wrapper.text()).toContain('Devices')
    expect(wrapper.text()).not.toContain('Favorites')
  })
})
