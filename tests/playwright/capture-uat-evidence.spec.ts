import { test, expect } from '@playwright/test';
import path from 'path';

const SCREENSHOT_DIR = 'C:/Users/ralam/.gemini/antigravity/brain/37609e76-32d0-4a5e-8715-8e9934fbf3fd/screenshots';

test.describe('Capture Visual UAT Evidence Suite', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/dev-login');
    await page.waitForURL('**/dashboard', { timeout: 15000 });
  });

  test('Bukti 1: Dashboard ERP Duta Tunggal', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/dashboard');
    await page.waitForLoadState('networkidle');
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '01_dashboard.png'),
      fullPage: false,
    });
  });

  test('Bukti 2: Daftar Permintaan Pembelian (Order Request)', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/pembelian/permintaan');
    await page.waitForLoadState('networkidle');
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '02_order_request_list.png'),
      fullPage: false,
    });
  });

  test('Bukti 3: Formulir Buat Permintaan Barang (OR) dengan Quick-Add Search', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/pembelian/permintaan/create');
    await page.waitForLoadState('networkidle');
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '03_order_request_create.png'),
      fullPage: false,
    });
  });

  test('Bukti 4: Daftar Penawaran Harga (Quotation) Terpaginasi', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/penjualan/quotations');
    await page.waitForLoadState('networkidle');
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '04_quotation_list.png'),
      fullPage: false,
    });
  });

  test('Bukti 5: Formulir Buat Pesanan Penjualan (SO) Pasca-Migrasi (Bug-01 Tuntas)', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/penjualan/so/create');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('#soForm')).toBeVisible();
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '05_sales_order_create_fixed.png'),
      fullPage: false,
    });
  });

  test('Bukti 6: Daftar Surat Jalan / Delivery Order', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/penjualan/surat-jalan');
    await page.waitForLoadState('networkidle');
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '06_surat_jalan_list.png'),
      fullPage: false,
    });
  });

  test('Bukti 7: Halaman Proteksi Sesi Kedaluwarsa (HTTP 419 Friendly Defensive)', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/pembelian/permintaan/create');
    await page.waitForLoadState('networkidle');
    // Clear cookies to trigger expired CSRF session
    await page.context().clearCookies();
    const submitBtn = page.locator('#orForm button[type="submit"]').first();
    await submitBtn.click();
    await page.waitForLoadState('networkidle');
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '07_session_expiry_defensive.png'),
      fullPage: false,
    });
  });
});

