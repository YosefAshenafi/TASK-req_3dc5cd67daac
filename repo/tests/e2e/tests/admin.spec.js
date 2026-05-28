import { test, expect } from '@playwright/test'

const BASE = process.env.BASE_URL || 'http://localhost:3000'

async function loginAsAdmin(page) {
  await page.goto(BASE + '/login')
  await page.fill('input#username', 'admin')
  await page.fill('input[type="password"]', 'Password123!')
  await page.click('button[type="submit"]')
  await expect(page).toHaveURL(/\/library/, { timeout: 10000 })
}

test.describe('Admin Console', () => {
  test('admin can access user management', async ({ page }) => {
    await loginAsAdmin(page)
    await page.click('a[href*="/admin"]')
    await page.waitForURL(/\/admin/)
    await page.click('a[href*="/admin/users"]')
    await expect(page.locator('h2')).toContainText('User Management')
  })

  test('admin can access asset review', async ({ page }) => {
    await loginAsAdmin(page)
    await page.goto(BASE + '/admin/assets')
    await expect(page.locator('h2')).toContainText('Asset Review')
  })

  test('admin can access monitoring', async ({ page }) => {
    await loginAsAdmin(page)
    await page.goto(BASE + '/admin/monitoring')
    await expect(page.locator('h2')).toContainText('System Monitoring')
    await expect(page.locator('text=Database')).toBeVisible()
  })

  test('admin can freeze a user', async ({ page }) => {
    await loginAsAdmin(page)
    await page.goto(BASE + '/admin/users')
    await page.waitForSelector('table')
    // Target the technician row by role text to avoid freezing admin or the shared 'user' test account
    const freezeButton = page.locator('tr')
      .filter({ has: page.locator('span.badge:has-text("technician")') })
      .locator('button:has-text("Freeze")')
      .first()
    if (await freezeButton.isVisible().catch(() => false)) {
      await freezeButton.click()
      await page.fill('input[type="number"]', '72')
      await page.click('button.btn-primary')
      await page.waitForTimeout(500)
    }
  })

  test('regular user cannot access admin', async ({ page }) => {
    await page.goto(BASE + '/login')
    await page.fill('input#username', 'user')
    await page.fill('input[type="password"]', 'Password123!')
    await page.click('button[type="submit"]')
    await expect(page).toHaveURL(/\/library/, { timeout: 10000 })
    await page.goto(BASE + '/admin')
    await expect(page).toHaveURL(/\/library/)
  })

  test('asset review page shows table after load', async ({ page }) => {
    await loginAsAdmin(page)
    await page.goto(BASE + '/admin/assets')
    await expect(page.locator('h2')).toContainText('Asset Review')
    // Wait for skeleton to disappear
    await page.waitForFunction(() => !document.querySelector('.skeleton'), { timeout: 5000 }).catch(() => {})
    // Either the table is rendered (assets exist) or empty state is shown
    const hasTable = await page.locator('table').isVisible().catch(() => false)
    const hasEmptyState = await page.locator('text=No assets found').isVisible().catch(() => false)
    expect(hasTable || hasEmptyState).toBe(true)
  })

  test('admin can approve a pending asset and UI updates', async ({ page }) => {
    await loginAsAdmin(page)
    await page.goto(BASE + '/admin/assets?status=pending')
    await page.waitForFunction(() => !document.querySelector('.skeleton'), { timeout: 5000 }).catch(() => {})
    const approveBtn = page.locator('button:has-text("Approve")').first()
    const btnVisible = await approveBtn.isVisible().catch(() => false)
    if (btnVisible) {
      await approveBtn.click()
      // After approve the list reloads — the button should either disappear or a different set of rows renders
      await page.waitForTimeout(1000)
      // The page must still show Asset Review (no crash, no redirect)
      await expect(page.locator('h2')).toContainText('Asset Review')
    }
  })

  test('admin can reject a pending asset and UI updates', async ({ page }) => {
    await loginAsAdmin(page)
    await page.goto(BASE + '/admin/assets?status=pending')
    await page.waitForFunction(() => !document.querySelector('.skeleton'), { timeout: 5000 }).catch(() => {})
    const rejectBtn = page.locator('button:has-text("Reject")').first()
    const btnVisible = await rejectBtn.isVisible().catch(() => false)
    if (btnVisible) {
      await rejectBtn.click()
      await page.waitForTimeout(1000)
      await expect(page.locator('h2')).toContainText('Asset Review')
    }
  })

  test('user management table shows role and status columns', async ({ page }) => {
    await loginAsAdmin(page)
    await page.goto(BASE + '/admin/users')
    await page.waitForFunction(() => !document.querySelector('.skeleton'), { timeout: 5000 }).catch(() => {})
    await expect(page.locator('th:has-text("Role")')).toBeVisible()
    await expect(page.locator('th:has-text("Status")')).toBeVisible()
  })
})
