<script setup lang="ts">
import { ref, reactive } from 'vue'
import type { Asset } from '@/types/api'
import { useUiStore } from '@/stores/ui'
import { ApiError, getStoredToken } from '@/services/api'

const uiStore = useUiStore()

interface UploadEntry {
  id: string
  file: File
  title: string
  description: string
  tags: string
  progress: number
  status: 'pending' | 'uploading' | 'done' | 'error'
  errorMessage?: string
  result?: Asset
}

const entries = ref<UploadEntry[]>([])
const isDragOver = ref(false)

function createEntry(file: File): UploadEntry {
  return {
    id: `upload-${Date.now()}-${Math.random().toString(36).slice(2)}`,
    file,
    title: file.name.replace(/\.[^/.]+$/, ''),
    description: '',
    tags: '',
    progress: 0,
    status: 'pending',
  }
}

function handleDrop(e: DragEvent) {
  isDragOver.value = false
  const files = e.dataTransfer?.files
  if (files) addFiles(Array.from(files))
}

function handleFileInput(e: Event) {
  const input = e.target as HTMLInputElement
  if (input.files) {
    addFiles(Array.from(input.files))
    input.value = ''
  }
}

function addFiles(files: File[]) {
  const valid = files.filter((f) => f.type.startsWith('video/') || f.type.startsWith('audio/') || f.type.startsWith('image/'))
  if (valid.length < files.length) {
    uiStore.addNotification({ type: 'warning', message: 'Some files were skipped (unsupported type)' })
  }
  entries.value.push(...valid.map(createEntry))
}

function removeEntry(id: string) {
  entries.value = entries.value.filter((e) => e.id !== id)
}

async function uploadEntry(entry: UploadEntry) {
  entry.status = 'uploading'
  entry.progress = 0
  entry.errorMessage = undefined

  const fd = new FormData()
  fd.append('file', entry.file)
  fd.append('title', entry.title.trim() || entry.file.name)
  if (entry.description.trim()) fd.append('description', entry.description.trim())
  if (entry.tags.trim()) {
    entry.tags.split(',').map((t) => t.trim()).filter(Boolean).forEach((t) => fd.append('tags', t))
  }

  try {
    // Use XHR for progress tracking
    const result = await new Promise<Asset>((resolve, reject) => {
      const xhr = new XMLHttpRequest()
      xhr.open('POST', `/api/assets`)
      xhr.withCredentials = true

      // Auth
      const token = getStoredToken()
      if (token) xhr.setRequestHeader('Authorization', `Bearer ${token}`)

      // XSRF
      const xsrf = document.cookie.split('; ').find((r) => r.startsWith('XSRF-TOKEN='))?.split('=')[1]
      if (xsrf) xhr.setRequestHeader('X-XSRF-TOKEN', decodeURIComponent(xsrf))

      xhr.upload.addEventListener('progress', (e) => {
        if (e.lengthComputable) {
          entry.progress = Math.round((e.loaded / e.total) * 100)
        }
      })

      xhr.addEventListener('load', () => {
        if (xhr.status >= 200 && xhr.status < 300) {
          try {
            resolve(JSON.parse(xhr.responseText))
          } catch {
            resolve({ id: 0 } as Asset)
          }
        } else {
          let msg = `HTTP ${xhr.status}`
          try {
            const body = JSON.parse(xhr.responseText)
            msg = body.message ?? msg
          } catch {
            // noop
          }
          reject(new ApiError(xhr.status, null, msg))
        }
      })

      xhr.addEventListener('error', () => reject(new Error('Network error')))
      xhr.send(fd)
    })

    entry.result = result
    entry.progress = 100
    entry.status = 'done'
    uiStore.addNotification({ type: 'success', message: `"${entry.title}" uploaded` })
  } catch (err) {
    entry.status = 'error'
    if (err instanceof ApiError) {
      entry.errorMessage = err.body?.message ?? err.message
    } else if (err instanceof Error) {
      entry.errorMessage = err.message
    }
  }
}

async function uploadAll() {
  const pending = entries.value.filter((e) => e.status === 'pending' || e.status === 'error')
  for (const entry of pending) {
    await uploadEntry(entry)
  }
}

function formatSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}
</script>

