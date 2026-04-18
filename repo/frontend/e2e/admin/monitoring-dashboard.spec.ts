import { test, expect } from '@playwright/test'

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:5173'

async function loginAsAdmin(page: any) {
  await page.goto(`${BASE_URL}/login`)
  await page.getByLabel('Username').fill('admin')
  await page.getByRole('textbox', { name: 'Password' }).fill('password')
  await page.getByRole('button', { name: 'Sign In' }).click()
  await page.waitForURL('**/admin', { timeout: 10000 })
}

test.describe('Monitoring dashboard', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page)
  })

  test('monitoring page displays heading', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/monitoring`)
    await expect(page.getByRole('heading', { name: 'Monitoring' })).toBeVisible()
  })

  test('displays all monitoring panels', async ({ page }) => {
    // Mock the monitoring status API
    await page.route('**/api/monitoring/status', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          api: { p95_ms_5m: 42.5, error_rate_5m: 0.001 },
          queues: { transcoding: 3, thumbnails: 0 },
          storage: { media_volume_free_bytes: 10737418240, media_volume_used_pct: 45.2 },
          devices: { online: 5, offline: 2, dedup_rate_1h: 0.12 },
          feature_flags: {
            recommended_enabled: { enabled: true, last_transition_at: '2024-01-01T00:00:00Z', reason: null },
          },
        }),
      })
    })

    await page.goto(`${BASE_URL}/admin/monitoring`)
    await page.waitForTimeout(1000)

    await expect(page.getByRole('heading', { name: 'API Health' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Devices' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Storage' })).toBeVisible()
  })

  test('refresh button is present and clickable', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/monitoring`)
    const refreshBtn = page.getByRole('button', { name: 'Refresh' })
    await expect(refreshBtn).toBeVisible()
    await refreshBtn.click()
    await page.waitForTimeout(500)
    await expect(page.getByRole('heading', { name: 'Monitoring' })).toBeVisible()
  })

  test('feature flags section shows reset buttons', async ({ page }) => {
    await page.route('**/api/monitoring/status', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          api: { p95_ms_5m: 100, error_rate_5m: 0 },
          queues: {},
          storage: { media_volume_free_bytes: 1000000000, media_volume_used_pct: 20 },
          devices: { online: 1, offline: 0, dedup_rate_1h: 0 },
          feature_flags: {
            recommended_enabled: { enabled: true, last_transition_at: null, reason: null },
          },
        }),
      })
    })

    await page.goto(`${BASE_URL}/admin/monitoring`)
    await page.waitForTimeout(1000)

    await expect(page.getByRole('heading', { name: 'Feature Flags' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Reset' }).first()).toBeVisible()
  })
})
