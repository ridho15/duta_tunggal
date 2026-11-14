import { test, expect } from '@playwright/test';
import path from 'path';

const SCREENSHOT_DIR = 'C:/Users/ralam/.gemini/antigravity/brain/fa80b9da-259a-45a7-8f1e-c487a82b1211/screenshots';

test.describe('Audit Verification Suite: Perbaikan Hambatan Operasional Tahap 4', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/dev-login');
    await page.waitForURL('**/dashboard', { timeout: 15000 });
  });

  test('Bukti 29 [TAHAP4-CN]: Modal Void Nota Kredit Terbit (Super Admin)', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/penjualan/credit-notes/4');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/Nota Kredit/i);

    const voidBtn = page.getByRole('button', { name: /Void Nota Kredit/i });
    await expect(voidBtn).toBeVisible({ timeout: 10000 });
    await voidBtn.click();
    await page.waitForTimeout(600);

    await expect(page.locator('body')).toContainText(/Batalkan \/ Void Nota Kredit/i);
    await expect(page.locator('body')).toContainText(/Konfirmasi Void Nota Kredit/i);

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '29_credit_note_void_modal.png'),
      fullPage: false,
    });
  });

  test('Bukti 30 [TAHAP4-JOURNAL]: Modal Buat Jurnal Pembalik Otomatis Reversal Voucher (Super Admin)', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/keuangan/jurnal/JE-DEP-1-202605');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/Jurnal|Voucher/i);

    const reverseBtn = page.getByRole('button', { name: /Buat Jurnal Pembalik/i });
    await expect(reverseBtn).toBeVisible({ timeout: 10000 });
    await reverseBtn.click();
    await page.waitForTimeout(600);

    await expect(page.locator('body')).toContainText(/Buat Jurnal Pembalik \(Reversal\)/i);
    await expect(page.locator('body')).toContainText(/Proses Jurnal Pembalik/i);

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '30_journal_reversal_modal.png'),
      fullPage: false,
    });
  });

  test('Bukti 31 [TAHAP4-TRANSFER]: Modal Batalkan Transfer Antar Rekening Kas Bank (Super Admin)', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/keuangan/transfer/1');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/Transfer/i);

    const cancelBtn = page.getByRole('button', { name: /Batalkan Transfer/i });
    await expect(cancelBtn).toBeVisible({ timeout: 10000 });
    await cancelBtn.click();
    await page.waitForTimeout(600);

    await expect(page.locator('body')).toContainText(/Batalkan Transfer Antar Rekening/i);
    await expect(page.locator('body')).toContainText(/Konfirmasi Batalkan Transfer/i);

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, '31_cash_bank_transfer_cancel_modal.png'),
      fullPage: false,
    });
  });
});
