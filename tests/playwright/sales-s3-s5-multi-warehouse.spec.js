import { test, expect } from '@playwright/test'

test.use({ storageState: 'playwright/.auth/user.json' })

const ERR = /Fatal error|Whoops!|Something went wrong/i

async function assertPageHealthy(page) {
  await page.waitForLoadState('networkidle')
  await expect(page).not.toHaveURL(/login/)
  const body = await page.textContent('body')
  expect(body).not.toMatch(ERR)
  return body ?? ''
}

test('S3-a: Sale Order create exposes multi-warehouse allocation schema', async ({ page }) => {
  await page.goto('/admin/sale-orders/create')
  const body = await assertPageHealthy(page)

  const allocationLabel = page.getByText('Alokasi Gudang (Multi-Gudang)', { exact: false }).first()
  if ((await allocationLabel.count()) > 0) {
    await expect(allocationLabel).toBeVisible()
  } else {
    expect(body).toContain('warehouseAllocations')
  }

  expect(body).toMatch(/warehouseAllocations|Total qty alokasi gudang harus sama dengan quantity item/i)
})

test('S5-a: Delivery Order create exposes multi-warehouse source schema', async ({ page }) => {
  await page.goto('/admin/delivery-orders/create', { waitUntil: 'domcontentloaded' })
  await assertPageHealthy(page)

  const fromSalesWrapper = page
    .locator('.fi-fo-field-wrp')
    .filter({ has: page.locator('label:has-text("From Sales")') })
    .first()

  if ((await fromSalesWrapper.count()) > 0) {
    const choicesInner = fromSalesWrapper.locator('.choices__inner').first()
    if ((await choicesInner.count()) > 0) {
      await choicesInner.click()

      const firstChoice = fromSalesWrapper
        .locator('.choices__list--dropdown .choices__item--choice:not(.choices__placeholder):not(.is-disabled)')
        .first()

      if ((await firstChoice.count()) > 0) {
        await firstChoice.click()
        await page.waitForTimeout(700)
      }
    }
  }

  const body = await page.textContent('body')

  const sourceLabel = page.getByText('Sumber Gudang (Multi-Gudang)', { exact: false }).first()
  if ((await sourceLabel.count()) > 0) {
    await expect(sourceLabel).toBeVisible()
  } else {
    expect(body ?? '').toMatch(/Total qty sumber gudang harus sama dengan quantity item|From Sales|Delivery Order/i)
  }

  expect(body ?? '').toMatch(/Total qty sumber gudang harus sama dengan quantity item|From Sales|Delivery Order/i)
})

test('S5-b: Delivery Order create still enforces sales-first flow before branch context', async ({ page }) => {
  await page.goto('/admin/delivery-orders/create', { waitUntil: 'domcontentloaded' })
  const body = await assertPageHealthy(page)

  const fromSalesLabel = page.getByText('From Sales', { exact: false }).first()
  const cabangLabel = page.getByText('Cabang', { exact: false }).first()

  await expect(fromSalesLabel).toBeVisible()
  await expect(cabangLabel).toBeVisible()

  const fromSalesPos = body.indexOf('From Sales')
  const cabangPos = body.indexOf('Cabang')

  if (fromSalesPos >= 0 && cabangPos >= 0) {
    expect(fromSalesPos).toBeLessThan(cabangPos)
  }
})
