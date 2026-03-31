import { test, expect } from '@playwright/test';

async function openAdminPage(page, path) {
  await page.goto(path);
  await page.waitForLoadState('networkidle');
}

test.describe('Manufacturing audit fixes', () => {
  test('sidebar order shows BOM above production plan and production plan above manufacturing order', async ({ page }) => {
    await openAdminPage(page, '/admin');

    const navLinks = await page.locator('aside a').evaluateAll((elements) => {
      return elements.map((element) => ({
        href: element.getAttribute('href') ?? '',
        text: element.textContent?.replace(/\s+/g, ' ').trim() ?? '',
      }));
    });

    const bomIndex = navLinks.findIndex((link) => /\/admin\/bill-of-material/i.test(link.href));
    const planIndex = navLinks.findIndex((link) => /\/admin\/production-plans/i.test(link.href));
    const moIndex = navLinks.findIndex((link) => /\/admin\/manufacturing-orders/i.test(link.href));

    expect(bomIndex).toBeGreaterThan(-1);
    expect(planIndex).toBeGreaterThan(-1);
    expect(moIndex).toBeGreaterThan(-1);
    expect(bomIndex).toBeLessThan(planIndex);
    expect(planIndex).toBeLessThan(moIndex);
  });

  test('manufacturing order create page loads with production plan selector', async ({ page }) => {
    await openAdminPage(page, '/admin/manufacturing-orders/create');

    await expect(page.locator('body')).toContainText('Rencana Produksi');
    await expect(page.locator('body')).not.toContainText(/fatal error|whoops|something went wrong/i);
  });

  test('production list shows product and quantity detail columns', async ({ page }) => {
    await openAdminPage(page, '/admin/productions');

    const emptyState = page.getByText('Tidak ada data yang ditemukan');
    if (await emptyState.count()) {
      await expect(page.locator('body')).not.toContainText(/fatal error|whoops|something went wrong/i);
      return;
    }

    await expect(page.getByRole('columnheader', { name: /^Qty Plan$/i })).toBeVisible();
    await expect(page.getByRole('columnheader', { name: /^Qty Produced$/i })).toBeVisible();
    await expect(page.getByRole('columnheader', { name: /^Product$/i })).toBeVisible();
  });

  test('quality control manufacture process action opens editable modal with product context', async ({ page }) => {
    await openAdminPage(page, '/admin/quality-control-manufactures');

    const processButton = page.getByRole('button', { name: /process qc/i }).first();
    if (await processButton.count() === 0) {
      await expect(page.locator('body')).not.toContainText(/fatal error|whoops|something went wrong/i);
      return;
    }

    await processButton.click();

    await expect(page.getByText('Produk')).toBeVisible();
    await expect(page.getByText('Referensi Produksi')).toBeVisible();
    await expect(page.getByText('Total Produksi')).toBeVisible();
    await expect(page.getByText('Passed Quantity')).toBeVisible();
    await expect(page.getByText('Rejected Quantity')).toBeVisible();
    await expect(page.getByText('Reason Reject')).toBeVisible();
  });
});