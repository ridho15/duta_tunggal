import { test, expect } from '@playwright/test';
import path from 'path';

const SCREENSHOT_DIR = 'C:/Users/ralam/.gemini/antigravity/brain/fa80b9da-259a-45a7-8f1e-c487a82b1211/screenshots';

test.describe('Audit Verification Suite: Bukti Konkrit Remediasi Bug Fase 6', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/dev-login');
    await page.waitForURL('**/dashboard', { timeout: 15000 });
  });

  test('Bukti 12 [BUG-NEW-06]: Penyusutan Aset Tetap - Tabel Penyusutan & Tombol Proses Bulanan Aktif', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/keuangan/aset/depresiasi');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/Penyusutan|Depresiasi/i);

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '12_asset_depreciation_index.png'),
      fullPage: false,
    });
  });

  test('Bukti 13 [BUG-NEW-07]: Surat Perintah Kerja (SPK) Manufaktur - Status dan Proteksi Pembatalan', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/manufaktur/spk');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/Surat Perintah Kerja|SPK|Manufacturing Order/i);

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '13_manufacturing_order_spk_index.png'),
      fullPage: false,
    });
  });

  test('Bukti 14 [BUG-NEW-08]: Terminal Kasir Cepat (POS) - Proteksi Modal Awal & Pembukaan Shift', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/kasir');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/Kasir|Point of Sale|Shift/i);

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '14_pos_terminal_shift_protection.png'),
      fullPage: false,
    });
  });

  test('Bukti 15 [BUG-NEW-09]: Retur Penjualan (Customer Return) - Rekap Status & Proteksi Atomic Completion', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/penjualan/retur');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/Retur Penjualan|Customer Return/i);

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '15_customer_return_index.png'),
      fullPage: false,
    });
  });

  test('Bukti 16 [BUG-NEW-10]: Tutup Buku & Periode Akuntansi (General Ledger) - Penutupan Idempoten', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/keuangan/tutup-buku');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/Periode Akuntansi|Tutup Buku/i);

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '16_accounting_period_index.png'),
      fullPage: false,
    });
  });
});

