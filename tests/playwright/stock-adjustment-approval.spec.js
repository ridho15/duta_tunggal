import { test, expect } from '@playwright/test'
import { execSync } from 'node:child_process'
import { confirmDialogAction, querySingleValue } from './helpers/manufacturing-fixture.js'

test.use({ storageState: 'playwright/.auth/user.json' })

const BASE = 'http://localhost:8009'
const ERR = /Fatal error|Whoops!|Something went wrong/i

function queryAdjustmentId(number) {
  return querySingleValue(`DB::table('stock_adjustments')->where('adjustment_number', '${number}')->value('id')`)
}

function queryAdjustmentStatus(number) {
  return querySingleValue(`DB::table('stock_adjustments')->where('adjustment_number', '${number}')->value('status')`)
}

function queryAdjustmentMovementCount(number) {
  const id = queryAdjustmentId(number)
  return Number(querySingleValue(`DB::table('stock_movements')->where('from_model_type', 'App\\Models\\StockAdjustment')->where('from_model_id', ${id})->count()`))
}

function queryAvailableStock(warehouseCode, rakCode) {
  return Number(querySingleValue(`DB::table('inventory_stocks')
    ->join('warehouses', 'warehouses.id', '=', 'inventory_stocks.warehouse_id')
    ->join('raks', 'raks.id', '=', 'inventory_stocks.rak_id')
    ->where('warehouses.kode', '${warehouseCode}')
    ->where('raks.code', '${rakCode}')
    ->value('inventory_stocks.qty_available')`))
}

async function openRowAction(page, recordNumber, actionLabel) {
  const row = page.locator('tr', { hasText: recordNumber }).first()
  await row.locator('button').first().click()
  await page.getByRole('button', { name: actionLabel }).click()
}

test.describe.serial('Stock adjustment approval guard', () => {
  test.beforeAll(() => {
    execSync('php scripts/setup_stock_adjustment_playwright_data.php', { stdio: 'inherit' })
  })

  test('approve draft increase adjustment updates stock once', async ({ page }) => {
    const adjustmentNumber = 'ADJ-PW-APPROVE-001'
    const adjustmentId = queryAdjustmentId(adjustmentNumber)
    expect(adjustmentId).toBeTruthy()

    await page.goto(`${BASE}/admin/stock-adjustments`)
    await page.waitForLoadState('networkidle')
    await openRowAction(page, adjustmentNumber, 'Approve')
    await confirmDialogAction(page, 'Konfirmasi')

    await expect.poll(() => queryAdjustmentStatus(adjustmentNumber), { timeout: 15000 }).toBe('approved')
    await expect.poll(() => queryAdjustmentMovementCount(adjustmentNumber), { timeout: 15000 }).toBe(1)
    await expect.poll(() => queryAvailableStock('GDG-PW-SA-001', 'RAK-PW-SA-001'), { timeout: 15000 }).toBe(15)

    const body = (await page.textContent('body')) || ''
    expect(body).toContain('Stock adjustment berhasil disetujui')
    expect(body).not.toMatch(ERR)
  })

  test('approve decrease adjustment with insufficient stock shows readable validation', async ({ page }) => {
    const adjustmentNumber = 'ADJ-PW-FAIL-001'
    const adjustmentId = queryAdjustmentId(adjustmentNumber)
    expect(adjustmentId).toBeTruthy()

    await page.goto(`${BASE}/admin/stock-adjustments`)
    await page.waitForLoadState('networkidle')
    await openRowAction(page, adjustmentNumber, 'Approve')
    await confirmDialogAction(page, 'Konfirmasi')

    await expect(page.locator('body')).toContainText('Stok tidak cukup')
    await expect(page.locator('body')).not.toContainText(ERR)
    await expect.poll(() => queryAdjustmentStatus(adjustmentNumber), { timeout: 15000 }).toBe('draft')
    await expect.poll(() => queryAdjustmentMovementCount(adjustmentNumber), { timeout: 15000 }).toBe(0)
    await expect.poll(() => queryAvailableStock('GDG-PW-SA-002', 'RAK-PW-SA-002'), { timeout: 15000 }).toBe(3)
  })
})