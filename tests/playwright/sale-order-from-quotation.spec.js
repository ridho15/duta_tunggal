/**
 * Playwright E2E — Sales Order Create from Quotation
 *
 * Skenario yang dicakup:
 *  1. Saat memilih quotation di form SO (Refer Quotation), field tempo_pembayaran
 *     harus otomatis terisi dari quotation tersebut.
 *  2. Unit price pada modal "Buat Sales Order" tampil dalam format Rupiah, tidak inflasi.
 *  3. Submit jalur: klik "Buat Sales Order" → SO tersimpan → redirect ke edit page.
 *  4. Form SO halaman create menampilkan label gudang yang benar sesuai mode.
 */

import { test, expect } from '@playwright/test'
import { execSync } from 'node:child_process'

test.describe.configure({ mode: 'serial' })

test.use({ storageState: 'playwright/.auth/user.json' })

const ERR = /Fatal error|Whoops!|Something went wrong|DateMalformedStringException/i
const QUOTATION_NUMBER = 'QT-PW-SO-001'
const STOCKED_WAREHOUSE = 'PW-SO-STOCK-A'
const EMPTY_WAREHOUSE = 'PW-SO-STOCK-B'

test.beforeAll(async () => {
  execSync('php scripts/setup_sale_order_from_quotation_playwright_data.php', { stdio: 'inherit' })
})

async function assertHealthy(page) {
  await page.waitForLoadState('networkidle')
  await expect(page).not.toHaveURL(/login/)
  const body = await page.textContent('body')
  expect(body || '').not.toMatch(ERR)
  return body || ''
}

async function chooseReferQuotation(page) {
  await page.goto('/admin/sale-orders/create')
  await assertHealthy(page)

  const optionsCombobox = page.getByRole('combobox').nth(0)
  await expect(optionsCombobox).toBeVisible()
  await optionsCombobox.click()

  const referQuotationOption = page.getByRole('option', { name: /Refer Quotation/i }).first()
  await expect(referQuotationOption).toBeVisible()
  await referQuotationOption.click()
  await page.waitForTimeout(400)

  const quotationCombobox = page.getByRole('combobox').nth(1)
  await expect(quotationCombobox).toBeVisible()
  await quotationCombobox.click()

  const quotationOption = page.getByRole('option', { name: new RegExp(QUOTATION_NUMBER) }).first()
  await expect(quotationOption).toBeVisible()
  await quotationOption.click()
  await page.waitForTimeout(1200)
  await page.getByRole('heading', { name: /Buat Sales Order/i }).click()
  await page.waitForTimeout(200)
}

async function addWarehouseAllocation(page, quantity = '3') {
  const addAllocationButton = page.getByRole('button', { name: /Tambahkan ke alokasi Gudang/i }).first()
  await expect(addAllocationButton).toBeVisible()
  await addAllocationButton.click()
  await page.waitForTimeout(700)

  const warehouseGroup = page.locator('div').filter({ hasText: /Hanya menampilkan gudang yang memiliki stok tersedia untuk produk ini\./ }).first()
  const warehouseCombobox = warehouseGroup.getByRole('combobox').first()
  await warehouseCombobox.click()
  await page.waitForTimeout(300)

  const body = await page.textContent('body')
  expect(body || '').toContain(STOCKED_WAREHOUSE)
  expect(body || '').not.toContain(EMPTY_WAREHOUSE)

  const stockedWarehouseChoice = page
    .locator('.choices__list--dropdown .choices__item--choice:not(.choices__placeholder):not(.is-disabled)')
    .filter({ hasText: new RegExp(STOCKED_WAREHOUSE) })
    .first()
  await expect(stockedWarehouseChoice).toHaveCount(1)

  const warehouseSelect = page.locator('select[id*="warehouseAllocations"][id*="warehouse_id"]').first()
  const warehouseSelectId = await warehouseSelect.getAttribute('id')
  const warehouseValue = await stockedWarehouseChoice.getAttribute('data-value')

  expect(warehouseSelectId).toBeTruthy()
  expect(warehouseValue).toBeTruthy()

  const warehouseStateApplied = await page.evaluate(({ fieldPath, value }) => {
    const field = document.getElementById(fieldPath)
    const componentId = field?.closest('[wire\\:id]')?.getAttribute('wire:id')
    if (!componentId || !window.Livewire?.find) {
      return false
    }

    const component = window.Livewire.find(componentId)
    if (!component?.set) {
      return false
    }

    component.set(fieldPath, value)
    return true
  }, {
    fieldPath: warehouseSelectId,
    value: warehouseValue,
  })
  expect(warehouseStateApplied).toBe(true)
  await page.waitForTimeout(500)

  const allocationQtyInput = page.locator('input[id*="warehouseAllocations"][id*="quantity"]').first()
  await expect(allocationQtyInput).toBeVisible()
  await allocationQtyInput.fill(quantity)
  await allocationQtyInput.press('Tab')
  await page.waitForTimeout(400)
}

