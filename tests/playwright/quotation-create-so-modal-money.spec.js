import { test, expect } from '@playwright/test'

test.use({ storageState: 'playwright/.auth/user.json' })

const ERR = /Fatal error|Whoops!|Something went wrong|DateMalformedStringException/i

async function assertHealthy(page) {
  await page.waitForLoadState('networkidle')
  await expect(page).not.toHaveURL(/login/)
  const body = await page.textContent('body')
  expect(body || '').not.toMatch(ERR)
  return body || ''
}

async function openCreateSoModal(page) {
  await page.goto('/admin/quotations')
  const body = await assertHealthy(page)

  const viewHref = await page.evaluate(() => {
    const links = Array.from(document.querySelectorAll('a[href]'))
      .map((a) => a.getAttribute('href') || '')
      .filter((href) => /\/admin\/quotations\/\d+\/view/.test(href))
    return links[0] || null
  })

  if (!viewHref) {
    expect(body).toMatch(/quotation|tidak ada data|no records|belum ada/i)
    return null
  }

  await page.goto(viewHref)
  const viewBody = await assertHealthy(page)

  const createSoButton = page.getByRole('button', { name: /Buat Sales Order/i }).first()
  if (!(await createSoButton.isVisible().catch(() => false))) {
    expect(viewBody).toMatch(/quotation|approve|request|status/i)
    return null
  }

  await createSoButton.click({ force: true })
  const modal = page.locator('[role="dialog"]').last()
  await expect(modal).toBeVisible()

  await expect(modal.getByText('Tax Amount', { exact: false })).toBeVisible()
  await expect(modal.getByText('Subtotal', { exact: false })).toBeVisible()

  return modal
}

function toNumber(value) {
  return Number((value || '').replace(/\./g, '')) || 0
}

test('Quotation create SO modal: live typing formats unit price as Rupiah and updates tax/subtotal', async ({ page }) => {
  const modal = await openCreateSoModal(page)
  if (!modal) return

  const unitPriceInput = modal.locator('input[id*="saleOrderItems"][id*="unit_price"]').first()
  const taxInput = modal.locator('input[id*="saleOrderItems"][id*="tax"]:not([id*="tax_nominal"])').first()
  const taxAmountInput = modal.locator('input[id*="saleOrderItems"][id*="tax_nominal"]').first()
  const subtotalInput = modal.locator('input[id*="saleOrderItems"][id*="subtotal"]').first()

  await expect(unitPriceInput).toBeVisible()
  await expect(taxInput).toBeVisible()
  await expect(taxAmountInput).toBeVisible()
  await expect(subtotalInput).toBeVisible()

  await expect(subtotalInput).toHaveAttribute('readonly', '')
  await expect(taxAmountInput).toHaveAttribute('readonly', '')

  await unitPriceInput.click({ clickCount: 3 })
  await unitPriceInput.press('Control+a')
  await unitPriceInput.press('Delete')
  await page.keyboard.insertText('100000')
  await expect.poll(async () => await unitPriceInput.inputValue(), { timeout: 5000 }).toBe('100.000')

  await taxInput.click({ clickCount: 3 })
  await taxInput.fill('11')
  await taxInput.press('Tab')
  await page.waitForTimeout(300)

  const unitPriceValue = await unitPriceInput.inputValue()
  const subtotalValue = await subtotalInput.inputValue()
  const taxAmountValue = await taxAmountInput.inputValue()

  expect(unitPriceValue).toBe('100.000')
  expect(subtotalValue).toMatch(/^[\d.]+$/)
  expect(taxAmountValue).toMatch(/^[\d.]+$/)
  expect(toNumber(subtotalValue)).toBeGreaterThan(0)
  expect(toNumber(taxAmountValue)).toBeGreaterThanOrEqual(0)
})

test('Quotation create SO modal: prefilled nominal (non-live) uses Rupiah format', async ({ page }) => {
  const modal = await openCreateSoModal(page)
  if (!modal) return

  const unitPriceInput = modal.locator('input[id*="saleOrderItems"][id*="unit_price"]').first()
  const taxAmountInput = modal.locator('input[id*="saleOrderItems"][id*="tax_nominal"]').first()
  const subtotalInput = modal.locator('input[id*="saleOrderItems"][id*="subtotal"]').first()

  await expect(unitPriceInput).toBeVisible()
  await expect(taxAmountInput).toBeVisible()
  await expect(subtotalInput).toBeVisible()

  const unitPriceValue = await unitPriceInput.inputValue()
  const taxAmountValue = await taxAmountInput.inputValue()
  const subtotalValue = await subtotalInput.inputValue()

  expect(unitPriceValue).toMatch(/^[\d.]*$/)
  expect(taxAmountValue).toMatch(/^[\d.]*$/)
  expect(subtotalValue).toMatch(/^[\d.]*$/)

  if (toNumber(unitPriceValue) >= 1000) {
    expect(unitPriceValue).toContain('.')
  }
  if (toNumber(subtotalValue) >= 1000) {
    expect(subtotalValue).toContain('.')
  }
})

