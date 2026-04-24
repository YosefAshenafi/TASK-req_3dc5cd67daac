import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useUiStore } from '@/stores/ui'

describe('UI Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.useFakeTimers()
  })

  it('addNotification adds notification and auto-removes by default timeout', () => {
    const store = useUiStore()

    const id = store.addNotification({ type: 'success', message: 'Saved' })
    expect(store.notifications).toHaveLength(1)
    expect(store.notifications[0]?.id).toBe(id)

    vi.advanceTimersByTime(3999)
    expect(store.notifications).toHaveLength(1)

    vi.advanceTimersByTime(1)
    expect(store.notifications).toHaveLength(0)
  })

  it('addNotification with timeout=0 does not auto-remove', () => {
    const store = useUiStore()

    store.addNotification({ type: 'warning', message: 'Persistent', timeout: 0 })

    vi.advanceTimersByTime(10000)
    expect(store.notifications).toHaveLength(1)
  })

  it('removeNotification removes only matching id', () => {
    const store = useUiStore()

    const id1 = store.addNotification({ type: 'info', message: 'A', timeout: 0 })
    const id2 = store.addNotification({ type: 'error', message: 'B', timeout: 0 })

    store.removeNotification(id1)

    expect(store.notifications).toHaveLength(1)
    expect(store.notifications[0]?.id).toBe(id2)
  })

  it('removeNotification is a no-op when id does not exist', () => {
    const store = useUiStore()

    store.addNotification({ type: 'info', message: 'A', timeout: 0 })
    store.removeNotification('missing-id')

    expect(store.notifications).toHaveLength(1)
  })

  it('setOffline toggles offline state', () => {
    const store = useUiStore()

    store.setOffline(true)
    expect(store.offline).toBe(true)

    store.setOffline(false)
    expect(store.offline).toBe(false)
  })
})
