<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-surface-900">My Playlists</h1>
      <div class="flex gap-2">
        <button @click="showRedeemModal = true" class="btn-secondary">Redeem Code</button>
        <button @click="showCreateModal = true" class="btn-primary">New Playlist</button>
      </div>
    </div>

    <div v-if="store.isLoading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="n in 3" :key="n" class="skeleton h-32 rounded-xl" />
    </div>

    <div v-else-if="store.playlists.length === 0" class="card text-center py-16">
      <svg class="w-16 h-16 text-surface-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7" />
      </svg>
      <p class="text-surface-500 font-medium">No playlists yet</p>
      <button @click="showCreateModal = true" class="btn-primary mt-4">Create your first playlist</button>
    </div>

    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="playlist in store.playlists" :key="playlist.id" class="card hover:shadow-md transition-shadow">
        <RouterLink :to="`/playlists/${playlist.id}`" class="block">
          <h3 class="font-semibold text-surface-900 hover:text-brand-700 transition-colors">{{ playlist.name }}</h3>
          <p v-if="playlist.description" class="text-sm text-surface-500 mt-1 line-clamp-2">{{ playlist.description }}</p>
          <p class="text-xs text-surface-400 mt-2">{{ (playlist.items || []).length }} items</p>
        </RouterLink>
        <div class="flex items-center gap-2 mt-3 pt-3 border-t border-surface-100">
          <button
            v-if="playlist.share_code"
            class="inline-flex items-center gap-1 font-mono text-xs bg-surface-100 hover:bg-surface-200 px-2 py-1 rounded transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-500"
            :title="copiedId === playlist.id ? 'Copied!' : 'Click to copy share code'"
            :aria-label="`Copy share code ${playlist.share_code}`"
            @click="copyShareCode(playlist.id, playlist.share_code)"
          >
            {{ playlist.share_code }}
            <svg v-if="copiedId !== playlist.id" class="w-3 h-3 text-surface-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
            </svg>
            <svg v-else class="w-3 h-3 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
          </button>
          <button @click="deletePlaylist(playlist.id)" class="ml-auto text-sm text-red-500 hover:text-red-700">Delete</button>
        </div>
      </div>
    </div>

    <CreatePlaylistModal v-if="showCreateModal" @close="showCreateModal = false" @created="store.fetchAll()" />
    <RedeemCodeModal v-if="showRedeemModal" @close="showRedeemModal = false" />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { usePlaylistStore } from '@/stores/playlist'
import CreatePlaylistModal from '@/components/CreatePlaylistModal.vue'
import RedeemCodeModal from '@/components/RedeemCodeModal.vue'

const store = usePlaylistStore()
const showCreateModal = ref(false)
const showRedeemModal = ref(false)
const copiedId = ref(null)

async function deletePlaylist(id) {
  if (!confirm('Delete this playlist?')) return
  await store.remove(id)
}

async function copyShareCode(id, code) {
  try {
    await navigator.clipboard.writeText(code)
    copiedId.value = id
    setTimeout(() => { copiedId.value = null }, 1500)
  } catch {
    // Clipboard API unavailable; code is still visible in the button
  }
}

onMounted(() => store.fetchAll())
</script>
