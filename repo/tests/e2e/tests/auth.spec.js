import { test, expect } from '@playwright/test'

const BASE = process.env.BASE_URL || 'http://localhost:8080'

test.describe('Authentication', () => {
  test('login page renders correctly', async ({ page }) => {
    await page.goto(BASE + '/login')
    await expect(page.locator('h1')).toContainText('SmartPark')
    await expect(page.locator('input#username')).toBeVisible()
    await expect(page.locator('input[type="password"]')).toBeVisible()
    await expect(page.locator('button[type="submit"]')).toBeEnabled()
  })

  test('shows validation error for empty form', async ({ page }) => {
    await page.goto(BASE + '/login')
    await page.click('button[type="submit"]')
    await expect(page.locator('[role="alert"]').first()).toBeVisible()
  })

  test('shows error for wrong credentials', async ({ page }) => {
    await page.goto(BASE + '/login')
    await page.fill('input#username', 'wronguser')
    await page.fill('input[type="password"]', 'wrongpassword')
    await page.click('button[type="submit"]')
    await expect(page.locator('[role="alert"]')).toBeVisible()
  })

  test('successful login redirects to library', async ({ page }) => {
    await page.goto(BASE + '/login')
    await page.fill('input#username', 'user')
    await page.fill('input[type="password"]', 'Password123!')
    await page.click('button[type="submit"]')
    await expect(page).toHaveURL(/\/library/, { timeout: 10000 })
  })

  test('admin can log in and see admin nav', async ({ page }) => {
    await page.goto(BASE + '/login')
    await page.fill('input#username', 'admin')
    await page.fill('input[type="password"]', 'Password123!')
    await page.click('button[type="submit"]')
    await expect(page).toHaveURL(/\/library/, { timeout: 10000 })
    await expect(page.locator('a[href*="/admin"]')).toBeVisible()
  })

  test('unauthenticated access redirects to login', async ({ page }) => {
    await page.goto(BASE + '/library')
    await expect(page).toHaveURL(/\/login/, { timeout: 5000 })
  })
})
