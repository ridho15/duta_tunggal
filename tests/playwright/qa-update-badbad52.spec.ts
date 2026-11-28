import { test, expect } from '@playwright/test';
import path from 'path';

const SCREENSHOT_DIR = 'C:/Users/ralam/.gemini/antigravity/brain/fa80b9da-259a-45a7-8f1e-c487a82b1211/screenshots';
const BASE_URL = 'http://127.0.0.1:8009';

test.describe('QA Evidence Update badbad52 (28 Nov 2026)', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(`${BASE_URL}/dev-login?role=Owner`, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.waitForURL('**/dashboard', { timeout: 20000 });
  });

  test('01. Pusat Laporan (/laporan)', async ({ page }) => {
    await page.goto(`${BASE_URL}/laporan`, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText('Detail Penjualan');
    await expect(page.locator('body')).toContainText('Stok Barang Terjual');
    await expect(page.locator('body')).toContainText('Cetak Banyak Produk');

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'badbad52_01_pusat_laporan_index.png'),
      fullPage: false,
    });
  });

  test('02. Detail Penjualan (/laporan/detail-penjualan)', async ({ page }) => {
    await page.goto(`${BASE_URL}/laporan/detail-penjualan`, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Detail Penjualan/i);

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'badbad52_02_laporan_penjualan_detail.png'),
      fullPage: false,
    });
  });

  test('03. Stok Barang Terjual (/laporan/stok-terjual)', async ({ page }) => {
    await page.goto(`${BASE_URL}/laporan/stok-terjual`, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Stok Barang Terjual/i);

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'badbad52_03_laporan_stok_terjual.png'),
      fullPage: false,
    });
  });

  test('04. Permintaan Bayar Non-Barang (/keuangan/permintaan-bayar)', async ({ page }) => {
    await page.goto(`${BASE_URL}/keuangan/permintaan-bayar`, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Permintaan Bayar/i);

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'badbad52_04_permintaan_bayar_index.png'),
      fullPage: false,
    });
  });

  test('05. Form Tambah Permintaan Bayar Non-Barang (/keuangan/permintaan-bayar/buat)', async ({ page }) => {
    await page.goto(`${BASE_URL}/keuangan/permintaan-bayar/buat`, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Permintaan Bayar|Penerima/i);

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'badbad52_05_permintaan_bayar_create_form.png'),
      fullPage: false,
    });
  });

  test('06. Pengaturan Tanda Tangan Digital (/tanda-tangan-saya)', async ({ page }) => {
    await page.goto(`${BASE_URL}/tanda-tangan-saya`, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Tanda Tangan/i);

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'badbad52_06_tanda_tangan_digital.png'),
      fullPage: false,
    });
  });
});
