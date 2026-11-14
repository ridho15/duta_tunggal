import { test, expect } from '@playwright/test';
import path from 'path';

const SCREENSHOT_DIR = 'C:/Users/ralam/.gemini/antigravity/brain/fa80b9da-259a-45a7-8f1e-c487a82b1211/screenshots';

test.describe('Audit Verification Suite: Perbaikan Hambatan Operasional Tahap 3', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/dev-login');
    await page.waitForURL('**/dashboard', { timeout: 15000 });
  });

  test('Bukti 26 [TAHAP3-SPK]: Modal Intervensi Batal Darurat SPK In Progress (Super Admin)', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/manufaktur/spk/2');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/Surat Perintah Kerja|SPK/i);

    const cancelBtn = page.getByRole('button', { name: /Batalkan SPK/i });
    await expect(cancelBtn).toBeVisible({ timeout: 10000 });
    await cancelBtn.click();
    await page.waitForTimeout(600);

    await expect(page.locator('body')).toContainText(/Batalkan SPK Manufaktur/i);
    await expect(page.locator('body')).toContainText(/Intervensi Batal Darurat/i);

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '26_spk_force_cancel_modal.png'),
      fullPage: false,
    });
  });

  test('Bukti 27 [TAHAP3-TRANSFER]: Modal Void Transfer Stok Disetujui (Super Admin)', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/gudang/transfer/13');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/Transfer Stok/i);

    const voidBtn = page.getByRole('button', { name: /Void Transfer Stok/i });
    await expect(voidBtn).toBeVisible({ timeout: 10000 });
    await voidBtn.click();
    await page.waitForTimeout(600);

    await expect(page.locator('body')).toContainText(/Void Transfer Stok Antar Gudang/i);

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '27_stock_transfer_void_modal.png'),
      fullPage: false,
    });
  });

  test('Bukti 28 [TAHAP3-OPNAME]: Modal Batalkan Sesi Hitung Fisik Stock Opname', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/gudang/opname/3');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/Stock Opname/i);

    const cancelBtn = page.getByRole('button', { name: /Batalkan Sesi Opname/i });
    await expect(cancelBtn).toBeVisible({ timeout: 10000 });
    await cancelBtn.click();
    await page.waitForTimeout(600);

    await expect(page.locator('body')).toContainText(/Batalkan Sesi Stock Opname/i);

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '28_stock_opname_cancel_modal.png'),
      fullPage: false,
    });
  });
});

