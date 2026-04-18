<script setup lang="ts">
import { useRouter } from 'vue-router'
import { Users, Upload, BarChart2, Cpu, SlidersHorizontal, ArrowRight, TrendingUp, Shield } from 'lucide-vue-next'

const router = useRouter()

const sections = [
  {
    title: 'Users',
    description: 'Manage accounts, roles, freeze & blacklist actions.',
    to: '/admin/users',
    icon: Users,
    accent: 'text-sky-400',
    badge: 'Management',
  },
  {
    title: 'Uploads',
    description: 'Upload and review media assets through the pipeline.',
    to: '/admin/uploads',
    icon: Upload,
    accent: 'text-violet-400',
    badge: 'Media',
  },
  {
    title: 'Monitoring',
    description: 'API health, queue backlogs, and feature flag controls.',
    to: '/admin/monitoring',
    icon: BarChart2,
    accent: 'text-emerald-400',
    badge: 'System',
  },
  {
    title: 'Settings',
    description: 'Site name, tagline, and search tag configuration.',
    to: '/admin/settings',
    icon: SlidersHorizontal,
    accent: 'text-amber-400',
    badge: 'Config',
  },
  {
    title: 'Devices',
    description: 'Connected kiosk devices, events and replay audits.',
    to: '/devices',
    icon: Cpu,
    accent: 'text-rose-400',
    badge: 'Hardware',
  },
]
</script>

<template>
  <div class="min-h-full">
    <!-- Dark page header -->
    <div class="bg-gray-900 border-b border-gray-800 px-6 py-8">
      <div class="max-w-5xl mx-auto">
        <div class="flex items-center gap-3 mb-1">
          <Shield class="w-5 h-5 text-sky-400" />
          <span class="text-xs font-semibold text-sky-400 uppercase tracking-widest">Administration</span>
        </div>
        <h1 class="text-2xl font-bold text-white">Admin Console</h1>
        <p class="text-sm text-gray-400 mt-1">Manage your platform from a single place</p>
      </div>
    </div>

    <!-- Content -->
    <div class="max-w-5xl mx-auto px-6 py-8">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <button
          v-for="section in sections"
          :key="section.to"
          @click="router.push(section.to)"
          class="group relative bg-white rounded-xl border border-gray-200 p-5 text-left hover:border-gray-300 hover:shadow-md transition-all duration-200 overflow-hidden"
        >
          <!-- Top accent line -->
          <div class="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-sky-500/0 via-sky-500 to-sky-500/0 opacity-0 group-hover:opacity-100 transition-opacity" />

          <div class="flex items-start justify-between mb-4">
            <div class="w-10 h-10 rounded-lg bg-gray-900 flex items-center justify-center">
              <component :is="section.icon" :class="['w-5 h-5', section.accent]" />
            </div>
            <span class="text-xs font-medium text-gray-400 bg-gray-100 px-2 py-0.5 rounded-md">
              {{ section.badge }}
            </span>
          </div>

          <h2 class="text-sm font-semibold text-gray-900 mb-1">{{ section.title }}</h2>
          <p class="text-xs text-gray-500 leading-relaxed">{{ section.description }}</p>

          <div class="flex items-center gap-1 mt-4 text-xs font-medium text-sky-600 group-hover:gap-2 transition-all">
            Open
            <ArrowRight class="w-3 h-3" />
          </div>
        </button>
      </div>

      <!-- Quick stat strip -->
      <div class="mt-8 grid grid-cols-3 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4">
          <div class="flex items-center gap-2 mb-2">
            <TrendingUp class="w-4 h-4 text-sky-500" />
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Quick tip</span>
          </div>
          <p class="text-xs text-gray-600 leading-relaxed">Use <span class="font-medium text-gray-900">Monitoring</span> to check system health and reset feature flags.</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
          <div class="flex items-center gap-2 mb-2">
            <Upload class="w-4 h-4 text-violet-500" />
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Pipeline</span>
          </div>
          <p class="text-xs text-gray-600 leading-relaxed">Uploaded assets move through <span class="font-medium text-gray-900">processing → ready</span> automatically.</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
          <div class="flex items-center gap-2 mb-2">
            <SlidersHorizontal class="w-4 h-4 text-amber-500" />
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Settings</span>
          </div>
          <p class="text-xs text-gray-600 leading-relaxed">Customise site name, tagline, and search filter tags from <span class="font-medium text-gray-900">Settings</span>.</p>
        </div>
      </div>
    </div>
  </div>
</template>
