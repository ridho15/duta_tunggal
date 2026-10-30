import { test, expect } from '@playwright/test';

test.use({ storageState: 'playwright/.auth/user.json' });

const ADMIN_URL = '/admin/financial-statement';
const PREVIEW_QUERY = 'start_date=2026-04-01&end_date=2026-04-30&statement_type=all';
const PREVIEW_URL = `/reports/financial-statement/preview?${PREVIEW_QUERY}`;

test('financial statement standalone preview renders combined printable report', async ({ page }) => {
  await page.goto(PREVIEW_URL, { waitUntil: 'networkidle' });

  await expect(page.getByText('LAPORAN FINANCIAL STATEMENT')).toBeVisible();
  await expect(page.getByText('Laporan Laba Rugi')).toBeVisible();
  await expect(page.getByText('Neraca / Balance Sheet')).toBeVisible();
  await expect(page.locator('.fs-card-label', { hasText: 'Total Aset' }).first()).toBeVisible();
  await expect(page.locator('.fs-card-label', { hasText: 'Status Neraca' }).first()).toBeVisible();
  await expect(page.getByRole('link', { name: 'Download Excel' })).toBeVisible();
  await expect(page.getByRole('link', { name: 'Download PDF' })).toBeVisible();
});

test('financial statement admin preview embeds the standalone report', async ({ page }) => {
  await page.goto(`${ADMIN_URL}?preview=1&${PREVIEW_QUERY}`, { waitUntil: 'networkidle' });

  const frame = page.locator('iframe[title="Financial Statement Preview"]');
  await expect(frame).toBeVisible();

  const src = await frame.getAttribute('src');
  expect(src).toContain('/reports/financial-statement/preview');
  expect(src).toContain('statement_type=all');
});