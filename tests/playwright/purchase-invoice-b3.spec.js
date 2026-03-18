import { test, expect } from '@playwright/test'
import {
  BASE,
  FIXTURE,
  ensurePurchaseInvoiceFixture,
  openCreatePage,
  chooseFixtureSupplier,
  checkCheckboxByLabel,
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

  const poCheckbox = await checkCheckboxByLabel(page, FIXTURE.poNumber)
  await expect(poCheckbox).toBeVisible()
  await expect(poCheckbox).toBeEnabled()
  await poCheckbox.check({ force: true })

  await page.waitForTimeout(1200)

  const lockedReceiptRow = page.locator('label').filter({ hasText: FIXTURE.receiptLocked }).first()
  await expect(lockedReceiptRow).toBeVisible()
  await expect(lockedReceiptRow).toContainText('Sudah di-invoice')

  const lockedReceiptCheckbox = await checkCheckboxByLabel(page, FIXTURE.receiptLocked)
  await expect(lockedReceiptCheckbox).toBeVisible()
  await expect(lockedReceiptCheckbox).toBeDisabled()

  const openReceiptCheckbox = await checkCheckboxByLabel(page, FIXTURE.receiptOpen)
  await expect(openReceiptCheckbox).toBeVisible()
  await expect(openReceiptCheckbox).toBeEnabled()
})
