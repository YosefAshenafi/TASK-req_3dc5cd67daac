<script setup lang="ts">
import { useRouter } from 'vue-router'
import { Users, Upload, BarChart2, Cpu, ChevronRight } from 'lucide-vue-next'

const router = useRouter()

const sections = [
  {
    title: 'Users',
    description: 'Manage user accounts, roles, and access control',
    to: '/admin/users',
    icon: Users,
    color: 'bg-blue-50 text-blue-600',
    badge: 'Management',
    badgeStyle: 'bg-blue-100 text-blue-700',
  },
  {
    title: 'Uploads',
    description: 'Upload and manage media assets for the platform',
    to: '/admin/uploads',
    icon: Upload,
    color: 'bg-indigo-50 text-indigo-600',
    badge: 'Media',
    badgeStyle: 'bg-indigo-100 text-indigo-700',
  },
  {
    title: 'Monitoring',
    description: 'System health, queue status, and feature flags',
    to: '/admin/monitoring',
    icon: BarChart2,
    color: 'bg-emerald-50 text-emerald-600',
    badge: 'System',
    badgeStyle: 'bg-emerald-100 text-emerald-700',
  },
  {
    title: 'Devices',
    description: 'Connected kiosk devices and event logs',
    to: '/devices',
    icon: Cpu,
    color: 'bg-violet-50 text-violet-600',
    badge: 'Hardware',
    badgeStyle: 'bg-violet-100 text-violet-700',
  },
]
</script>

<template>
  <div class="p-6 max-w-4xl mx-auto">
    <div class="mb-8">
      <h1 class="text-2xl font-bold text-slate-900">Admin Console</h1>
      <p class="text-sm text-slate-500 mt-0.5">Manage your SmartPark platform</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <button
        v-for="section in sections"
        :key="section.to"
        @click="router.push(section.to)"
        class="group bg-white rounded-2xl border border-slate-200 p-6 text-left hover:border-indigo-300 hover:shadow-md hover:shadow-indigo-500/10 transition-all duration-200"
      >
        <div class="flex items-start justify-between mb-4">
          <div :class="['w-12 h-12 rounded-xl flex items-center justify-center', section.color]">
            <component :is="section.icon" class="w-5 h-5" />
          </div>
          <span :class="['text-xs font-semibold px-2.5 py-1 rounded-full', section.badgeStyle]">
            {{ section.badge }}
          </span>
        </div>
        <div class="flex items-end justify-between">
          <div>
            <h2 class="text-lg font-bold text-slate-900 group-hover:text-indigo-700 transition-colors">
              {{ section.title }}
            </h2>
            <p class="text-sm text-slate-500 mt-1 leading-snug">{{ section.description }}</p>
          </div>
          <ChevronRight class="w-5 h-5 text-slate-300 group-hover:text-indigo-500 transition-colors ml-3 shrink-0" />
        </div>
      </button>
    </div>
  </div>
</template>
