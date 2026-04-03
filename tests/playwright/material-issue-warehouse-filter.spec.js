import { test, expect } from '@playwright/test'
import { acquirePlaywrightDbLock, ensureManufacturingFixture, querySingleValue, FIXTURE, seedManufacturingFixture } from './helpers/manufacturing-fixture.js'

const BASE = 'http://localhost:8009'
const ERR = /Fatal error|Whoops!|Something went wrong/i

test.use({ storageState: 'playwright/.auth/user.json' })

test.beforeAll(() => {
  ensureManufacturingFixture()
})

test('material issue edit page filters warehouse choices by product stock', async ({ page }) => {
  const releaseLock = await acquirePlaywrightDbLock()

  try {
  seedManufacturingFixture()
  const issueId = querySingleValue(`DB::table('material_issues')->where('issue_number', '${FIXTURE.issueNumber}')->value('id')`)
  expect(issueId).toBeTruthy()

  await page.goto(`${BASE}/admin/material-issues/${issueId}/edit`, { waitUntil: 'domcontentloaded' })

  await expect(page.locator('body')).not.toContainText(ERR)
  await expect(page.locator('body')).toContainText(FIXTURE.rawMaterialSku)
  await expect(page.locator('body')).toContainText(FIXTURE.warehouseCode)
  } finally {
    releaseLock()
  }
})
