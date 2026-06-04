<template>
  <div
    class="fixed inset-0 z-50 flex justify-end bg-black/40 backdrop-blur-sm"
    @click.self="$emit('close')"
  >
    <div
      class="bg-white h-full w-full max-w-md shadow-2xl flex flex-col drawer-panel"
      role="dialog"
      aria-modal="true"
      aria-label="Asset details"
    >
      <!-- Header -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-surface-200 shrink-0">
        <h2 class="text-lg font-semibold text-surface-900">Asset Details</h2>
        <button
          @click="$emit('close')"
          class="text-surface-400 hover:text-surface-700 text-2xl leading-none focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-500 rounded"
          aria-label="Close details"
        >×</button>
      </div>

      <!-- Body -->
      <div class="flex-1 overflow-y-auto px-6 py-5">
        <div v-if="loading" class="space-y-3">
          <div class="skeleton h-6 w-2/3 rounded" />
          <div class="skeleton h-4 w-full rounded" />
          <div class="skeleton h-4 w-1/2 rounded" />
        </div>

        <div v-else-if="error" class="card text-center py-10">
          <p class="text-red-600">{{ error }}</p>
          <button @click="load" class="btn-secondary mt-3">Retry</button>
        </div>

        <template v-else-if="asset">
          <!-- View mode -->
          <div v-if="!editing">
            <h3 class="text-xl font-bold text-surface-900 break-words" data-testid="detail-title">{{ asset.title }}</h3>
            <span class="badge mt-2 inline-block" :class="statusClass">{{ asset.status }}</span>

            <dl class="mt-5 space-y-4 text-sm">
              <div>
                <dt class="font-medium text-surface-500">Description</dt>
                <dd class="text-surface-900 mt-0.5 whitespace-pre-line" data-testid="detail-description">
                  {{ asset.description || '—' }}
                </dd>
              </div>
              <div>
                <dt class="font-medium text-surface-500">Tags</dt>
                <dd class="mt-1 flex flex-wrap gap-1.5">
                  <span v-for="tag in (asset.tags || [])" :key="tag" class="badge bg-surface-100 text-surface-600">{{ tag }}</span>
                  <span v-if="!(asset.tags || []).length" class="text-surface-400">—</span>
                </dd>
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <dt class="font-medium text-surface-500">Type</dt>
                  <dd class="text-surface-900 mt-0.5">{{ asset.mime_type || '—' }}</dd>
                </div>
                <div>
                  <dt class="font-medium text-surface-500">Size</dt>
                  <dd class="text-surface-900 mt-0.5">{{ formatSize(asset.file_size) }}</dd>
                </div>
                <div>
                  <dt class="font-medium text-surface-500">Duration</dt>
                  <dd class="text-surface-900 mt-0.5">{{ asset.duration ? formatDuration(asset.duration) : '—' }}</dd>
                </div>
                <div>
                  <dt class="font-medium text-surface-500">Plays</dt>
                  <dd class="text-surface-900 mt-0.5">{{ asset.play_count }}</dd>
                </div>
                <div>
                  <dt class="font-medium text-surface-500">Uploaded by</dt>
                  <dd class="text-surface-900 mt-0.5">#{{ asset.uploaded_by }}</dd>
                </div>
                <div>
                  <dt class="font-medium text-surface-500">Created</dt>
                  <dd class="text-surface-900 mt-0.5">{{ formatDate(asset.created_at) }}</dd>
                </div>
              </div>
            </dl>
          </div>

          <!-- Edit mode (admin only) -->
          <form v-else @submit.prevent="save" class="space-y-4">
            <div>
              <label for="edit-title" class="block text-sm font-medium text-surface-700 mb-1">Title <span class="text-red-500">*</span></label>
              <input id="edit-title" v-model="form.title" type="text" maxlength="255" required class="input w-full" :class="{ 'border-red-500': fieldErrors.title }" />
              <p v-if="fieldErrors.title" class="mt-1 text-sm text-red-600">{{ fieldErrors.title }}</p>
            </div>
            <div>
              <label for="edit-description" class="block text-sm font-medium text-surface-700 mb-1">Description</label>
              <textarea id="edit-description" v-model="form.description" rows="3" maxlength="5000" class="input w-full resize-none" />
            </div>
            <div>
              <label for="edit-tags" class="block text-sm font-medium text-surface-700 mb-1">Tags (comma-separated)</label>
              <input id="edit-tags" v-model="form.tags" type="text" class="input w-full" placeholder="e.g. safety, training" />
            </div>
            <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
          </form>
        </template>
      </div>

      <!-- Footer actions -->
      <div v-if="asset" class="px-6 py-4 border-t border-surface-200 shrink-0 flex items-center gap-3">
        <template v-if="!editing">
          <button class="btn-primary inline-flex items-center gap-1.5" data-testid="detail-play" @click="$emit('play', asset.id)">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.018 14L14.41 8 4.018 2v12zM3 2.482a1 1 0 011.5-.866l9.518 5.5a1 1 0 010 1.732l-9.518 5.5A1 1 0 013 13.482v-11z" clip-rule="evenodd" /></svg>
            Play
          </button>
          <button
            class="btn-secondary inline-flex items-center gap-1.5"
            data-testid="detail-favorite"
            :aria-pressed="favorites.isFavorite(asset.id) ? 'true' : 'false'"
            @click="favorites.toggle(asset.id)"
          >
            <svg class="w-4 h-4" :class="favorites.isFavorite(asset.id) ? 'text-red-500' : 'text-surface-400'" :fill="favorites.isFavorite(asset.id) ? 'currentColor' : 'none'" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
            </svg>
            {{ favorites.isFavorite(asset.id) ? 'Favorited' : 'Favorite' }}
          </button>
          <button v-if="auth.isAdmin" class="btn-secondary ml-auto" data-testid="detail-edit" @click="startEdit">Edit</button>
        </template>
        <template v-else>
          <button class="btn-secondary" :disabled="saving" @click="editing = false">Cancel</button>
          <button class="btn-primary ml-auto" :disabled="saving" data-testid="detail-save" @click="save">
            {{ saving ? 'Saving…' : 'Save' }}
          </button>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch } from 'vue'
