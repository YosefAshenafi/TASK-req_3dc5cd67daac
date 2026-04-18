<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import type { Asset } from '@/types/api'
import { assetsApi } from '@/services/api'
import { useUiStore } from '@/stores/ui'
import AssetTile from '@/components/AssetTile.vue'
import AddToPlaylistDialog from '@/components/AddToPlaylistDialog.vue'
import { Library, Loader2 } from 'lucide-vue-next'

const uiStore = useUiStore()

const assets = ref<Asset[]>([])
const nextCursor = ref<string | null>(null)
const loading = ref(false)
const loadingMore = ref(false)
const sort = ref<'most_played' | 'newest'>('newest')

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
    if (reset) assets.value = result.items
    else assets.value.push(...result.items)
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
  <div class="p-6 max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Library</h1>
        <p class="text-sm text-slate-500 mt-0.5">All available media assets</p>
      </div>
      <div class="flex items-center gap-1.5 bg-white border border-slate-200 rounded-xl p-1 shadow-sm">
        <button
          @click="sort = 'newest'"
          :class="[
            'px-3 py-1.5 text-xs font-semibold rounded-lg transition-all duration-150',
            sort === 'newest'
              ? 'bg-indigo-600 text-white shadow-sm'
              : 'text-slate-600 hover:text-slate-900'
          ]"
        >Newest</button>
        <button
          @click="sort = 'most_played'"
          :class="[
            'px-3 py-1.5 text-xs font-semibold rounded-lg transition-all duration-150',
            sort === 'most_played'
              ? 'bg-indigo-600 text-white shadow-sm'
              : 'text-slate-600 hover:text-slate-900'
          ]"
        >Most Played</button>
      </div>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
      <div v-for="n in 10" :key="n" class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="aspect-video bg-slate-200 animate-pulse" />
        <div class="p-3 space-y-2">
          <div class="h-3 bg-slate-200 rounded-full animate-pulse w-3/4" />
          <div class="h-3 bg-slate-200 rounded-full animate-pulse w-1/2" />
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else-if="assets.length === 0" class="flex flex-col items-center justify-center py-24 text-center">
      <div class="w-16 h-16 rounded-2xl bg-slate-100 flex items-center justify-center mb-4">
        <Library class="w-7 h-7 text-slate-400" />
      </div>
      <h3 class="text-base font-semibold text-slate-700 mb-1">Library is empty</h3>
      <p class="text-sm text-slate-400">Upload media assets to get started</p>
    </div>

    <!-- Asset grid -->
    <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
      <AssetTile
        v-for="asset in assets"
        :key="asset.id"
        :asset="asset"
        @add-to-playlist="addToPlaylistAsset = $event"
      />
    </div>

    <!-- Load more -->
    <div v-if="nextCursor && !loading" class="flex justify-center mt-8">
      <button
        @click="fetchAssets(false)"
        :disabled="loadingMore"
        class="flex items-center gap-2 px-6 py-2.5 bg-white border border-slate-200 text-slate-700 text-sm font-semibold rounded-xl hover:border-indigo-300 hover:text-indigo-600 disabled:opacity-50 transition-all shadow-sm"
      >
        <Loader2 v-if="loadingMore" class="w-4 h-4 animate-spin" />
        {{ loadingMore ? 'Loading…' : 'Load more' }}
      </button>
    </div>

    <AddToPlaylistDialog
      v-if="addToPlaylistAsset"
      :asset="addToPlaylistAsset"
      @close="addToPlaylistAsset = null"
    />
  </div>
</template>
