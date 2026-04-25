import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AdminUsersView from '@/views/admin/AdminUsersView.vue'
import { useUiStore } from '@/stores/ui'
import { ApiError } from '@/services/api'

const api = {
  list: vi.fn(),
  create: vi.fn(),
  freeze: vi.fn(),
  unfreeze: vi.fn(),
  blacklist: vi.fn(),
  softDelete: vi.fn(),
}

vi.mock('@/services/api', async () => {
  const actual = await vi.importActual<typeof import('@/services/api')>('@/services/api')
  return {
    ...actual,
    usersApi: {
      list: (...a: unknown[]) => api.list(...a),
      create: (...a: unknown[]) => api.create(...a),
      freeze: (...a: unknown[]) => api.freeze(...a),
      unfreeze: (...a: unknown[]) => api.unfreeze(...a),
      blacklist: (...a: unknown[]) => api.blacklist(...a),
      softDelete: (...a: unknown[]) => api.softDelete(...a),
    },
  }
})

function user(overrides: Record<string, unknown> = {}) {
  return {
    id: 1,
    username: 'alice',
    role: 'user' as const,
    frozen_until: null,
    blacklisted_at: null,
    deleted_at: null,
    ...overrides,
  }
}

describe('AdminUsersView.vue', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    Object.values(api).forEach((m) => m.mockReset())
  })

  it('renders a row for each user with the correct status label', async () => {
    api.list.mockResolvedValue({
      items: [
        user({ id: 1, username: 'alice', role: 'admin' }),
        user({ id: 2, username: 'bob', role: 'user', frozen_until: new Date(Date.now() + 60_000).toISOString() }),
        user({ id: 3, username: 'carol', role: 'user', blacklisted_at: new Date().toISOString() }),
      ],
    })

    const wrapper = mount(AdminUsersView)
    await flushPromises()

    const body = wrapper.text()
    expect(body).toContain('alice')
    expect(body).toContain('bob')
    expect(body).toContain('carol')
    expect(body).toContain('Active')
    expect(body).toContain('Frozen')
    expect(body).toContain('Blacklisted')
  })

  it('creates a user via the form and appends the row', async () => {
    api.list.mockResolvedValue({ items: [] })
    api.create.mockResolvedValue(user({ id: 10, username: 'newbie', role: 'technician' }))

    const wrapper = mount(AdminUsersView)
    await flushPromises()

    // First "Create User" button is the header toggle — click to expand.
    const createUserButtons = () => wrapper.findAll('button').filter((b) => b.text().includes('Create User'))
    await createUserButtons()[0]!.trigger('click')

    const [usernameInput, passwordInput] = wrapper.findAll('input')
    await usernameInput!.setValue('newbie')
    await passwordInput!.setValue('StrongPass!23')
    await wrapper.get('select').setValue('technician')

    // Second "Create User" button is now the submit action inside the form.
    const buttons = createUserButtons()
    await buttons[buttons.length - 1]!.trigger('click')
    await flushPromises()

    expect(api.create).toHaveBeenCalledWith({ username: 'newbie', password: 'StrongPass!23', role: 'technician' })
    expect(wrapper.text()).toContain('newbie')
  })

  it('renders the ApiError message from the backend on create failure', async () => {
    api.list.mockResolvedValue({ items: [] })
    api.create.mockRejectedValue(new ApiError(422, { message: 'username taken' }, 'username taken'))

    const wrapper = mount(AdminUsersView)
    await flushPromises()

    const createUserButtons = () => wrapper.findAll('button').filter((b) => b.text().includes('Create User'))
    await createUserButtons()[0]!.trigger('click')

    const [usernameInput, passwordInput] = wrapper.findAll('input')
    await usernameInput!.setValue('alice')
    await passwordInput!.setValue('x')

    const buttons = createUserButtons()
    await buttons[buttons.length - 1]!.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('username taken')
  })

  it('freezes a user with the chosen duration', async () => {
    api.list.mockResolvedValue({ items: [user({ id: 7, username: 'frosty' })] })
    api.freeze.mockResolvedValue(user({ id: 7, username: 'frosty', frozen_until: new Date(Date.now() + 24 * 3600 * 1000).toISOString() }))

    const wrapper = mount(AdminUsersView)
    await flushPromises()

    await wrapper.findAll('button').find((b) => b.text().includes('Freeze'))!.trigger('click')
    await wrapper.get('input[type="number"]').setValue(48)
    await wrapper.findAll('button').find((b) => b.text() === 'Confirm')!.trigger('click')
    await flushPromises()

    expect(api.freeze).toHaveBeenCalledWith(7, { duration_hours: 48 })
    expect(wrapper.text()).toContain('Frozen')
  })

  it('blacklists a user after confirm and updates status', async () => {
    api.list.mockResolvedValue({ items: [user({ id: 8, username: 'baddy' })] })
    api.blacklist.mockResolvedValue(user({ id: 8, username: 'baddy', blacklisted_at: new Date().toISOString() }))
    const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(true)

    const wrapper = mount(AdminUsersView)
    await flushPromises()

    await wrapper.findAll('button').find((b) => b.text().includes('Blacklist'))!.trigger('click')
    await flushPromises()

    expect(api.blacklist).toHaveBeenCalledWith(8)
    expect(wrapper.text()).toContain('Blacklisted')
    confirmSpy.mockRestore()
  })

  it('filters the table by role and status via the dropdowns', async () => {
    api.list.mockResolvedValue({
      items: [
        user({ id: 1, username: 'alice', role: 'admin' }),
        user({ id: 2, username: 'bob', role: 'user' }),
        user({ id: 3, username: 'carl', role: 'user', frozen_until: new Date(Date.now() + 60_000).toISOString() }),
      ],
    })

    const wrapper = mount(AdminUsersView)
    await flushPromises()

    const [roleSelect, statusSelect] = wrapper.findAll('select')
    await roleSelect!.setValue('user')
    expect(wrapper.text()).not.toContain('alice')
    expect(wrapper.text()).toContain('bob')
    expect(wrapper.text()).toContain('carl')

    await statusSelect!.setValue('frozen')
    expect(wrapper.text()).not.toContain('bob')
    expect(wrapper.text()).toContain('carl')
  })

  it('notifies on list failure', async () => {
    const ui = useUiStore()
    const spy = vi.spyOn(ui, 'addNotification')
    api.list.mockRejectedValue(new Error('boom'))

    mount(AdminUsersView)
    await flushPromises()

    expect(spy).toHaveBeenCalledWith({ type: 'error', message: 'Failed to load users' })
  })

  it('unfreezes a frozen user and updates their status to Active', async () => {
    const frozenUser = user({ id: 5, username: 'frosty', frozen_until: new Date(Date.now() + 24 * 3600 * 1000).toISOString() })
    api.list.mockResolvedValue({ items: [frozenUser] })
    api.unfreeze.mockResolvedValue(user({ id: 5, username: 'frosty', frozen_until: null }))

    const wrapper = mount(AdminUsersView)
    await flushPromises()

    await wrapper.findAll('button').find((b) => b.text().includes('Unfreeze'))!.trigger('click')
    await flushPromises()

    expect(api.unfreeze).toHaveBeenCalledWith(5)
    expect(wrapper.text()).toContain('Active')
  })

  it('soft-deletes a user after confirm and removes them from the table', async () => {
    api.list.mockResolvedValue({ items: [user({ id: 9, username: 'victim' })] })
    api.softDelete.mockResolvedValue(undefined)
    const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(true)

    const wrapper = mount(AdminUsersView)
    await flushPromises()

    await wrapper.findAll('button').find((b) => b.text().includes('Delete'))!.trigger('click')
    await flushPromises()

    expect(api.softDelete).toHaveBeenCalledWith(9)
    expect(wrapper.text()).not.toContain('victim')
    confirmSpy.mockRestore()
  })

  it('does NOT delete when confirm() is denied', async () => {
    api.list.mockResolvedValue({ items: [user({ id: 10, username: 'kept' })] })
    const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(false)

    const wrapper = mount(AdminUsersView)
    await flushPromises()

    await wrapper.findAll('button').find((b) => b.text().includes('Delete'))!.trigger('click')
    await flushPromises()

    expect(api.softDelete).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('kept')
    confirmSpy.mockRestore()
  })

  it('shows Deleted label and style for a soft-deleted user', async () => {
    api.list.mockResolvedValue({
      items: [user({ id: 11, username: 'ghost', deleted_at: new Date().toISOString() })],
    })

    const wrapper = mount(AdminUsersView)
    await flushPromises()

    expect(wrapper.text()).toContain('Deleted')
  })

  it('notifies on freeze failure', async () => {
    api.list.mockResolvedValue({ items: [user({ id: 12, username: 'snow' })] })
    api.freeze.mockRejectedValue(new Error('freeze fail'))
    const ui = useUiStore()
    const spy = vi.spyOn(ui, 'addNotification')

    const wrapper = mount(AdminUsersView)
    await flushPromises()

    await wrapper.findAll('button').find((b) => b.text().includes('Freeze'))!.trigger('click')
    await wrapper.get('input[type="number"]').setValue(24)
    await wrapper.findAll('button').find((b) => b.text() === 'Confirm')!.trigger('click')
    await flushPromises()

    expect(spy).toHaveBeenCalledWith({ type: 'error', message: 'Failed to freeze user' })
  })

  it('cancels the freeze input when Cancel is clicked', async () => {
    api.list.mockResolvedValue({ items: [user({ id: 13, username: 'nope' })] })

    const wrapper = mount(AdminUsersView)
    await flushPromises()

    await wrapper.findAll('button').find((b) => b.text().includes('Freeze'))!.trigger('click')
    const cancelBtn = wrapper.findAll('button').find((b) => b.text() === 'Cancel')!
    await cancelBtn.trigger('click')

    expect(wrapper.find('input[type="number"]').exists()).toBe(false)
  })

  it('filters by active status, hiding frozen and blacklisted users', async () => {
    api.list.mockResolvedValue({
      items: [
        user({ id: 1, username: 'active_user', role: 'user' }),
        user({ id: 2, username: 'frozen_user', role: 'user', frozen_until: new Date(Date.now() + 60_000).toISOString() }),
        user({ id: 3, username: 'blacklisted_user', role: 'user', blacklisted_at: new Date().toISOString() }),
      ],
    })

    const wrapper = mount(AdminUsersView)
    await flushPromises()

    const [, statusSelect] = wrapper.findAll('select')
    await statusSelect!.setValue('active')

    expect(wrapper.text()).toContain('active_user')
    expect(wrapper.text()).not.toContain('frozen_user')
    expect(wrapper.text()).not.toContain('blacklisted_user')
  })

  it('does NOT blacklist when confirm() is denied', async () => {
    api.list.mockResolvedValue({ items: [user({ id: 14, username: 'safe' })] })
    const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(false)

    const wrapper = mount(AdminUsersView)
    await flushPromises()

    await wrapper.findAll('button').find((b) => b.text().includes('Blacklist'))!.trigger('click')
    await flushPromises()

    expect(api.blacklist).not.toHaveBeenCalled()
    confirmSpy.mockRestore()
  })
})
