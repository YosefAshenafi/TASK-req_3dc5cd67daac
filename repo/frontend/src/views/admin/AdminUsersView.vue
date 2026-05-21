<template>
  <div>
    <h2 class="text-xl font-bold text-surface-900 mb-6">User Management</h2>

    <div v-if="isLoading" class="space-y-3">
      <div v-for="n in 5" :key="n" class="skeleton h-16 rounded-xl" />
    </div>

    <div v-else class="card p-0 overflow-hidden">
      <table class="min-w-full divide-y divide-surface-200">
        <thead class="bg-surface-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-surface-500 uppercase tracking-wider">Name</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-surface-500 uppercase tracking-wider">Email</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-surface-500 uppercase tracking-wider">Role</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-surface-500 uppercase tracking-wider">Status</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-surface-500 uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-surface-200">
          <tr v-for="user in users" :key="user.id" class="hover:bg-surface-50">
            <td class="px-6 py-4 text-sm font-medium text-surface-900">{{ user.name }}</td>
            <td class="px-6 py-4 text-sm text-surface-500">{{ user.email }}</td>
            <td class="px-6 py-4">
              <span class="badge" :class="roleBadge(user.role)">{{ user.role }}</span>
            </td>
            <td class="px-6 py-4">
              <span class="badge" :class="statusBadge(user.account_status)">{{ user.account_status }}</span>
            </td>
            <td class="px-6 py-4 text-sm">
              <div class="flex items-center gap-2">
                <button
                  v-if="user.account_status !== 'frozen'"
                  @click="freezeUser(user)"
                  class="text-yellow-600 hover:text-yellow-800 font-medium"
                >Freeze</button>
                <button
                  v-if="user.account_status !== 'blacklisted'"
                  @click="blacklistUser(user.id)"
                  class="text-red-600 hover:text-red-800 font-medium"
                >Blacklist</button>
                <button @click="deleteUser(user.id)" class="text-surface-400 hover:text-surface-600">Delete</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="freezeTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.self="freezeTarget = null">
      <div class="bg-white rounded-xl p-6 w-full max-w-sm">
        <h3 class="font-semibold text-surface-900 mb-4">Freeze {{ freezeTarget.name }}</h3>
        <label class="block text-sm font-medium text-surface-700 mb-1">Duration (hours)</label>
        <input v-model.number="freezeHours" type="number" min="1" max="8760" class="input mb-4" placeholder="72" />
        <div class="flex gap-3 justify-end">
          <button @click="freezeTarget = null" class="btn-secondary">Cancel</button>
          <button @click="confirmFreeze" class="btn-primary">Freeze</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/api/axios'

const users = ref([])
const isLoading = ref(false)
const freezeTarget = ref(null)
const freezeHours = ref(72)

async function load() {
  isLoading.value = true
  try {
    const response = await api.get('/admin/users')
    users.value = response.data.data
  } finally {
    isLoading.value = false
  }
}

function freezeUser(user) { freezeTarget.value = user; freezeHours.value = 72 }

async function confirmFreeze() {
  if (!freezeTarget.value) return
  await api.patch(`/admin/users/${freezeTarget.value.id}/freeze`, { duration_hours: freezeHours.value })
  freezeTarget.value = null
  await load()
}

async function blacklistUser(id) {
  if (!confirm('Permanently blacklist this user?')) return
  await api.patch(`/admin/users/${id}/blacklist`)
  await load()
}

async function deleteUser(id) {
  if (!confirm('Soft-delete this user?')) return
  await api.delete(`/admin/users/${id}`)
  await load()
}

function roleBadge(role) {
  return { 'bg-purple-100 text-purple-700': role === 'admin', 'bg-green-100 text-green-700': role === 'technician', 'bg-blue-100 text-blue-700': role === 'user' }
}
function statusBadge(status) {
  return { 'bg-green-100 text-green-700': status === 'active', 'bg-yellow-100 text-yellow-700': status === 'frozen', 'bg-red-100 text-red-700': status === 'blacklisted' }
}

onMounted(load)
</script>
