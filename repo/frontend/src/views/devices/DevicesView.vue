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
  <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Devices</h1>

    <div v-if="loading" class="space-y-3">
      <div v-for="n in 5" :key="n" class="h-16 bg-gray-200 rounded-xl animate-pulse" />
    </div>

    <div v-else-if="devices.length === 0" class="text-center py-16 text-gray-400">
      No devices registered.
    </div>

    <div v-else class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
      <table class="w-full">
        <thead class="bg-gray-50 border-b border-gray-200">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Device ID</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Kind</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Label</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Last Seq#</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Last Seen</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr
            v-for="device in devices"
            :key="device.id"
            @click="router.push(`/devices/${device.id}`)"
            class="hover:bg-gray-50 cursor-pointer"
          >
            <td class="px-4 py-3">
              <span class="font-mono text-sm text-gray-900">{{ device.id }}</span>
            </td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ device.kind }}</td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ device.label ?? '—' }}</td>
            <td class="px-4 py-3 text-sm font-mono text-gray-600">{{ device.last_sequence_no }}</td>
            <td class="px-4 py-3 text-sm text-gray-500">{{ formatLastSeen(device.last_seen_at) }}</td>
            <td class="px-4 py-3">
              <span
                :class="[
                  'text-xs font-semibold px-2 py-1 rounded-full',
                  isOnline(device) ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'
                ]"
              >
                {{ isOnline(device) ? 'Online' : 'Offline' }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
