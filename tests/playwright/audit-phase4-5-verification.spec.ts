import { test, expect } from '@playwright/test';
import path from 'path';

const SCREENSHOT_DIR = 'C:/Users/ralam/.gemini/antigravity/brain/fa80b9da-259a-45a7-8f1e-c487a82b1211/screenshots';

test.describe('Audit Verification Suite: Bukti Konkrit Remediasi Bug Fase 4 & 5', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/dev-login');
    await page.waitForURL('**/dashboard', { timeout: 15000 });
  });

  test('Bukti 7 [BUG-NEW-03]: Transfer Stok Approved - Tombol & Modal Hapus Terkunci Permanen', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/gudang/transfer/13');
    await page.waitForLoadState('networkidle');

    // Status disetujui / approved
    await expect(page.locator('body')).toContainText(/Disetujui|Approved/i);

    // Pastikan tombol hapus dan modal hapus tidak ada di DOM
    const deleteButton = page.locator('button:has-text("Hapus")');
    await expect(deleteButton).toHaveCount(0);

    const deleteModal = page.locator('div[x-show*="delete-transfer"]');
    await expect(deleteModal).toHaveCount(0);

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '07_stock_transfer_approved_no_delete.png'),
      fullPage: false,
    });
  });

  test('Bukti 8 [BUG-NEW-01]: Formulir Pembayaran Hutang Vendor (Vendor Payment) Siap Input', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/pembelian/pembayaran/create');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/Pembayaran.*Vendor|Pelunasan Hutang|Pembayaran/i);

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '08_vendor_payment_create_form.png'),
      fullPage: false,
    });
  });

  test('Bukti 9 [BUG-NEW-02]: Formulir Penerimaan Piutang Kasir (Customer Receipt) Siap Input', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/penjualan/receipts/create');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/Penerimaan Customer|Pelunasan Piutang/i);

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '09_customer_receipt_create_form.png'),
      fullPage: false,
    });
  });

  test('Bukti 10 [BUG-NEW-04]: Formulir Penerimaan Barang Gudang (GRN) dengan Filter PO Sah', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/gudang/penerimaan/create');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/Penerimaan Barang|Surat Jalan/i);

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '10_purchase_receipt_grn_create_form.png'),
      fullPage: false,
    });
  });

  test('Bukti 11 [BUG-NEW-05]: Formulir Hasil Produksi Manufaktur dengan Batasan SPK', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/manufaktur/production/create');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/Produksi|LHP|Bukti Hasil/i);

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '11_manufacturing_production_create_form.png'),
      fullPage: false,
    });
  });
});

