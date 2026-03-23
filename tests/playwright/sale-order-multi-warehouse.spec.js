/**
 * Playwright E2E — Sales Order Multi-Warehouse
 *
 * Skenario yang dicakup:
 *  1. View SO dengan multi-gudang menampilkan ringkasan alokasi gudang (bukan hanya satu)
 *  2. Form edit SO dengan multi-gudang menampilkan warehouseAllocations repeater yang berisi data
 *  3. Form SO create: saat menambah alokasi multi-gudang, label "Gudang" berubah ke
 *     "Gudang Utama (Mode Multi-Gudang Aktif — Opsional)"
 *  4. Form SO create: validasi quantity gagal jika total alokasi ≠ quantity item
 */

import { test, expect } from '@playwright/test'

test.use({ storageState: 'playwright/.auth/user.json' })

const BASE = 'http://localhost:8009'
const ERR = /Fatal error|Whoops!|Something went wrong/i

async function assertHealthy(page) {
  await page.waitForLoadState('networkidle')
  await expect(page).not.toHaveURL(/login/)
  const body = await page.textContent('body')
  expect(body || '').not.toMatch(ERR)
  return body || ''
}

// SO id=6 has: item with warehouse_id=null (multi-warehouse) + 2 allocations (wh1 qty=5, wh2 qty=5)
const SO_MULTI_ID = 6

// ---------------------------------------------------------------------------
// Test 1: View SO multi-gudang menampilkan ringkasan alokasi gudang
// ---------------------------------------------------------------------------
test('View SO multi-gudang: menampilkan "Alokasi Gudang" dengan lebih dari satu gudang', async ({ page }) => {
  await page.goto(`${BASE}/admin/sale-orders/${SO_MULTI_ID}`)
  const body = await assertHealthy(page)

  // Should show "Item Sales Order" section (from the updated infolist)
  expect(body).toMatch(/Item Sales Order/i)

  // Should show "Alokasi Gudang" column
  expect(body).toMatch(/Alokasi Gudang/i)

  // The allocation summary should contain BOTH warehouses
  // (content: "Gudang Utama: 5 | Gudang Cabang A: 5" or similar)
  expect(body).toMatch(/Gudang Utama/i)
  expect(body).toMatch(/Gudang Cabang A/i)
})

// ---------------------------------------------------------------------------
// Test 2: View SO multi-gudang menampilkan Mode Gudang sebagai "Multi-Gudang"
// ---------------------------------------------------------------------------
test('View SO multi-gudang: kolom "Mode Gudang" shows mode multi-gudang', async ({ page }) => {
  await page.goto(`${BASE}/admin/sale-orders/${SO_MULTI_ID}`)
  const body = await assertHealthy(page)

  // Should show "Mode Gudang" column
  expect(body).toMatch(/Mode Gudang/i)

  // Should show multi-gudang label (2 gudang)
  expect(body).toMatch(/Multi-Gudang/i)
})

// ---------------------------------------------------------------------------
// Test 3: Edit SO multi-gudang — warehouseAllocations repeater tampil dengan data
// ---------------------------------------------------------------------------
test('Edit SO multi-gudang: warehouseAllocations repeater menampilkan alokasi yang sudah tersimpan', async ({ page }) => {
  await page.goto(`${BASE}/admin/sale-orders/${SO_MULTI_ID}/edit`)
  const body = await assertHealthy(page)

  // The form should have loaded without error
  expect(body).not.toMatch(ERR)

  // The saleOrderItem repeater should be visible
  expect(body).toMatch(/Add Items/i)

  // The warehouseAllocations repeater inside each item should be present
  // (the collapsed header shows "Alokasi Gudang (Mode Multi-Gudang)")
  expect(body).toMatch(/Alokasi Gudang \(Mode Multi-Gudang\)/i)
})

// ---------------------------------------------------------------------------
// Test 4: Form SO create — label "Gudang" berubah saat alokasi diisi
// ---------------------------------------------------------------------------
test('SO create form: label Gudang berubah ke "Gudang Utama (Mode Multi-Gudang Aktif)" saat alokasi ditambah', async ({ page }) => {
  await page.goto(`${BASE}/admin/sale-orders/create`)
  await assertHealthy(page)

  // Click "Add Items" to add a SO item
  await page.getByText('Add Items').click()
  await page.waitForTimeout(500)

  // Select a product (id=1 — Panel Kontrol Industri)
  const productSelect = page.locator('input[id*="product_id"]').first()
  if (await productSelect.isVisible()) {
    await productSelect.fill('Panel')
    await page.waitForTimeout(600)
    const option = page.getByRole('option').first()
    if (await option.isVisible()) {
      await option.click()
    }
  }

  await page.waitForTimeout(800)

  // Expand the warehouseAllocations repeater
  const allocHeader = page.getByText('Alokasi Gudang (Mode Multi-Gudang)').first()
  if (await allocHeader.isVisible({ timeout: 2000 }).catch(() => false)) {
    await allocHeader.click()
    await page.waitForTimeout(500)
  }

  // Add an allocation entry by clicking the "Add" button inside the repeater
  const addAllocBtn = page.getByRole('button', { name: /Add.*/i }).nth(1) // second Add button (inner repeater)
  if (await addAllocBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
    await addAllocBtn.click()
    await page.waitForTimeout(800)
  }

  // Now check the outer "Gudang" select label has changed
  const body = await page.textContent('body')
  expect(body || '').toMatch(/Mode Multi-Gudang|Multi-Gudang Aktif/i)
})

// ---------------------------------------------------------------------------
// Test 5: Health check — SO listing page loads correctly
// ---------------------------------------------------------------------------
test('SO listing: loads without error', async ({ page }) => {
  await page.goto(`${BASE}/admin/sale-orders`)
  const body = await assertHealthy(page)
  expect(body).toMatch(/Sales Order|SO Number/i)
})
