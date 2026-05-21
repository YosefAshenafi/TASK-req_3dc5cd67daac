import { defineStore } from 'pinia'
import { ref, reactive } from 'vue'
import api from '@/api/axios'

export const useSearchStore = defineStore('search', () => {
  const results = ref([])
  const isLoading = ref(false)
  const error = ref(null)
  const meta = ref(null)

  const filters = reactive({
    q: '',
    tags: [],
    duration_max: null,
    recency_days: null,
    sort: 'newest',
  })

  async function search() {
    isLoading.value = true
    error.value = null
    try {
      const params = new URLSearchParams()
      if (filters.q) params.set('q', filters.q)
      filters.tags.forEach((t) => params.append('tags[]', t))
      if (filters.duration_max) params.set('duration_max', String(filters.duration_max))
      if (filters.recency_days) params.set('recency_days', String(filters.recency_days))
      if (filters.sort) params.set('sort', filters.sort)

      const response = await api.get('/assets?' + params.toString())
      results.value = response.data.data
      meta.value = response.data.meta
    } catch (err) {
      error.value = err.response?.data?.message || 'Search failed'
      results.value = []
    } finally {
      isLoading.value = false
    }
  }

  function setSort(sort) {
    filters.sort = sort
    search()
  }

  function reset() {
    filters.q = ''
    filters.tags = []
    filters.duration_max = null
    filters.recency_days = null
    filters.sort = 'newest'
    results.value = []
    meta.value = null
  }

  return { results, isLoading, error, meta, filters, search, setSort, reset }
})
