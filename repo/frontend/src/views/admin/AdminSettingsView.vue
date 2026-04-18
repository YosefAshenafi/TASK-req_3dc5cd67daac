<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useUiStore } from '@/stores/ui'
import { useSettingsStore } from '@/stores/settings'
import { settingsApi } from '@/services/api'
import { SlidersHorizontal, Plus, X, Save } from 'lucide-vue-next'

const uiStore = useUiStore()
const settingsStore = useSettingsStore()

const siteName = ref('')
const siteTagline = ref('')
const tags = ref<string[]>([])
const newTag = ref('')
const saving = ref(false)

onMounted(async () => {
  await settingsStore.load()
  siteName.value = settingsStore.siteName
  siteTagline.value = settingsStore.siteTagline
  tags.value = [...settingsStore.availableTags]
})

function addTag() {
  const t = newTag.value.trim()
  if (t && !tags.value.includes(t)) {
    tags.value.push(t)
  }
  newTag.value = ''
}

function removeTag(tag: string) {
  tags.value = tags.value.filter((t) => t !== tag)
}

function handleTagKeydown(e: KeyboardEvent) {
  if (e.key === 'Enter') {
    e.preventDefault()
    addTag()
  }
}

async function save() {
  saving.value = true
  try {
    const updated = await settingsApi.update({
      site_name: siteName.value.trim() || 'SmartPark',
      site_tagline: siteTagline.value.trim(),
      available_tags: tags.value,
    })
    settingsStore.applyUpdate(updated)
    uiStore.addNotification({ type: 'success', message: 'Settings saved' })
  } catch {
    uiStore.addNotification({ type: 'error', message: 'Failed to save settings' })
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="min-h-full">
    <!-- Dark page header -->
    <div class="bg-gray-900 border-b border-gray-800 px-6 py-8">
      <div class="max-w-3xl mx-auto">
        <div class="flex items-center gap-3 mb-1">
          <SlidersHorizontal class="w-5 h-5 text-amber-400" />
          <span class="text-xs font-semibold text-amber-400 uppercase tracking-widest">Config</span>
        </div>
        <h1 class="text-2xl font-bold text-white">Settings</h1>
        <p class="text-sm text-gray-400 mt-1">Manage site identity and search configuration</p>
      </div>
    </div>

    <!-- Content -->
    <div class="max-w-3xl mx-auto px-6 py-8 space-y-6">
      <!-- Site Identity -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
          <h2 class="text-sm font-semibold text-gray-900">Site Identity</h2>
          <p class="text-xs text-gray-500 mt-0.5">Displayed in the sidebar and page headers</p>
        </div>
        <div class="px-6 py-5 space-y-4">
          <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1.5">Site Name</label>
            <input
              v-model="siteName"
              type="text"
              placeholder="SmartPark"
              class="w-full px-3.5 py-2.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500/50 focus:border-sky-500"
            />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1.5">Tagline</label>
            <input
              v-model="siteTagline"
              type="text"
              placeholder="Find and discover media assets"
              class="w-full px-3.5 py-2.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500/50 focus:border-sky-500"
            />
          </div>
        </div>
      </div>

      <!-- Search Tags -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
          <h2 class="text-sm font-semibold text-gray-900">Search Tags</h2>
          <p class="text-xs text-gray-500 mt-0.5">Tags available as filters on the search page</p>
        </div>
        <div class="px-6 py-5">
          <!-- Current tags -->
          <div class="flex flex-wrap gap-2 mb-4 min-h-[2rem]">
            <span
              v-if="tags.length === 0"
              class="text-sm text-gray-400"
            >No tags yet. Add one below.</span>
            <span
              v-for="tag in tags"
              :key="tag"
              class="inline-flex items-center gap-1.5 px-3 py-1 bg-sky-50 border border-sky-200 text-sky-700 text-xs font-semibold rounded-full"
            >
              {{ tag }}
              <button
                @click="removeTag(tag)"
                class="text-sky-400 hover:text-sky-600 transition-colors"
                :aria-label="`Remove ${tag}`"
              >
                <X class="w-3 h-3" />
              </button>
            </span>
          </div>

          <!-- Add tag -->
          <div class="flex gap-2">
            <input
              v-model="newTag"
              type="text"
              placeholder="New tag…"
              @keydown="handleTagKeydown"
              class="flex-1 px-3.5 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500/50 focus:border-sky-500"
            />
            <button
              @click="addTag"
              class="flex items-center gap-1.5 px-4 py-2 bg-sky-600 text-white text-sm font-semibold rounded-lg hover:bg-sky-700 transition-colors"
            >
              <Plus class="w-4 h-4" />
              Add
            </button>
          </div>
        </div>
      </div>

      <!-- Save -->
      <div class="flex justify-end">
        <button
          @click="save"
          :disabled="saving"
          class="flex items-center gap-2 px-6 py-2.5 bg-sky-600 text-white text-sm font-semibold rounded-lg hover:bg-sky-700 disabled:opacity-50 transition-colors shadow-sm shadow-sky-600/20"
        >
          <Save class="w-4 h-4" />
          {{ saving ? 'Saving…' : 'Save Settings' }}
        </button>
      </div>
    </div>
  </div>
</template>
