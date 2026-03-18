import { test, expect } from '@playwright/test'
import { execSync } from 'node:child_process'

test.use({ storageState: 'playwright/.auth/user.json' })

const FIXTURES = [
  { requestNumber: 'OR-TEST-A4-REQAPP', statusLabel: 'REQUEST APPROVE', rowClass: 'bg-gray-100' },
  { requestNumber: 'OR-TEST-A4-APPROVED', statusLabel: 'APPROVED', rowClass: 'bg-blue-50' },
  { requestNumber: 'OR-TEST-A4-PARTIAL', statusLabel: 'PARTIAL', rowClass: 'bg-yellow-50' },
  { requestNumber: 'OR-TEST-A4-COMPLETE', statusLabel: 'COMPLETE', rowClass: 'bg-green-50' },
  { requestNumber: 'OR-TEST-A4-CLOSED', statusLabel: 'CLOSED', rowClass: 'bg-red-50' },
  { requestNumber: 'OR-TEST-A4-REJECTED', statusLabel: 'REJECTED', rowClass: 'bg-red-50' },
]

test.beforeAll(async () => {
  execSync('php scripts/setup_order_request_a4_playwright_data.php', { stdio: 'inherit' })
})

test('A4: row classes and status badge are consistent for key statuses', async ({ page }) => {
  await page.goto('/admin/order-requests')
  await page.waitForLoadState('networkidle')
  await expect(page).not.toHaveURL(/login/)

  for (const fixture of FIXTURES) {
    const row = page.locator('tr, .fi-ta-row').filter({ hasText: fixture.requestNumber }).first()
    await expect(row).toBeVisible()

    const classAttr = (await row.getAttribute('class')) || ''
    expect(classAttr).toContain(fixture.rowClass)

    const statusCell = row.locator('td, .fi-ta-text').filter({ hasText: fixture.statusLabel }).first()
    await expect(statusCell).toBeVisible()
  }
})
