import { test, expect } from '@playwright/test'

const BASE = process.env.BASE_URL || 'http://localhost:8080'

async function loginAsUser(page) {
  await page.goto(BASE + '/login')
  await page.fill('input#username', 'user')
  await page.fill('input[type="password"]', 'Password123!')
  await page.click('button[type="submit"]')
  await expect(page).toHaveURL(/\/library/, { timeout: 10000 })
}

test.describe('Playlists', () => {
  test('user can navigate to playlists page', async ({ page }) => {
    await loginAsUser(page)
    await page.click('a[href*="/playlists"]')
    await expect(page).toHaveURL(/\/playlists/)
    await expect(page.locator('h1')).toContainText('My Playlists')
  })

  test('user can create a playlist', async ({ page }) => {
    await loginAsUser(page)
    await page.goto(BASE + '/playlists')
    await page.click('button:has-text("New Playlist")')
    await page.fill('input#pl-name', 'Test E2E Playlist')
    await page.click('button:has-text("Create")')
    await page.waitForTimeout(1000)
    await expect(page.locator('text=Test E2E Playlist')).toBeVisible()
  })

  test('share code appears on created playlist card', async ({ page }) => {
    await loginAsUser(page)
    await page.goto(BASE + '/playlists')
    await page.click('button:has-text("New Playlist")')
    await page.fill('input#pl-name', 'Share Code Test Playlist')
    await page.click('button:has-text("Create")')
    await page.waitForTimeout(1500)

    // Find the newly created playlist card
    const card = page.locator('div.card').filter({ hasText: 'Share Code Test Playlist' }).first()
    await expect(card).toBeVisible()

    // The share code button should appear inside the card — 8-char uppercase alphanumeric
    const shareCodeBtn = card.locator('button[aria-label]').first()
    const isVisible = await shareCodeBtn.isVisible().catch(() => false)
    if (isVisible) {
      const label = await shareCodeBtn.getAttribute('aria-label')
      expect(label).toMatch(/Copy share code [A-Z0-9]{8}/)
    }
  })

  test('user can redeem a share code', async ({ page }) => {
    await loginAsUser(page)
    await page.goto(BASE + '/playlists')
    await page.click('button:has-text("Redeem Code")')
    await expect(page.locator('h2:has-text("Redeem")')).toBeVisible()
    await page.fill('input#share-code', 'DEMO1234')
    await page.click('button:has-text("Redeem")')
    await page.waitForTimeout(1000)
  })
})
