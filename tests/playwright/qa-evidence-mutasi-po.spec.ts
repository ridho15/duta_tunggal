import { test, expect } from '@playwright/test';
import path from 'path';

const SCREENSHOT_DIR = 'C:/Users/ralam/.gemini/antigravity/brain/fa80b9da-259a-45a7-8f1e-c487a82b1211/screenshots';

test.describe('Evidence-Based QA: Mutasi 4 Tahap & PO PDF Pajak', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/dev-login');
    await page.waitForURL('**/dashboard');
  });

  test('Uji Alur Nyata Mutasi 4 Tahap & Cek PO PDF', async ({ page }) => {
    test.setTimeout(90000);

    // 1. BUKA DOKUMEN MUTASI BARU (ID 14, Status: Request)
    await page.goto('http://127.0.0.1:8009/gudang/transfer/14');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/ST-LIVE-|Transfer Stok/i);
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'qa_01_mutasi_requested.png'),
      fullPage: false,
    });

    // 2. SETUJUI TRANSFER (APPROVE)
    // Klik tombol Setujui Transfer yang memicu modal konfirmasi
    const approveBtn = page.locator('button:has-text("Setujui"), button:has-text("Setujui Transfer")').first();
    if (await approveBtn.isVisible()) {
      await approveBtn.click();
      await page.waitForTimeout(500);
      const confirmApproveBtn = page.locator('button:has-text("Ya, Setujui")');
      if (await confirmApproveBtn.isVisible()) {
        await confirmApproveBtn.click();
      }
    }
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/Disetujui|dicadangkan/i);
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'qa_02_mutasi_approved.png'),
      fullPage: false,
    });

    // 3. KIRIM SEBAGIAN (6 dari 10 unit)
    const kirimBtn = page.locator('button:has-text("Kirim Barang")').first();
    await expect(kirimBtn).toBeVisible();
    await kirimBtn.click();
    await page.waitForTimeout(600);

    // Isi kuantitas kirim 6 pada input modal
    const qtyInput = page.locator('input[name^="quantities"]').first();
    if (await qtyInput.isVisible()) {
      await qtyInput.fill('6');
    }
    const notesInput = page.locator('input[name="notes"]').first();
    if (await notesInput.isVisible()) {
      await notesInput.fill('Pengiriman Tahap 1 Muat 6 Unit');
    }

    const confirmKirimBtn = page.locator('button:has-text("Kirim")').last();
    await confirmKirimBtn.click();
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/Dikirim|SHP-/i);
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'qa_03_mutasi_shipped_partial.png'),
      fullPage: false,
    });

    // 4. TERIMA PENGIRIMAN DI GUDANG TUJUAN
    const terimaBtn = page.locator('button:has-text("Terima"), button:has-text("Terima Pengiriman")').first();
    if (await terimaBtn.isVisible()) {
      await terimaBtn.click();
      await page.waitForTimeout(500);
      const confirmTerimaBtn = page.locator('button:has-text("Ya, Barang Diterima")');
      if (await confirmTerimaBtn.isVisible()) {
        await confirmTerimaBtn.click();
      }
      await page.waitForLoadState('networkidle');
    }

    await expect(page.locator('body')).toContainText(/Diterima|Stok gudang tujuan sudah bertambah/i);
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'qa_04_mutasi_received.png'),
      fullPage: false,
    });

    // 5. CEK DETAIL PURCHASE ORDER (PO 1)
    await page.goto('http://127.0.0.1:8009/pembelian/po/1');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/Pesanan Pembelian|Purchase Order/i);
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'qa_05_po_detail_tax_summary.png'),
      fullPage: false,
    });

    // 6. CEK PDF PURCHASE ORDER (PO 1)
    await page.goto('http://127.0.0.1:8009/pembelian/po/1/pdf');
    await page.waitForLoadState('networkidle');

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'qa_06_po_pdf_faktur_pajak_format.png'),
      fullPage: false,
    });
  });
});
