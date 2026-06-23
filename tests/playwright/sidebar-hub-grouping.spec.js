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
    expect(href).not.toMatch(/\/admin\/.+-hub$/);
  }
}

test.describe('Hub Pages – v2 Design & Navigation', () => {
  // ── Design regression ────────────────────────────────────────────────────

  test('purchase hub renders v2 hero and flattened direct-menu cards', async ({ page }) => {
    await assertHubV2(page, {
      url: '/admin/purchase-hub', id: '#purchase-hub',
      title: 'Pembelian', minCards: 13,
    });
    const hub = page.locator('#purchase-hub');
    await expect(hub.getByRole('link', { name: /permintaan pembelian/i })).toBeVisible();
    await expect(hub.getByRole('link', { name: /pesanan pembelian/i })).toBeVisible();
    await expect(hub.getByRole('link', { name: /utang usaha/i })).toBeVisible();
    await expect(hub.getByRole('link', { name: /invoice pembelian/i })).toBeVisible();
    await expect(hub.locator('.hubv2-cd').first()).not.toBeEmpty();
  });

  test('finance purchase hub renders v2 hero and 2 nav cards', async ({ page }) => {
    await assertHubV2(page, {
      url: '/admin/finance-purchase-hub', id: '#finance-purchase-hub',
      title: 'Keuangan Pembelian', minCards: 2,
    });
  });

  test('finance sales hub renders v2 hero and 3 nav cards', async ({ page }) => {
    await assertHubV2(page, {
      url: '/admin/finance-sales-hub', id: '#finance-sales-hub',
      title: 'Keuangan Penjualan', minCards: 3,
    });
  });

  test('warehouse hub renders v2 hero, section headers, and 7 nav cards', async ({ page }) => {
    await assertHubV2(page, {
      url: '/admin/warehouse-hub', id: '#warehouse-hub',
      title: 'Gudang', minCards: 7,
    });
    const hub = page.locator('#warehouse-hub');
    await expect(hub.locator('.hubv2-sh-name').first()).toBeVisible();
    await expect(hub.locator('.hubv2-sh')).toHaveCount(2);
  });

  test('delivery hub renders v2 hero and 3 nav cards', async ({ page }) => {
    await assertHubV2(page, {
      url: '/admin/delivery-hub', id: '#delivery-hub',
      title: 'Pengiriman', minCards: 3,
    });
  });

  test('accounting hub renders v2 hero, section headers, and 6 nav cards', async ({ page }) => {
    await assertHubV2(page, {
      url: '/admin/accounting-hub', id: '#accounting-hub',
      title: 'Akuntansi', minCards: 6,
    });
    const hub = page.locator('#accounting-hub');
    await expect(hub.locator('.hubv2-sh')).toHaveCount(4);
  });

  test('payment hub renders v2 hero and 6 nav cards', async ({ page }) => {
    await assertHubV2(page, {
      url: '/admin/payment-hub', id: '#payment-hub',
      title: 'Pembayaran', minCards: 6,
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

});

test.describe('Sidebar Hub Grouping', () => {
  test('report sub-hubs stay hidden from the sidebar', async ({ page }) => {
    await page.goto('/admin');
    await page.waitForLoadState('networkidle');

    const sidebar = page.getByRole('complementary');

    await expect(sidebar.getByRole('link', { name: /laporan keuangan/i })).toHaveCount(0);
    await expect(sidebar.getByRole('link', { name: /laporan operasional/i })).toHaveCount(0);
  });

  test('hub pages are visible in sidebar for the top-level modules', async ({ page }) => {
    await page.goto('/admin');
    await page.waitForLoadState('networkidle');

    const sidebar = page.getByRole('complementary');

    await expect(page.getByRole('link', { name: /dashboard/i }).first()).toBeVisible({ timeout: 10000 });
    await expect(sidebar.getByRole('link', { name: /retur pelanggan/i })).toHaveCount(0);
    await expect(sidebar.getByRole('link', { name: /laporan stok/i })).toHaveCount(0);
    await expect(page.getByRole('link', { name: /^penjualan$/i }).first()).toBeVisible({ timeout: 10000 });
    await expect(page.getByRole('link', { name: /^pembelian$/i }).first()).toBeVisible({ timeout: 10000 });
    await expect(page.getByRole('link', { name: /^pengiriman$/i }).first()).toBeVisible({ timeout: 10000 });
    await expect(page.getByRole('link', { name: /^akuntansi$/i }).first()).toBeVisible({ timeout: 10000 });
    await expect(page.getByRole('link', { name: /^data master$/i }).first()).toBeVisible({ timeout: 10000 });
    await expect(page.getByRole('link', { name: /^manajemen user & role$/i }).first()).toBeVisible({ timeout: 10000 });
    await expect(page.getByRole('link', { name: /^manufaktur$/i }).first()).toBeVisible({ timeout: 10000 });
  });

  test('dashboard hub page renders quick links for finance and reports while operational report link stays hidden', async ({ page }) => {
    await page.goto('/admin/dashboard-hub');
    await page.waitForLoadState('networkidle');

    const sidebar = page.getByRole('complementary');
    const hub = page.locator('#dashboard-hub');

    await expect(hub).toBeVisible({ timeout: 10000 });
    await expect(hub.getByRole('link', { name: /finance dashboard/i })).toBeVisible();
    await expect(hub.getByRole('link', { name: /laporan penjualan/i })).toBeVisible();
    await expect(hub.getByRole('link', { name: /laporan pembelian/i })).toBeVisible();
    await expect(sidebar.getByRole('link', { name: /laporan operasional/i })).toHaveCount(0);
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

  test('inventory hub page renders direct inventory links without nested warehouse hub links', async ({ page }) => {
    await page.goto('/admin/inventory-hub');
    await page.waitForLoadState('networkidle');

    const sidebar = page.getByRole('complementary');
    const hub = page.locator('#inventory-hub');

    await expect(hub).toBeVisible({ timeout: 10000 });
    await expect(hub.getByRole('link', { name: /stock transfer/i })).toBeVisible();
    await expect(hub.getByRole('link', { name: /stock adjustment/i })).toBeVisible();
    await expect(hub.getByRole('link', { name: /kartu persediaan/i })).toBeVisible();
    await expect(hub.getByRole('link', { name: /laporan stok/i })).toBeVisible();
    await expect(sidebar.getByRole('link', { name: /stock transfer/i })).toHaveCount(0);
    await expect(sidebar.getByRole('link', { name: /kartu persediaan/i })).toHaveCount(0);
    await expect(sidebar.getByRole('link', { name: /laporan stok/i })).toHaveCount(0);
  });

  test('purchase hub page renders quick links while detailed purchase sidebar links stay hidden', async ({ page }) => {
    await page.goto('/admin/purchase-hub');
    await page.waitForLoadState('networkidle');

    const sidebar = page.getByRole('complementary');
    const hub = page.locator('#purchase-hub');

    await expect(hub).toBeVisible({ timeout: 10000 });
    await expect(hub.getByRole('link', { name: /permintaan pembelian/i })).toBeVisible();
    await expect(hub.getByRole('link', { name: /pesanan pembelian/i })).toBeVisible();
    await expect(hub.getByRole('link', { name: /utang usaha/i })).toBeVisible();
    await expect(hub.getByRole('link', { name: /invoice pembelian/i })).toBeVisible();
    await expect(sidebar.getByRole('link', { name: /pesanan pembelian/i })).toHaveCount(0);
    await expect(sidebar.getByRole('link', { name: /kontrol kualitas pembelian/i })).toHaveCount(0);
    await expect(sidebar.getByRole('link', { name: /utang usaha|account payable/i })).toHaveCount(0);
    await expect(sidebar.getByRole('link', { name: /invoice pembelian/i })).toHaveCount(0);
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

  test('sales and manufacturing hubs expose their child cards while the sidebar stays clean', async ({ page }) => {
    await page.goto('/admin/sales-hub');
    await page.waitForLoadState('networkidle');

    const sidebar = page.getByRole('complementary');
    const salesHub = page.locator('#sales-hub');

    await expect(salesHub).toBeVisible({ timeout: 10000 });
    await expect(salesHub.getByRole('link', { name: /sale orders/i })).toBeVisible();
    await expect(salesHub.getByRole('link', { name: /piutang usaha/i })).toBeVisible();
    await expect(salesHub.getByRole('link', { name: /invoice penjualan/i })).toBeVisible();
    await expect(salesHub.getByRole('link', { name: /penjualan lainnya/i })).toBeVisible();
    await expect(sidebar.getByRole('link', { name: /pesanan penjualan/i })).toHaveCount(0);
    await expect(sidebar.getByRole('link', { name: /invoice penjualan/i })).toHaveCount(0);
    await expect(sidebar.getByRole('link', { name: /penjualan lainnya/i })).toHaveCount(0);

    await page.goto('/admin/manufacturing-hub');
    await page.waitForLoadState('networkidle');

    const manufacturingHub = page.locator('#manufacturing-hub');

    await expect(manufacturingHub).toBeVisible({ timeout: 10000 });
    await expect(manufacturingHub.getByRole('link', { name: /manufacturing order/i })).toBeVisible();
    await expect(manufacturingHub.getByRole('link', { name: /qc manufacture/i })).toBeVisible();
    await expect(sidebar.getByRole('link', { name: /manufacturing order/i })).toHaveCount(0);
  });
});