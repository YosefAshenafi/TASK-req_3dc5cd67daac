import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import RedeemDialog from '@/components/RedeemDialog.vue'
import { ApiError } from '@/services/api'

const api = { redeem: vi.fn() }
vi.mock('@/services/api', async () => {
  const actual = await vi.importActual<typeof import('@/services/api')>('@/services/api')
  return {
    ...actual,
    playlistsApi: {
      redeem: (...a: unknown[]) => api.redeem(...a),
    },
  }
})

async function typeCode(wrapper: ReturnType<typeof mount>, code: string) {
  for (const ch of code) {
    const btn = wrapper.findAll('button').find((b) => b.text() === ch)
    if (!btn) throw new Error(`no keypad button for ${ch}`)
    await btn.trigger('click')
  }
}

describe('RedeemDialog.vue', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    api.redeem.mockReset()
  })

  it('builds the code via the keypad and shows progress', async () => {
    const wrapper = mount(RedeemDialog)
    await typeCode(wrapper, 'ABCD')

    expect(wrapper.text()).toContain('ABCD')
    expect(wrapper.text()).toContain('4/8 characters')
  })

  it('backspace and clear reset the code progressively', async () => {
    const wrapper = mount(RedeemDialog)
    await typeCode(wrapper, 'ABCD')

    await wrapper.findAll('button').find((b) => b.text() === '← Back')!.trigger('click')
    expect(wrapper.text()).toContain('3/8 characters')

    await wrapper.findAll('button').find((b) => b.text() === 'Clear')!.trigger('click')
    expect(wrapper.text()).toContain('0/8 characters')
  })

  it('blocks submit when code is shorter than 8 characters', async () => {
    const wrapper = mount(RedeemDialog)
    await typeCode(wrapper, 'ABC')

    const submit = wrapper.findAll('button').find((b) => b.text() === 'Redeem')!
    expect(submit.attributes('disabled')).toBeDefined()
  })

  it('redeems a full 8-char code and emits redeemed with the playlist', async () => {
    api.redeem.mockResolvedValue({
      id: 42,
      owner_id: 1,
      name: 'Redeemed List',
      items_count: 0,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    })
    const wrapper = mount(RedeemDialog)
    await typeCode(wrapper, 'ABCDEFGH')

    await wrapper.findAll('button').find((b) => b.text() === 'Redeem')!.trigger('click')
    await flushPromises()

    expect(api.redeem).toHaveBeenCalledWith('ABCDEFGH')
    const emitted = wrapper.emitted('redeemed')
    expect(emitted).toBeTruthy()
    expect((emitted![0] as [unknown])[0]).toMatchObject({ id: 42, name: 'Redeemed List' })
  })

  it.each([
    [404, 'Code not found or already expired'],
    [410, 'This code has expired'],
    [423, 'This code has been blacklisted'],
  ])('renders the status-specific error for ApiError %i', async (status, expected) => {
    api.redeem.mockRejectedValue(new ApiError(status, { message: 'server says nope' }, `HTTP ${status}`))
    const wrapper = mount(RedeemDialog)
    await typeCode(wrapper, 'ABCDEFGH')

    await wrapper.findAll('button').find((b) => b.text() === 'Redeem')!.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain(expected)
  })

  it('falls back to the body message for generic ApiErrors', async () => {
    api.redeem.mockRejectedValue(new ApiError(500, { message: 'Internal error' }, 'Internal error'))
    const wrapper = mount(RedeemDialog)
    await typeCode(wrapper, 'ABCDEFGH')

    await wrapper.findAll('button').find((b) => b.text() === 'Redeem')!.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Internal error')
  })

  it('caps the code at 8 characters — extra presses are ignored', async () => {
    const wrapper = mount(RedeemDialog)
    await typeCode(wrapper, 'ABCDEFGH')
    // Try to press another key; should not increase length.
    await wrapper.findAll('button').find((b) => b.text() === 'A')!.trigger('click')

    expect(wrapper.text()).toContain('8/8 characters')
  })
})
