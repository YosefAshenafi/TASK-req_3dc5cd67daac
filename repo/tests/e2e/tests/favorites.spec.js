import { test, expect } from '@playwright/test'

const BASE = process.env.BASE_URL || 'http://localhost:8080'

async function loginAsUser(page) {
  await page.goto(BASE + '/login')
  await page.fill('input#username', 'user')
  await page.fill('input[type="password"]', 'Password123!')
  await page.click('button[type="submit"]')
  await expect(page).toHaveURL(/\/library/, { timeout: 10000 })
}

test.describe('Favorites', () => {
  test('user can navigate to favorites page via nav link', async ({ page }) => {
    await loginAsUser(page)
    await page.click('a[href*="/favorites"]')
    await expect(page).toHaveURL(/\/favorites/)
    await expect(page.locator('h1')).toContainText('My Favorites')
  })

  test('favorites page heading is visible when navigated directly', async ({ page }) => {
    await loginAsUser(page)
    await page.goto(BASE + '/favorites')
    await expect(page.locator('h1')).toContainText('My Favorites')
  })

  test('favorites page renders either list or empty state after load', async ({ page }) => {
    await loginAsUser(page)
    await page.goto(BASE + '/favorites')
    await page.waitForSelector('h1')
    // Wait for skeleton to disappear
    await page.waitForFunction(() => !document.querySelector('.skeleton'), { timeout: 5000 }).catch(() => {})
    const hasCards = await page.locator('.card').count()
    const hasEmptyState = await page.locator('text=No favorites yet').isVisible().catch(() => false)
    expect(hasCards > 0 || hasEmptyState).toBe(true)
  })

  test('remove favorite button has descriptive aria-label', async ({ page }) => {
    await loginAsUser(page)
    await page.goto(BASE + '/favorites')
    await page.waitForFunction(() => !document.querySelector('.skeleton'), { timeout: 5000 }).catch(() => {})
    const removeBtn = page.locator('button[title="Remove favorite"]').first()
    const btnVisible = await removeBtn.isVisible().catch(() => false)
    if (btnVisible) {
      const label = await removeBtn.getAttribute('aria-label')
      expect(label).toMatch(/Remove .+ from favorites/)
    }
  })

  test('clicking remove favorite updates the list', async ({ page }) => {
    await loginAsUser(page)
    await page.goto(BASE + '/favorites')
    await page.waitForFunction(() => !document.querySelector('.skeleton'), { timeout: 5000 }).catch(() => {})

    const removeBtns = page.locator('button[title="Remove favorite"]')
    const count = await removeBtns.count()
    if (count > 0) {
      const cardCountBefore = await page.locator('[data-testid="asset-card"], .card').count()
      await removeBtns.first().click()
      await page.waitForTimeout(800)
      const cardCountAfter = await page.locator('[data-testid="asset-card"], .card').count()
      expect(cardCountAfter).toBeLessThan(cardCountBefore)
    }
  })
})
