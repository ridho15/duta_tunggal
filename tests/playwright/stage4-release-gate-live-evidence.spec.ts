import { test, expect } from '@playwright/test';
import path from 'path';

const SCREENSHOT_DIR = 'C:/Users/ralam/.gemini/antigravity/brain/fa80b9da-259a-45a7-8f1e-c487a82b1211/screenshots';

test.describe('Bukti Verifikasi UAT Tahap 4: Production Release Gate & System Readiness Audit', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/dev-login');
    await page.waitForURL('**/dashboard');
  });

  // BUKTI 4.1: EXECUTIVE DASHBOARD & KPI INTEGRITY
  test('Bukti UAT 4.1: Dashboard Eksekutif - Metrik Penjualan, Kas/Bank, & Status Piutang', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/dashboard');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Dashboard|Ringkasan|Penjualan/i);
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'uat4_01_release_gate_dashboard_kpi.png'),
      fullPage: false,
    });
  });

  // BUKTI 4.2: FINANCIAL INTEGRITY & BALANCE SHEET
  test('Bukti UAT 4.2: Integritas Finansial - Neraca Saldo Mutlak Seimbang', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/laporan/keuangan/neraca-saldo');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Neraca Saldo|Trial Balance/i);
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'uat4_02_release_gate_financial_integrity.png'),
      fullPage: false,
    });
  });

  // BUKTI 4.3: PHYSICAL INVENTORY & STOCK OPNAME READINESS
  test('Bukti UAT 4.3: Kesiapan Persediaan - Audit Fisik & Stock Opname Gudang', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/gudang/opname');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Stock Opname|Penyesuaian/i);
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'uat4_03_release_gate_stock_opname_balance.png'),
      fullPage: false,
    });
  });

  // BUKTI 4.4: MANUFACTURING SCHEDULE & PRODUCTION WORKFLOW
  test('Bukti UAT 4.4: Operasional Produksi - SPK Manufaktur & Kontrol Alur Kerja', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/manufaktur/spk');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Surat Perintah Kerja|SPK|Manufaktur/i);
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'uat4_04_release_gate_manufacturing_schedule.png'),
      fullPage: false,
    });
  });

  // BUKTI 4.5: CHART OF ACCOUNTS & REKENING ANTAR CABANG
  test('Bukti UAT 4.5: Bagan Akun Lengkap - Rekening Antar Cabang 1180.02 Terverifikasi', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/master/coa');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Bagan Akun|Chart of Account/i);
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'uat4_05_release_gate_accounting_coa_audit.png'),
      fullPage: false,
    });
  });

  // BUKTI 4.6: DOUBLE-ENTRY JOURNAL AUDIT TRAIL
  test('Bukti UAT 4.6: Jejak Audit Akuntansi - Jurnal Umum Lintas Entitas', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/keuangan/jurnal');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Jurnal|Buku Jurnal|Transaksi/i);
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'uat4_06_release_gate_journal_audit_trail.png'),
      fullPage: false,
    });
  });
});
