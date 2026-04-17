<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import type { Playlist, PlaylistItem, PlaylistShare } from '@/types/api'
import { playlistsApi } from '@/services/api'
import { useUiStore } from '@/stores/ui'
import ShareDialog from '@/components/ShareDialog.vue'

const route = useRoute()
const router = useRouter()
const uiStore = useUiStore()

const playlist = ref<Playlist | null>(null)
const loading = ref(false)
const saving = ref(false)
const deleting = ref(false)
const editName = ref('')
const isEditingName = ref(false)
const removingItemId = ref<number | null>(null)
const activeShare = ref<PlaylistShare | null>(null)

const playlistId = computed(() => Number(route.params.id))

async function fetchPlaylist() {
  loading.value = true
  try {
    playlist.value = await playlistsApi.get(playlistId.value)
    editName.value = playlist.value.name
  } catch {
    uiStore.addNotification({ type: 'error', message: 'Failed to load playlist' })
    router.push('/playlists')
  } finally {
    loading.value = false
  }
}

onMounted(() => fetchPlaylist())

async function handleSaveName() {
  if (!playlist.value || !editName.value.trim()) return
  saving.value = true
  try {
    const updated = await playlistsApi.update(playlistId.value, { name: editName.value.trim() })
    playlist.value.name = updated.name
    isEditingName.value = false
    uiStore.addNotification({ type: 'success', message: 'Playlist renamed' })
  } catch {
    uiStore.addNotification({ type: 'error', message: 'Failed to rename playlist' })
  } finally {
    saving.value = false
  }
}

async function handleDelete() {
  if (!playlist.value || !confirm(`Delete "${playlist.value.name}"?`)) return
  deleting.value = true
  try {
    await playlistsApi.delete(playlistId.value)
    uiStore.addNotification({ type: 'success', message: 'Playlist deleted' })
    router.push('/playlists')
  } catch {
    uiStore.addNotification({ type: 'error', message: 'Failed to delete playlist' })
  } finally {
    deleting.value = false
  }
}

async function handleRemoveItem(item: PlaylistItem) {
  removingItemId.value = item.id
  try {
    await playlistsApi.removeItem(playlistId.value, item.id)
    if (playlist.value?.items) {
      playlist.value.items = playlist.value.items.filter((i) => i.id !== item.id)
    }
  } catch {
    uiStore.addNotification({ type: 'error', message: 'Failed to remove item' })
  } finally {
    removingItemId.value = null
  }
}

async function moveItem(index: number, direction: 'up' | 'down') {
  if (!playlist.value?.items) return
  const items = [...playlist.value.items]
  const newIndex = direction === 'up' ? index - 1 : index + 1
  if (newIndex < 0 || newIndex >= items.length) return

  const temp = items[index]!
  items[index] = items[newIndex]!
  items[newIndex] = temp
  playlist.value.items = items

  try {
    await playlistsApi.reorderItems(playlistId.value, { item_ids: items.map((i) => i.id) })
  } catch {
    uiStore.addNotification({ type: 'error', message: 'Failed to reorder items' })
    await fetchPlaylist()
  }
}

async function handleShare() {
  try {
    const share = await playlistsApi.share(playlistId.value, { expires_in_hours: 24 })
    activeShare.value = share
  } catch {
    uiStore.addNotification({ type: 'error', message: 'Failed to create share' })
  }
}

function handleShareRevoked() {
  activeShare.value = null
  uiStore.addNotification({ type: 'success', message: 'Share revoked' })
}
</script>

