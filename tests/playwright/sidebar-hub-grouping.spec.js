import { test, expect } from '@playwright/test';

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