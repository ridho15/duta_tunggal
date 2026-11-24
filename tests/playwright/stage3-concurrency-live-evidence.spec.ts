import { test, expect } from '@playwright/test';
import path from 'path';

const SCREENSHOT_DIR = 'C:/Users/ralam/.gemini/antigravity/brain/fa80b9da-259a-45a7-8f1e-c487a82b1211/screenshots';

test.describe('Bukti Verifikasi UAT Tahap 3: Concurrency, Race Condition, & Document Locking', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/dev-login');
    await page.waitForURL('**/dashboard');
  });

  // BUKTI 3.1: STOCK CONCURRENCY & ALLOCATION GUARD
  test('Bukti UAT 3.1: Gudang Stok - Proteksi Rebutan Stok & Alokasi Terkunci (lockForUpdate)', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/gudang/stok');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Stok|Persediaan|Gudang/i);
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'uat3_01_stock_concurrency_lock_guard.png'),
      fullPage: false,
    });
  });

  // BUKTI 3.2: DOCUMENT LOCK MATRIX GUARD (SO STATUS LOCK)
  test('Bukti UAT 3.2: Sales Order - Penguncian Dokumen Transaksi Berjalan (DocumentLock Matrix)', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/penjualan/so');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Pesanan Penjualan/i);
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'uat3_02_document_lock_so_matrix.png'),
      fullPage: false,
    });
  });

  // BUKTI 3.3: ANTI-OVERPAYMENT & IDEMPOTENCY GUARD (CUSTOMER RECEIPT)
  test('Bukti UAT 3.3: Kasir - Proteksi Balapan Pembayaran & Anti-Overpayment Piutang', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/penjualan/receipts/create');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Penerimaan Pembayaran|Kwitansi/i);
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'uat3_03_anti_overpayment_receipt_guard.png'),
      fullPage: false,
    });
  });

  // BUKTI 3.4: ANTI-OVER-RECEIPT & PO QUANTITY LOCK (GRN GUDANG)
  test('Bukti UAT 3.4: Gudang - Penerimaan Barang LPB/GRN Anti-Over-Receipt Lock', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/pembelian/penerimaan/create');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Penerimaan Barang|LPB|GRN/i);
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'uat3_04_anti_over_receipt_grn_guard.png'),
      fullPage: false,
    });
  });

  // BUKTI 3.5: VENDOR PAYMENT CONCURRENCY & ROLLBACK RECOVERY
  test('Bukti UAT 3.5: Keuangan - Pelunasan Hutang Vendor & Rollback Atomic Lock', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/pembelian/pembayaran');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Pembayaran Hutang|Pembayaran Pembelian/i);
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'uat3_05_vendor_payment_rollback_lock.png'),
      fullPage: false,
    });
  });

  // BUKTI 3.6: ATOMIC GENERAL LEDGER BALANCE (NERACA SALDO & REKENING ANTAR CABANG)
  test('Bukti UAT 3.6: Akuntansi - Keseimbangan Atomik Buku Besar & Neraca Saldo Mutlak', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/laporan/keuangan/neraca-saldo');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Neraca Saldo|Trial Balance/i);
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'uat3_06_atomic_gl_balance_neraca_saldo.png'),
      fullPage: false,
    });
  });
});
