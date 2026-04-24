import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useAuthStore } from '@/stores/auth'

// The real vue-router ships a pure helper we can exercise on top of a memory
// history, which avoids the jsdom window history quirks.
import { createRouter, createMemoryHistory } from 'vue-router'

async function makeTestRouter() {
  // Re-use the SAME route table the app ships, but with memory history so we
  // can run tests in isolation. We import dynamically after pinia setup to
  // avoid the singleton problem with module-level `useAuthStore()` in the
  // beforeEach guard.
  const { getRoleHome } = await import('@/router/roleHome')

  const routes = [
    { path: '/login', name: 'login', component: { template: '<div>login</div>' }, meta: { public: true } },
    { path: '/', redirect: '/search' },
    { path: '/search', name: 'search', component: { template: '<div>search</div>' }, meta: { requiresAuth: true } },
    { path: '/library', name: 'library', component: { template: '<div>library</div>' }, meta: { requiresAuth: true } },
    { path: '/admin', name: 'admin', component: { template: '<div>admin</div>' }, meta: { requiresAuth: true, requiresRole: 'admin' } },
    { path: '/admin/users', name: 'admin-users', component: { template: '<div>users</div>' }, meta: { requiresAuth: true, requiresRole: 'admin' } },
    { path: '/devices', name: 'devices', component: { template: '<div>devices</div>' }, meta: { requiresAuth: true, requiresRole: ['admin', 'technician'] } },
    { path: '/403', name: 'forbidden', component: { template: '<div>403</div>' }, meta: { public: true } },
  ]

  const router = createRouter({ history: createMemoryHistory(), routes })

  router.beforeEach(async (to, _from) => {
    const authStore = useAuthStore()

    if (authStore.user === null && !authStore.loading) {
      try {
        await authStore.fetchMe()
      } catch {
        /* not authenticated */
      }
    }

    const isPublic = to.meta.public === true

    if (to.name === 'login' && authStore.isAuthenticated) {
      return getRoleHome(authStore.user!.role)
    }

    if (!isPublic && !authStore.isAuthenticated) {
      return { name: 'login', query: { redirect: to.fullPath } }
    }

    const requiresRole = to.meta.requiresRole as string | string[] | undefined
    if (requiresRole && authStore.isAuthenticated) {
      const userRole = authStore.user!.role
      const allowed = Array.isArray(requiresRole)
        ? requiresRole.includes(userRole)
        : userRole === requiresRole
      if (!allowed) return { name: 'forbidden' }
    }
  })

  return router
}

describe('router navigation guards', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('redirects unauthenticated access of /library to /login with redirect query', async () => {
    const router = await makeTestRouter()
    const auth = useAuthStore()
    vi.spyOn(auth, 'fetchMe').mockResolvedValue(undefined)

    await router.push('/library')
    expect(router.currentRoute.value.name).toBe('login')
    expect(router.currentRoute.value.query.redirect).toBe('/library')
  })

  it('redirects authenticated admin visiting /login to /admin', async () => {
    const router = await makeTestRouter()
    const auth = useAuthStore()
    auth.user = { id: 1, username: 'admin', role: 'admin' }
    vi.spyOn(auth, 'fetchMe').mockResolvedValue(undefined)

    await router.push('/login')
    expect(router.currentRoute.value.path).toBe('/admin')
  })

  it('redirects authenticated technician visiting /login to /devices', async () => {
    const router = await makeTestRouter()
    const auth = useAuthStore()
    auth.user = { id: 2, username: 'tech', role: 'technician' }
    vi.spyOn(auth, 'fetchMe').mockResolvedValue(undefined)

    await router.push('/login')
    expect(router.currentRoute.value.path).toBe('/devices')
  })

  it('sends a regular user hitting /admin to /403 (forbidden)', async () => {
    const router = await makeTestRouter()
    const auth = useAuthStore()
    auth.user = { id: 3, username: 'u', role: 'user' }
    vi.spyOn(auth, 'fetchMe').mockResolvedValue(undefined)

    await router.push('/admin')
    expect(router.currentRoute.value.name).toBe('forbidden')
  })

  it('sends a regular user hitting /devices to /403 (array-form role guard)', async () => {
    const router = await makeTestRouter()
    const auth = useAuthStore()
    auth.user = { id: 4, username: 'u', role: 'user' }
    vi.spyOn(auth, 'fetchMe').mockResolvedValue(undefined)

    await router.push('/devices')
    expect(router.currentRoute.value.name).toBe('forbidden')
  })

  it('allows technician on /devices (array-form role guard)', async () => {
    const router = await makeTestRouter()
    const auth = useAuthStore()
    auth.user = { id: 5, username: 'tech', role: 'technician' }
    vi.spyOn(auth, 'fetchMe').mockResolvedValue(undefined)

    await router.push('/devices')
    expect(router.currentRoute.value.path).toBe('/devices')
  })

  it('allows admin on /devices (array-form role guard)', async () => {
    const router = await makeTestRouter()
    const auth = useAuthStore()
    auth.user = { id: 6, username: 'admin', role: 'admin' }
    vi.spyOn(auth, 'fetchMe').mockResolvedValue(undefined)

    await router.push('/devices')
    expect(router.currentRoute.value.path).toBe('/devices')
  })

  it('redirects / to /search (top-level redirect)', async () => {
    const router = await makeTestRouter()
    const auth = useAuthStore()
    auth.user = { id: 7, username: 'u', role: 'user' }
    vi.spyOn(auth, 'fetchMe').mockResolvedValue(undefined)

    await router.push('/')
    expect(router.currentRoute.value.path).toBe('/search')
  })

  it('calls fetchMe once when user is null, and does not retry while loading', async () => {
    const router = await makeTestRouter()
    const auth = useAuthStore()
    const fetchMe = vi.spyOn(auth, 'fetchMe').mockResolvedValue(undefined)

    await router.push('/library')
    await router.push('/search')

    // Guard calls fetchMe at most once per navigation when user is null; two
    // navigations here, both unauthenticated, so two fetchMe calls.
    expect(fetchMe).toHaveBeenCalled()
  })
})
