import { test, expect } from '@playwright/test';

test.use({ storageState: 'playwright/.auth/user.json' });

const ADMIN_URL = '/admin/alk-grafik';
const QUERY = 'start_date=2026-04-01&end_date=2026-04-30';
const PREVIEW_URL = `/reports/alk-grafik/preview?${QUERY}`;

test('ALK admin page shows consistent filter layout and actions', async ({ page }) => {
  await page.goto(ADMIN_URL, { waitUntil: 'networkidle' });

  await expect(page.getByText('Filter Analisis Laporan Keuangan')).toBeVisible();
  await expect(page.locator('label').filter({ hasText: 'Tanggal Mulai' })).toBeVisible();
  await expect(page.locator('label').filter({ hasText: 'Tanggal Akhir' })).toBeVisible();
  await expect(page.locator('label').filter({ hasText: 'Cabang' })).toBeVisible();
  await expect(page.locator('input[type="date"]').nth(0)).toBeVisible();
  await expect(page.locator('input[type="date"]').nth(1)).toBeVisible();
  await expect(page.locator('select').first()).toBeVisible();
  await expect(page.getByRole('button', { name: 'Tampilkan Laporan' })).toBeVisible();
  await expect(page.getByRole('link', { name: 'Export Excel' })).toBeVisible();
  await expect(page.getByRole('link', { name: 'Export PDF' })).toBeVisible();
});

test('ALK header action opens standalone preview popup', async ({ page }) => {
  await page.goto(`${ADMIN_URL}?${QUERY}`, { waitUntil: 'networkidle' });

  const [popup] = await Promise.all([
    page.waitForEvent('popup'),
    page.getByRole('button', { name: 'Tampilkan Laporan' }).click(),
  ]);

  await popup.waitForLoadState('networkidle');
  await expect(popup.getByText('Laporan ALK Grafik')).toBeVisible();
  await expect(popup.getByRole('link', { name: 'Download Excel' })).toBeVisible();
  await expect(popup.getByRole('link', { name: 'Download PDF' })).toBeVisible();
});

test('ALK standalone preview renders summary, ratios, charts, and table', async ({ page }) => {
  await page.goto(PREVIEW_URL, { waitUntil: 'networkidle' });

  await expect(page.getByText('Duta Tunggal ERP')).toBeVisible();
  await expect(page.getByText('Laporan ALK Grafik')).toBeVisible();
  await expect(page.getByText('Rasio Keuangan Utama')).toBeVisible();
  await expect(page.getByText('Detail Tren Bulanan')).toBeVisible();
  await expect(page.locator('#trendChart')).toBeVisible();
  await expect(page.locator('#compositionChart')).toBeVisible();
  await expect(page.locator('th').filter({ hasText: /^Bulan$/i })).toBeVisible();
  await expect(page.locator('th').filter({ hasText: /^Pendapatan$/i })).toBeVisible();
});

test('ALK admin preview embeds standalone report via iframe', async ({ page }) => {
  await page.goto(`${ADMIN_URL}?preview=1&${QUERY}`, { waitUntil: 'networkidle' });

  const frame = page.locator('iframe[title="ALK Grafik Preview"]');
  await expect(frame).toBeVisible();

  const src = await frame.getAttribute('src');
  expect(src).toContain('/reports/alk-grafik/preview');
  expect(src).toContain('start_date=2026-04-01');
  expect(src).toContain('embedded=1');
});
