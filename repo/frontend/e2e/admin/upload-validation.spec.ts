import { test, expect } from '@playwright/test'

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:5173'

async function loginAsAdmin(page: any) {
  await page.goto(`${BASE_URL}/login`)
  await page.getByLabel('Username').fill('admin')
  await page.getByRole('textbox', { name: 'Password' }).fill('password')
  await page.getByRole('button', { name: 'Sign In' }).click()
  await page.waitForURL('**/admin', { timeout: 10000 })
}

test.describe('Admin upload validation', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page)
  })

  test('uploads page displays heading and drop zone', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/uploads`)
    await expect(page.getByRole('heading', { name: 'Upload Media' })).toBeVisible()
    await expect(page.getByText('Drop files here')).toBeVisible()
  })

  test('browse files button is present', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/uploads`)
    await expect(page.getByText('Browse Files')).toBeVisible()
  })

  test('can upload a test file and see metadata form', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/uploads`)

    // Create a test file
    const buffer = Buffer.from('fake video content')
    const fileInput = page.locator('input[type="file"]')

    await fileInput.setInputFiles({
      name: 'test-video.mp4',
      mimeType: 'video/mp4',
      buffer,
    })

    await page.waitForTimeout(500)

    await expect(page.getByText('test-video.mp4')).toBeVisible()
    await expect(page.getByPlaceholder('Safety, Parking, Event')).toBeVisible()
    await expect(page.getByRole('button', { name: 'Upload', exact: true })).toBeVisible()
  })

  test('metadata form has title, tags, description fields', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/uploads`)

    const buffer = Buffer.from('test content')
    await page.locator('input[type="file"]').setInputFiles({
      name: 'test.mp4',
      mimeType: 'video/mp4',
      buffer,
    })

    await page.waitForTimeout(300)

    await expect(page.locator('label').filter({ hasText: 'Title' })).toBeVisible()
    await expect(page.locator('label').filter({ hasText: /Tags/ })).toBeVisible()
    await expect(page.locator('label').filter({ hasText: 'Description' })).toBeVisible()
  })
})
