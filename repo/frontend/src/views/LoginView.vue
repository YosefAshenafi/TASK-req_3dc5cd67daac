<script setup lang="ts">
import { ref, computed, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { ApiError, FrozenError } from '@/services/api'
import { Music, AlertCircle, Clock, ShieldAlert, Eye, EyeOff, Loader2 } from 'lucide-vue-next'

const authStore = useAuthStore()
const router = useRouter()
const route = useRoute()

const username = ref('')
const password = ref('')
const showPassword = ref(false)
const error = ref('')
const frozenUntil = ref<Date | null>(null)
const rateLimitRetryAfter = ref(0)
const isSubmitting = ref(false)

let countdownInterval: ReturnType<typeof setInterval> | null = null

const frozenMessage = computed(() => {
  if (!frozenUntil.value) return ''
  const now = new Date()
  const diff = Math.max(0, Math.floor((frozenUntil.value.getTime() - now.getTime()) / 1000))
  if (diff === 0) return 'Account frozen'
  const h = Math.floor(diff / 3600)
  const m = Math.floor((diff % 3600) / 60)
  const s = diff % 60
  return `Unlocks in ${h}h ${m}m ${s}s`
})

const rateLimitMessage = computed(() => {
  if (!rateLimitRetryAfter.value) return ''
  return `Try again in ${rateLimitRetryAfter.value}s`
})

const isDisabled = computed(() => isSubmitting.value || rateLimitRetryAfter.value > 0 || !!frozenUntil.value)

function startCountdown() {
  if (countdownInterval) clearInterval(countdownInterval)
  countdownInterval = setInterval(() => {
    if (rateLimitRetryAfter.value > 0) rateLimitRetryAfter.value -= 1
    if (frozenUntil.value) {
      const diff = Math.max(0, Math.floor((frozenUntil.value.getTime() - Date.now()) / 1000))
      if (diff === 0) frozenUntil.value = null
    }
    if (rateLimitRetryAfter.value === 0 && !frozenUntil.value) {
      if (countdownInterval) clearInterval(countdownInterval)
    }
  }, 1000)
}

onUnmounted(() => {
  if (countdownInterval) clearInterval(countdownInterval)
})

async function handleSubmit() {
  if (isSubmitting.value) return
  error.value = ''
  isSubmitting.value = true
  try {
    await authStore.login(username.value, password.value)
    const redirect = (route.query.redirect as string) || null
    router.push(redirect || (authStore.user ? getHomeForRole(authStore.user.role) : '/search'))
  } catch (err) {
    if (err instanceof FrozenError) {
      frozenUntil.value = err.body?.frozen_until
        ? new Date(err.body.frozen_until)
        : new Date(Date.now() + 72 * 3600 * 1000)
      startCountdown()
    } else if (err instanceof ApiError) {
      if (err.status === 429) {
        rateLimitRetryAfter.value = Number(err.body?.retry_after ?? 60)
        startCountdown()
      } else {
        error.value = err.body?.message ?? 'Invalid username or password'
      }
    } else {
      error.value = 'An unexpected error occurred'
    }
  } finally {
    isSubmitting.value = false
  }
}

function getHomeForRole(role: string): string {
  switch (role) {
    case 'admin': return '/admin'
    case 'technician': return '/devices'
    default: return '/search'
  }
}
</script>

<template>
  <div class="min-h-screen flex">
    <!-- Left panel - branding -->
    <div class="hidden lg:flex lg:flex-1 bg-gradient-to-br from-slate-950 via-indigo-950 to-violet-950 items-center justify-center p-12 relative overflow-hidden">
      <!-- Background decoration -->
      <div class="absolute inset-0 overflow-hidden">
        <div class="absolute -top-40 -left-40 w-80 h-80 bg-indigo-500/10 rounded-full blur-3xl" />
        <div class="absolute top-1/2 -right-20 w-96 h-96 bg-violet-500/10 rounded-full blur-3xl" />
        <div class="absolute -bottom-20 left-1/3 w-72 h-72 bg-indigo-600/10 rounded-full blur-3xl" />
      </div>

      <div class="relative text-center max-w-md">
        <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-indigo-500 to-violet-600 rounded-3xl mb-8 shadow-2xl shadow-indigo-500/40">
          <Music class="w-10 h-10 text-white" />
        </div>
        <h1 class="text-4xl font-bold text-white mb-4 tracking-tight">SmartPark</h1>
        <p class="text-lg text-slate-300 leading-relaxed">
          Media Operations Platform for intelligent parking management
        </p>
        <div class="mt-10 grid grid-cols-3 gap-6 text-center">
          <div>
            <p class="text-2xl font-bold text-white">Media</p>
            <p class="text-xs text-slate-400 mt-1">Management</p>
          </div>
          <div>
            <p class="text-2xl font-bold text-white">Device</p>
            <p class="text-xs text-slate-400 mt-1">Monitoring</p>
          </div>
          <div>
            <p class="text-2xl font-bold text-white">Smart</p>
            <p class="text-xs text-slate-400 mt-1">Analytics</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Right panel - login form -->
    <div class="flex-1 flex items-center justify-center p-6 bg-slate-50">
      <div class="w-full max-w-md">
        <!-- Mobile logo -->
        <div class="lg:hidden text-center mb-8">
          <div class="inline-flex items-center justify-center w-14 h-14 bg-gradient-to-br from-indigo-500 to-violet-600 rounded-2xl mb-3 shadow-lg shadow-indigo-500/30">
            <Music class="w-7 h-7 text-white" />
          </div>
          <h1 class="text-2xl font-bold text-slate-900">SmartPark</h1>
          <p class="text-slate-500 text-sm mt-1">Media Operations</p>
        </div>

        <!-- Card -->
        <div class="bg-white rounded-2xl shadow-xl shadow-slate-200/60 border border-slate-200/60 p-8">
          <div class="mb-7">
            <h2 class="text-xl font-bold text-slate-900">Welcome back</h2>
            <p class="text-sm text-slate-500 mt-1">Sign in to your account to continue</p>
          </div>

          <!-- Error states -->
          <div v-if="error" class="mb-5 flex items-start gap-3 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
            <AlertCircle class="w-4 h-4 mt-0.5 shrink-0" />
            <span>{{ error }}</span>
          </div>

          <div v-if="frozenUntil" class="mb-5 flex items-start gap-3 p-4 bg-orange-50 border border-orange-200 rounded-xl text-sm text-orange-700">
            <ShieldAlert class="w-4 h-4 mt-0.5 shrink-0" />
            <div>
              <p class="font-semibold">Account frozen</p>
              <p class="mt-0.5 text-orange-600">{{ frozenMessage }}</p>
            </div>
          </div>

          <div v-if="rateLimitRetryAfter > 0" class="mb-5 flex items-start gap-3 p-4 bg-amber-50 border border-amber-200 rounded-xl text-sm text-amber-700">
            <Clock class="w-4 h-4 mt-0.5 shrink-0" />
            <div>
              <p class="font-semibold">Too many attempts</p>
              <p class="mt-0.5 text-amber-600">{{ rateLimitMessage }}</p>
            </div>
          </div>

          <!-- Form -->
          <form @submit.prevent="handleSubmit" class="space-y-5">
            <div>
              <label for="username" class="block text-sm font-medium text-slate-700 mb-1.5">
                Username
              </label>
              <input
                id="username"
                v-model="username"
                type="text"
                autocomplete="username"
                required
                :disabled="isDisabled"
                class="w-full px-4 py-3 text-sm border border-slate-300 rounded-xl bg-white text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 disabled:opacity-50 disabled:bg-slate-50 transition-shadow"
                placeholder="Enter your username"
              />
            </div>

            <div>
              <label for="password" class="block text-sm font-medium text-slate-700 mb-1.5">
                Password
              </label>
              <div class="relative">
                <input
                  id="password"
                  v-model="password"
                  :type="showPassword ? 'text' : 'password'"
                  autocomplete="current-password"
                  required
                  :disabled="isDisabled"
                  class="w-full px-4 py-3 pr-12 text-sm border border-slate-300 rounded-xl bg-white text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 disabled:opacity-50 disabled:bg-slate-50 transition-shadow"
                  placeholder="Enter your password"
                />
                <button
                  type="button"
                  @click="showPassword = !showPassword"
                  class="absolute right-3 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-slate-600 transition-colors"
                  :aria-label="showPassword ? 'Hide password' : 'Show password'"
                >
                  <EyeOff v-if="showPassword" class="w-4 h-4" />
                  <Eye v-else class="w-4 h-4" />
                </button>
              </div>
            </div>

            <button
              type="submit"
              :disabled="isDisabled"
              class="w-full py-3 px-4 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-semibold rounded-xl text-sm transition-all duration-150 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 shadow-md shadow-indigo-600/20"
            >
              <Loader2 v-if="isSubmitting" class="w-4 h-4 animate-spin" />
              {{ isSubmitting ? 'Signing in…' : 'Sign In' }}
            </button>
          </form>
        </div>

        <p class="text-center text-xs text-slate-400 mt-6">
          SmartPark Media Operations &copy; {{ new Date().getFullYear() }}
        </p>
      </div>
    </div>
  </div>
</template>
