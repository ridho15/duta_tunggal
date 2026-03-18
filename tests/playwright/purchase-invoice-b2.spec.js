import { test, expect } from '@playwright/test'
import {
  BASE,
  FIXTURE,
  ensurePurchaseInvoiceFixture,
  openCreatePage,
  chooseFixtureSupplier,
  checkCheckboxByLabel,
} from './helpers/purchase-invoice-fixture'

const ERR = /Fatal error|Whoops!|Something went wrong/i

test.use({ storageState: 'playwright/.auth/user.json' })

test.beforeAll(async () => {
  ensurePurchaseInvoiceFixture()
})

function parseIdr(value) {
  if (!value) return 0
  const normalized = String(value).replace(/[^\d,-]/g, '').replace(/\./g, '').replace(',', '.')
  const num = Number(normalized)
  return Number.isFinite(num) ? num : 0
}

test('B2-a: purchase invoice page loads without errors', async ({ page }) => {
  await openCreatePage(page)

  const body = await page.textContent('body')
  expect(body).not.toMatch(ERR)
})

test('B2-b: PPN nominal follows DPP × rate after selecting fixture receipt', async ({ page }) => {
  await openCreatePage(page)

  const body = await page.textContent('body')
  expect(body).not.toMatch(ERR)

  await chooseFixtureSupplier(page)

  const poCheckbox = await checkCheckboxByLabel(page, FIXTURE.poNumber)
  await expect(poCheckbox).toBeVisible()
  await expect(poCheckbox).toBeEnabled()
  await poCheckbox.check({ force: true })

  await page.waitForTimeout(900)

  const openReceiptCheckbox = await checkCheckboxByLabel(page, FIXTURE.receiptOpen)
  await expect(openReceiptCheckbox).toBeVisible()
  await expect(openReceiptCheckbox).toBeEnabled()
  await openReceiptCheckbox.check({ force: true })

  await page.waitForTimeout(1200)

  const dppInput = page.locator('input[id*="dpp"]').first()
  const ppnRateInput = page.locator('input[id*="ppn_rate"]').first()
  const ppnAmountInput = page.locator('input[id*="ppn_amount"]').first()

  await expect(dppInput).toBeVisible()
  await expect(ppnRateInput).toBeVisible()
  await expect(ppnAmountInput).toBeVisible()

  const dpp = parseIdr(await dppInput.inputValue())
  const ppnRate = Number((await ppnRateInput.inputValue() || '0').replace(',', '.'))
  const ppnAmount = parseIdr(await ppnAmountInput.inputValue())

  expect(dpp).toBeGreaterThan(0)

  const expectedPpn = Math.round((dpp * ppnRate) / 100)
  expect(Math.abs(ppnAmount - expectedPpn)).toBeLessThanOrEqual(1)
})
