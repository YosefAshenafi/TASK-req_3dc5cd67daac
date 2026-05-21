import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { useAuthStore } from '@/stores/auth'

vi.mock('@/api/axios', () => ({
  default: {
    post: vi.fn(),
    get: vi.fn(),
  },
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: vi.fn() }),
  useRoute: () => ({ query: {}, fullPath: '/' }),
}))

import LoginView from '@/views/LoginView.vue'

describe('LoginView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('renders the login form with username input', () => {
    const wrapper = mount(LoginView)
    expect(wrapper.find('input#username').exists()).toBe(true)
    expect(wrapper.find('input[type="text"]').exists()).toBe(true)
    expect(wrapper.find('input[type="password"]').exists()).toBe(true)
    expect(wrapper.find('button[type="submit"]').exists()).toBe(true)
  })

  it('shows validation error when submitted with empty username', async () => {
    const wrapper = mount(LoginView)
    await wrapper.find('form').trigger('submit')
    expect(wrapper.text()).toContain('required')
  })

  it('shows loading state during login', async () => {
    const api = (await import('@/api/axios')).default
    api.post.mockImplementation(() => new Promise(() => {}))

    const wrapper = mount(LoginView)
    await wrapper.find('input#username').setValue('user')
    await wrapper.find('input[type="password"]').setValue('Password123!')
    await wrapper.find('form').trigger('submit')
    await wrapper.vm.$nextTick()

    expect(wrapper.find('button[type="submit"]').attributes('disabled')).toBeDefined()
  })

  it('calls auth.login with username and password', async () => {
    const api = (await import('@/api/axios')).default
    api.post.mockResolvedValueOnce({ data: { user: { id: 1, username: 'user', role: 'user' } } })
    api.get.mockResolvedValueOnce({ data: { user: null } })

    const wrapper = mount(LoginView)
    const auth = useAuthStore()
    const loginSpy = vi.spyOn(auth, 'login').mockResolvedValueOnce({})

    await wrapper.find('input#username').setValue('user')
    await wrapper.find('input[type="password"]').setValue('Password123!')
    await wrapper.find('form').trigger('submit')
    await wrapper.vm.$nextTick()

    expect(loginSpy).toHaveBeenCalledWith('user', 'Password123!')
  })
})
