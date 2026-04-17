<script setup lang="ts">
import { ref, onMounted } from 'vue'
import type { Playlist, PlaylistShare } from '@/types/api'
import { playlistsApi } from '@/services/api'
import { useUiStore } from '@/stores/ui'
import ShareDialog from '@/components/ShareDialog.vue'
import RedeemDialog from '@/components/RedeemDialog.vue'
import { useRouter } from 'vue-router'

const uiStore = useUiStore()
const router = useRouter()

const playlists = ref<Playlist[]>([])
const loading = ref(false)
const creating = ref(false)
const newPlaylistName = ref('')
const showCreateInput = ref(false)
const deletingId = ref<number | null>(null)
const sharingPlaylist = ref<Playlist | null>(null)
const activeShare = ref<PlaylistShare | null>(null)
const showRedeem = ref(false)

async function fetchPlaylists() {
  loading.value = true
  try {
    playlists.value = await playlistsApi.list()
  } catch {
    uiStore.addNotification({ type: 'error', message: 'Failed to load playlists' })
  } finally {
    loading.value = false
  }
}

onMounted(() => fetchPlaylists())

async function handleCreate() {
  if (!newPlaylistName.value.trim()) return
  creating.value = true
  try {
    const pl = await playlistsApi.create({ name: newPlaylistName.value.trim() })
    playlists.value.push(pl)
    newPlaylistName.value = ''
    showCreateInput.value = false
    uiStore.addNotification({ type: 'success', message: 'Playlist created' })
  } catch {
    uiStore.addNotification({ type: 'error', message: 'Failed to create playlist' })
  } finally {
    creating.value = false
  }
}

async function handleDelete(pl: Playlist) {
  if (!confirm(`Delete playlist "${pl.name}"?`)) return
  deletingId.value = pl.id
  try {
    await playlistsApi.delete(pl.id)
    playlists.value = playlists.value.filter((p) => p.id !== pl.id)
    uiStore.addNotification({ type: 'success', message: 'Playlist deleted' })
  } catch {
    uiStore.addNotification({ type: 'error', message: 'Failed to delete playlist' })
  } finally {
    deletingId.value = null
  }
}

async function handleShare(pl: Playlist) {
  try {
    const share = await playlistsApi.share(pl.id, { expires_in_hours: 24 })
    sharingPlaylist.value = pl
    activeShare.value = share
  } catch {
    uiStore.addNotification({ type: 'error', message: 'Failed to create share link' })
  }
}

function handleRedeemed(pl: Playlist) {
  playlists.value.push(pl)
  showRedeem.value = false
  uiStore.addNotification({ type: 'success', message: `Playlist "${pl.name}" added!` })
}

function handleShareRevoked() {
  activeShare.value = null
  sharingPlaylist.value = null
  uiStore.addNotification({ type: 'success', message: 'Share revoked' })
}
</script>

<template>
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Playlists</h1>
      <div class="flex gap-2">
        <button
          @click="showRedeem = true"
          class="min-h-[44px] px-4 text-sm font-semibold text-blue-600 border border-blue-200 rounded-lg hover:bg-blue-50 transition-colors"
        >
          Redeem Code
        </button>
        <button
          @click="showCreateInput = !showCreateInput"
          class="min-h-[44px] px-4 text-sm font-semibold bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
        >
          + New Playlist
        </button>
      </div>
    </div>

    <!-- Create input -->
    <div v-if="showCreateInput" class="mb-6 flex gap-3">
      <input
        v-model="newPlaylistName"
        type="text"
        placeholder="Playlist name…"
        class="flex-1 min-h-[44px] px-4 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
        @keydown.enter="handleCreate"
        @keydown.escape="showCreateInput = false"
        autofocus
      />
      <button
        @click="handleCreate"
        :disabled="creating || !newPlaylistName.trim()"
        class="min-h-[44px] px-5 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 disabled:opacity-50"
      >
        {{ creating ? 'Creating…' : 'Create' }}
      </button>
    </div>

    <div v-if="loading" class="space-y-3">
      <div v-for="n in 4" :key="n" class="h-20 bg-gray-200 rounded-xl animate-pulse" />
    </div>

    <div v-else-if="playlists.length === 0" class="text-center py-16 text-gray-400">
      No playlists yet. Create one to get started.
    </div>

    <div v-else class="space-y-3">
      <div
        v-for="pl in playlists"
        :key="pl.id"
        class="bg-white rounded-xl border border-gray-200 p-4 flex items-center justify-between hover:border-blue-200 transition-colors"
      >
        <button
          @click="router.push(`/playlists/${pl.id}`)"
          class="flex-1 min-h-[44px] text-left"
        >
          <p class="font-semibold text-gray-900">{{ pl.name }}</p>
          <p class="text-xs text-gray-400 mt-0.5">
            {{ pl.items?.length ?? 0 }} item{{ (pl.items?.length ?? 0) !== 1 ? 's' : '' }}
          </p>
        </button>

        <div class="flex items-center gap-2 ml-4">
          <button
            @click="handleShare(pl)"
            class="min-h-[44px] min-w-[44px] flex items-center justify-center text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
            aria-label="Share playlist"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
            </svg>
          </button>
          <button
            @click="handleDelete(pl)"
            :disabled="deletingId === pl.id"
            class="min-h-[44px] min-w-[44px] flex items-center justify-center text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors disabled:opacity-50"
            aria-label="Delete playlist"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
          </button>
        </div>
      </div>
    </div>

    <!-- Share dialog -->
    <ShareDialog
      v-if="sharingPlaylist && activeShare"
      :share="activeShare"
      :playlist-id="sharingPlaylist.id"
      @close="sharingPlaylist = null; activeShare = null"
      @revoked="handleShareRevoked"
    />

    <!-- Redeem dialog -->
    <RedeemDialog
      v-if="showRedeem"
      @close="showRedeem = false"
      @redeemed="handleRedeemed"
    />
  </div>
</template>
