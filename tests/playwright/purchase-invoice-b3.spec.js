import { test, expect } from '@playwright/test'
import {
  BASE,
  FIXTURE,
  ensurePurchaseInvoiceFixture,
  openCreatePage,
  chooseFixtureSupplier,
  checkCheckboxByLabel,
  clickCheckboxByLabel,
} from './helpers/purchase-invoice-fixture'

const ERR = /Fatal error|Whoops!|Something went wrong/i

test.use({ storageState: 'playwright/.auth/user.json' })

test.beforeAll(async () => {
  ensurePurchaseInvoiceFixture()
})

test('B3-a: purchase invoice create page loads and shows helper text', async ({ page }) => {
  await openCreatePage(page)

  const body = await page.textContent('body')
  expect(body).not.toMatch(ERR)
  expect(body).toContain('Receipt yang berlabel "Sudah di-invoice" tetap ditampilkan, namun tidak dapat dipilih.')
})

test('B3-b: fixture already-invoiced receipt option is disabled', async ({ page }) => {
  await openCreatePage(page)

  const body = await page.textContent('body')
  expect(body).not.toMatch(ERR)

  await chooseFixtureSupplier(page)

  let poCheckbox = await checkCheckboxByLabel(page, FIXTURE.poNumber)
  await expect(poCheckbox).toBeVisible()
  if (!(await poCheckbox.isEnabled())) {
    poCheckbox = page.locator('input[type="checkbox"][wire\\:model\\.live="data.selected_purchase_orders"]:not([disabled])').first()
    await expect(poCheckbox).toBeVisible({ timeout: 5000 })
  }
  await expect(poCheckbox).toBeEnabled({ timeout: 5000 })
  await poCheckbox.click({ force: true })

  await page.waitForLoadState('networkidle')

  const disabledReceipt = page.locator('input[type="checkbox"][wire\\:model\\.live="data.selected_purchase_receipts"][disabled]').first()
  const enabledReceipt = page.locator('input[type="checkbox"][wire\\:model\\.live="data.selected_purchase_receipts"]:not([disabled])').first()

  await expect(disabledReceipt).toBeVisible({ timeout: 10000 })
  await expect(disabledReceipt).toBeDisabled()
  await expect(enabledReceipt).toBeVisible({ timeout: 10000 })
  await expect(enabledReceipt).toBeEnabled()
})
