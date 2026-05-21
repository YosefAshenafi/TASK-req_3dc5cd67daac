<template>
  <div class="fixed bottom-0 left-0 right-0 bg-white border-t border-surface-200 shadow-lg z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <button
        @click="isExpanded = !isExpanded"
        class="w-full flex items-center justify-between py-3 text-sm font-medium text-surface-700 hover:text-surface-900 focus-visible:outline-none"
        :aria-expanded="isExpanded"
      >
        <span class="flex items-center gap-2">
          <svg class="w-4 h-4 text-brand-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          Now Playing
          <span v-if="nowPlaying.history.length > 0" class="text-xs text-surface-400">
            ({{ nowPlaying.history.length }} recent)
          </span>
        </span>
        <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': isExpanded }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
        </svg>
      </button>

      <div v-if="isExpanded" class="pb-4">
        <div v-if="nowPlaying.isLoading" class="space-y-2">
          <div v-for="n in 3" :key="n" class="skeleton h-12 rounded" />
        </div>
        <div v-else-if="nowPlaying.history.length === 0" class="text-center py-4 text-surface-400 text-sm">
          No recent plays in this session
        </div>
        <div v-else class="space-y-2 max-h-48 overflow-y-auto">
          <div
            v-for="entry in nowPlaying.history"
            :key="entry.id"
            class="flex items-center gap-3 p-2 rounded hover:bg-surface-50"
          >
            <div class="w-8 h-8 bg-brand-100 rounded flex items-center justify-center shrink-0">
              <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
              </svg>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-surface-900 truncate">{{ entry.asset?.title }}</p>
              <p class="text-xs text-surface-400">{{ formatTime(entry.played_at) }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useNowPlayingStore } from '@/stores/nowPlaying'

const nowPlaying = useNowPlayingStore()
const isExpanded = ref(false)

onMounted(() => {
  nowPlaying.fetchHistory()
})

function formatTime(isoString) {
  if (!isoString) return ''
  const date = new Date(isoString)
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}
</script>
