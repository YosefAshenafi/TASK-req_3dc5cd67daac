import { test, expect } from '@playwright/test'

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || process.env.BASE_URL || 'http://localhost:5173'

interface RoleConfig {
  username: string
  password: string
  expectedPath: string
  expectedHeading: string
}

const ROLE_CONFIGS: RoleConfig[] = [
  {
    username: 'admin',
    password: 'password',
    expectedPath: '/admin',
    expectedHeading: 'Admin Console',
  },
  {
    username: 'user1',
    password: 'password',
    expectedPath: '/search',
    expectedHeading: 'Search',
  },
  {
    username: 'tech1',
    password: 'password',
    expectedPath: '/devices',
    expectedHeading: 'Devices',
  },
]

test.describe('Login by role', () => {
  for (const config of ROLE_CONFIGS) {
    test(`${config.username} logs in and lands on ${config.expectedPath}`, async ({ page }) => {
      await page.goto(`${BASE_URL}/login`)

      await expect(page.getByText('SmartPark')).toBeVisible()

      await page.getByLabel('Username').fill(config.username)
      await page.getByRole('textbox', { name: 'Password' }).fill(config.password)
      await page.getByRole('button', { name: 'Sign In' }).click()

      await page.waitForURL(`**${config.expectedPath}`, { timeout: 10000 })
      expect(page.url()).toContain(config.expectedPath)

      await expect(page.getByRole('heading', { name: config.expectedHeading })).toBeVisible({
        timeout: 5000,
      })
    })
  }

  test('shows error for invalid credentials', async ({ page }) => {
    await page.goto(`${BASE_URL}/login`)

    await page.getByLabel('Username').fill('notauser')
    await page.getByRole('textbox', { name: 'Password' }).fill('wrongpassword')
    await page.getByRole('button', { name: 'Sign In' }).click()

    await expect(page.getByRole('alert')).toBeVisible({ timeout: 5000 })
    expect(page.url()).toContain('/login')
  })

  test('redirects authenticated user away from /login', async ({ page }) => {
    // Login first
    await page.goto(`${BASE_URL}/login`)
    await page.getByLabel('Username').fill('user1')
    await page.getByRole('textbox', { name: 'Password' }).fill('password')
    await page.getByRole('button', { name: 'Sign In' }).click()
    await page.waitForURL('**/search', { timeout: 10000 })

    // Navigating to /login should redirect
    await page.goto(`${BASE_URL}/login`)
    await page.waitForURL('**/search', { timeout: 5000 })
    expect(page.url()).not.toContain('/login')
  })
})
