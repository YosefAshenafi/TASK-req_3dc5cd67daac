<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-surface-900">Device Event Console</h1>
      <div class="flex items-center gap-3">
        <span class="text-sm text-surface-400">Auto-refresh 10s</span>
        <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse" />
      </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <div class="card text-center" v-for="device in devices" :key="device.id">
        <p class="text-xs font-medium text-surface-500 truncate">{{ device.name }}</p>
        <p class="text-lg font-bold text-surface-900 mt-1">Seq {{ device.last_sequence }}</p>
        <p class="text-xs text-surface-400 mt-1 truncate">{{ device.device_type }}</p>
        <p class="text-xs text-surface-300 mt-1">{{ formatRelative(device.last_event_at) }}</p>
      </div>
    </div>

    <div class="flex items-center gap-3 mb-4">
      <select v-model="statusFilter" @change="loadEvents" class="input w-40 text-sm">
        <option value="">All statuses</option>
        <option value="received">Received</option>
        <option value="late">Late</option>
        <option value="buffered">Buffered</option>
        <option value="duplicate">Duplicate</option>
      </select>
    </div>

    <div v-if="isLoadingEvents" class="space-y-3">
      <div v-for="n in 5" :key="n" class="skeleton h-16 rounded-xl" />
    </div>

    <div v-else class="card p-0 overflow-hidden">
      <table class="min-w-full divide-y divide-surface-200">
        <thead class="bg-surface-50">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-surface-500 uppercase">Device</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-surface-500 uppercase">Event Type</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-surface-500 uppercase">Seq</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-surface-500 uppercase">Status</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-surface-500 uppercase">Received</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-surface-200">
          <tr v-if="events.length === 0">
            <td colspan="5" class="px-4 py-8 text-center text-sm text-surface-400">No events found</td>
          </tr>
          <tr v-for="event in events" :key="event.id" class="hover:bg-surface-50">
            <td class="px-4 py-3 text-sm text-surface-900">{{ event.device_name }}</td>
            <td class="px-4 py-3 text-sm text-surface-700 font-mono">{{ event.event_type }}</td>
            <td class="px-4 py-3 text-sm text-surface-700 font-mono">{{ event.sequence }}</td>
            <td class="px-4 py-3">
              <span class="badge text-xs" :class="statusBadge(event.status)">{{ event.status }}</span>
            </td>
            <td class="px-4 py-3 text-xs text-surface-400">{{ formatDate(event.received_at) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import api from '@/api/axios'

const devices = ref([])
const events = ref([])
const isLoadingEvents = ref(false)
const statusFilter = ref('')
let pollTimer = null

async function loadDevices() {
  try {
    const response = await api.get('/technician/devices')
    devices.value = response.data.data
  } catch {}
}

async function loadEvents() {
  isLoadingEvents.value = true
  try {
    const params = statusFilter.value ? `?status=${statusFilter.value}` : ''
    const response = await api.get(`/technician/events${params}`)
    events.value = response.data.data
  } finally {
    isLoadingEvents.value = false
  }
}

function statusBadge(status) {
  return {
    'bg-green-100 text-green-700': status === 'received',
    'bg-yellow-100 text-yellow-700': status === 'late',
    'bg-blue-100 text-blue-700': status === 'buffered',
    'bg-surface-100 text-surface-500': status === 'duplicate',
  }
}

function formatDate(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleString()
}

function formatRelative(iso) {
  if (!iso) return 'Never'
  const diff = Date.now() - new Date(iso).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 1) return 'Just now'
  if (mins < 60) return `${mins}m ago`
  return `${Math.floor(mins / 60)}h ago`
}

onMounted(() => {
  loadDevices()
  loadEvents()
  pollTimer = setInterval(() => { loadDevices(); loadEvents() }, 10000)
})

onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer)
})
</script>
