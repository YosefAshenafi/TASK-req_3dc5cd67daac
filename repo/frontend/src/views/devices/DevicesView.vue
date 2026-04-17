<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import type { Device } from '@/types/api'
import { devicesApi } from '@/services/api'
import { useUiStore } from '@/stores/ui'
import { useRouter } from 'vue-router'

const uiStore = useUiStore()
const router = useRouter()

const devices = ref<Device[]>([])
const loading = ref(false)

onMounted(async () => {
  loading.value = true
  try {
    devices.value = await devicesApi.list()
  } catch {
    uiStore.addNotification({ type: 'error', message: 'Failed to load devices' })
  } finally {
    loading.value = false
  }
})

function isOnline(device: Device): boolean {
  if (!device.last_seen_at) return false
  const diff = Date.now() - new Date(device.last_seen_at).getTime()
  return diff < 5 * 60 * 1000 // online if seen in last 5 min
}

function formatLastSeen(dateStr?: string): string {
  if (!dateStr) return 'Never'
  return new Date(dateStr).toLocaleString()
}
</script>

<template>
  <div class="p-6 max-w-6xl mx-auto">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-slate-900">Devices</h1>
      <p class="text-sm text-slate-500 mt-0.5">Connected kiosks and gate devices</p>
    </div>

    <div v-if="loading" class="space-y-2">
      <div v-for="n in 5" :key="n" class="h-14 bg-white border border-slate-200 rounded-xl animate-pulse" />
    </div>

    <div v-else-if="devices.length === 0" class="flex flex-col items-center justify-center py-24 text-center">
      <div class="w-14 h-14 rounded-2xl bg-slate-100 flex items-center justify-center mb-4">
        <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
        </svg>
      </div>
      <h3 class="text-base font-semibold text-slate-700 mb-1">No devices registered</h3>
      <p class="text-sm text-slate-400">Connect a device to see it here</p>
    </div>

    <div v-else class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
      <table class="w-full">
        <thead>
          <tr class="border-b border-slate-200 bg-slate-50">
            <th class="px-5 py-3.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Device ID</th>
            <th class="px-5 py-3.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Kind</th>
            <th class="px-5 py-3.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Label</th>
            <th class="px-5 py-3.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Last Seq#</th>
            <th class="px-5 py-3.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Last Seen</th>
            <th class="px-5 py-3.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr
            v-for="device in devices"
            :key="device.id"
            @click="router.push(`/devices/${device.id}`)"
            class="hover:bg-slate-50/50 cursor-pointer transition-colors"
          >
            <td class="px-5 py-4">
              <span class="font-mono text-sm text-slate-900">{{ device.id }}</span>
            </td>
            <td class="px-5 py-4 text-sm text-slate-600 capitalize">{{ device.kind }}</td>
            <td class="px-5 py-4 text-sm text-slate-600">{{ device.label ?? '—' }}</td>
            <td class="px-5 py-4 text-sm font-mono text-slate-600">{{ device.last_sequence_no }}</td>
            <td class="px-5 py-4 text-sm text-slate-500">{{ formatLastSeen(device.last_seen_at) }}</td>
            <td class="px-5 py-4">
              <span :class="['inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full', isOnline(device) ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500']">
                <span :class="['w-1.5 h-1.5 rounded-full', isOnline(device) ? 'bg-emerald-500' : 'bg-slate-400']" />
                {{ isOnline(device) ? 'Online' : 'Offline' }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
