import { test, expect } from '@playwright/test'
import {
  FIXTURE,
  ensurePurchaseInvoiceFixture,
  openCreatePage,
  chooseFixtureSupplier,
  checkCheckboxByLabel,
  clickCheckboxByLabel,
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

  let poCheckbox = await checkCheckboxByLabel(page, FIXTURE.poNumber)
  await expect(poCheckbox).toBeVisible()
  if (!(await poCheckbox.isEnabled())) {
    poCheckbox = page.locator('input[type="checkbox"][wire\\:model\\.live*="selected_purchase_orders"]:not([disabled])').first()
    await expect(poCheckbox).toBeVisible({ timeout: 5000 })
  }
  await expect(poCheckbox).toBeEnabled({ timeout: 5000 })
  await poCheckbox.click({ force: true })
  if (!(await poCheckbox.isChecked().catch(() => false))) {
    await clickCheckboxByLabel(page, FIXTURE.poNumber)
  }
  await expect(poCheckbox).toBeChecked({ timeout: 5000 })
  await page.waitForTimeout(1000)

  // Wait for receipt section to populate and find the open receipt by its label text
  // Note: Filament renders checkboxes with wire:model.live (not wire:model), so we use
  // label-based selection which is both more reliable and attribute-agnostic.
  const receiptLabel = page.locator('label').filter({ hasText: FIXTURE.receiptOpen })
  await expect(receiptLabel).toBeVisible({ timeout: 10000 })
  const openReceiptCheckbox = receiptLabel.locator('input[type="checkbox"]').first()
  await expect(openReceiptCheckbox).toBeEnabled({ timeout: 5000 })
  await openReceiptCheckbox.click({ force: true })
  if (!(await openReceiptCheckbox.isChecked().catch(() => false))) {
    await clickCheckboxByLabel(page, FIXTURE.receiptOpen)
  }
  await expect(openReceiptCheckbox).toBeChecked({ timeout: 5000 })

  await page.waitForLoadState('networkidle')

  const dppInput = page.locator('input[id*="dpp"]:visible').first()
  const ppnRateInput = page.locator('input[id*="ppn_rate"]:visible').first()
  const ppnAmountInput = page.locator('input[id*="ppn_amount"]:visible').first()

  await expect(dppInput).toBeVisible()
  await expect(ppnRateInput).toBeVisible()
  await expect(ppnAmountInput).toBeVisible()

  await expect.poll(async () => parseIdr(await dppInput.inputValue()), { timeout: 10000 }).toBeGreaterThan(0)

  const dpp = parseIdr(await dppInput.inputValue())
  const ppnRate = Number((await ppnRateInput.inputValue() || '0').replace(',', '.'))
  const ppnAmount = parseIdr(await ppnAmountInput.inputValue())

  expect(dpp).toBeGreaterThan(0)

  const expectedPpn = Math.round((dpp * ppnRate) / 100)
  expect(Math.abs(ppnAmount - expectedPpn)).toBeLessThanOrEqual(1)
})
