import { test, expect } from '@playwright/test';
import path from 'path';

const SCREENSHOT_DIR = 'C:/Users/ralam/.gemini/antigravity/brain/fa80b9da-259a-45a7-8f1e-c487a82b1211/screenshots';

test.describe('Audit Verification Suite: Perbaikan Hambatan Operasional Tahap 5', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/dev-login');
    await page.waitForURL('**/dashboard', { timeout: 15000 });
  });

  test('Bukti 32 [TAHAP5-DISPOSAL]: Modal Void Pelepasan Aset Tetap Disposed (Super Admin)', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/keuangan/aset/disposal/3');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/Pelepasan/i);

    const voidBtn = page.getByRole('button', { name: /Void Pelepasan Aset/i });
    await expect(voidBtn).toBeVisible({ timeout: 10000 });
    await voidBtn.click();
    await page.waitForTimeout(600);

    await expect(page.locator('body')).toContainText(/Batalkan \/ Void Pelepasan Aset/i);
    await expect(page.locator('body')).toContainText(/Konfirmasi Void Pelepasan/i);

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '32_asset_disposal_void_modal.png'),
      fullPage: false,
    });
  });

  test('Bukti 33 [TAHAP5-TRANSFER]: Modal Void Mutasi Transfer Aset Selesai Completed (Super Admin)', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/keuangan/aset/transfer/3');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/Mutasi/i);

    const voidBtn = page.getByRole('button', { name: /Void Transfer Aset/i });
    await expect(voidBtn).toBeVisible({ timeout: 10000 });
    await voidBtn.click();
    await page.waitForTimeout(600);

    await expect(page.locator('body')).toContainText(/Batalkan \/ Void Transfer Aset/i);
    await expect(page.locator('body')).toContainText(/Konfirmasi Void Transfer/i);

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '33_asset_transfer_void_modal.png'),
      fullPage: false,
    });
  });

  test('Bukti 34 [TAHAP5-KONSOLIDASI]: Indikator Integritas Mutasi & Keseimbangan Neraca Saldo', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/laporan/keuangan/neraca-saldo');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/Neraca Saldo/i);
    await expect(page.locator('body')).toContainText(/Integritas Mutasi/i);

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '34_trial_balance_balance_indicator.png'),
      fullPage: false,
    });
  });
});
