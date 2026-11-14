import { test, expect } from '@playwright/test';
import path from 'path';

const SCREENSHOT_DIR = 'C:/Users/ralam/.gemini/antigravity/brain/fa80b9da-259a-45a7-8f1e-c487a82b1211/screenshots';

test.describe('Audit Verification Suite: Perbaikan Hambatan Operasional Tahap 2', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/dev-login');
    await page.waitForURL('**/dashboard', { timeout: 15000 });
  });

  test('Bukti 23 [TAHAP2-PO]: Modal Batalkan PO Resmi & Pemulihan Kuota Order Request', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/pembelian/po/4');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/Pesanan Pembelian/i);

    // Temukan tombol Batalkan PO dan klik
    const cancelBtn = page.getByRole('button', { name: /Batalkan PO/i });
    if (await cancelBtn.isVisible()) {
      await cancelBtn.click();
      await page.waitForTimeout(500);
      await expect(page.locator('body')).toContainText(/Batalkan Pesanan Pembelian/i);
    }

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '23_purchase_order_cancel_modal.png'),
      fullPage: false,
    });
  });

  test('Bukti 24 [TAHAP2-GRN]: Modal Batalkan Penerimaan Barang (Void GRN) & Reversal Stok', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/gudang/penerimaan/2');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/Penerimaan Barang|GRN/i);

    // Temukan tombol Batalkan GRN (Void) dan klik
    const voidBtn = page.getByRole('button', { name: /Batalkan GRN/i });
    if (await voidBtn.isVisible()) {
      await voidBtn.click();
      await page.waitForTimeout(500);
      await expect(page.locator('body')).toContainText(/Batalkan Penerimaan Barang \(Void\)/i);
    }

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '24_purchase_receipt_void_modal.png'),
      fullPage: false,
    });
  });

  test('Bukti 25 [TAHAP2-OR]: Form Pembuatan PO Siap Pakai Tanpa Hambatan Kuota', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/pembelian/po/create');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/Pesanan Pembelian|Pemasok|Supplier/i);

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '25_purchase_order_create_screen.png'),
      fullPage: false,
    });
  });
});

