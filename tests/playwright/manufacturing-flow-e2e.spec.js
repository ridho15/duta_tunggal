import { test, expect } from '@playwright/test'
import {
  FIXTURE,
  ensureManufacturingFixture,
  selectFixtureProductionPlan,
  querySingleValue,
} from './helpers/manufacturing-fixture.js'

const BASE = 'http://localhost:8009'
const ERR = /Fatal error|Whoops!|Something went wrong/i

test.use({ storageState: 'playwright/.auth/user.json' })

test.describe.serial('Manufacturing full flow', () => {
  test.beforeAll(async () => {
    ensureManufacturingFixture()
  })

  test('plan to MO to production to QC completes successfully', async ({ page }) => {
    const moNumber = `MO-PW-E2E-${Date.now()}`

    await page.goto(`${BASE}/admin/manufacturing-orders/create`)
    await page.waitForLoadState('networkidle')
    await expect(page).not.toHaveURL(/login/)

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

    await page.goto(`${BASE}/admin/productions?tableAction=finished&tableActionRecord=${productionId}`)
    await page.waitForLoadState('networkidle')

    const finishConfirmButton = page.getByRole('button', { name: /^Konfirmasi$/ }).last()
    await expect(finishConfirmButton).toBeVisible({ timeout: 10000 })
    await finishConfirmButton.click({ force: true })

    await expect.poll(
      () => querySingleValue(`DB::table('productions')->where('id', ${productionId})->value('status')`),
      { timeout: 15000 },
    ).toBe('finished')

    const qualityControlId = querySingleValue(`DB::table('quality_controls')->where('from_model_type', 'App\\\\Models\\\\Production')->where('from_model_id', ${productionId})->value('id')`)
    expect(qualityControlId).toBeTruthy()

    await page.goto(`${BASE}/admin/quality-control-manufactures?tableAction=process_qc&tableActionRecord=${qualityControlId}`)
    await page.waitForLoadState('networkidle')

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

    await page.goto(`${BASE}/admin/manufacturing-orders/${manufacturingOrderId}`)
    await page.waitForLoadState('networkidle')

    await expect.poll(
      () => querySingleValue(`DB::table('manufacturing_orders')->where('id', ${manufacturingOrderId})->value('status')`),
      { timeout: 15000 },
    ).toBe('completed')
    await expect(page.getByRole('button', { name: /^Produksi$/ })).toBeHidden()
  })
})