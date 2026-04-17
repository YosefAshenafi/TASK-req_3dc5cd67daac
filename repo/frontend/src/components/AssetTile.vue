<script setup lang="ts">
import { ref, computed } from 'vue'
import type { Asset } from '@/types/api'
import { favoritesApi } from '@/services/api'
import { usePlayerStore } from '@/stores/player'
import { useUiStore } from '@/stores/ui'
import { Play, Heart, Plus, Sparkles } from 'lucide-vue-next'

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
const isHovered = ref(false)

const durationFormatted = computed(() => {
  const secs = props.asset.duration_seconds ?? 0
  const m = Math.floor(secs / 60)
  const s = Math.floor(secs % 60)
  return `${m}:${String(s).padStart(2, '0')}`
})

const thumbnailUrl = computed(() => props.asset.thumbnail_urls?.['160'] ?? '')

const isCurrentlyPlaying = computed(
  () => playerStore.currentAsset?.id === props.asset.id && playerStore.isPlaying
)

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
  <div
    class="group bg-white rounded-xl border border-slate-200 overflow-hidden hover:border-indigo-300 hover:shadow-lg hover:shadow-indigo-500/10 transition-all duration-200"
    @mouseenter="isHovered = true"
    @mouseleave="isHovered = false"
  >
    <!-- Thumbnail area -->
    <div class="relative aspect-video bg-slate-100 overflow-hidden">
      <img
        v-if="thumbnailUrl"
        :src="thumbnailUrl"
        :alt="asset.title"
        class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
        loading="lazy"
      />
      <div
        v-else
        class="w-full h-full flex items-center justify-center bg-gradient-to-br from-slate-100 to-slate-200"
      >
        <svg class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.069A1 1 0 0121 8.82v6.361a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
        </svg>
      </div>

      <!-- Gradient overlay -->
      <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-200" />

      <!-- Duration badge -->
      <span
        v-if="asset.duration_seconds"
        class="absolute bottom-2 right-2 bg-black/70 text-white text-xs px-1.5 py-0.5 rounded-md font-medium backdrop-blur-sm"
      >
        {{ durationFormatted }}
      </span>

      <!-- Now playing indicator -->
      <div
        v-if="isCurrentlyPlaying"
        class="absolute top-2 left-2 flex items-center gap-1 bg-indigo-600/90 text-white text-xs px-2 py-1 rounded-full backdrop-blur-sm"
      >
        <span class="w-1.5 h-1.5 bg-white rounded-full animate-pulse" />
        Playing
      </div>

      <!-- Play button overlay -->
      <button
        @click="handlePlay"
        class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-200"
        :aria-label="`Play ${asset.title}`"
      >
        <div class="w-12 h-12 rounded-full bg-white/95 flex items-center justify-center shadow-xl transform scale-90 group-hover:scale-100 transition-transform duration-200">
          <Play class="w-5 h-5 text-slate-900 ml-0.5 fill-slate-900" />
        </div>
      </button>
    </div>

    <!-- Content -->
    <div class="p-3">
      <h3 class="font-semibold text-slate-900 text-sm line-clamp-2 leading-snug mb-2">
        {{ asset.title }}
      </h3>

      <!-- Tags -->
      <div v-if="asset.tags?.length" class="flex flex-wrap gap-1 mb-2">
        <span
          v-for="tag in asset.tags.slice(0, 3)"
          :key="tag"
          class="text-xs bg-indigo-50 text-indigo-600 px-2 py-0.5 rounded-full font-medium"
        >
          {{ tag }}
        </span>
        <span
          v-if="asset.tags.length > 3"
          class="text-xs text-slate-400 px-1 py-0.5"
        >+{{ asset.tags.length - 3 }}</span>
      </div>

      <!-- Reason tags -->
      <div
        v-if="showReasonTags && asset.reason_tags?.length"
        class="mb-2 flex items-center gap-1.5 text-xs text-violet-600 bg-violet-50 rounded-lg px-2 py-1.5"
      >
        <Sparkles class="w-3 h-3 shrink-0" />
        <span class="truncate">{{ asset.reason_tags.slice(0, 2).join(', ') }}</span>
      </div>

      <!-- Actions -->
      <div class="flex items-center justify-between pt-1">
        <button
          @click="handlePlay"
          class="flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg transition-colors shadow-sm shadow-indigo-600/20"
        >
          <Play class="w-3 h-3 fill-white" />
          Play
        </button>

        <div class="flex items-center gap-0.5">
          <button
            @click="handleToggleFavorite"
            :disabled="favoriteLoading"
            class="p-2 rounded-lg hover:bg-slate-100 transition-colors disabled:opacity-40"
            :aria-label="favorited ? 'Remove from favorites' : 'Add to favorites'"
          >
            <Heart
              class="w-4 h-4 transition-colors"
              :class="favorited ? 'fill-red-500 text-red-500' : 'text-slate-400'"
            />
          </button>

          <button
            @click="handleAddToPlaylist"
            class="p-2 rounded-lg hover:bg-slate-100 transition-colors"
            aria-label="Add to playlist"
          >
            <Plus class="w-4 h-4 text-slate-400" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
