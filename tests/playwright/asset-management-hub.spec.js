import { test, expect } from '@playwright/test'

test('asset management hub renders hero and primary asset shortcuts', async ({ page }) => {
  await page.goto('/admin/asset-management-hub')
  await page.waitForLoadState('networkidle')

  const hub = page.locator('#asset-management-hub')

  await expect(hub).toBeVisible()
  await expect(page.locator('.hubv2-hero-title').first()).toHaveText('Pusat Manajemen Aset')
  await expect(hub.locator('[data-hub-card]')).toHaveCount(3)
  await expect(hub.getByRole('link', { name: /aset tetap master aset dan nilai buku/i })).toBeVisible()
  await expect(hub.getByRole('link', { name: /transfer aset perpindahan aset antar cabang/i })).toBeVisible()
  await expect(hub.getByRole('link', { name: /disposal aset penghapusan aset tetap/i })).toBeVisible()
})