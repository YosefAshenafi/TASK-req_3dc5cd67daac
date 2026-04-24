import { describe, expect, it } from 'vitest'
import { getRoleHome } from '@/router/roleHome'

describe('router getRoleHome', () => {
  it('returns admin home', () => {
    expect(getRoleHome('admin')).toBe('/admin')
  })

  it('returns technician home', () => {
    expect(getRoleHome('technician')).toBe('/devices')
  })

  it('returns default home for unknown role', () => {
    expect(getRoleHome('user')).toBe('/search')
    expect(getRoleHome('unknown')).toBe('/search')
  })
})
