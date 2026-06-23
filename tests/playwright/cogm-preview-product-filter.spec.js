import { test, expect } from '@playwright/test'
import { execSync } from 'node:child_process'

function querySingleValue(expression) {
  return execSync(`php artisan tinker --execute="echo ${expression};"`, {
    encoding: 'utf8',
  }).trim()
}

function getProductScopedReport(productId) {
  const output = execSync(
    `php artisan tinker --execute="echo json_encode(app(App\\\\Services\\\\Reports\\\\HppReportService::class)->generate('2025-01-01', '2025-01-31', ['product_id' => ${productId}]));"`,
    { encoding: 'utf8' },
  ).trim()

  return JSON.parse(output)
}

function formatRupiah(value) {
  return Number(value).toLocaleString('id-ID', { maximumFractionDigits: 0 })
}

test.beforeAll(() => {
  execSync('php scripts/setup_cogm_playwright_data.php', { stdio: 'inherit' })
})

test('COGM preview shows product-filtered totals matching HPP service', async ({ page }) => {
  const productId = querySingleValue(`DB::table('products')->where('sku', 'FG-PW-COGM-A')->value('id')`)
  const expected = getProductScopedReport(productId)

  await page.goto(`/reports/cogm/preview?start_date=2025-01-01&end_date=2025-01-31&product_id=${productId}`)
  await page.waitForLoadState('networkidle')

  await expect(page.locator('body')).toContainText('Fixture COGM Product A')
  await expect(page.locator('body')).toContainText(`Rp ${formatRupiah(expected.raw_materials.used)}`)
  await expect(page.locator('body')).toContainText(`Rp ${formatRupiah(expected.direct_labor)}`)
  await expect(page.locator('body')).toContainText(`Rp ${formatRupiah(expected.overhead.total)}`)
  await expect(page.locator('body')).toContainText(`Rp ${formatRupiah(expected.production_cost)}`)
  await expect(page.locator('body')).toContainText(`(Rp ${formatRupiah(expected.wip.closing)})`)
  await expect(page.locator('body')).toContainText(`Rp ${formatRupiah(expected.cogm)}`)
  await expect(page.locator('body')).not.toContainText('Fixture COGM Product B')
})

test('COGM preview shows the HPP data-quality warning banner when fallback data is used', async ({ page }) => {
  await page.goto('/reports/hpp/preview?startDate=2026-04-01&endDate=2026-04-30&preview=1', {
    waitUntil: 'networkidle',
  })

  const warningBanner = page.locator('.data-quality-warning')

  await expect(warningBanner).toBeVisible({ timeout: 10_000 })
  await expect(warningBanner).toContainText('Peringatan kualitas data HPP')
  await expect(page.locator('body')).toContainText('Raw material purchases are being derived from inventory debits')
  await expect(page.locator('body')).toContainText('Raw material inventory balances are being derived from stock movements')
})