import { execSync } from 'node:child_process'
import { expect } from '@playwright/test'
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
  await page.goto('/admin/purchase-invoices/create')
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
  // Try getByRole first (most reliable — uses ARIA accessible name)
  const byRole = page.getByRole('checkbox', { name: labelText, exact: false })
  if (await byRole.count()) {
    return byRole.first()
  }

  // Fallback: find input inside a label that contains the text
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

/**
 * Selects a Filament CheckboxList option by label text using Livewire's $wire API.
 * Bypasses DOM click simulation which does not reliably trigger Alpine/Livewire
 * reactive updates. Directly calls $wire.set() just as the native change listener does.
 */
export async function clickCheckboxByLabel(page, labelText) {
  const success = await page.evaluate(async (text) => {
    // Find the checkbox input via its enclosing label
    const allLabels = Array.from(document.querySelectorAll('label'))
    const targetLabel = allLabels.find(l => l.textContent.trim().includes(text))
    if (!targetLabel) return false

    const input = targetLabel.querySelector('input[type="checkbox"]')
    if (!input) return false

    // Find the wire:model.* attribute (wire:model.live, etc.)
    const modelAttr = Array.from(input.attributes).find(a => a.name.startsWith('wire:model'))
    if (!modelAttr) {
      // No wire:model — fall back to native click
      targetLabel.click()
      return true
    }

    const modelPath = modelAttr.value   // e.g. "data.selected_purchase_orders"
    const checkboxValue = input.value    // e.g. "125"

    // Find the Livewire component wrapping this input
    const wireEl = input.closest('[wire\\:id]')
    if (!wireEl) return false

    // window.Livewire.find(id) returns the Alpine component whose $wire is the Livewire proxy
    const alpineComponent = window.Livewire.find(wireEl.getAttribute('wire:id'))
    if (!alpineComponent) return false

    // In Livewire v3, the API is $wire.$get / $wire.$set (with $ prefix)
    const wire = alpineComponent.$wire
    if (!wire) return false

    // Read current array then add this value; fall back to overwrite with [value] if $get unavailable
    let current = []
    try {
      const raw = typeof wire.$get === 'function' ? await wire.$get(modelPath) : null
      if (Array.isArray(raw)) current = raw
    } catch (e) { /* ignore — will overwrite */ }

    const strVal = String(checkboxValue)
    if (!current.map(String).includes(strVal)) {
      // $wire.$set triggers a server round-trip + DOM update (returns a Promise)
      const setFn = typeof wire.$set === 'function' ? wire.$set.bind(wire) : null
      if (setFn) {
        await setFn(modelPath, [...current, strVal])
      } else {
        // Ultimate fallback: native label click
        targetLabel.click()
      }
    }

    return true
  }, labelText)

  if (!success) {
    // Absolute fallback: native HTMLElement.click() via evaluate
    await page.evaluate((text) => {
      const label = Array.from(document.querySelectorAll('label')).find(l => l.textContent.includes(text))
      if (label) label.click()
    }, labelText)
  }
}
