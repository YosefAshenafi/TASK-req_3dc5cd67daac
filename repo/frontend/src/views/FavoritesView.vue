<script setup lang="ts">
import { ref, onMounted } from 'vue'
import type { Favorite } from '@/types/api'
import { favoritesApi } from '@/services/api'
import { useUiStore } from '@/stores/ui'
import AssetTile from '@/components/AssetTile.vue'
import AddToPlaylistDialog from '@/components/AddToPlaylistDialog.vue'
import { Heart, Loader2 } from 'lucide-vue-next'

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
    if (reset) favorites.value = result.items
    else favorites.value.push(...result.items)
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
  <div class="p-6 max-w-7xl mx-auto">
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-slate-900">Favorites</h1>
      <p class="text-sm text-slate-500 mt-0.5">Your saved media assets</p>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
      <div v-for="n in 8" :key="n" class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="aspect-video bg-slate-200 animate-pulse" />
        <div class="p-3 space-y-2">
          <div class="h-3 bg-slate-200 rounded-full animate-pulse w-3/4" />
          <div class="h-3 bg-slate-200 rounded-full animate-pulse w-1/2" />
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else-if="favorites.length === 0" class="flex flex-col items-center justify-center py-24 text-center">
      <div class="w-16 h-16 rounded-2xl bg-red-50 flex items-center justify-center mb-4">
        <Heart class="w-7 h-7 text-red-300" />
      </div>
      <h3 class="text-base font-semibold text-slate-700 mb-1">No favorites yet</h3>
      <p class="text-sm text-slate-400">Heart an asset to save it here</p>
    </div>

    <!-- Grid -->
    <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
      <AssetTile
        v-for="fav in favorites"
        :key="fav.asset_id"
        :asset="fav.asset!"
        :is-favorited="true"
        @unfavorited="handleUnfavorited"
        @add-to-playlist="addToPlaylistFav = fav"
      />
    </div>

    <!-- Load more -->
    <div v-if="nextCursor && !loading" class="flex justify-center mt-8">
      <button
        @click="fetchFavorites(false)"
        :disabled="loadingMore"
        class="flex items-center gap-2 px-6 py-2.5 bg-white border border-slate-200 text-slate-700 text-sm font-semibold rounded-xl hover:border-indigo-300 hover:text-indigo-600 disabled:opacity-50 transition-all shadow-sm"
      >
        <Loader2 v-if="loadingMore" class="w-4 h-4 animate-spin" />
        {{ loadingMore ? 'Loading…' : 'Load more' }}
      </button>
    </div>

    <AddToPlaylistDialog
      v-if="addToPlaylistFav?.asset"
      :asset="addToPlaylistFav.asset"
      @close="addToPlaylistFav = null"
    />
  </div>
</template>
