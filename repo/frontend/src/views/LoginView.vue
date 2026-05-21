<template>
  <div class="min-h-screen bg-gradient-to-br from-brand-900 to-surface-900 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-brand-500 rounded-2xl mb-4">
          <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
          </svg>
        </div>
        <h1 class="text-2xl font-bold text-white">SmartPark Media</h1>
        <p class="text-surface-400 mt-1">Operations Management System</p>
      </div>

      <div class="card">
        <h2 class="text-lg font-semibold text-surface-900 mb-6">Sign in to your account</h2>

        <form @submit.prevent="handleLogin" class="space-y-4" novalidate>
          <div>
            <label for="username" class="block text-sm font-medium text-surface-700 mb-1">
              Username
            </label>
            <input
              id="username"
              v-model="form.username"
              type="text"
              autocomplete="username"
              required
              class="input"
              :class="{ 'border-red-500': errors.username }"
              placeholder="your_username"
            />
            <p v-if="errors.username" class="mt-1 text-sm text-red-600" role="alert">{{ errors.username }}</p>
          </div>

          <div>
            <label for="password" class="block text-sm font-medium text-surface-700 mb-1">
              Password
            </label>
            <input
              id="password"
              v-model="form.password"
              type="password"
              autocomplete="current-password"
              required
              class="input"
              :class="{ 'border-red-500': errors.password }"
              placeholder="••••••••"
            />
            <p v-if="errors.password" class="mt-1 text-sm text-red-600" role="alert">{{ errors.password }}</p>
          </div>

          <div v-if="loginError" class="p-3 bg-red-50 border border-red-200 rounded text-sm text-red-700" role="alert">
            {{ loginError }}
          </div>

          <button
            type="submit"
            class="btn-primary w-full"
            :disabled="isSubmitting"
          >
            <span v-if="isSubmitting" class="mr-2">
              <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
            </span>
            {{ isSubmitting ? 'Signing in...' : 'Sign in' }}
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

const form = reactive({ username: '', password: '' })
const errors = reactive({ username: '', password: '' })
const loginError = ref('')
const isSubmitting = ref(false)

function validate() {
  errors.username = ''
  errors.password = ''
  let valid = true
  if (!form.username) { errors.username = 'Username is required'; valid = false }
  if (!form.password) { errors.password = 'Password is required'; valid = false }
  return valid
}

async function handleLogin() {
  loginError.value = ''
  if (!validate()) return

  isSubmitting.value = true
  try {
    await auth.login(form.username, form.password)
    const redirect = route.query.redirect || '/'
    router.push(redirect)
  } catch (err) {
    loginError.value = err.response?.data?.message || 'Login failed. Please try again.'
  } finally {
    isSubmitting.value = false
  }
}
</script>
