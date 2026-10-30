import { test, expect } from '@playwright/test';

async function navigate(page, path) {
  await page.goto(path);
  if (page.url().includes('/login')) {
    await page.locator('#data\.email').waitFor({ state: 'visible', timeout: 15_000 });
    await page.locator('#data\.email').fill('ralamzah@gmail.com');
    await page.locator('#data\.password').fill('ridho123');
    await page.locator('form').getByRole('button', { name: /masuk|login|sign in/i }).click();
    await page.waitForFunction(() => !window.location.pathname.endsWith('/login'), { timeout: 30_000 });
    await page.goto(path);
  }
  await page.waitForLoadState('networkidle');
}

test('user detail page shows permission search and pagination', async ({ page }) => {
  await navigate(page, '/admin/users/1');

  await expect(page.getByText('Kemampuan Berdasarkan Permission')).toBeVisible({ timeout: 10_000 });
  await expect(page.getByText('Ringkasan Akses')).toBeVisible();
  await expect(page.getByLabel('Cari permission')).toBeVisible();
  await expect(page.locator('button:has-text("Sebelumnya")')).toBeVisible({ timeout: 15_000 });
  await expect(page.locator('button:has-text("Berikutnya")')).toBeVisible();

  const permissionArea = page.locator('#user-permission-search').locator('xpath=ancestor::div[contains(@class, "space-y-3")]');
  const permissionTable = permissionArea.locator('table');

  const firstPermission = (await permissionTable.locator('tbody tr').first().locator('span').first().textContent())?.trim();
  expect(firstPermission).toBeTruthy();

  await page.getByLabel('Cari permission').fill(firstPermission);
  await expect(permissionTable.locator('tbody tr')).toHaveCount(1);
  await expect(permissionTable.locator('tbody tr').first()).toContainText(firstPermission);

  await page.getByLabel('Cari permission').fill('');
  const pageIndicator = permissionArea.locator('p.text-sm.text-gray-600').last();
  await expect(pageIndicator).toContainText('Halaman 1 dari');
  await permissionArea.locator('xpath=ancestor::div[contains(@class, "space-y-4") and @x-data][1]').evaluate((element) => {
    const component = element._x_dataStack && element._x_dataStack[0];

    if (component) {
      component.page = 2;
    }
  });
  await expect(pageIndicator).toContainText('Halaman 2 dari');
});