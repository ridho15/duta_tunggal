import { test, expect } from '@playwright/test'
import { confirmDialogAction, ensureManufacturingFixture, querySingleValue, FIXTURE } from './helpers/manufacturing-fixture.js'

const BASE = 'http://localhost:8009'
const ERR = /Fatal error|Whoops!|Something went wrong/i

test.use({ storageState: 'playwright/.auth/user.json' })

test.describe.serial('Warehouse confirmation material issue view', () => {
  test.beforeAll(() => {
    ensureManufacturingFixture()
  })

  test('view page shows material issue detail per bahan', async ({ page }) => {
    const issueId = querySingleValue(`DB::table('material_issues')->where('issue_number', '${FIXTURE.issueNumber}')->value('id')`)
    expect(issueId).toBeTruthy()

    await page.goto(`${BASE}/admin/material-issues?tableAction=request_approval&tableActionRecord=${issueId}`)
    await page.waitForLoadState('networkidle')
    await confirmDialogAction(page, 'Konfirmasi')

    await expect.poll(
      () => querySingleValue(`DB::table('warehouse_confirmations')->where('confirmable_type', 'App\\Models\\MaterialIssue')->where('confirmable_id', ${issueId})->value('id')`),
      { timeout: 15000 },
    ).not.toBe('')

    const warehouseConfirmationId = querySingleValue(
      `DB::table('warehouse_confirmations')->where('confirmable_type', 'App\\Models\\MaterialIssue')->where('confirmable_id', ${issueId})->value('id')`,
    )

    expect(warehouseConfirmationId).toBeTruthy()

    await page.goto(`${BASE}/admin/warehouse-confirmations/${warehouseConfirmationId}`)
    await page.waitForLoadState('networkidle')

    await expect(page.locator('body')).not.toContainText(ERR)
    await expect(page.locator('body')).toContainText('Material Issue Confirmation')
    await expect(page.locator('body')).toContainText('Source Item')
    await expect(page.locator('body')).toContainText('Produk Request')
    await expect(page.locator('body')).toContainText('Gudang Request')
    await expect(page.locator('body')).toContainText('Informasi Material Issue')
    await expect(page.locator('body')).toContainText('Rincian Bahan')
    await expect(page.locator('body')).toContainText('Item Konfirmasi')
    await expect(page.locator('body')).toContainText(FIXTURE.rawMaterialSku)
    await expect(page.locator('body')).toContainText('Material Issue Item #')
    await expect(page.locator('body')).toContainText('Request 50')
    await expect(page.locator('body')).toContainText('Confirm 50')
    await expect(page.locator('body')).toContainText('Status Request')
  })
})