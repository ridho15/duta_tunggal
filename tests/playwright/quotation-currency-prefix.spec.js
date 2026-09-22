import { test, expect } from '@playwright/test'
import { execSync } from 'node:child_process'

function setupFixture() {
  return JSON.parse(execSync('php scripts/setup_quotation_currency_prefix_playwright_data.php', { encoding: 'utf8' }))
}

async function selectFirstVisibleProduct(page) {
  const productSelect = page.locator('select[id*="product_id"]:visible').first()
  if (await productSelect.isVisible().catch(() => false)) {
    const optionCount = await productSelect.locator('option').count()
    if (optionCount > 1) {
      await productSelect.selectOption({ index: 1 })
      await page.waitForTimeout(600)
    }
  }
}

test('quotation item currency prefix follows USD order request context', async ({ page }) => {
  const fixture = setupFixture()

  await page.goto(`/admin/quotations/create?order_request_id=${fixture.order_request_id}`)
  await page.waitForLoadState('networkidle')
  await expect(page).not.toHaveURL(/login/)

  const currencySelect = page.locator('#data\\.currency_id')
  await expect(currencySelect).toHaveValue(String(fixture.currency_id))

  const addButton = page.getByRole('button', { name: /Tambah Items|Add Items/i }).first()
  if (await addButton.isVisible().catch(() => false)) {
    await addButton.click()
    await page.waitForTimeout(600)
  }

  await selectFirstVisibleProduct(page)

  const unitPrice = page.locator('input[id*="quotationItem"][id*="unit_price"]:not([type="hidden"]):visible').first()
  await expect(unitPrice).toBeVisible()

  const wrapperText = await unitPrice.evaluate((element) => {
    let cursor = element.parentElement
    const texts = []

    for (let index = 0; cursor && index < 6; index += 1) {
      texts.push(cursor.innerText || cursor.textContent || '')
      cursor = cursor.parentElement
    }

    return texts.join('\n')
  })
  expect(wrapperText).toContain(fixture.currency_symbol)
  expect(wrapperText).not.toContain('Rp')
})
