<script setup lang="ts">
import { ref, onMounted } from 'vue'
import type { Asset, Playlist } from '@/types/api'
import { playlistsApi } from '@/services/api'
import { useUiStore } from '@/stores/ui'

const props = defineProps<{
  asset: Asset
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

const uiStore = useUiStore()
const playlists = ref<Playlist[]>([])
const loading = ref(true)
const adding = ref<number | null>(null)

onMounted(async () => {
  try {
    playlists.value = await playlistsApi.list()
  } catch {
    uiStore.addNotification({ type: 'error', message: 'Failed to load playlists' })
  } finally {
    loading.value = false
  }
})

async function addToPlaylist(playlist: Playlist) {
  adding.value = playlist.id
  try {
    await playlistsApi.addItem(playlist.id, { asset_id: props.asset.id })
    uiStore.addNotification({ type: 'success', message: `Added to "${playlist.name}"` })
    emit('close')
  } catch {
    uiStore.addNotification({ type: 'error', message: 'Failed to add to playlist' })
  } finally {
    adding.value = null
  }
}
</script>

<template>
  <div
    class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
    @click.self="emit('close')"
  >
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6">
      <h2 class="text-lg font-bold text-gray-900 mb-1">Add to Playlist</h2>
      <p class="text-sm text-gray-500 mb-4 truncate">{{ asset.title }}</p>

      <div v-if="loading" class="py-8 text-center text-gray-400">Loading playlists…</div>

      <div v-else-if="playlists.length === 0" class="py-8 text-center text-gray-400">
        No playlists yet. Create one first.
      </div>

      <div v-else class="flex flex-col gap-2 max-h-60 overflow-y-auto">
        <button
          v-for="pl in playlists"
          :key="pl.id"
          @click="addToPlaylist(pl)"
          :disabled="adding === pl.id"
          class="w-full min-h-[44px] text-left px-4 py-2 rounded-lg hover:bg-gray-50 border border-gray-200 disabled:opacity-50 transition-colors"
        >
          <span class="font-medium text-gray-900">{{ pl.name }}</span>
          <span class="text-xs text-gray-400 ml-2">{{ pl.items_count ?? pl.items?.length ?? 0 }} items</span>
        </button>
      </div>

      <button
        @click="emit('close')"
        class="w-full mt-4 min-h-[44px] text-gray-600 font-semibold rounded-lg hover:bg-gray-100 transition-colors"
      >
        Cancel
      </button>
    </div>
  </div>
</template>
