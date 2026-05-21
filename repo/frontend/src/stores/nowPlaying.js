import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/api/axios'

export const useNowPlayingStore = defineStore('nowPlaying', () => {
  const history = ref([])
  const isLoading = ref(false)

  async function fetchHistory() {
    isLoading.value = true
    try {
      const response = await api.get('/play-history')
      history.value = response.data.data
    } finally {
      isLoading.value = false
    }
  }

  async function recordPlay(assetId) {
    await api.post('/play-history', { asset_id: assetId })
    await fetchHistory()
  }

  return { history, isLoading, fetchHistory, recordPlay }
})
