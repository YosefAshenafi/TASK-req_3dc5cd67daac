<template>
  <div class="fixed bottom-0 left-0 right-0 bg-white border-t border-surface-200 shadow-lg z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center gap-2 py-3">
        <button
          @click="isExpanded = !isExpanded"
          class="flex-1 flex items-center gap-2 min-w-0 text-sm font-medium text-surface-700 hover:text-surface-900 focus-visible:outline-none"
          :aria-expanded="isExpanded"
        >
          <svg class="w-4 h-4 text-brand-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
          </svg>
          <span class="shrink-0">Now Playing</span>

          <!-- The most recent play, visible even while collapsed so clicking
               Play gives immediate, honest feedback (a logged play — not audio). -->
          <span v-if="nowPlaying.current" class="flex items-center gap-1.5 min-w-0 text-brand-700">
            <span class="text-surface-300 shrink-0">—</span>
            <span class="w-1.5 h-1.5 rounded-full bg-brand-500 shrink-0" aria-hidden="true"></span>
            <span class="truncate font-semibold">{{ currentTitle }}</span>
          </span>

          <span v-else-if="nowPlaying.history.length > 0" class="text-xs text-surface-400">
            ({{ nowPlaying.history.length }} recent)
          </span>
        </button>

        <button
          v-if="nowPlaying.current"
          @click.stop="nowPlaying.stop()"
          data-testid="np-stop"
          class="shrink-0 inline-flex items-center gap-1 text-xs font-medium text-surface-500 hover:text-surface-800 rounded px-2 py-1 hover:bg-surface-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-500"
          aria-label="Stop — clear the current play"
        >
          <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
            <rect x="5" y="5" width="10" height="10" rx="1.5" />
          </svg>
          Stop
        </button>

        <button
          @click="isExpanded = !isExpanded"
          class="shrink-0 text-surface-500 hover:text-surface-800 rounded p-1 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-500"
          :aria-label="isExpanded ? 'Collapse recent plays' : 'Expand recent plays'"
          :aria-expanded="isExpanded"
        >
          <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': isExpanded }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
          </svg>
        </button>
      </div>

      <!-- Screen-reader announcement of the asset that was just played. -->
      <p class="sr-only" aria-live="polite">
        <span v-if="nowPlaying.current">Played: {{ currentTitle }}</span>
      </p>

      <div v-if="isExpanded" class="pb-4">
        <p class="text-xs text-surface-400 mb-3">
          Playing an asset logs it to your history and tunes recommendations. This console tracks plays — it doesn't stream audio.
        </p>

        <div v-if="nowPlaying.isLoading" class="space-y-2">
          <div v-for="n in 3" :key="n" class="skeleton h-12 rounded" />
        </div>
        <div v-else-if="nowPlaying.history.length === 0" class="text-center py-4 text-surface-400 text-sm">
          No recent plays in this session
        </div>
        <div v-else ref="listEl" class="space-y-2 max-h-48 overflow-y-auto">
          <div
            v-for="entry in nowPlaying.history"
            :key="entry.id"
            class="flex items-center gap-3 p-2 rounded transition-colors"
            :class="entry.id === nowPlaying.current?.id ? 'bg-brand-50 ring-1 ring-brand-200' : 'hover:bg-surface-50'"
          >
            <div
              class="w-8 h-8 rounded flex items-center justify-center shrink-0"
              :class="entry.id === nowPlaying.current?.id ? 'bg-brand-600 text-white' : 'bg-brand-100 text-brand-600'"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
              </svg>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-surface-900 truncate">{{ entry.asset?.title }}</p>
              <p class="text-xs text-surface-400">
                <span v-if="entry.id === nowPlaying.current?.id" class="text-brand-600 font-medium">Current · </span>{{ formatTime(entry.played_at) }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted } from 'vue'
import { useNowPlayingStore } from '@/stores/nowPlaying'

const nowPlaying = useNowPlayingStore()
const isExpanded = ref(false)
const listEl = ref(null)

const currentTitle = computed(() => nowPlaying.current?.asset?.title ?? '')

onMounted(() => {
  nowPlaying.fetchHistory()
})

// Each recorded play bumps playToken — reveal the panel and bring the freshly
// played item into view so the action is visibly acknowledged.
watch(
  () => nowPlaying.playToken,
  () => {
    isExpanded.value = true
    nextTick(() => {
      if (listEl.value) listEl.value.scrollTop = 0
    })
  }
)

function formatTime(isoString) {
  if (!isoString) return ''
  const date = new Date(isoString)
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}
</script>
