import { test, expect } from '@playwright/test'
import { acquirePlaywrightDbLock, ensureManufacturingFixture, querySingleValue, FIXTURE } from './helpers/manufacturing-fixture.js'

const BASE = 'http://localhost:8009'
const ERR = /Fatal error|Whoops!|Something went wrong/i

test.use({ storageState: 'playwright/.auth/user.json' })

test.describe.serial('Warehouse confirmation material issue view', () => {
  test.beforeAll(() => {
    ensureManufacturingFixture()
  })

  test('view page shows material issue detail per bahan', async ({ page }) => {
    const releaseLock = await acquirePlaywrightDbLock()

    try {
    const issueId = querySingleValue(`DB::table('material_issues')->where('issue_number', '${FIXTURE.issueNumber}')->value('id')`)
    expect(issueId).toBeTruthy()

    await page.goto(`${BASE}/admin/material-issues`, { waitUntil: 'domcontentloaded' })
    await expect(page.locator('body')).not.toContainText(ERR)

    const row = page.locator('tr', { hasText: FIXTURE.issueNumber }).first()
    await expect(row).toBeVisible()
    await row.locator('button:visible').last().click({ force: true })
    await page.waitForTimeout(300)

    const requestConfirmationButton = page.locator(`[wire\\:click*="mountTableAction('request_approval', '${issueId}')"]:visible`).last()
    await expect(requestConfirmationButton).toBeVisible()
    await requestConfirmationButton.click({ force: true })

    const confirmationDialog = page.locator('.fi-modal.fi-modal-open').filter({ hasText: 'Request Konfirmasi Gudang' }).last()
    await expect(confirmationDialog.getByRole('button', { name: 'Konfirmasi' })).toBeVisible()

    const livewireUpdate = page.waitForResponse(
      (response) => response.url().includes('/livewire/update') && response.request().method() === 'POST',
    )

    await confirmationDialog.getByRole('button', { name: 'Konfirmasi' }).click({ force: true })
    await livewireUpdate
    await expect(page.locator('body')).not.toContainText(ERR)

    await expect.poll(
      () => Number(querySingleValue(`DB::table('warehouse_confirmations')->where('confirmable_type', 'App\\Models\\MaterialIssue')->where('confirmable_id', ${issueId})->count()`)),
      { timeout: 15000 },
    ).toBeGreaterThan(0)

    const warehouseConfirmationId = querySingleValue(
      `DB::table('warehouse_confirmations')->where('confirmable_type', 'App\\Models\\MaterialIssue')->where('confirmable_id', ${issueId})->orderBy('id')->value('id')`,
    )

    expect(warehouseConfirmationId).toBeTruthy()

    await page.goto(`${BASE}/admin/warehouse-confirmations/${warehouseConfirmationId}`, { waitUntil: 'domcontentloaded' })

    await expect(page.locator('body')).not.toContainText(ERR)
    await expect(page.locator('body')).toContainText('Konfirmasi Gudang')
    await expect(page.locator('body')).toContainText('Source Item')
    await expect(page.locator('body')).toContainText('Produk Request')
    await expect(page.locator('body')).toContainText('Gudang Request')
    await expect(page.locator('body')).toContainText(FIXTURE.issueNumber)
    await expect(page.locator('body')).toContainText(FIXTURE.rawMaterialSku)
    await expect(page.locator('body')).toContainText(FIXTURE.warehouseCode)
    await expect(page.locator('body')).toContainText('Material Issue Item #')
    await expect(page.locator('body')).toContainText('Request 50')
    } finally {
      releaseLock()
    }
  })
})