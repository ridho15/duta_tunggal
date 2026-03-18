import { test, expect } from '@playwright/test'
import {
  ensureVendorPaymentFixture,
  openVendorPaymentCreatePage,
  selectFixturePaymentRequest,
  FIXTURE,
} from './helpers/vendor-payment-fixture'

const ERR = /Fatal error|Whoops!|Something went wrong/i

test.use({ storageState: 'playwright/.auth/user.json' })

test.describe.serial('C3 status transition PaymentRequest (partial -> paid)', () => {
  test.beforeAll(async () => {
    ensureVendorPaymentFixture()
  })

  async function ensureCoaSelected(page) {
    const coaSelect = page.locator('#data\\.coa_id')
    if ((await coaSelect.count()) === 0) return

    const current = await coaSelect.inputValue()
    if (current && current !== '0') return

    const optionValue = await coaSelect.evaluate((selectEl) => {
      const select = selectEl
      const options = Array.from(select.options).map((opt) => opt.value).filter((v) => v && v !== '0')
      return options.length ? options[0] : null
    })

    if (optionValue) {
      await coaSelect.selectOption(String(optionValue))
      await page.waitForTimeout(500)
    }
  }

  async function createVendorPayment(page, mode = 'partial') {
    await openVendorPaymentCreatePage(page)

    const body = await page.textContent('body')
    expect(body).not.toMatch(ERR)

    await selectFixturePaymentRequest(page)

    const checked = page.locator('input[type="checkbox"][value]:checked:not([disabled])')
    const checkedCount = await checked.count()
    expect(checkedCount).toBeGreaterThan(0)

    if (mode === 'partial' && checkedCount > 1) {
      await checked.nth(0).uncheck({ force: true })
      await page.waitForTimeout(1000)
    }

    const cashRadio = page.locator('input[type="radio"][value="Cash"]').first()
    await expect(cashRadio).toBeVisible()
    await cashRadio.check({ force: true })
    await page.waitForTimeout(800)

    await ensureCoaSelected(page)

    const submitBtn = page.getByRole('button', { name: /^Buat$/ }).first()
    await expect(submitBtn).toBeVisible()
    await submitBtn.click({ force: true })

    await page.waitForLoadState('networkidle')
    await page.waitForTimeout(1200)

    const latestBody = await page.textContent('body')
    expect(latestBody).not.toMatch(/required|harus diisi|wajib/i)
  }

  async function expectPaymentRequestStatus(page, expectedLabelRegex) {
    await page.goto('/admin/payment-requests')
    await page.waitForLoadState('networkidle')

    const row = page.locator('tr, .fi-ta-row').filter({ hasText: FIXTURE.paymentRequestNumber }).first()
    await expect(row).toBeVisible()
    await expect(row).toContainText(expectedLabelRegex)
  }

  test('payment request status moves from partial to paid after staged vendor payments', async ({ page }) => {
    await createVendorPayment(page, 'partial')
    await expectPaymentRequestStatus(page, /Dibayar Sebagian|partial/i)

    await createVendorPayment(page, 'full')
    await expectPaymentRequestStatus(page, /Dibayar|paid/i)
  })
})
