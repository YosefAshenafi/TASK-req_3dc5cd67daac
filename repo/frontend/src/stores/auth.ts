import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authApi, ApiError, getStoredToken } from '@/services/api'
import type { User } from '@/types/api'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const loading = ref(false)

  const isAuthenticated = computed(() => user.value !== null)
  const isAdmin = computed(() => user.value?.role === 'admin')
  const isTechnician = computed(() => user.value?.role === 'technician')
  const isUser = computed(() => user.value?.role === 'user')

  async function login(username: string, password: string): Promise<void> {
    loading.value = true
    try {
      const res = await authApi.login({ username, password })
      user.value = res.user
    } finally {
      loading.value = false
    }
  }

  async function logout(): Promise<void> {
    loading.value = true
    try {
      await authApi.logout()
    } catch {
      // ignore logout errors
    } finally {
      user.value = null
      loading.value = false
    }
  }

  async function fetchMe(): Promise<void> {
    if (!getStoredToken()) {
      user.value = null
      return
    }
    loading.value = true
    try {
      user.value = await authApi.me()
    } catch (err) {
      if (err instanceof ApiError && err.status === 401) {
        user.value = null
      } else {
        throw err
      }
    } finally {
      loading.value = false
    }
  }

  return {
    user,
    loading,
    isAuthenticated,
    isAdmin,
    isTechnician,
    isUser,
    login,
    logout,
    fetchMe,
  }
})
