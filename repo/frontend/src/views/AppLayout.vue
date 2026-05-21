<template>
  <div class="min-h-screen bg-surface-50 flex flex-col">
    <header class="bg-white border-b border-surface-200 sticky top-0 z-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
          <div class="flex items-center gap-8">
            <RouterLink to="/" class="flex items-center gap-2 font-bold text-brand-700">
              <svg class="w-6 h-6 text-brand-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
              </svg>
              SmartPark
            </RouterLink>
            <nav class="hidden md:flex items-center gap-1">
              <RouterLink to="/library" class="nav-link">Library</RouterLink>
              <RouterLink to="/favorites" class="nav-link">Favorites</RouterLink>
              <RouterLink to="/playlists" class="nav-link">Playlists</RouterLink>
              <RouterLink v-if="auth.isAdmin" to="/admin" class="nav-link">Admin</RouterLink>
              <RouterLink v-if="auth.isTechnician" to="/technician" class="nav-link">Console</RouterLink>
            </nav>
          </div>
          <div class="flex items-center gap-3">
            <span class="text-sm text-surface-600 hidden sm:block">
              {{ auth.user?.name }}
              <span class="ml-1 badge" :class="roleBadgeClass">{{ auth.user?.role }}</span>
            </span>
            <button @click="handleLogout" class="btn-secondary text-sm">Sign out</button>
          </div>
        </div>
      </div>
    </header>

    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <RouterView />
    </main>

    <NowPlayingPanel />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { RouterLink, RouterView, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import NowPlayingPanel from '@/components/NowPlayingPanel.vue'

const auth = useAuthStore()
const router = useRouter()

const roleBadgeClass = computed(() => ({
  'bg-purple-100 text-purple-700': auth.user?.role === 'admin',
  'bg-green-100 text-green-700': auth.user?.role === 'technician',
  'bg-blue-100 text-blue-700': auth.user?.role === 'user',
}))

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}
</script>

<style scoped>
.nav-link {
  @apply px-3 py-2 rounded text-sm font-medium text-surface-600 hover:text-surface-900 hover:bg-surface-100 transition-colors;
}
.nav-link.router-link-active {
  @apply text-brand-600 bg-brand-50;
}
</style>
