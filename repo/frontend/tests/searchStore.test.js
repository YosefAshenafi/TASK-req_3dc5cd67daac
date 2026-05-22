import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useSearchStore } from '@/stores/search'

const apiMock = vi.hoisted(() => ({ get: vi.fn() }))
vi.mock('@/api/axios', () => ({ default: apiMock }))

describe('searchStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    apiMock.get.mockClear()
    apiMock.get.mockResolvedValue({ data: { data: [], meta: { total: 0, per_page: 20 } } })
  })

  describe('initial state', () => {
    it('results starts as empty array', () => {
      expect(useSearchStore().results).toEqual([])
    })

    it('isLoading starts as false', () => {
      expect(useSearchStore().isLoading).toBe(false)
    })

    it('error starts as null', () => {
      expect(useSearchStore().error).toBeNull()
    })

    it('meta starts as null', () => {
      expect(useSearchStore().meta).toBeNull()
    })

    it('filters.q starts as empty string', () => {
      expect(useSearchStore().filters.q).toBe('')
    })

    it('filters.sort defaults to newest', () => {
      expect(useSearchStore().filters.sort).toBe('newest')
    })

    it('filters.tags starts as empty array', () => {
      expect(useSearchStore().filters.tags).toEqual([])
    })

    it('filters.duration_max starts as null', () => {
      expect(useSearchStore().filters.duration_max).toBeNull()
    })

    it('filters.recency_days starts as null', () => {
      expect(useSearchStore().filters.recency_days).toBeNull()
    })
  })

  describe('search()', () => {
    it('sets isLoading true during fetch and false after', async () => {
      const store = useSearchStore()
      let resolveApi
      apiMock.get.mockReturnValue(new Promise(r => { resolveApi = r }))
      const promise = store.search()
      expect(store.isLoading).toBe(true)
      resolveApi({ data: { data: [], meta: null } })
      await promise
      expect(store.isLoading).toBe(false)
    })

    it('populates results from response', async () => {
      const store = useSearchStore()
      const mockData = [{ id: 1, title: 'Track A' }, { id: 2, title: 'Track B' }]
      apiMock.get.mockResolvedValue({ data: { data: mockData, meta: { total: 2 } } })
      await store.search()
      expect(store.results).toEqual(mockData)
    })

    it('stores meta from response', async () => {
      const store = useSearchStore()
      const meta = { total: 42, per_page: 20 }
      apiMock.get.mockResolvedValue({ data: { data: [], meta } })
      await store.search()
      expect(store.meta).toEqual(meta)
    })

    it('includes q param when set', async () => {
      const store = useSearchStore()
      store.filters.q = 'morning'
      await store.search()
      expect(apiMock.get).toHaveBeenCalledWith(expect.stringContaining('q=morning'))
    })

    it('includes sort param in request', async () => {
      const store = useSearchStore()
      store.filters.sort = 'most_played'
      await store.search()
      expect(apiMock.get).toHaveBeenCalledWith(expect.stringContaining('sort=most_played'))
    })

    it('includes duration_max param when set', async () => {
      const store = useSearchStore()
      store.filters.duration_max = 30
      await store.search()
      expect(apiMock.get).toHaveBeenCalledWith(expect.stringContaining('duration_max=30'))
    })

    it('includes recency_days param when set', async () => {
      const store = useSearchStore()
      store.filters.recency_days = 7
      await store.search()
      expect(apiMock.get).toHaveBeenCalledWith(expect.stringContaining('recency_days=7'))
    })

    it('includes tags[] params for each tag', async () => {
      const store = useSearchStore()
      store.filters.tags = ['safety', 'morning']
      await store.search()
      const url = apiMock.get.mock.calls[0][0]
      expect(url).toContain('tags%5B%5D=safety')
      expect(url).toContain('tags%5B%5D=morning')
    })

    it('omits q param when empty string', async () => {
      const store = useSearchStore()
      store.filters.q = ''
      await store.search()
      expect(apiMock.get).toHaveBeenCalledWith(expect.not.stringContaining('q='))
    })

    it('sets error from api response message on failure', async () => {
      const store = useSearchStore()
      apiMock.get.mockRejectedValue({ response: { data: { message: 'Server error' } } })
      await store.search()
      expect(store.error).toBe('Server error')
    })

    it('sets results to empty array on api failure', async () => {
      const store = useSearchStore()
      apiMock.get.mockRejectedValue(new Error('network'))
      await store.search()
      expect(store.results).toEqual([])
    })

    it('sets fallback error message when error has no response', async () => {
      const store = useSearchStore()
      apiMock.get.mockRejectedValue(new Error('timeout'))
      await store.search()
      expect(store.error).toBe('Search failed')
    })

    it('clears previous error before each search', async () => {
      const store = useSearchStore()
      apiMock.get.mockRejectedValueOnce(new Error('first error'))
      await store.search()
      expect(store.error).toBeTruthy()

      apiMock.get.mockResolvedValue({ data: { data: [], meta: null } })
      await store.search()
      expect(store.error).toBeNull()
    })
  })

  describe('setSort()', () => {
    it('updates filters.sort', async () => {
      const store = useSearchStore()
      await store.setSort('most_played')
      expect(store.filters.sort).toBe('most_played')
    })

    it('triggers an api call', async () => {
      const store = useSearchStore()
      await store.setSort('recommended')
      expect(apiMock.get).toHaveBeenCalled()
    })
  })

  describe('reset()', () => {
    it('clears filters.q', () => {
      const store = useSearchStore()
      store.filters.q = 'test'
      store.reset()
      expect(store.filters.q).toBe('')
    })

    it('clears filters.tags', () => {
      const store = useSearchStore()
      store.filters.tags = ['safety', 'morning']
      store.reset()
      expect(store.filters.tags).toEqual([])
    })

    it('resets filters.sort to newest', () => {
      const store = useSearchStore()
      store.filters.sort = 'recommended'
      store.reset()
      expect(store.filters.sort).toBe('newest')
    })

    it('sets filters.duration_max to null', () => {
      const store = useSearchStore()
      store.filters.duration_max = 60
      store.reset()
      expect(store.filters.duration_max).toBeNull()
    })

    it('sets filters.recency_days to null', () => {
      const store = useSearchStore()
      store.filters.recency_days = 7
      store.reset()
      expect(store.filters.recency_days).toBeNull()
    })

    it('clears results', async () => {
      const store = useSearchStore()
      apiMock.get.mockResolvedValue({ data: { data: [{ id: 1 }], meta: null } })
      await store.search()
      expect(store.results).toHaveLength(1)
      store.reset()
      expect(store.results).toEqual([])
    })

    it('clears meta', async () => {
      const store = useSearchStore()
      apiMock.get.mockResolvedValue({ data: { data: [], meta: { total: 5 } } })
      await store.search()
      expect(store.meta).not.toBeNull()
      store.reset()
      expect(store.meta).toBeNull()
    })
  })
})
