<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { getRoleHome } from '@/router/index'

const router = useRouter()
const authStore = useAuthStore()

function goHome() {
  const role = authStore.user?.role ?? 'user'
  router.push(getRoleHome(role))
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
    <div class="text-center">
      <p class="text-8xl font-bold text-gray-200">403</p>
      <h1 class="text-2xl font-bold text-gray-900 mt-4">Access Forbidden</h1>
      <p class="text-gray-500 mt-2">You don't have permission to access this page.</p>
      <button
        @click="goHome"
        class="mt-6 min-h-[44px] px-6 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors"
      >
        Go to Home
      </button>
    </div>
  </div>
</template>
