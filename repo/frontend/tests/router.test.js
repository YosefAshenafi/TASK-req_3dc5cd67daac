import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

// Auth state controlled per-test
let mockUser = null
const mockFetchMe = vi.fn()

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    get user() { return mockUser },
    fetchMe: mockFetchMe,
  }),
}))

// Import after mock is registered
import router from '@/router'

describe('Router — route registration', () => {
  it('registers a login route', () => {
    const names = router.getRoutes().map(r => r.name)
    expect(names).toContain('login')
  })

  it('registers a library route', () => {
    expect(router.getRoutes().some(r => r.name === 'library')).toBe(true)
  })

  it('registers a favorites route', () => {
    expect(router.getRoutes().some(r => r.name === 'favorites')).toBe(true)
  })

  it('registers a playlists route', () => {
    expect(router.getRoutes().some(r => r.name === 'playlists')).toBe(true)
  })

  it('registers a playlist-detail route', () => {
    expect(router.getRoutes().some(r => r.name === 'playlist-detail')).toBe(true)
  })

  it('registers a history route', () => {
    expect(router.getRoutes().some(r => r.name === 'history')).toBe(true)
  })

  it('registers an admin-dashboard route', () => {
    expect(router.getRoutes().some(r => r.name === 'admin-dashboard')).toBe(true)
  })

  it('registers an admin-users route', () => {
    expect(router.getRoutes().some(r => r.name === 'admin-users')).toBe(true)
  })

  it('registers an admin-assets route', () => {
    expect(router.getRoutes().some(r => r.name === 'admin-assets')).toBe(true)
  })

  it('registers an admin-monitoring route', () => {
    expect(router.getRoutes().some(r => r.name === 'admin-monitoring')).toBe(true)
  })

  it('registers a technician-console route', () => {
    expect(router.getRoutes().some(r => r.name === 'technician-console')).toBe(true)
  })

  it('login route has public meta', () => {
    const loginRoute = router.getRoutes().find(r => r.name === 'login')
    expect(loginRoute?.meta?.public).toBe(true)
  })

  it('admin route has requiresRole admin meta', () => {
    const adminRoute = router.getRoutes().find(r => r.name === 'admin-dashboard')
    // Admin routes are nested; check meta on the parent or child
    const parentAdmin = router.getRoutes().find(r =>
      r.meta?.requiresRole === 'admin' && r.children?.length > 0
    )
    expect(parentAdmin).toBeTruthy()
  })

  it('technician route has requiresRole technician meta', () => {
    const techParent = router.getRoutes().find(r =>
      r.meta?.requiresRole === 'technician'
    )
    expect(techParent).toBeTruthy()
  })
})

describe('Router — navigation guards', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    mockUser = null
    mockFetchMe.mockRejectedValue(new Error('unauthenticated'))
  })

  it('redirects unauthenticated user to login for protected route', async () => {
    mockUser = null
    await router.push('/library')
    expect(router.currentRoute.value.name).toBe('login')
  })

  it('allows authenticated user to access library', async () => {
    mockUser = { id: 1, role: 'user' }
    await router.push('/library')
    expect(router.currentRoute.value.name).toBe('library')
  })

  it('redirects user role away from admin routes', async () => {
    mockUser = { id: 1, role: 'user' }
    await router.push('/admin')
    expect(router.currentRoute.value.name).toBe('library')
  })

  it('allows admin role to access admin routes', async () => {
    mockUser = { id: 2, role: 'admin' }
    await router.push('/admin')
    expect(router.currentRoute.value.name).toBe('admin-dashboard')
  })

  it('redirects user role away from technician routes', async () => {
    mockUser = { id: 1, role: 'user' }
    await router.push('/technician')
    expect(router.currentRoute.value.name).toBe('library')
  })

  it('allows technician role to access technician routes', async () => {
    mockUser = { id: 3, role: 'technician' }
    await router.push('/technician')
    expect(router.currentRoute.value.name).toBe('technician-console')
  })

  it('includes redirect query param when redirecting to login', async () => {
    mockUser = null
    await router.push('/favorites')
    const query = router.currentRoute.value.query
    expect(query.redirect).toBe('/favorites')
  })
})
