import { test, expect } from '@playwright/test'
import { execSync } from 'node:child_process'

test.use({ storageState: 'playwright/.auth/user.json' })
test.describe.configure({ mode: 'serial' })

function queryValue(expression) {
  return execSync(`php artisan tinker --execute="echo ${expression};"`, {
    encoding: 'utf8',
  }).trim()
}

function formatRupiah(value) {
  return Number(value).toLocaleString('id-ID', { maximumFractionDigits: 0 })
}

test.beforeAll(() => {
  execSync('php scripts/setup_journal_consolidation_playwright_data.php', { stdio: 'inherit' })
})

test('journal consolidation admin page renders grouped preview using shared data', async ({ page }) => {
  const totalDebit = queryValue(
    `app(App\\Services\\Reports\\JournalConsolidationReportService::class)->generate(['start_date' => '2026-04-01', 'end_date' => '2026-04-30', 'group_by_branch' => true])['total_debit']`
  )

  await page.goto('/admin/journal-consolidation?preview=1&start_date=2026-04-01&end_date=2026-04-30&group_by_branch=1', {
    waitUntil: 'networkidle',
  })

  await expect(page.locator('body')).toContainText('Filter Konsolidasi Jurnal')
  await expect(page.locator('body')).toContainText('Ringkasan per Akun')
  await expect(page.locator('body')).toContainText('Cabang Fixture Journal A')
  await expect(page.locator('body')).toContainText('Cabang Fixture Journal B')
  await expect(page.locator('body')).toContainText(`Rp ${formatRupiah(totalDebit)}`)
})

test('journal consolidation standalone preview renders consolidated manual totals', async ({ page }) => {
  const manualTotalDebit = queryValue(
    `app(App\\Services\\Reports\\JournalConsolidationReportService::class)->generate(['start_date' => '2026-04-01', 'end_date' => '2026-04-30', 'journal_type' => 'manual', 'group_by_branch' => false])['total_debit']`
  )

  await page.goto('/reports/journal-consolidation/preview?start_date=2026-04-01&end_date=2026-04-30&journal_type=manual&group_by_branch=0', {
    waitUntil: 'networkidle',
  })

  await expect(page.locator('body')).toContainText('Journal Consolidation')
  await expect(page.locator('body')).toContainText('Semua Cabang (Konsolidasi)')
  await expect(page.locator('body')).toContainText('Jenis jurnal: manual')
  await expect(page.locator('body')).toContainText(`Rp ${formatRupiah(manualTotalDebit)}`)
  await expect(page.locator('body')).not.toContainText('Tidak ada jurnal untuk filter yang dipilih')
})