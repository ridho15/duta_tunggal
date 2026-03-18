import { execSync } from 'node:child_process'
import { expect } from '@playwright/test'

export const BASE = 'http://localhost:8009'
export const FIXTURE = {
  supplierCode: 'SUPP001',
  supplierName: 'PT Supplier Utama',
  poNumber: 'PO-TEST-INV-B23',
  receiptLocked: 'PR-TEST-INV-LOCKED',
  receiptOpen: 'PR-TEST-INV-OPEN',
}

export function ensurePurchaseInvoiceFixture() {
  execSync('php scripts/setup_purchase_invoice_playwright_data.php', { stdio: 'inherit' })
}

export async function openCreatePage(page) {
  await page.goto(`${BASE}/admin/purchase-invoices/create`)
  await page.waitForLoadState('networkidle')
  await expect(page).not.toHaveURL(/login/)
}

export async function chooseFixtureSupplier(page) {
  const supplierCombobox = page.getByRole('combobox').first()
  await expect(supplierCombobox).toBeVisible()
  await supplierCombobox.click({ force: true })

  const supplierSearch = page.locator('input.choices__input--cloned[aria-label="Pilih salah satu opsi"]:visible').first()
  await expect(supplierSearch).toBeVisible()
  await supplierSearch.fill(FIXTURE.supplierCode)
  const supplierOption = page.locator('[role="option"]').filter({ hasText: FIXTURE.supplierCode }).first()
  await expect(supplierOption).toBeVisible()
  await supplierOption.click({ force: true })

  await expect(supplierCombobox).toContainText(FIXTURE.supplierCode)
  await page.waitForTimeout(1000)
}

export async function checkCheckboxByLabel(page, labelText) {
  const checkboxInLabel = page
    .locator('label')
    .filter({ hasText: labelText })
    .locator('input[type="checkbox"]')
    .first()

  if (await checkboxInLabel.count()) {
    return checkboxInLabel
  }

  return page.locator(`xpath=//label[contains(., "${labelText}")]//input[@type='checkbox']`).first()
}
