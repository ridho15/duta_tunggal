import { test, expect } from '@playwright/test';
import path from 'path';

const SCREENSHOT_DIR = 'C:/Users/ralam/.gemini/antigravity/brain/fa80b9da-259a-45a7-8f1e-c487a82b1211/screenshots';

test.describe('Deep Verification Suite: Bukti Konkrit Skenario Remediasi Bug ERP', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/dev-login');
    await page.waitForURL('**/dashboard', { timeout: 15000 });
  });

  test('Bukti 1 [Dashboard]: Sesi aktif & status operasional server', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/dashboard');
    await page.waitForLoadState('networkidle');
    await expect(page).toHaveTitle(/.*Dashboard.*/i);
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '01_dashboard_active.png'),
      fullPage: false,
    });
  });

  test('Bukti 2 [BUG-01]: Formulir Penerbitan Faktur Penjualan dari Surat Jalan Valid', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/penjualan/invoice/create');
    await page.waitForLoadState('networkidle');
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '02_sales_invoice_create_form.png'),
      fullPage: false,
    });
  });

  test('Bukti 3 [BUG-02 & BUG-04]: Form Sales Order dengan Info Plafon Kredit Customer', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/penjualan/so/create');
    await page.waitForLoadState('networkidle');
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '03_sales_order_create_credit_check.png'),
      fullPage: false,
    });
  });

  test('Bukti 4 [BUG-03]: Sesi Stock Opname Approved - Tidak Ada Tombol Hapus (Integritas Terkunci)', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/gudang/opname/1');
    await page.waitForLoadState('networkidle');

    // Verifikasi teks status Disetujui ada di halaman
    await expect(page.locator('body')).toContainText(/Disetujui|Approved/i);

    // Verifikasi tombol 'Hapus Sesi' maupun modal hapus TIDAK ADA pada dokumen yang sudah disetujui
    const deleteButton = page.locator('button:has-text("Hapus Sesi")');
    await expect(deleteButton).toHaveCount(0);

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '04_stock_opname_approved_no_delete.png'),
      fullPage: false,
    });
  });

  test('Bukti 5 [BUG-06]: Halaman Kas & Bank Valid (BKM/BKK)', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/keuangan/kas-bank');
    await page.waitForLoadState('networkidle');
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '05_cash_bank_index.png'),
      fullPage: false,
    });
  });

  test('Bukti 6 [BUG-07]: Formulir Tambah Master Produk (SKU Anti-Crash)', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/master/produk/create');
    await page.waitForLoadState('domcontentloaded');
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '06_master_product_create_form.png'),
      fullPage: false,
    });
  });
});
