import { test, expect } from '@playwright/test';
import path from 'path';

const SCREENSHOT_DIR = 'C:/Users/ralam/.gemini/antigravity/brain/fa80b9da-259a-45a7-8f1e-c487a82b1211/screenshots';

test.describe('Bukti Verifikasi Browser: Remediasi Bug Kritis & Operasional ERP', () => {
  test.beforeEach(async ({ page }) => {
    // Otentikasi otomatis via dev-login
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

  test('Bukti 2: Halaman Faktur Penjualan (Sales Invoice) - Verifikasi BUG-01', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/penjualan/invoices');
    await page.waitForLoadState('networkidle');
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '02_sales_invoices.png'),
      fullPage: false,
    });
  });

  test('Bukti 3: Halaman Pesanan Penjualan (Sales Order) - Verifikasi BUG-02 & BUG-04', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/penjualan/so');
    await page.waitForLoadState('networkidle');
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '03_sales_orders.png'),
      fullPage: false,
    });
  });

  test('Bukti 4: Halaman Stock Opname Gudang - Verifikasi BUG-03', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/gudang/opname');
    await page.waitForLoadState('networkidle');
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '04_stock_opname.png'),
      fullPage: false,
    });
  });

  test('Bukti 5: Halaman Kas & Bank - Verifikasi BUG-06', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/keuangan/cashbank');
    await page.waitForLoadState('networkidle');
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '05_cash_bank.png'),
      fullPage: false,
    });
  });

  test('Bukti 6: Halaman Master Produk - Verifikasi BUG-07', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/master/produk');
    await page.waitForLoadState('networkidle');
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '06_master_product.png'),
      fullPage: false,
    });
  });
});

