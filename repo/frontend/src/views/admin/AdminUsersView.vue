<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import type { User, CreateUserRequest } from '@/types/api'
import { usersApi, ApiError } from '@/services/api'
import { useUiStore } from '@/stores/ui'
import { UserPlus, Search, ChevronDown, ShieldAlert, Trash2, Snowflake, RotateCcw, Loader2 } from 'lucide-vue-next'

const uiStore = useUiStore()

const users = ref<User[]>([])
const loading = ref(false)
const filterRole = ref<string>('')
const filterStatus = ref<string>('')
const showCreateForm = ref(false)

const newUsername = ref('')
const newPassword = ref('')
const newRole = ref<'user' | 'admin' | 'technician'>('user')
const creating = ref(false)
const createError = ref('')

const freezingUserId = ref<number | null>(null)
const freezeDuration = ref(72)
const actionLoading = ref<number | null>(null)

const filteredUsers = computed(() =>
  users.value.filter((u) => {
    if (filterRole.value && u.role !== filterRole.value) return false
    if (filterStatus.value === 'frozen' && !u.frozen_until) return false
    if (filterStatus.value === 'blacklisted' && !u.blacklisted_at) return false
    if (filterStatus.value === 'active' && (u.frozen_until || u.blacklisted_at || u.deleted_at)) return false
    return true
  })
)

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
    createError.value = err instanceof ApiError ? (err.body?.message ?? 'Failed to create user') : 'Unexpected error'
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
  if (!confirm(`Blacklist ${user.username}? They will be permanently blocked from logging in.`)) return
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

function getStatusStyle(user: User): string {
  if (user.deleted_at) return 'bg-slate-100 text-slate-600'
  if (user.blacklisted_at) return 'bg-red-100 text-red-700'
  if (user.frozen_until && new Date(user.frozen_until) > new Date()) return 'bg-amber-100 text-amber-700'
  return 'bg-emerald-100 text-emerald-700'
}

function getRoleStyle(role: string): string {
  switch (role) {
    case 'admin': return 'bg-red-50 text-red-700 border border-red-200'
    case 'technician': return 'bg-amber-50 text-amber-700 border border-amber-200'
    default: return 'bg-blue-50 text-blue-700 border border-blue-200'
  }
}
</script>

