import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/api/axios'

/**
 * Tracks the current user's favorited assets so the library can render the
 * correct (filled / outline) heart state and toggle without a page reload.
 * The backend already exposes GET/POST/DELETE /favorites; this store keeps a
 * client-side map of assetId -> favoriteId derived from those endpoints.
 */
export const useFavoritesStore = defineStore('favorites', () => {
  // assetId (number) -> favoriteId (number)
  const map = ref({})
  const loaded = ref(false)
  const error = ref(null)

  async function load() {
    try {
      const response = await api.get('/favorites')
      const next = {}
      for (const item of response.data.data) {
        if (item.asset) next[item.asset.id] = item.favorite_id
      }
      map.value = next
      loaded.value = true
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to load favorites'
    }
  }

  function isFavorite(assetId) {
    return Object.prototype.hasOwnProperty.call(map.value, assetId)
  }

  async function add(assetId) {
    const response = await api.post('/favorites', { asset_id: assetId })
    map.value = { ...map.value, [assetId]: response.data.data.favorite_id }
  }

  async function remove(assetId) {
    const favoriteId = map.value[assetId]
    if (!favoriteId) return
    await api.delete(`/favorites/${favoriteId}`)
    const next = { ...map.value }
    delete next[assetId]
    map.value = next
  }

  async function toggle(assetId) {
    if (isFavorite(assetId)) {
      await remove(assetId)
    } else {
      await add(assetId)
    }
  }

  return { map, loaded, error, load, isFavorite, add, remove, toggle }
})
