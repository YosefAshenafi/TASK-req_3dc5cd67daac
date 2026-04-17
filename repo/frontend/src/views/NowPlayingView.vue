<script setup lang="ts">
import { onMounted } from 'vue'
import { usePlayerStore } from '@/stores/player'
import { historyApi } from '@/services/api'
import { useUiStore } from '@/stores/ui'

const playerStore = usePlayerStore()
const uiStore = useUiStore()

onMounted(async () => {
  try {
    const result = await historyApi.list()
    playerStore.setRecentPlays(result.items)
  } catch {
    // ignore
  }
})

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleString()
}

function formatDuration(secs?: number): string {
  if (!secs) return ''
  const m = Math.floor(secs / 60)
  const s = Math.floor(secs % 60)
  return `${m}:${String(s).padStart(2, '0')}`
}
</script>

<template>
  <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Now Playing</h1>

    <!-- Current item -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-6">
      <div v-if="playerStore.currentAsset">
        <div class="flex gap-4 items-center mb-4">
          <div class="w-20 h-14 bg-gray-200 rounded-lg overflow-hidden shrink-0">
            <img
              v-if="playerStore.currentAsset.thumbnail_urls?.['160']"
              :src="playerStore.currentAsset.thumbnail_urls['160']"
              :alt="playerStore.currentAsset.title"
              class="w-full h-full object-cover"
            />
          </div>
          <div class="flex-1 min-w-0">
            <p class="font-bold text-gray-900 text-lg truncate">
              {{ playerStore.currentAsset.title }}
            </p>
            <p v-if="playerStore.currentAsset.duration_seconds" class="text-sm text-gray-500">
              {{ formatDuration(playerStore.currentAsset.duration_seconds) }}
            </p>
          </div>
          <div class="flex items-center gap-2">
            <div
              v-if="playerStore.isPlaying"
              class="flex items-center gap-1 text-green-600 text-sm font-medium"
            >
              <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse" />
              Playing
            </div>
          </div>
        </div>

        <!-- Progress bar placeholder -->
        <div class="w-full h-2 bg-gray-200 rounded-full overflow-hidden">
          <div class="h-full bg-blue-500 rounded-full w-1/3 transition-all" />
        </div>

        <!-- Reasons -->
        <div
          v-if="playerStore.nowPlayingReasons.length"
          class="mt-3 text-sm text-purple-600"
        >
          Based on your favorites: {{ playerStore.nowPlayingReasons.join(', ') }}
        </div>
      </div>

      <div v-else class="text-center py-8 text-gray-400">
        <svg class="w-12 h-12 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
        </svg>
        Nothing playing. Select an asset to start.
      </div>
    </div>

    <!-- Up-next queue -->
    <div class="mb-6">
      <h2 class="text-lg font-semibold text-gray-900 mb-3">Up Next</h2>
      <div v-if="playerStore.queue.length === 0" class="text-sm text-gray-400 py-4">
        Queue is empty.
      </div>
      <div v-else class="space-y-2">
        <div
          v-for="(asset, idx) in playerStore.queue"
          :key="`${asset.id}-${idx}`"
          class="bg-white rounded-xl border border-gray-200 p-3 flex items-center gap-3"
        >
          <span class="text-xs text-gray-400 w-5">{{ idx + 1 }}</span>
          <div class="w-10 h-7 bg-gray-200 rounded overflow-hidden shrink-0">
            <img
              v-if="asset.thumbnail_urls?.['160']"
              :src="asset.thumbnail_urls['160']"
              :alt="asset.title"
              class="w-full h-full object-cover"
            />
          </div>
          <p class="flex-1 text-sm font-medium text-gray-900 truncate">{{ asset.title }}</p>
        </div>
      </div>
    </div>

    <!-- Recent plays -->
    <div>
      <h2 class="text-lg font-semibold text-gray-900 mb-3">Recently Played</h2>
      <div v-if="playerStore.recentPlays.length === 0" class="text-sm text-gray-400 py-4">
        No recent plays.
      </div>
      <div v-else class="space-y-2">
        <div
          v-for="entry in playerStore.recentPlays.slice(0, 10)"
          :key="entry.id"
          class="bg-white rounded-xl border border-gray-200 p-3 flex items-center gap-3"
        >
          <div class="w-10 h-7 bg-gray-200 rounded overflow-hidden shrink-0">
            <img
              v-if="entry.asset?.thumbnail_urls?.['160']"
              :src="entry.asset.thumbnail_urls['160']"
              :alt="entry.asset?.title"
              class="w-full h-full object-cover"
            />
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-900 truncate">
              {{ entry.asset?.title ?? `Asset #${entry.asset_id}` }}
            </p>
            <p class="text-xs text-gray-400">{{ formatDate(entry.played_at) }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
