<script setup lang="ts">
import { useRouter } from 'vue-router'

const router = useRouter()

const sections = [
  { title: 'Users', description: 'Manage user accounts, roles, and access', to: '/admin/users', icon: '👥' },
  { title: 'Uploads', description: 'Upload and manage media assets', to: '/admin/uploads', icon: '📤' },
  { title: 'Monitoring', description: 'System health, queues, and feature flags', to: '/admin/monitoring', icon: '📊' },
  { title: 'Devices', description: 'Connected devices and event logs', to: '/devices', icon: '📡' },
]
</script>

<template>
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <h1 class="text-2xl font-bold text-gray-900 mb-8">Admin Console</h1>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <button
        v-for="section in sections"
        :key="section.to"
        @click="router.push(section.to)"
        class="bg-white rounded-2xl border border-gray-200 p-6 text-left hover:border-blue-400 hover:shadow-md transition-all min-h-[120px]"
      >
        <div class="text-3xl mb-3">{{ section.icon }}</div>
        <h2 class="text-lg font-bold text-gray-900">{{ section.title }}</h2>
        <p class="text-sm text-gray-500 mt-1">{{ section.description }}</p>
      </button>
    </div>
  </div>
</template>
