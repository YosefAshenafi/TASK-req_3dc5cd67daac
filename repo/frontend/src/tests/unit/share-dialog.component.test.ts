import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import ShareDialog from '@/components/ShareDialog.vue'
import { useUiStore } from '@/stores/ui'

const api = { revokeShare: vi.fn() }
vi.mock('@/services/api', () => ({
  playlistsApi: {
    revokeShare: (...a: unknown[]) => api.revokeShare(...a),
  },
}))

function shareProp(overrides: Record<string, unknown> = {}) {
  return {
    share: {
      id: 1,
      playlist_id: 7,
      code: 'SHAREME8',
      created_at: new Date().toISOString(),
      expires_at: new Date(Date.now() + 10 * 60 * 1000).toISOString(),
      ...overrides,
    },
    playlistId: 7,
  }
}

describe('ShareDialog.vue', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    api.revokeShare.mockReset()
  })

  it('renders the share code and a live TTL countdown', async () => {
    // onMounted → updateCountdown runs after initial render, so we must wait
    // one microtask tick before reading the formatted TTL.
    const props = {
      share: {
        id: 1,
        playlist_id: 7,
        code: 'SHAREME8',
        created_at: new Date().toISOString(),
        expires_at: new Date(Date.now() + 10 * 60 * 1000).toISOString(),
      },
      playlistId: 7,
    }

    const wrapper = mount(ShareDialog, { props })
    await flushPromises()

    expect(wrapper.text()).toContain('SHAREME8')
    expect(wrapper.text()).toMatch(/(9m 5\ds|10m 0s)/)
  })

  it('colors the TTL red when less than 60 seconds remain', async () => {
    const wrapper = mount(ShareDialog, {
      props: {
        share: {
          id: 1,
          playlist_id: 7,
          code: 'SOONDEAD',
          created_at: new Date().toISOString(),
          expires_at: new Date(Date.now() + 30 * 1000).toISOString(),
        },
        playlistId: 7,
      },
    })
    await flushPromises()

    // 30s remaining → red.
    expect(wrapper.html()).toContain('text-red-600')
  })

  it('shows "Expired" once the TTL drops to zero', async () => {
    const wrapper = mount(ShareDialog, {
      props: {
        share: {
          id: 1,
          playlist_id: 7,
          code: 'GONE',
          created_at: new Date(Date.now() - 10 * 60 * 1000).toISOString(),
          expires_at: new Date(Date.now() - 1000).toISOString(),
        },
        playlistId: 7,
      },
    })
    await flushPromises()

    expect(wrapper.text()).toContain('Expired')
  })

  it('copies the code to the clipboard and toasts success', async () => {
    const writeText = vi.fn().mockResolvedValue(undefined)
    vi.stubGlobal('navigator', { ...navigator, clipboard: { writeText } })
    const ui = useUiStore()
    const notify = vi.spyOn(ui, 'addNotification')

    const wrapper = mount(ShareDialog, { props: shareProp() })
    await wrapper.findAll('button').find((b) => b.text() === 'Copy Code')!.trigger('click')
    await flushPromises()

    expect(writeText).toHaveBeenCalledWith('SHAREME8')
    expect(notify).toHaveBeenCalledWith({ type: 'success', message: 'Code copied to clipboard!' })
  })

  it('falls back to an error toast when clipboard write fails', async () => {
    const writeText = vi.fn().mockRejectedValue(new Error('no clipboard'))
    vi.stubGlobal('navigator', { ...navigator, clipboard: { writeText } })
    const ui = useUiStore()
    const notify = vi.spyOn(ui, 'addNotification')

    const wrapper = mount(ShareDialog, { props: shareProp() })
    await wrapper.findAll('button').find((b) => b.text() === 'Copy Code')!.trigger('click')
    await flushPromises()

    expect(notify).toHaveBeenCalledWith({ type: 'error', message: 'Failed to copy code' })
  })

  it('revokes the share and emits revoked on success', async () => {
    api.revokeShare.mockResolvedValue(undefined)
    const wrapper = mount(ShareDialog, { props: shareProp() })

    await wrapper.findAll('button').find((b) => b.text() === 'Revoke')!.trigger('click')
    await flushPromises()

    expect(api.revokeShare).toHaveBeenCalledWith(7, 1)
    expect(wrapper.emitted('revoked')).toBeTruthy()
  })

  it('notifies on revoke failure and does NOT emit revoked', async () => {
    api.revokeShare.mockRejectedValue(new Error('boom'))
    const ui = useUiStore()
    const notify = vi.spyOn(ui, 'addNotification')

    const wrapper = mount(ShareDialog, { props: shareProp() })
    await wrapper.findAll('button').find((b) => b.text() === 'Revoke')!.trigger('click')
    await flushPromises()

    expect(notify).toHaveBeenCalledWith({ type: 'error', message: 'Failed to revoke share' })
    expect(wrapper.emitted('revoked')).toBeFalsy()
  })

  it('emits close when the backdrop or Close button is clicked', async () => {
    const wrapper = mount(ShareDialog, { props: shareProp() })

    await wrapper.trigger('click') // backdrop
    expect(wrapper.emitted('close')).toBeTruthy()

    const wrapper2 = mount(ShareDialog, { props: shareProp() })
    await wrapper2.findAll('button').find((b) => b.text() === 'Close')!.trigger('click')
    expect(wrapper2.emitted('close')).toBeTruthy()
  })
})
