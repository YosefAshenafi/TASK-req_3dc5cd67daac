import { test, expect } from '@playwright/test'

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:5173'

async function loginAsTech(page: any) {
  await page.goto(`${BASE_URL}/login`)
  await page.getByLabel('Username').fill('tech1')
  await page.getByLabel('Password').fill('password')
  await page.getByRole('button', { name: 'Sign In' }).click()
  await page.waitForURL('**/devices', { timeout: 10000 })
}

const MOCK_DEVICE_ID = 'replay-device-001'

test.describe('Replay with audit trail', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsTech(page)

    await page.route('**/api/devices', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify([
          { id: MOCK_DEVICE_ID, kind: 'kiosk', label: 'Test Device', last_sequence_no: 500, last_seen_at: new Date().toISOString() },
        ]),
      })
    })

    await page.route(`**/api/devices/${MOCK_DEVICE_ID}`, async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ id: MOCK_DEVICE_ID, kind: 'kiosk', label: 'Test Device', last_sequence_no: 500, last_seen_at: new Date().toISOString() }),
      })
    })

    await page.route(`**/api/devices/${MOCK_DEVICE_ID}/events**`, async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ items: [], next_cursor: null }),
      })
    })

    await page.route(`**/api/devices/${MOCK_DEVICE_ID}/replay/audits`, async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify([
          {
            id: 1,
            device_id: MOCK_DEVICE_ID,
            initiated_by: 3,
            since_sequence_no: 100,
            until_sequence_no: 200,
            reason: 'Testing replay functionality',
            created_at: new Date().toISOString(),
          },
        ]),
      })
    })
  })

  test('replay tab shows sequence inputs', async ({ page }) => {
    await page.goto(`${BASE_URL}/devices`)
    await page.waitForTimeout(500)
    await page.getByText(MOCK_DEVICE_ID).click()
    await page.waitForURL(`**/devices/${MOCK_DEVICE_ID}`)

    await page.getByRole('button', { name: 'Replay' }).click()
    await expect(page.getByRole('heading', { name: 'Initiate Replay' })).toBeVisible()
    await expect(page.getByRole('spinbutton').first()).toBeVisible()
    await expect(page.getByPlaceholder('Why is this replay being initiated?')).toBeVisible()
  })

  test('audit trail shows replay records', async ({ page }) => {
    await page.goto(`${BASE_URL}/devices`)
    await page.waitForTimeout(500)
    await page.getByText(MOCK_DEVICE_ID).click()
    await page.waitForURL(`**/devices/${MOCK_DEVICE_ID}`)

    await page.getByRole('button', { name: 'Audit Trail' }).click()
    await page.waitForTimeout(500)

    await expect(page.getByText('Seq# 100')).toBeVisible()
    await expect(page.getByText('Testing replay functionality')).toBeVisible()
  })

  test('replay confirmation dialog shows before submitting', async ({ page }) => {
    await page.route(`**/api/devices/${MOCK_DEVICE_ID}/replay`, async (route) => {
      await route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({
          id: 2,
          device_id: MOCK_DEVICE_ID,
          initiated_by: 3,
          since_sequence_no: 50,
          reason: 'E2E test replay',
          created_at: new Date().toISOString(),
        }),
      })
    })

    await page.goto(`${BASE_URL}/devices`)
    await page.waitForTimeout(500)
    await page.getByText(MOCK_DEVICE_ID).click()
    await page.waitForURL(`**/devices/${MOCK_DEVICE_ID}`)

    await page.getByRole('button', { name: 'Replay' }).click()

    const sinceInput = page.getByRole('spinbutton').first()
    await sinceInput.fill('50')
    await page.getByPlaceholder('Why is this replay being initiated?').fill('E2E test replay')

    await page.getByRole('button', { name: 'Initiate Replay' }).click()
    await expect(page.getByText('Confirm Replay')).toBeVisible()
    await expect(page.getByText('Replay events from seq# 50')).toBeVisible()
  })
})
