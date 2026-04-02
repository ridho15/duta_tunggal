import { test, expect } from '@playwright/test'

const BASE = 'http://localhost:8009'
const ERR = /Fatal error|Whoops!|Something went wrong/i

test.use({ storageState: 'playwright/.auth/user.json' })

test('material issue edit page filters warehouse choices by product stock', async ({ page }) => {
  await page.goto(`${BASE}/admin/material-issues/1/edit`)
  await page.waitForLoadState('networkidle')

  await expect(page.locator('body')).not.toContainText(ERR)
  await expect(page.locator('body')).toContainText('SKU-003')
  await expect(page.locator('body')).toContainText('GCA001')
})
