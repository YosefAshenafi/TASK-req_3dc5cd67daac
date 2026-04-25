import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import LoginView from '@/views/LoginView.vue'
import { useAuthStore } from '@/stores/auth'
import { ApiError, FrozenError } from '@/services/api'

// Keep router + route mocks in module scope so each test can reconfigure them
// without re-running vi.mock. `push` is the observable side-effect we assert.
const push = vi.fn()
const routeRef: { query: Record<string, string> } = { query: {} }

vi.mock('vue-router', () => ({
  useRouter: () => ({ push }),
  useRoute: () => routeRef,
}))

describe('LoginView.vue', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    push.mockReset()
    routeRef.query = {}
  })

  it('submits credentials and redirects admin to /admin by default', async () => {
    const auth = useAuthStore()
    // Emulate a successful login — the store action assigns auth.user itself.
    vi.spyOn(auth, 'login').mockImplementation(async () => {
      auth.user = { id: 1, username: 'admin', role: 'admin' }
    })

    const wrapper = mount(LoginView)
    await wrapper.get('#username').setValue('admin')
    await wrapper.get('#password').setValue('secret')
    await wrapper.get('form').trigger('submit.prevent')
    await flushPromises()

    expect(auth.login).toHaveBeenCalledWith('admin', 'secret')
    expect(push).toHaveBeenCalledWith('/admin')
  })

  it('redirects technician home after login', async () => {
    const auth = useAuthStore()
    vi.spyOn(auth, 'login').mockImplementation(async () => {
      auth.user = { id: 2, username: 'tech', role: 'technician' }
    })

    const wrapper = mount(LoginView)
    await wrapper.get('#username').setValue('tech')
    await wrapper.get('#password').setValue('secret')
    await wrapper.get('form').trigger('submit.prevent')
    await flushPromises()

    expect(push).toHaveBeenCalledWith('/devices')
  })

  it('honors the ?redirect= query param over the role home', async () => {
    routeRef.query = { redirect: '/playlists/42' }
    const auth = useAuthStore()
    vi.spyOn(auth, 'login').mockImplementation(async () => {
      auth.user = { id: 3, username: 'u', role: 'user' }
    })

    const wrapper = mount(LoginView)
    await wrapper.get('#username').setValue('u')
    await wrapper.get('#password').setValue('p')
    await wrapper.get('form').trigger('submit.prevent')
    await flushPromises()

    expect(push).toHaveBeenCalledWith('/playlists/42')
  })

  it('renders the ApiError message on 401-style invalid credentials', async () => {
    const auth = useAuthStore()
    vi.spyOn(auth, 'login').mockRejectedValue(
      new ApiError(401, { message: 'Invalid username or password' }, 'Invalid username or password'),
    )

    const wrapper = mount(LoginView)
    await wrapper.get('#username').setValue('bad')
    await wrapper.get('#password').setValue('bad')
    await wrapper.get('form').trigger('submit.prevent')
    await flushPromises()

    const alert = wrapper.find('[role="alert"]')
    expect(alert.exists()).toBe(true)
    expect(alert.text()).toContain('Invalid username or password')
    expect(push).not.toHaveBeenCalled()
  })

  it('renders the frozen-account banner when login throws FrozenError', async () => {
    const auth = useAuthStore()
    const frozenUntil = new Date(Date.now() + 60_000).toISOString()
    vi.spyOn(auth, 'login').mockRejectedValue(
      new FrozenError({ frozen_until: frozenUntil }),
    )

    const wrapper = mount(LoginView)
    await wrapper.get('#username').setValue('frozen')
    await wrapper.get('#password').setValue('x')
    await wrapper.get('form').trigger('submit.prevent')
    await flushPromises()

    expect(wrapper.text()).toContain('Account frozen')
    // Submit button is disabled while the frozen countdown is active.
    expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeDefined()
  })

  it('shows the rate-limit banner when login throws 429', async () => {
    const auth = useAuthStore()
    vi.spyOn(auth, 'login').mockRejectedValue(
      new ApiError(429, { retry_after: 30 }, 'Too many attempts'),
    )

    const wrapper = mount(LoginView)
    await wrapper.get('#username').setValue('user')
    await wrapper.get('#password').setValue('x')
    await wrapper.get('form').trigger('submit.prevent')
    await flushPromises()

    expect(wrapper.text()).toContain('Too many attempts')
    expect(wrapper.text()).toMatch(/Try again in 30s/)
  })

  it('toggles password visibility via the show/hide button', async () => {
    const wrapper = mount(LoginView)

    const pw = wrapper.get<HTMLInputElement>('#password')
    expect(pw.attributes('type')).toBe('password')

    await wrapper.get('button[aria-label="Show password"]').trigger('click')
    expect(wrapper.get<HTMLInputElement>('#password').attributes('type')).toBe('text')

    await wrapper.get('button[aria-label="Hide password"]').trigger('click')
    expect(wrapper.get<HTMLInputElement>('#password').attributes('type')).toBe('password')
  })

  it('shows the generic fallback message when an unexpected non-ApiError is thrown', async () => {
    // The catch has a fallback branch for `!(ApiError instance)` that only
    // triggers when the store throws something that isn't one of the known
    // subclasses. This locks that fallback wording.
    const auth = useAuthStore()
    vi.spyOn(auth, 'login').mockRejectedValue(new Error('network hiccup'))

    const wrapper = mount(LoginView)
    await wrapper.get('#username').setValue('u')
    await wrapper.get('#password').setValue('p')
    await wrapper.get('form').trigger('submit.prevent')
    await flushPromises()

    const alert = wrapper.find('[role="alert"]')
    expect(alert.exists()).toBe(true)
    expect(alert.text()).toContain('An unexpected error occurred')
  })

  it('clears the countdown interval on unmount', async () => {
    vi.useFakeTimers()
    const auth = useAuthStore()
    vi.spyOn(auth, 'login').mockRejectedValue(
      new ApiError(429, { retry_after: 30 }, 'Too many attempts'),
    )

    const wrapper = mount(LoginView)
    await wrapper.get('#username').setValue('u')
    await wrapper.get('#password').setValue('x')
    await wrapper.get('form').trigger('submit.prevent')
    await flushPromises()

    // Unmounting while the interval is active covers the onUnmounted if(countdownInterval) branch.
    wrapper.unmount()
    vi.useRealTimers()
  })

  it('frozen-account countdown ticks and clears frozenUntil when it reaches zero', async () => {
    vi.useFakeTimers()
    const auth = useAuthStore()
    const frozenUntil = new Date(Date.now() + 2000).toISOString()
    vi.spyOn(auth, 'login').mockRejectedValue(new FrozenError({ frozen_until: frozenUntil }))

    const wrapper = mount(LoginView)
    await wrapper.get('#username').setValue('frozen')
    await wrapper.get('#password').setValue('x')
    await wrapper.get('form').trigger('submit.prevent')
    await flushPromises()

    expect(wrapper.text()).toContain('Account frozen')

    // Advance past the frozen_until timestamp so the countdown clears it.
    vi.advanceTimersByTime(3000)
    await flushPromises()

    // frozenUntil is cleared; the frozen banner is gone and form re-enabled.
    expect(wrapper.find('div.bg-orange-50').exists()).toBe(false)
    vi.useRealTimers()
  })

  it('counts down the rate-limit retry every second and re-enables the form at 0', async () => {
    vi.useFakeTimers()
    const auth = useAuthStore()
    vi.spyOn(auth, 'login').mockRejectedValue(
      new ApiError(429, { retry_after: 2 }, 'Too many attempts'),
    )

    const wrapper = mount(LoginView)
    await wrapper.get('#username').setValue('u')
    await wrapper.get('#password').setValue('x')
    await wrapper.get('form').trigger('submit.prevent')
    // Use real timers briefly so the rejected Promise continuation can flush,
    // then switch back to fake timers for the interval tick.
    await flushPromises()

    expect(wrapper.text()).toMatch(/Try again in 2s/)

    vi.advanceTimersByTime(1000)
    await flushPromises()
    expect(wrapper.text()).toMatch(/Try again in 1s/)

    vi.advanceTimersByTime(1000)
    await flushPromises()
    // At 0, the banner message changes — countdown cleared.
    expect(wrapper.text()).not.toMatch(/Try again in \ds/)
    vi.useRealTimers()
  })
})
