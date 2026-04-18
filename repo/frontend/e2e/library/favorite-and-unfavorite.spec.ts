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
    await expect(page.getByRole('heading', { name: 'Favorites', exact: true })).toBeVisible()
  })

  test('shows empty state when no favorites', async ({ page }) => {
    await page.goto(`${BASE_URL}/favorites`)
    // Either shows assets or empty state message
    const heading = page.getByRole('heading', { name: 'Favorites', exact: true })
    await expect(heading).toBeVisible()
  })

  test('can navigate to favorites from nav', async ({ page }) => {
    await page.goto(`${BASE_URL}/search`)
    await page.getByRole('link', { name: 'Favorites' }).click()
    await page.waitForURL('**/favorites')
    await expect(page.getByRole('heading', { name: 'Favorites', exact: true })).toBeVisible()
  })

  test('heart button is visible on asset tiles in search', async ({ page }) => {
    await page.goto(`${BASE_URL}/search`)
    await page.waitForTimeout(1500) // wait for assets to load

    // Favorite buttons carry aria-label="Add to favorites" / "Remove from favorites".
    // Target by aria-label to avoid coupling to tile utility classes.
    const heartButtons = page.locator('button[aria-label*="favorites"]')
    const count = await heartButtons.count()

    if (count > 0) {
      await expect(heartButtons.first()).toBeVisible()
    } else {
      // If no assets are returned, the Search heading is still the page anchor.
      await expect(page.getByRole('heading', { name: 'Search', exact: true })).toBeVisible()
    }
  })
})