<template>
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Upload Media</h1>

    <!-- Drop zone -->
    <div
      @dragover.prevent="isDragOver = true"
      @dragleave.prevent="isDragOver = false"
      @drop.prevent="handleDrop"
      :class="[
        'border-2 border-dashed rounded-2xl p-12 text-center mb-6 transition-colors',
        isDragOver ? 'border-blue-400 bg-blue-50' : 'border-gray-300 hover:border-gray-400'
      ]"
    >
      <svg class="w-12 h-12 mx-auto text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
      </svg>
      <p class="text-gray-600 mb-2">Drag & drop files here or</p>
      <label class="inline-block min-h-[44px] px-6 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 cursor-pointer leading-[44px]">
        Browse Files
        <input
          type="file"
          multiple
          accept="video/*,audio/*,image/*"
          class="hidden"
          @change="handleFileInput"
        />
      </label>
      <p class="text-xs text-gray-400 mt-3">Supported: video, audio, image files</p>
    </div>

    <!-- Upload all button -->
    <div v-if="entries.length > 0" class="flex justify-between items-center mb-4">
      <p class="text-sm text-gray-500">{{ entries.length }} file(s) queued</p>
      <button
        @click="uploadAll"
        :disabled="entries.every((e) => e.status === 'done' || e.status === 'uploading')"
        class="min-h-[44px] px-6 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 disabled:opacity-50"
      >
        Upload All
      </button>
    </div>

    <!-- Entry list -->
    <div class="space-y-4">
      <div
        v-for="entry in entries"
        :key="entry.id"
        :class="[
          'bg-white rounded-2xl border p-5',
          entry.status === 'error' ? 'border-red-200' : entry.status === 'done' ? 'border-green-200' : 'border-gray-200'
        ]"
      >
        <div class="flex items-start justify-between gap-4 mb-3">
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-700 truncate">{{ entry.file.name }}</p>
            <p class="text-xs text-gray-400">{{ formatSize(entry.file.size) }}</p>
          </div>
          <div class="flex items-center gap-2">
            <span
              :class="[
                'text-xs font-semibold px-2 py-1 rounded-full',
                entry.status === 'done' ? 'bg-green-100 text-green-700' :
                entry.status === 'error' ? 'bg-red-100 text-red-700' :
                entry.status === 'uploading' ? 'bg-blue-100 text-blue-700' :
                'bg-gray-100 text-gray-600'
              ]"
            >
              {{ entry.status }}
            </span>
            <button
              v-if="entry.status !== 'uploading'"
              @click="removeEntry(entry.id)"
              class="min-h-[36px] min-w-[36px] flex items-center justify-center text-gray-400 hover:text-gray-600 rounded"
              aria-label="Remove"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Error row -->
        <div v-if="entry.errorMessage" class="mb-3 p-2 bg-red-50 text-red-700 text-sm rounded-lg">
          {{ entry.errorMessage }}
        </div>

        <!-- Progress bar -->
        <div v-if="entry.status === 'uploading'" class="mb-3">
          <div class="w-full h-2 bg-gray-200 rounded-full overflow-hidden">
            <div
              class="h-full bg-blue-500 rounded-full transition-all"
              :style="{ width: `${entry.progress}%` }"
            />
          </div>
          <p class="text-xs text-gray-400 mt-1">{{ entry.progress }}%</p>
        </div>

        <!-- Metadata form -->
        <div v-if="entry.status === 'pending' || entry.status === 'error'" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Title *</label>
            <input
              v-model="entry.title"
              type="text"
              class="w-full min-h-[40px] px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Tags (comma-separated)</label>
            <input
              v-model="entry.tags"
              type="text"
              placeholder="Safety, Parking, Event"
              class="w-full min-h-[40px] px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Description</label>
            <input
              v-model="entry.description"
              type="text"
              class="w-full min-h-[40px] px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
        </div>

        <!-- Upload single button -->
        <div v-if="entry.status === 'pending' || entry.status === 'error'" class="mt-3">
          <button
            @click="uploadEntry(entry)"
            class="min-h-[40px] px-4 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700"
          >
            Upload
          </button>
        </div>

        <!-- Done state -->
        <div v-if="entry.status === 'done'" class="flex items-center gap-2 text-green-600 text-sm mt-2">
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
          </svg>
          Uploaded successfully
        </div>
      </div>
    </div>
  </div>
</template>
