import { defineStore } from 'pinia'
import { ref } from 'vue'

export interface Notification {
  id: string
  type: 'success' | 'error' | 'warning' | 'info'
  message: string
  timeout?: number
}

export const useUiStore = defineStore('ui', () => {
  const notifications = ref<Notification[]>([])
  const offline = ref(false)

  function addNotification(
    notification: Omit<Notification, 'id'>,
  ): string {
    const id = `notif-${Date.now()}-${Math.random().toString(36).slice(2)}`
    const notif: Notification = { id, ...notification }
    notifications.value.push(notif)

    if (notification.timeout !== 0) {
      setTimeout(() => {
        removeNotification(id)
      }, notification.timeout ?? 4000)
    }

    return id
  }

  function removeNotification(id: string): void {
    const idx = notifications.value.findIndex((n) => n.id === id)
    if (idx !== -1) notifications.value.splice(idx, 1)
  }

  function setOffline(value: boolean): void {
    offline.value = value
  }

  return {
    notifications,
    offline,
    addNotification,
    removeNotification,
    setOffline,
  }
})
