import { test, expect } from '@playwright/test'

test.use({ storageState: 'playwright/.auth/user.json' })

const ERR = /DateMalformedStringException|Fatal error|Whoops!|Something went wrong/i

async function ensureHealthy(page) {
  await page.waitForLoadState('networkidle')
  await expect(page).not.toHaveURL(/login/)
  const body = await page.textContent('body')
  expect(body || '').not.toMatch(ERR)
  return body || ''
}

test('PR-10-a: payment request create page loads without DateMalformedStringException', async ({ page }) => {
  await page.goto('/admin/payment-requests/create')
  const body = await ensureHealthy(page)
  expect(body).toMatch(/payment request|permintaan pembayaran|supplier|tanggal/i)
})

test('PR-10-b: payment request edit save-path does not trigger DateMalformedStringException', async ({ page }) => {
  await page.goto('/admin/payment-requests')
  const listBody = await ensureHealthy(page)

  const editLink = page
    .locator('a[href*="/admin/payment-requests/"]')
    .filter({ hasNotText: 'create' })
    .first()

  if ((await editLink.count()) === 0) {
    expect(listBody).toMatch(/payment request|tidak ada data|no records/i)
    return
  }

  const href = await editLink.getAttribute('href')
  if (!href) {
    expect(listBody).toMatch(/payment request|tidak ada data|no records/i)
    return
  }

  const editUrl = href.includes('/edit') ? href : `${href.replace(/\/$/, '')}/edit`
  await page.goto(editUrl)
  await ensureHealthy(page)

  const saveButton = page
    .locator('button')
    .filter({ hasText: /simpan|save/i })
    .first()

  if ((await saveButton.count()) > 0) {
    await expect(saveButton).toBeVisible()
    await saveButton.click()
    await page.waitForTimeout(900)
  }

  const bodyAfterSave = await page.textContent('body')
  expect(bodyAfterSave || '').not.toMatch(ERR)
})
