import { test, expect } from '@playwright/test'
import {
  acquirePlaywrightDbLock,
  confirmDialogAction,
  FIXTURE,
  ensureManufacturingFixture,
  openRowAction,
  seedManufacturingFixture,
  selectFixtureProductionPlan,
  querySingleValue,
} from './helpers/manufacturing-fixture.js'

const BASE = 'http://localhost:8009'
const ERR = /Fatal error|Whoops!|Something went wrong/i

test.use({ storageState: 'playwright/.auth/user.json' })
test.setTimeout(60000)

async function navigate(page, path) {
  await page.goto(path)
  await page.waitForLoadState('domcontentloaded')

  if (page.url().includes('/login')) {
    await page.getByLabel('Alamat email').fill('superadmin@gmail.com')
    await page.getByLabel('Kata sandi').fill('superadmin')
    await page.locator('form').getByRole('button', { name: /masuk|login|sign in/i }).click()
    await page.waitForLoadState('networkidle')
    await page.goto(path)
    await page.waitForLoadState('domcontentloaded')
  }

  await page.waitForLoadState('networkidle')
}

test.describe.serial('Manufacturing full flow', () => {
  test.beforeAll(async () => {
    ensureManufacturingFixture()
  })

  test('material issue shows available and reserved stock across reserve flow', async ({ page }) => {
    const releaseLock = await acquirePlaywrightDbLock()

    try {
    seedManufacturingFixture()
    const issueId = querySingleValue(`DB::table('material_issues')->where('issue_number', '${FIXTURE.issueNumber}')->value('id')`)
    expect(issueId).toBeTruthy()

    const readStockField = async (label) => {
      const field = page.getByRole('textbox', { name: label }).first()
      await expect(field).toBeVisible({ timeout: 10000 })
      return Number((await field.inputValue()).replace(/,/g, ''))
    }

    const availableQuery = `DB::table('inventory_stocks')->where('product_id', DB::table('products')->where('sku', '${FIXTURE.rawMaterialSku}')->value('id'))->where('warehouse_id', DB::table('warehouses')->where('kode', '${FIXTURE.warehouseCode}')->value('id'))->value('qty_available')`
    const reservedQuery = `DB::table('inventory_stocks')->where('product_id', DB::table('products')->where('sku', '${FIXTURE.rawMaterialSku}')->value('id'))->where('warehouse_id', DB::table('warehouses')->where('kode', '${FIXTURE.warehouseCode}')->value('id'))->value('qty_reserved')`

      await navigate(page, `${BASE}/admin/material-issues/${issueId}/edit`)
      await expect(page.locator('body')).not.toContainText(ERR)

    await expect.poll(() => readStockField('Stock Available'), { timeout: 10000 }).toBe(100)
    await expect.poll(() => readStockField('Stock Reserved'), { timeout: 10000 }).toBe(0)

    await navigate(page, `${BASE}/admin/material-issues`)

    const materialIssueRow = page.locator('tr', { hasText: FIXTURE.issueNumber }).first()
    await expect(materialIssueRow).toBeVisible()
    await materialIssueRow.locator('button:visible').last().click({ force: true })
    await page.waitForTimeout(300)

    const requestConfirmationButton = page.locator(`[wire\\:click*="mountTableAction('request_approval', '${issueId}')"]:visible`).last()
    await expect(requestConfirmationButton).toBeVisible()
    await requestConfirmationButton.click({ force: true })
    await confirmDialogAction(page, 'Konfirmasi')
    await page.waitForLoadState('networkidle')
    await expect.poll(
      () => querySingleValue(`DB::table('material_issues')->where('id', ${issueId})->value('status')`),
      { timeout: 15000 },
    ).toBe('pending_approval')

    const warehouseConfirmationId = querySingleValue(
      `DB::table('warehouse_confirmations')->where('confirmable_type', 'App\\Models\\MaterialIssue')->where('confirmable_id', ${issueId})->orderBy('id')->value('id')`,
    )
    expect(warehouseConfirmationId).toBeTruthy()

    await expect.poll(
      () => querySingleValue(`DB::table('warehouse_confirmations')->where('id', ${warehouseConfirmationId})->value('status')`),
      { timeout: 15000 },
    ).toBe('request')

    await navigate(page, `${BASE}/admin/warehouse-confirmations`)

    const confirmationRow = page.locator('tr', { hasText: FIXTURE.issueNumber }).first()
    await expect(confirmationRow).toBeVisible()
    await openRowAction(page, confirmationRow, 'Approve')
    await confirmDialogAction(page, 'Konfirmasi')
    await page.waitForLoadState('networkidle')
    await expect.poll(
      () => querySingleValue(`DB::table('material_issues')->where('id', ${issueId})->value('status')`),
      { timeout: 15000 },
    ).toBe('completed')
    await expect.poll(() => Number(querySingleValue(availableQuery)), { timeout: 15000 }).toBe(50)
    await expect.poll(() => Number(querySingleValue(reservedQuery)), { timeout: 15000 }).toBe(0)
    } finally {
      releaseLock()
    }
  })

  test('plan to MO to production to QC completes successfully', async ({ page }) => {
    const releaseLock = await acquirePlaywrightDbLock()

    try {
    seedManufacturingFixture()
    const moNumber = `MO-PW-E2E-${Date.now()}`

    await navigate(page, `${BASE}/admin/manufacturing-orders/create`)

    const createBody = await page.textContent('body')
    expect(createBody).not.toMatch(ERR)

    await page.getByLabel(/mo number/i).fill(moNumber)
    await selectFixtureProductionPlan(page)

    const cabangField = page.locator('.fi-fo-field-wrp').filter({ hasText: 'Cabang' }).first().getByRole('combobox').first()
    await expect.poll(async () => ((await cabangField.textContent()) ?? '').trim(), { timeout: 10000 }).not.toMatch(/Pilih salah satu opsi/i)

    await page.getByRole('button', { name: /^Buat$/ }).click()
    await page.waitForURL(/\/admin\/manufacturing-orders\/\d+/, { timeout: 15000 })
    await page.waitForLoadState('networkidle')

    const urlMatch = page.url().match(/\/admin\/manufacturing-orders\/(\d+)/)
    const manufacturingOrderId = urlMatch?.[1] ?? querySingleValue(`DB::table('manufacturing_orders')->where('mo_number', '${moNumber}')->value('id')`)
    expect(manufacturingOrderId).toBeTruthy()

    await expect(page.getByRole('textbox', { name: 'Mo number' })).toHaveValue(moNumber)
    await expect(page.locator('.fi-fo-field-wrp').filter({ hasText: 'Rencana Produksi' }).first()).toContainText(FIXTURE.planNumber)
    await page.getByRole('button', { name: /^Produksi$/ }).click()
    const confirmProduksi = page.getByRole('button', { name: /^Konfirmasi$/ }).last()
    await expect(confirmProduksi).toBeVisible({ timeout: 10000 })
    await confirmProduksi.click({ force: true })

    await expect.poll(
      () => querySingleValue(`DB::table('productions')->where('manufacturing_order_id', ${manufacturingOrderId})->value('id')`),
      { timeout: 15000 },
    ).not.toBe('')

    const productionId = querySingleValue(`DB::table('productions')->where('manufacturing_order_id', ${manufacturingOrderId})->value('id')`)
    expect(productionId).toBeTruthy()

    await navigate(page, `${BASE}/admin/productions/${productionId}`)

    const finishButton = page.getByRole('button', { name: /^Finished$/ }).last()
    await expect(finishButton).toBeVisible({ timeout: 10000 })
    await finishButton.click({ force: true })
    await confirmDialogAction(page, 'Konfirmasi')

    await expect.poll(
      () => querySingleValue(`DB::table('productions')->where('id', ${productionId})->value('status')`),
      { timeout: 15000 },
    ).toBe('finished')

    const qualityControlId = querySingleValue(`DB::table('quality_controls')->where('from_model_type', 'App\\\\Models\\\\Production')->where('from_model_id', ${productionId})->value('id')`)
    expect(qualityControlId).toBeTruthy()

    await navigate(page, `${BASE}/admin/quality-control-manufactures`)

    const qualityControlRow = page.locator('tr', { hasText: moNumber }).first()
    await expect(qualityControlRow).toBeVisible()
    await openRowAction(page, qualityControlRow, 'Process QC')

    const passedInput = page.getByRole('spinbutton', { name: 'Passed Quantity' }).last()
    const rejectedInput = page.getByRole('spinbutton', { name: 'Rejected Quantity' }).last()
    const rejectReasonInput = page.getByRole('textbox', { name: 'Reason Reject' }).last()

    await expect(passedInput).toBeEditable({ timeout: 10000 })
    await passedInput.fill('4')
    await rejectedInput.fill('1')
    await rejectReasonInput.fill('Reject fixture reason from Playwright end-to-end test')

    const processSubmit = page.getByRole('button', { name: /^(Kirim|Process QC|Konfirmasi)$/i }).last()
    await expect(processSubmit).toBeVisible()
    await processSubmit.click({ force: true })
    await expect.poll(
      () => querySingleValue(`DB::table('quality_controls')->where('id', ${qualityControlId})->value('status')`),
      { timeout: 15000 },
    ).toBe('1')
    await expect.poll(
      () => querySingleValue(`DB::table('quality_controls')->where('id', ${qualityControlId})->value('passed_quantity')`),
      { timeout: 15000 },
    ).toBe('4.00')
    await expect.poll(
      () => querySingleValue(`DB::table('quality_controls')->where('id', ${qualityControlId})->value('rejected_quantity')`),
      { timeout: 15000 },
    ).toBe('1.00')

    await navigate(page, `${BASE}/admin/manufacturing-orders/${manufacturingOrderId}`)

    await expect.poll(
      () => querySingleValue(`DB::table('manufacturing_orders')->where('id', ${manufacturingOrderId})->value('status')`),
      { timeout: 15000 },
    ).toBe('completed')
    await expect(page.getByRole('button', { name: /^Produksi$/ })).toBeHidden()
    } finally {
      releaseLock()
    }
  })
})