import { test, expect } from '@playwright/test'

const BASE = process.env.BASE_URL || 'http://localhost:3000'

async function login(page, username) {
  await page.goto(BASE + '/login')
  await page.fill('input#username', username)
  await page.fill('input[type="password"]', 'Password123!')
  await page.click('button[type="submit"]')
  await expect(page).toHaveURL(/\/library/, { timeout: 10000 })
}

async function waitForCards(page) {
  await page.waitForSelector('[data-testid="asset-card"]', { timeout: 10000 })
}

test.describe('Asset detail / favorite / edit', () => {
  test('clicking a card opens the detail drawer showing full fields', async ({ page }) => {
    await login(page, 'user')
    await waitForCards(page)

    await page.locator('[data-testid="asset-card"]').first().click()

    // Drawer opens with the canonical detail (title + play action + more than the name)
    await expect(page.locator('[data-testid="detail-title"]')).toBeVisible({ timeout: 5000 })
    await expect(page.locator('[data-testid="detail-play"]')).toBeVisible()
    await expect(page.locator('[data-testid="detail-favorite"]')).toBeVisible()
    // Fields beyond the title are rendered
    const drawer = page.locator('[role="dialog"]')
    await expect(drawer.getByText('Type', { exact: true })).toBeVisible()
    await expect(drawer.getByText('Plays', { exact: true })).toBeVisible()
  })

  test('a regular user can favorite an asset from the library', async ({ page }) => {
    await login(page, 'user')
    await waitForCards(page)

    const favBtn = page.locator('[data-testid="favorite-btn"]').first()
    const before = await favBtn.getAttribute('aria-pressed')
    await favBtn.click()
    await page.waitForTimeout(700)
    const after = await favBtn.getAttribute('aria-pressed')
    expect(after).not.toBe(before)
  })

  test('an admin can edit an asset and see the new value immediately', async ({ page }) => {
    await login(page, 'admin')
    await waitForCards(page)

    await page.locator('[data-testid="asset-card"]').first().click()
    await expect(page.locator('[data-testid="detail-title"]')).toBeVisible({ timeout: 5000 })

    // Admin-only Edit control is present
    await page.click('[data-testid="detail-edit"]')
    await page.fill('#edit-title', 'Updated Event')
    await page.click('[data-testid="detail-save"]')

    await expect(page.locator('[data-testid="detail-title"]')).toContainText('Updated Event', { timeout: 5000 })
  })

  test('a regular user does not see the Edit control', async ({ page }) => {
    await login(page, 'user')
    await waitForCards(page)
    await page.locator('[data-testid="asset-card"]').first().click()
    await expect(page.locator('[data-testid="detail-title"]')).toBeVisible({ timeout: 5000 })
    await expect(page.locator('[data-testid="detail-edit"]')).toHaveCount(0)
  })
})
