<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useUiStore } from '@/stores/ui'
import AppLayout from '@/layouts/AppLayout.vue'

const route = useRoute()
const uiStore = useUiStore()

const useLayout = computed(() => {
  return !route.meta.public
})

// Set up online/offline detection
if (typeof window !== 'undefined') {
  window.addEventListener('online', () => uiStore.setOffline(false))
  window.addEventListener('offline', () => uiStore.setOffline(true))
}
</script>

<template>
  <AppLayout v-if="useLayout">
    <RouterView />
  </AppLayout>
  <RouterView v-else />
</template>
