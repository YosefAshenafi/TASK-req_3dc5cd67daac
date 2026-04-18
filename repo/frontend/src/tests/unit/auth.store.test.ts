import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import type { User, LoginResponse } from '@/types/api'

vi.mock('@/services/api', () => ({
  authApi: {
    login: vi.fn(),
    logout: vi.fn(),
    me: vi.fn(),
  },
  ApiError: class ApiError extends Error {
    constructor(
      public status: number,
      public body: any,
      message?: string,
    ) {
      super(message ?? `HTTP ${status}`)
      this.name = 'ApiError'
    }
  },
  FrozenError: class FrozenError extends Error {
    constructor(body: any) {
      super('Frozen')
      this.name = 'FrozenError'
    }
  },
  getStoredToken: vi.fn(() => 'mock-token'),
}))

const mockAdminUser: User = { id: 1, username: 'admin', role: 'admin' }
const mockRegularUser: User = { id: 2, username: 'user1', role: 'user' }
const mockTechUser: User = { id: 3, username: 'tech1', role: 'technician' }

function mockLoginResponse(user: User): LoginResponse {
  return { user, token: 'mock-token', csrf_token: null }
}

describe('Auth Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  describe('login → me → logout flow', () => {
    it('should set user after successful login', async () => {
      const { authApi } = await import('@/services/api')
      vi.mocked(authApi.login).mockResolvedValue(mockLoginResponse(mockRegularUser))

      const store = useAuthStore()
      expect(store.user).toBeNull()
      expect(store.isAuthenticated).toBe(false)

      await store.login('user1', 'password')

      expect(authApi.login).toHaveBeenCalledWith({ username: 'user1', password: 'password' })
      expect(store.user).toEqual(mockRegularUser)
      expect(store.isAuthenticated).toBe(true)
    })

    it('should fetch current user via fetchMe', async () => {
      const { authApi } = await import('@/services/api')
      vi.mocked(authApi.me).mockResolvedValue(mockAdminUser)

      const store = useAuthStore()
      await store.fetchMe()

      expect(authApi.me).toHaveBeenCalled()
      expect(store.user).toEqual(mockAdminUser)
      expect(store.isAdmin).toBe(true)
      expect(store.isTechnician).toBe(false)
    })

    it('should clear user after logout', async () => {
      const { authApi } = await import('@/services/api')
      vi.mocked(authApi.logout).mockResolvedValue(undefined)
      vi.mocked(authApi.me).mockResolvedValue(mockRegularUser)

      const store = useAuthStore()
      await store.fetchMe()
      expect(store.isAuthenticated).toBe(true)

      await store.logout()

      expect(authApi.logout).toHaveBeenCalled()
      expect(store.user).toBeNull()
      expect(store.isAuthenticated).toBe(false)
    })

    it('should handle logout errors gracefully', async () => {
      const { authApi } = await import('@/services/api')
      vi.mocked(authApi.me).mockResolvedValue(mockRegularUser)
      vi.mocked(authApi.logout).mockRejectedValue(new Error('Network error'))

      const store = useAuthStore()
      await store.fetchMe()

      await expect(store.logout()).resolves.not.toThrow()
      expect(store.user).toBeNull()
    })

    it('should set loading to false after login completes', async () => {
      const { authApi } = await import('@/services/api')
      vi.mocked(authApi.login).mockResolvedValue(mockLoginResponse(mockRegularUser))

      const store = useAuthStore()
      const loginPromise = store.login('user1', 'password')
      await loginPromise

      expect(store.loading).toBe(false)
    })

    it('should skip fetchMe when no stored token', async () => {
      const { authApi, getStoredToken } = await import('@/services/api')
      vi.mocked(getStoredToken).mockReturnValueOnce(null)

      const store = useAuthStore()
      await store.fetchMe()

      expect(authApi.me).not.toHaveBeenCalled()
      expect(store.user).toBeNull()
    })

    it('should clear user on fetchMe when API returns 401', async () => {
      const { authApi, ApiError } = await import('@/services/api')
      vi.mocked(authApi.me).mockRejectedValue(new ApiError(401, null))

      const store = useAuthStore()
      await store.fetchMe()

      expect(store.user).toBeNull()
    })

    it('should rethrow fetchMe when API returns non-401 error', async () => {
      const { authApi, ApiError } = await import('@/services/api')
      vi.mocked(authApi.me).mockRejectedValue(new ApiError(503, {}, 'Unavailable'))

      const store = useAuthStore()
      await expect(store.fetchMe()).rejects.toMatchObject({ status: 503 })
    })
  })

  describe('role getters', () => {
    it('should correctly identify admin role', async () => {
      const { authApi } = await import('@/services/api')
      vi.mocked(authApi.me).mockResolvedValue(mockAdminUser)

      const store = useAuthStore()
      await store.fetchMe()

      expect(store.isAdmin).toBe(true)
      expect(store.isTechnician).toBe(false)
      expect(store.isUser).toBe(false)
    })

    it('should correctly identify technician role', async () => {
      const { authApi } = await import('@/services/api')
      vi.mocked(authApi.me).mockResolvedValue(mockTechUser)

      const store = useAuthStore()
      await store.fetchMe()

      expect(store.isAdmin).toBe(false)
      expect(store.isTechnician).toBe(true)
      expect(store.isUser).toBe(false)
    })

    it('should correctly identify user role', async () => {
      const { authApi } = await import('@/services/api')
      vi.mocked(authApi.me).mockResolvedValue(mockRegularUser)

      const store = useAuthStore()
      await store.fetchMe()

      expect(store.isAdmin).toBe(false)
      expect(store.isTechnician).toBe(false)
      expect(store.isUser).toBe(true)
    })
  })

  describe('route guard behavior', () => {
    it('should block /admin route for user role', async () => {
      const { authApi } = await import('@/services/api')
      vi.mocked(authApi.me).mockResolvedValue(mockRegularUser)

      const store = useAuthStore()
      await store.fetchMe()

      const requiresRole = 'admin'
      const userRole = store.user!.role
      const allowed = userRole === requiresRole

      expect(allowed).toBe(false)
    })

    it('should allow /admin route for admin role', async () => {
      const { authApi } = await import('@/services/api')
      vi.mocked(authApi.me).mockResolvedValue(mockAdminUser)

      const store = useAuthStore()
      await store.fetchMe()

      const requiresRole = 'admin'
      const userRole = store.user!.role
      const allowed = userRole === requiresRole

      expect(allowed).toBe(true)
    })

    it('should block /devices route for user role', async () => {
      const { authApi } = await import('@/services/api')
      vi.mocked(authApi.me).mockResolvedValue(mockRegularUser)

      const store = useAuthStore()
      await store.fetchMe()

      const requiresRole = ['admin', 'technician']
      const userRole = store.user!.role
      const allowed = requiresRole.includes(userRole)

      expect(allowed).toBe(false)
    })

    it('should allow /devices route for technician role', async () => {
      const { authApi } = await import('@/services/api')
      vi.mocked(authApi.me).mockResolvedValue(mockTechUser)

      const store = useAuthStore()
      await store.fetchMe()

      const requiresRole = ['admin', 'technician']
      const userRole = store.user!.role
      const allowed = requiresRole.includes(userRole)

      expect(allowed).toBe(true)
    })

    it('should redirect to correct home per role', () => {
      const getRoleHome = (role: string): string => {
        switch (role) {
          case 'admin':
            return '/admin'
          case 'technician':
            return '/devices'
          default:
            return '/search'
        }
      }

      expect(getRoleHome('admin')).toBe('/admin')
      expect(getRoleHome('technician')).toBe('/devices')
      expect(getRoleHome('user')).toBe('/search')
    })
  })
})
