import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import ForbiddenView from '@/views/ForbiddenView.vue'
import { useAuthStore } from '@/stores/auth'

const push = vi.fn()

vi.mock('vue-router', () => ({
  useRouter: () => ({ push }),
}))

// ForbiddenView imports `getRoleHome` from `@/router/index`, which in turn
// creates the real router (and therefore needs `createRouter`/`createWebHistory`
// from vue-router, which we deliberately don't expose). Stub the router module
// to only export the pure helper we actually need.
vi.mock('@/router/index', () => ({
  getRoleHome: (role: string) => {
    switch (role) {
      case 'admin':
        return '/admin'
      case 'technician':
        return '/devices'
      default:
        return '/search'
    }
  },
}))

describe('ForbiddenView.vue', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    push.mockReset()
  })

  it('renders the 403 header, hint text, and Go to Home CTA', () => {
    const wrapper = mount(ForbiddenView)
    const body = wrapper.text()

    // Hard-coded strings that the QA team reads directly from prod — regressions
    // in wording have broken the screenshot diff in the past.
    expect(body).toContain('403')
    expect(body).toContain('Access Forbidden')
    expect(body).toContain("You don't have permission to access this page.")
    expect(body).toContain('Go to Home')
  })

  it('navigates an admin user to /admin when they click Go to Home', async () => {
    const auth = useAuthStore()
    auth.user = { id: 1, username: 'admin', role: 'admin' }

    const wrapper = mount(ForbiddenView)
    await wrapper.find('button').trigger('click')

    expect(push).toHaveBeenCalledWith('/admin')
  })

  it('navigates a technician to /devices when they click Go to Home', async () => {
    const auth = useAuthStore()
    auth.user = { id: 2, username: 'tech', role: 'technician' }

    const wrapper = mount(ForbiddenView)
    await wrapper.find('button').trigger('click')

    expect(push).toHaveBeenCalledWith('/devices')
  })

  it('navigates a regular user to /search when they click Go to Home', async () => {
    const auth = useAuthStore()
    auth.user = { id: 3, username: 'user1', role: 'user' }

    const wrapper = mount(ForbiddenView)
    await wrapper.find('button').trigger('click')

    expect(push).toHaveBeenCalledWith('/search')
  })

  it('defaults to /search when no user is signed in (role fallback)', async () => {
    // This path covers the `authStore.user?.role ?? "user"` fallback. If the
    // coalesce were removed, this click would throw on `.role` of undefined.
    const auth = useAuthStore()
    auth.user = null

    const wrapper = mount(ForbiddenView)
    await wrapper.find('button').trigger('click')

    expect(push).toHaveBeenCalledWith('/search')
  })
})
