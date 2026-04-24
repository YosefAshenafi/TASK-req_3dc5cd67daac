import { defineComponent, nextTick, ref } from 'vue'
import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useUnsavedGuard } from '@/composables/useUnsavedGuard'

let capturedRouteLeave: (() => boolean) | undefined

vi.mock('vue-router', () => ({
  onBeforeRouteLeave: (cb: () => boolean) => {
    capturedRouteLeave = cb
  },
}))

describe('useUnsavedGuard', () => {
  beforeEach(() => {
    capturedRouteLeave = undefined
    vi.restoreAllMocks()
  })

  it('registers and unregisters beforeunload listener', async () => {
    const addSpy = vi.spyOn(window, 'addEventListener')
    const removeSpy = vi.spyOn(window, 'removeEventListener')

    const Host = defineComponent({
      setup() {
        const dirty = ref(false)
        useUnsavedGuard(() => dirty.value)
        return { dirty }
      },
      template: '<div />',
    })

    const wrapper = mount(Host)
    expect(addSpy).toHaveBeenCalledWith('beforeunload', expect.any(Function))

    wrapper.unmount()
    expect(removeSpy).toHaveBeenCalledWith('beforeunload', expect.any(Function))
  })

  it('blocks route leave and confirms when dirty', async () => {
    const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(true)
    const Host = defineComponent({
      setup() {
        const dirty = ref(true)
        useUnsavedGuard(() => dirty.value, 'Unsaved!')
        return { dirty }
      },
      template: '<div />',
    })

    const wrapper = mount(Host)
    await nextTick()

    expect(capturedRouteLeave).toBeTypeOf('function')
    expect(capturedRouteLeave!()).toBe(true)
    expect(confirmSpy).toHaveBeenCalledWith('Unsaved!')

    wrapper.vm.dirty = false
    await nextTick()
    expect(capturedRouteLeave!()).toBe(true)
  })

  it('sets returnValue on beforeunload when dirty', async () => {
    const addSpy = vi.spyOn(window, 'addEventListener')
    const Host = defineComponent({
      setup() {
        const dirty = ref(true)
        useUnsavedGuard(() => dirty.value, 'Unsaved changes')
        return { dirty }
      },
      template: '<div />',
    })

    mount(Host)
    const beforeUnloadHandler = addSpy.mock.calls.find((call) => call[0] === 'beforeunload')?.[1]
    expect(beforeUnloadHandler).toBeTypeOf('function')

    const event = new Event('beforeunload') as BeforeUnloadEvent
    Object.defineProperty(event, 'returnValue', { writable: true, value: '' })
    const preventDefault = vi.fn()
    event.preventDefault = preventDefault

    ;(beforeUnloadHandler as EventListener)(event)

    expect(preventDefault).toHaveBeenCalled()
    expect((event as BeforeUnloadEvent).returnValue).toBe('Unsaved changes')
  })

  it('does nothing on beforeunload when clean', () => {
    const addSpy = vi.spyOn(window, 'addEventListener')
    const Host = defineComponent({
      setup() {
        useUnsavedGuard(() => false, 'No-op')
        return {}
      },
      template: '<div />',
    })

    mount(Host)
    const beforeUnloadHandler = addSpy.mock.calls.find((call) => call[0] === 'beforeunload')?.[1]
    expect(beforeUnloadHandler).toBeTypeOf('function')

    const event = new Event('beforeunload') as BeforeUnloadEvent
    Object.defineProperty(event, 'returnValue', { writable: true, value: '' })
    const preventDefault = vi.fn()
    event.preventDefault = preventDefault

    ;(beforeUnloadHandler as EventListener)(event)

    expect(preventDefault).not.toHaveBeenCalled()
    expect((event as BeforeUnloadEvent).returnValue).toBe('')
  })
})
