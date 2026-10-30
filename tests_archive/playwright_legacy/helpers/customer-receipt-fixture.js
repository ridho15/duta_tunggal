import { execSync } from 'node:child_process'
import { expect } from '@playwright/test'

const CUSTOMER_LABEL = 'PW Customer Receipt - PT Maju Bersama'

export function ensureCustomerReceiptFixture() {
  execSync('php scripts/setup_customer_receipt_playwright_data.php', { stdio: 'inherit' })
}

async function selectFirstChoicesOption(page, labelText, searchTerm = '') {
  const label = page.locator(`label:has-text("${labelText}")`).first()
  const wrapper = page.locator('.fi-fo-field-wrp').filter({ has: label }).first()

  let combobox = null
  if (await wrapper.count()) {
    await expect(wrapper).toBeVisible()
    combobox = wrapper.getByRole('combobox').first()
  } else {
    combobox = page.getByRole('combobox').first()
  }

  await combobox.click()

  if (searchTerm) {
    const searchInput = page.locator('.choices__input--cloned:visible').first()
    if (await searchInput.isVisible().catch(() => false)) {
      await searchInput.fill(searchTerm)
      await page.waitForTimeout(600)
    }
  }

  const matchingItem = wrapper
    .locator('.choices__list--dropdown .choices__item--choice:not(.choices__placeholder):not(.is-disabled)')
    .filter({ hasText: searchTerm || labelText })
    .first()

  const globalMatchingItem = page
    .locator('.choices__list--dropdown .choices__item--choice:not(.choices__placeholder):not(.is-disabled):visible')
    .filter({ hasText: searchTerm || labelText })
    .first()

  const fallbackItem = wrapper.locator('.choices__list--dropdown .choices__item--choice:not(.choices__placeholder):not(.is-disabled)').first()
  const globalFallbackItem = page.locator('.choices__list--dropdown .choices__item--choice:not(.choices__placeholder):not(.is-disabled):visible').first()

  let targetItem = fallbackItem

  if (await matchingItem.count()) {
    targetItem = matchingItem
  } else if (await globalMatchingItem.count()) {
    targetItem = globalMatchingItem
  } else if (await globalFallbackItem.count()) {
    targetItem = globalFallbackItem
  }

  await expect(targetItem).toBeVisible({ timeout: 10000 })
  await targetItem.click({ force: true })
  await page.waitForTimeout(600)
}

export async function chooseFixtureCustomer(page) {
  await selectFirstChoicesOption(page, 'Customer', CUSTOMER_LABEL)
}
