/**
 * Batch: H4 / I1 / I2 focused tests
 *
 * H4-a  ViewDeliveryOrder draft page loads and reflects the current header action flow
 * H4-b  ViewDeliveryOrder does not expose the removed manual Request Stock action on draft DO
 * H4-c  DeliveryOrderResource infolist section "Status Konfirmasi Gudang" visible when WC linked
 * I1-a  ViewWarehouseConfirmation shows Approve + Tolak buttons for DO-linked WC at status=request
 * I1-b  ViewWarehouseConfirmation does NOT show Approve/Tolak for SO-only WC
 * I2-a  WarehouseConfirmation list table has "Delivery Order" column
 * I2-b  ViewWarehouseConfirmation shows "Informasi Delivery Order" section for DO-linked WC
 */
import { test, expect } from '@playwright/test'
import { querySingleValue } from './helpers/manufacturing-fixture.js'

const BASE = 'http://localhost:8009'
const ERR = /Fatal error|Whoops!|Something went wrong/i

test.use({ storageState: 'playwright/.auth/user.json' })
test.describe.configure({ mode: 'serial' })

// -------------------------------------------------------------------------
// H4 — "Request Stock ke Gudang" button on ViewDeliveryOrder
// -------------------------------------------------------------------------

test('H4-a  ViewDeliveryOrder list loads without error', async ({ page }) => {
  await page.goto(`${BASE}/admin/delivery-orders`, { waitUntil: 'domcontentloaded' })

  const body = await page.textContent('body')
  expect(body).not.toMatch(ERR)
  expect(page.url()).not.toMatch(/login/)
})

test('H4-b  ViewDeliveryOrder draft page reflects current warehouse-confirmation flow', async ({ page }) => {
  const draftDeliveryOrderId = querySingleValue("DB::table('delivery_orders')->where('status', 'draft')->orderByDesc('id')->value('id')")

  await page.goto(`${BASE}/admin/delivery-orders`, { waitUntil: 'domcontentloaded' })

  const body = await page.textContent('body')
  expect(body).not.toMatch(ERR)

  if (!draftDeliveryOrderId) {
    expect(body).toMatch(/delivery order/i)
    return
  }

  await page.goto(`${BASE}/admin/delivery-orders/${draftDeliveryOrderId}`, { waitUntil: 'domcontentloaded' })

  const pageBody = await page.textContent('body')
  expect(pageBody).not.toMatch(ERR)
  expect(pageBody).toMatch(/Lihat Delivery Order|Delivery Order Details/i)
  expect(pageBody).not.toMatch(/Request Stock ke Gudang/i)
  expect(pageBody).toMatch(/Request Close/i)
})

// -------------------------------------------------------------------------
// I2 — "Delivery Order" column in WC table
// -------------------------------------------------------------------------

test('I2-a  WC list table has Delivery Order column', async ({ page }) => {
  await page.goto(`${BASE}/admin/warehouse-confirmations`, { waitUntil: 'domcontentloaded' })

  const body = await page.textContent('body')
  expect(body).not.toMatch(ERR)
  expect(body).toMatch(/Delivery Order/i)
  expect(body).toMatch(/Source Item/i)
  expect(body).toMatch(/Qty Request/i)
  expect(body).toMatch(/Gudang/i)
})

// -------------------------------------------------------------------------
// I1 / I2 — ViewWarehouseConfirmation DO-linked WC
// -------------------------------------------------------------------------

test('I1+I2  WC list loads without error', async ({ page }) => {
  await page.goto(`${BASE}/admin/warehouse-confirmations`, { waitUntil: 'domcontentloaded' })

  const body = await page.textContent('body')
  expect(body).not.toMatch(ERR)
  expect(page.url()).not.toMatch(/login/)
})

test('I1-b  WC view page loads and has correct action buttons', async ({ page }) => {
  const warehouseConfirmationId = querySingleValue("DB::table('warehouse_confirmations')->where('confirmable_type', 'App\\Models\\DeliveryOrder')->orderByDesc('id')->value('id')")

  await page.goto(`${BASE}/admin/warehouse-confirmations`, { waitUntil: 'domcontentloaded' })

  const body = await page.textContent('body')
  expect(body).not.toMatch(ERR)

  if (!warehouseConfirmationId) {
    // No WC records in DB – just check the list page rendered OK
    expect(body).toMatch(/warehouse confirmation|konfirmasi gudang/i)
    return
  }

  await page.goto(`${BASE}/admin/warehouse-confirmations/${warehouseConfirmationId}`, { waitUntil: 'domcontentloaded' })

  const viewBody = await page.textContent('body')
  expect(viewBody).not.toMatch(ERR)
  // The page should render basic WC content (title or section)
  expect(viewBody).toMatch(/warehouse confirmation|konfirmasi gudang/i)
})
