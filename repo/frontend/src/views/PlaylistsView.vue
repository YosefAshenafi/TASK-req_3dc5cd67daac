<script setup lang="ts">
import { ref, onMounted } from 'vue'
import type { Playlist, PlaylistShare } from '@/types/api'
import { playlistsApi } from '@/services/api'
import { useUiStore } from '@/stores/ui'
import ShareDialog from '@/components/ShareDialog.vue'
import RedeemDialog from '@/components/RedeemDialog.vue'
import { useRouter } from 'vue-router'
import { ListMusic, Plus, Share2, Trash2, ChevronRight, Ticket, Loader2 } from 'lucide-vue-next'

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
  <div class="p-6 max-w-4xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Playlists</h1>
        <p class="text-sm text-slate-500 mt-0.5">Organize your media into collections</p>
      </div>
      <div class="flex gap-2">
        <button
          @click="showRedeem = true"
          class="flex items-center gap-2 px-4 py-2 text-sm font-semibold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-xl hover:bg-indigo-100 transition-colors"
        >
          <Ticket class="w-4 h-4" />
          Redeem
        </button>
        <button
          @click="showCreateInput = !showCreateInput"
          class="flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 transition-colors shadow-sm shadow-indigo-600/20"
        >
          <Plus class="w-4 h-4" />
          New Playlist
        </button>
      </div>
    </div>

    <!-- Create input -->
    <div v-if="showCreateInput" class="bg-white border border-indigo-200 rounded-xl p-4 mb-5 shadow-sm">
      <p class="text-sm font-semibold text-slate-700 mb-3">New Playlist</p>
      <div class="flex gap-3">
        <input
          v-model="newPlaylistName"
          type="text"
          placeholder="Enter playlist name…"
          class="flex-1 px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 bg-white"
          @keydown.enter="handleCreate"
          @keydown.escape="showCreateInput = false"
          autofocus
        />
        <button
          @click="handleCreate"
          :disabled="creating || !newPlaylistName.trim()"
          class="flex items-center gap-2 px-5 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 disabled:opacity-50 transition-colors"
        >
          <Loader2 v-if="creating" class="w-4 h-4 animate-spin" />
          {{ creating ? 'Creating…' : 'Create' }}
        </button>
        <button
          @click="showCreateInput = false"
          class="px-4 py-2.5 text-sm text-slate-600 rounded-xl hover:bg-slate-100 transition-colors"
        >
          Cancel
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="space-y-2">
      <div v-for="n in 4" :key="n" class="h-16 bg-white border border-slate-200 rounded-xl animate-pulse" />
    </div>

    <!-- Empty state -->
    <div v-else-if="playlists.length === 0" class="flex flex-col items-center justify-center py-24 text-center">
      <div class="w-16 h-16 rounded-2xl bg-slate-100 flex items-center justify-center mb-4">
        <ListMusic class="w-7 h-7 text-slate-400" />
      </div>
      <h3 class="text-base font-semibold text-slate-700 mb-1">No playlists yet</h3>
      <p class="text-sm text-slate-400">Create a playlist to organize your media</p>
    </div>

    <!-- Playlist list -->
    <div v-else class="space-y-2">
      <div
        v-for="pl in playlists"
        :key="pl.id"
        class="bg-white rounded-xl border border-slate-200 hover:border-indigo-200 hover:shadow-sm transition-all duration-150 group"
      >
        <div class="flex items-center px-4 py-3.5">
          <!-- Playlist icon -->
          <div class="w-10 h-10 rounded-lg bg-indigo-50 flex items-center justify-center mr-3 shrink-0 group-hover:bg-indigo-100 transition-colors">
            <ListMusic class="w-5 h-5 text-indigo-500" />
          </div>

          <!-- Playlist info (clickable) -->
          <button
            @click="router.push(`/playlists/${pl.id}`)"
            class="flex-1 min-w-0 text-left"
          >
            <p class="font-semibold text-slate-900 truncate">{{ pl.name }}</p>
            <p class="text-xs text-slate-400 mt-0.5">
              {{ pl.items_count ?? pl.items?.length ?? 0 }}
              {{ (pl.items_count ?? pl.items?.length ?? 0) === 1 ? 'item' : 'items' }}
            </p>
          </button>

          <!-- Actions -->
          <div class="flex items-center gap-1 ml-3">
            <button
              @click="handleShare(pl)"
              class="p-2 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors"
              aria-label="Share playlist"
            >
              <Share2 class="w-4 h-4" />
            </button>
            <button
              @click="handleDelete(pl)"
              :disabled="deletingId === pl.id"
              class="p-2 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors disabled:opacity-40"
              aria-label="Delete playlist"
            >
              <Trash2 class="w-4 h-4" />
            </button>
            <ChevronRight class="w-4 h-4 text-slate-300 ml-1" />
          </div>
        </div>
      </div>
    </div>

    <!-- Dialogs -->
    <ShareDialog
      v-if="sharingPlaylist && activeShare"
      :share="activeShare"
      :playlist-id="sharingPlaylist.id"
      @close="sharingPlaylist = null; activeShare = null"
      @revoked="handleShareRevoked"
    />
    <RedeemDialog
      v-if="showRedeem"
      @close="showRedeem = false"
      @redeemed="handleRedeemed"
    />
  </div>
</template>
