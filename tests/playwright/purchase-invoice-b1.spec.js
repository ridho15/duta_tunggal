import { test, expect } from '@playwright/test'
import {
  FIXTURE,
  ensurePurchaseInvoiceFixture,
  openCreatePage,
  chooseFixtureSupplier,
  chooseFixtureOrderRequest,
  checkCheckboxByLabel,
  clickCheckboxByLabel,
} from './helpers/purchase-invoice-fixture'

const ERR = /Fatal error|Whoops!|Something went wrong/i

test.use({ storageState: 'playwright/.auth/user.json' })

test.beforeAll(async () => {
  ensurePurchaseInvoiceFixture()
})

test('B1-a: purchase invoice item pricing fields are read-only/disabled', async ({ page }) => {
  await openCreatePage(page)

  const body = await page.textContent('body')
  expect(body).not.toMatch(ERR)
  expect(body).toContain('Harga mengikuti Purchase Receipt / Purchase Order dan tidak dapat diubah manual.')

  await chooseFixtureSupplier(page)
  await chooseFixtureOrderRequest(page)

  let poCheckbox = await checkCheckboxByLabel(page, FIXTURE.poNumber)
  await expect(poCheckbox).toBeVisible()
  if (!(await poCheckbox.isEnabled())) {
    poCheckbox = page.locator('input[type="checkbox"][wire\\:model\\.live="data.selected_purchase_orders"]:not([disabled])').first()
    await expect(poCheckbox).toBeVisible({ timeout: 5000 })
  }
  await expect(poCheckbox).toBeEnabled({ timeout: 5000 })
  await poCheckbox.click({ force: true })

  // Wait for receipt section to populate after Livewire re-render
  let openReceiptCheckbox = await checkCheckboxByLabel(page, FIXTURE.receiptOpen)
  if (!(await openReceiptCheckbox.isVisible().catch(() => false)) || !(await openReceiptCheckbox.isEnabled().catch(() => false))) {
    openReceiptCheckbox = page.locator('input[type="checkbox"][wire\\:model\\.live="data.selected_purchase_receipts"]:not([disabled])').first()
  }
  await expect(openReceiptCheckbox).toBeVisible({ timeout: 10000 })
  await expect(openReceiptCheckbox).toBeEnabled({ timeout: 5000 })
  await openReceiptCheckbox.click({ force: true })

  await page.waitForLoadState('networkidle')

  const qtyInput = page.locator('input[id*="invoiceItem"][id*="quantity"]').first()
  const priceInput = page.locator('input[id*="invoiceItem"][id*="price"]').first()
  const totalInput = page.locator('input[id*="invoiceItem"][id*="total"]').first()

  await expect(qtyInput).toBeVisible({ timeout: 5000 })
  await expect(priceInput).toBeVisible({ timeout: 5000 })
  await expect(totalInput).toBeVisible({ timeout: 5000 })

  // quantity and product are disabled (not editable)
  await expect(qtyInput).toBeDisabled()
  // price and total are readOnly (not disabled — value submits in form)
  const priceReadonly = await priceInput.getAttribute('readonly')
  const totalReadonly = await totalInput.getAttribute('readonly')
  expect(priceReadonly, 'price input should have readonly attribute').not.toBeNull()
  expect(totalReadonly, 'total input should have readonly attribute').not.toBeNull()
  const priceDisabled = await priceInput.getAttribute('disabled')
  const totalDisabled = await totalInput.getAttribute('disabled')
  expect(priceDisabled, 'price input should NOT have disabled attribute').toBeNull()
  expect(totalDisabled, 'total input should NOT have disabled attribute').toBeNull()

  const ppnAmountInput = page.locator('input[id*="ppn_amount"]').first()
  const invoiceTotalInput = page.locator('input[id*="total"]').filter({ hasNot: page.locator('[id*="invoiceItem"]') }).first()

  await expect(ppnAmountInput).toBeVisible()
  await expect(invoiceTotalInput).toBeVisible()

  const ppnAmountReadonly = await ppnAmountInput.getAttribute('readonly')
  const invoiceTotalReadonly = await invoiceTotalInput.getAttribute('readonly')
  expect(ppnAmountReadonly).not.toBeNull()
  expect(invoiceTotalReadonly).not.toBeNull()
})

test('B1-b: ppn_rate is non-editable on edit form', async ({ page }) => {
  await page.goto('/admin/purchase-invoices')
  await page.waitForLoadState('networkidle')

  const body = await page.textContent('body')
  expect(body).not.toMatch(ERR)

  const row = page.locator('tr, .fi-ta-row').filter({ hasText: 'INV-TEST-INV-LOCKED' }).first()
  await expect(row).toBeVisible()

  const hrefs = await row.locator('a[href*="/admin/purchase-invoices/"]').evaluateAll((els) =>
    els
      .map((el) => el.getAttribute('href'))
      .filter((href) => href && /\/admin\/purchase-invoices\/\d+$/.test(href))
  )
  expect(hrefs.length).toBeGreaterThan(0)

  await page.goto(hrefs[0])
  await page.waitForLoadState('networkidle')

  if (!page.url().includes('/edit')) {
    const editBtn = page.getByRole('button', { name: /Edit|Ubah/i }).first()
    if (await editBtn.count()) {
      await editBtn.click()
      await page.waitForLoadState('networkidle')
    } else {
      await page.goto(`${page.url().replace(/\/$/, '')}/edit`)
      await page.waitForLoadState('networkidle')
    }
  }

  const ppnRateInput = page.locator('input[id*="ppn_rate"]').first()
  await expect(ppnRateInput).toBeVisible()
  await expect(ppnRateInput).toBeDisabled()
})
