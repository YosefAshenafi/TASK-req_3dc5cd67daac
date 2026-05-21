<template>
  <div>
    <div class="flex items-center gap-3 mb-6 pb-4 border-b border-surface-200">
      <span class="badge bg-green-100 text-green-700 text-sm px-3 py-1">Technician Console</span>
    </div>
    <RouterView />
  </div>
</template>

<script setup>
import { RouterView } from 'vue-router'
</script>
