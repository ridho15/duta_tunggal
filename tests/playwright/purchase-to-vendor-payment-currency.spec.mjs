import { test, expect } from '@playwright/test'
import { execSync } from 'node:child_process'

const USD_INV = 'INV-PLAY-USD'
const JPY_INV = 'INV-PLAY-JPY'

test.use({ storageState: 'playwright/.auth/user.json' })

test.beforeAll(() => {
  execSync('php scripts/setup_purchase_invoice_currency_playwright_data.php', { stdio: 'inherit' })
})

function parseRpAmount(text) {
  if (!text) return 0
  // extract first occurrence of Rp and following number
  const m = text.match(/Rp\s*([0-9\.,]+)/)
  let num = m ? m[1] : (text.match(/([0-9\.,]+)/)?.[1] || '')
  // remove dots and commas
  num = num.replace(/[\.,]/g, '')
  return parseInt(num || '0', 10)
}

async function findInvoiceRow(page, invoiceNumber) {
  await page.goto('/admin/purchase-invoices')
  await page.waitForLoadState('networkidle')
  const row = page.locator('tr, .fi-ta-row').filter({ hasText: invoiceNumber }).first()
  await expect(row).toBeVisible({ timeout: 10000 })
  return row
}

test('purchase→invoice→vendor payment flow shows converted IDR totals for USD and JPY invoices', async ({ page }) => {
  // Verify USD invoice exists and shows converted amount
  const usdRow = await findInvoiceRow(page, USD_INV)
  const usdText = await usdRow.textContent()
  expect(usdText).toContain(USD_INV)
  const usdAmount = parseRpAmount(usdText)
  expect(usdAmount).toBeGreaterThan(0)
  // expect 80000 (Rp 80.000)
  expect(usdAmount).toBe(80000)

  // Verify JPY invoice exists and shows converted amount
  const jpyRow = await findInvoiceRow(page, JPY_INV)
  const jpyText = await jpyRow.textContent()
  expect(jpyText).toContain(JPY_INV)
  const jpyAmount = parseRpAmount(jpyText)
  expect(jpyAmount).toBe(55000)
})
