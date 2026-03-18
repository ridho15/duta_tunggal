import { test, expect } from '@playwright/test'

const BASE = 'http://localhost:8009'
const ERR = /Fatal error|Whoops!|Something went wrong/i

test.use({ storageState: 'playwright/.auth/user.json' })

// ── J1: SuratJalan DO filter (approved only) ───────────────────────────────

test('J1-a: surat-jalans create page loads without error', async ({ page }) => {
  await page.goto(`${BASE}/admin/surat-jalans/create`)
  await page.waitForLoadState('networkidle')
  expect(page.url()).not.toMatch(/login/)
  const body = await page.textContent('body')
  expect(body).not.toMatch(ERR)
})

test('J1-b: surat-jalans list page loads without error', async ({ page }) => {
  await page.goto(`${BASE}/admin/surat-jalans`)
  await page.waitForLoadState('networkidle')
  const body = await page.textContent('body')
  expect(body).not.toMatch(ERR)
})

// ── A1: Order Request — no default supplier field ──────────────────────────

test('A1-a: order-requests create page loads without error', async ({ page }) => {
  await page.goto(`${BASE}/admin/order-requests/create`)
  await page.waitForLoadState('networkidle')
  expect(page.url()).not.toMatch(/login/)
  const body = await page.textContent('body')
  expect(body).not.toMatch(ERR)
})

test('A1-b: order-requests create has no visible supplier header field', async ({ page }) => {
  await page.goto(`${BASE}/admin/order-requests/create`)
  await page.waitForLoadState('networkidle')
  // The header-level supplier Select is now a Hidden field — should not be visible as a labeled input
  const supplierLabel = page.locator('label:has-text("Supplier (Default)")')
  await expect(supplierLabel).toHaveCount(0)
})

test('A1-c: order-requests list page loads without error', async ({ page }) => {
  await page.goto(`${BASE}/admin/order-requests`)
  await page.waitForLoadState('networkidle')
  const body = await page.textContent('body')
  expect(body).not.toMatch(ERR)
})

// ── F2: Unit Satuan field in repeaters ────────────────────────────────────

test('F2-a: sale-orders create page loads and shows Satuan label', async ({ page }) => {
  await page.goto(`${BASE}/admin/sale-orders/create`)
  await page.waitForLoadState('networkidle')
  const body = await page.textContent('body')
  expect(body).not.toMatch(ERR)
  // The repeater should have the Satuan field (even if hidden until product selected)
  const rows = await page.locator('label:has-text("Satuan")').count()
  expect(rows).toBeGreaterThanOrEqual(1)
})

test('F2-b: order-requests create page loads and shows Satuan label', async ({ page }) => {
  await page.goto(`${BASE}/admin/order-requests/create`)
  await page.waitForLoadState('networkidle')
  const body = await page.textContent('body')
  expect(body).not.toMatch(ERR)
  const rows = await page.locator('label:has-text("Satuan")').count()
  expect(rows).toBeGreaterThanOrEqual(1)
})

test('F2-c: purchase-orders create page loads and shows Satuan label after adding item', async ({ page }) => {
  await page.goto(`${BASE}/admin/purchase-orders/create`)
  await page.waitForLoadState('networkidle')
  const body = await page.textContent('body')
  expect(body).not.toMatch(ERR)
  // Click the repeater "Add" / "Tambah" button to reveal the first row
  const addBtn = page.locator('button:has-text("Tambah Order Items"), button:has-text("Tambahkan"), button:has-text("Add Item"), button:has-text("Tambah Item")').first()
  const addBtnCount = await addBtn.count()
  if (addBtnCount > 0) {
    await addBtn.click()
    await page.waitForTimeout(500)
  }
  const rows = await page.locator('label:has-text("Satuan")').count()
  expect(rows).toBeGreaterThanOrEqual(1)
})

test('F2-d: sale-orders list shows rupiah format', async ({ page }) => {
  await page.goto(`${BASE}/admin/sale-orders`)
  await page.waitForLoadState('networkidle')
  const body = await page.textContent('body')
  expect(body).not.toMatch(ERR)
  // Skip if no records
  const links = await page.evaluate(() =>
    [...document.querySelectorAll('a[href]')]
      .map(a => a.href)
      .filter(h => /\/admin\/sale-orders\/\d+/.test(h))
  )
  if (links.length === 0) {
    test.skip(true, 'Tidak ada data SO')
    return
  }
  const hasRupiah = body.includes('Rp') || /\d{1,3}(\.\d{3})+/.test(body)
  expect(hasRupiah).toBe(true)
})

test('F2-e: purchase-orders list shows rupiah format', async ({ page }) => {
  await page.goto(`${BASE}/admin/purchase-orders`)
  await page.waitForLoadState('networkidle')
  const body = await page.textContent('body')
  expect(body).not.toMatch(ERR)
  // Skip if no records
  const links = await page.evaluate(() =>
    [...document.querySelectorAll('a[href]')]
      .map(a => a.href)
      .filter(h => /\/admin\/purchase-orders\/\d+/.test(h))
  )
  if (links.length === 0) {
    test.skip(true, 'Tidak ada data PO')
    return
  }
  const hasRupiah = body.includes('Rp') || /\d{1,3}(\.\d{3})+/.test(body)
  expect(hasRupiah).toBe(true)
})
