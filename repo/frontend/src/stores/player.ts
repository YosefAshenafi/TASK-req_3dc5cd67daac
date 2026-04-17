import { defineStore } from 'pinia'
import { ref } from 'vue'
import { historyApi } from '@/services/api'
import type { Asset, PlayHistoryEntry } from '@/types/api'

export const usePlayerStore = defineStore('player', () => {
  const currentAsset = ref<Asset | null>(null)
  const queue = ref<Asset[]>([])
  const recentPlays = ref<PlayHistoryEntry[]>([])
  const nowPlayingReasons = ref<string[]>([])
  const isPlaying = ref(false)

  async function play(asset: Asset): Promise<void> {
    currentAsset.value = asset
    isPlaying.value = true

    if (asset.reason_tags?.length) {
      nowPlayingReasons.value = asset.reason_tags
    } else {
      nowPlayingReasons.value = []
    }

    try {
      const entry = await historyApi.record(asset.id)
      recentPlays.value.unshift(entry)
      if (recentPlays.value.length > 20) {
        recentPlays.value = recentPlays.value.slice(0, 20)
      }
    } catch {
      // ignore history recording errors
    }
  }

  function enqueue(asset: Asset): void {
    queue.value.push(asset)
  }

  function dequeue(): Asset | undefined {
    return queue.value.shift()
  }

  function clearQueue(): void {
    queue.value = []
  }

  async function markPlayed(): Promise<void> {
    if (!currentAsset.value) return
    try {
      const entry = await historyApi.record(currentAsset.value.id)
      const exists = recentPlays.value.find((p) => p.id === entry.id)
      if (!exists) {
        recentPlays.value.unshift(entry)
        if (recentPlays.value.length > 20) {
          recentPlays.value = recentPlays.value.slice(0, 20)
        }
      }
    } catch {
      // ignore
    }
  }

  function playNext(): void {
    const next = dequeue()
    if (next) {
      play(next)
    } else {
      isPlaying.value = false
    }
  }

  function setRecentPlays(plays: PlayHistoryEntry[]): void {
    recentPlays.value = plays
  }

  return {
    currentAsset,
    queue,
    recentPlays,
    nowPlayingReasons,
    isPlaying,
    play,
    enqueue,
    dequeue,
    clearQueue,
    markPlayed,
    playNext,
    setRecentPlays,
  }
})
