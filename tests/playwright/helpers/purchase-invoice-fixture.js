import { execSync } from 'node:child_process'
import { expect } from '@playwright/test'
export const FIXTURE = {
  supplierCode: 'SUPP001',
  supplierName: 'PT Supplier Utama',
  orderRequestNumber: 'OR-TEST-INV-B23',
  poNumber: 'PO-TEST-INV-B23',
  receiptLocked: 'PR-TEST-INV-LOCKED',
  receiptOpen: 'PR-TEST-INV-OPEN',
  invoiceNumber: 'INV-TEST-INV-LOCKED',
}

export function ensurePurchaseInvoiceFixture() {
  execSync('php scripts/setup_purchase_invoice_playwright_data.php', { stdio: 'inherit' })
}

function getFixtureData() {
  const output = execSync('php scripts/get_purchase_invoice_playwright_fixture.php', { encoding: 'utf8' })
  return JSON.parse(output)
}

async function setLivewireFormField(page, field, value) {
  await page.evaluate(async ({ field, value }) => {
    const wireEl = document.querySelector('form')?.closest('[wire\\:id]')
      ?? document.querySelector('main [wire\\:id]')
    if (!wireEl) {
      throw new Error('Livewire component not found')
    }

    const component = window.Livewire.find(wireEl.getAttribute('wire:id'))
    const wire = component?.$wire

    if (component && typeof component.set === 'function') {
      component.set(`data.${field}`, value)
      return
    }

    if (wire && typeof wire.$set === 'function') {
      wire.$set(`data.${field}`, value)
      return
    }

    throw new Error('Livewire set API is not available')
  }, { field, value })

  await page.waitForLoadState('networkidle')
  await page.waitForTimeout(500)
}

function getFixtureCabangName() {
  try {
    const output = execSync('php scripts/debug_purchase_invoice_fixture.php', { encoding: 'utf8' })
    const match = output.match(/^Cabang:\s+.*name=(.+)$/m)
    return match ? match[1].trim() : ''
  } catch {
    return ''
  }
}

async function selectFirstChoicesOption(page, labelText, searchTerm = '') {
  const wrapper = page.locator('.fi-fo-field-wrp').filter({ has: page.locator(`label:has-text("${labelText}")`) }).first()
  await expect(wrapper).toBeVisible()

  const choicesInner = wrapper.locator('.choices__inner')
  await choicesInner.click()

  if (searchTerm) {
    const searchInput = wrapper.locator('.choices__input--cloned, .choices__input[type="search"]').first()
    await expect(searchInput).toBeVisible()
    await searchInput.click({ force: true })
    await searchInput.type(searchTerm, { delay: 60 })
    await page.waitForTimeout(600)
  }

  const firstItem = wrapper
    .locator('.choices__list--dropdown .choices__item--choice:not(.choices__placeholder):not(.is-disabled)')
    .filter({ hasText: searchTerm || undefined })
    .first()
  await expect(firstItem).toBeVisible({ timeout: 10000 })
  await firstItem.click({ force: true })

  await page.waitForTimeout(500)

  if (await wrapper.locator('.choices__list--dropdown').isVisible().catch(() => false)) {
    await page.keyboard.press('Enter')
    await page.waitForTimeout(500)
  }
}

export async function openCreatePage(page) {
  await page.goto('/admin/purchase-invoices/create')
  await page.waitForLoadState('networkidle')
  await expect(page).not.toHaveURL(/login/)
}

export async function chooseFixtureSupplier(page) {
  const fixture = getFixtureData()
  await setLivewireFormField(page, 'selected_supplier', fixture.supplier_id)
}

export async function chooseFixtureCabang(page) {
  const wrapper = page.locator('.fi-fo-field-wrp').filter({ has: page.locator('label:has-text("Cabang")') }).first()

  if (!(await wrapper.isVisible().catch(() => false))) {
    return
  }

  const fixture = getFixtureData()
  await setLivewireFormField(page, 'cabang_id', fixture.cabang_id)
}

export function getFixtureInvoiceId() {
  try {
    const output = execSync('php scripts/get_purchase_invoice_playwright_fixture.php', { encoding: 'utf8' })
    const data = JSON.parse(output)

    return data.invoice_id || null
  } catch {
    return null
  }
}

export async function chooseFixtureOrderRequest(page) {
  await selectFirstChoicesOption(page, 'Order Request (OR)', FIXTURE.orderRequestNumber)
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
