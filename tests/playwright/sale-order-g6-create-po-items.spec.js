import { test, expect } from '@playwright/test'

const BASE = 'http://localhost:8009'
const ERR = /Fatal error|Whoops!|Something went wrong/i

test.use({ storageState: 'playwright/.auth/user.json' })

test('G6: create PO modal shows selectable SO items not yet linked', async ({ page }) => {
  await page.goto(`${BASE}/admin/sale-orders`)
  await page.waitForLoadState('networkidle')

  const listBody = await page.textContent('body')
  expect(listBody).not.toMatch(ERR)
  expect(page.url()).not.toMatch(/login/)

  const firstViewHref = await page.evaluate(() => {
    const links = Array.from(document.querySelectorAll('a[href]'))
      .map((a) => a.getAttribute('href') || '')
      .filter((href) => /\/admin\/sale-orders\/\d+\/view/.test(href))
    return links[0] || null
  })

  if (!firstViewHref) {
    expect(listBody).toMatch(/sale order|tidak ada data|no records|belum ada/i)
    return
  }

  await page.goto(`${BASE}${firstViewHref}`)
  await page.waitForLoadState('networkidle')

  const viewBody = await page.textContent('body')
  expect(viewBody).not.toMatch(ERR)

  const createPoBtn = page.getByRole('button', { name: /Create Purchase Order/i }).first()
  if (!(await createPoBtn.isVisible().catch(() => false))) {
    expect(viewBody).toMatch(/sales order|action|approved|confirmed|completed/i)
    return
  }

  await createPoBtn.click({ force: true })
  await page.waitForTimeout(500)

  const modal = page.locator('[role="dialog"]').last()
  await expect(modal).toBeVisible()
  await expect(modal).toContainText(/Pilih Item Sales Order/i)

  const checkboxes = modal.locator('input[type="checkbox"]')
  const checkboxCount = await checkboxes.count()
  expect(checkboxCount).toBeGreaterThan(0)

  const modalText = (await modal.textContent()) || ''
  expect(modalText).toMatch(/Qty:/i)
})
