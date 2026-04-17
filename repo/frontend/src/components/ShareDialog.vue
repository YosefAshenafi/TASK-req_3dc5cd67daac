<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import type { PlaylistShare } from '@/types/api'
import { playlistsApi } from '@/services/api'
import { useUiStore } from '@/stores/ui'

const props = defineProps<{
  share: PlaylistShare
  playlistId: number
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'revoked'): void
}>()

const uiStore = useUiStore()
const revoking = ref(false)
const secondsLeft = ref(0)
let countdownInterval: ReturnType<typeof setInterval> | null = null

const ttlFormatted = computed(() => {
  const s = secondsLeft.value
  if (s <= 0) return 'Expired'
  const h = Math.floor(s / 3600)
  const m = Math.floor((s % 3600) / 60)
  const sec = s % 60
  if (h > 0) return `${h}h ${m}m ${sec}s`
  if (m > 0) return `${m}m ${sec}s`
  return `${sec}s`
})

function updateCountdown() {
  const expiry = new Date(props.share.expires_at).getTime()
  secondsLeft.value = Math.max(0, Math.floor((expiry - Date.now()) / 1000))
}

onMounted(() => {
  updateCountdown()
  countdownInterval = setInterval(updateCountdown, 1000)
})

onUnmounted(() => {
  if (countdownInterval) clearInterval(countdownInterval)
})

async function copyCode() {
  try {
    await navigator.clipboard.writeText(props.share.code)
    uiStore.addNotification({ type: 'success', message: 'Code copied to clipboard!' })
  } catch {
    uiStore.addNotification({ type: 'error', message: 'Failed to copy code' })
  }
}

async function handleRevoke() {
  revoking.value = true
  try {
    await playlistsApi.revokeShare(props.playlistId, props.share.id)
    emit('revoked')
  } catch {
    uiStore.addNotification({ type: 'error', message: 'Failed to revoke share' })
  } finally {
    revoking.value = false
  }
}
</script>

<template>
  <div
    class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
    @click.self="emit('close')"
  >
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6">
      <h2 class="text-lg font-bold text-gray-900 mb-4">Share Playlist</h2>

      <!-- Share code -->
      <div class="bg-gray-50 rounded-xl p-4 text-center mb-4">
        <p class="text-xs text-gray-500 mb-2">Share Code</p>
        <p class="text-4xl font-mono font-bold tracking-widest text-gray-900 select-all">
          {{ share.code }}
        </p>
      </div>

      <!-- TTL countdown -->
      <div class="text-center mb-4">
        <span
          :class="[
            'text-sm font-medium',
            secondsLeft > 300 ? 'text-green-600' : secondsLeft > 60 ? 'text-yellow-600' : 'text-red-600'
          ]"
        >
          Expires in: {{ ttlFormatted }}
        </span>
      </div>

      <!-- Actions -->
      <div class="flex flex-col gap-3">
        <button
          @click="copyCode"
          class="w-full min-h-[44px] bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors"
        >
          Copy Code
        </button>

        <button
          @click="handleRevoke"
          :disabled="revoking"
          class="w-full min-h-[44px] bg-red-50 text-red-600 font-semibold rounded-lg hover:bg-red-100 transition-colors disabled:opacity-50"
        >
          {{ revoking ? 'Revoking…' : 'Revoke' }}
        </button>

        <button
          @click="emit('close')"
          class="w-full min-h-[44px] text-gray-600 font-semibold rounded-lg hover:bg-gray-100 transition-colors"
        >
          Close
        </button>
      </div>
    </div>
  </div>
</template>
