import { test, expect } from '@playwright/test'
import { execSync } from 'node:child_process'

test.use({ storageState: 'playwright/.auth/user.json' })

const SO_NUMBER = 'SO-TEST-G2-0001'
const ERR = /Fatal error|Whoops!|Something went wrong/i

test.beforeAll(async () => {
  execSync('php scripts/setup_sale_order_g2_playwright_data.php', { stdio: 'inherit' })
})

test('G2-a: sale order list has total_amount column in Rupiah', async ({ page }) => {
  await page.goto('/admin/sale-orders')
  await page.waitForLoadState('networkidle')
  await expect(page).not.toHaveURL(/login/)

  const body = await page.textContent('body')
  expect(body).not.toMatch(ERR)

  const row = page.locator('tr, .fi-ta-row').filter({ hasText: SO_NUMBER }).first()
  await expect(row).toBeVisible()
  await expect(row).toContainText(/Rp|\d{1,3}(\.\d{3})+/)
})

test('G2-b: sale order view shows Total Amount in infolist', async ({ page }) => {
  await page.goto('/admin/sale-orders')
  await page.waitForLoadState('networkidle')

  const row = page.locator('tr, .fi-ta-row').filter({ hasText: SO_NUMBER }).first()
  await expect(row).toBeVisible()

  const hrefs = await row.locator('a[href*="/admin/sale-orders/"]').evaluateAll((els) =>
    els
      .map((el) => el.getAttribute('href'))
      .filter((href) => href && /\/admin\/sale-orders\/\d+$/.test(href))
  )
  expect(hrefs.length).toBeGreaterThan(0)

  await page.goto(hrefs[0])
  await page.waitForLoadState('networkidle')

  const body = await page.textContent('body')
  expect(body).not.toMatch(ERR)

  const totalEntry = page
    .locator('.fi-in-entry-wrp')
    .filter({ hasText: /Total amount|Total Amount/i })
    .first()
  await expect(totalEntry).toBeVisible()
  await expect(totalEntry).toContainText(/Rp|\d{1,3}(\.\d{3})+/)
})

test('G2-c: sale order form keeps total_amount disabled (auto-sum target)', async ({ page }) => {
  await page.goto('/admin/sale-orders/create')
  await page.waitForLoadState('networkidle')
  await expect(page).not.toHaveURL(/login/)

  const totalAmountInput = page.locator('#data\\.total_amount').first()
  await expect(totalAmountInput).toBeVisible()
  await expect(totalAmountInput).toBeDisabled()
})
