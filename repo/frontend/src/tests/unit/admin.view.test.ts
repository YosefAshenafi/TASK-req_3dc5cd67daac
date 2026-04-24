import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent } from 'vue'
import AdminView from '@/views/admin/AdminView.vue'

const push = vi.fn()

vi.mock('vue-router', () => ({
  useRouter: () => ({ push }),
  RouterLink: defineComponent({
    name: 'RouterLink',
    props: { to: { type: [String, Object], required: false } },
    template: '<a><slot /></a>',
  }),
}))

describe('AdminView.vue (admin console landing)', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    push.mockReset()
  })

  it('renders the admin console header and all five section tiles', () => {
    const wrapper = mount(AdminView)
    const body = wrapper.text()

    // The header is the first thing a returning admin sees — wording must
    // stay stable so the E2E smoke test keeps passing.
    expect(body).toContain('Admin Console')
    expect(body).toContain('Administration')

    // Each of the five navigation tiles must appear.
    expect(body).toContain('Users')
    expect(body).toContain('Uploads')
    expect(body).toContain('Monitoring')
    expect(body).toContain('Settings')
    expect(body).toContain('Devices')
  })

  it('navigates to /admin/users when the Users tile is clicked', async () => {
    const wrapper = mount(AdminView)
    const usersButton = wrapper
      .findAll('button')
      .find((b) => b.text().includes('Users'))
    expect(usersButton).toBeDefined()

    await usersButton!.trigger('click')

    expect(push).toHaveBeenCalledWith('/admin/users')
  })

  it('navigates to /admin/uploads, /admin/monitoring, /admin/settings, and /devices for the other tiles', async () => {
    const wrapper = mount(AdminView)

    const click = async (label: string) => {
      const btn = wrapper.findAll('button').find((b) => b.text().includes(label))
      await btn!.trigger('click')
    }

    await click('Uploads')
    await click('Monitoring')
    await click('Settings')
    await click('Devices')

    expect(push.mock.calls.map((c) => c[0])).toEqual([
      '/admin/uploads',
      '/admin/monitoring',
      '/admin/settings',
      '/devices',
    ])
  })

  it('shows the quick-tip strip at the bottom of the page', () => {
    // The tip strip is the admin's at-a-glance reminder of what each section
    // does — regression test for the three category labels.
    const wrapper = mount(AdminView)
    const body = wrapper.text()

    expect(body).toContain('Quick tip')
    expect(body).toContain('Pipeline')
    // The "Settings" label is reused in both a tile and the tip strip; just
    // verify the "processing → ready" pipeline hint that only exists in the tip.
    expect(body).toContain('processing')
    expect(body).toContain('ready')
  })
})
