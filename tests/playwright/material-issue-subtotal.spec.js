import { test, expect } from '@playwright/test'

const BASE = 'http://localhost:8009'
const ERR = /Fatal error|Whoops!|Something went wrong/i

test.use({ storageState: 'playwright/.auth/user.json' })
test.setTimeout(30000)

async function navigate(page, path) {
  await page.goto(path)
  await page.waitForLoadState('domcontentloaded')

  if (page.url().includes('/login')) {
    await page.getByLabel('Alamat email').fill('superadmin@gmail.com')
    await page.getByLabel('Kata sandi').fill('superadmin')
    await page.locator('form').getByRole('button', { name: /masuk|login|sign in/i }).click()
    await page.waitForFunction(() => !window.location.pathname.endsWith('/login'), { timeout: 30000 })
    await page.goto(path)
    await page.waitForLoadState('domcontentloaded')
  }

  await page.waitForLoadState('networkidle')
}

test('Material Issue edit saves without subtotal numeric validation error', async ({ page }) => {
  // Open a known material issue edit page (ID 1) and attempt save
  await navigate(page, `${BASE}/admin/material-issues/1/edit`)
  await expect(page.locator('body')).not.toContainText(ERR)

  const editForm = page.locator('form').filter({ has: page.getByLabel('Nomor Issue') }).first()
  await expect(editForm).toBeVisible({ timeout: 10000 })
  await editForm.evaluate((form) => form.requestSubmit())
  await page.waitForLoadState('networkidle')

  // The page should not show the Indonesian numeric validation error
  await expect(page.locator('body')).not.toContainText('Subtotal harus berupa angka.')
})
