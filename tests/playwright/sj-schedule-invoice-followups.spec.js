import { test, expect } from '@playwright/test'

const BASE = 'http://localhost:8009'
const ERR = /Fatal error|Whoops!|Something went wrong/i

test.use({ storageState: 'playwright/.auth/user.json' })

test('G1-SJ-a: surat-jalans create has Cabang field', async ({ page }) => {
  await page.goto(`${BASE}/admin/surat-jalans/create`)
  await page.waitForLoadState('networkidle')

  const body = await page.textContent('body')
  expect(page.url()).not.toMatch(/login/)
  expect(body).not.toMatch(ERR)
  expect(body).toContain('Cabang')
})

test('G1-SJ-b: surat-jalans create has cabang auto-fill helper', async ({ page }) => {
  await page.goto(`${BASE}/admin/surat-jalans/create`)
  await page.waitForLoadState('networkidle')

  await expect(page.locator('text=Diisi otomatis dari Delivery Order. Dapat diubah bila perlu.')).toBeVisible()
})

test('K3-a: delivery-schedules list page loads', async ({ page }) => {
  await page.goto(`${BASE}/admin/delivery-schedules`)
  await page.waitForLoadState('networkidle')

  const body = await page.textContent('body')
  expect(body).not.toMatch(ERR)
})

test('K3-b: delivery-schedules create includes status delivered option', async ({ page }) => {
  await page.goto(`${BASE}/admin/delivery-schedules/create`)
  await page.waitForLoadState('networkidle')

  const body = await page.textContent('body')
  expect(body).not.toMatch(ERR)
  expect(body).toContain('Selesai / Terkirim')
})

test('B1-a: purchase-invoices create shows non-editable pricing helper', async ({ page }) => {
  await page.goto(`${BASE}/admin/purchase-invoices/create`)
  await page.waitForLoadState('networkidle')

  const body = await page.textContent('body')
  expect(body).not.toMatch(ERR)
  expect(body).toContain('Harga mengikuti Purchase Receipt / Purchase Order dan tidak dapat diubah manual.')
})
