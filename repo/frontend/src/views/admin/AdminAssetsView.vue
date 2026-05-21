<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-xl font-bold text-surface-900">Asset Review</h2>
      <select v-model="statusFilter" @change="load" class="input w-40 text-sm">
        <option value="">All</option>
        <option value="pending">Pending</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
      </select>
    </div>

    <div v-if="isLoading" class="space-y-3">
      <div v-for="n in 5" :key="n" class="skeleton h-16 rounded-xl" />
    </div>

    <div v-else-if="assets.length === 0" class="card text-center py-12">
      <p class="text-surface-500">No assets found</p>
    </div>

    <div v-else class="card p-0 overflow-hidden">
      <table class="min-w-full divide-y divide-surface-200">
        <thead class="bg-surface-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-surface-500 uppercase tracking-wider">Title</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-surface-500 uppercase tracking-wider">Type</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-surface-500 uppercase tracking-wider">Status</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-surface-500 uppercase tracking-wider">Uploaded</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-surface-500 uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-surface-200">
          <tr v-for="asset in assets" :key="asset.id" class="hover:bg-surface-50">
            <td class="px-6 py-4 text-sm font-medium text-surface-900">{{ asset.title }}</td>
            <td class="px-6 py-4 text-sm text-surface-500">{{ asset.mime_type }}</td>
            <td class="px-6 py-4">
              <span class="badge" :class="statusBadge(asset.status)">{{ asset.status }}</span>
            </td>
            <td class="px-6 py-4 text-xs text-surface-400">{{ formatDate(asset.created_at) }}</td>
            <td class="px-6 py-4 text-sm">
              <div class="flex items-center gap-2">
                <button v-if="asset.status !== 'approved'" @click="approve(asset.id)" class="text-green-600 hover:text-green-800 font-medium">Approve</button>
                <button v-if="asset.status !== 'rejected'" @click="reject(asset.id)" class="text-red-600 hover:text-red-800 font-medium">Reject</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/api/axios'

const assets = ref([])
const isLoading = ref(false)
const statusFilter = ref('pending')

async function load() {
  isLoading.value = true
  try {
    const params = statusFilter.value ? `?status=${statusFilter.value}` : ''
    const response = await api.get(`/admin/assets${params}`)
    assets.value = response.data.data
  } finally {
    isLoading.value = false
  }
}

async function approve(id) {
  await api.patch(`/admin/assets/${id}/approve`)
  await load()
}

async function reject(id) {
  await api.patch(`/admin/assets/${id}/reject`)
  await load()
}

function statusBadge(status) {
  return { 'bg-yellow-100 text-yellow-700': status === 'pending', 'bg-green-100 text-green-700': status === 'approved', 'bg-red-100 text-red-700': status === 'rejected' }
}

function formatDate(iso) { return new Date(iso).toLocaleDateString() }

onMounted(load)
</script>
