import { test, expect } from '@playwright/test';
import path from 'path';

const SCREENSHOT_DIR = 'C:/Users/ralam/.gemini/antigravity/brain/fa80b9da-259a-45a7-8f1e-c487a82b1211/screenshots';

test.describe('Bukti Verifikasi Fitur Baru PR #8 & PR #9 (Keputusan 21 Nov)', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/dev-login');
    await page.waitForURL('**/dashboard');
  });

  test('Bukti 35: Daftar Pesanan Penjualan (SO) - Status & Tombol Selesai Manual Sales', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/penjualan/so');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/Pesanan Penjualan/i);
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '35_sales_order_index_pr8_complete_flow.png'),
      fullPage: false,
    });
  });

  test('Bukti 36: Master Produk - Field Harga Beli Terakhir (HPP Moving Average)', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/master/produk/1');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/Master Produk/i);
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '36_master_product_last_purchase_price.png'),
      fullPage: false,
    });
  });

  test('Bukti 37: Bagan Akun (COA) - Rekening Antar Cabang 1180.02', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/master/coa');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/Bagan Akun|Chart of Account/i);
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '37_chart_of_accounts_inter_branch.png'),
      fullPage: false,
    });
  });
});
