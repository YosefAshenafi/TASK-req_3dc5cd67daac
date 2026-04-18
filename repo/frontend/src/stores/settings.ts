import { defineStore } from 'pinia'
import { ref } from 'vue'
import { settingsApi } from '@/services/api'

const DEFAULT_TAGS = ['Safety', 'Overnight', 'Gate Issues', 'Parking', 'Event', 'General', 'Emergency']

export const useSettingsStore = defineStore('settings', () => {
  const siteName = ref('SmartPark')
  const siteTagline = ref('Find and discover media assets')
  const availableTags = ref<string[]>(DEFAULT_TAGS)
  const loaded = ref(false)

  async function load() {
    if (loaded.value) return
    try {
      const s = await settingsApi.get()
      siteName.value = s.site_name || 'SmartPark'
      siteTagline.value = s.site_tagline || 'Find and discover media assets'
      availableTags.value = s.available_tags?.length ? s.available_tags : DEFAULT_TAGS
      loaded.value = true
    } catch {
      // keep defaults — settings are non-critical
    }
  }

  function applyUpdate(s: { site_name: string; site_tagline: string; available_tags: string[] }) {
    siteName.value = s.site_name
    siteTagline.value = s.site_tagline
    availableTags.value = s.available_tags
  }

  return { siteName, siteTagline, availableTags, loaded, load, applyUpdate }
})
