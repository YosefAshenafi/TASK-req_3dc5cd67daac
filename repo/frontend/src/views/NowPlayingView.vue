<script setup lang="ts">
import { onMounted } from 'vue'
import { usePlayerStore } from '@/stores/player'
import { historyApi } from '@/services/api'
import { Music2, Clock, Sparkles } from 'lucide-vue-next'

const playerStore = usePlayerStore()

onMounted(async () => {
  try {
    const result = await historyApi.list()
    playerStore.setRecentPlays(result.items)
  } catch {
    // ignore
  }
})

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleString(undefined, {
    month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
  })
}

function formatDuration(secs?: number): string {
  if (!secs) return ''
  const m = Math.floor(secs / 60)
  const s = Math.floor(secs % 60)
  return `${m}:${String(s).padStart(2, '0')}`
}
</script>

<template>
  <div class="p-6 max-w-3xl mx-auto">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-slate-900">Now Playing</h1>
      <p class="text-sm text-slate-500 mt-0.5">Current playback and history</p>
    </div>

    <!-- Player card -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-6">
      <div v-if="playerStore.currentAsset" class="p-6">
        <div class="flex gap-4 items-center mb-5">
          <div class="w-20 h-14 bg-slate-100 rounded-xl overflow-hidden shrink-0 shadow-sm">
            <img
              v-if="playerStore.currentAsset.thumbnail_urls?.['160']"
              :src="playerStore.currentAsset.thumbnail_urls['160']"
              :alt="playerStore.currentAsset.title"
              class="w-full h-full object-cover"
            />
            <div v-else class="w-full h-full flex items-center justify-center">
              <Music2 class="w-6 h-6 text-slate-300" />
            </div>
          </div>
          <div class="flex-1 min-w-0">
            <p class="font-bold text-slate-900 text-lg truncate leading-tight">
              {{ playerStore.currentAsset.title }}
            </p>
            <p v-if="playerStore.currentAsset.duration_seconds" class="text-sm text-slate-500 mt-0.5">
              {{ formatDuration(playerStore.currentAsset.duration_seconds) }}
            </p>
          </div>
          <div
            v-if="playerStore.isPlaying"
            class="flex items-center gap-1.5 text-emerald-700 bg-emerald-50 border border-emerald-200 text-xs font-semibold px-3 py-1.5 rounded-full shrink-0"
          >
            <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse" />
            Playing
          </div>
        </div>

        <!-- Progress bar -->
        <div class="w-full h-1.5 bg-slate-100 rounded-full overflow-hidden mb-4">
          <div class="h-full bg-indigo-500 rounded-full transition-all duration-500" style="width: 33%" />
        </div>

        <!-- Recommendation reason -->
        <div
          v-if="playerStore.nowPlayingReasons.length"
          class="flex items-center gap-2 text-sm text-violet-600 bg-violet-50 border border-violet-100 rounded-xl px-3 py-2"
        >
          <Sparkles class="w-3.5 h-3.5 shrink-0" />
          <span>Based on your favorites: {{ playerStore.nowPlayingReasons.join(', ') }}</span>
        </div>
      </div>

      <div v-else class="flex flex-col items-center justify-center py-12 text-center px-6">
        <div class="w-14 h-14 rounded-2xl bg-slate-100 flex items-center justify-center mb-4">
          <Music2 class="w-6 h-6 text-slate-400" />
        </div>
        <h3 class="font-semibold text-slate-700 mb-1">Nothing playing</h3>
        <p class="text-sm text-slate-400">Select an asset from the library to start</p>
      </div>
    </div>

    <!-- Up Next -->
    <div class="mb-6">
      <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">Up Next</h2>
      <div v-if="playerStore.queue.length === 0" class="bg-white rounded-xl border border-slate-200 px-4 py-6 text-center text-sm text-slate-400">
        Queue is empty
      </div>
      <div v-else class="space-y-1.5">
        <div
          v-for="(asset, idx) in playerStore.queue"
          :key="`${asset.id}-${idx}`"
          class="bg-white rounded-xl border border-slate-200 p-3 flex items-center gap-3 hover:border-slate-300 transition-colors"
        >
          <span class="text-xs text-slate-400 w-5 text-center font-medium">{{ idx + 1 }}</span>
          <div class="w-10 h-7 bg-slate-100 rounded-lg overflow-hidden shrink-0">
            <img
              v-if="asset.thumbnail_urls?.['160']"
              :src="asset.thumbnail_urls['160']"
              :alt="asset.title"
              class="w-full h-full object-cover"
            />
          </div>
          <p class="flex-1 text-sm font-medium text-slate-900 truncate">{{ asset.title }}</p>
        </div>
      </div>
    </div>

    <!-- Recently Played -->
    <div>
      <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">Recently Played</h2>
      <div v-if="playerStore.recentPlays.length === 0" class="bg-white rounded-xl border border-slate-200 px-4 py-6 text-center text-sm text-slate-400">
        No recent plays
      </div>
      <div v-else class="space-y-1.5">
        <div
          v-for="entry in playerStore.recentPlays.slice(0, 10)"
          :key="entry.id"
          class="bg-white rounded-xl border border-slate-200 p-3 flex items-center gap-3 hover:border-slate-300 transition-colors"
        >
          <div class="w-10 h-7 bg-slate-100 rounded-lg overflow-hidden shrink-0">
            <img
              v-if="entry.asset?.thumbnail_urls?.['160']"
              :src="entry.asset.thumbnail_urls['160']"
              :alt="entry.asset?.title"
              class="w-full h-full object-cover"
            />
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-slate-900 truncate">
              {{ entry.asset?.title ?? `Asset #${entry.asset_id}` }}
            </p>
            <div class="flex items-center gap-1 mt-0.5">
              <Clock class="w-3 h-3 text-slate-400" />
              <p class="text-xs text-slate-400">{{ formatDate(entry.played_at) }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
