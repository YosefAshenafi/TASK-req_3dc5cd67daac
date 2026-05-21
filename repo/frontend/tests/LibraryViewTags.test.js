import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

vi.mock('@/api/axios', () => ({
  default: {
    get: vi.fn().mockResolvedValue({ data: { data: [], meta: null } }),
    post: vi.fn(),
  },
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: vi.fn() }),
  useRoute: () => ({ query: {} }),
}))

vi.mock('@/components/AssetCard.vue', () => ({
  default: { template: '<div class="asset-card"></div>' },
}))

import LibraryView from '@/views/LibraryView.vue'
import { useSearchStore } from '@/stores/search'

describe('LibraryView tag filter', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('renders tag input', () => {
    const wrapper = mount(LibraryView)
    expect(wrapper.find('[data-testid="tag-input"]').exists()).toBe(true)
  })

  it('adds a tag on Enter', async () => {
    const wrapper = mount(LibraryView)
    const search = useSearchStore()

    const input = wrapper.find('[data-testid="tag-input"]')
    await input.setValue('announcement')
    await input.trigger('keyup.enter')

    expect(search.filters.tags).toContain('announcement')
  })

  it('displays a chip for each added tag', async () => {
    const wrapper = mount(LibraryView)

    const input = wrapper.find('[data-testid="tag-input"]')
    await input.setValue('safety')
    await input.trigger('keyup.enter')

    expect(wrapper.text()).toContain('safety')
  })

  it('removes a tag when chip × is clicked', async () => {
    const wrapper = mount(LibraryView)
    const search = useSearchStore()

    const input = wrapper.find('[data-testid="tag-input"]')
    await input.setValue('training')
    await input.trigger('keyup.enter')

    expect(search.filters.tags).toContain('training')

    const removeBtn = wrapper.find('button[aria-label="Remove tag training"]')
    await removeBtn.trigger('click')

    expect(search.filters.tags).not.toContain('training')
  })

  it('does not add duplicate tags', async () => {
    const wrapper = mount(LibraryView)
    const search = useSearchStore()

    const input = wrapper.find('[data-testid="tag-input"]')
    await input.setValue('music')
    await input.trigger('keyup.enter')
    await input.setValue('music')
    await input.trigger('keyup.enter')

    expect(search.filters.tags.filter(t => t === 'music').length).toBe(1)
  })

  it('clears tag input after adding', async () => {
    const wrapper = mount(LibraryView)

    const input = wrapper.find('[data-testid="tag-input"]')
    await input.setValue('notice')
    await input.trigger('keyup.enter')

    expect(input.element.value).toBe('')
  })
})
