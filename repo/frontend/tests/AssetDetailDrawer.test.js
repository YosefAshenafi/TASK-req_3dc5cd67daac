import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

const apiGet = vi.hoisted(() => vi.fn())
const apiPost = vi.hoisted(() => vi.fn())
const apiPatch = vi.hoisted(() => vi.fn())
const apiDelete = vi.hoisted(() => vi.fn())
vi.mock('@/api/axios', () => ({
  default: { get: apiGet, post: apiPost, patch: apiPatch, delete: apiDelete },
}))

import AssetDetailDrawer from '@/components/AssetDetailDrawer.vue'
import { useAuthStore } from '@/stores/auth'

const mockAsset = {
  id: 7,
  title: 'Safety Briefing',
  description: 'Required safety info',
  tags: ['safety', 'training'],
  mime_type: 'video/mp4',
  file_size: 52428800,
  duration: 180,
  status: 'approved',
  play_count: 8,
  uploaded_by: 1,
  created_at: '2026-06-01T10:00:00+00:00',
  updated_at: '2026-06-01T10:00:00+00:00',
}

describe('AssetDetailDrawer', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    apiGet.mockReset()
    apiPost.mockReset()
    apiPatch.mockReset()
    apiDelete.mockReset()
    apiGet.mockResolvedValue({ data: { data: mockAsset } })
    apiPost.mockResolvedValue({ data: { data: { favorite_id: 99 } } })
    apiPatch.mockResolvedValue({ data: { data: { ...mockAsset, title: 'Updated Event' } } })
  })

  it('loads the asset and renders all of its fields', async () => {
    const wrapper = mount(AssetDetailDrawer, { props: { assetId: 7 } })
    await flushPromises()

    expect(apiGet).toHaveBeenCalledWith('/assets/7')
    expect(wrapper.text()).toContain('Safety Briefing')
    expect(wrapper.text()).toContain('Required safety info')
    expect(wrapper.text()).toContain('safety')
    expect(wrapper.text()).toContain('training')
    expect(wrapper.text()).toContain('video/mp4')
    expect(wrapper.text()).toContain('approved')
    expect(wrapper.text()).toContain('8') // play count
  })

  it('emits close when the close button is clicked', async () => {
    const wrapper = mount(AssetDetailDrawer, { props: { assetId: 7 } })
    await flushPromises()
    await wrapper.find('button[aria-label="Close details"]').trigger('click')
    expect(wrapper.emitted('close')).toBeTruthy()
  })

  it('emits play with the asset id', async () => {
    const wrapper = mount(AssetDetailDrawer, { props: { assetId: 7 } })
    await flushPromises()
    await wrapper.find('[data-testid="detail-play"]').trigger('click')
    expect(wrapper.emitted('play')[0]).toEqual([7])
  })

  it('does not show the Edit button for non-admins', async () => {
    const wrapper = mount(AssetDetailDrawer, { props: { assetId: 7 } })
    await flushPromises()
    expect(wrapper.find('[data-testid="detail-edit"]').exists()).toBe(false)
  })

  it('shows Edit for admins and saves changes via PATCH /admin/assets/{id}', async () => {
    const auth = useAuthStore()
    auth.user = { id: 1, role: 'admin', username: 'admin' }

    const wrapper = mount(AssetDetailDrawer, { props: { assetId: 7 } })
    await flushPromises()

    const editBtn = wrapper.find('[data-testid="detail-edit"]')
    expect(editBtn.exists()).toBe(true)
    await editBtn.trigger('click')

    const titleInput = wrapper.find('#edit-title')
    expect(titleInput.exists()).toBe(true)
    await titleInput.setValue('Updated Event')

    await wrapper.find('[data-testid="detail-save"]').trigger('click')
    await flushPromises()

    expect(apiPatch).toHaveBeenCalledWith(
      '/admin/assets/7',
      expect.objectContaining({ title: 'Updated Event' }),
    )
    expect(wrapper.emitted('updated')).toBeTruthy()
    // Detail view reflects the new value immediately, old value gone.
    expect(wrapper.text()).toContain('Updated Event')
    expect(wrapper.text()).not.toContain('Safety Briefing')
  })

  it('blocks saving an empty title and does not call the API', async () => {
    const auth = useAuthStore()
    auth.user = { id: 1, role: 'admin' }

    const wrapper = mount(AssetDetailDrawer, { props: { assetId: 7 } })
    await flushPromises()
    await wrapper.find('[data-testid="detail-edit"]').trigger('click')
    await wrapper.find('#edit-title').setValue('')
    await wrapper.find('[data-testid="detail-save"]').trigger('click')
    await flushPromises()

    expect(apiPatch).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Title is required.')
  })

  it('toggles favorite via POST /favorites', async () => {
    const wrapper = mount(AssetDetailDrawer, { props: { assetId: 7 } })
    await flushPromises()
    await wrapper.find('[data-testid="detail-favorite"]').trigger('click')
    await flushPromises()
    expect(apiPost).toHaveBeenCalledWith('/favorites', { asset_id: 7 })
  })
})
