import { test, expect } from '@playwright/test'
import { execSync } from 'node:child_process'

const ERR = /Fatal error|Whoops!|Something went wrong/i

test.use({ storageState: 'playwright/.auth/user.json' })

test.beforeAll(() => {
  execSync('php scripts/setup_journal_entry_playwright_data.php', { stdio: 'inherit' })
})

function getJournalEntryFixtureIds() {
  const output = execSync(
    `php artisan tinker --execute="echo App\\Models\\JournalEntry::where('reference', 'JE-PW-001')->value('id') . '|' . App\\Models\\PurchaseReceipt::where('receipt_number', 'PR-PW-JE-001')->value('id');"`,
    { encoding: 'utf8' }
  ).trim()

  const [journalIdRaw, receiptIdRaw] = output.split('|')
  const journalId = Number(journalIdRaw)
  const receiptId = Number(receiptIdRaw)

  if (!journalId || !receiptId) {
    throw new Error(`Unable to resolve journal fixture ids. Output: ${output}`)
  }

  return { journal_id: journalId, receipt_id: receiptId }
}

test('journal entry view shows source number, source detail, and clickable source link', async ({ page }) => {
  const { journal_id: journalId, receipt_id: receiptId } = getJournalEntryFixtureIds()

  await page.goto(`/admin/journal-entries/${journalId}`)
  await page.waitForLoadState('networkidle')

  const sourceSectionToggle = page.locator('summary:has-text("Source Information"), button:has-text("Source Information")').first()
  if (await sourceSectionToggle.count()) {
    await sourceSectionToggle.click()
    await page.waitForTimeout(500)
  }

  const body = await page.textContent('body')
  expect(body).not.toMatch(ERR)
  expect(body).toContain('Source Number')
  expect(body).toContain('PR-PW-JE-001')
  expect(body).toContain('Source Details')
  expect(body).toContain('PT Journal Entry Fixture')
  expect(body).toContain('Qty Diterima')

  const sourceLink = page.locator(`a[href*="/admin/purchase-receipts/${receiptId}"]`).first()

  const popupPromise = page.waitForEvent('popup', { timeout: 5000 }).catch(() => null)
  const navigationPromise = page.waitForURL(new RegExp(`/admin/purchase-receipts/${receiptId}$`), { timeout: 5000 }).catch(() => null)

  await sourceLink.click({ force: true })

  const popup = await popupPromise
  await navigationPromise

  if (popup) {
    await popup.waitForLoadState('networkidle')
    await expect(popup).toHaveURL(new RegExp(`/admin/purchase-receipts/${receiptId}$`))
    await expect(popup.locator('body')).not.toContainText(ERR)
    await expect(popup.locator('body')).toContainText('PR-PW-JE-001')
  } else {
    await expect(page).toHaveURL(new RegExp(`/admin/purchase-receipts/${receiptId}$`))
    await expect(page.locator('body')).not.toContainText(ERR)
    await expect(page.locator('body')).toContainText('PR-PW-JE-001')
  }
})