import api from '@/api/axios'
import { useAuthStore } from '@/stores/auth'
import { useFavoritesStore } from '@/stores/favorites'

const props = defineProps({
  assetId: { type: [Number, String], required: true },
})

const emit = defineEmits(['close', 'play', 'updated'])

const auth = useAuthStore()
const favorites = useFavoritesStore()

const asset = ref(null)
const loading = ref(true)
const error = ref('')
const editing = ref(false)
const saving = ref(false)
const form = reactive({ title: '', description: '', tags: '' })
const fieldErrors = reactive({ title: '' })

async function load() {
  loading.value = true
  error.value = ''
  try {
    const response = await api.get(`/assets/${props.assetId}`)
    asset.value = response.data.data
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load asset details.'
  } finally {
    loading.value = false
  }
}

function startEdit() {
  fieldErrors.title = ''
  error.value = ''
  form.title = asset.value.title
  form.description = asset.value.description || ''
  form.tags = (asset.value.tags || []).join(', ')
  editing.value = true
}

async function save() {
  fieldErrors.title = ''
  error.value = ''
  if (!form.title.trim()) {
    fieldErrors.title = 'Title is required.'
    return
  }
  const payload = {
    title: form.title.trim(),
    description: form.description.trim() || null,
    tags: form.tags.split(',').map((t) => t.trim()).filter(Boolean),
  }
  saving.value = true
  try {
    const response = await api.patch(`/admin/assets/${props.assetId}`, payload)
    asset.value = response.data.data
    editing.value = false
    emit('updated', asset.value)
  } catch (err) {
    if (err.response?.status === 422) {
      fieldErrors.title = err.response.data?.errors?.title?.[0] || ''
      error.value = err.response.data?.message || 'Validation failed.'
    } else {
      error.value = err.response?.data?.message || 'Failed to save changes.'
    }
  } finally {
    saving.value = false
  }
}

const statusClass = ref('')
watch(asset, (a) => {
  statusClass.value = a?.status === 'approved'
    ? 'bg-green-100 text-green-700'
    : a?.status === 'rejected'
      ? 'bg-red-100 text-red-700'
      : 'bg-amber-100 text-amber-700'
})

watch(() => props.assetId, () => {
  editing.value = false
  load()
})

function formatDuration(seconds) {
  const m = Math.floor(seconds / 60)
  const s = seconds % 60
  return `${m}:${String(s).padStart(2, '0')}`
}

function formatSize(bytes) {
  if (!bytes && bytes !== 0) return '—'
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

function formatDate(iso) {
  if (!iso) return '—'
  try {
    return new Date(iso).toLocaleString()
  } catch {
    return iso
  }
}

onMounted(load)
</script>

<style scoped>
.drawer-panel {
  animation: slidein 0.2s ease-out;
}
@keyframes slidein {
  from { transform: translateX(100%); }
  to { transform: translateX(0); }
}
</style>
