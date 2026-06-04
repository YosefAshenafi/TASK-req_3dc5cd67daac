<template>
  <div
    class="card hover:shadow-md transition-shadow cursor-pointer group relative"
    data-testid="asset-card"
    role="button"
    tabindex="0"
    :aria-label="`View details for ${asset.title}`"
    @click="$emit('open', asset.id)"
    @keyup.enter="$emit('open', asset.id)"
  >
    <div class="flex items-start gap-3">
      <div class="w-12 h-12 rounded-lg flex items-center justify-center shrink-0" :class="iconBg">
        <svg class="w-6 h-6" :class="iconColor" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="iconPath" />
        </svg>
      </div>
      <div class="flex-1 min-w-0">
        <h3 class="font-semibold text-surface-900 truncate group-hover:text-brand-700 transition-colors">
          {{ asset.title }}
        </h3>
        <p v-if="asset.description" class="text-sm text-surface-500 mt-0.5 line-clamp-2">
          {{ asset.description }}
        </p>
        <div class="flex flex-wrap items-center gap-2 mt-2">
          <span v-if="asset.duration" class="text-xs text-surface-400">{{ formatDuration(asset.duration) }}</span>
          <span v-for="tag in (asset.tags || []).slice(0, 3)" :key="tag" class="badge bg-surface-100 text-surface-600">
            {{ tag }}
          </span>
          <span class="text-xs text-surface-400 ml-auto">{{ asset.play_count }} plays</span>
        </div>
        <div v-if="asset.recommendation_reason" class="mt-2 text-xs text-brand-600 flex items-center gap-1">
          <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
          </svg>
          {{ asset.recommendation_reason }}
        </div>
      </div>
    </div>

    <!-- Action bar: Play and Favorite. Both stop propagation so they never
         trigger the card's "open details" click. -->
    <div class="flex items-center gap-2 mt-3 pt-3 border-t border-surface-100">
      <button
        type="button"
        class="inline-flex items-center gap-1 text-sm font-medium text-brand-700 hover:text-brand-800 rounded px-1 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-500"
        :aria-label="`Play ${asset.title}`"
        data-testid="play-btn"
        @click.stop="$emit('play', asset.id)"
      >
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M4.018 14L14.41 8 4.018 2v12zM3 2.482a1 1 0 011.5-.866l9.518 5.5a1 1 0 010 1.732l-9.518 5.5A1 1 0 013 13.482v-11z" clip-rule="evenodd" />
        </svg>
        Play
      </button>

      <button
        type="button"
        class="ml-auto inline-flex items-center justify-center w-8 h-8 rounded-full transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-500"
        :class="isFavorite ? 'text-red-500 hover:text-red-600 bg-red-50' : 'text-surface-400 hover:text-red-500 hover:bg-surface-100'"
        :aria-pressed="isFavorite ? 'true' : 'false'"
        :aria-label="isFavorite ? `Remove ${asset.title} from favorites` : `Add ${asset.title} to favorites`"
        :title="isFavorite ? 'Remove from favorites' : 'Add to favorites'"
        data-testid="favorite-btn"
        @click.stop="$emit('toggle-favorite', asset)"
      >
        <svg v-if="isFavorite" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" />
        </svg>
        <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
        </svg>
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  asset: { type: Object, required: true },
  isFavorite: { type: Boolean, default: false },
})

defineEmits(['open', 'play', 'toggle-favorite'])

const iconBg = computed(() => {
  const type = props.asset.mime_type || ''
  if (type.startsWith('audio/')) return 'bg-purple-100'
  if (type.startsWith('video/')) return 'bg-red-100'
  if (type === 'application/pdf') return 'bg-orange-100'
  return 'bg-blue-100'
})

const iconColor = computed(() => {
  const type = props.asset.mime_type || ''
  if (type.startsWith('audio/')) return 'text-purple-600'
  if (type.startsWith('video/')) return 'text-red-600'
  if (type === 'application/pdf') return 'text-orange-600'
  return 'text-blue-600'
})

const iconPath = computed(() => {
  const type = props.asset.mime_type || ''
  if (type.startsWith('audio/')) return 'M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3'
  if (type.startsWith('video/')) return 'M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
  return 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'
})

function formatDuration(seconds) {
  if (!seconds) return ''
  const m = Math.floor(seconds / 60)
  const s = seconds % 60
  return `${m}:${String(s).padStart(2, '0')}`
}
</script>
