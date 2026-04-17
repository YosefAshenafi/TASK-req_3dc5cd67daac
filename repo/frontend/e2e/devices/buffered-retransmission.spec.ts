// NOTE: These tests use mocked Playwright route handlers for all API calls
// (/api/devices, /api/devices/{id}, /api/devices/{id}/events).
// They are component-level browser tests, NOT full end-to-end tests against
// the live backend. The mocked route paths match the correct backend routes.
import { test, expect } from '@playwright/test'

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:5173'

async function loginAsTech(page: any) {
  await page.goto(`${BASE_URL}/login`)
  await page.getByLabel('Username').fill('tech1')
  await page.getByRole('textbox', { name: 'Password' }).fill('password')
  await page.getByRole('button', { name: 'Sign In' }).click()
  await page.waitForURL('**/devices', { timeout: 10000 })
}

test.describe('Buffered retransmission', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsTech(page)
  })

  test('devices page is accessible to technicians', async ({ page }) => {
    await page.goto(`${BASE_URL}/devices`)
    await expect(page.getByRole('heading', { name: 'Devices' })).toBeVisible()
  })

  test('device detail shows events tab by default', async ({ page }) => {
    await page.route('**/api/devices', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify([
          { id: 'gw-001', kind: 'gateway', last_sequence_no: 50, last_seen_at: new Date().toISOString() },
        ]),
      })
    })

    await page.route('**/api/devices/gw-001', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ id: 'gw-001', kind: 'gateway', last_sequence_no: 50, last_seen_at: new Date().toISOString() }),
      })
    })

    await page.route('**/api/devices/gw-001/events**', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ items: [], next_cursor: null }),
      })
    })

    await page.goto(`${BASE_URL}/devices`)
    await page.waitForTimeout(500)
    await page.getByText('gw-001').click()
    await page.waitForURL('**/devices/gw-001')

    // Events tab should be active by default
    const eventsTab = page.getByRole('button', { name: 'Events' })
    await expect(eventsTab).toBeVisible()
    await expect(eventsTab).toHaveClass(/border-blue-600/)
  })

  test('device detail shows replay and audit tabs', async ({ page }) => {
    await page.route('**/api/devices', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify([
          { id: 'gw-002', kind: 'gateway', last_sequence_no: 200, last_seen_at: new Date().toISOString() },
        ]),
      })
    })

    await page.route('**/api/devices/gw-002', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ id: 'gw-002', kind: 'gateway', last_sequence_no: 200, last_seen_at: new Date().toISOString() }),
      })
    })

    await page.route('**/api/devices/gw-002/events**', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ items: [], next_cursor: null }),
      })
    })

    await page.goto(`${BASE_URL}/devices`)
    await page.waitForTimeout(500)
    await page.getByText('gw-002').click()
    await page.waitForURL('**/devices/gw-002')

    await expect(page.getByRole('button', { name: 'Replay' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Audit Trail' })).toBeVisible()
  })
})
