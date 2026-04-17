<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import type { MonitoringStatus } from '@/types/api'
import { monitoringApi } from '@/services/api'
import { useUiStore } from '@/stores/ui'

const uiStore = useUiStore()
const status = ref<MonitoringStatus | null>(null)
const loading = ref(false)
const lastRefreshed = ref<Date | null>(null)
let refreshTimer: ReturnType<typeof setInterval> | null = null

async function fetchStatus() {
  loading.value = true
  try {
    status.value = await monitoringApi.status()
    lastRefreshed.value = new Date()
  } catch {
    uiStore.addNotification({ type: 'error', message: 'Failed to fetch monitoring status' })
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchStatus()
  refreshTimer = setInterval(fetchStatus, 10000)
})

onUnmounted(() => {
  if (refreshTimer) clearInterval(refreshTimer)
})

function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  if (bytes < 1024 * 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
  return `${(bytes / (1024 * 1024 * 1024)).toFixed(2)} GB`
}

async function handleResetFlag(flag: string) {
  try {
    await monitoringApi.resetFlag(flag)
    uiStore.addNotification({ type: 'success', message: `Flag "${flag}" reset` })
    await fetchStatus()
  } catch {
    uiStore.addNotification({ type: 'error', message: 'Failed to reset flag' })
  }
}
</script>

<template>
  <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Monitoring</h1>
      <div class="flex items-center gap-4">
        <span v-if="lastRefreshed" class="text-xs text-gray-400">
          Last: {{ lastRefreshed.toLocaleTimeString() }}
        </span>
        <button
          @click="fetchStatus"
          :disabled="loading"
          class="min-h-[44px] px-4 text-sm font-semibold text-blue-600 border border-blue-200 rounded-lg hover:bg-blue-50 disabled:opacity-50"
        >
          {{ loading ? 'Refreshing…' : 'Refresh' }}
        </button>
      </div>
    </div>

    <div v-if="!status" class="text-center py-16 text-gray-400">
      <div class="animate-spin w-8 h-8 border-2 border-blue-500 border-t-transparent rounded-full mx-auto mb-3" />
      Loading monitoring data…
    </div>

    <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-6">
      <!-- API Panel -->
      <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">API Health</h2>
        <div class="space-y-3">
          <div class="flex justify-between items-center">
            <span class="text-sm text-gray-600">P95 Latency (5m)</span>
            <span
              :class="[
                'text-sm font-bold',
                status.api.p95_ms_5m < 200 ? 'text-green-600' :
                status.api.p95_ms_5m < 500 ? 'text-yellow-600' : 'text-red-600'
              ]"
            >
              {{ status.api.p95_ms_5m.toFixed(0) }} ms
            </span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-sm text-gray-600">Error Rate (5m)</span>
            <span
              :class="[
                'text-sm font-bold',
                status.api.error_rate_5m < 0.01 ? 'text-green-600' :
                status.api.error_rate_5m < 0.05 ? 'text-yellow-600' : 'text-red-600'
              ]"
            >
              {{ (status.api.error_rate_5m * 100).toFixed(2) }}%
            </span>
          </div>
        </div>
      </div>

      <!-- Devices Panel -->
      <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Devices</h2>
        <div class="space-y-3">
          <div class="flex justify-between items-center">
            <span class="text-sm text-gray-600">Online</span>
            <span class="text-sm font-bold text-green-600">{{ status.devices.online }}</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-sm text-gray-600">Offline</span>
            <span class="text-sm font-bold text-red-600">{{ status.devices.offline }}</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-sm text-gray-600">Dedup Rate (1h)</span>
            <span class="text-sm font-bold text-gray-900">
              {{ (status.devices.dedup_rate_1h * 100).toFixed(1) }}%
            </span>
          </div>
        </div>
      </div>

      <!-- Storage Panel -->
      <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Storage</h2>
        <div class="space-y-3">
          <div class="flex justify-between items-center">
            <span class="text-sm text-gray-600">Free</span>
            <span class="text-sm font-bold text-gray-900">
              {{ formatBytes(status.storage.media_volume_free_bytes) }}
            </span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-sm text-gray-600">Used</span>
            <span
              :class="[
                'text-sm font-bold',
                status.storage.media_volume_used_pct < 70 ? 'text-green-600' :
                status.storage.media_volume_used_pct < 90 ? 'text-yellow-600' : 'text-red-600'
              ]"
            >
              {{ status.storage.media_volume_used_pct.toFixed(1) }}%
            </span>
          </div>
          <!-- Usage bar -->
          <div class="w-full h-2 bg-gray-200 rounded-full overflow-hidden">
            <div
              :class="[
                'h-full rounded-full transition-all',
                status.storage.media_volume_used_pct < 70 ? 'bg-green-500' :
                status.storage.media_volume_used_pct < 90 ? 'bg-yellow-500' : 'bg-red-500'
              ]"
              :style="{ width: `${status.storage.media_volume_used_pct}%` }"
            />
          </div>
        </div>
      </div>

      <!-- Queues Panel -->
      <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Queue Backlogs</h2>
        <div
          v-if="Object.keys(status.queues).length === 0"
          class="text-sm text-gray-400"
        >
          No queue data
        </div>
        <div v-else class="space-y-2">
          <div
            v-for="(count, queueName) in status.queues"
            :key="queueName"
            class="flex justify-between items-center"
          >
            <span class="text-sm text-gray-600 font-mono">{{ queueName }}</span>
            <span
              :class="[
                'text-sm font-bold',
                count === 0 ? 'text-green-600' :
                count < 100 ? 'text-yellow-600' : 'text-red-600'
              ]"
            >
              {{ count }}
            </span>
          </div>
        </div>
      </div>

      <!-- Feature Flags Panel (full width) -->
      <div class="bg-white rounded-2xl border border-gray-200 p-6 sm:col-span-2">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Feature Flags</h2>
        <div
          v-if="Object.keys(status.feature_flags).length === 0"
          class="text-sm text-gray-400"
        >
          No feature flags
        </div>
        <div v-else class="divide-y divide-gray-100">
          <div
            v-for="(flag, flagName) in status.feature_flags"
            :key="flagName"
            class="py-3 flex items-center justify-between gap-4"
          >
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-3">
                <span class="font-mono text-sm text-gray-900">{{ flagName }}</span>
                <span
                  :class="[
                    'text-xs font-semibold px-2 py-0.5 rounded-full',
                    flag.enabled ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'
                  ]"
                >
                  {{ flag.enabled ? 'Enabled' : 'Disabled' }}
                </span>
              </div>
              <div class="flex items-center gap-4 mt-1">
                <span v-if="flag.last_transition_at" class="text-xs text-gray-400">
                  Last changed: {{ new Date(flag.last_transition_at).toLocaleString() }}
                </span>
                <span v-if="flag.reason" class="text-xs text-gray-400">
                  Reason: {{ flag.reason }}
                </span>
              </div>
            </div>
            <button
              @click="handleResetFlag(flagName)"
              class="min-h-[36px] px-3 text-sm font-semibold text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 shrink-0"
            >
              Reset
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
