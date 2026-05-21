<template>
  <div>
    <h1 class="text-2xl font-bold text-surface-900 mb-6">Play History</h1>

    <div v-if="isLoading" class="space-y-3">
      <div v-for="n in 5" :key="n" class="skeleton h-16 rounded-xl" />
    </div>

    <div v-else-if="history.length === 0" class="card text-center py-16">
      <p class="text-surface-500">No play history yet</p>
    </div>

    <div v-else class="space-y-3">
      <div v-for="entry in history" :key="entry.id" class="card flex items-center gap-4">
        <div class="w-10 h-10 bg-brand-100 rounded-lg flex items-center justify-center shrink-0">
          <svg class="w-5 h-5 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="flex-1">
          <p class="font-medium text-surface-900">{{ entry.asset?.title }}</p>
          <p class="text-xs text-surface-400">{{ formatDate(entry.played_at) }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useNowPlayingStore } from '@/stores/nowPlaying'

const store = useNowPlayingStore()
const history = ref([])
const isLoading = ref(false)

onMounted(async () => {
  isLoading.value = true
  await store.fetchHistory()
  history.value = store.history
  isLoading.value = false
})

function formatDate(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleString()
}
</script>
