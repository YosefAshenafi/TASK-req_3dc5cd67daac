<script setup lang="ts">
import { ref, computed } from 'vue'
import type { Asset } from '@/types/api'
import { favoritesApi, playlistsApi } from '@/services/api'
import { usePlayerStore } from '@/stores/player'
import { useUiStore } from '@/stores/ui'

const props = defineProps<{
  asset: Asset
  isFavorited?: boolean
  showReasonTags?: boolean
}>()

const emit = defineEmits<{
  (e: 'favorited', assetId: number): void
  (e: 'unfavorited', assetId: number): void
  (e: 'addToPlaylist', asset: Asset): void
}>()

const playerStore = usePlayerStore()
const uiStore = useUiStore()

const favorited = ref(props.isFavorited ?? false)
const favoriteLoading = ref(false)

const durationFormatted = computed(() => {
  const secs = props.asset.duration_seconds ?? 0
  const m = Math.floor(secs / 60)
  const s = Math.floor(secs % 60)
  return `${m}:${String(s).padStart(2, '0')}`
})

const thumbnailUrl = computed(() => {
  return props.asset.thumbnail_urls?.['160'] ?? ''
})

async function handlePlay() {
  await playerStore.play(props.asset)
}

async function handleToggleFavorite() {
  if (favoriteLoading.value) return
  favoriteLoading.value = true
  try {
    if (favorited.value) {
      await favoritesApi.remove(props.asset.id)
      favorited.value = false
      emit('unfavorited', props.asset.id)
    } else {
      await favoritesApi.add(props.asset.id)
      favorited.value = true
      emit('favorited', props.asset.id)
    }
  } catch {
    uiStore.addNotification({ type: 'error', message: 'Failed to update favorite' })
  } finally {
    favoriteLoading.value = false
  }
}

function handleAddToPlaylist() {
  emit('addToPlaylist', props.asset)
}
</script>

<template>
  <div class="bg-white rounded-xl shadow-sm overflow-hidden hover:shadow-md transition-shadow border border-gray-100">
    <!-- Thumbnail -->
    <div class="relative aspect-video bg-gray-200">
      <img
        v-if="thumbnailUrl"
        :src="thumbnailUrl"
        :alt="asset.title"
        class="w-full h-full object-cover"
        loading="lazy"
      />
      <div
        v-else
        class="w-full h-full flex items-center justify-center text-gray-400"
      >
        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M15 10l4.553-2.069A1 1 0 0121 8.82v6.361a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
        </svg>
      </div>

      <!-- Duration badge -->
      <span
        v-if="asset.duration_seconds"
        class="absolute bottom-2 right-2 bg-black/70 text-white text-xs px-1.5 py-0.5 rounded"
      >
        {{ durationFormatted }}
      </span>

      <!-- Play overlay button -->
      <button
        @click="handlePlay"
        class="absolute inset-0 flex items-center justify-center bg-black/0 hover:bg-black/30 transition-colors group"
        :aria-label="`Play ${asset.title}`"
      >
        <span class="w-12 h-12 rounded-full bg-white/90 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
          <svg class="w-5 h-5 text-gray-900 ml-0.5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd" />
          </svg>
        </span>
      </button>
    </div>

    <!-- Content -->
    <div class="p-3">
      <h3 class="font-semibold text-gray-900 text-sm line-clamp-2 mb-2">
        {{ asset.title }}
      </h3>

      <!-- Tags -->
      <div v-if="asset.tags?.length" class="flex flex-wrap gap-1 mb-2">
        <span
          v-for="tag in asset.tags.slice(0, 4)"
          :key="tag"
          class="text-xs bg-blue-50 text-blue-700 px-2 py-0.5 rounded-full"
        >
          {{ tag }}
        </span>
      </div>

      <!-- Reason tags -->
      <div
        v-if="showReasonTags && asset.reason_tags?.length"
        class="mb-2 text-xs text-purple-600 bg-purple-50 rounded px-2 py-1"
      >
        Based on: {{ asset.reason_tags.join(', ') }}
      </div>

      <!-- Actions row -->
      <div class="flex items-center justify-between mt-2">
        <button
          @click="handlePlay"
          class="min-h-[44px] min-w-[44px] flex items-center gap-1.5 px-3 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 transition-colors"
        >
          <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd" />
          </svg>
          Play
        </button>

        <div class="flex items-center gap-1">
          <!-- Favorite button -->
          <button
            @click="handleToggleFavorite"
            :disabled="favoriteLoading"
            class="min-h-[44px] min-w-[44px] flex items-center justify-center rounded-lg hover:bg-gray-100 transition-colors disabled:opacity-50"
            :aria-label="favorited ? 'Remove from favorites' : 'Add to favorites'"
          >
            <svg
              class="w-5 h-5"
              :class="favorited ? 'text-red-500 fill-red-500' : 'text-gray-400'"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
            </svg>
          </button>

          <!-- Add to playlist button -->
          <button
            @click="handleAddToPlaylist"
            class="min-h-[44px] min-w-[44px] flex items-center justify-center rounded-lg hover:bg-gray-100 transition-colors"
            aria-label="Add to playlist"
          >
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
