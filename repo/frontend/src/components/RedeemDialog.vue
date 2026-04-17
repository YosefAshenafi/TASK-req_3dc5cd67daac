<script setup lang="ts">
import { ref } from 'vue'
import { playlistsApi } from '@/services/api'
import { ApiError } from '@/services/api'
import type { Playlist } from '@/types/api'

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'redeemed', playlist: Playlist): void
}>()

// Valid characters: A-Z excluding O and I; 0-9 excluding 0 and 1
const VALID_CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'.split('')

const code = ref('')
const error = ref('')
const loading = ref(false)

function handleKey(char: string) {
  if (code.value.length < 8) {
    code.value += char
    error.value = ''
  }
}

function handleBackspace() {
  code.value = code.value.slice(0, -1)
  error.value = ''
}

function handleClear() {
  code.value = ''
  error.value = ''
}

async function handleSubmit() {
  if (code.value.length !== 8) {
    error.value = 'Please enter a complete 8-character code'
    return
  }

  loading.value = true
  error.value = ''

  try {
    const playlist = await playlistsApi.redeem(code.value)
    emit('redeemed', playlist)
  } catch (err) {
    if (err instanceof ApiError) {
      if (err.status === 404) {
        error.value = 'Code not found or already expired'
      } else if (err.status === 410) {
        error.value = 'This code has expired'
      } else if (err.status === 423) {
        error.value = 'This code has been blacklisted'
      } else {
        error.value = err.body?.message ?? 'Failed to redeem code'
      }
    } else {
      error.value = 'An unexpected error occurred'
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div
    class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
    @click.self="emit('close')"
  >
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6">
      <h2 class="text-lg font-bold text-gray-900 mb-4">Redeem Playlist Code</h2>

      <!-- Code display -->
      <div class="bg-gray-50 rounded-xl p-4 text-center mb-4">
        <p class="text-3xl font-mono font-bold tracking-widest text-gray-900 min-h-[40px]">
          {{ code || '· · · · · · · ·' }}
        </p>
        <p class="text-xs text-gray-500 mt-1">{{ code.length }}/8 characters</p>
      </div>

      <!-- Error -->
      <div
        v-if="error"
        class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm"
      >
        {{ error }}
      </div>

      <!-- Keypad -->
      <div class="grid grid-cols-8 gap-1 mb-3">
        <button
          v-for="char in VALID_CHARS"
          :key="char"
          @click="handleKey(char)"
          :disabled="code.length >= 8"
          class="min-h-[44px] text-sm font-bold bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
        >
          {{ char }}
        </button>
      </div>

      <!-- Utility buttons -->
      <div class="flex gap-2 mb-4">
        <button
          @click="handleBackspace"
          :disabled="code.length === 0"
          class="flex-1 min-h-[44px] bg-yellow-50 text-yellow-700 font-semibold rounded-lg hover:bg-yellow-100 transition-colors disabled:opacity-40"
        >
          ← Back
        </button>
        <button
          @click="handleClear"
          :disabled="code.length === 0"
          class="flex-1 min-h-[44px] bg-red-50 text-red-600 font-semibold rounded-lg hover:bg-red-100 transition-colors disabled:opacity-40"
        >
          Clear
        </button>
      </div>

      <!-- Submit & Close -->
      <div class="flex flex-col gap-2">
        <button
          @click="handleSubmit"
          :disabled="loading || code.length !== 8"
          class="w-full min-h-[44px] bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50"
        >
          {{ loading ? 'Redeeming…' : 'Redeem' }}
        </button>
        <button
          @click="emit('close')"
          class="w-full min-h-[44px] text-gray-600 font-semibold rounded-lg hover:bg-gray-100 transition-colors"
        >
          Cancel
        </button>
      </div>
    </div>
  </div>
</template>