<template>
  <div class="p-6 max-w-3xl mx-auto">
    <div v-if="loading" class="animate-pulse space-y-4">
      <div class="h-8 bg-slate-200 rounded-xl w-1/3" />
      <div class="h-4 bg-slate-200 rounded-xl w-1/2" />
    </div>

    <div v-else-if="playlist">
      <!-- Header -->
      <div class="flex items-start justify-between mb-6 gap-4">
        <div class="flex-1">
          <div v-if="isEditingName" class="flex gap-2 items-center">
            <input
              v-model="editName"
              class="flex-1 text-xl font-bold border-b-2 border-indigo-500 focus:outline-none bg-transparent text-slate-900"
              @keydown.enter="handleSaveName"
              @keydown.escape="isEditingName = false"
              autofocus
            />
            <button @click="handleSaveName" :disabled="saving" class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 disabled:opacity-50">
              {{ saving ? 'Saving…' : 'Save' }}
            </button>
            <button @click="isEditingName = false" class="px-4 py-2 text-slate-500 rounded-xl hover:bg-slate-100 text-sm">Cancel</button>
          </div>
          <div v-else class="flex items-center gap-2">
            <h1 class="text-2xl font-bold text-slate-900">{{ playlist.name }}</h1>
            <button
              @click="isEditingName = true"
              class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100"
              aria-label="Edit name"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
              </svg>
            </button>
          </div>
          <p class="text-sm text-slate-400 mt-0.5">{{ playlist.items?.length ?? 0 }} {{ (playlist.items?.length ?? 0) === 1 ? 'item' : 'items' }}</p>
        </div>

        <div class="flex items-center gap-2">
          <button @click="handleShare" class="flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-indigo-600 border border-indigo-200 bg-indigo-50 rounded-xl hover:bg-indigo-100 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
            </svg>
            Share
          </button>
          <button @click="handleDelete" :disabled="deleting" class="flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-red-600 border border-red-200 bg-red-50 rounded-xl hover:bg-red-100 transition-colors disabled:opacity-50">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
            {{ deleting ? 'Deleting…' : 'Delete' }}
          </button>
        </div>
      </div>

      <!-- Items -->
      <div v-if="!playlist.items?.length" class="flex flex-col items-center justify-center py-16 text-center bg-white rounded-2xl border border-slate-200">
        <svg class="w-10 h-10 text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
        </svg>
        <p class="text-sm text-slate-500">No items in this playlist yet</p>
        <p class="text-xs text-slate-400 mt-1">Add items from Search or Library</p>
      </div>

      <div v-else class="space-y-2">
        <div
          v-for="(item, index) in playlist.items"
          :key="item.id"
          class="bg-white rounded-xl border border-slate-200 p-3.5 flex items-center gap-3 hover:border-slate-300 transition-colors"
        >
          <span class="text-xs font-mono text-slate-400 w-5 text-center shrink-0">{{ index + 1 }}</span>
          <div class="w-12 h-8 bg-slate-100 rounded-lg overflow-hidden shrink-0">
            <img
              v-if="item.asset?.thumbnail_urls?.['160']"
              :src="item.asset.thumbnail_urls['160']"
              :alt="item.asset?.title"
              class="w-full h-full object-cover"
            />
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-slate-900 truncate">
              {{ item.asset?.title ?? `Asset #${item.asset_id}` }}
            </p>
          </div>
          <div class="flex items-center gap-0.5 shrink-0">
            <button @click="moveItem(index, 'up')" :disabled="index === 0" class="p-1.5 text-slate-400 hover:text-slate-600 disabled:opacity-30 rounded-lg hover:bg-slate-100 transition-colors">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" /></svg>
            </button>
            <button @click="moveItem(index, 'down')" :disabled="index === (playlist.items?.length ?? 0) - 1" class="p-1.5 text-slate-400 hover:text-slate-600 disabled:opacity-30 rounded-lg hover:bg-slate-100 transition-colors">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
            </button>
            <button @click="handleRemoveItem(item)" :disabled="removingItemId === item.id" class="p-1.5 text-slate-400 hover:text-red-500 disabled:opacity-50 rounded-lg hover:bg-red-50 transition-colors">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <ShareDialog
      v-if="activeShare && playlist"
      :share="activeShare"
      :playlist-id="playlistId"
      @close="activeShare = null"
      @revoked="handleShareRevoked"
    />
  </div>
</template>
