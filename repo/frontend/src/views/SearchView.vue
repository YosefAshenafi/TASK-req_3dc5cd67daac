<script setup lang="ts">
import { ref, watch, computed, onMounted } from 'vue'
import type { Asset } from '@/types/api'
import { searchApi } from '@/services/api'
import { useUiStore } from '@/stores/ui'
import AssetTile from '@/components/AssetTile.vue'
import AddToPlaylistDialog from '@/components/AddToPlaylistDialog.vue'

const uiStore = useUiStore()

const query = ref('')
const selectedTags = ref<string[]>([])
const maxDuration = ref<number | null>(null)
const recencyDays = ref<number | null>(null)
const sort = ref<'played_count' | 'created_at' | 'recommended'>('recommended')

const assets = ref<Asset[]>([])
const nextCursor = ref<string | null>(null)
const degraded = ref(false)
const loading = ref(false)
const loadingMore = ref(false)

const addToPlaylistAsset = ref<Asset | null>(null)

let debounceTimer: ReturnType<typeof setTimeout> | null = null
let abortController: AbortController | null = null

const availableTags = ['Safety', 'Overnight', 'Gate Issues', 'Parking', 'Event', 'General', 'Emergency']

const durationOptions = [
  { label: '< 2 min', value: 120 },
  { label: '< 5 min', value: 300 },
  { label: 'Any', value: null },
]

const recencyOptions = [
  { label: '30 days', value: 30 },
  { label: '90 days', value: 90 },
  { label: 'All time', value: null },
]

async function fetchAssets(reset = false) {
  if (abortController) abortController.abort()
  abortController = new AbortController()

  if (reset) {
    loading.value = true
    assets.value = []
    nextCursor.value = null
  } else {
    loadingMore.value = true
  }

  try {
    const result = await searchApi.search(
      {
        q: query.value || undefined,
        tags: selectedTags.value.length ? selectedTags.value : undefined,
        max_duration_seconds: maxDuration.value ?? undefined,
        recency_days: recencyDays.value ?? undefined,
        sort: sort.value,
        cursor: reset ? undefined : nextCursor.value ?? undefined,
        limit: 24,
      },
      abortController.signal,
    )

    if (reset) {
      assets.value = result.data.items
    } else {
      assets.value.push(...result.data.items)
    }
    nextCursor.value = result.data.next_cursor
    degraded.value = result.degraded
  } catch (err: any) {
    if (err?.name !== 'AbortError') {
      uiStore.addNotification({ type: 'error', message: 'Search failed' })
    }
  } finally {
    loading.value = false
    loadingMore.value = false
  }
}

function triggerSearch() {
  if (debounceTimer) clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => fetchAssets(true), 300)
}

watch([query, selectedTags, maxDuration, recencyDays, sort], () => {
  triggerSearch()
})

onMounted(() => {
  fetchAssets(true)
})

function toggleTag(tag: string) {
  const idx = selectedTags.value.indexOf(tag)
  if (idx >= 0) {
    selectedTags.value.splice(idx, 1)
  } else {
    selectedTags.value.push(tag)
  }
}

function loadMore() {
  if (nextCursor.value && !loadingMore.value) {
    fetchAssets(false)
  }
}
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Search</h1>
      <div v-if="degraded" class="flex items-center gap-2 text-xs font-semibold text-yellow-700 bg-yellow-100 px-3 py-1.5 rounded-full">
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
        </svg>
        Recommendations degraded
      </div>
    </div>

    <!-- Search input -->
    <div class="relative mb-4">
      <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
      </svg>
      <input
        v-model="query"
        type="search"
        placeholder="Search media..."
        class="w-full min-h-[48px] pl-10 pr-4 text-base border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500"
      />
    </div>

    <!-- Filters row -->
    <div class="flex flex-wrap gap-3 mb-6">
      <!-- Tag filter chips -->
      <div class="flex flex-wrap gap-2">
        <button
          v-for="tag in availableTags"
          :key="tag"
          @click="toggleTag(tag)"
          :class="[
            'min-h-[36px] px-3 text-sm rounded-full border transition-colors',
            selectedTags.includes(tag)
              ? 'bg-blue-600 text-white border-blue-600'
              : 'bg-white text-gray-600 border-gray-300 hover:border-blue-400'
          ]"
        >
          {{ tag }}
        </button>
      </div>

      <!-- Duration filter -->
      <div class="flex items-center gap-2">
        <span class="text-xs text-gray-500 font-medium">Duration:</span>
        <div class="flex gap-1">
          <button
            v-for="opt in durationOptions"
            :key="String(opt.value)"
            @click="maxDuration = opt.value"
            :class="[
              'min-h-[36px] px-3 text-sm rounded-full border transition-colors',
              maxDuration === opt.value
                ? 'bg-blue-600 text-white border-blue-600'
                : 'bg-white text-gray-600 border-gray-300 hover:border-blue-400'
            ]"
          >
            {{ opt.label }}
          </button>
        </div>
      </div>

      <!-- Recency filter -->
      <div class="flex items-center gap-2">
        <span class="text-xs text-gray-500 font-medium">Added:</span>
        <div class="flex gap-1">
          <button
            v-for="opt in recencyOptions"
            :key="String(opt.value)"
            @click="recencyDays = opt.value"
            :class="[
              'min-h-[36px] px-3 text-sm rounded-full border transition-colors',
              recencyDays === opt.value
                ? 'bg-blue-600 text-white border-blue-600'
                : 'bg-white text-gray-600 border-gray-300 hover:border-blue-400'
            ]"
          >
            {{ opt.label }}
          </button>
        </div>
      </div>

      <!-- Sort toggle -->
      <div class="flex items-center gap-2">
        <span class="text-xs text-gray-500 font-medium">Sort:</span>
        <div class="flex gap-1">
          <button
            @click="sort = 'played_count'"
            :class="['min-h-[36px] px-3 text-sm rounded-full border transition-colors', sort === 'played_count' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-300 hover:border-blue-400']"
          >Most Played</button>
          <button
            @click="sort = 'created_at'"
            :class="['min-h-[36px] px-3 text-sm rounded-full border transition-colors', sort === 'created_at' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-300 hover:border-blue-400']"
          >Newest</button>
          <button
            @click="sort = 'recommended'"
            :class="['min-h-[36px] px-3 text-sm rounded-full border transition-colors', sort === 'recommended' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-300 hover:border-blue-400']"
          >Recommended</button>
        </div>
      </div>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4">
      <div
        v-for="n in 12"
        :key="n"
        class="bg-gray-200 rounded-xl animate-pulse aspect-video"
      />
    </div>

    <!-- Empty state -->
    <div v-else-if="assets.length === 0" class="text-center py-16 text-gray-400">
      <svg class="w-12 h-12 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <p>No media found</p>
    </div>

    <!-- Asset grid -->
    <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4">
      <AssetTile
        v-for="asset in assets"
        :key="asset.id"
        :asset="asset"
        :show-reason-tags="sort === 'recommended'"
        @add-to-playlist="addToPlaylistAsset = $event"
      />
    </div>

    <!-- Load more -->
    <div v-if="nextCursor" class="flex justify-center mt-8">
      <button
        @click="loadMore"
        :disabled="loadingMore"
        class="min-h-[44px] px-8 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors"
      >
        {{ loadingMore ? 'Loading…' : 'Load More' }}
      </button>
    </div>

    <!-- Add to playlist dialog -->
    <AddToPlaylistDialog
      v-if="addToPlaylistAsset"
      :asset="addToPlaylistAsset"
      @close="addToPlaylistAsset = null"
    />
  </div>
</template>
