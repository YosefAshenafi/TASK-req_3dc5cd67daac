import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes = [
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/LoginView.vue'),
    meta: { public: true },
  },
  {
    path: '/',
    component: () => import('@/views/AppLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        name: 'home',
        redirect: '/library',
      },
      {
        path: 'library',
        name: 'library',
        component: () => import('@/views/LibraryView.vue'),
      },
      {
        path: 'favorites',
        name: 'favorites',
        component: () => import('@/views/FavoritesView.vue'),
      },
      {
        path: 'playlists',
        name: 'playlists',
        component: () => import('@/views/PlaylistsView.vue'),
      },
      {
        path: 'playlists/:id',
        name: 'playlist-detail',
        component: () => import('@/views/PlaylistDetailView.vue'),
      },
      {
        path: 'history',
        name: 'history',
        component: () => import('@/views/HistoryView.vue'),
      },
      {
        path: 'admin',
        component: () => import('@/views/admin/AdminLayout.vue'),
        meta: { requiresRole: 'admin' },
        children: [
          {
            path: '',
            name: 'admin-dashboard',
            component: () => import('@/views/admin/AdminDashboardView.vue'),
          },
          {
            path: 'users',
            name: 'admin-users',
            component: () => import('@/views/admin/AdminUsersView.vue'),
          },
          {
            path: 'assets',
            name: 'admin-assets',
            component: () => import('@/views/admin/AdminAssetsView.vue'),
          },
          {
            path: 'monitoring',
            name: 'admin-monitoring',
            component: () => import('@/views/admin/AdminMonitoringView.vue'),
          },
        ],
      },
      {
        path: 'technician',
        component: () => import('@/views/TechnicianLayout.vue'),
        meta: { requiresRole: 'technician' },
        children: [
          {
            path: '',
            name: 'technician-console',
            component: () => import('@/views/TechnicianConsoleView.vue'),
          },
        ],
      },
    ],
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/',
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (!to.meta.public && !auth.user) {
    await auth.fetchMe().catch(() => {})
  }

  if (to.meta.requiresAuth && !auth.user) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.requiresRole && auth.user?.role !== to.meta.requiresRole) {
    return { name: 'library' }
  }
})

export default router
