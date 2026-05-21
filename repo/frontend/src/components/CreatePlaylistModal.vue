<template>
  <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.self="$emit('close')">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
      <div class="flex items-center justify-between p-6 border-b border-surface-200">
        <h2 class="text-lg font-semibold text-surface-900">New Playlist</h2>
        <button @click="$emit('close')" class="text-surface-400 hover:text-surface-600" aria-label="Close">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
      <form @submit.prevent="submit" class="p-6 space-y-4">
        <div>
          <label for="pl-name" class="block text-sm font-medium text-surface-700 mb-1">Name</label>
          <input id="pl-name" v-model="form.name" type="text" class="input" :class="{ 'border-red-500': errors.name }" placeholder="My Playlist" />
          <p v-if="errors.name" class="mt-1 text-sm text-red-600">{{ errors.name }}</p>
        </div>
        <div>
          <label for="pl-desc" class="block text-sm font-medium text-surface-700 mb-1">Description (optional)</label>
          <textarea id="pl-desc" v-model="form.description" rows="3" class="input" placeholder="What's this playlist for?" />
        </div>
        <div v-if="error" class="p-3 bg-red-50 border border-red-200 rounded text-sm text-red-700">{{ error }}</div>
        <div class="flex gap-3 justify-end pt-2">
          <button type="button" @click="$emit('close')" class="btn-secondary">Cancel</button>
          <button type="submit" class="btn-primary" :disabled="isSubmitting">
            {{ isSubmitting ? 'Creating...' : 'Create' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { usePlaylistStore } from '@/stores/playlist'

const emit = defineEmits(['close', 'created'])
const store = usePlaylistStore()

const form = reactive({ name: '', description: '' })
const errors = reactive({ name: '' })
const error = ref('')
const isSubmitting = ref(false)

async function submit() {
  errors.name = ''
  if (!form.name.trim()) { errors.name = 'Name is required'; return }
  isSubmitting.value = true
  try {
    await store.create({ name: form.name, description: form.description || undefined })
    emit('created')
    emit('close')
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to create playlist'
  } finally {
    isSubmitting.value = false
  }
}
</script>
