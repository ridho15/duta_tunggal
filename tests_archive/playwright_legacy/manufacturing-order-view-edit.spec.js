import { test, expect } from '@playwright/test'
import { ensureManufacturingFixture, querySingleValue, FIXTURE } from './helpers/manufacturing-fixture.js'

const BASE = 'http://localhost:8009'
const ERR = /Fatal error|Whoops!|Something went wrong/i
const MO_NUMBER = 'MO-PW-MFG-001'

test.use({ storageState: 'playwright/.auth/user.json' })

test.describe.serial('Manufacturing order view/edit pages', () => {
  test.beforeAll(() => {
    ensureManufacturingFixture()
  })

  test('view page shows material details', async ({ page }) => {
    const manufacturingOrderId = querySingleValue(`DB::table('manufacturing_orders')->where('mo_number', '${MO_NUMBER}')->value('id')`)
    expect(manufacturingOrderId).toBeTruthy()

    await page.goto(`${BASE}/admin/manufacturing-orders/${manufacturingOrderId}`)
    await page.waitForLoadState('networkidle')

    await expect(page.locator('body')).not.toContainText(ERR)
    await expect(page.locator('body')).toContainText('Detail Bahan')
    await expect(page.locator('body')).toContainText(FIXTURE.rawMaterialSku)
    await expect(page.locator('body')).toContainText('Fixture Raw Material Manufacturing')
    await expect(page.locator('body')).toContainText(FIXTURE.planNumber)
  })

  test('edit page loads with manufacturing order form and material section', async ({ page }) => {
    const manufacturingOrderId = querySingleValue(`DB::table('manufacturing_orders')->where('mo_number', '${MO_NUMBER}')->value('id')`)
    expect(manufacturingOrderId).toBeTruthy()

    await page.goto(`${BASE}/admin/manufacturing-orders/${manufacturingOrderId}/edit`)
    await page.waitForLoadState('networkidle')

    await expect(page.locator('body')).not.toContainText(ERR)
    await expect(page.getByRole('textbox', { name: /mo number/i })).toBeVisible()
    await expect(page.locator('body')).toContainText('Detail Bahan')
    await expect(page.locator('body')).toContainText('Rencana Produksi')
    await expect(page.locator('body')).toContainText(FIXTURE.planNumber)
  })
})
