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
  <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div v-if="loading" class="animate-pulse space-y-4">
      <div class="h-8 bg-gray-200 rounded w-1/3" />
      <div class="h-4 bg-gray-200 rounded w-1/2" />
    </div>

    <div v-else-if="playlist">
      <!-- Header -->
      <div class="flex items-start justify-between mb-6 gap-4">
        <div class="flex-1">
          <div v-if="isEditingName" class="flex gap-2 items-center">
            <input
              v-model="editName"
              class="flex-1 min-h-[44px] text-xl font-bold border-b-2 border-blue-500 focus:outline-none bg-transparent"
              @keydown.enter="handleSaveName"
              @keydown.escape="isEditingName = false"
              autofocus
            />
            <button
              @click="handleSaveName"
              :disabled="saving"
              class="min-h-[44px] px-4 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 disabled:opacity-50"
            >
              {{ saving ? 'Saving…' : 'Save' }}
            </button>
            <button
              @click="isEditingName = false"
              class="min-h-[44px] px-4 text-gray-500 rounded-lg hover:bg-gray-100"
            >
              Cancel
            </button>
          </div>
          <div v-else class="flex items-center gap-2">
            <h1 class="text-2xl font-bold text-gray-900">{{ playlist.name }}</h1>
            <button
              @click="isEditingName = true"
              class="min-h-[36px] min-w-[36px] flex items-center justify-center text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100"
              aria-label="Edit name"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
              </svg>
            </button>
          </div>
          <p class="text-sm text-gray-400 mt-1">{{ playlist.items?.length ?? 0 }} items</p>
        </div>

        <div class="flex items-center gap-2">
          <button
            @click="handleShare"
            class="min-h-[44px] px-4 text-sm font-semibold text-blue-600 border border-blue-200 rounded-lg hover:bg-blue-50 transition-colors"
          >
            Share
          </button>
          <button
            @click="handleDelete"
            :disabled="deleting"
            class="min-h-[44px] px-4 text-sm font-semibold text-red-600 border border-red-200 rounded-lg hover:bg-red-50 transition-colors disabled:opacity-50"
          >
            {{ deleting ? 'Deleting…' : 'Delete' }}
          </button>
        </div>
      </div>

      <!-- Items list -->
      <div v-if="!playlist.items?.length" class="text-center py-12 text-gray-400">
        No items in this playlist yet.
      </div>

      <div v-else class="space-y-2">
        <div
          v-for="(item, index) in playlist.items"
          :key="item.id"
          class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-4"
        >
          <!-- Position number -->
          <span class="text-sm font-mono text-gray-400 w-6 text-right shrink-0">{{ index + 1 }}</span>

          <!-- Thumbnail -->
          <div class="w-12 h-8 bg-gray-200 rounded overflow-hidden shrink-0">
            <img
              v-if="item.asset?.thumbnail_urls?.['160']"
              :src="item.asset.thumbnail_urls['160']"
              :alt="item.asset?.title"
              class="w-full h-full object-cover"
            />
          </div>

          <!-- Info -->
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-900 truncate">
              {{ item.asset?.title ?? `Asset #${item.asset_id}` }}
            </p>
          </div>

          <!-- Controls -->
          <div class="flex items-center gap-1 shrink-0">
            <button
              @click="moveItem(index, 'up')"
              :disabled="index === 0"
              class="min-h-[36px] min-w-[36px] flex items-center justify-center text-gray-400 hover:text-gray-600 disabled:opacity-30 rounded"
              aria-label="Move up"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
              </svg>
            </button>
            <button
              @click="moveItem(index, 'down')"
              :disabled="index === (playlist.items?.length ?? 0) - 1"
              class="min-h-[36px] min-w-[36px] flex items-center justify-center text-gray-400 hover:text-gray-600 disabled:opacity-30 rounded"
              aria-label="Move down"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            <button
              @click="handleRemoveItem(item)"
              :disabled="removingItemId === item.id"
              class="min-h-[36px] min-w-[36px] flex items-center justify-center text-gray-400 hover:text-red-600 disabled:opacity-50 rounded"
              aria-label="Remove item"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Share dialog -->
    <ShareDialog
      v-if="activeShare && playlist"
      :share="activeShare"
      :playlist-id="playlistId"
      @close="activeShare = null"
      @revoked="handleShareRevoked"
    />
  </div>
</template>