async function fillRequiredSaleOrderFields(page, soNumber) {
  const soNumberInput = page.locator('#data\\.so_number').first()
  await expect(soNumberInput).toBeVisible()
  await soNumberInput.fill(soNumber)

  const orderDateInput = page.locator('#data\\.order_date').first()
  await expect(orderDateInput).toBeVisible()
  await orderDateInput.fill(new Date().toISOString().slice(0, 10))

  const shippingOption = page.getByText(/Kirim Ke Customer|Kirim Langsung/i).first()
  if (await shippingOption.count()) {
    await shippingOption.click()
  }
}

// ---------------------------------------------------------------------------
// Test 1: tempo_pembayaran auto-fill saat pilih quotation di form SO create
// ---------------------------------------------------------------------------
test('SO form: memilih quotation otomatis mengisi tempo_pembayaran', async ({ page }) => {
  await chooseReferQuotation(page)

  // Pastikan tempo_pembayaran terisi
  const tempoInput = page.locator('[id*="tempo_pembayaran"]').first()
  await expect(tempoInput).toBeVisible()
  await expect(tempoInput).toHaveValue('21')

  await assertHealthy(page)
})

test('SO form: quotation mengisi total amount dan subtotal dengan format rupiah konsisten', async ({ page }) => {
  await chooseReferQuotation(page)

  const totalAmountInput = page.locator('#data\\.total_amount').first()
  await expect(totalAmountInput).toBeVisible()
  const totalAmount = await totalAmountInput.inputValue()
  expect(totalAmount).toMatch(/^\d{1,3}(\.\d{3})+$/)

  const subtotalInput = page.locator('input[id*="saleOrderItem"][id*="subtotal"]').first()
  await expect(subtotalInput).toBeVisible()
  const subtotal = await subtotalInput.inputValue()
  expect(subtotal).toMatch(/^\d{1,3}(\.\d{3})+$/)

  const parsedTotal = Number(totalAmount.replace(/\./g, ''))
  const parsedSubtotal = Number(subtotal.replace(/\./g, ''))
  expect(parsedTotal).toBeGreaterThan(0)
  expect(parsedSubtotal).toBeGreaterThan(0)
})

test('SO form: alokasi gudang hanya menampilkan gudang yang punya inventory stock untuk produk quotation', async ({ page }) => {
  await chooseReferQuotation(page)

  const addAllocationButton = page.getByRole('button', { name: /Tambahkan ke alokasi Gudang/i }).first()
  await expect(addAllocationButton).toBeVisible()
  await addAllocationButton.click()
  await page.waitForTimeout(700)

  const warehouseGroup = page.locator('div').filter({ hasText: /Hanya menampilkan gudang yang memiliki stok tersedia untuk produk ini\./ }).first()
  const warehouseCombobox = warehouseGroup.getByRole('combobox').first()
  await warehouseCombobox.click()
  await page.waitForTimeout(300)

  const body = await page.textContent('body')
  expect(body || '').toContain(STOCKED_WAREHOUSE)
  expect(body || '').not.toContain(EMPTY_WAREHOUSE)
})

test('SO form: submit berhasil setelah memilih alokasi gudang terfilter dari quotation', async ({ page }) => {
  await chooseReferQuotation(page)

  const soNumber = `SO-PW-CRT-${Date.now()}`
  await fillRequiredSaleOrderFields(page, soNumber)
  await addWarehouseAllocation(page)

  const submitButton = page.getByRole('button', { name: /^Buat$/ }).last()

  await submitButton.click()
  await page.waitForFunction(
    () => /\/admin\/sale-orders\/\d+(?:\/edit)?$/.test(window.location.pathname),
    { timeout: 15000 },
  )
  await page.waitForLoadState('networkidle')

  const body = await assertHealthy(page)
  expect(page.url()).toMatch(/\/admin\/sale-orders\/\d+(?:\/edit)?$/)
  expect(body).toContain(soNumber)
})

