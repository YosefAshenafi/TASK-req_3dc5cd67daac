<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-surface-900">Media Library</h1>
      <button @click="showUploadModal = true" class="btn-primary">+ Upload Asset</button>
    </div>

    <!-- Upload Modal -->
    <div v-if="showUploadModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm" @click.self="closeUpload">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6">
        <div class="flex items-center justify-between mb-5">
          <h2 class="text-lg font-semibold text-surface-900">Upload Asset</h2>
          <button @click="closeUpload" class="text-surface-400 hover:text-surface-700 text-xl leading-none">×</button>
        </div>

        <form @submit.prevent="submitUpload" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-surface-700 mb-1">File <span class="text-red-500">*</span></label>
            <input type="file" ref="fileInput" required class="block w-full text-sm text-surface-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 cursor-pointer" />
          </div>
          <div>
            <label class="block text-sm font-medium text-surface-700 mb-1">Title <span class="text-red-500">*</span></label>
            <input v-model="uploadForm.title" type="text" required maxlength="255" class="input w-full" placeholder="Asset title" />
          </div>
          <div>
            <label class="block text-sm font-medium text-surface-700 mb-1">Description</label>
            <textarea v-model="uploadForm.description" rows="2" maxlength="5000" class="input w-full resize-none" placeholder="Optional description"></textarea>
          </div>
          <div>
            <label class="block text-sm font-medium text-surface-700 mb-1">Tags (comma-separated)</label>
            <input v-model="uploadForm.tags" type="text" class="input w-full" placeholder="e.g. safety, training" />
          </div>

          <p v-if="uploadError" class="text-sm text-red-600">{{ uploadError }}</p>
          <p v-if="uploadSuccess" class="text-sm text-green-600">Asset uploaded. Pending admin approval.</p>

          <div class="flex justify-end gap-3 pt-2">
            <button type="button" @click="closeUpload" class="btn-secondary">Cancel</button>
            <button type="submit" :disabled="uploading" class="btn-primary">
              {{ uploading ? 'Uploading…' : 'Upload' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <div class="card mb-6">
      <div class="flex flex-col sm:flex-row gap-4">
        <div class="flex-1">
          <label for="search" class="sr-only">Search</label>
          <div class="relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-surface-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0" />
            </svg>
            <input
              id="search"
              v-model="search.filters.q"
              type="search"
              placeholder="Search by title, description, or tags..."
              class="input pl-9"
              @keyup.enter="doSearch"
            />
          </div>
        </div>
        <button @click="doSearch" class="btn-primary shrink-0">Search</button>
      </div>

      <div class="mt-4 flex flex-wrap gap-3">
        <div>
          <label class="block text-xs font-medium text-surface-500 mb-1">Max Duration (sec)</label>
          <input v-model.number="search.filters.duration_max" type="number" min="1" placeholder="e.g. 120" class="input w-32 text-sm" />
        </div>
        <div>
          <label class="block text-xs font-medium text-surface-500 mb-1">Recency (days)</label>
          <select v-model="search.filters.recency_days" class="input text-sm">
            <option :value="null">Any time</option>
            <option :value="7">Last 7 days</option>
            <option :value="30">Last 30 days</option>
            <option :value="90">Last 90 days</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-surface-500 mb-1">Sort by</label>
          <select v-model="search.filters.sort" @change="doSearch" class="input text-sm" data-testid="sort-select">
            <option value="newest">Newest</option>
            <option value="most_played">Most Played</option>
            <option value="recommended">Recommended</option>
          </select>
        </div>
        <div class="self-end">
          <button @click="resetSearch" class="btn-secondary text-sm">Clear</button>
        </div>
      </div>

      <div class="mt-4">
        <label class="block text-xs font-medium text-surface-500 mb-1">Filter by Tags</label>
        <div class="flex flex-wrap gap-2 mb-2">
          <span
            v-for="tag in search.filters.tags"
            :key="tag"
            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-brand-100 text-brand-700 text-sm font-medium"
          >
            {{ tag }}
            <button
              type="button"
              class="ml-1 text-brand-500 hover:text-brand-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-500 rounded-full"
              :aria-label="`Remove tag ${tag}`"
              @click="removeTag(tag)"
            >×</button>
          </span>
        </div>
        <input
          v-model="tagInput"
          type="text"
          placeholder="Type a tag and press Enter"
          class="input text-sm w-56"
          @keyup.enter="addTag"
          data-testid="tag-input"
        />
      </div>
    </div>

    <div v-if="search.isLoading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="n in 6" :key="n" class="skeleton h-40 rounded-xl" />
    </div>

    <div v-else-if="search.error" class="card text-center py-12">
      <p class="text-red-600">{{ search.error }}</p>
      <button @click="doSearch" class="btn-secondary mt-3">Retry</button>
    </div>

    <div v-else-if="search.results.length === 0 && hasSearched" class="card text-center py-12">
      <svg class="w-12 h-12 text-surface-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <p class="text-surface-500 font-medium">No results found</p>
      <p class="text-surface-400 text-sm mt-1">Try adjusting your search or filters</p>
    </div>

    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <AssetCard
        v-for="asset in search.results"
        :key="asset.id"
        :asset="asset"
        :is-favorite="favorites.isFavorite(asset.id)"
        @open="openDetail"
        @play="recordPlay"
        @toggle-favorite="onToggleFavorite"
      />
    </div>

    <AssetDetailDrawer
      v-if="selectedAssetId !== null"
      :asset-id="selectedAssetId"
      @close="selectedAssetId = null"
      @play="recordPlay"
      @updated="onAssetUpdated"
    />

    <div v-if="!hasSearched && !search.isLoading" class="card text-center py-16">
      <svg class="w-16 h-16 text-surface-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0" />
      </svg>
      <p class="text-surface-500 font-medium text-lg">Search the media library</p>
      <p class="text-surface-400 mt-1">Find audio announcements, video clips, and documents</p>
      <button @click="loadAll" class="btn-primary mt-4">Browse All</button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useSearchStore } from '@/stores/search'
import { useNowPlayingStore } from '@/stores/nowPlaying'
import { useFavoritesStore } from '@/stores/favorites'
import AssetCard from '@/components/AssetCard.vue'
import AssetDetailDrawer from '@/components/AssetDetailDrawer.vue'
import api from '@/api/axios'

const search = useSearchStore()
const nowPlaying = useNowPlayingStore()
const favorites = useFavoritesStore()
const hasSearched = ref(false)
const tagInput = ref('')
const selectedAssetId = ref(null)

const showUploadModal = ref(false)
const fileInput = ref(null)
const uploading = ref(false)
const uploadError = ref('')
const uploadSuccess = ref(false)
const uploadForm = ref({ title: '', description: '', tags: '' })

onMounted(() => {
  loadAll()
  favorites.load()
})

async function doSearch() {
  hasSearched.value = true
  await search.search()
}

function openDetail(assetId) {
  selectedAssetId.value = assetId
}

async function onToggleFavorite(asset) {
  await favorites.toggle(asset.id)
}

function onAssetUpdated(updated) {
  const idx = search.results.findIndex((a) => a.id === updated.id)
  if (idx >= 0) search.results[idx] = { ...search.results[idx], ...updated }
}

async function loadAll() {
  hasSearched.value = true
  await search.search()
}

function resetSearch() {
  search.reset()
  tagInput.value = ''
  hasSearched.value = false
  loadAll()
}

async function recordPlay(assetId) {
  await nowPlaying.recordPlay(assetId)
}

function addTag() {
  const tag = tagInput.value.trim()
  if (!tag) return
  if (!search.filters.tags) search.filters.tags = []
  if (!search.filters.tags.includes(tag)) {
    search.filters.tags.push(tag)
  }
  tagInput.value = ''
}

function removeTag(tag) {
  if (!search.filters.tags) return
  search.filters.tags = search.filters.tags.filter(t => t !== tag)
}

function closeUpload() {
  showUploadModal.value = false
  uploadError.value = ''
  uploadSuccess.value = false
  uploadForm.value = { title: '', description: '', tags: '' }
  if (fileInput.value) fileInput.value.value = ''
}

async function submitUpload() {
  uploadError.value = ''
  uploadSuccess.value = false
  const file = fileInput.value?.files?.[0]
  if (!file) { uploadError.value = 'Please select a file.'; return }
  if (!uploadForm.value.title.trim()) { uploadError.value = 'Title is required.'; return }

  const body = new FormData()
  body.append('file', file)
  body.append('title', uploadForm.value.title.trim())
  if (uploadForm.value.description.trim()) body.append('description', uploadForm.value.description.trim())
  const tags = uploadForm.value.tags.split(',').map(t => t.trim()).filter(Boolean)
  tags.forEach(t => body.append('tags[]', t))

  uploading.value = true
  try {
    await api.post('/assets', body, { headers: { 'Content-Type': undefined } })
    uploadSuccess.value = true
    uploadForm.value = { title: '', description: '', tags: '' }
    if (fileInput.value) fileInput.value.value = ''
  } catch (err) {
    uploadError.value = err.response?.data?.message || 'Upload failed.'
  } finally {
    uploading.value = false
  }
}
</script>
