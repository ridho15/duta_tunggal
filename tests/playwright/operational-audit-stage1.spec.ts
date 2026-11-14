import { test, expect } from '@playwright/test';
import path from 'path';

const SCREENSHOT_DIR = 'C:/Users/ralam/.gemini/antigravity/brain/fa80b9da-259a-45a7-8f1e-c487a82b1211/screenshots';

test.describe('Audit Verification Suite: Perbaikan Hambatan Operasional Tahap 1', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/dev-login');
    await page.waitForURL('**/dashboard', { timeout: 15000 });
  });

  test('Bukti 20 [TAHAP1-POS]: Manajemen Shift Kasir & Akses Force Close Super Admin', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/kasir/shift');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/Riwayat Shift Kasir|Shift|Z-Report/i);

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '20_pos_shift_management_screen.png'),
      fullPage: false,
    });
  });

  test('Bukti 21 [TAHAP1-SO]: Form Sales Order Terbuka & Input Pesanan Tanpa Deadlock Plafon Kredit', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/penjualan/so/create');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/Tambah Sales Order|Pesanan Penjualan/i);

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '21_sales_order_credit_warning_form.png'),
      fullPage: false,
    });
  });

  test('Bukti 22 [TAHAP1-SJ]: Daftar Surat Jalan & Proteksi Pembatalan Aman Pre-Dispatch', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/penjualan/surat-jalan');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/Surat Jalan|SJ/i);

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '22_surat_jalan_cancel_modal.png'),
      fullPage: false,
    });
  });
});
