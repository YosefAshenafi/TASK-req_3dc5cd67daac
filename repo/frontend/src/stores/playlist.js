import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/api/axios'

export const usePlaylistStore = defineStore('playlist', () => {
  const playlists = ref([])
  const currentPlaylist = ref(null)
  const isLoading = ref(false)
  const error = ref(null)

  async function fetchAll() {
    isLoading.value = true
    try {
      const response = await api.get('/playlists')
      playlists.value = response.data.data
    } catch (err) {
      error.value = err.response?.data?.message
    } finally {
      isLoading.value = false
    }
  }

  async function fetchOne(id) {
    isLoading.value = true
    try {
      const response = await api.get(`/playlists/${id}`)
      currentPlaylist.value = response.data.data
      return currentPlaylist.value
    } catch (err) {
      error.value = err.response?.data?.message
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function create(data) {
    const response = await api.post('/playlists', data)
    playlists.value.unshift(response.data.data)
    return response.data.data
  }

  async function update(id, data) {
    const response = await api.patch(`/playlists/${id}`, data)
    const idx = playlists.value.findIndex((p) => p.id === id)
    if (idx >= 0) playlists.value[idx] = response.data.data
    return response.data.data
  }

  async function remove(id) {
    await api.delete(`/playlists/${id}`)
    playlists.value = playlists.value.filter((p) => p.id !== id)
  }

  async function addItem(playlistId, assetId) {
    const response = await api.post(`/playlists/${playlistId}/items`, { asset_id: assetId })
    return response.data.data
  }

  async function removeItem(playlistId, itemId) {
    await api.delete(`/playlists/${playlistId}/items/${itemId}`)
  }

  async function redeem(shareCode) {
    const response = await api.post('/playlists/redeem', { share_code: shareCode })
    return response.data.data.playlist
  }

  return {
    playlists, currentPlaylist, isLoading, error,
    fetchAll, fetchOne, create, update, remove, addItem, removeItem, redeem,
  }
})
