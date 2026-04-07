/**
 * Playwright – Grouped Journal Entries page
 *
 * URL: /admin/journal-entries/grouped
 *
 * Test coverage:
 *  1. Page loads without fatal error
 *  2. Page title / heading is visible
 *  3. Filter form fields rendered (Start Date, End Date, Journal Type, Branch)
 *  4. Summary statistics cards are rendered (Total Entries, Total Debit, Total Credit, Balance Status)
 *  5. "Journal Entries by Parent COA" section is visible
 *  6. Number formatting uses "Rp" prefix
 *  7. Apply Filters button submits and page stays functional
 *  8. Reset button is present and functional
 *  9. Export button is present
 * 10. Navigation: Back to List button returns to index
 */

import { test, expect } from '@playwright/test'

const PAGE_URL = '/admin/journal-entries/grouped'
const ERR_PATTERN = /Fatal error|Whoops!|Something went wrong|500 Server Error/i

test.use({ storageState: 'playwright/.auth/user.json' })

// ──────────────────────────────────────────────────────────────────────────────
// Helper
// ──────────────────────────────────────────────────────────────────────────────
async function loadPage(page) {
  await page.goto(PAGE_URL)
  await page.waitForLoadState('networkidle')
}

// ──────────────────────────────────────────────────────────────────────────────
// 1. Page loads without errors
// ──────────────────────────────────────────────────────────────────────────────
test('grouped journal entries page loads without fatal error', async ({ page }) => {
  await loadPage(page)
  const body = await page.textContent('body')
  expect(body).not.toMatch(ERR_PATTERN)
})

// ──────────────────────────────────────────────────────────────────────────────
// 2. Page heading visible
// ──────────────────────────────────────────────────────────────────────────────
test('page shows Journal Entries heading', async ({ page }) => {
  await loadPage(page)
  await expect(
    page.locator('h1, .custom-title').filter({ hasText: /Journal Entries/i }).first()
  ).toBeVisible()
})

// ──────────────────────────────────────────────────────────────────────────────
// 3. Filter form fields are rendered
// ──────────────────────────────────────────────────────────────────────────────
test('filter form shows Start Date, End Date, Journal Type, Branch labels', async ({ page }) => {
  await loadPage(page)
  const body = await page.textContent('body')
  expect(body).toContain('Start Date')
  expect(body).toContain('End Date')
  expect(body).toContain('Journal Type')
  expect(body).toContain('Branch')
})

test('Apply Filters button is visible', async ({ page }) => {
  await loadPage(page)
  await expect(
    page.getByRole('button', { name: /Apply Filters/i }).first()
  ).toBeVisible()
})

test('Reset button is visible', async ({ page }) => {
  await loadPage(page)
  await expect(
    page.getByRole('button', { name: /Reset/i }).first()
  ).toBeVisible()
})

// ──────────────────────────────────────────────────────────────────────────────
// 4. Summary statistics cards
// ──────────────────────────────────────────────────────────────────────────────
test('summary shows Total Entries card', async ({ page }) => {
  await loadPage(page)
  await expect(page.getByText('Total Entries').first()).toBeVisible()
})

test('summary shows Total Debit card', async ({ page }) => {
  await loadPage(page)
  await expect(page.getByText('Total Debit').first()).toBeVisible()
})

test('summary shows Total Credit card', async ({ page }) => {
  await loadPage(page)
  await expect(page.getByText('Total Credit').first()).toBeVisible()
})

test('summary shows Balance Status card', async ({ page }) => {
  await loadPage(page)
  await expect(page.getByText('Balance Status').first()).toBeVisible()
})

// ──────────────────────────────────────────────────────────────────────────────
// 5. Grouped entries section
// ──────────────────────────────────────────────────────────────────────────────
test('Journal Entries by Parent COA section is visible', async ({ page }) => {
  await loadPage(page)
  const body = await page.textContent('body')
  expect(body).toContain('Journal Entries by Parent COA')
})

