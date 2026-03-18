import { test, expect } from '@playwright/test'
import {
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

test('B1-a: purchase invoice item pricing fields are read-only/disabled', async ({ page }) => {
  await openCreatePage(page)

  const body = await page.textContent('body')
  expect(body).not.toMatch(ERR)
  expect(body).toContain('Harga mengikuti Purchase Receipt / Purchase Order dan tidak dapat diubah manual.')

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

  const qtyInput = page.locator('input[id*="invoiceItem"][id*="quantity"]').first()
  const priceInput = page.locator('input[id*="invoiceItem"][id*="price"]').first()
  const totalInput = page.locator('input[id*="invoiceItem"][id*="total"]').first()

  await expect(qtyInput).toBeVisible()
  await expect(priceInput).toBeVisible()
  await expect(totalInput).toBeVisible()

  await expect(qtyInput).toBeDisabled()
  await expect(priceInput).toBeDisabled()
  await expect(totalInput).toBeDisabled()

  const ppnAmountInput = page.locator('input[id*="ppn_amount"]').first()
  const invoiceTotalInput = page.locator('input[id*="total"]').filter({ hasNot: page.locator('[id*="invoiceItem"]') }).first()

  await expect(ppnAmountInput).toBeVisible()
  await expect(invoiceTotalInput).toBeVisible()

  const ppnAmountReadonly = await ppnAmountInput.getAttribute('readonly')
  const totalReadonly = await invoiceTotalInput.getAttribute('readonly')
  expect(ppnAmountReadonly).not.toBeNull()
  expect(totalReadonly).not.toBeNull()
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
