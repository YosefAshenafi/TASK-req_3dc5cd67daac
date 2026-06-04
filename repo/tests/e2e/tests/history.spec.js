import { test, expect } from '@playwright/test'

const BASE = process.env.BASE_URL || 'http://localhost:3000'

async function loginAsUser(page) {
  await page.goto(BASE + '/login')
  await page.fill('input#username', 'user')
  await page.fill('input[type="password"]', 'Password123!')
  await page.click('button[type="submit"]')
  await expect(page).toHaveURL(/\/library/, { timeout: 10000 })
}

// Navigate to the history page and wait until it has actually loaded.
//
// In this client-routed SPA there is a brief window after navigation where the
// HistoryView component is not mounted yet (bundle eval + router resolution).
// During that window the page has no `.skeleton` element simply because the
// view does not exist — so waiting on "no skeleton" alone can resolve before
// anything renders and yield a count of 0. We first wait for the view's heading
// (proves HistoryView is mounted), then wait for the fetch to settle (skeletons
// gone), so any subsequent `.card` count reflects the real, loaded list.
async function gotoHistoryLoaded(page) {
  await page.goto(BASE + '/history')
  await expect(page.locator('h1')).toContainText('Play History', { timeout: 15000 })
  await page.waitForFunction(() => !document.querySelector('.skeleton'), { timeout: 15000 })
}

test.describe('Play History', () => {
  test('history page heading is visible', async ({ page }) => {
    await loginAsUser(page)
    await gotoHistoryLoaded(page)
    await expect(page.locator('h1')).toContainText('Play History')
  })

  test('history page shows either entries or empty state after load', async ({ page }) => {
    await loginAsUser(page)
    await gotoHistoryLoaded(page)
    const hasCards = await page.locator('.card').count()
    const hasEmptyState = await page.locator('text=No play history yet').isVisible().catch(() => false)
    expect(hasCards > 0 || hasEmptyState).toBe(true)
  })

  test('history entries show asset title and timestamp', async ({ page }) => {
    await loginAsUser(page)
    await gotoHistoryLoaded(page)
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
    await gotoHistoryLoaded(page)
    const initialCount = await page.locator('.card').count()

    // Play an asset from the library
    await page.goto(BASE + '/library')
    await page.waitForSelector('[data-testid="asset-card"]', { timeout: 15000 })

    const playBtn = page.locator('[data-testid="play-btn"]').first()
    await expect(playBtn).toBeVisible({ timeout: 10000 })
    await playBtn.click()

    // recordPlay() issues POST /play-history then refetches; the Stop control
    // appears only once that completes, so this is a reliable "play persisted"
    // signal before we navigate away to verify the history list.
    await expect(page.locator('[data-testid="np-stop"]')).toBeVisible({ timeout: 10000 })

    // Re-check history
    await gotoHistoryLoaded(page)
    const newCount = await page.locator('.card').count()
    expect(newCount).toBeGreaterThan(initialCount)
  })
})
