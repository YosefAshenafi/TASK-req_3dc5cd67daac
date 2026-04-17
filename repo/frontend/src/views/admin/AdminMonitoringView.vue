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
  <div class="p-6 max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Monitoring</h1>
        <p class="text-sm text-slate-500 mt-0.5">System health and performance</p>
      </div>
      <div class="flex items-center gap-3">
        <span v-if="lastRefreshed" class="text-xs text-slate-400">
          Updated {{ lastRefreshed.toLocaleTimeString() }}
        </span>
        <button
          @click="fetchStatus"
          :disabled="loading"
          class="flex items-center gap-2 px-4 py-2 text-sm font-semibold text-indigo-600 border border-indigo-200 bg-indigo-50 rounded-xl hover:bg-indigo-100 disabled:opacity-50 transition-colors"
        >
          <svg v-if="loading" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
          </svg>
          {{ loading ? 'Refreshing…' : 'Refresh' }}
        </button>
      </div>
    </div>

    <div v-if="!status" class="flex flex-col items-center justify-center py-24 text-center">
      <div class="w-8 h-8 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin mb-3" />
      <p class="text-sm text-slate-500">Loading monitoring data…</p>
    </div>

    <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <!-- API Panel -->
      <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-4">API Health</h2>
        <div class="space-y-3">
          <div class="flex justify-between items-center">
            <span class="text-sm text-slate-600">P95 Latency (5m)</span>
            <span :class="['text-sm font-bold', status.api.p95_ms_5m < 200 ? 'text-emerald-600' : status.api.p95_ms_5m < 500 ? 'text-amber-600' : 'text-red-600']">
              {{ status.api.p95_ms_5m.toFixed(0) }} ms
            </span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-sm text-slate-600">Error Rate (5m)</span>
            <span :class="['text-sm font-bold', status.api.error_rate_5m < 0.01 ? 'text-emerald-600' : status.api.error_rate_5m < 0.05 ? 'text-amber-600' : 'text-red-600']">
              {{ (status.api.error_rate_5m * 100).toFixed(2) }}%
            </span>
          </div>
        </div>
      </div>

      <!-- Devices Panel -->
      <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-4">Devices</h2>
        <div class="space-y-3">
          <div class="flex justify-between items-center">
            <span class="text-sm text-slate-600">Online</span>
            <span class="text-sm font-bold text-emerald-600">{{ status.devices.online }}</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-sm text-slate-600">Offline</span>
            <span class="text-sm font-bold text-red-500">{{ status.devices.offline }}</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-sm text-slate-600">Dedup Rate (1h)</span>
            <span class="text-sm font-bold text-slate-900">{{ (status.devices.dedup_rate_1h * 100).toFixed(1) }}%</span>
          </div>
        </div>
      </div>

      <!-- Storage Panel -->
      <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-4">Storage</h2>
        <div class="space-y-3">
          <div class="flex justify-between items-center">
            <span class="text-sm text-slate-600">Free</span>
            <span class="text-sm font-bold text-slate-900">{{ formatBytes(status.storage.media_volume_free_bytes) }}</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-sm text-slate-600">Used</span>
            <span :class="['text-sm font-bold', status.storage.media_volume_used_pct < 70 ? 'text-emerald-600' : status.storage.media_volume_used_pct < 90 ? 'text-amber-600' : 'text-red-600']">
              {{ status.storage.media_volume_used_pct.toFixed(1) }}%
            </span>
          </div>
          <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
            <div
              :class="['h-full rounded-full transition-all', status.storage.media_volume_used_pct < 70 ? 'bg-emerald-500' : status.storage.media_volume_used_pct < 90 ? 'bg-amber-500' : 'bg-red-500']"
              :style="{ width: `${status.storage.media_volume_used_pct}%` }"
            />
          </div>
        </div>
      </div>

      <!-- Queues Panel -->
      <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-4">Queue Backlogs</h2>
        <div v-if="Object.keys(status.queues).length === 0" class="text-sm text-slate-400">No queue data</div>
        <div v-else class="space-y-2">
          <div v-for="(count, queueName) in status.queues" :key="queueName" class="flex justify-between items-center">
            <span class="text-sm text-slate-600 font-mono">{{ queueName }}</span>
            <span :class="['text-sm font-bold', count === 0 ? 'text-emerald-600' : count < 100 ? 'text-amber-600' : 'text-red-600']">{{ count }}</span>
          </div>
        </div>
      </div>

      <!-- Feature Flags -->
      <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm sm:col-span-2">
        <h2 class="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-4">Feature Flags</h2>
        <div v-if="Object.keys(status.feature_flags).length === 0" class="text-sm text-slate-400">No feature flags</div>
        <div v-else class="divide-y divide-slate-100">
          <div v-for="(flag, flagName) in status.feature_flags" :key="flagName" class="py-3.5 flex items-center justify-between gap-4">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-3 flex-wrap">
                <span class="font-mono text-sm text-slate-900">{{ flagName }}</span>
                <span :class="['text-xs font-semibold px-2.5 py-1 rounded-full', flag.enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600']">
                  {{ flag.enabled ? 'Enabled' : 'Disabled' }}
                </span>
              </div>
              <div class="flex items-center gap-4 mt-1 flex-wrap">
                <span v-if="flag.last_transition_at" class="text-xs text-slate-400">Changed: {{ new Date(flag.last_transition_at).toLocaleString() }}</span>
                <span v-if="flag.reason" class="text-xs text-slate-400">Reason: {{ flag.reason }}</span>
              </div>
            </div>
            <button
              @click="handleResetFlag(flagName)"
              class="px-3 py-1.5 text-sm font-semibold text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors shrink-0"
            >
              Reset
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
