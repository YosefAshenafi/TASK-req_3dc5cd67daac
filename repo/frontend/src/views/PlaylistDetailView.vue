<template>
  <div>
    <div v-if="store.isLoading" class="space-y-4">
      <div class="skeleton h-8 w-48 rounded" />
      <div class="skeleton h-40 rounded-xl" />
    </div>

    <div v-else-if="store.currentPlaylist">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl font-bold text-surface-900">{{ store.currentPlaylist.name }}</h1>
          <p v-if="store.currentPlaylist.description" class="text-surface-500 mt-1">{{ store.currentPlaylist.description }}</p>
        </div>
        <div class="flex items-center gap-2">
          <button v-if="store.currentPlaylist.share_code" @click="copyShareCode" class="btn-secondary text-sm">
            Share: {{ store.currentPlaylist.share_code }}
          </button>
          <RouterLink to="/playlists" class="btn-secondary text-sm">← Back</RouterLink>
        </div>
      </div>

      <div v-if="store.currentPlaylist.items?.length === 0" class="card text-center py-12">
        <p class="text-surface-500">This playlist is empty</p>
      </div>

      <div v-else class="space-y-3">
        <div
          v-for="(item, index) in store.currentPlaylist.items"
          :key="item.id"
          class="card flex items-center gap-4"
        >
          <span class="text-surface-400 font-mono text-sm w-6 text-center shrink-0">{{ index + 1 }}</span>
          <div class="flex-1 min-w-0">
            <p class="font-medium text-surface-900 truncate">{{ item.asset?.title }}</p>
            <p class="text-xs text-surface-400 mt-0.5">{{ item.asset?.mime_type }}</p>
          </div>
          <button
            @click="removeItem(item.id)"
            class="text-red-400 hover:text-red-600 transition-colors shrink-0"
            aria-label="Remove item"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>

      <p v-if="copyMsg" class="text-sm text-green-600 mt-3">{{ copyMsg }}</p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { usePlaylistStore } from '@/stores/playlist'

const route = useRoute()
const store = usePlaylistStore()
const copyMsg = ref('')

async function removeItem(itemId) {
  if (!store.currentPlaylist) return
  await store.removeItem(store.currentPlaylist.id, itemId)
  await store.fetchOne(route.params.id)
}

async function copyShareCode() {
  const code = store.currentPlaylist?.share_code
  if (!code) return
  try {
    await navigator.clipboard.writeText(code)
    copyMsg.value = 'Copied to clipboard!'
    setTimeout(() => { copyMsg.value = '' }, 2000)
  } catch {
    copyMsg.value = code
  }
}

onMounted(() => store.fetchOne(route.params.id))
</script>
