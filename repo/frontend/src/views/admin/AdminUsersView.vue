<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import type { User, CreateUserRequest } from '@/types/api'
import { usersApi } from '@/services/api'
import { ApiError } from '@/services/api'
import { useUiStore } from '@/stores/ui'

const uiStore = useUiStore()

const users = ref<User[]>([])
const loading = ref(false)
const filterRole = ref<string>('')
const filterStatus = ref<string>('')
const showCreateForm = ref(false)

// Create form
const newUsername = ref('')
const newPassword = ref('')
const newRole = ref<'user' | 'admin' | 'technician'>('user')
const creating = ref(false)
const createError = ref('')

// Freeze form
const freezingUserId = ref<number | null>(null)
const freezeDuration = ref(72)
const actionLoading = ref<number | null>(null)

const filteredUsers = computed(() => {
  return users.value.filter((u) => {
    if (filterRole.value && u.role !== filterRole.value) return false
    if (filterStatus.value === 'frozen' && !u.frozen_until) return false
    if (filterStatus.value === 'blacklisted' && !u.blacklisted_at) return false
    if (filterStatus.value === 'active' && (u.frozen_until || u.blacklisted_at || u.deleted_at)) return false
    return true
  })
})

async function fetchUsers() {
  loading.value = true
  try {
    const result = await usersApi.list()
    users.value = result.items
  } catch {
    uiStore.addNotification({ type: 'error', message: 'Failed to load users' })
  } finally {
    loading.value = false
  }
}

onMounted(() => fetchUsers())

async function handleCreate() {
  if (!newUsername.value.trim() || !newPassword.value.trim()) return
  creating.value = true
  createError.value = ''
  try {
    const user = await usersApi.create({
      username: newUsername.value.trim(),
      password: newPassword.value,
      role: newRole.value,
    })
    users.value.push(user)
    newUsername.value = ''
    newPassword.value = ''
    newRole.value = 'user'
    showCreateForm.value = false
    uiStore.addNotification({ type: 'success', message: 'User created' })
  } catch (err) {
    if (err instanceof ApiError) {
      createError.value = err.body?.message ?? 'Failed to create user'
    } else {
      createError.value = 'Unexpected error'
    }
  } finally {
    creating.value = false
  }
}

async function handleFreeze(user: User) {
  actionLoading.value = user.id
  try {
    const updated = await usersApi.freeze(user.id, { duration_hours: freezeDuration.value })
    updateUser(updated)
    freezingUserId.value = null
    uiStore.addNotification({ type: 'success', message: `${user.username} frozen for ${freezeDuration.value}h` })
  } catch {
    uiStore.addNotification({ type: 'error', message: 'Failed to freeze user' })
  } finally {
    actionLoading.value = null
  }
}

async function handleUnfreeze(user: User) {
  actionLoading.value = user.id
  try {
    const updated = await usersApi.unfreeze(user.id)
    updateUser(updated)
    uiStore.addNotification({ type: 'success', message: `${user.username} unfrozen` })
  } catch {
    uiStore.addNotification({ type: 'error', message: 'Failed to unfreeze user' })
  } finally {
    actionLoading.value = null
  }
}

async function handleBlacklist(user: User) {
  if (!confirm(`Blacklist ${user.username}? This will prevent them from logging in.`)) return
  actionLoading.value = user.id
  try {
    const updated = await usersApi.blacklist(user.id)
    updateUser(updated)
    uiStore.addNotification({ type: 'success', message: `${user.username} blacklisted` })
  } catch {
    uiStore.addNotification({ type: 'error', message: 'Failed to blacklist user' })
  } finally {
    actionLoading.value = null
  }
}

async function handleDelete(user: User) {
  if (!confirm(`Soft-delete ${user.username}?`)) return
  actionLoading.value = user.id
  try {
    await usersApi.softDelete(user.id)
    users.value = users.value.filter((u) => u.id !== user.id)
    uiStore.addNotification({ type: 'success', message: `${user.username} deleted` })
  } catch {
    uiStore.addNotification({ type: 'error', message: 'Failed to delete user' })
  } finally {
    actionLoading.value = null
  }
}

function updateUser(updated: User) {
  const idx = users.value.findIndex((u) => u.id === updated.id)
  if (idx >= 0) users.value[idx] = updated
}

function getStatusLabel(user: User): string {
  if (user.deleted_at) return 'Deleted'
  if (user.blacklisted_at) return 'Blacklisted'
  if (user.frozen_until && new Date(user.frozen_until) > new Date()) return 'Frozen'
  return 'Active'
}

function getStatusClass(user: User): string {
  if (user.deleted_at) return 'bg-gray-100 text-gray-600'
  if (user.blacklisted_at) return 'bg-red-100 text-red-700'
  if (user.frozen_until && new Date(user.frozen_until) > new Date()) return 'bg-orange-100 text-orange-700'
  return 'bg-green-100 text-green-700'
}

function getRoleBadgeClass(role: string): string {
  switch (role) {
    case 'admin': return 'bg-red-100 text-red-800'
    case 'technician': return 'bg-yellow-100 text-yellow-800'
    default: return 'bg-blue-100 text-blue-800'
  }
}
</script>

