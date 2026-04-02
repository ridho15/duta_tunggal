import { test, expect } from '@playwright/test'
import { execSync } from 'node:child_process'
import { confirmDialogAction, querySingleValue } from './helpers/manufacturing-fixture.js'

test.use({ storageState: 'playwright/.auth/user.json' })

const BASE = 'http://localhost:8009'
const ERR = /Fatal error|Whoops!|Something went wrong/i

function queryTransferId(number) {
  return querySingleValue(`DB::table('stock_transfers')->where('transfer_number', '${number}')->value('id')`)
}

function queryTransferStatus(number) {
  return querySingleValue(`DB::table('stock_transfers')->where('transfer_number', '${number}')->value('status')`)
}

function queryMovementCount(number) {
  const id = queryTransferId(number)
  return Number(querySingleValue(`DB::table('stock_movements')->where('from_model_type', 'App\\Models\\StockTransfer')->where('from_model_id', ${id})->count()`))
}

test.describe.serial('Stock transfer approval guard', () => {
  test.beforeAll(() => {
    execSync('php scripts/setup_stock_transfer_playwright_data.php', { stdio: 'inherit' })
  })

  test('approve request transfer moves stock exactly once', async ({ page }) => {
    const transferNumber = 'ST-PW-APPROVE-001'
    const transferId = queryTransferId(transferNumber)
    expect(transferId).toBeTruthy()

    await page.goto(`${BASE}/admin/stock-transfers?tableAction=approve&tableActionRecord=${transferId}`)
    await page.waitForLoadState('networkidle')
    await confirmDialogAction(page, 'Konfirmasi')

    await expect.poll(() => queryTransferStatus(transferNumber), { timeout: 15000 }).toBe('Approved')
    await expect.poll(() => queryMovementCount(transferNumber), { timeout: 15000 }).toBe(2)

    const body = (await page.textContent('body')) || ''
    expect(body).not.toMatch(ERR)
    expect(body).toMatch(/berhasil diapprove/i)
  })

  test('approve transfer with insufficient stock shows readable validation message', async ({ page }) => {
    const transferNumber = 'ST-PW-FAIL-001'
    const transferId = queryTransferId(transferNumber)
    expect(transferId).toBeTruthy()

    await page.goto(`${BASE}/admin/stock-transfers?tableAction=approve&tableActionRecord=${transferId}`)
    await page.waitForLoadState('networkidle')
    await confirmDialogAction(page, 'Konfirmasi')

    await expect(page.locator('body')).toContainText('Stok tidak cukup')
    await expect(page.locator('body')).not.toContainText(ERR)
    await expect.poll(() => queryTransferStatus(transferNumber), { timeout: 15000 }).toBe('Request')
    await expect.poll(() => queryMovementCount(transferNumber), { timeout: 15000 }).toBe(0)
  })
})
