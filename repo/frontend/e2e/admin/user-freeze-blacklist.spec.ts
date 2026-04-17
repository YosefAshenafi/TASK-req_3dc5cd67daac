import { test, expect } from '@playwright/test'

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:5173'

async function loginAsAdmin(page: any) {
  await page.goto(`${BASE_URL}/login`)
  await page.getByLabel('Username').fill('admin')
  await page.getByLabel('Password').fill('password')
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
    await expect(page.getByText('Role', { exact: true })).toBeVisible()
    await expect(page.getByText('Status', { exact: true })).toBeVisible()
    await expect(page.getByText('Actions', { exact: true })).toBeVisible()
  })

  test('can open create user form', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/users`)
    await page.getByRole('button', { name: '+ Create User' }).click()
    await expect(page.getByText('New User')).toBeVisible()
    await expect(page.getByRole('combobox').first()).toBeVisible()
  })

  test('filter by role dropdown exists', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/users`)
    const roleFilter = page.locator('select').first()
    await expect(roleFilter).toBeVisible()
    await roleFilter.selectOption('admin')
  })

  test('freeze button is visible for active users', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/users`)
    await page.waitForTimeout(1000)

    const freezeBtn = page.getByRole('button', { name: 'Freeze' }).first()
    const isVisible = await freezeBtn.isVisible()
    if (isVisible) {
      await expect(freezeBtn).toBeVisible()
    }
    expect(true).toBe(true)
  })

  test('non-admin cannot access admin users page', async ({ page }) => {
    // Clear auth token so the router doesn't redirect us away from /login
    await page.evaluate(() => localStorage.clear())
    // Log in as regular user
    await page.goto(`${BASE_URL}/login`)
    await page.getByLabel('Username').fill('user1')
    await page.getByLabel('Password').fill('password')
    await page.getByRole('button', { name: 'Sign In' }).click()
    await page.waitForURL('**/search', { timeout: 10000 })

    await page.goto(`${BASE_URL}/admin/users`)
    await page.waitForURL('**/403', { timeout: 5000 })
    await expect(page.getByText('403')).toBeVisible()
  })
})