<template>
  <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-gray-900">User Management</h1>
      <button
        @click="showCreateForm = !showCreateForm"
        class="min-h-[44px] px-4 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors"
      >
        + Create User
      </button>
    </div>

    <!-- Create form -->
    <div v-if="showCreateForm" class="bg-white rounded-2xl border border-gray-200 p-6 mb-6">
      <h2 class="font-semibold text-gray-900 mb-4">New User</h2>

      <div v-if="createError" class="mb-4 p-3 bg-red-50 text-red-700 text-sm rounded-lg">
        {{ createError }}
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
          <input
            v-model="newUsername"
            type="text"
            class="w-full min-h-[44px] px-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
          <input
            v-model="newPassword"
            type="password"
            class="w-full min-h-[44px] px-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
          <select
            v-model="newRole"
            class="w-full min-h-[44px] px-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="user">User</option>
            <option value="admin">Admin</option>
            <option value="technician">Technician</option>
          </select>
        </div>
      </div>

      <div class="flex gap-3">
        <button
          @click="handleCreate"
          :disabled="creating"
          class="min-h-[44px] px-6 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 disabled:opacity-50"
        >
          {{ creating ? 'Creating…' : 'Create' }}
        </button>
        <button
          @click="showCreateForm = false"
          class="min-h-[44px] px-6 text-gray-600 rounded-lg hover:bg-gray-100"
        >
          Cancel
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex gap-4 mb-4 flex-wrap">
      <div class="flex items-center gap-2">
        <label class="text-sm text-gray-500">Role:</label>
        <select
          v-model="filterRole"
          class="min-h-[36px] px-3 border border-gray-300 rounded-lg text-sm focus:outline-none"
        >
          <option value="">All</option>
          <option value="user">User</option>
          <option value="admin">Admin</option>
          <option value="technician">Technician</option>
        </select>
      </div>
      <div class="flex items-center gap-2">
        <label class="text-sm text-gray-500">Status:</label>
        <select
          v-model="filterStatus"
          class="min-h-[36px] px-3 border border-gray-300 rounded-lg text-sm focus:outline-none"
        >
          <option value="">All</option>
          <option value="active">Active</option>
          <option value="frozen">Frozen</option>
          <option value="blacklisted">Blacklisted</option>
        </select>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="space-y-3">
      <div v-for="n in 5" :key="n" class="h-16 bg-gray-200 rounded-xl animate-pulse" />
    </div>

    <!-- User table -->
    <div v-else class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
      <table class="w-full">
        <thead class="bg-gray-50 border-b border-gray-200">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Username</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Role</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="user in filteredUsers" :key="user.id" class="hover:bg-gray-50">
            <td class="px-4 py-3">
              <span class="font-medium text-gray-900">{{ user.username }}</span>
              <span class="text-xs text-gray-400 ml-2">#{{ user.id }}</span>
            </td>
            <td class="px-4 py-3">
              <span :class="['text-xs font-semibold px-2 py-1 rounded-full', getRoleBadgeClass(user.role)]">
                {{ user.role }}
              </span>
            </td>
            <td class="px-4 py-3">
              <span :class="['text-xs font-semibold px-2 py-1 rounded-full', getStatusClass(user)]">
                {{ getStatusLabel(user) }}
              </span>
              <span v-if="user.frozen_until" class="text-xs text-gray-400 ml-2">
                until {{ new Date(user.frozen_until).toLocaleDateString() }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2 flex-wrap">
                <!-- Freeze -->
                <div v-if="!user.frozen_until || new Date(user.frozen_until) <= new Date()">
                  <div v-if="freezingUserId === user.id" class="flex items-center gap-2">
                    <input
                      v-model.number="freezeDuration"
                      type="number"
                      min="1"
                      max="720"
                      class="w-20 min-h-[36px] px-2 border border-gray-300 rounded text-sm"
                    />
                    <span class="text-xs text-gray-500">hours</span>
                    <button
                      @click="handleFreeze(user)"
                      :disabled="actionLoading === user.id"
                      class="min-h-[36px] px-3 bg-orange-500 text-white text-sm font-semibold rounded hover:bg-orange-600 disabled:opacity-50"
                    >
                      Confirm
                    </button>
                    <button
                      @click="freezingUserId = null"
                      class="min-h-[36px] px-3 text-gray-500 text-sm rounded hover:bg-gray-100"
                    >
                      Cancel
                    </button>
                  </div>
                  <button
                    v-else
                    @click="freezingUserId = user.id"
                    class="min-h-[36px] px-3 text-orange-600 border border-orange-200 text-sm font-semibold rounded hover:bg-orange-50"
                  >
                    Freeze
                  </button>
                </div>

                <!-- Unfreeze -->
                <button
                  v-if="user.frozen_until && new Date(user.frozen_until) > new Date()"
                  @click="handleUnfreeze(user)"
                  :disabled="actionLoading === user.id"
                  class="min-h-[36px] px-3 text-blue-600 border border-blue-200 text-sm font-semibold rounded hover:bg-blue-50 disabled:opacity-50"
                >
                  Unfreeze
                </button>

                <!-- Blacklist -->
                <button
                  v-if="!user.blacklisted_at"
                  @click="handleBlacklist(user)"
                  :disabled="actionLoading === user.id"
                  class="min-h-[36px] px-3 text-red-600 border border-red-200 text-sm font-semibold rounded hover:bg-red-50 disabled:opacity-50"
                >
                  Blacklist
                </button>

                <!-- Delete -->
                <button
                  v-if="!user.deleted_at"
                  @click="handleDelete(user)"
                  :disabled="actionLoading === user.id"
                  class="min-h-[36px] px-3 text-gray-500 border border-gray-200 text-sm font-semibold rounded hover:bg-gray-50 disabled:opacity-50"
                >
                  Delete
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="filteredUsers.length === 0" class="text-center py-12 text-gray-400">
        No users match the current filters.
      </div>
    </div>
  </div>
</template>
