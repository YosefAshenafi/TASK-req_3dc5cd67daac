import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/api/axios'

export const useNowPlayingStore = defineStore('nowPlaying', () => {
  const history = ref([])
  const isLoading = ref(false)
  // The most recently played entry (drives the "Now Playing" header + highlight).
  const current = ref(null)
  // Incremented on every play so UI can react (e.g. auto-expand the panel) even
  // when the played asset is already the current one.
  const playToken = ref(0)

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
    // After the refetch the newest play sits at the top of the list; prefer the
    // entry that matches the asset just played, falling back to the latest.
    current.value =
      history.value.find((h) => h.asset?.id === assetId) || history.value[0] || null
    playToken.value += 1
  }

  // Clear the active "now playing" selection. Symmetric counterpart to a play;
  // the play already lives in history, so this only dismisses the active marker.
  function stop() {
    current.value = null
  }

  return { history, isLoading, current, playToken, fetchHistory, recordPlay, stop }
})
