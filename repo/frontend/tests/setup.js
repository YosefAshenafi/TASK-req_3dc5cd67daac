import { vi } from 'vitest'
import { config } from '@vue/test-utils'

config.global.stubs = {
  RouterLink: { template: '<a><slot /></a>' },
  RouterView: { template: '<div />' },
}

Object.defineProperty(window, 'location', {
  value: { pathname: '/library', href: 'http://localhost/library' },
  writable: true,
})