<template>
  <div class="p-6 max-w-6xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">User Management</h1>
        <p class="text-sm text-slate-500 mt-0.5">{{ users.length }} total users</p>
      </div>
      <button
        @click="showCreateForm = !showCreateForm"
        class="flex items-center gap-2 px-4 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 transition-colors shadow-sm shadow-indigo-600/20"
      >
        <UserPlus class="w-4 h-4" />
        Create User
      </button>
    </div>

    <!-- Create form -->
    <div v-if="showCreateForm" class="bg-white rounded-2xl border border-indigo-200 p-6 mb-6 shadow-sm">
      <h2 class="font-semibold text-slate-900 mb-5">New User</h2>
      <div v-if="createError" class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl">
        {{ createError }}
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Username</label>
          <input
            v-model="newUsername"
            type="text"
            class="w-full px-3.5 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500"
            placeholder="username"
          />
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Password</label>
          <input
            v-model="newPassword"
            type="password"
            class="w-full px-3.5 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500"
            placeholder="••••••••"
          />
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Role</label>
          <select
            v-model="newRole"
            class="w-full px-3.5 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 bg-white"
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
          :disabled="creating || !newUsername.trim() || !newPassword.trim()"
          class="flex items-center gap-2 px-5 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 disabled:opacity-50 transition-colors"
        >
          <Loader2 v-if="creating" class="w-4 h-4 animate-spin" />
          {{ creating ? 'Creating…' : 'Create User' }}
        </button>
        <button
          @click="showCreateForm = false"
          class="px-5 py-2.5 text-sm text-slate-600 rounded-xl hover:bg-slate-100 transition-colors"
        >
          Cancel
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap gap-3 mb-5">
      <div class="flex items-center gap-2">
        <label class="text-xs font-medium text-slate-500">Role</label>
        <select
          v-model="filterRole"
          class="px-3 py-1.5 text-sm border border-slate-200 rounded-lg focus:outline-none bg-white text-slate-700"
        >
          <option value="">All roles</option>
          <option value="user">User</option>
          <option value="admin">Admin</option>
          <option value="technician">Technician</option>
        </select>
      </div>
      <div class="flex items-center gap-2">
        <label class="text-xs font-medium text-slate-500">Status</label>
        <select
          v-model="filterStatus"
          class="px-3 py-1.5 text-sm border border-slate-200 rounded-lg focus:outline-none bg-white text-slate-700"
        >
          <option value="">All statuses</option>
          <option value="active">Active</option>
          <option value="frozen">Frozen</option>
          <option value="blacklisted">Blacklisted</option>
        </select>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="space-y-2">
      <div v-for="n in 5" :key="n" class="h-14 bg-white border border-slate-200 rounded-xl animate-pulse" />
    </div>

    <!-- Table -->
    <div v-else class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
      <table class="w-full">
        <thead>
          <tr class="border-b border-slate-200 bg-slate-50">
            <th class="px-5 py-3.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">User</th>
            <th class="px-5 py-3.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Role</th>
            <th class="px-5 py-3.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
            <th class="px-5 py-3.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="user in filteredUsers" :key="user.id" class="hover:bg-slate-50/50 transition-colors">
            <td class="px-5 py-4">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs font-bold shrink-0">
                  {{ user.username?.[0]?.toUpperCase() ?? 'U' }}
                </div>
                <div>
                  <p class="text-sm font-semibold text-slate-900">{{ user.username }}</p>
                  <p class="text-xs text-slate-400">#{{ user.id }}</p>
                </div>
              </div>
            </td>
            <td class="px-5 py-4">
              <span :class="['text-xs font-semibold px-2.5 py-1 rounded-full', getRoleStyle(user.role)]">
                {{ user.role }}
              </span>
            </td>
            <td class="px-5 py-4">
              <div>
                <span :class="['text-xs font-semibold px-2.5 py-1 rounded-full', getStatusStyle(user)]">
                  {{ getStatusLabel(user) }}
                </span>
                <p v-if="user.frozen_until" class="text-xs text-slate-400 mt-1">
                  until {{ new Date(user.frozen_until).toLocaleDateString() }}
                </p>
              </div>
            </td>
            <td class="px-5 py-4">
              <div class="flex items-center gap-2 flex-wrap">
                <!-- Freeze control -->
                <div v-if="!user.frozen_until || new Date(user.frozen_until) <= new Date()">
                  <div v-if="freezingUserId === user.id" class="flex items-center gap-2">
                    <input
                      v-model.number="freezeDuration"
                      type="number"
                      min="1"
                      max="720"
                      class="w-16 px-2 py-1.5 text-xs border border-slate-200 rounded-lg text-center"
                    />
                    <span class="text-xs text-slate-400">hrs</span>
                    <button
                      @click="handleFreeze(user)"
                      :disabled="actionLoading === user.id"
                      class="px-3 py-1.5 bg-amber-500 text-white text-xs font-semibold rounded-lg hover:bg-amber-600 disabled:opacity-50"
                    >
                      <Loader2 v-if="actionLoading === user.id" class="w-3 h-3 animate-spin" />
                      <span v-else>Confirm</span>
                    </button>
                    <button @click="freezingUserId = null" class="px-3 py-1.5 text-xs text-slate-500 rounded-lg hover:bg-slate-100">
                      Cancel
                    </button>
                  </div>
                  <button
                    v-else
                    @click="freezingUserId = user.id"
                    class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-amber-700 border border-amber-200 bg-amber-50 rounded-lg hover:bg-amber-100 transition-colors"
                  >
                    <Snowflake class="w-3 h-3" />
                    Freeze
                  </button>
                </div>

                <!-- Unfreeze -->
                <button
                  v-if="user.frozen_until && new Date(user.frozen_until) > new Date()"
                  @click="handleUnfreeze(user)"
                  :disabled="actionLoading === user.id"
                  class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-indigo-700 border border-indigo-200 bg-indigo-50 rounded-lg hover:bg-indigo-100 disabled:opacity-50 transition-colors"
                >
                  <RotateCcw class="w-3 h-3" />
                  Unfreeze
                </button>

                <!-- Blacklist -->
                <button
                  v-if="!user.blacklisted_at"
                  @click="handleBlacklist(user)"
                  :disabled="actionLoading === user.id"
                  class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-red-700 border border-red-200 bg-red-50 rounded-lg hover:bg-red-100 disabled:opacity-50 transition-colors"
                >
                  <ShieldAlert class="w-3 h-3" />
                  Blacklist
                </button>

                <!-- Delete -->
                <button
                  v-if="!user.deleted_at"
                  @click="handleDelete(user)"
                  :disabled="actionLoading === user.id"
                  class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-slate-600 border border-slate-200 bg-slate-50 rounded-lg hover:bg-slate-100 disabled:opacity-50 transition-colors"
                >
                  <Trash2 class="w-3 h-3" />
                  Delete
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="filteredUsers.length === 0" class="flex flex-col items-center justify-center py-12 text-center">
        <Search class="w-8 h-8 text-slate-300 mb-3" />
        <p class="text-sm text-slate-500">No users match the current filters</p>
      </div>
    </div>
  </div>
</template>
