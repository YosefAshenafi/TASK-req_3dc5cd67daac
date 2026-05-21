import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/api/axios'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const isLoading = ref(false)
  const error = ref(null)

  const isAdmin = computed(() => user.value?.role === 'admin')
  const isTechnician = computed(() => user.value?.role === 'technician')
  const isAuthenticated = computed(() => user.value !== null)

  async function login(username, password) {
    isLoading.value = true
    error.value = null
    try {
      const response = await api.post('/auth/login', { username, password })
      user.value = response.data.user
      return response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Login failed'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function logout() {
    try {
      await api.post('/auth/logout')
    } finally {
      user.value = null
    }
  }

  async function fetchMe() {
    try {
      const response = await api.get('/auth/me')
      user.value = response.data.user
    } catch {
      user.value = null
    }
  }

  return { user, isLoading, error, isAdmin, isTechnician, isAuthenticated, login, logout, fetchMe }
})
