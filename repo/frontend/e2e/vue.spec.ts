import { test, expect } from '@playwright/test'

test('visits the app root url', async ({ page }) => {
  await page.goto('/')
  // Unauthenticated users are redirected to /login
  await page.waitForURL('**/login**', { timeout: 10000 })
  await expect(page.locator('body')).toBeVisible()
})
