import { test, expect } from '@playwright/test'
import { execSync } from 'node:child_process'
import { confirmDialogAction, querySingleValue } from './helpers/manufacturing-fixture.js'

test.use({ storageState: 'playwright/.auth/user.json' })

const BASE = 'http://localhost:8009'
const ERR = /Fatal error|Whoops!|Something went wrong/i

async function selectFirstChoicesOption(page, labelText, searchTerm = '', scopeSelector = '') {
  const scope = scopeSelector ? page.locator(scopeSelector).first() : page
  const wrapper = scope.locator('.fi-fo-field-wrp').filter({ has: scope.locator(`label:has-text("${labelText}")`) }).first()
  await wrapper.waitFor({ state: 'visible', timeout: 10000 })

  const choicesInner = wrapper.locator('.choices__inner')
  await choicesInner.click()

  if (searchTerm) {
    const searchInput = wrapper.locator('.choices__input--cloned, .choices__input[type="search"]').first()
    await searchInput.waitFor({ state: 'visible', timeout: 10000 })
    await searchInput.click({ force: true })
    await searchInput.fill(searchTerm)
    await page.waitForTimeout(700)
  }

  const firstItem = wrapper.locator('.choices__list--dropdown .choices__item--choice:not(.choices__placeholder):not(.is-disabled)').first()
  await firstItem.waitFor({ state: 'visible', timeout: 10000 })
  await firstItem.click()

  await page.waitForTimeout(500)
}

async function selectRepeaterChoicesOption(page, choiceIndex, searchTerm = '') {
  const repeaterItem = page.locator('.fi-fo-repeater-item').first()
  await repeaterItem.waitFor({ state: 'visible', timeout: 10000 })

  const choicesInner = repeaterItem.locator('.choices__inner').nth(choiceIndex)
  await choicesInner.click()

  const option = repeaterItem.locator('.choices__list--dropdown .choices__item--choice:not(.choices__placeholder):not(.is-disabled)').filter({ hasText: searchTerm }).first()
  await option.evaluate((element) => element.click())

  await page.waitForTimeout(500)
}

async function navigate(page, path) {
  await page.goto(path)
  await page.waitForLoadState('domcontentloaded')

  if (page.url().includes('/login')) {
    const email = page.getByLabel('Alamat email')
    const password = page.getByLabel('Kata sandi')
    await email.waitFor({ state: 'visible', timeout: 30000 })
    await email.fill('superadmin@gmail.com')
    await password.fill('superadmin')
    await page.locator('form').getByRole('button', { name: /masuk|login|sign in/i }).click()
    await page.waitForFunction(() => !window.location.pathname.endsWith('/login'), { timeout: 30000 })
    await page.goto(path)
    await page.waitForLoadState('domcontentloaded')
  }

  await page.waitForLoadState('networkidle')
}

function queryAdjustmentId(number) {
  return querySingleValue(`DB::table('stock_adjustments')->where('adjustment_number', '${number}')->value('id')`)
}

function queryAdjustmentStatus(number) {
  return querySingleValue(`DB::table('stock_adjustments')->where('adjustment_number', '${number}')->value('status')`)
}

function queryAdjustmentMovementCount(number) {
  const id = queryAdjustmentId(number)
  return Number(querySingleValue(`DB::table('stock_movements')->where('from_model_type', 'App\\Models\\StockAdjustment')->where('from_model_id', ${id})->count()`))
}

function queryAvailableStock(warehouseCode, rakCode) {
  return Number(querySingleValue(`DB::table('inventory_stocks')
    ->join('warehouses', 'warehouses.id', '=', 'inventory_stocks.warehouse_id')
    ->join('raks', 'raks.id', '=', 'inventory_stocks.rak_id')
    ->where('warehouses.kode', '${warehouseCode}')
    ->where('raks.code', '${rakCode}')
    ->value('inventory_stocks.qty_available')`))
}

async function openApprovalAction(page, adjustmentId) {
  const adjustmentNumber = querySingleValue(`DB::table('stock_adjustments')->where('id', ${adjustmentId})->value('adjustment_number')`)
  expect(adjustmentNumber).toBeTruthy()

  await navigate(page, `${BASE}/admin/stock-adjustments`)
  await expect(page.locator('body')).not.toContainText(ERR)

  const row = page.locator('tr', { hasText: adjustmentNumber }).first()
  await expect(row).toBeVisible()
  await row.locator('button:visible').last().click({ force: true })
  await page.waitForTimeout(300)

  const approveButton = page.locator(`[wire\\:click*="mountTableAction('approve', '${adjustmentId}')"]:visible`).last()
  await expect(approveButton).toBeVisible()
  await approveButton.click({ force: true })
}

test.describe.serial('Stock adjustment approval guard', () => {
  test.beforeAll(() => {
    execSync('php scripts/setup_stock_adjustment_playwright_data.php', { stdio: 'inherit' })
  })

  test('create form supports inline item input and shows SKU and rak code labels', async ({ page }) => {
    await navigate(page, `${BASE}/admin/stock-adjustments/create`)

    const body = await page.textContent('body')
    expect(body).not.toMatch(ERR)
    expect(body).toMatch(/Item Adjustment/i)
    expect(body).toMatch(/Produk/i)
    expect(body).toMatch(/Rak/i)
    expect(body).toMatch(/Qty Saat Ini/i)
    expect(body).toMatch(/Qty Setelah Adjustment/i)
  })

  test('approve draft increase adjustment updates stock once', async ({ page }) => {
    const adjustmentNumber = 'ADJ-PW-APPROVE-001'
    const adjustmentId = queryAdjustmentId(adjustmentNumber)
    expect(adjustmentId).toBeTruthy()

    await openApprovalAction(page, adjustmentId)

    const livewireUpdate = page.waitForResponse(
      (response) => response.url().includes('/livewire/update') && response.request().method() === 'POST',
    )

    await confirmDialogAction(page, 'Konfirmasi')
    await livewireUpdate
    await page.waitForLoadState('networkidle')

    await expect.poll(() => queryAdjustmentStatus(adjustmentNumber), { timeout: 15000 }).toBe('approved')
    await expect.poll(() => queryAdjustmentMovementCount(adjustmentNumber), { timeout: 15000 }).toBe(1)
    await expect.poll(() => queryAvailableStock('GDG-PW-SA-001', 'RAK-PW-SA-001'), { timeout: 15000 }).toBe(15)

    const body = (await page.textContent('body')) || ''
    expect(body).not.toMatch(ERR)
  })

  test('approve decrease adjustment with insufficient stock shows readable validation', async ({ page }) => {
    const adjustmentNumber = 'ADJ-PW-FAIL-001'
    const adjustmentId = queryAdjustmentId(adjustmentNumber)
    expect(adjustmentId).toBeTruthy()

    await openApprovalAction(page, adjustmentId)
    await confirmDialogAction(page, 'Konfirmasi')

    await expect(page.locator('body')).not.toContainText(ERR)
    await expect.poll(() => queryAdjustmentStatus(adjustmentNumber), { timeout: 15000 }).toBe('draft')
    await expect.poll(() => queryAdjustmentMovementCount(adjustmentNumber), { timeout: 15000 }).toBe(0)
    await expect.poll(() => queryAvailableStock('GDG-PW-SA-002', 'RAK-PW-SA-002'), { timeout: 15000 }).toBe(3)
  })
})