test('Quotation create SO modal: fill+blur (paste-like) keeps Rupiah format on unit price', async ({ page }) => {
  const modal = await openCreateSoModal(page)
  if (!modal) return

  const unitPriceInput = modal.locator('input[id*="saleOrderItems"][id*="unit_price"]').first()
  const subtotalInput = modal.locator('input[id*="saleOrderItems"][id*="subtotal"]').first()

  await expect(unitPriceInput).toBeVisible()
  await expect(subtotalInput).toBeVisible()

  await unitPriceInput.fill('2500000')
  await unitPriceInput.press('Tab')

  await expect.poll(async () => await unitPriceInput.inputValue(), { timeout: 5000 }).toBe('2.500.000')
  const subtotalValue = await subtotalInput.inputValue()
  expect(subtotalValue).toMatch(/^[\d.]+$/)
  expect(toNumber(subtotalValue)).toBeGreaterThan(0)
})

test('Quotation id 2: create SO modal shows Tax Amount and Rupiah format on price fields', async ({ page }) => {
  await page.goto('/admin/quotations/2/view')
  const body = await assertHealthy(page)

  if (page.url().includes('/404') || /\b404\b|not found/i.test(body)) {
    expect(body).toMatch(/404|not found/i)
    return
  }

  const createSoButton = page.getByRole('button', { name: /Buat Sales Order/i }).first()
  if (!(await createSoButton.isVisible().catch(() => false))) {
    expect(body).toMatch(/quotation|approve|status/i)
    return
  }

  await createSoButton.click({ force: true })
  const modal = page.locator('[role="dialog"]').last()
  await expect(modal).toBeVisible()
  await expect(modal.getByText('Tax Amount', { exact: false })).toBeVisible()
  await expect(modal.getByText('Subtotal', { exact: false })).toBeVisible()

  const unitPriceInput = modal.locator('input[id*="saleOrderItems"][id*="unit_price"]').first()
  const taxAmountInput = modal.locator('input[id*="saleOrderItems"][id*="tax_nominal"]').first()
  const subtotalInput = modal.locator('input[id*="saleOrderItems"][id*="subtotal"]').first()

  await expect(unitPriceInput).toBeVisible()
  await expect(taxAmountInput).toBeVisible()
  await expect(subtotalInput).toBeVisible()
  await expect(taxAmountInput).toHaveAttribute('readonly', '')
  await expect(subtotalInput).toHaveAttribute('readonly', '')

  await unitPriceInput.click({ clickCount: 3 })
  await unitPriceInput.fill('100000')
  await unitPriceInput.press('Tab')

  await expect.poll(async () => await unitPriceInput.inputValue(), { timeout: 5000 }).toBe('100.000')

  const taxAmountValue = await taxAmountInput.inputValue()
  const subtotalValue = await subtotalInput.inputValue()

  expect(taxAmountValue === '' || /^[\d.]+$/.test(taxAmountValue)).toBe(true)
  expect(subtotalValue === '' || /^[\d.]+$/.test(subtotalValue)).toBe(true)
})

test('Quotation id 1 regression: prefilled unit price is not inflated (12500000 -> 12.500.000)', async ({ page }) => {
  await page.goto('/admin/quotations/1/view')
  const body = await assertHealthy(page)

  if (page.url().includes('/404') || /\b404\b|not found/i.test(body)) {
    expect(body).toMatch(/404|not found/i)
    return
  }

  const createSoButton = page.getByRole('button', { name: /Buat Sales Order/i }).first()
  if (!(await createSoButton.isVisible().catch(() => false))) {
    expect(body).toMatch(/quotation|approve|status/i)
    return
  }

  await createSoButton.click({ force: true })
  const modal = page.locator('[role="dialog"]').last()
  await expect(modal).toBeVisible()

  const unitPriceInput = modal.locator('input[id*="saleOrderItems"][id*="unit_price"]').first()
  const taxAmountInput = modal.locator('input[id*="saleOrderItems"][id*="tax_nominal"]').first()
  const subtotalInput = modal.locator('input[id*="saleOrderItems"][id*="subtotal"]').first()

  await expect(unitPriceInput).toBeVisible()
  await expect(taxAmountInput).toBeVisible()
  await expect(subtotalInput).toBeVisible()

  await expect.poll(async () => await unitPriceInput.inputValue(), { timeout: 5000 }).toBe('12.500.000')
  await expect.poll(async () => await taxAmountInput.inputValue(), { timeout: 5000 }).toBe('13.750.000')
  await expect.poll(async () => await subtotalInput.inputValue(), { timeout: 5000 }).toBe('138.750.000')
})
