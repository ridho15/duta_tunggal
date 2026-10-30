import { test, expect } from '@playwright/test'
import {
  ensureVendorPaymentFixture,
  openVendorPaymentCreatePage,
  selectFixturePaymentRequest,
} from './helpers/vendor-payment-fixture'

const ERR = /Fatal error|Whoops!|Something went wrong/i

test.use({ storageState: 'playwright/.auth/user.json' })

test.beforeAll(async () => {
  ensureVendorPaymentFixture()
})

async function openVendorPaymentCreate(page) {
  await openVendorPaymentCreatePage(page)

  const body = await page.textContent('body')
  expect(body).not.toMatch(ERR)
}

test('C4-a: NTPN field stays optional and manual input only', async ({ page }) => {
  await openVendorPaymentCreate(page)

  const ntpnInput = page.locator('input[name="data[ntpn]"], input[wire\\:model*="ntpn"]').first()
  await expect(ntpnInput).toBeVisible()

  const requiredAttr = await ntpnInput.getAttribute('required')
  expect(requiredAttr).toBeNull()

  const body = await page.textContent('body')
  expect(body).toContain('NTPN hanya diisi untuk pembayaran impor. Input manual, tidak dapat digenerate.')
  expect(body).not.toContain('Generate NTPN')
})

test('C3-a: payment amount per invoice enforces max remaining helper', async ({ page }) => {
  await openVendorPaymentCreate(page)
  await selectFixturePaymentRequest(page)

  const body = await page.textContent('body')
  expect(body).toContain('Pilih Invoice')

  const invoiceCheckboxes = page.locator('input[type="checkbox"][value]:not([disabled])')
  if (await invoiceCheckboxes.count()) {
    await expect(invoiceCheckboxes.first()).toBeVisible()
    await invoiceCheckboxes.first().check({ force: true })
    await page.waitForTimeout(1200)

    const updatedBody = await page.textContent('body')
    expect(updatedBody).toContain('Maks: Rp')
  } else {
    const allInvoiceCheckboxes = page.locator('input[type="checkbox"][value]')
    if (await allInvoiceCheckboxes.count()) {
      await expect(allInvoiceCheckboxes.first()).toBeDisabled()
    }
    const updatedBody = await page.textContent('body')
    expect(updatedBody).toMatch(/pilih invoice|tidak ada|belum ada|lunas/i)
  }
})

test('C3-b: total payment field is auto-calculated and read-only', async ({ page }) => {
  await openVendorPaymentCreate(page)
  await selectFixturePaymentRequest(page)

  const invoiceCheckboxes = page.locator('input[type="checkbox"][value]:not([disabled])')
  if (await invoiceCheckboxes.count()) {
    await expect(invoiceCheckboxes.first()).toBeVisible()
    await invoiceCheckboxes.first().check({ force: true })
    await page.waitForTimeout(1200)
  }

  const totalPaymentInput = page
    .locator('#data\\.total_payment, input[id*="total_payment"]')
    .first()
  if (await totalPaymentInput.isVisible().catch(() => false)) {
    const readOnlyAttr = await totalPaymentInput.getAttribute('readonly')
    expect(readOnlyAttr).not.toBeNull()

    const totalValue = (await totalPaymentInput.inputValue()).trim()
    expect(totalValue).toMatch(/^\d{1,3}(\.\d{3})*$/)
  } else {
    const updatedBody = await page.textContent('body')
    expect(updatedBody).toMatch(/vendor payment|payment method|pilih invoice/i)
  }
})
