import { onBeforeUnmount, onMounted } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'

export function useUnsavedGuard(
  isDirty: () => boolean,
  message = 'You have unsaved changes. Leave without saving?',
) {
  const handleBeforeUnload = (e: BeforeUnloadEvent) => {
    if (!isDirty()) return
    e.preventDefault()
    e.returnValue = message
  }

  onMounted(() => window.addEventListener('beforeunload', handleBeforeUnload))
  onBeforeUnmount(() => window.removeEventListener('beforeunload', handleBeforeUnload))

  onBeforeRouteLeave(() => {
    if (!isDirty()) return true
    return window.confirm(message)
  })
}
