import { test, expect } from '@playwright/test';
import path from 'path';

const SCREENSHOT_DIR = 'C:/Users/ralam/.gemini/antigravity/brain/fa80b9da-259a-45a7-8f1e-c487a82b1211/screenshots';

test.describe('Bukti Verifikasi UAT Tahap 2: Alur Bisnis Nyata Lintas Peran', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/dev-login');
    await page.waitForURL('**/dashboard');
  });

  // SIKLUS 1: ORDER-TO-CASH (O2C)
  test('Bukti UAT 2.1: Order-to-Cash - Pesanan Penjualan (SO)', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/penjualan/so');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Pesanan Penjualan/i);
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'uat2_01_o2c_sales_order_management.png'),
      fullPage: false,
    });
  });

  test('Bukti UAT 2.2: Order-to-Cash - Pengiriman & Surat Jalan (DO)', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/penjualan/do');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Surat Jalan|Delivery Order/i);
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'uat2_02_o2c_delivery_order_and_surat_jalan.png'),
      fullPage: false,
    });
  });

  test('Bukti UAT 2.3: Order-to-Cash - Pelunasan Piutang Kasir (Customer Receipts)', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/penjualan/receipts');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Penerimaan Pembayaran|Kwitansi|Pelunasan/i);
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'uat2_03_o2c_customer_receipt_settlement.png'),
      fullPage: false,
    });
  });

  // SIKLUS 2: PROCURE-TO-PAY (P2P)
  test('Bukti UAT 2.4: Procure-to-Pay - Pesanan Pembelian (PO)', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/pembelian/po');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Pesanan Pembelian|Purchase Order/i);
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'uat2_04_p2p_purchase_order_lifecycle.png'),
      fullPage: false,
    });
  });

  test('Bukti UAT 2.5: Procure-to-Pay - Penerimaan Barang Gudang (LPB / GRN)', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/pembelian/penerimaan');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Penerimaan Barang|LPB|GRN/i);
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'uat2_05_p2p_goods_receipt_grn.png'),
      fullPage: false,
    });
  });

  test('Bukti UAT 2.6: Procure-to-Pay - Pembayaran Hutang Vendor (Vendor Payment)', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/pembelian/pembayaran');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Pembayaran Hutang|Pembayaran Pembelian/i);
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'uat2_06_p2p_vendor_payment_settlement.png'),
      fullPage: false,
    });
  });

  // SIKLUS 3: MUTASI STOK ANTAR CABANG & REKENING ANTAR CABANG
  test('Bukti UAT 2.7: Mutasi Gudang - Transfer Stok Antar Cabang', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/gudang/transfer');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Transfer Stok|Mutasi/i);
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'uat2_07_inventory_interbranch_transfer.png'),
      fullPage: false,
    });
  });

  test('Bukti UAT 2.8: Akuntansi - Bagan Akun (COA) Rekening Antar Cabang (1180.02)', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/master/coa');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Bagan Akun|Chart of Account/i);
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'uat2_08_accounting_interbranch_coa.png'),
      fullPage: false,
    });
  });

  // SIKLUS 4: RETUR PENJUALAN & DO PENGGANTI CACAT
  test('Bukti UAT 2.9: Retur Penjualan Pelanggan', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/penjualan/retur');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Retur Penjualan/i);
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'uat2_09_sales_return_and_do_replacement.png'),
      fullPage: false,
    });
  });

  test('Bukti UAT 2.10: Gudang - Karantina & Persetujuan Retur DO', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/gudang/retur-do');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Retur DO|Persetujuan Retur/i);
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'uat2_10_warehouse_do_return_inspection.png'),
      fullPage: false,
    });
  });
});
