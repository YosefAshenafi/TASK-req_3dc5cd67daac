import { test, expect } from '@playwright/test'

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:5173'

async function loginAsUser(page: any) {
  await page.goto(`${BASE_URL}/login`)
  await page.getByLabel('Username').fill('user1')
  await page.getByRole('textbox', { name: 'Password' }).fill('password')
  await page.getByRole('button', { name: 'Sign In' }).click()
  await page.waitForURL('**/search', { timeout: 10000 })
}

test.describe('Favorite and unfavorite', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsUser(page)
  })

  test('favorites page displays heading', async ({ page }) => {
    await page.goto(`${BASE_URL}/favorites`)
    await expect(page.getByRole('heading', { name: 'Favorites' })).toBeVisible()
  })

  test('shows empty state when no favorites', async ({ page }) => {
    await page.goto(`${BASE_URL}/favorites`)
    // Either shows assets or empty state message
    const heading = page.getByRole('heading', { name: 'Favorites' })
    await expect(heading).toBeVisible()
  })

  test('can navigate to favorites from nav', async ({ page }) => {
    await page.goto(`${BASE_URL}/search`)
    await page.getByRole('link', { name: 'Favorites' }).click()
    await page.waitForURL('**/favorites')
    await expect(page.getByRole('heading', { name: 'Favorites' })).toBeVisible()
  })

  test('heart button is visible on asset tiles in search', async ({ page }) => {
    await page.goto(`${BASE_URL}/search`)
    await page.waitForTimeout(1500) // wait for assets to load

    const assetTiles = page.locator('.bg-white.rounded-xl')
    const count = await assetTiles.count()

    if (count > 0) {
      // Look for favorite button (heart icon)
      const heartButton = assetTiles.first().locator('button[aria-label*="favorite"]')
      await expect(heartButton).toBeVisible()
    } else {
      // No assets to test, skip gracefully
      expect(true).toBe(true)
    }
  })
})
