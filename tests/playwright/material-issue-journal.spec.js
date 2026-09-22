import { test, expect } from '@playwright/test'
import { execSync } from 'node:child_process'
import { ensureManufacturingFixture, querySingleValue } from './helpers/manufacturing-fixture.js'

const BASE = 'http://localhost:8009'
const ERR = /Fatal error|Whoops!|Something went wrong/i

test.use({ storageState: 'playwright/.auth/user.json' })
test.setTimeout(60000)

async function navigate(page, path) {
  await page.goto(path)
  await page.waitForLoadState('domcontentloaded')

  if (page.url().includes('/login')) {
    await page.getByLabel('Alamat email').fill('superadmin@gmail.com')
    await page.getByLabel('Kata sandi').fill('superadmin')
    await page.locator('form').getByRole('button', { name: /masuk|login|sign in/i }).click()
    await page.waitForFunction(() => !window.location.pathname.endsWith('/login'), { timeout: 30000 })
    await page.goto(path)
    await page.waitForLoadState('domcontentloaded')
  }

  await page.waitForLoadState('networkidle')
}

test('Material Issue completed view shows generated journal result', async ({ page }) => {
  ensureManufacturingFixture()

  execSync(
    `php artisan tinker --execute="\\App\\Models\\MaterialIssue::where('issue_number', 'MI-PW-MFG-001')->update(['status' => 'completed']); app(\\App\\Services\\ManufacturingJournalService::class)->generateJournalForMaterialIssue(\\App\\Models\\MaterialIssue::where('issue_number', 'MI-PW-MFG-001')->first());"`,
    { stdio: 'inherit' },
  )

  const issueId = querySingleValue(`DB::table('material_issues')->where('issue_number', 'MI-PW-MFG-001')->value('id')`)
  expect(issueId).toBeTruthy()

  await navigate(page, `${BASE}/admin/material-issues/${issueId}`)
  await expect(page.locator('body')).not.toContainText(ERR)

  await expect(page.getByRole('heading', { name: 'Jurnal Hasil' }).last()).toBeVisible({ timeout: 10000 })
  const journalTable = page.locator('table:visible').first()
  await expect(journalTable).toBeVisible()
  await expect(page.getByRole('columnheader', { name: 'Akun' })).toBeVisible()
  await expect(page.getByRole('columnheader', { name: 'Debit' })).toBeVisible()
  await expect(page.getByRole('columnheader', { name: 'Credit' })).toBeVisible()
  await expect(page.getByText('(1400.04)').first()).toBeVisible()
})
