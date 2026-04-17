<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'

const authStore = useAuthStore()
const uiStore = useUiStore()
const router = useRouter()

const roleBadgeClass = computed(() => {
  switch (authStore.user?.role) {
    case 'admin':
      return 'bg-red-100 text-red-800'
    case 'technician':
      return 'bg-yellow-100 text-yellow-800'
    default:
      return 'bg-green-100 text-green-800'
  }
})

const navLinks = computed(() => {
  const role = authStore.user?.role
  if (role === 'admin') {
    return [
      { name: 'Admin', to: '/admin' },
      { name: 'Users', to: '/admin/users' },
      { name: 'Uploads', to: '/admin/uploads' },
      { name: 'Monitoring', to: '/admin/monitoring' },
      { name: 'Devices', to: '/devices' },
    ]
  }
  if (role === 'technician') {
    return [{ name: 'Devices', to: '/devices' }]
  }
  return [
    { name: 'Search', to: '/search' },
    { name: 'Library', to: '/library' },
    { name: 'Favorites', to: '/favorites' },
    { name: 'Playlists', to: '/playlists' },
    { name: 'Now Playing', to: '/now-playing' },
  ]
})

async function handleLogout() {
  await authStore.logout()
  router.push('/login')
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 flex flex-col">
    <!-- Header -->
    <header class="bg-white shadow-sm sticky top-0 z-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
          <!-- App name + role badge -->
          <div class="flex items-center gap-3">
            <span class="text-xl font-bold text-gray-900">SmartPark</span>
            <span
              v-if="authStore.user"
              :class="['text-xs font-semibold px-2 py-1 rounded-full uppercase tracking-wide', roleBadgeClass]"
            >
              {{ authStore.user.role }}
            </span>
            <!-- Offline indicator -->
            <span
              v-if="uiStore.offline"
              class="text-xs font-semibold px-2 py-1 rounded-full uppercase tracking-wide bg-gray-800 text-white"
            >
              Offline
            </span>
          </div>

          <!-- Navigation links -->
          <nav class="hidden md:flex items-center gap-1">
            <RouterLink
              v-for="link in navLinks"
              :key="link.to"
              :to="link.to"
              class="min-h-[44px] px-3 flex items-center text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-md transition-colors"
              active-class="text-blue-600 bg-blue-50"
            >
              {{ link.name }}
            </RouterLink>
          </nav>

          <!-- Right actions -->
          <div class="flex items-center gap-2">
            <!-- Notifications bell -->
            <button
              class="min-w-[44px] min-h-[44px] flex items-center justify-center rounded-full hover:bg-gray-100 relative"
              aria-label="Notifications"
            >
              <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
              </svg>
              <span
                v-if="uiStore.notifications.length > 0"
                class="absolute top-1 right-1 w-4 h-4 bg-red-500 rounded-full text-white text-xs flex items-center justify-center"
              >
                {{ uiStore.notifications.length }}
              </span>
            </button>

            <!-- Logout -->
            <button
              @click="handleLogout"
              class="min-h-[44px] px-4 flex items-center text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-md transition-colors"
            >
              Logout
            </button>
          </div>
        </div>

        <!-- Mobile nav -->
        <nav class="md:hidden flex items-center gap-1 overflow-x-auto pb-2">
          <RouterLink
            v-for="link in navLinks"
            :key="link.to"
            :to="link.to"
            class="min-h-[44px] px-3 flex items-center text-sm font-medium text-gray-600 hover:text-gray-900 whitespace-nowrap"
            active-class="text-blue-600"
          >
            {{ link.name }}
          </RouterLink>
        </nav>
      </div>
    </header>

    <!-- Notification toasts -->
    <div class="fixed top-20 right-4 z-50 flex flex-col gap-2 max-w-sm w-full">
      <TransitionGroup name="notif">
        <div
          v-for="notif in uiStore.notifications"
          :key="notif.id"
          :class="[
            'px-4 py-3 rounded-lg shadow-lg text-sm font-medium flex items-start gap-3',
            notif.type === 'error' ? 'bg-red-600 text-white' :
            notif.type === 'success' ? 'bg-green-600 text-white' :
            notif.type === 'warning' ? 'bg-yellow-500 text-white' :
            'bg-blue-600 text-white'
          ]"
        >
          <span class="flex-1">{{ notif.message }}</span>
          <button
            @click="uiStore.removeNotification(notif.id)"
            class="min-w-[24px] min-h-[24px] flex items-center justify-center"
            aria-label="Dismiss"
          >
            ×
          </button>
        </div>
      </TransitionGroup>
    </div>

    <!-- Main content -->
    <main class="flex-1">
      <slot />
    </main>
  </div>
</template>

<style scoped>
.notif-enter-active,
.notif-leave-active {
  transition: all 0.3s ease;
}
.notif-enter-from,
.notif-leave-to {
  opacity: 0;
  transform: translateX(100%);
}
</style>
