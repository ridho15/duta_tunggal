import { test, expect } from '@playwright/test'
import {
  FIXTURE,
  ensurePurchaseInvoiceFixture,
  openCreatePage,
  chooseFixtureSupplier,
  chooseFixtureOrderRequest,
  checkCheckboxByLabel,
  clickCheckboxByLabel,
} from './helpers/purchase-invoice-fixture'

const BASE = 'http://localhost:8009'
const ERR = /Fatal error|Whoops!|Something went wrong/i

test.use({ storageState: 'playwright/.auth/user.json' })

test.beforeAll(async () => {
  ensurePurchaseInvoiceFixture()
})

test('D1-a: purchase-orders create shows branch auto-fill helper', async ({ page }) => {
  await page.goto(`${BASE}/admin/purchase-orders/create`)
  await page.waitForLoadState('networkidle')

  const body = await page.textContent('body')
  expect(page.url()).not.toMatch(/login/)
  expect(body).not.toMatch(ERR)
  expect(body).toContain('Diisi otomatis dari Order Request saat referensi dipilih. Dapat diubah bila perlu.')
})

test('D1-b: purchase-invoices create shows branch auto-fill helper', async ({ page }) => {
  await openCreatePage(page)

  const body = await page.textContent('body')
  expect(page.url()).not.toMatch(/login/)
  expect(body).not.toMatch(ERR)

  expect(body).toContain('Diisi otomatis dari PO/Receipt yang dipilih. Dapat diubah bila perlu.')
})

test('D1-c: selecting fixture PO populates cabang in purchase invoice form', async ({ page }) => {
  await openCreatePage(page)

  await chooseFixtureSupplier(page)
  await chooseFixtureOrderRequest(page)

  let poCheckbox = await checkCheckboxByLabel(page, FIXTURE.poNumber)
  await expect(poCheckbox).toBeVisible()
  if (!(await poCheckbox.isEnabled())) {
    poCheckbox = page.locator('input[type="checkbox"][wire\\:model\\.live="data.selected_purchase_orders"]:not([disabled])').first()
    await expect(poCheckbox).toBeVisible({ timeout: 5000 })
  }
  await expect(poCheckbox).toBeEnabled({ timeout: 5000 })
  await poCheckbox.click({ force: true })

  await page.waitForLoadState('networkidle')

  const cabangFieldWrapper = page
    .locator('.fi-fo-field-wrp')
    .filter({ has: page.locator('label:has-text("Cabang")') })
    .first()

  await expect(cabangFieldWrapper).toBeVisible()

  const cabangCombobox = cabangFieldWrapper.getByRole('combobox').first()
  await expect(cabangCombobox).toBeVisible()

  await expect.poll(async () => {
    const text = (await cabangCombobox.textContent()) ?? ''
    return text.trim()
  }, { timeout: 10000 }).not.toMatch(/Pilih salah satu opsi/i)
})
