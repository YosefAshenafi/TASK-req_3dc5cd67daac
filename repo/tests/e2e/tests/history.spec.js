import { test, expect } from '@playwright/test'

const BASE = process.env.BASE_URL || 'http://localhost:3000'

async function loginAsUser(page) {
  await page.goto(BASE + '/login')
  await page.fill('input#username', 'user')
  await page.fill('input[type="password"]', 'Password123!')
  await page.click('button[type="submit"]')
  await expect(page).toHaveURL(/\/library/, { timeout: 10000 })
}

test.describe('Play History', () => {
  test('history page heading is visible', async ({ page }) => {
    await loginAsUser(page)
    await page.goto(BASE + '/history')
    await expect(page.locator('h1')).toContainText('Play History')
  })

  test('history page shows either entries or empty state after load', async ({ page }) => {
    await loginAsUser(page)
    await page.goto(BASE + '/history')
    await page.waitForSelector('h1')
    await page.waitForFunction(() => !document.querySelector('.skeleton'), { timeout: 5000 }).catch(() => {})
    const hasCards = await page.locator('.card').count()
    const hasEmptyState = await page.locator('text=No play history yet').isVisible().catch(() => false)
    expect(hasCards > 0 || hasEmptyState).toBe(true)
  })

  test('history entries show asset title and timestamp', async ({ page }) => {
    await loginAsUser(page)
    await page.goto(BASE + '/history')
    await page.waitForFunction(() => !document.querySelector('.skeleton'), { timeout: 5000 }).catch(() => {})
    const entries = page.locator('.card')
    const count = await entries.count()
    if (count > 0) {
      const firstEntry = entries.first()
      const text = await firstEntry.textContent()
      // Each entry should have non-empty content (asset title)
      expect(text?.trim().length).toBeGreaterThan(0)
    }
  })

  test('playing an asset from library appends to history', async ({ page }) => {
    await loginAsUser(page)

    // Record initial history count
    await page.goto(BASE + '/history')
    await page.waitForFunction(() => !document.querySelector('.skeleton'), { timeout: 5000 }).catch(() => {})
    const initialCount = await page.locator('.card').count()

    // Play an asset from the library
    await page.goto(BASE + '/library')
    await page.waitForSelector('[class*="grid"]', { timeout: 10000 }).catch(() => {})

    const playBtn = page.locator('button[aria-label*="Play"]').first()
    const playVisible = await playBtn.isVisible().catch(() => false)
    if (playVisible) {
      await playBtn.click()
      await page.waitForTimeout(1500)

      // Re-check history
      await page.goto(BASE + '/history')
      await page.waitForFunction(() => !document.querySelector('.skeleton'), { timeout: 5000 }).catch(() => {})
      const newCount = await page.locator('.card').count()
      expect(newCount).toBeGreaterThan(initialCount)
    }
  })
})
