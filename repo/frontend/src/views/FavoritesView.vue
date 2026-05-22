<template>
  <div>
    <h1 class="text-2xl font-bold text-surface-900 mb-6">My Favorites</h1>

    <div v-if="isLoading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="n in 6" :key="n" class="skeleton h-40 rounded-xl" />
    </div>

    <div v-else-if="error" class="card text-center py-12">
      <p class="text-red-600">{{ error }}</p>
      <button @click="load" class="btn-secondary mt-3">Retry</button>
    </div>

    <div v-else-if="favorites.length === 0" class="card text-center py-16">
      <svg class="w-16 h-16 text-surface-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
      </svg>
      <p class="text-surface-500 font-medium">No favorites yet</p>
      <p class="text-surface-400 text-sm mt-1">Save assets from the library to see them here</p>
    </div>

    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="item in favorites" :key="item.favorite_id" class="relative">
        <AssetCard :asset="item.asset" @play="nowPlaying.recordPlay($event)" />
        <button
          @click="removeFavorite(item.favorite_id)"
          class="absolute top-3 right-3 text-red-400 hover:text-red-600 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 rounded"
          title="Remove favorite"
          :aria-label="`Remove ${item.asset?.title} from favorites`"
        >
          <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
          </svg>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/api/axios'
import { useNowPlayingStore } from '@/stores/nowPlaying'
import AssetCard from '@/components/AssetCard.vue'

const favorites = ref([])
const isLoading = ref(true)
const error = ref(null)
const nowPlaying = useNowPlayingStore()

async function load() {
  isLoading.value = true
  error.value = null
  try {
    const response = await api.get('/favorites')
    favorites.value = response.data.data
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load favorites'
  } finally {
    isLoading.value = false
  }
}

async function removeFavorite(id) {
  try {
    await api.delete(`/favorites/${id}`)
    favorites.value = favorites.value.filter((f) => f.favorite_id !== id)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to remove favorite'
  }
}

onMounted(load)
</script>
