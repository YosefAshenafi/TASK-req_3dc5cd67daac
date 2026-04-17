<script setup lang="ts">
import { ref, onMounted } from 'vue'
import type { Favorite } from '@/types/api'
import { favoritesApi } from '@/services/api'
import { useUiStore } from '@/stores/ui'
import AssetTile from '@/components/AssetTile.vue'
import AddToPlaylistDialog from '@/components/AddToPlaylistDialog.vue'

const uiStore = useUiStore()
const favorites = ref<Favorite[]>([])
const nextCursor = ref<string | null>(null)
const loading = ref(false)
const loadingMore = ref(false)
const addToPlaylistFav = ref<Favorite | null>(null)

async function fetchFavorites(reset = false) {
  if (reset) {
    loading.value = true
    favorites.value = []
    nextCursor.value = null
  } else {
    loadingMore.value = true
  }

  try {
    const result = await favoritesApi.list(reset ? undefined : nextCursor.value ?? undefined)
    if (reset) {
      favorites.value = result.items
    } else {
      favorites.value.push(...result.items)
    }
    nextCursor.value = result.next_cursor
  } catch {
    uiStore.addNotification({ type: 'error', message: 'Failed to load favorites' })
  } finally {
    loading.value = false
    loadingMore.value = false
  }
}

onMounted(() => fetchFavorites(true))

function handleUnfavorited(assetId: number) {
  favorites.value = favorites.value.filter((f) => f.asset_id !== assetId)
}
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Favorites</h1>

    <div v-if="loading" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4">
      <div v-for="n in 12" :key="n" class="bg-gray-200 rounded-xl animate-pulse aspect-video" />
    </div>

    <div v-else-if="favorites.length === 0" class="text-center py-16 text-gray-400">
      <svg class="w-12 h-12 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
      </svg>
      No favorites yet. Heart an asset to add it here.
    </div>

    <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4">
      <AssetTile
        v-for="fav in favorites"
        :key="fav.asset_id"
        :asset="fav.asset!"
        :is-favorited="true"
        @unfavorited="handleUnfavorited"
        @add-to-playlist="addToPlaylistFav = fav"
      />
    </div>

    <div v-if="nextCursor" class="flex justify-center mt-8">
      <button
        @click="fetchFavorites(false)"
        :disabled="loadingMore"
        class="min-h-[44px] px-8 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 disabled:opacity-50"
      >
        {{ loadingMore ? 'Loading…' : 'Load More' }}
      </button>
    </div>

    <AddToPlaylistDialog
      v-if="addToPlaylistFav?.asset"
      :asset="addToPlaylistFav.asset"
      @close="addToPlaylistFav = null"
    />
  </div>
</template>
