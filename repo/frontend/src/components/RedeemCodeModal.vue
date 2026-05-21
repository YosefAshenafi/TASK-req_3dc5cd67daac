<template>
  <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.self="$emit('close')">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
      <div class="flex items-center justify-between p-6 border-b border-surface-200">
        <h2 class="text-lg font-semibold text-surface-900">Redeem Share Code</h2>
        <button @click="$emit('close')" class="text-surface-400 hover:text-surface-600" aria-label="Close">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
      <form @submit.prevent="submit" class="p-6 space-y-4">
        <div>
          <label for="share-code" class="block text-sm font-medium text-surface-700 mb-1">Share Code</label>
          <input
            id="share-code"
            v-model="code"
            type="text"
            maxlength="8"
            class="input font-mono uppercase tracking-widest"
            placeholder="XXXXXXXX"
          />
        </div>
        <div v-if="error" class="p-3 bg-red-50 border border-red-200 rounded text-sm text-red-700">{{ error }}</div>
        <div v-if="redeemedPlaylist" class="p-3 bg-green-50 border border-green-200 rounded">
          <p class="text-sm text-green-700 font-medium">Playlist found: {{ redeemedPlaylist.name }}</p>
          <p class="text-xs text-green-600 mt-1">{{ (redeemedPlaylist.items || []).length }} items</p>
        </div>
        <div class="flex gap-3 justify-end pt-2">
          <button type="button" @click="$emit('close')" class="btn-secondary">Cancel</button>
          <button type="submit" class="btn-primary" :disabled="isSubmitting || !code">
            {{ isSubmitting ? 'Redeeming...' : 'Redeem' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { usePlaylistStore } from '@/stores/playlist'

defineEmits(['close'])
const store = usePlaylistStore()
const code = ref('')
const error = ref('')
const isSubmitting = ref(false)
const redeemedPlaylist = ref(null)

async function submit() {
  error.value = ''
  isSubmitting.value = true
  try {
    redeemedPlaylist.value = await store.redeem(code.value.toUpperCase())
  } catch (err) {
    error.value = err.response?.data?.message || 'Invalid or expired share code'
  } finally {
    isSubmitting.value = false
  }
}
</script>
