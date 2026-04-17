<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import {
  Search, Library, Heart, ListMusic, Music, Settings,
  Users, Upload, BarChart2, Cpu, LogOut, Bell, Menu, X,
  WifiOff, CheckCircle2, AlertCircle, AlertTriangle, Info
} from 'lucide-vue-next'

const authStore = useAuthStore()
const uiStore = useUiStore()
const router = useRouter()
const sidebarOpen = ref(false)

const navLinks = computed(() => {
  const role = authStore.user?.role
  if (role === 'admin') {
    return [
      { name: 'Dashboard', to: '/admin', icon: BarChart2 },
      { name: 'Users', to: '/admin/users', icon: Users },
      { name: 'Uploads', to: '/admin/uploads', icon: Upload },
      { name: 'Monitoring', to: '/admin/monitoring', icon: Cpu },
      { name: 'Devices', to: '/devices', icon: Settings },
    ]
  }
  if (role === 'technician') {
    return [{ name: 'Devices', to: '/devices', icon: Cpu }]
  }
  return [
    { name: 'Search', to: '/search', icon: Search },
    { name: 'Library', to: '/library', icon: Library },
    { name: 'Favorites', to: '/favorites', icon: Heart },
    { name: 'Playlists', to: '/playlists', icon: ListMusic },
    { name: 'Now Playing', to: '/now-playing', icon: Music },
  ]
})

const userInitial = computed(() => {
  const name = authStore.user?.username || ''
  return name.charAt(0).toUpperCase() || 'U'
})

const roleBadgeStyle = computed(() => {
  switch (authStore.user?.role) {
    case 'admin': return 'bg-red-500/20 text-red-300 border-red-500/30'
    case 'technician': return 'bg-amber-500/20 text-amber-300 border-amber-500/30'
    default: return 'bg-indigo-500/20 text-indigo-300 border-indigo-500/30'
  }
})

const notifIconMap = {
  success: CheckCircle2,
  error: AlertCircle,
  warning: AlertTriangle,
  info: Info,
}

const notifStyleMap = {
  success: 'bg-emerald-50 border-emerald-200 text-emerald-800',
  error: 'bg-red-50 border-red-200 text-red-800',
  warning: 'bg-amber-50 border-amber-200 text-amber-800',
  info: 'bg-blue-50 border-blue-200 text-blue-800',
}

async function handleLogout() {
  await authStore.logout()
  router.push('/login')
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 flex">
    <!-- Mobile overlay -->
    <Transition name="fade">
      <div
        v-if="sidebarOpen"
        class="fixed inset-0 bg-black/60 z-40 lg:hidden backdrop-blur-sm"
        @click="sidebarOpen = false"
      />
    </Transition>

    <!-- Sidebar -->
    <aside
      :class="[
        'fixed inset-y-0 left-0 z-50 w-64 bg-slate-950 flex flex-col',
        'transition-transform duration-300 ease-in-out',
        'lg:static lg:translate-x-0 lg:z-auto',
        sidebarOpen ? 'translate-x-0' : '-translate-x-full'
      ]"
    >
      <!-- Brand -->
      <div class="flex items-center justify-between px-5 py-4 border-b border-slate-800">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 bg-gradient-to-br from-indigo-500 to-violet-600 rounded-lg flex items-center justify-center shadow-lg shadow-indigo-500/30">
            <Music class="w-4 h-4 text-white" />
          </div>
          <span class="text-base font-bold text-white tracking-tight">SmartPark</span>
        </div>
        <button
          @click="sidebarOpen = false"
          class="lg:hidden p-1.5 rounded-md text-slate-500 hover:text-white hover:bg-slate-800 transition-colors"
        >
          <X class="w-4 h-4" />
        </button>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto">
        <RouterLink
          v-for="link in navLinks"
          :key="link.to"
          :to="link.to"
          @click="sidebarOpen = false"
          class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800/80 transition-all duration-150 text-sm font-medium group"
          active-class="!text-white !bg-indigo-600 shadow-sm shadow-indigo-500/30"
        >
          <component :is="link.icon" class="w-4 h-4 shrink-0 transition-transform group-hover:scale-110" />
          {{ link.name }}
        </RouterLink>
      </nav>

      <!-- User profile -->
      <div class="px-3 pb-4 pt-2 border-t border-slate-800 space-y-1">
        <div v-if="authStore.user" class="flex items-center gap-3 px-3 py-2 rounded-lg">
          <div class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-white text-xs font-bold shrink-0">
            {{ userInitial }}
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-white text-sm font-medium truncate leading-tight">
              {{ authStore.user.username }}
            </p>
            <span :class="['text-xs px-1.5 py-0.5 rounded border font-medium capitalize', roleBadgeStyle]">
              {{ authStore.user.role }}
            </span>
          </div>
        </div>

        <!-- Offline indicator -->
        <div
          v-if="uiStore.offline"
          class="flex items-center gap-2 px-3 py-2 rounded-lg bg-amber-500/10 text-amber-400 text-xs font-medium"
        >
          <WifiOff class="w-3.5 h-3.5" />
          Offline mode
        </div>

        <button
          @click="handleLogout"
          class="flex items-center gap-3 w-full px-3 py-2.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800/80 transition-all duration-150 text-sm font-medium group"
        >
          <LogOut class="w-4 h-4 shrink-0 transition-transform group-hover:scale-110" />
          Sign out
        </button>
      </div>
    </aside>

    <!-- Main content -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
      <!-- Top bar -->
      <header class="bg-white/90 backdrop-blur-sm border-b border-slate-200 sticky top-0 z-30">
        <div class="flex items-center gap-3 px-4 sm:px-6 h-14">
          <!-- Mobile menu -->
          <button
            @click="sidebarOpen = true"
            class="lg:hidden p-2 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-900 transition-colors"
          >
            <Menu class="w-5 h-5" />
          </button>

          <div class="flex-1" />

          <!-- Notification bell -->
          <button
            class="relative p-2 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-900 transition-colors"
            aria-label="Notifications"
          >
            <Bell class="w-5 h-5" />
            <span
              v-if="uiStore.notifications.length > 0"
              class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full ring-2 ring-white"
            />
          </button>
        </div>
      </header>

      <!-- Toast notifications -->
      <div class="fixed top-16 right-4 z-50 flex flex-col gap-2 w-full max-w-sm pointer-events-none">
        <TransitionGroup name="notif">
          <div
            v-for="notif in uiStore.notifications"
            :key="notif.id"
            :class="[
              'pointer-events-auto flex items-start gap-3 px-4 py-3 rounded-xl border shadow-lg text-sm font-medium',
              notifStyleMap[notif.type as keyof typeof notifStyleMap] || notifStyleMap.info
            ]"
          >
            <component
              :is="notifIconMap[notif.type as keyof typeof notifIconMap] || Info"
              class="w-4 h-4 mt-0.5 shrink-0"
            />
            <span class="flex-1 leading-snug">{{ notif.message }}</span>
            <button
              @click="uiStore.removeNotification(notif.id)"
              class="shrink-0 opacity-60 hover:opacity-100 transition-opacity ml-1 text-base leading-none"
              aria-label="Dismiss"
            >×</button>
          </div>
        </TransitionGroup>
      </div>

      <!-- Page content -->
      <main class="flex-1 overflow-auto">
        <slot />
      </main>
    </div>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.notif-enter-active,
.notif-leave-active {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.notif-enter-from,
.notif-leave-to {
  opacity: 0;
  transform: translateX(calc(100% + 1rem));
}
</style>
