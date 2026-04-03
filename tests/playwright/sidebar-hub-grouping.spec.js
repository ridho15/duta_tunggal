import { test, expect } from '@playwright/test';

// ── Helper ────────────────────────────────────────────────────────────────────
/**
 * Verify the v2 hub design for a given page.
 * Asserts: hero section visible, correct title, min card count, all links reachable.
 */
async function assertHubV2(page, { url, id, title, minCards }) {
  await page.goto(url);
  await page.waitForLoadState('networkidle');

  const hub  = page.locator(id);
  const hero = page.locator('.hubv2-hero').first();

  await expect(hub).toBeVisible({ timeout: 10000 });
  await expect(hero).toBeVisible({ timeout: 5000 });
  await expect(page.locator('.hubv2-hero-title').first()).toHaveText(title, { timeout: 5000 });

  const cards = hub.locator('[data-hub-card]');
  const count = await cards.count();
  expect(count).toBeGreaterThanOrEqual(minCards);

  // Every card must have a non-empty href
  for (let i = 0; i < count; i++) {
    const href = await cards.nth(i).getAttribute('href');
    expect(href).toBeTruthy();
  }
}

test.describe('Hub Pages – v2 Design & Navigation', () => {
  // ── Design regression ────────────────────────────────────────────────────

  test('purchase hub renders v2 hero and 5 nav cards', async ({ page }) => {
    await assertHubV2(page, {
      url: '/admin/purchase-hub', id: '#purchase-hub',
      title: 'Pusat Pembelian', minCards: 5,
    });
    const hub = page.locator('#purchase-hub');
    await expect(hub.getByRole('link', { name: /permintaan pembelian/i })).toBeVisible();
    await expect(hub.getByRole('link', { name: /pesanan pembelian/i })).toBeVisible();
    await expect(hub.locator('.hubv2-cd').first()).not.toBeEmpty();
  });

  test('finance purchase hub renders v2 hero and 2 nav cards', async ({ page }) => {
    await assertHubV2(page, {
      url: '/admin/finance-purchase-hub', id: '#finance-purchase-hub',
      title: 'Pusat Keuangan Pembelian', minCards: 2,
    });
  });

  test('finance sales hub renders v2 hero and 3 nav cards', async ({ page }) => {
    await assertHubV2(page, {
      url: '/admin/finance-sales-hub', id: '#finance-sales-hub',
      title: 'Pusat Keuangan Penjualan', minCards: 3,
    });
  });

  test('warehouse hub renders v2 hero, section headers, and 7 nav cards', async ({ page }) => {
    await assertHubV2(page, {
      url: '/admin/warehouse-hub', id: '#warehouse-hub',
      title: 'Pusat Gudang', minCards: 7,
    });
    const hub = page.locator('#warehouse-hub');
    await expect(hub.locator('.hubv2-sh-name').first()).toBeVisible();
    await expect(hub.locator('.hubv2-sh')).toHaveCount(2);
  });

  test('delivery hub renders v2 hero and 3 nav cards', async ({ page }) => {
    await assertHubV2(page, {
      url: '/admin/delivery-hub', id: '#delivery-hub',
      title: 'Pusat Pengiriman', minCards: 3,
    });
  });

  test('accounting hub renders v2 hero, section headers, and 6 nav cards', async ({ page }) => {
    await assertHubV2(page, {
      url: '/admin/accounting-hub', id: '#accounting-hub',
      title: 'Pusat Akuntansi', minCards: 6,
    });
    const hub = page.locator('#accounting-hub');
    await expect(hub.locator('.hubv2-sh')).toHaveCount(2);
  });

  test('payment hub renders v2 hero and 6 nav cards', async ({ page }) => {
    await assertHubV2(page, {
      url: '/admin/payment-hub', id: '#payment-hub',
      title: 'Pusat Pembayaran', minCards: 6,
    });
  });

  test('finance report hub renders v2 hero, section headers, and 13 nav cards', async ({ page }) => {
    await assertHubV2(page, {
      url: '/admin/finance-reports', id: '#finance-report-hub',
      title: 'Laporan Keuangan', minCards: 13,
    });
    const hub = page.locator('#finance-report-hub');
    await expect(hub.locator('.hubv2-sh')).toHaveCount(2);
  });

  test('operational reports hub renders v2 hero and 2 nav cards', async ({ page }) => {
    await assertHubV2(page, {
      url: '/admin/operational-reports', id: '#operational-report-hub',
      title: 'Laporan Operasional', minCards: 2,
    });
  });
});

