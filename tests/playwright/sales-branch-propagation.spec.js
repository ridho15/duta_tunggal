import { test, expect } from '@playwright/test'

const BASE = 'http://localhost:8009'
const ERR = /Fatal error|Whoops!|Something went wrong/i

test.use({ storageState: 'playwright/.auth/user.json' })

test('G1-a: sale-orders create page loads and shows Quotation + Cabang fields', async ({ page }) => {
  await page.goto(`${BASE}/admin/sale-orders/create`)
  await page.waitForLoadState('networkidle')

  expect(page.url()).not.toMatch(/login/)
  const body = await page.textContent('body')
  expect(body).not.toMatch(ERR)

  expect(body).toContain('Refer Quotation')
  expect(body).toContain('Cabang')
})

test('G1-b: delivery-orders create page contains auto-cabang helper', async ({ page }) => {
  await page.goto(`${BASE}/admin/delivery-orders/create`)
  await page.waitForLoadState('networkidle')

  expect(page.url()).not.toMatch(/login/)
  const body = await page.textContent('body')
  expect(body).not.toMatch(ERR)

  await expect(page.locator('text=Diisi otomatis dari Sales Order. Dapat diubah bila perlu.')).toBeVisible()
})

test('G1-c: sale-orders create can auto-fill cabang from selected quotation (if quotation data exists)', async ({ page }) => {
  await page.goto(`${BASE}/admin/sale-orders/create`)
  await page.waitForLoadState('networkidle')

  const body = await page.textContent('body')
  expect(body).not.toMatch(ERR)

  // Switch to "Refer Quotation" mode
  const modeSet = await page.evaluate(() => {
    const select = document.querySelector('select[name="data.options_form"]')
    if (!select) return false
    select.value = '2' // Refer Quotation
    select.dispatchEvent(new Event('input', { bubbles: true }))
    select.dispatchEvent(new Event('change', { bubbles: true }))
    return true
  })
  if (!modeSet) {
    test.skip(true, 'Select options_form tidak ditemukan')
    return
  }
  await page.waitForTimeout(700)

  // Open quotation combobox and select first real option if available
  const quotationCombo = page.locator('input[placeholder="Pilih salah satu opsi"]').first()
  if ((await quotationCombo.count()) === 0) {
    test.skip(true, 'Komponen Quotation combobox tidak ditemukan di UI saat ini')
    return
  }

  await quotationCombo.click({ force: true })
  await page.waitForTimeout(500)

  const realOptions = page.locator('[role="option"]').filter({ hasNotText: 'Pilih salah satu opsi' })
  const optionCount = await realOptions.count()
  if (optionCount === 0) {
    test.skip(true, 'Tidak ada data Quotation untuk menguji auto-fill cabang')
    return
  }

  await realOptions.first().click({ force: true })
  await page.waitForTimeout(700)

  // If cabang is visible for this user, it should no longer stay on empty placeholder
  const cabangInput = page.locator('input[placeholder="Pilih salah satu opsi"]').nth(1)
  if ((await cabangInput.count()) === 0) {
    test.skip(true, 'Field cabang tidak ditampilkan untuk role user ini')
    return
  }

  const cabangValue = (await cabangInput.inputValue()).trim()
  expect(cabangValue.length).toBeGreaterThan(0)
  expect(cabangValue).not.toBe('Pilih salah satu opsi')
})