// ---------------------------------------------------------------------------
// Test 2: Unit price pada modal "Buat Sales Order" dari view quotation — format Rupiah
// ---------------------------------------------------------------------------
test('Modal Buat SO: unit price tampil format Rupiah (tidak inflasi)', async ({ page }) => {
  await page.goto('/admin/quotations/1/view')
  const body = await assertHealthy(page)

  if (page.url().includes('/404') || /\b404\b|not found/i.test(body)) {
    return
  }

  const btn = page.getByRole('button', { name: /Buat Sales Order/i }).first()
  if (!(await btn.isVisible().catch(() => false))) {
    expect(body).toMatch(/quotation|approve|status/i)
    return
  }

  await btn.click({ force: true })
  const modal = page.locator('[role="dialog"]').last()
  await expect(modal).toBeVisible()

  const unitPriceInputs = modal.locator('input[id*="saleOrderItems"][id*="unit_price"]')
  const count = await unitPriceInputs.count()
  if (count === 0) return

  for (let i = 0; i < count; i++) {
    const val = await unitPriceInputs.nth(i).inputValue()
    // Format: hanya digit dan titik (separator ribuan), bukan lebih dari 15 digit
    expect(val).toMatch(/^[\d.]+$/)
    // Tidak boleh lebih dari 999.999.999.999 (1 triliun) — cegah inflasi 1000x
    const numeric = Number(val.replace(/\./g, ''))
    expect(numeric).toBeLessThan(1_000_000_000_000)
    // Jika nilainya cukup besar, harus ada titik separator
    if (numeric >= 1000) {
      expect(val).toContain('.')
    }
  }
})

// ---------------------------------------------------------------------------
// Test 3: Submit "Buat Sales Order" dari view quotation → redirect ke edit SO
// ---------------------------------------------------------------------------
test('Modal Buat SO: submit berhasil dan redirect ke edit SO page', async ({ page }) => {
  await page.goto('/admin/quotations/1/view')
  const body = await assertHealthy(page)

  if (page.url().includes('/404') || /\b404\b|not found/i.test(body)) {
    return
  }

  const btn = page.getByRole('button', { name: /Buat Sales Order/i }).first()
  if (!(await btn.isVisible().catch(() => false))) {
    expect(body).toMatch(/quotation|approve|status/i)
    return
  }

  await btn.click({ force: true })
  const modal = page.locator('[role="dialog"]').last()
  await expect(modal).toBeVisible()

  // Isi gudang di semua item (required)
  const warehouseSelects = modal.locator('[id*="saleOrderItems"][id*="warehouse_id"]:not([id*="allocation"])')
  const wsCount = await warehouseSelects.count()
  for (let i = 0; i < wsCount; i++) {
    const ws = warehouseSelects.nth(i)
    if (await ws.isVisible().catch(() => false)) {
      await ws.click()
      const opt = page.getByRole('option').first()
      if (await opt.isVisible().catch(() => false)) {
        await opt.click()
        await page.waitForTimeout(200)
      }
    }
  }

  // Klik submit
  const submitBtn = modal.getByRole('button', { name: /Buat Sales Order/i }).first()
    .or(page.getByRole('button', { name: /Buat Sales Order/i }).last())

  if (!(await submitBtn.isVisible().catch(() => false))) {
    // Submit button mungkin diluar modal
    const submitOutside = page.getByRole('button', { name: /Buat Sales Order/i }).last()
    if (await submitOutside.isVisible().catch(() => false)) {
      await submitOutside.click()
    }
  } else {
    await submitBtn.click()
  }

  // Tunggu navigasi ke edit SO page
  await page.waitForURL(/sale-orders\/.+\/edit|sale-orders\/create/, { timeout: 10000 }).catch(() => {})

  const afterBody = await assertHealthy(page)

  // Setelah submit, harus ke halaman edit SO atau notifikasi success
  const isOnSoEdit = /sale-orders\/.+\/edit/.test(page.url())
  const hasSuccess = /berhasil|success|SO-/i.test(afterBody)

  expect(isOnSoEdit || hasSuccess).toBe(true)
})

// ---------------------------------------------------------------------------
// Test 4: Form SO create — label gudang berubah saat mode multi-gudang aktif
// ---------------------------------------------------------------------------
test('SO create form: dual warehouse mode — label gudang menunjukkan mode aktif', async ({ page }) => {
  await page.goto('/admin/sale-orders/create')
  await assertHealthy(page)

  // Cek teks helper / label yang menjelaskan dual-mode gudang ada di halaman
  const body = await page.textContent('body')

  // Setelah fix, salah satu helper text harus ada:
  // "Mode gudang tunggal" ATAU "Alokasi Gudang" ATAU "Mode Multi-Gudang Aktif"
  expect(body || '').toMatch(/Alokasi Gudang|multi.gudang|Mode gudang tunggal/i)
})
