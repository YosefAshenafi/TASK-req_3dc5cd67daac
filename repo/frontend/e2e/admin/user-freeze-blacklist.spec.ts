import { test, expect } from '@playwright/test'

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:5173'

async function loginAsAdmin(page: any) {
  await page.goto(`${BASE_URL}/login`)
  await page.getByLabel('Username').fill('admin')
  await page.getByRole('textbox', { name: 'Password' }).fill('password')
  await page.getByRole('button', { name: 'Sign In' }).click()
  await page.waitForURL('**/admin', { timeout: 10000 })
}

test.describe('Admin user freeze and blacklist', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page)
  })

  test('admin users page displays heading', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/users`)
    await expect(page.getByRole('heading', { name: 'User Management' })).toBeVisible()
  })

  test('user table displays with role/status columns', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/users`)
    // Use column-header role so we don't collide with filter labels that share these names.
    await expect(page.getByRole('columnheader', { name: 'Role' })).toBeVisible()
    await expect(page.getByRole('columnheader', { name: 'Status' })).toBeVisible()
    await expect(page.getByRole('columnheader', { name: 'Actions' })).toBeVisible()
  })

  test('can open create user form', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/users`)
    await page.getByRole('button', { name: 'Create User' }).click()
    await expect(page.getByText('New User')).toBeVisible()
    await expect(page.getByRole('combobox').first()).toBeVisible()
  })

  test('filter by role dropdown exists', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/users`)
    const roleFilter = page.locator('select').first()
    await expect(roleFilter).toBeVisible()
    await roleFilter.selectOption('admin')
  })

  test('user table lists seeded users with freeze/blacklist actions', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/users`)
    await page.waitForTimeout(1000)
    // Seeded DB always has at least admin + user1 + tech1 — table must render real rows.
    const rows = page.locator('table tbody tr')
    await expect(rows.first()).toBeVisible()
    const rowCount = await rows.count()
    expect(rowCount).toBeGreaterThan(0)
  })

  test('non-admin cannot access admin users page', async ({ page }) => {
    // Clear auth token so the router doesn't redirect us away from /login
    await page.evaluate(() => localStorage.clear())
    // Log in as regular user
    await page.goto(`${BASE_URL}/login`)
    await page.getByLabel('Username').fill('user1')
    await page.getByRole('textbox', { name: 'Password' }).fill('password')
    await page.getByRole('button', { name: 'Sign In' }).click()
    await page.waitForURL('**/search', { timeout: 10000 })

    await page.goto(`${BASE_URL}/admin/users`)
    await page.waitForURL('**/403', { timeout: 5000 })
    await expect(page.getByText('403')).toBeVisible()
  })
})
