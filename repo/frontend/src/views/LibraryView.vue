<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import type { Asset } from '@/types/api'
import { assetsApi } from '@/services/api'
import { useUiStore } from '@/stores/ui'
import AssetTile from '@/components/AssetTile.vue'
import AddToPlaylistDialog from '@/components/AddToPlaylistDialog.vue'

const uiStore = useUiStore()

const assets = ref<Asset[]>([])
const nextCursor = ref<string | null>(null)
const loading = ref(false)
const loadingMore = ref(false)
const sort = ref<'played_count' | 'created_at'>('created_at')

const addToPlaylistAsset = ref<Asset | null>(null)

async function fetchAssets(reset = false) {
  if (reset) {
    loading.value = true
    assets.value = []
    nextCursor.value = null
  } else {
    loadingMore.value = true
  }

  try {
    const result = await assetsApi.list({
      cursor: reset ? undefined : nextCursor.value ?? undefined,
      sort: sort.value,
      limit: 24,
    })
    if (reset) {
      assets.value = result.items
    } else {
      assets.value.push(...result.items)
    }
    nextCursor.value = result.next_cursor
  } catch {
    uiStore.addNotification({ type: 'error', message: 'Failed to load library' })
  } finally {
    loading.value = false
    loadingMore.value = false
  }
}

onMounted(() => fetchAssets(true))
watch(sort, () => fetchAssets(true))
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Library</h1>
      <div class="flex items-center gap-2">
        <span class="text-xs text-gray-500 font-medium">Sort:</span>
        <button
          @click="sort = 'played_count'"
          :class="['min-h-[36px] px-3 text-sm rounded-full border transition-colors', sort === 'played_count' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-300 hover:border-blue-400']"
        >Most Played</button>
        <button
          @click="sort = 'created_at'"
          :class="['min-h-[36px] px-3 text-sm rounded-full border transition-colors', sort === 'created_at' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-300 hover:border-blue-400']"
        >Newest</button>
      </div>
    </div>

    <div v-if="loading" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4">
      <div v-for="n in 12" :key="n" class="bg-gray-200 rounded-xl animate-pulse aspect-video" />
    </div>

    <div v-else-if="assets.length === 0" class="text-center py-16 text-gray-400">
      No media in library yet.
    </div>

    <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4">
      <AssetTile
        v-for="asset in assets"
        :key="asset.id"
        :asset="asset"
        @add-to-playlist="addToPlaylistAsset = $event"
      />
    </div>

    <div v-if="nextCursor" class="flex justify-center mt-8">
      <button
        @click="fetchAssets(false)"
        :disabled="loadingMore"
        class="min-h-[44px] px-8 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 disabled:opacity-50"
      >
        {{ loadingMore ? 'Loading…' : 'Load More' }}
      </button>
    </div>

    <AddToPlaylistDialog
      v-if="addToPlaylistAsset"
      :asset="addToPlaylistAsset"
      @close="addToPlaylistAsset = null"
    />
  </div>
</template>
