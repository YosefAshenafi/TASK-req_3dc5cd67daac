import { test, expect } from '@playwright/test'

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:5173'
const API_BASE_URL = process.env.PLAYWRIGHT_API_BASE_URL || 'http://localhost:8090'

async function apiLoginAndGetToken(request: any, username: string, password: string): Promise<string> {
  const loginResponse = await request.post(`${API_BASE_URL}/api/auth/login`, {
    data: { username, password },
  })
  expect(loginResponse.ok()).toBe(true)
  const payload = await loginResponse.json()
  return payload.token
}

test.describe('Live device events integration', () => {
  test('technician sees ingested device and event status from live backend', async ({ page, request }) => {
    const token = await apiLoginAndGetToken(request, 'tech1', 'password')

    const deviceId = `live-device-${Date.now()}`
    const idempotencyKey = `ikey-${Date.now()}`

    const payload = {
      device_id: deviceId,
      event_type: 'heartbeat',
      sequence_no: 100,
      occurred_at: new Date().toISOString(),
      payload: { lane: 'W1' },
    }

    // The API requires the idempotency key in the X-Idempotency-Key header
    // (see DeviceController::events) — sending it only in the body is rejected with 400.
    const ingestResponse = await request.post(`${API_BASE_URL}/api/devices/events`, {
      headers: {
        Authorization: `Bearer ${token}`,
        'Content-Type': 'application/json',
        'X-Idempotency-Key': idempotencyKey,
      },
      data: payload,
    })

    expect(ingestResponse.ok()).toBe(true)

    await page.goto(`${BASE_URL}/login`)
    await page.getByLabel('Username').fill('tech1')
    await page.getByRole('textbox', { name: 'Password' }).fill('password')
    await page.getByRole('button', { name: 'Sign In' }).click()
    await page.waitForURL('**/devices', { timeout: 10000 })

    await expect(page.getByText(deviceId)).toBeVisible({ timeout: 10000 })
    await page.getByText(deviceId).click()
    await page.waitForURL(`**/devices/${deviceId}`, { timeout: 5000 })

    await expect(page.getByRole('button', { name: 'Accepted' })).toBeVisible()
    await expect(page.getByText('accepted').first()).toBeVisible()
  })
})
