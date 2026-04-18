<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useUiStore } from '@/stores/ui'
import { useSettingsStore } from '@/stores/settings'
import AppLayout from '@/layouts/AppLayout.vue'

const route = useRoute()
const uiStore = useUiStore()
const settingsStore = useSettingsStore()

const useLayout = computed(() => {
  return !route.meta.public
})

if (typeof window !== 'undefined') {
  window.addEventListener('online', () => uiStore.setOffline(false))
  window.addEventListener('offline', () => uiStore.setOffline(true))
}

onMounted(() => {
  settingsStore.load()
})
</script>

<template>
  <AppLayout v-if="useLayout">
    <RouterView />
  </AppLayout>
  <RouterView v-else />
</template>
