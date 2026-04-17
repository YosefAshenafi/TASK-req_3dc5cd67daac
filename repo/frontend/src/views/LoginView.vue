<script setup lang="ts">
import { ref, computed, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { ApiError, FrozenError } from '@/services/api'

const authStore = useAuthStore()
const router = useRouter()
const route = useRoute()

const username = ref('')
const password = ref('')
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
  return `Account frozen. Unlocks in ${h}h ${m}m ${s}s`
})

const rateLimitMessage = computed(() => {
  if (!rateLimitRetryAfter.value) return ''
  return `Too many attempts. Try again in ${rateLimitRetryAfter.value}s`
})

function startCountdown() {
  if (countdownInterval) clearInterval(countdownInterval)
  countdownInterval = setInterval(() => {
    if (rateLimitRetryAfter.value > 0) {
      rateLimitRetryAfter.value -= 1
    }
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
      frozenUntil.value = err.body?.frozen_until ? new Date(err.body.frozen_until) : new Date(Date.now() + 72 * 3600 * 1000)
      startCountdown()
    } else if (err instanceof ApiError) {
      if (err.status === 429) {
        const retryAfter = Number(err.body?.retry_after ?? 60)
        rateLimitRetryAfter.value = retryAfter
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
  <div class="min-h-screen bg-gray-100 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-lg w-full max-w-md p-8">
      <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-gray-900">SmartPark</h1>
        <p class="text-gray-500 mt-1">Media Operations</p>
      </div>

      <!-- Error banner -->
      <div
        v-if="error"
        class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm"
        role="alert"
      >
        {{ error }}
      </div>

      <!-- Frozen banner -->
      <div
        v-if="frozenUntil"
        class="mb-4 p-4 bg-orange-50 border border-orange-200 rounded-lg text-orange-700 text-sm"
        role="alert"
      >
        {{ frozenMessage }}
      </div>

      <!-- Rate limit banner -->
      <div
        v-if="rateLimitRetryAfter > 0"
        class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg text-yellow-700 text-sm"
        role="alert"
      >
        {{ rateLimitMessage }}
      </div>

      <form @submit.prevent="handleSubmit" class="flex flex-col gap-5">
        <div>
          <label for="username" class="block text-sm font-medium text-gray-700 mb-1">
            Username
          </label>
          <input
            id="username"
            v-model="username"
            type="text"
            autocomplete="username"
            required
            class="w-full min-h-[48px] px-4 py-3 text-base border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            placeholder="Enter your username"
          />
        </div>

        <div>
          <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
            Password
          </label>
          <input
            id="password"
            v-model="password"
            type="password"
            autocomplete="current-password"
            required
            class="w-full min-h-[48px] px-4 py-3 text-base border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            placeholder="Enter your password"
          />
        </div>

        <button
          type="submit"
          :disabled="isSubmitting || rateLimitRetryAfter > 0 || !!frozenUntil"
          class="w-full min-h-[48px] bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-base"
        >
          {{ isSubmitting ? 'Signing in…' : 'Sign In' }}
        </button>
      </form>
    </div>
  </div>
</template>
