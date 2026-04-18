<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import type { User, CreateUserRequest } from '@/types/api'
import { usersApi, ApiError } from '@/services/api'
import { useUiStore } from '@/stores/ui'
import { UserPlus, Users, Snowflake, RotateCcw, ShieldAlert, Trash2, Loader2, Search, ChevronDown } from 'lucide-vue-next'

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
  if (user.deleted_at) return 'bg-gray-100 text-gray-600'
  if (user.blacklisted_at) return 'bg-red-50 text-red-700 ring-1 ring-red-200'
  if (user.frozen_until && new Date(user.frozen_until) > new Date()) return 'bg-amber-50 text-amber-700 ring-1 ring-amber-200'
  return 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200'
}

function getRoleStyle(role: string): string {
  switch (role) {
    case 'admin': return 'bg-red-50 text-red-700 ring-1 ring-red-200'
    case 'technician': return 'bg-amber-50 text-amber-700 ring-1 ring-amber-200'
    default: return 'bg-sky-50 text-sky-700 ring-1 ring-sky-200'
  }
}
</script>

<template>
  <div class="min-h-full">
    <!-- Dark page header -->
    <div class="bg-gray-900 border-b border-gray-800 px-6 py-6">
      <div class="max-w-6xl mx-auto flex items-center justify-between">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <Users class="w-4 h-4 text-sky-400" />
            <span class="text-xs font-semibold text-sky-400 uppercase tracking-widest">Admin</span>
          </div>
          <h1 class="text-xl font-bold text-white">User Management</h1>
          <p class="text-sm text-gray-400 mt-0.5">{{ users.length }} total users</p>
        </div>
        <button
          @click="showCreateForm = !showCreateForm"
          class="flex items-center gap-2 px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white text-sm font-semibold rounded-lg transition-colors shadow-sm"
        >
          <UserPlus class="w-4 h-4" />
          Create User
        </button>
      </div>
    </div>

    <div class="max-w-6xl mx-auto px-6 py-6 space-y-5">
      <!-- Create form -->
      <div v-if="showCreateForm" class="bg-white rounded-xl border border-sky-200 p-6 shadow-sm">
        <h2 class="text-sm font-semibold text-gray-900 mb-4">New User</h2>
        <div v-if="createError" class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
          {{ createError }}
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1.5">Username</label>
            <input
              v-model="newUsername"
              type="text"
              class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500/40 focus:border-sky-500"
              placeholder="username"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1.5">Password</label>
            <input
              v-model="newPassword"
              type="password"
              class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500/40 focus:border-sky-500"
              placeholder="••••••••"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1.5">Role</label>
            <select
              v-model="newRole"
              class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500/40 focus:border-sky-500 bg-white"
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
            class="flex items-center gap-2 px-4 py-2 bg-sky-600 text-white text-sm font-semibold rounded-lg hover:bg-sky-700 disabled:opacity-50 transition-colors"
          >
            <Loader2 v-if="creating" class="w-4 h-4 animate-spin" />
            {{ creating ? 'Creating…' : 'Create User' }}
          </button>
          <button @click="showCreateForm = false" class="px-4 py-2 text-sm text-gray-600 rounded-lg hover:bg-gray-100 transition-colors">
            Cancel
          </button>
        </div>
      </div>

      <!-- Filters -->
      <div class="flex items-center gap-3 flex-wrap">
        <div class="flex items-center gap-2">
          <label class="text-xs font-medium text-gray-500">Role</label>
          <div class="relative">
            <select
              v-model="filterRole"
              class="pl-3 pr-8 py-1.5 text-sm border border-gray-200 rounded-lg bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-sky-500/40 appearance-none cursor-pointer"
            >
              <option value="">All roles</option>
              <option value="user">User</option>
              <option value="admin">Admin</option>
              <option value="technician">Technician</option>
            </select>
            <ChevronDown class="absolute right-2 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none" />
          </div>
        </div>
        <div class="flex items-center gap-2">
          <label class="text-xs font-medium text-gray-500">Status</label>
          <div class="relative">
            <select
              v-model="filterStatus"
              class="pl-3 pr-8 py-1.5 text-sm border border-gray-200 rounded-lg bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-sky-500/40 appearance-none cursor-pointer"
            >
              <option value="">All statuses</option>
              <option value="active">Active</option>
              <option value="frozen">Frozen</option>
              <option value="blacklisted">Blacklisted</option>
            </select>
            <ChevronDown class="absolute right-2 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none" />
          </div>
        </div>
      </div>

      <!-- Loading skeletons -->
      <div v-if="loading" class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
        <div v-for="n in 5" :key="n" class="h-14 px-5 flex items-center gap-4">
          <div class="w-8 h-8 bg-gray-100 rounded-full animate-pulse shrink-0" />
          <div class="flex-1 space-y-1.5">
            <div class="h-3 bg-gray-100 rounded-full animate-pulse w-32" />
            <div class="h-2.5 bg-gray-100 rounded-full animate-pulse w-16" />
          </div>
        </div>
      </div>

      <!-- Table -->
      <div v-else class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
        <table class="w-full">
          <thead>
            <tr class="bg-gray-50 border-b border-gray-200">
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">User</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Role</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="user in filteredUsers" :key="user.id" class="hover:bg-gray-50/60 transition-colors">
              <td class="px-5 py-3.5">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-full bg-sky-100 text-sky-600 flex items-center justify-center text-xs font-bold shrink-0">
                    {{ user.username?.[0]?.toUpperCase() ?? 'U' }}
                  </div>
                  <div>
                    <p class="text-sm font-medium text-gray-900">{{ user.username }}</p>
                    <p class="text-xs text-gray-400">#{{ user.id }}</p>
                  </div>
                </div>
              </td>
              <td class="px-5 py-3.5">
                <span :class="['text-xs font-medium px-2 py-0.5 rounded-md capitalize', getRoleStyle(user.role)]">
                  {{ user.role }}
                </span>
              </td>
              <td class="px-5 py-3.5">
                <div>
                  <span :class="['text-xs font-medium px-2 py-0.5 rounded-md', getStatusStyle(user)]">
                    {{ getStatusLabel(user) }}
                  </span>
                  <p v-if="user.frozen_until" class="text-xs text-gray-400 mt-1">
                    until {{ new Date(user.frozen_until).toLocaleDateString() }}
                  </p>
                </div>
              </td>
              <td class="px-5 py-3.5">
                <div class="flex items-center gap-1.5 flex-wrap">
                  <!-- Freeze -->
                  <div v-if="!user.frozen_until || new Date(user.frozen_until) <= new Date()">
                    <div v-if="freezingUserId === user.id" class="flex items-center gap-1.5">
                      <input
                        v-model.number="freezeDuration"
                        type="number"
                        min="1"
                        max="720"
                        class="w-14 px-2 py-1 text-xs border border-gray-200 rounded-md text-center"
                      />
                      <span class="text-xs text-gray-400">h</span>
                      <button
                        @click="handleFreeze(user)"
                        :disabled="actionLoading === user.id"
                        class="px-2.5 py-1 bg-amber-500 text-white text-xs font-medium rounded-md hover:bg-amber-600 disabled:opacity-50"
                      >
                        <Loader2 v-if="actionLoading === user.id" class="w-3 h-3 animate-spin" />
                        <span v-else>Confirm</span>
                      </button>
                      <button @click="freezingUserId = null" class="px-2.5 py-1 text-xs text-gray-500 rounded-md hover:bg-gray-100">Cancel</button>
                    </div>
                    <button
                      v-else
                      @click="freezingUserId = user.id"
                      class="flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-amber-700 border border-amber-200 bg-amber-50 rounded-md hover:bg-amber-100 transition-colors"
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
                    class="flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-sky-700 border border-sky-200 bg-sky-50 rounded-md hover:bg-sky-100 disabled:opacity-50 transition-colors"
                  >
                    <RotateCcw class="w-3 h-3" />
                    Unfreeze
                  </button>

                  <!-- Blacklist -->
                  <button
                    v-if="!user.blacklisted_at"
                    @click="handleBlacklist(user)"
                    :disabled="actionLoading === user.id"
                    class="flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-red-700 border border-red-200 bg-red-50 rounded-md hover:bg-red-100 disabled:opacity-50 transition-colors"
                  >
                    <ShieldAlert class="w-3 h-3" />
                    Blacklist
                  </button>

                  <!-- Delete -->
                  <button
                    v-if="!user.deleted_at"
                    @click="handleDelete(user)"
                    :disabled="actionLoading === user.id"
                    class="flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-gray-600 border border-gray-200 bg-gray-50 rounded-md hover:bg-gray-100 disabled:opacity-50 transition-colors"
                  >
                    <Trash2 class="w-3 h-3" />
                    Delete
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>

        <div v-if="filteredUsers.length === 0" class="flex flex-col items-center justify-center py-12">
          <Search class="w-7 h-7 text-gray-300 mb-2" />
          <p class="text-sm text-gray-500">No users match the current filters</p>
        </div>
      </div>
    </div>
  </div>
</template>