test('parent accounts count label is visible', async ({ page }) => {
  await loadPage(page)
  // Should show "N parent accounts"
  await expect(
    page.locator('.custom-text-sm-gray').filter({ hasText: /parent accounts?/i }).first()
  ).toBeVisible()
})

// ──────────────────────────────────────────────────────────────────────────────
// 6. Number formatting uses Rp prefix in summary
// ──────────────────────────────────────────────────────────────────────────────
test('debit and credit amounts are formatted with Rp prefix', async ({ page }) => {
  await loadPage(page)
  const body = await page.textContent('body')
  // The summary cards use "Rp" prefix
  expect(body).toContain('Rp')
})

// ──────────────────────────────────────────────────────────────────────────────
// 7. Apply Filters submits without error
// ──────────────────────────────────────────────────────────────────────────────
test('applying a date filter does not cause error', async ({ page }) => {
  await loadPage(page)

  // Fill Start Date
  const startDateInput = page.locator('input[placeholder*="date" i], input[id*="start_date" i]').first()
  if (await startDateInput.count()) {
    await startDateInput.fill('2024-01-01')
  }

  // Click Apply Filters
  await page.getByRole('button', { name: /Apply Filters/i }).first().click()
  await page.waitForLoadState('networkidle')

  const body = await page.textContent('body')
  expect(body).not.toMatch(ERR_PATTERN)
  expect(body).toContain('Journal Entries by Parent COA')
})

// ──────────────────────────────────────────────────────────────────────────────
// 8. Reset button restores state without error
// ──────────────────────────────────────────────────────────────────────────────
test('clicking Reset does not cause error', async ({ page }) => {
  await loadPage(page)

  await page.getByRole('button', { name: /Reset/i }).first().click()
  await page.waitForLoadState('networkidle')

  const body = await page.textContent('body')
  expect(body).not.toMatch(ERR_PATTERN)
})

// ──────────────────────────────────────────────────────────────────────────────
// 9. Export button is present
// ──────────────────────────────────────────────────────────────────────────────
test('Export to Excel button is visible', async ({ page }) => {
  await loadPage(page)
  await expect(
    page.getByRole('button', { name: /Export to Excel/i }).first()
  ).toBeVisible()
})

// ──────────────────────────────────────────────────────────────────────────────
// 10. Back to List navigation
// ──────────────────────────────────────────────────────────────────────────────
test('Back to List button navigates to journal entries index', async ({ page }) => {
  await loadPage(page)

  const backButton = page.getByRole('link', { name: /Back to List/i }).first()
  await expect(backButton).toBeVisible()
  await backButton.click()
  await page.waitForLoadState('networkidle')

  await expect(page).toHaveURL(/\/admin\/journal-entries($|\?)/)
  const body = await page.textContent('body')
  expect(body).not.toMatch(ERR_PATTERN)
})

// ──────────────────────────────────────────────────────────────────────────────
// 11. If data exists, rows are click-navigable (deferred — only runs if rows exist)
// ──────────────────────────────────────────────────────────────────────────────
test('clicking a parent COA row expands the dropdown', async ({ page }) => {
  await loadPage(page)

  // Try to find the first parent row (custom-p-4 class with cursor-pointer)
  const firstParentRow = page.locator('.custom-p-4.custom-cursor-pointer').first()
  if ((await firstParentRow.count()) === 0) {
    test.skip() // no data yet — skip
    return
  }

  // Verify the chevron is in closed state (no open class)
  const chevron = firstParentRow.locator('.custom-dropdown-icon').first()
  await expect(chevron).not.toHaveClass(/custom-rotate-90/)

  await firstParentRow.click()
  await page.waitForTimeout(400) // allow Alpine.js transition

  // After click the chevron should rotate
  await expect(chevron).toHaveClass(/custom-rotate-90/)
})
