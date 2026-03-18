import { test, expect } from '@playwright/test'
import { execSync } from 'node:child_process'

test.use({ storageState: 'playwright/.auth/user.json' })

const PRODUCT_SKU = 'FG-OR-A2-PW-001'
const SUPPLIER_LOW_CODE = 'A2-PW-LOW'
const SUPPLIER_HIGH_CODE = 'A2-PW-HIGH'
const SUPPLIER_NULL_CODE = 'A2-PW-NUL'

function parseMoney(value) {
  if (!value) return 0
  return Number(String(value).replace(/\./g, '').replace(/,/g, '.'))
}

async function selectChoicesByLabel(page, labelText, searchText) {
  const wrapper = page
    .locator('.fi-fo-field-wrp')
    .filter({ has: page.locator(`label:has-text("${labelText}")`) })
    .first()

  await expect(wrapper).toBeVisible()
  await wrapper.locator('.choices__inner').first().click()

  const searchInput = wrapper.locator('.choices__input--cloned, .choices__input[type="search"]').first()
  await searchInput.fill(searchText)
  await page.waitForTimeout(350)

  let option = wrapper
    .locator('.choices__list--dropdown .choices__item--choice:not(.choices__placeholder):not(.is-disabled)')
    .filter({ hasText: searchText || /.+/ })
    .first()

  if ((await option.count()) === 0) {
    option = wrapper
      .locator('.choices__list--dropdown .choices__item--choice:not(.choices__placeholder):not(.is-disabled)')
      .first()
  }

  await expect(option).toBeVisible()
  await option.click()
}

async function selectRepeaterChoicesByLabel(page, labelText, searchText) {
  const row = page.locator('.fi-fo-repeater-item').first()
  await expect(row).toBeVisible()

  const wrapper = row
    .locator('.fi-fo-field-wrp')
    .filter({ has: page.locator(`label:has-text("${labelText}")`) })
    .first()

  await expect(wrapper).toBeVisible()
  await wrapper.locator('.choices__inner').first().click()

  const searchInput = wrapper.locator('.choices__input--cloned, .choices__input[type="search"]').first()
  await searchInput.fill(searchText)
  await page.waitForTimeout(350)

  let option = wrapper
    .locator('.choices__list--dropdown .choices__item--choice:not(.choices__placeholder):not(.is-disabled)')
    .filter({ hasText: searchText || /.+/ })
    .first()

  if ((await option.count()) === 0) {
    option = wrapper
      .locator('.choices__list--dropdown .choices__item--choice:not(.choices__placeholder):not(.is-disabled)')
      .first()
  }

  await expect(option).toBeVisible()
  await option.click()
}

test.beforeAll(async () => {
  execSync('php scripts/setup_order_request_a2_playwright_data.php', { stdio: 'inherit' })
})

test('A2: supplier recommendation and per-item supplier price fallback are correct', async ({ page }) => {
  await page.goto('/admin/order-requests/create')
  await page.waitForLoadState('networkidle')
  await expect(page).not.toHaveURL(/login/)

  await selectChoicesByLabel(page, 'Cabang', 'Cabang Pusat')
  await selectChoicesByLabel(page, 'Gudang', '')

  const existingRow = page.locator('.fi-fo-repeater-item').first()
  if (await existingRow.count() === 0) {
    const addItemCandidates = [
      page.getByRole('button', { name: /tambah item|add item/i }).first(),
      page.locator('.fi-fo-repeater button').filter({ hasText: /tambah|add/i }).first(),
      page.locator('button[wire\\:click*="add"], button[x-on\\:click*="add"]').first(),
    ]

    let clicked = false
    for (const button of addItemCandidates) {
      if (await button.count()) {
        await button.click()
        clicked = true
        break
      }
    }

    if (!clicked) {
      throw new Error('Unable to find add item button in OR repeater')
    }
  }

  await selectRepeaterChoicesByLabel(page, 'Product', PRODUCT_SKU)
  await page.waitForTimeout(700)

  const row = page.locator('.fi-fo-repeater-item').first()

  const recommendationField = row
    .locator('.fi-fo-field-wrp')
    .filter({ hasText: 'Rekomendasi Supplier' })
    .first()
  await expect(recommendationField).toContainText('Supplier A2 Harga Termurah')
  await expect(recommendationField).toContainText('Rp 100.000')

  const originalPriceInput = row.locator('input[id*="original_price"]').first()
  const unitPriceInput = row.locator('input[id*="unit_price"]').first()
  await expect(originalPriceInput).toHaveValue('125.000')
  await expect(unitPriceInput).toHaveValue('125.000')

  await selectRepeaterChoicesByLabel(page, 'Supplier', SUPPLIER_HIGH_CODE)
  await page.waitForTimeout(500)
  await expect(originalPriceInput).toHaveValue('145.000')
  await expect(unitPriceInput).toHaveValue('145.000')

  await selectRepeaterChoicesByLabel(page, 'Supplier', SUPPLIER_LOW_CODE)
  await page.waitForTimeout(500)
  await expect(originalPriceInput).toHaveValue('100.000')
  await expect(unitPriceInput).toHaveValue('100.000')

  await selectRepeaterChoicesByLabel(page, 'Supplier', SUPPLIER_NULL_CODE)
  await page.waitForTimeout(500)
  await expect(originalPriceInput).toHaveValue('100.000')
  await expect(unitPriceInput).toHaveValue('100.000')

  const fallbackOriginal = parseMoney(await originalPriceInput.inputValue())
  const fallbackUnit = parseMoney(await unitPriceInput.inputValue())
  expect(fallbackOriginal).toBe(100000)
  expect(fallbackUnit).toBe(100000)
})
