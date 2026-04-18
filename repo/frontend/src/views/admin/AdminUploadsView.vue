<script setup lang="ts">
import { ref, onMounted } from 'vue'
import type { Asset } from '@/types/api'
import { useUiStore } from '@/stores/ui'
import { ApiError, getStoredToken, assetsApi } from '@/services/api'
import { Upload, CheckCircle2, X } from 'lucide-vue-next'

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

type ReviewTab = 'processing' | 'failed' | 'ready'
const reviewTab = ref<ReviewTab>('processing')
const reviewAssets = ref<Asset[]>([])
const reviewLoading = ref(false)

async function loadReview(tab: ReviewTab = reviewTab.value) {
  reviewTab.value = tab
  reviewLoading.value = true
  try {
    const page = await assetsApi.list({ status: tab, limit: 25, sort: 'newest' })
    reviewAssets.value = page.items
  } catch (err) {
    uiStore.addNotification({
      type: 'error',
      message: err instanceof Error ? err.message : 'Failed to load review queue',
    })
  } finally {
    reviewLoading.value = false
  }
}

onMounted(() => loadReview('processing'))

function statusBadgeClass(status: string): string {
  switch (status) {
    case 'ready': return 'bg-emerald-100 text-emerald-700'
    case 'processing': return 'bg-sky-100 text-sky-700'
    case 'failed': return 'bg-red-100 text-red-700'
    default: return 'bg-gray-100 text-gray-600'
  }
}

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
  const valid = files.filter((f) =>
    f.type.startsWith('video/') ||
    f.type.startsWith('audio/') ||
    f.type.startsWith('image/') ||
    f.type === 'application/pdf'
  )
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
    entry.tags.split(',').map((t) => t.trim()).filter(Boolean).forEach((t) => fd.append('tags[]', t))
  }

  try {
    const result = await new Promise<Asset>((resolve, reject) => {
      const xhr = new XMLHttpRequest()
      xhr.open('POST', `/api/assets`)
      xhr.withCredentials = true

      const token = getStoredToken()
      if (token) xhr.setRequestHeader('Authorization', `Bearer ${token}`)

      const xsrf = document.cookie.split('; ').find((r) => r.startsWith('XSRF-TOKEN='))?.split('=')[1]
      if (xsrf) xhr.setRequestHeader('X-XSRF-TOKEN', decodeURIComponent(xsrf))

      xhr.upload.addEventListener('progress', (e) => {
        if (e.lengthComputable) entry.progress = Math.round((e.loaded / e.total) * 100)
      })

      xhr.addEventListener('load', () => {
        if (xhr.status >= 200 && xhr.status < 300) {
          try { resolve(JSON.parse(xhr.responseText)) } catch { resolve({ id: 0 } as Asset) }
        } else {
          let msg = `HTTP ${xhr.status}`
          try { const body = JSON.parse(xhr.responseText); msg = body.message ?? msg } catch { /* noop */ }
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
  <div class="min-h-full">
    <!-- Dark page header -->
    <div class="bg-gray-900 border-b border-gray-800 px-6 py-8">
      <div class="max-w-4xl mx-auto">
        <div class="flex items-center gap-3 mb-1">
          <Upload class="w-5 h-5 text-violet-400" />
          <span class="text-xs font-semibold text-violet-400 uppercase tracking-widest">Media</span>
        </div>
        <h1 class="text-2xl font-bold text-white">Upload Media</h1>
        <p class="text-sm text-gray-400 mt-1">Add new media assets to the platform</p>
      </div>
    </div>

    <!-- Content -->
    <div class="max-w-4xl mx-auto px-6 py-8">
      <!-- Drop zone -->
      <div
        @dragover.prevent="isDragOver = true"
        @dragleave.prevent="isDragOver = false"
        @drop.prevent="handleDrop"
        :class="[
          'border-2 border-dashed rounded-xl p-12 text-center mb-6 transition-all duration-200',
          isDragOver ? 'border-sky-400 bg-sky-50' : 'border-gray-300 hover:border-gray-400 hover:bg-gray-50'
        ]"
      >
        <div class="w-14 h-14 rounded-xl bg-gray-900 flex items-center justify-center mx-auto mb-4">
          <Upload class="w-6 h-6 text-violet-400" />
        </div>
        <p class="text-gray-700 font-medium mb-1">Drop files here</p>
        <p class="text-sm text-gray-400 mb-4">or browse from your computer</p>
        <label class="inline-flex items-center gap-2 px-5 py-2.5 bg-sky-600 text-white text-sm font-semibold rounded-lg hover:bg-sky-700 cursor-pointer transition-colors shadow-sm shadow-sky-600/20">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Browse Files
          <input type="file" multiple accept="image/jpeg,image/png,application/pdf,audio/mpeg,video/mp4" class="hidden" @change="handleFileInput" />
        </label>
        <p class="text-xs text-gray-400 mt-4">Supports JPEG, PNG, PDF, MP3, and MP4 (25 MB images/docs, 250 MB video)</p>
      </div>

      <!-- Upload all -->
      <div v-if="entries.length > 0" class="flex justify-between items-center mb-5">
        <p class="text-sm text-gray-500">{{ entries.length }} file{{ entries.length === 1 ? '' : 's' }} queued</p>
        <button
          @click="uploadAll"
          :disabled="entries.every((e) => e.status === 'done' || e.status === 'uploading')"
          class="px-5 py-2.5 bg-sky-600 text-white text-sm font-semibold rounded-lg hover:bg-sky-700 disabled:opacity-50 transition-colors"
        >
          Upload All
        </button>
      </div>

      <!-- Entries -->
      <div class="space-y-3">
        <div
          v-for="entry in entries"
          :key="entry.id"
          :class="[
            'bg-white rounded-xl border p-5 shadow-sm',
            entry.status === 'error' ? 'border-red-200' :
            entry.status === 'done' ? 'border-emerald-200' : 'border-gray-200'
          ]"
        >
          <div class="flex items-start justify-between gap-4 mb-4">
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-gray-800 truncate">{{ entry.file.name }}</p>
              <p class="text-xs text-gray-400 mt-0.5">{{ formatSize(entry.file.size) }}</p>
            </div>
            <div class="flex items-center gap-2">
              <span :class="[
                'text-xs font-semibold px-2.5 py-1 rounded-full capitalize',
                entry.status === 'done' ? 'bg-emerald-100 text-emerald-700' :
                entry.status === 'error' ? 'bg-red-100 text-red-700' :
                entry.status === 'uploading' ? 'bg-sky-100 text-sky-700' :
                'bg-gray-100 text-gray-600'
              ]">{{ entry.status }}</span>
              <button
                v-if="entry.status !== 'uploading'"
                @click="removeEntry(entry.id)"
                class="p-1.5 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition-colors"
              >
                <X class="w-4 h-4" />
              </button>
            </div>
          </div>

          <div v-if="entry.errorMessage" class="mb-3 p-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
            {{ entry.errorMessage }}
          </div>

          <div v-if="entry.status === 'uploading'" class="mb-4">
            <div class="flex items-center justify-between mb-1">
              <span class="text-xs text-gray-500">Uploading…</span>
              <span class="text-xs font-semibold text-sky-600">{{ entry.progress }}%</span>
            </div>
            <div class="w-full h-1.5 bg-gray-100 rounded-full overflow-hidden">
              <div class="h-full bg-sky-500 rounded-full transition-all" :style="{ width: `${entry.progress}%` }" />
            </div>
          </div>

          <div v-if="entry.status === 'pending' || entry.status === 'error'" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label class="block text-xs font-semibold text-gray-500 mb-1.5">Title *</label>
              <input v-model="entry.title" type="text" class="w-full px-3.5 py-2.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500/50 focus:border-sky-500" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-500 mb-1.5">Tags (comma-separated)</label>
              <input v-model="entry.tags" type="text" placeholder="Safety, Parking, Event" class="w-full px-3.5 py-2.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500/50 focus:border-sky-500" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-500 mb-1.5">Description</label>
              <input v-model="entry.description" type="text" class="w-full px-3.5 py-2.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500/50 focus:border-sky-500" />
            </div>
          </div>

          <div v-if="entry.status === 'pending' || entry.status === 'error'" class="mt-4">
            <button @click="uploadEntry(entry)" class="px-4 py-2 bg-sky-600 text-white text-sm font-semibold rounded-lg hover:bg-sky-700 transition-colors">
              Upload
            </button>
          </div>

          <div v-if="entry.status === 'done'" class="flex items-center gap-2 text-emerald-600 text-sm mt-2">
            <CheckCircle2 class="w-4 h-4" />
            Uploaded successfully
          </div>
        </div>
      </div>

      <!-- Review queue -->
      <section class="mt-10">
        <div class="flex items-end justify-between mb-3">
          <div>
            <h2 class="text-lg font-semibold text-gray-900">Review queue</h2>
            <p class="text-sm text-gray-500 mt-0.5">Assets still being processed, or that failed validation, for admin review.</p>
          </div>
          <div class="flex gap-1 bg-gray-100 p-1 rounded-lg">
            <button
              v-for="tab in (['processing', 'failed', 'ready'] as const)"
              :key="tab"
              @click="loadReview(tab)"
              :class="[
                'px-3 py-1.5 text-xs font-semibold rounded-md capitalize transition-colors',
                reviewTab === tab ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700',
              ]"
            >{{ tab }}</button>
          </div>
        </div>

        <div v-if="reviewLoading" class="space-y-2">
          <div v-for="n in 3" :key="n" class="h-16 bg-white border border-gray-200 rounded-xl animate-pulse" />
        </div>

        <div v-else-if="reviewAssets.length === 0" class="bg-white border border-gray-200 rounded-xl px-4 py-10 text-center">
          <p class="text-sm font-semibold text-gray-700 mb-1">Nothing in the {{ reviewTab }} queue</p>
          <p class="text-xs text-gray-400">Upload an asset above to see it flow through the review pipeline.</p>
        </div>

        <ul v-else class="space-y-2">
          <li
            v-for="asset in reviewAssets"
            :key="asset.id"
            class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-4"
          >
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-gray-800 truncate">{{ asset.title }}</p>
              <p class="text-xs text-gray-400 mt-0.5">
                <span>#{{ asset.id }}</span>
                <span class="mx-2">·</span>
                <span>{{ asset.mime }}</span>
                <span class="mx-2">·</span>
                <span>{{ formatSize(asset.size_bytes) }}</span>
              </p>
            </div>
            <span :class="['text-xs font-semibold px-2.5 py-1 rounded-full capitalize', statusBadgeClass(asset.status)]">
              {{ asset.status }}
            </span>
          </li>
        </ul>
      </section>
    </div>
  </div>
</template>
