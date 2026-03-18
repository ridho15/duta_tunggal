import { test, expect } from '@playwright/test'
import {
  BASE,
  FIXTURE,
  ensureVendorPaymentFixture,
  openVendorPaymentCreatePage,
  selectFixturePaymentRequest,
} from './helpers/vendor-payment-fixture'

const ERR = /Fatal error|Whoops!|Something went wrong/i

test.use({ storageState: 'playwright/.auth/user.json' })

test.beforeAll(async () => {
  ensureVendorPaymentFixture()
})

test('C1-a: vendor-payments create page loads and shows Payment Request reference', async ({ page }) => {
  await openVendorPaymentCreatePage(page)

  const body = await page.textContent('body')
  expect(body).not.toMatch(ERR)
  expect(body).toContain('Payment Request (PR)')
  expect(body).toContain('Pilih Payment Request yang sudah disetujui. Invoice yang ditampilkan hanya berasal dari Payment Request yang dipilih.')
})

test('C1-b: selecting payment request auto-fills supplier', async ({ page }) => {
  await openVendorPaymentCreatePage(page)
  await selectFixturePaymentRequest(page)

  const supplierCombobox = page.getByRole('combobox').nth(1)
  await expect(supplierCombobox).toBeVisible()
  await expect(supplierCombobox).toContainText(FIXTURE.supplierCode)
})

test('C2-a: invoice checkbox list appears after payment request selected', async ({ page }) => {
  await openVendorPaymentCreatePage(page)
  await selectFixturePaymentRequest(page)

  const body = await page.textContent('body')
  expect(body).toContain('Pilih Invoice')

  const invoiceCheckboxes = page.locator('input[type="checkbox"][value]')
  await expect(invoiceCheckboxes.first()).toBeVisible()
  expect(await invoiceCheckboxes.count()).toBeGreaterThan(0)
})
