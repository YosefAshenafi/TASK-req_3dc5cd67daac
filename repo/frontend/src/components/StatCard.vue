<template>
  <div class="card">
    <p class="text-sm font-medium text-surface-500">{{ label }}</p>
    <p class="text-3xl font-bold mt-1" :class="valueClass">{{ value ?? '—' }}</p>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  label: String,
  value: [Number, String],
  color: { type: String, default: 'blue' },
})

const valueClass = computed(() => ({
  'text-blue-600': props.color === 'blue',
  'text-green-600': props.color === 'green',
  'text-yellow-600': props.color === 'yellow',
  'text-red-600': props.color === 'red',
  'text-purple-600': props.color === 'purple',
}))
</script>
