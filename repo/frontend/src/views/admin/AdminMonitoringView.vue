<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-xl font-bold text-surface-900">System Monitoring</h2>
      <button @click="load" :disabled="isLoading" class="btn-secondary text-sm">
        {{ isLoading ? 'Refreshing...' : 'Refresh' }}
      </button>
    </div>

    <div v-if="isLoading && !data" class="grid grid-cols-2 gap-4">
      <div v-for="n in 4" :key="n" class="skeleton h-32 rounded-xl" />
    </div>

    <div v-else-if="data" class="space-y-6">
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="card text-center">
          <p class="text-xs font-medium text-surface-500 mb-2">Database</p>
          <span class="badge text-sm px-3 py-1" :class="data.health.database === 'healthy' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">
            {{ data.health.database }}
          </span>
        </div>
        <div class="card text-center">
          <p class="text-xs font-medium text-surface-500 mb-2">Queue</p>
          <span class="badge text-sm px-3 py-1" :class="data.health.queue === 'healthy' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'">
            {{ data.health.queue }}
          </span>
        </div>
        <div class="card text-center">
          <p class="text-xs font-medium text-surface-500 mb-2">Rec Engine</p>
          <span class="badge text-sm px-3 py-1" :class="data.recommendations.engine_status === 'active' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'">
            {{ data.recommendations.engine_status }}
          </span>
        </div>
        <div class="card text-center">
          <p class="text-xs font-medium text-surface-500 mb-2">P95 Latency</p>
          <p class="text-xl font-bold text-surface-700">
            {{ data.recommendations.p95_latency_ms ? data.recommendations.p95_latency_ms.toFixed(1) + 'ms' : 'N/A' }}
          </p>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div class="card">
          <h3 class="text-sm font-semibold text-surface-700 mb-3">Queue Status</h3>
          <dl class="space-y-2">
            <div class="flex justify-between text-sm">
              <dt class="text-surface-500">Pending Jobs</dt>
              <dd class="font-medium text-surface-900">{{ data.queue.pending_jobs }}</dd>
            </div>
            <div class="flex justify-between text-sm">
              <dt class="text-surface-500">Failed Jobs</dt>
              <dd class="font-medium" :class="data.queue.failed_jobs > 0 ? 'text-red-600' : 'text-surface-900'">{{ data.queue.failed_jobs }}</dd>
            </div>
          </dl>
        </div>
        <div class="card">
          <h3 class="text-sm font-semibold text-surface-700 mb-2">Last Updated</h3>
          <p class="text-sm text-surface-500">{{ new Date(data.timestamp).toLocaleString() }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/api/axios'

const data = ref(null)
const isLoading = ref(false)

async function load() {
  isLoading.value = true
  try {
    const response = await api.get('/admin/monitoring')
    data.value = response.data.data
  } finally {
    isLoading.value = false
  }
}

onMounted(load)
</script>
