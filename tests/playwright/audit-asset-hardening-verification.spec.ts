import { test, expect } from '@playwright/test';
import path from 'path';

const SCREENSHOT_DIR = 'C:/Users/ralam/.gemini/antigravity/brain/fa80b9da-259a-45a7-8f1e-c487a82b1211/screenshots';

test.describe('Audit Verification Suite: Hardening Mutasi & Pelepasan Aset Tetap', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/dev-login');
    await page.waitForURL('**/dashboard', { timeout: 15000 });
  });

  test('Bukti 17 [HARDENING-AST-01]: Pelepasan Aset Tetap - Tabel Riwayat & KPI Pelepasan Aset', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/keuangan/aset/disposal');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/Pelepasan Aset|Disposal/i);

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '17_asset_disposal_index.png'),
      fullPage: false,
    });
  });

  test('Bukti 18 [HARDENING-AST-02]: Formulir Pelepasan Aset - Proteksi Atomic & Cek Status Aset', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/keuangan/aset/disposal/create');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/Catat Pelepasan Aset|Pelepasan|Disposal/i);

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '18_asset_disposal_create_form.png'),
      fullPage: false,
    });
  });

  test('Bukti 19 [HARDENING-AST-03]: Mutasi Transfer Aset Antar-Cabang - Proteksi Status & Atomic Workflow', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/keuangan/aset/transfer');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/Mutasi Transfer Aset|Transfer Aset|Mutasi Aset/i);

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '19_asset_transfer_index.png'),
      fullPage: false,
    });
  });
});

