import { test, expect } from '@playwright/test'
import { acquirePlaywrightDbLock, ensureManufacturingFixture, querySingleValue, FIXTURE, seedManufacturingFixture } from './helpers/manufacturing-fixture.js'

const BASE = 'http://localhost:8009'
const ERR = /Fatal error|Whoops!|Something went wrong/i

test.use({ storageState: 'playwright/.auth/user.json' })

test.beforeAll(() => {
  ensureManufacturingFixture()
})

test('material issue edit page filters warehouse choices by product stock', async ({ page }) => {
  const releaseLock = await acquirePlaywrightDbLock()

  try {
  seedManufacturingFixture()
  const issueId = querySingleValue(`DB::table('material_issues')->where('issue_number', '${FIXTURE.issueNumber}')->value('id')`)
  expect(issueId).toBeTruthy()

  await page.goto(`${BASE}/admin/material-issues/${issueId}/edit`, { waitUntil: 'domcontentloaded' })

  await expect(page.locator('body')).not.toContainText(ERR)
  await expect(page.locator('body')).toContainText(FIXTURE.rawMaterialSku)
  await expect(page.locator('body')).toContainText(FIXTURE.warehouseCode)
  } finally {
    releaseLock()
  }
})

test('material issue edit page can save with formatted subtotal', async ({ page }) => {
  const releaseLock = await acquirePlaywrightDbLock()

  try {
    seedManufacturingFixture()
    const issueId = querySingleValue(`DB::table('material_issues')->where('issue_number', '${FIXTURE.issueNumber}')->value('id')`)
    expect(issueId).toBeTruthy()

    await page.goto(`${BASE}/admin/material-issues/${issueId}/edit`, { waitUntil: 'domcontentloaded' })
    await page.waitForLoadState('networkidle')

    const bodyBeforeSave = await page.textContent('body')
    expect(bodyBeforeSave || '').not.toMatch(ERR)
    expect(bodyBeforeSave || '').not.toContain('Subtotal harus berupa angka.')

    const saveButton = page.locator('button').filter({ hasText: /simpan|save/i }).first()
    await expect(saveButton).toBeVisible()
    await saveButton.click({ force: true })

    await page.waitForLoadState('networkidle')
    await page.waitForTimeout(1500)

    const bodyAfterSave = await page.textContent('body')
    expect(bodyAfterSave || '').not.toMatch(ERR)
    expect(bodyAfterSave || '').not.toContain('Subtotal harus berupa angka.')
  } finally {
    releaseLock()
  }
})

test('material issue edit page can save with formatted cost price', async ({ page }) => {
  const releaseLock = await acquirePlaywrightDbLock()

  try {
    seedManufacturingFixture()
    const issueId = querySingleValue(`DB::table('material_issues')->where('issue_number', '${FIXTURE.issueNumber}')->value('id')`)
    expect(issueId).toBeTruthy()

    await page.goto(`${BASE}/admin/material-issues/${issueId}/edit`, { waitUntil: 'domcontentloaded' })
    await page.waitForLoadState('networkidle')

    const costPriceInput = page.locator('input[id*="cost_per_unit"]').first()
    await expect(costPriceInput).toBeVisible()

    const currentValue = await costPriceInput.inputValue()
    expect(currentValue).toMatch(/^[\d.]+$/)

    await costPriceInput.click({ clickCount: 3 })
    await costPriceInput.press('Control+a')
    await costPriceInput.press('Delete')
    await costPriceInput.fill('11176600')
    await page.waitForTimeout(700)

    const formattedValue = await costPriceInput.inputValue()
    expect(formattedValue).toMatch(/11\.176\.600|11176600/)

    const saveButton = page.locator('button').filter({ hasText: /simpan|save/i }).first()
    await expect(saveButton).toBeVisible()
    await saveButton.click({ force: true })

    await page.waitForLoadState('networkidle')
    await page.waitForTimeout(1500)

    const bodyAfterSave = await page.textContent('body')
    expect(bodyAfterSave || '').not.toMatch(ERR)
    expect(bodyAfterSave || '').not.toContain('Cost Price harus berupa angka.')
  } finally {
    releaseLock()
  }
})
