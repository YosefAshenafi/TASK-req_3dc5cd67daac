<script setup lang="ts">
import { ref, watch, onMounted, computed } from 'vue'
import type { Asset } from '@/types/api'
import { searchApi } from '@/services/api'
import { useUiStore } from '@/stores/ui'
import { useSettingsStore } from '@/stores/settings'
import AssetTile from '@/components/AssetTile.vue'
import AddToPlaylistDialog from '@/components/AddToPlaylistDialog.vue'
import { Search, AlertTriangle, Loader2, SlidersHorizontal } from 'lucide-vue-next'

const uiStore = useUiStore()
const settingsStore = useSettingsStore()

const query = ref('')
const selectedTags = ref<string[]>([])
const maxDuration = ref<number | null>(null)
const recencyDays = ref<number | null>(null)
const sort = ref<'most_played' | 'newest' | 'recommended'>('recommended')

const assets = ref<Asset[]>([])
const nextCursor = ref<string | null>(null)
const degraded = ref(false)
const loading = ref(false)
const loadingMore = ref(false)

const addToPlaylistAsset = ref<Asset | null>(null)

let debounceTimer: ReturnType<typeof setTimeout> | null = null
let abortController: AbortController | null = null

const availableTags = computed(() => settingsStore.availableTags)

const durationOptions = [
  { label: 'Any', value: null },
  { label: '< 2 min', value: 120 },
  { label: '< 5 min', value: 300 },
]

const recencyOptions = [
  { label: 'All time', value: null },
  { label: '30 days', value: 30 },
  { label: '90 days', value: 90 },
]

const sortOptions = [
  { label: 'Recommended', value: 'recommended' as const },
  { label: 'Most Played', value: 'most_played' as const },
  { label: 'Newest', value: 'newest' as const },
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
        duration_lt: maxDuration.value ?? undefined,
        recent_days: recencyDays.value ?? undefined,
        sort: sort.value,
        cursor: reset ? undefined : nextCursor.value ?? undefined,
        per_page: 24,
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

onMounted(() => fetchAssets(true))

function toggleTag(tag: string) {
  const idx = selectedTags.value.indexOf(tag)
  if (idx >= 0) selectedTags.value.splice(idx, 1)
  else selectedTags.value.push(tag)
}
</script>

<template>
  <div class="p-6 max-w-7xl mx-auto">
    <!-- Page header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Search</h1>
        <p class="text-sm text-slate-500 mt-0.5">Find and discover media assets</p>
      </div>
      <div
        v-if="degraded"
        class="flex items-center gap-2 text-xs font-semibold text-amber-700 bg-amber-50 border border-amber-200 px-3 py-2 rounded-xl"
      >
        <AlertTriangle class="w-3.5 h-3.5" />
        Recommendations degraded
      </div>
    </div>

    <!-- Search bar -->
    <div class="relative mb-5">
      <Search class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
      <input
        v-model="query"
        type="search"
        placeholder="Search by title, tag, or keyword…"
        class="w-full pl-11 pr-4 py-3 text-sm bg-white border border-slate-200 rounded-xl text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 shadow-sm transition-shadow"
      />
    </div>

    <!-- Filters -->
    <div class="bg-white border border-slate-200 rounded-xl p-4 mb-6 shadow-sm">
      <div class="flex items-center gap-2 mb-3">
        <SlidersHorizontal class="w-3.5 h-3.5 text-slate-400" />
        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Filters</span>
      </div>

      <div class="space-y-3">
        <!-- Tags -->
        <div class="flex items-start gap-3">
          <span class="text-xs font-medium text-slate-500 w-16 pt-1.5 shrink-0">Tags</span>
          <div class="flex flex-wrap gap-1.5">
            <button
              v-for="tag in availableTags"
              :key="tag"
              @click="toggleTag(tag)"
              :class="[
                'px-3 py-1 text-xs rounded-full border font-medium transition-all duration-150',
                selectedTags.includes(tag)
                  ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm shadow-indigo-600/20'
                  : 'bg-white text-slate-600 border-slate-200 hover:border-indigo-300 hover:text-indigo-600'
              ]"
            >
              {{ tag }}
            </button>
          </div>
        </div>

        <!-- Duration + Recency + Sort -->
        <div class="flex flex-wrap gap-4">
          <div class="flex items-center gap-2">
            <span class="text-xs font-medium text-slate-500 whitespace-nowrap">Duration</span>
            <div class="flex gap-1">
              <button
                v-for="opt in durationOptions"
                :key="String(opt.value)"
                @click="maxDuration = opt.value"
                :class="[
                  'px-2.5 py-1 text-xs rounded-lg border font-medium transition-all duration-150',
                  maxDuration === opt.value
                    ? 'bg-indigo-600 text-white border-indigo-600'
                    : 'bg-white text-slate-600 border-slate-200 hover:border-indigo-300'
                ]"
              >{{ opt.label }}</button>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <span class="text-xs font-medium text-slate-500 whitespace-nowrap">Added</span>
            <div class="flex gap-1">
              <button
                v-for="opt in recencyOptions"
                :key="String(opt.value)"
                @click="recencyDays = opt.value"
                :class="[
                  'px-2.5 py-1 text-xs rounded-lg border font-medium transition-all duration-150',
                  recencyDays === opt.value
                    ? 'bg-indigo-600 text-white border-indigo-600'
                    : 'bg-white text-slate-600 border-slate-200 hover:border-indigo-300'
                ]"
              >{{ opt.label }}</button>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <span class="text-xs font-medium text-slate-500 whitespace-nowrap">Sort</span>
            <div class="flex gap-1">
              <button
                v-for="opt in sortOptions"
                :key="opt.value"
                @click="sort = opt.value"
                :class="[
                  'px-2.5 py-1 text-xs rounded-lg border font-medium transition-all duration-150',
                  sort === opt.value
                    ? 'bg-indigo-600 text-white border-indigo-600'
                    : 'bg-white text-slate-600 border-slate-200 hover:border-indigo-300'
                ]"
              >{{ opt.label }}</button>
            </div>
          </div>
        </div>
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
        <Search class="w-7 h-7 text-slate-400" />
      </div>
      <h3 class="text-base font-semibold text-slate-700 mb-1">No results found</h3>
      <p class="text-sm text-slate-400">Try adjusting your search or filters</p>
    </div>

    <!-- Asset grid -->
    <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
      <AssetTile
        v-for="asset in assets"
        :key="asset.id"
        :asset="asset"
        :show-reason-tags="sort === 'recommended'"
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
        {{ loadingMore ? 'Loading…' : 'Load more results' }}
      </button>
    </div>

    <AddToPlaylistDialog
      v-if="addToPlaylistAsset"
      :asset="addToPlaylistAsset"
      @close="addToPlaylistAsset = null"
    />
  </div>
</template>
