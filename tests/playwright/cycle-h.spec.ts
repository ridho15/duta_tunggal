import { test, expect, Page } from '@playwright/test';
import * as path from 'path';
import * as fs from 'fs';
import { execSync } from 'child_process';

const QA_EVIDENCE_DIR = path.resolve('d:/Website/duta_tunggal/qa-evidence');
const BRAIN_SCREENSHOT_DIR = path.resolve('C:/Users/ralam/.gemini/antigravity/brain/37609e76-32d0-4a5e-8715-8e9934fbf3fd/screenshots');

async function saveEvidence(page: Page, filename: string) {
  if (!fs.existsSync(QA_EVIDENCE_DIR)) {
    fs.mkdirSync(QA_EVIDENCE_DIR, { recursive: true });
  }
  await page.screenshot({ path: path.join(QA_EVIDENCE_DIR, filename), fullPage: false });

  if (!fs.existsSync(BRAIN_SCREENSHOT_DIR)) {
    fs.mkdirSync(BRAIN_SCREENSHOT_DIR, { recursive: true });
  }
  await page.screenshot({ path: path.join(BRAIN_SCREENSHOT_DIR, filename), fullPage: false });
}

test.describe.serial('Siklus H: Dashboard Eksekutif, Laporan Manajerial Lintas Divisi & Master Konfigurasi Pendukung', () => {
  let consoleErrors: string[] = [];
  let pageErrors: string[] = [];

  test.beforeAll(() => {
    console.log('[Setup] Menjalankan scripts/reset-cycle-h-test-data.php...');
    const out = execSync('php scripts/reset-cycle-h-test-data.php', { encoding: 'utf-8' });
    console.log(out);
  });

  test.beforeEach(async ({ page }) => {
    consoleErrors = [];
    pageErrors = [];
    page.on('console', msg => {
      if (msg.type() === 'error') {
        const text = msg.text();
        if (!text.includes('favicon.ico') && !text.includes('Failed to load resource: the server responded with a status of 404')) {
          consoleErrors.push(text);
        }
      }
    });
    page.on('pageerror', exception => {
      pageErrors.push(exception.message);
    });
  });

  test.afterEach(async () => {
    const testTitle = test.info().title;
    console.log(`[Console/Exception Log] ${testTitle}: console.error=${consoleErrors.length}, pageerror=${pageErrors.length}`);
    expect(consoleErrors, `Found console.error in ${testTitle}: ${consoleErrors.join(' | ')}`).toHaveLength(0);
    expect(pageErrors, `Found pageerror in ${testTitle}: ${pageErrors.join(' | ')}`).toHaveLength(0);
  });

  // =========================================================================
  // H-01: MASTER CABANG - UNHAPPY VALIDATION & HAPPY PATH (CBG-H-001)
  // =========================================================================
  test('H-01: Master Cabang - Unhappy Form Kosong & Pendaftaran Cabang Baru (CBG-H-001)', async ({ page }) => {
    await page.goto('http://localhost:8009/master/cabang/create');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('h1')).toContainText(/Tambah Cabang Baru/i);

    // Unhappy: submit form kosong (bypass client HTML5 validation)
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle', timeout: 15000 }),
      page.evaluate(() => {
        const form = document.querySelector('main form') as HTMLFormElement;
        if (form) {
          form.noValidate = true;
          form.submit();
        }
      })
    ]);

    await expect(page.locator('body')).toContainText(/Kode Cabang wajib diisi|Nama Cabang wajib diisi|Mohon periksa kembali/i);
    await saveEvidence(page, 'h_01a_cabang_unhappy.png');

    // Happy: Isi data Cabang Uji
    await page.fill('input#kode', 'CBG-H-001');
    await page.fill('input#nama', 'Cabang Gudang Logistik Timur');
    await page.fill('input#telepon', '021-88997766');
    await page.fill('input#kenaikan_harga', '5');
    await page.fill('textarea#alamat', 'Jl. Logistik Timur Raya No. 88, Kawasan Pergudangan');

    await Promise.all([
      page.waitForURL(url => url.pathname.includes('/master/cabang') && !url.pathname.includes('/create'), { timeout: 15000 }),
      page.click('main form button[type="submit"]')
    ]);

    await expect(page.locator('body')).toContainText('CBG-H-001');
    await expect(page.locator('body')).toContainText('Cabang Gudang Logistik Timur');
    await saveEvidence(page, 'h_01b_cabang_created.png');
  });

  // =========================================================================
  // H-02: MASTER KATEGORI PRODUK - UNHAPPY & HAPPY PATH (KAT-H-001)
  // =========================================================================
  test('H-02: Master Kategori Produk - Unhappy Form Kosong & Pendaftaran (KAT-H-001)', async ({ page }) => {
    await page.goto('http://localhost:8009/master/kategori/create');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Tambah Kategori Baru|Tambah Kategori Produk/i);

    // Unhappy: submit form kosong
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle', timeout: 15000 }),
      page.evaluate(() => {
        const form = document.querySelector('main form') as HTMLFormElement;
        if (form) {
          form.noValidate = true;
          form.submit();
        }
      })
    ]);

    await expect(page.locator('body')).toContainText(/Nama kategori wajib diisi|Kode kategori wajib diisi|Mohon periksa/i);
    await saveEvidence(page, 'h_02a_kategori_unhappy.png');

    // Happy: Isi kategori baru
    await page.fill('input[name="kode"]', 'KAT-H-001');
    await page.fill('input[name="name"]', 'Komponen Sensor & Relay DT');
    await page.fill('input[name="kenaikan_harga"]', '5');

    await Promise.all([
      page.waitForURL(url => url.pathname.includes('/master/kategori') && !url.pathname.includes('/create'), { timeout: 15000 }),
      page.click('main form button[type="submit"]')
    ]);

    await expect(page.locator('body')).toContainText('KAT-H-001');
    await expect(page.locator('body')).toContainText('Komponen Sensor & Relay DT');
    await saveEvidence(page, 'h_02b_kategori_created.png');
  });

  // =========================================================================
  // H-03: MASTER SATUAN / UOM - UNHAPPY & HAPPY PATH (PKT-H)
  // =========================================================================
  test('H-03: Master Satuan Ukuran (UOM) - Unhappy & Pendaftaran Satuan Baru (PKT-H)', async ({ page }) => {
    await page.goto('http://localhost:8009/master/satuan/create');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Tambah Satuan Ukuran/i);

    // Unhappy: submit form kosong
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle', timeout: 15000 }),
      page.evaluate(() => {
        const form = document.querySelector('main form') as HTMLFormElement;
        if (form) {
          form.noValidate = true;
          form.submit();
        }
      })
    ]);

    await expect(page.locator('body')).toContainText(/Nama satuan wajib diisi|Singkatan \/ simbol satuan wajib diisi|Mohon periksa/i);
    await saveEvidence(page, 'h_03a_satuan_unhappy.png');

    // Happy: Isi satuan baru
    await page.fill('input[name="name"]', 'Paket Kontrol Khusus');
    await page.fill('input[name="abbreviation"]', 'PKT-H');

    await Promise.all([
      page.waitForURL(url => url.pathname.includes('/master/satuan') && !url.pathname.includes('/create'), { timeout: 15000 }),
      page.click('main form button[type="submit"]')
    ]);

    await expect(page.locator('body')).toContainText('PKT-H');
    await expect(page.locator('body')).toContainText('Paket Kontrol Khusus');
    await saveEvidence(page, 'h_03b_satuan_created.png');
  });

  // =========================================================================
  // H-04: MASTER PENGATURAN PAJAK - UNHAPPY RATE & HAPPY PATH (Pajak Uji Siklus H)
  // =========================================================================
  test('H-04: Master Pengaturan Pajak - Unhappy Rate & Registrasi Tarif (Pajak Uji Siklus H)', async ({ page }) => {
    await page.goto('http://localhost:8009/master/pajak/create');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Tambah Tarif Pajak|Pengaturan Pajak/i);

    // Unhappy: rate > 100
    await page.fill('input[name="name"]', 'Pajak Invalid > 100%');
    await page.selectOption('select[name="type"]', 'PPN');
    await page.fill('input[name="rate"]', '150');

    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle', timeout: 15000 }),
      page.evaluate(() => {
        const form = document.querySelector('main form') as HTMLFormElement;
        if (form) {
          form.noValidate = true;
          form.submit();
        }
      })
    ]);

    await expect(page.locator('body')).toContainText(/tidak boleh lebih dari 100|maksimal 100/i);
    await saveEvidence(page, 'h_04a_pajak_unhappy.png');

    // Happy: Tarif 12% PPN resmi
    await page.fill('input[name="name"]', 'Pajak Uji Siklus H');
    await page.selectOption('select[name="type"]', 'PPN');
    await page.fill('input[name="rate"]', '12');
    await page.fill('input[name="effective_date"]', '2026-11-12');
    await page.check('input[name="status"][value="1"]');

    await Promise.all([
      page.waitForURL(url => url.pathname.includes('/master/pajak') && !url.pathname.includes('/create'), { timeout: 15000 }),
      page.click('main form button[type="submit"]')
    ]);

    await expect(page.locator('body')).toContainText('Pajak Uji Siklus H');
    await expect(page.locator('body')).toContainText('12');
    await saveEvidence(page, 'h_04b_pajak_created.png');
  });

  // =========================================================================
  // H-05: MASTER MATA UANG & KURS - HAPPY PATH (CUR-H)
  // =========================================================================
  test('H-05: Master Mata Uang & Kurs - Registrasi Valuta Baru (CUR-H / S$ 12.500)', async ({ page }) => {
    await page.goto('http://localhost:8009/master/mata-uang/create');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Tambah Mata Uang Baru/i);

    await page.fill('input[name="code"]', 'CUR-H');
    await page.fill('input[name="symbol"]', 'S$');
    await page.fill('input[name="name"]', 'Dollar Uji Siklus H');
    
    // Set kurs input display dan hidden
    await page.evaluate(() => {
      const display = document.getElementById('display_to_rupiah') as HTMLInputElement;
      const hidden = document.getElementById('to_rupiah') as HTMLInputElement;
      if (display) display.value = '12.500';
      if (hidden) hidden.value = '12500';
    });

    await Promise.all([
      page.waitForURL(url => url.pathname.includes('/master/mata-uang') && !url.pathname.includes('/create'), { timeout: 15000 }),
      page.click('main form button[type="submit"]')
    ]);

    await expect(page.locator('body')).toContainText('CUR-H');
    await expect(page.locator('body')).toContainText('S$');
    await saveEvidence(page, 'h_05_mata_uang_created.png');
  });

  // =========================================================================
  // H-06: DASHBOARD EKSEKUTIF - KPI WIDGETS & CABANG FILTER
  // =========================================================================
  test('H-06: Dashboard Eksekutif - Verifikasi Agregasi Metrik & Filter Cabang', async ({ page }) => {
    await page.goto('http://localhost:8009/dashboard');
    await page.waitForLoadState('networkidle');

    // Verifikasi keberadaan kartu metrik utama
    await expect(page.locator('body')).toContainText(/Penjualan|Omzet|Total Penjualan/i);
    await expect(page.locator('body')).toContainText(/Piutang|Tagihan/i);
    await expect(page.locator('body')).toContainText(/Kas & Bank|Saldo Kas/i);

    // Ganti filter cabang ke Cabang Bima (ID: 1)
    await page.goto('http://localhost:8009/dashboard?cabang_id=1&periode=month');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/Cabang Bima/i);
    await saveEvidence(page, 'h_06_dashboard_executive.png');
  });

  // =========================================================================
  // H-07: LAPORAN PENJUALAN - INDEX KPI & EKSPOR DOKUMEN (PDF & EXCEL)
  // =========================================================================
  test('H-07: Laporan Penjualan - Ringkasan KPI, Mode Invoice & Ekspor Dokumen', async ({ page }) => {
    await page.goto('http://localhost:8009/penjualan/laporan');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText('Laporan Penjualan');
    await expect(page.locator('body')).toContainText(/Penjualan \(Invoice\)|Pengiriman \(DO\)/i);

    // Verifikasi endpoint Cetak PDF & Ekspor Excel via page.request
    const pdfRes = await page.request.get('http://localhost:8009/penjualan/laporan/pdf');
    expect(pdfRes.status()).toBe(200);
    expect(pdfRes.headers()['content-type']).toContain('application/pdf');

    const excelRes = await page.request.get('http://localhost:8009/penjualan/laporan/excel');
    expect(excelRes.status()).toBe(200);
    expect(excelRes.headers()['content-type']).toMatch(/vnd\.openxmlformats|spreadsheet|octet-stream/i);

    await saveEvidence(page, 'h_07_laporan_penjualan.png');
  });

  // =========================================================================
  // H-08: LAPORAN PEMBELIAN - RINGKASAN & EKSPOR DOKUMEN (PDF & EXCEL)
  // =========================================================================
  test('H-08: Laporan Pembelian - Ringkasan Pengadaan & Ekspor Dokumen', async ({ page }) => {
    await page.goto('http://localhost:8009/pembelian/laporan');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText('Laporan Pembelian');

    const pdfRes = await page.request.get('http://localhost:8009/pembelian/laporan/pdf');
    expect(pdfRes.status()).toBe(200);
    expect(pdfRes.headers()['content-type']).toContain('application/pdf');

    const excelRes = await page.request.get('http://localhost:8009/pembelian/laporan/excel');
    expect(excelRes.status()).toBe(200);
    expect(excelRes.headers()['content-type']).toMatch(/vnd\.openxmlformats|spreadsheet|octet-stream/i);

    await saveEvidence(page, 'h_08_laporan_pembelian.png');
  });

  // =========================================================================
  // H-09: LAPORAN PERSEDIAAN / GUDANG - SALDO STOK & EKSPOR DOKUMEN
  // =========================================================================
  test('H-09: Laporan Persediaan Gudang - Saldo Fisik & Ekspor Dokumen', async ({ page }) => {
    await page.goto('http://localhost:8009/gudang/laporan');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText('Laporan Persediaan & Mutasi Stok');

    const pdfRes = await page.request.get('http://localhost:8009/gudang/laporan/pdf');
    expect(pdfRes.status()).toBe(200);
    expect(pdfRes.headers()['content-type']).toContain('application/pdf');

    const excelRes = await page.request.get('http://localhost:8009/gudang/laporan/excel');
    expect(excelRes.status()).toBe(200);
    expect(excelRes.headers()['content-type']).toMatch(/vnd\.openxmlformats|spreadsheet|octet-stream/i);

    await saveEvidence(page, 'h_09_laporan_gudang.png');
  });

  // =========================================================================
  // H-10: LAPORAN ANALITIS & GRAFIK KEUANGAN LANJUTAN (ALK & AGEING)
  // =========================================================================
  test('H-10: Laporan Analitis Keuangan - Grafik ALK & Analisis Umur Piutang (Ageing)', async ({ page }) => {
    // 1. Grafik Analisis Laporan Keuangan (ALK)
    await page.goto('http://localhost:8009/reports/alk-grafik/preview');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/Pendapatan|Laba Bersih|Current Ratio|ALK Grafik/i);
    await saveEvidence(page, 'h_10a_alk_grafik.png');

    // 2. Laporan Umur Piutang (Ageing Report)
    await page.goto('http://localhost:8009/reports/ageing-report/preview');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/Ageing Report|0-30 Hari|31-60 Hari|Piutang/i);
    await saveEvidence(page, 'h_10b_ageing_report.png');
  });

  // =========================================================================
  // H-11: RBAC SECURITY BOUNDARY CHECK (403 FORBIDDEN LINTAS PERAN)
  // =========================================================================
  test('H-11: RBAC Security Boundary Check - Penolakan 403 Forbidden pada Master Konfigurasi', async ({ browser }) => {
    // 1. Warehouse Staff
    const wsCtx = await browser.newContext({ storageState: { cookies: [], origins: [] } });
    const wsPage = await wsCtx.newPage();

    await wsPage.goto('http://localhost:8009/login');
    await wsPage.fill('input[name="login"]', 'warehouse_staff@example.com');
    await wsPage.fill('input[name="password"]', 'password');
    await wsPage.click('button[type="submit"]');
    await wsPage.waitForURL('**/dashboard', { timeout: 15000 });

    for (const url of ['/master/cabang', '/master/mata-uang', '/master/pajak']) {
      const res = await wsPage.goto(`http://localhost:8009${url}`);
      expect(res?.status(), `Warehouse Staff harus ditolak 403 pada ${url}`).toBe(403);
    }

    // 2. Sales
    const salesCtx = await browser.newContext({ storageState: { cookies: [], origins: [] } });
    const salesPage = await salesCtx.newPage();

    await salesPage.goto('http://localhost:8009/login');
    await salesPage.fill('input[name="login"]', 'sales@example.com');
    await salesPage.fill('input[name="password"]', 'password');
    await salesPage.click('button[type="submit"]');
    await salesPage.waitForURL('**/dashboard', { timeout: 15000 });

    for (const url of ['/master/cabang', '/master/mata-uang', '/master/pajak']) {
      const res = await salesPage.goto(`http://localhost:8009${url}`);
      expect(res?.status(), `Sales harus ditolak 403 pada ${url}`).toBe(403);
    }

    await saveEvidence(salesPage, 'h_11_rbac_403_boundary.png');

    await wsCtx.close();
    await salesCtx.close();
  });

  // =========================================================================
  // H-12: HARD REFRESH, MOBILE VIEWPORT & INDEPENDENT DB VERIFICATION
  // =========================================================================
  test('H-12: Hard Refresh, Mobile Viewport (375x812) & Verifikasi Independen DB', async ({ page }) => {
    // 1. Hard Refresh Persistence
    for (const check of [
      { url: '/master/cabang?search=CBG-H-001', expected: 'CBG-H-001' },
      { url: '/master/kategori?search=KAT-H-001', expected: 'KAT-H-001' },
      { url: '/master/satuan?search=PKT-H', expected: 'PKT-H' },
      { url: '/master/pajak?search=Pajak+Uji+Siklus+H', expected: 'Pajak Uji Siklus H' },
      { url: '/master/mata-uang?search=CUR-H', expected: 'CUR-H' },
    ]) {
      await page.goto(`http://localhost:8009${check.url}`);
      await page.waitForLoadState('networkidle');
      await page.reload({ waitUntil: 'networkidle' });
      await expect(page.locator('body')).toContainText(check.expected);
    }

    // 2. Mobile Viewport on Dashboard
    await page.setViewportSize({ width: 375, height: 812 });
    await page.goto('http://localhost:8009/dashboard');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Penjualan|Omzet|Duta Tunggal/i);
    await saveEvidence(page, 'h_12_dashboard_mobile.png');

    // 3. Verifikasi Independen Database
    console.log('[Audit] Menjalankan scripts/verify-h-cycle.php...');
    const auditOut = execSync('php scripts/verify-h-cycle.php', { encoding: 'utf-8' });
    console.log(auditOut);
    expect(auditOut).toContain('100% EXACT MATCH / REKONSILIASI SEMPURNA');
  });
});
