<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import type { MonitoringStatus } from '@/types/api'
import { monitoringApi } from '@/services/api'
import { useUiStore } from '@/stores/ui'
import { BarChart2, RefreshCw } from 'lucide-vue-next'

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
  <div class="min-h-full">
    <!-- Dark page header -->
    <div class="bg-gray-900 border-b border-gray-800 px-6 py-8">
      <div class="max-w-6xl mx-auto">
        <div class="flex items-center justify-between">
          <div>
            <div class="flex items-center gap-3 mb-1">
              <BarChart2 class="w-5 h-5 text-emerald-400" />
              <span class="text-xs font-semibold text-emerald-400 uppercase tracking-widest">System</span>
            </div>
            <h1 class="text-2xl font-bold text-white">Monitoring</h1>
            <p class="text-sm text-gray-400 mt-1">System health and performance</p>
          </div>
          <div class="flex items-center gap-3">
            <span v-if="lastRefreshed" class="text-xs text-gray-500">
              Updated {{ lastRefreshed.toLocaleTimeString() }}
            </span>
            <button
              @click="fetchStatus"
              :disabled="loading"
              class="flex items-center gap-2 px-4 py-2 text-sm font-semibold text-sky-400 border border-gray-700 bg-gray-800 rounded-lg hover:bg-gray-700 disabled:opacity-50 transition-colors"
            >
              <RefreshCw :class="['w-3.5 h-3.5', loading && 'animate-spin']" />
              {{ loading ? 'Refreshing…' : 'Refresh' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Content -->
    <div class="max-w-6xl mx-auto px-6 py-8">
      <div v-if="!status" class="flex flex-col items-center justify-center py-24 text-center">
        <div class="w-8 h-8 border-2 border-sky-500 border-t-transparent rounded-full animate-spin mb-3" />
        <p class="text-sm text-gray-500">Loading monitoring data…</p>
      </div>

      <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <!-- API Panel -->
        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
          <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">API Health</h2>
          <div class="space-y-3">
            <div class="flex justify-between items-center">
              <span class="text-sm text-gray-600">P95 Latency (5m)</span>
              <span :class="['text-sm font-bold', status.api.p95_ms_5m < 200 ? 'text-emerald-600' : status.api.p95_ms_5m < 500 ? 'text-amber-600' : 'text-red-600']">
                {{ status.api.p95_ms_5m.toFixed(0) }} ms
              </span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-gray-600">Error Rate (5m)</span>
              <span :class="['text-sm font-bold', status.api.error_rate_5m < 0.01 ? 'text-emerald-600' : status.api.error_rate_5m < 0.05 ? 'text-amber-600' : 'text-red-600']">
                {{ (status.api.error_rate_5m * 100).toFixed(2) }}%
              </span>
            </div>
          </div>
        </div>

        <!-- Devices Panel -->
        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
          <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">Devices</h2>
          <div class="space-y-3">
            <div class="flex justify-between items-center">
              <span class="text-sm text-gray-600">Online</span>
              <span class="text-sm font-bold text-emerald-600">{{ status.devices.online }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-gray-600">Offline</span>
              <span class="text-sm font-bold text-red-500">{{ status.devices.offline }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-gray-600">Dedup Rate (1h)</span>
              <span class="text-sm font-bold text-gray-900">{{ (status.devices.dedup_rate_1h * 100).toFixed(1) }}%</span>
            </div>
          </div>
        </div>

        <!-- Storage Panel -->
        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
          <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">Storage</h2>
          <div class="space-y-3">
            <div class="flex justify-between items-center">
              <span class="text-sm text-gray-600">Free</span>
              <span class="text-sm font-bold text-gray-900">{{ formatBytes(status.storage.media_volume_free_bytes) }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-gray-600">Used</span>
              <span :class="['text-sm font-bold', status.storage.media_volume_used_pct < 70 ? 'text-emerald-600' : status.storage.media_volume_used_pct < 90 ? 'text-amber-600' : 'text-red-600']">
                {{ status.storage.media_volume_used_pct.toFixed(1) }}%
              </span>
            </div>
            <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
              <div
                :class="['h-full rounded-full transition-all', status.storage.media_volume_used_pct < 70 ? 'bg-emerald-500' : status.storage.media_volume_used_pct < 90 ? 'bg-amber-500' : 'bg-red-500']"
                :style="{ width: `${status.storage.media_volume_used_pct}%` }"
              />
            </div>
          </div>
        </div>

        <!-- Queues Panel -->
        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
          <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">Queue Backlogs</h2>
          <div v-if="Object.keys(status.queues).length === 0" class="text-sm text-gray-400">No queue data</div>
          <div v-else class="space-y-2">
            <div v-for="(count, queueName) in status.queues" :key="queueName" class="flex justify-between items-center">
              <span class="text-sm text-gray-600 font-mono">{{ queueName }}</span>
              <span :class="['text-sm font-bold', count === 0 ? 'text-emerald-600' : count < 100 ? 'text-amber-600' : 'text-red-600']">{{ count }}</span>
            </div>
          </div>
        </div>

        <!-- Content Usage Panel -->
        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm sm:col-span-2">
          <div class="flex items-end justify-between mb-4">
            <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Content Usage</h2>
            <span class="text-xs text-gray-400">last {{ status.content_usage?.window_hours ?? 24 }}h</span>
          </div>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
            <div>
              <p class="text-xs text-gray-500 uppercase tracking-wide">Plays</p>
              <p class="text-xl font-bold text-gray-900 mt-0.5">{{ status.content_usage?.plays_24h ?? 0 }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 uppercase tracking-wide">Active users</p>
              <p class="text-xl font-bold text-gray-900 mt-0.5">{{ status.content_usage?.active_users_24h ?? 0 }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 uppercase tracking-wide">Ready assets</p>
              <p class="text-xl font-bold text-gray-900 mt-0.5">{{ status.content_usage?.total_ready_assets ?? 0 }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 uppercase tracking-wide">Playlists</p>
              <p class="text-xl font-bold text-gray-900 mt-0.5">{{ status.content_usage?.playlists_count ?? 0 }}</p>
            </div>
          </div>

          <div>
            <p class="text-xs text-gray-500 uppercase tracking-wide mb-2">Top played</p>
            <div v-if="!status.content_usage || status.content_usage.top_assets.length === 0" class="text-sm text-gray-400">
              No plays in the last {{ status.content_usage?.window_hours ?? 24 }} hours.
            </div>
            <ol v-else class="space-y-1.5">
              <li
                v-for="(row, idx) in status.content_usage.top_assets"
                :key="row.asset_id"
                class="flex items-center gap-3 text-sm"
              >
                <span class="w-5 text-xs text-gray-400 text-right font-semibold">{{ idx + 1 }}.</span>
                <span class="flex-1 truncate text-gray-800">{{ row.title ?? `Asset #${row.asset_id}` }}</span>
                <span class="text-xs text-gray-400">{{ row.mime ?? '' }}</span>
                <span class="text-sm font-semibold text-sky-600 w-10 text-right">{{ row.play_count }}</span>
              </li>
            </ol>
          </div>
        </div>

        <!-- Feature Flags -->
        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm sm:col-span-2">
          <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">Feature Flags</h2>
          <div v-if="Object.keys(status.feature_flags).length === 0" class="text-sm text-gray-400">No feature flags</div>
          <div v-else class="divide-y divide-gray-100">
            <div v-for="(flag, flagName) in status.feature_flags" :key="flagName" class="py-3.5 flex items-center justify-between gap-4">
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-3 flex-wrap">
                  <span class="font-mono text-sm text-gray-900">{{ flagName }}</span>
                  <span :class="['text-xs font-semibold px-2.5 py-1 rounded-full', flag.enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600']">
                    {{ flag.enabled ? 'Enabled' : 'Disabled' }}
                  </span>
                </div>
                <div class="flex items-center gap-4 mt-1 flex-wrap">
                  <span v-if="flag.last_transition_at" class="text-xs text-gray-400">Changed: {{ new Date(flag.last_transition_at).toLocaleString() }}</span>
                  <span v-if="flag.reason" class="text-xs text-gray-400">Reason: {{ flag.reason }}</span>
                </div>
              </div>
              <button
                @click="handleResetFlag(flagName)"
                class="px-3 py-1.5 text-sm font-semibold text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors shrink-0"
              >
                Reset
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