test.describe('Sidebar Hub Grouping', () => {
  test('dashboard finance is no longer shown inside the finance report group', async ({ page }) => {
    await page.goto('/admin');
    await page.waitForLoadState('networkidle');

    const sidebar = page.getByRole('complementary');

    await expect(sidebar.getByRole('link', { name: /dashboard finance/i })).toBeVisible({ timeout: 10000 });
    await expect(sidebar.getByRole('link', { name: /laporan keuangan/i })).toBeVisible();
  });

  test('accounting and warehouse hubs are visible in sidebar', async ({ page }) => {
    await page.goto('/admin');
    await page.waitForLoadState('networkidle');

    await expect(page.getByRole('link', { name: /pusat akuntansi/i }).first()).toBeVisible({ timeout: 10000 });
    await expect(page.getByRole('link', { name: /pusat gudang/i }).first()).toBeVisible({ timeout: 10000 });
    await expect(page.getByRole('link', { name: /pusat pembelian/i }).first()).toBeVisible({ timeout: 10000 });
    await expect(page.getByRole('link', { name: /pusat pengiriman/i }).first()).toBeVisible({ timeout: 10000 });
    await expect(page.getByRole('link', { name: /pusat keuangan penjualan/i }).first()).toBeVisible({ timeout: 10000 });
    await expect(page.getByRole('link', { name: /pusat keuangan pembelian/i }).first()).toBeVisible({ timeout: 10000 });
    await expect(page.getByRole('link', { name: /pusat pembayaran/i }).first()).toBeVisible({ timeout: 10000 });
  });

  test('accounting hub page renders quick links while detailed accounting sidebar links stay hidden', async ({ page }) => {
    await page.goto('/admin/accounting-hub');
    await page.waitForLoadState('networkidle');

    const sidebar = page.getByRole('complementary');
    const hub = page.locator('#accounting-hub');

    await expect(hub).toBeVisible({ timeout: 10000 });
    await expect(hub.getByRole('link', { name: /journal entries/i }).first()).toBeVisible();
    await expect(hub.getByRole('link', { name: /rekonsiliasi bank/i })).toBeVisible();
    await expect(sidebar.getByRole('link', { name: /journal entry/i })).toHaveCount(0);
    await expect(sidebar.getByRole('link', { name: /pengajuan voucher/i })).toHaveCount(0);
  });

  test('warehouse hub page renders quick links while detailed warehouse sidebar links stay hidden', async ({ page }) => {
    await page.goto('/admin/warehouse-hub');
    await page.waitForLoadState('networkidle');

    const sidebar = page.getByRole('complementary');
    const hub = page.locator('#warehouse-hub');

    await expect(hub).toBeVisible({ timeout: 10000 });
    await expect(hub.getByRole('link', { name: /stock transfer/i })).toBeVisible();
    await expect(hub.getByRole('link', { name: /inventory stock/i })).toBeVisible();
    await expect(sidebar.getByRole('link', { name: /stock transfer/i })).toHaveCount(0);
    await expect(sidebar.getByRole('link', { name: /stock opname/i })).toHaveCount(0);
  });

  test('purchase hub page renders quick links while detailed purchase sidebar links stay hidden', async ({ page }) => {
    await page.goto('/admin/purchase-hub');
    await page.waitForLoadState('networkidle');

    const sidebar = page.getByRole('complementary');
    const hub = page.locator('#purchase-hub');

    await expect(hub).toBeVisible({ timeout: 10000 });
    await expect(hub.getByRole('link', { name: /permintaan pembelian/i })).toBeVisible();
    await expect(hub.getByRole('link', { name: /pesanan pembelian/i })).toBeVisible();
    await expect(sidebar.getByRole('link', { name: /pesanan pembelian/i })).toHaveCount(0);
    await expect(sidebar.getByRole('link', { name: /kontrol kualitas pembelian/i })).toHaveCount(0);
  });

  test('delivery hub page renders quick links while detailed delivery sidebar links stay hidden', async ({ page }) => {
    await page.goto('/admin/delivery-hub');
    await page.waitForLoadState('networkidle');

    const sidebar = page.getByRole('complementary');
    const hub = page.locator('#delivery-hub');

    await expect(hub).toBeVisible({ timeout: 10000 });
    await expect(hub.getByRole('link', { name: /perintah pengiriman/i })).toBeVisible();
    await expect(hub.getByRole('link', { name: /surat jalan/i })).toBeVisible();
    await expect(sidebar.getByRole('link', { name: /perintah pengiriman/i })).toHaveCount(0);
    await expect(sidebar.getByRole('link', { name: /surat jalan/i })).toHaveCount(0);
  });

  test('finance sales hub page renders quick links while detailed finance sales sidebar links stay hidden', async ({ page }) => {
    await page.goto('/admin/finance-sales-hub');
    await page.waitForLoadState('networkidle');

    const sidebar = page.getByRole('complementary');
    const hub = page.locator('#finance-sales-hub');

    await expect(hub).toBeVisible({ timeout: 10000 });
    await expect(hub.getByRole('link', { name: /piutang usaha/i })).toBeVisible();
    await expect(hub.getByRole('link', { name: /invoice penjualan/i })).toBeVisible();
    await expect(sidebar.getByRole('link', { name: /piutang usaha|account receivable/i })).toHaveCount(0);
    await expect(sidebar.getByRole('link', { name: /invoice penjualan/i })).toHaveCount(0);
  });

  test('finance purchase hub page renders quick links while detailed finance purchase sidebar links stay hidden', async ({ page }) => {
    await page.goto('/admin/finance-purchase-hub');
    await page.waitForLoadState('networkidle');

    const sidebar = page.getByRole('complementary');
    const hub = page.locator('#finance-purchase-hub');

    await expect(hub).toBeVisible({ timeout: 10000 });
    await expect(hub.getByRole('link', { name: /utang usaha/i })).toBeVisible();
    await expect(hub.getByRole('link', { name: /invoice pembelian/i })).toBeVisible();
    await expect(sidebar.getByRole('link', { name: /utang usaha|account payable/i })).toHaveCount(0);
    await expect(sidebar.getByRole('link', { name: /invoice pembelian/i })).toHaveCount(0);
  });

  test('payment hub page renders quick links while detailed payment sidebar links stay hidden', async ({ page }) => {
    await page.goto('/admin/payment-hub');
    await page.waitForLoadState('networkidle');

    const sidebar = page.getByRole('complementary');
    const hub = page.locator('#payment-hub');

    await expect(hub).toBeVisible({ timeout: 10000 });
    await expect(hub.getByRole('link', { name: /permintaan pembayaran/i })).toBeVisible();
    await expect(hub.getByRole('link', { name: /transfer kas & bank/i })).toBeVisible();
    await expect(sidebar.getByRole('link', { name: /permintaan pembayaran/i })).toHaveCount(0);
    await expect(sidebar.getByRole('link', { name: /transfer kas & bank/i })).toHaveCount(0);
  });

  test('sales and manufacturing resources remain accessible after child label cleanup', async ({ page }) => {
    await page.goto('/admin/sale-orders');
    await page.waitForLoadState('networkidle');

    const sidebar = page.getByRole('complementary');

    await expect(page).toHaveURL(/\/admin\/sale-orders/);
    await expect(sidebar.getByRole('link', { name: /pesanan penjualan/i }).first()).toBeVisible({ timeout: 10000 });

    await page.goto('/admin/manufacturing-orders');
    await page.waitForLoadState('networkidle');

    await expect(page).toHaveURL(/\/admin\/manufacturing-orders/);
    await expect(sidebar.getByRole('link', { name: /perintah produksi/i }).first()).toBeVisible({ timeout: 10000 });
  });
});