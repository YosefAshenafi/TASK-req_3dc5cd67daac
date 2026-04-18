import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
      meta: { public: true },
    },
    {
      path: '/',
      redirect: '/search',
    },
    {
      path: '/search',
      name: 'search',
      component: () => import('@/views/SearchView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/library',
      name: 'library',
      component: () => import('@/views/LibraryView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/favorites',
      name: 'favorites',
      component: () => import('@/views/FavoritesView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/playlists',
      name: 'playlists',
      component: () => import('@/views/PlaylistsView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/playlists/:id',
      name: 'playlist-detail',
      component: () => import('@/views/PlaylistDetailView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/now-playing',
      name: 'now-playing',
      component: () => import('@/views/NowPlayingView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/admin',
      name: 'admin',
      component: () => import('@/views/admin/AdminView.vue'),
      meta: { requiresAuth: true, requiresRole: 'admin' },
    },
    {
      path: '/admin/users',
      name: 'admin-users',
      component: () => import('@/views/admin/AdminUsersView.vue'),
      meta: { requiresAuth: true, requiresRole: 'admin' },
    },
    {
      path: '/admin/uploads',
      name: 'admin-uploads',
      component: () => import('@/views/admin/AdminUploadsView.vue'),
      meta: { requiresAuth: true, requiresRole: 'admin' },
    },
    {
      path: '/admin/monitoring',
      name: 'admin-monitoring',
      component: () => import('@/views/admin/AdminMonitoringView.vue'),
      meta: { requiresAuth: true, requiresRole: 'admin' },
    },
    {
      path: '/admin/settings',
      name: 'admin-settings',
      component: () => import('@/views/admin/AdminSettingsView.vue'),
      meta: { requiresAuth: true, requiresRole: 'admin' },
    },
    {
      path: '/devices',
      name: 'devices',
      component: () => import('@/views/devices/DevicesView.vue'),
      meta: { requiresAuth: true, requiresRole: ['admin', 'technician'] },
    },
    {
      path: '/devices/:id',
      name: 'device-detail',
      component: () => import('@/views/devices/DeviceDetailView.vue'),
      meta: { requiresAuth: true, requiresRole: ['admin', 'technician'] },
    },
    {
      path: '/403',
      name: 'forbidden',
      component: () => import('@/views/ForbiddenView.vue'),
      meta: { public: true },
    },
  ],
})

function getRoleHome(role: string): string {
  switch (role) {
    case 'admin':
      return '/admin'
    case 'technician':
      return '/devices'
    default:
      return '/search'
  }
}

router.beforeEach(async (to, _from) => {
  const authStore = useAuthStore()

  // Attempt to fetch the current user if not yet loaded
  if (authStore.user === null && !authStore.loading) {
    try {
      await authStore.fetchMe()
    } catch {
      // not authenticated
    }
  }

  const isPublic = to.meta.public === true

  // Authenticated user visiting /login → redirect to their home
  if (to.name === 'login' && authStore.isAuthenticated) {
    return getRoleHome(authStore.user!.role)
  }

  // Unauthenticated → redirect to login (unless going to public route)
  if (!isPublic && !authStore.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  // Role check
  const requiresRole = to.meta.requiresRole
  if (requiresRole && authStore.isAuthenticated) {
    const userRole = authStore.user!.role
    const allowed = Array.isArray(requiresRole)
      ? requiresRole.includes(userRole)
      : userRole === requiresRole

    if (!allowed) {
      return { name: 'forbidden' }
    }
  }
})

export default router
export { getRoleHome }
