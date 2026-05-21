<template>
  <div>
    <div class="flex items-center gap-4 mb-6 pb-4 border-b border-surface-200">
      <span class="badge bg-purple-100 text-purple-700 text-sm px-3 py-1">Admin Console</span>
      <nav class="flex gap-1">
        <RouterLink to="/admin" exact-active-class="text-brand-600 bg-brand-50" class="px-3 py-1.5 rounded text-sm font-medium text-surface-600 hover:text-surface-900 hover:bg-surface-100 transition-colors">Dashboard</RouterLink>
        <RouterLink to="/admin/users" class="px-3 py-1.5 rounded text-sm font-medium text-surface-600 hover:text-surface-900 hover:bg-surface-100 transition-colors" active-class="text-brand-600 bg-brand-50">Users</RouterLink>
        <RouterLink to="/admin/assets" class="px-3 py-1.5 rounded text-sm font-medium text-surface-600 hover:text-surface-900 hover:bg-surface-100 transition-colors" active-class="text-brand-600 bg-brand-50">Assets</RouterLink>
        <RouterLink to="/admin/monitoring" class="px-3 py-1.5 rounded text-sm font-medium text-surface-600 hover:text-surface-900 hover:bg-surface-100 transition-colors" active-class="text-brand-600 bg-brand-50">Monitoring</RouterLink>
      </nav>
    </div>
    <RouterView />
  </div>
</template>

<script setup>
import { RouterLink, RouterView } from 'vue-router'
</script>
