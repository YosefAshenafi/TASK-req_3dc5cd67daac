import { test, expect } from '@playwright/test'

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:5173'

async function loginAsUser(page: any) {
  await page.goto(`${BASE_URL}/login`)
  await page.getByLabel('Username').fill('user1')
  await page.getByLabel('Password').fill('password')
  await page.getByRole('button', { name: 'Sign In' }).click()
  await page.waitForURL('**/search', { timeout: 10000 })
}

test.describe('Playlist build and edit', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsUser(page)
  })

  test('playlists page displays heading', async ({ page }) => {
    await page.goto(`${BASE_URL}/playlists`)
    await expect(page.getByRole('heading', { name: 'Playlists' })).toBeVisible()
  })

  test('can open create playlist form', async ({ page }) => {
    await page.goto(`${BASE_URL}/playlists`)
    await page.getByRole('button', { name: '+ New Playlist' }).click()
    await expect(page.getByPlaceholder('Playlist name…')).toBeVisible()
  })

  test('can create a new playlist', async ({ page }) => {
    await page.goto(`${BASE_URL}/playlists`)
    await page.getByRole('button', { name: '+ New Playlist' }).click()

    const testName = `Test Playlist ${Date.now()}`
    await page.getByPlaceholder('Playlist name…').fill(testName)
    await page.getByRole('button', { name: 'Create' }).click()

    await expect(page.getByText(testName)).toBeVisible({ timeout: 5000 })
  })

  test('can navigate to playlist detail', async ({ page }) => {
    await page.goto(`${BASE_URL}/playlists`)

    // Create a playlist first
    await page.getByRole('button', { name: '+ New Playlist' }).click()
    const testName = `Nav Test ${Date.now()}`
    await page.getByPlaceholder('Playlist name…').fill(testName)
    await page.getByRole('button', { name: 'Create' }).click()
    await page.waitForTimeout(500)

    // Click on the playlist
    await page.getByText(testName).click()
    await page.waitForURL('**/playlists/**', { timeout: 5000 })
    await expect(page.getByText(testName)).toBeVisible()
  })

  test('can edit playlist name', async ({ page }) => {
    await page.goto(`${BASE_URL}/playlists`)

    // Create a playlist
    await page.getByRole('button', { name: '+ New Playlist' }).click()
    const originalName = `Edit Test ${Date.now()}`
    await page.getByPlaceholder('Playlist name…').fill(originalName)
    await page.getByRole('button', { name: 'Create' }).click()
    await page.waitForTimeout(500)

    // Navigate to it
    await page.getByText(originalName).click()
    await page.waitForURL('**/playlists/**')

    // Click edit pencil
    await page.getByRole('button', { name: 'Edit name' }).click()
    const newName = `Renamed ${Date.now()}`
    const nameInput = page.locator('input[class*="text-xl"]')
    await nameInput.fill(newName)
    // Wait for the PUT (update) response specifically — GET fires on page load so filter by method
    const saveResponse = page.waitForResponse(
      (r) => r.url().includes('/api/playlists/') && r.request().method() === 'PUT',
    )
    await page.getByRole('button', { name: 'Save' }).click()
    await saveResponse
    // After a successful save, edit mode ends and the heading shows the new name
    await expect(page.locator('input[class*="text-xl"]')).not.toBeAttached({ timeout: 3000 })
    await expect(page.getByText(newName, { exact: false })).toBeVisible({ timeout: 3000 })
  })
})
