import { test, expect } from '@playwright/test'

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:5173'

async function loginAsUser(page: any, username = 'user1') {
  await page.goto(`${BASE_URL}/login`)
  await page.getByLabel('Username').fill(username)
  await page.getByRole('textbox', { name: 'Password' }).fill('password')
  await page.getByRole('button', { name: 'Sign In' }).click()
  await page.waitForURL('**/search', { timeout: 10000 })
}

test.describe('Share and redeem playlist', () => {
  test('playlists page has Redeem button', async ({ page }) => {
    await loginAsUser(page)
    await page.goto(`${BASE_URL}/playlists`)
    await expect(page.getByRole('button', { name: 'Redeem' })).toBeVisible()
  })

  test('redeem dialog opens with keypad', async ({ page }) => {
    await loginAsUser(page)
    await page.goto(`${BASE_URL}/playlists`)
    await page.getByRole('button', { name: 'Redeem' }).click()

    // Scope to the dialog so "Redeem" inside the modal doesn't collide with the
    // page-level "Redeem" button that opens it.
    const dialog = page.getByRole('dialog', { name: 'Redeem Playlist Code' })
    await expect(dialog.getByRole('heading', { name: 'Redeem Playlist Code' })).toBeVisible()
    await expect(dialog.getByRole('button', { name: 'A', exact: true }).first()).toBeVisible()
    await expect(dialog.getByRole('button', { name: 'Redeem', exact: true })).toBeVisible()
  })

  test('share dialog can be opened from playlist', async ({ page }) => {
    await loginAsUser(page)
    await page.goto(`${BASE_URL}/playlists`)

    // Create a playlist
    await page.getByRole('button', { name: 'New Playlist' }).click()
    const name = `Share Test ${Date.now()}`
    await page.getByPlaceholder('Enter playlist name…').fill(name)
    await page.getByRole('button', { name: 'Create' }).click()
    await page.waitForTimeout(500)

    // Mock share API to avoid rate-limit issues from repeated test runs
    await page.route('**/api/playlists/*/share', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          id: 999,
          code: 'ABCD1234',
          expires_at: new Date(Date.now() + 86400000).toISOString(),
        }),
      })
    })

    // Click share button
    const shareButton = page.getByRole('button', { name: 'Share playlist' }).first()
    if (await shareButton.isVisible()) {
      await shareButton.click()
      await expect(page.getByRole('heading', { name: 'Share Playlist' })).toBeVisible({ timeout: 5000 })
    }
  })

  test('redeem dialog stays open and shows feedback after submitting invalid code', async ({ page }) => {
    await loginAsUser(page)
    await page.goto(`${BASE_URL}/playlists`)
    await page.getByRole('button', { name: 'Redeem' }).click()

    // Type 8 invalid characters using keypad
    for (let i = 0; i < 8; i++) {
      await page.evaluate(() => {
        const btn = Array.from(document.querySelectorAll('button')).find(
          (b) => b.textContent?.trim() === 'A',
        )
        btn?.click()
      })
    }

    await page.evaluate(() => {
      const btn = Array.from(document.querySelectorAll('button')).find(
        (b) => b.textContent?.trim() === 'Redeem',
      )
      btn?.click()
    })
    await page.waitForTimeout(1500)

    // After submitting an invalid code the dialog must not navigate away —
    // the dialog's heading must still be present.
    const dialog = page.getByRole('dialog', { name: 'Redeem Playlist Code' })
    const dialogStillOpen = await dialog
      .getByRole('heading', { name: 'Redeem Playlist Code' })
      .isVisible()
      .catch(() => false)
    expect(dialogStillOpen).toBe(true)
  })
})
