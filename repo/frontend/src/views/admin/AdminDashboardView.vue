<template>
  <div>
    <h1 class="text-2xl font-bold text-surface-900 mb-6">Dashboard</h1>

    <div v-if="isLoading" class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div v-for="n in 8" :key="n" class="skeleton h-24 rounded-xl" />
    </div>

    <div v-else-if="stats" class="space-y-6">
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard label="Total Users" :value="stats.users.total" color="blue" />
        <StatCard label="Active Users" :value="stats.users.active" color="green" />
        <StatCard label="Frozen" :value="stats.users.frozen" color="yellow" />
        <StatCard label="Blacklisted" :value="stats.users.blacklisted" color="red" />
      </div>
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard label="Total Assets" :value="stats.assets.total" color="blue" />
        <StatCard label="Pending Review" :value="stats.assets.pending" color="yellow" />
        <StatCard label="Approved" :value="stats.assets.approved" color="green" />
        <StatCard label="Rejected" :value="stats.assets.rejected" color="red" />
      </div>
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard label="Total Plays" :value="stats.plays.total" color="purple" />
        <StatCard label="Plays (24h)" :value="stats.plays.last_24h" color="purple" />
        <StatCard label="Plays (7d)" :value="stats.plays.last_7d" color="purple" />
        <StatCard label="Active Devices" :value="stats.devices.active_last_24h" color="blue" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/api/axios'
import StatCard from '@/components/StatCard.vue'

const stats = ref(null)
const isLoading = ref(false)

onMounted(async () => {
  isLoading.value = true
  try {
    const response = await api.get('/admin/dashboard')
    stats.value = response.data.data.stats
  } finally {
    isLoading.value = false
  }
})
</script>
