import { execSync } from 'node:child_process'
import { expect } from '@playwright/test'

export const BASE = 'http://localhost:8009'
export const FIXTURE = {
  paymentRequestNumber: 'PR-TEST-VP-APPROVED',
  supplierCode: 'SUPP001',
}

export function ensureVendorPaymentFixture() {
  execSync('php scripts/setup_purchase_invoice_playwright_data.php', { stdio: 'inherit' })
  execSync('php scripts/setup_vendor_payment_playwright_data.php', { stdio: 'inherit' })
}

export async function openVendorPaymentCreatePage(page) {
  await page.goto(`${BASE}/admin/vendor-payments/create`)
  await page.waitForLoadState('networkidle')
  await expect(page).not.toHaveURL(/login/)
}

export async function selectFixturePaymentRequest(page) {
  const prCombobox = page.getByRole('combobox').first()
  await expect(prCombobox).toBeVisible()
  await prCombobox.click({ force: true })

  const prSearch = page.locator('input.choices__input--cloned[aria-label="Pilih salah satu opsi"]:visible').first()
  await expect(prSearch).toBeVisible()
  await prSearch.fill(FIXTURE.paymentRequestNumber)

  const option = page.locator('[role="option"]').filter({ hasText: FIXTURE.paymentRequestNumber }).first()
  await expect(option).toBeVisible()
  await option.click({ force: true })

  await expect(prCombobox).toContainText(FIXTURE.paymentRequestNumber)
  await page.waitForTimeout(1400)
}
