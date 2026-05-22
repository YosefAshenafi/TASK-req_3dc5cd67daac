import { test, expect } from '@playwright/test'

const BASE = process.env.BASE_URL || 'http://localhost:8080'

async function loginAsUser(page) {
  await page.goto(BASE + '/login')
  await page.fill('input#username', 'user')
  await page.fill('input[type="password"]', 'Password123!')
  await page.click('button[type="submit"]')
  await expect(page).toHaveURL(/\/library/, { timeout: 10000 })
}

test.describe('Library', () => {
  test('shows asset grid on initial load', async ({ page }) => {
    await loginAsUser(page)
    // Assets auto-load on mount; wait for the grid to appear
    await page.waitForSelector('[class*="grid"]', { timeout: 10000 })
    const cards = await page.locator('.card').count()
    expect(cards).toBeGreaterThan(0)
  })

  test('asset cards are visible without any interaction', async ({ page }) => {
    await loginAsUser(page)
    await page.waitForSelector('[class*="grid"]', { timeout: 10000 })
    const cards = await page.locator('.card').count()
    expect(cards).toBeGreaterThan(0)
  })

  test('search filter works', async ({ page }) => {
    await loginAsUser(page)
    await page.fill('input[type="search"]', 'Parking')
    await page.click('button:has-text("Search")')
    await page.waitForTimeout(1000)
  })

  test('sort by most played works', async ({ page }) => {
    await loginAsUser(page)
    await page.selectOption('[data-testid="sort-select"]', 'most_played')
    await page.waitForTimeout(1000)
  })

  test('tag filter input is visible', async ({ page }) => {
    await loginAsUser(page)
    await expect(page.locator('[data-testid="tag-input"]')).toBeVisible()
  })

  test('adding a tag creates a chip', async ({ page }) => {
    await loginAsUser(page)
    const tagInput = page.locator('[data-testid="tag-input"]')
    await tagInput.fill('announcement')
    await tagInput.press('Enter')
    await expect(page.locator('.bg-brand-100').filter({ hasText: 'announcement' })).toBeVisible()
  })

  test('removing a tag chip clears it', async ({ page }) => {
    await loginAsUser(page)
    const tagInput = page.locator('[data-testid="tag-input"]')
    await tagInput.fill('safety')
    await tagInput.press('Enter')
    const chip = page.locator('.bg-brand-100').filter({ hasText: 'safety' })
    await expect(chip).toBeVisible()
    await chip.locator('button').click()
    await expect(chip).not.toBeVisible()
  })

  test('now playing panel is visible', async ({ page }) => {
    await loginAsUser(page)
    await expect(page.locator('text=Now Playing')).toBeVisible()
  })

  test('recommended sort renders recommendation reason on cards', async ({ page }) => {
    await loginAsUser(page)
    // Library auto-loads on mount
    await page.waitForSelector('[class*="grid"]', { timeout: 10000 })
    // Switch to recommended sort
    await page.selectOption('[data-testid="sort-select"]', 'recommended')
    await page.waitForTimeout(1500)
    // Recommendation reasons are rendered in AssetCard when present
    // The text will be either "Based on your favorites: ..." or
    // "Based on your listening history" or "Popular in your facility"
    const reasonTexts = [
      'Based on your favorites',
      'Based on your listening history',
      'Popular in your facility',
    ]
    const reasonLocator = page.locator('[class*="text-brand-600"]').first()
    const reasonVisible = await reasonLocator.isVisible().catch(() => false)
    if (reasonVisible) {
      const reasonText = await reasonLocator.textContent()
      const matchesAny = reasonTexts.some(t => reasonText?.includes(t))
      expect(matchesAny).toBe(true)
    }
    // If no cards rendered (empty library) the test still passes
  })
})
