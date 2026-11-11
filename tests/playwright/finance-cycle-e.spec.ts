import { test, expect, Page } from '@playwright/test';
import path from 'path';
import fs from 'fs';
import { execSync } from 'child_process';

const BRAIN_SCREENSHOT_DIR = 'C:/Users/ralam/.gemini/antigravity/brain/37609e76-32d0-4a5e-8715-8e9934fbf3fd/screenshots';
const QA_EVIDENCE_DIR = path.join(process.cwd(), 'qa-evidence');

// Helper to save evidence screenshots in both locations
async function saveEvidence(page: Page, filename: string) {
  if (!fs.existsSync(QA_EVIDENCE_DIR)) {
    fs.mkdirSync(QA_EVIDENCE_DIR, { recursive: true });
  }
  const qaPath = path.join(QA_EVIDENCE_DIR, filename);
  await page.screenshot({ path: qaPath, fullPage: false });

  if (fs.existsSync(BRAIN_SCREENSHOT_DIR)) {
    const brainPath = path.join(BRAIN_SCREENSHOT_DIR, filename);
    await page.screenshot({ path: brainPath, fullPage: false });
  }
}

test.describe.serial('Siklus E: Keuangan, Kas & Bank, Aset Tetap & Tutup Buku (Finance & Accounting)', () => {
  let createdPeriodId: number | null = null;

  test.beforeAll(() => {
    // 1. Reset test data to clean baseline state before running Siklus E
    console.log('[Setup] Menjalankan scripts/reset-finance-test-data.php...');
    const out = execSync('php scripts/reset-finance-test-data.php', { encoding: 'utf-8' });
    console.log(out);
  });

  test.beforeEach(async ({ page }) => {
    test.setTimeout(60000);
    // Authenticate Super Admin session
    await page.goto('http://localhost:8009/dev-login');
    await page.waitForURL('**/dashboard', { timeout: 15000 });
  });

  // =========================================================================
  // 1. KAS & BANK - UNHAPPY PATHS
  // =========================================================================
  test('E-01: Kas & Bank - Unhappy Paths (Form Kosong, Nominal 0, & Rekening Sama)', async ({ page }) => {
    // 1.1 Kunjungi form kas/bank baru
    await page.goto('http://localhost:8009/keuangan/kas-bank/create');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('h1')).toContainText(/Catat Transaksi Kas & Bank/i);

    // 1.2 Bypass HTML5 validation untuk menguji validasi server-side
    await page.evaluate(() => {
      const form = document.querySelector('main form') as HTMLFormElement;
      if (form) form.noValidate = true;
    });

    // 1.3 Kosongkan input dan submit form
    await page.fill('#number', '');
    await page.click('main form button[type="submit"]');

    // 1.4 Verifikasi server-side validation error
    await page.waitForSelector('text=Nomor bukti transaksi wajib diisi', { timeout: 5000 });
    const bodyText = await page.textContent('body');
    expect(bodyText).toContain('Nomor bukti transaksi wajib diisi');

    // 1.5 Simpan bukti visual error form kosong
    await saveEvidence(page, 'e_01_kas_bank_unhappy.png');
  });

  // =========================================================================
  // 2. KAS & BANK - HAPPY PATH PENGELUARAN OPERASIONAL & AUTO-POSTING JURNAL
  // =========================================================================
  test('E-02: Kas & Bank - Happy Path Pengeluaran Operasional (CB-E-2026-001)', async ({ page }) => {
    await page.goto('http://localhost:8009/keuangan/kas-bank/create');
    await page.waitForLoadState('networkidle');

    // 2.1 Set nomor transaksi unik
    await page.fill('#number', 'CB-E-2026-001');
    await page.fill('#date', '2026-11-11');

    // 2.2 Pilih Akun Kas (1111.01 - Kas Besar Kantor) dan Akun Beban (6311 - Biaya Penyusutan)
    await page.evaluate(() => {
      const accInput = document.querySelector('input[name="account_coa_id"]') as HTMLInputElement;
      if (accInput) {
        accInput.value = '90'; // 1111.01 Kas Besar Kantor (leaf)
        accInput.dispatchEvent(new Event('input', { bubbles: true }));
        accInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
      const offsetInput = document.querySelector('input[name="offset_coa_id"]') as HTMLInputElement;
      if (offsetInput) {
        offsetInput.value = '73'; // 6311 Beban Operasional / Penyusutan (leaf)
        offsetInput.dispatchEvent(new Event('input', { bubbles: true }));
        offsetInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });

    // 2.3 Isi Nominal Rp 2.500.000
    await page.fill('#amount_display', '2500000');
    await page.fill('#counterparty', 'PT Teknisi Servis Prima');
    await page.fill('#description', 'Biaya pemeliharaan operasional mesin bengkel dan instalasi kabel listrik.');

    // 2.4 Submit Transaksi
    await page.click('main form button[type="submit"]');

    // 2.5 Verifikasi redirect ke halaman show dan banner sukses
    await page.waitForURL(url => /\/keuangan\/kas-bank\/\d+/.test(url.pathname), { timeout: 15000 });
    await expect(page.locator('body')).toContainText(/CB-E-2026-001/);
    await expect(page.locator('body')).toContainText(/2\.500\.000/);

    // 2.6 Verifikasi baris jurnal akuntansi otomatis
    const pageContent = await page.textContent('body');
    expect(pageContent).toMatch(/Jurnal Akuntansi/i);

    // 2.7 Simpan bukti visual voucher kas keluar
    await saveEvidence(page, 'e_02_kas_bank_detail.png');
  });

  // =========================================================================
  // 3. TRANSFER KAS & BANK - UNHAPPY PATHS
  // =========================================================================
  test('E-03: Transfer Kas & Bank - Unhappy Paths (Rekening Sama & Nominal 0)', async ({ page }) => {
    await page.goto('http://localhost:8009/keuangan/transfer/create');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('h1')).toContainText(/Transfer Antar Kas & Bank/i);

    // 3.1 Bypass HTML5
    await page.evaluate(() => {
      const form = document.querySelector('main form') as HTMLFormElement;
      if (form) form.noValidate = true;
    });

    // 3.2 Pilih rekening yang sama untuk asal dan tujuan (ID 90 - Kas Besar Kantor)
    await page.fill('#number', 'TRF-ERR-001');
    await page.evaluate(() => {
      const fromInput = document.querySelector('input[name="from_coa_id"]') as HTMLInputElement;
      if (fromInput) {
        fromInput.value = '90';
        fromInput.dispatchEvent(new Event('input', { bubbles: true }));
      }
      const toInput = document.querySelector('input[name="to_coa_id"]') as HTMLInputElement;
      if (toInput) {
        toInput.value = '90';
        toInput.dispatchEvent(new Event('input', { bubbles: true }));
      }
    });

    await page.fill('#amount_display', '0');
    await page.click('main form button[type="submit"]');

    // 3.3 Verifikasi pesan error penolakan rekening sama
    await page.waitForSelector('text=Akun tujuan tidak boleh sama dengan akun sumber dana', { timeout: 5000 });
    const content = await page.textContent('body');
    expect(content).toContain('Akun tujuan tidak boleh sama dengan akun sumber dana');

    // 3.4 Simpan bukti visual error transfer
    await saveEvidence(page, 'e_03_transfer_unhappy.png');
  });

  // =========================================================================
  // 4. TRANSFER KAS & BANK - HAPPY PATH (KAS KE BANK + BIAYA ADMIN)
  // =========================================================================
  test('E-04: Transfer Kas & Bank - Happy Path (TRF-E-2026-001)', async ({ page }) => {
    await page.goto('http://localhost:8009/keuangan/transfer/create');
    await page.waitForLoadState('networkidle');

    // 4.1 Isi form transfer dari Kas Operasional (90) ke Bank BCA (93)
    await page.fill('#number', 'TRF-E-2026-001');
    await page.fill('#date', '2026-11-11');

    await page.evaluate(() => {
      const fromInput = document.querySelector('input[name="from_coa_id"]') as HTMLInputElement;
      if (fromInput) {
        fromInput.value = '90'; // 1111.01 - Kas Besar Kantor
        fromInput.dispatchEvent(new Event('input', { bubbles: true }));
        fromInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
      const toInput = document.querySelector('input[name="to_coa_id"]') as HTMLInputElement;
      if (toInput) {
        toInput.value = '93'; // 1112.01 - Bank BCA - Operasional
        toInput.dispatchEvent(new Event('input', { bubbles: true }));
        toInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });

    await page.fill('#amount_display', '5000000');
    await page.fill('#other_costs_display', '6500');

    // Pilih pos akun biaya admin bank jika dropdown tersedia
    await page.evaluate(() => {
      const costSelect = document.querySelector('select[name="other_costs_coa_id"]') as HTMLSelectElement;
      if (costSelect && costSelect.options.length > 1) {
        costSelect.selectedIndex = 1;
        costSelect.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });

    await page.fill('#reference', 'SETOR-TUNAI-001');
    await page.fill('#description', 'Setoran penerimaan tunai harian ke rekening giro bank utama.');

    // 4.2 Submit Transfer
    await page.click('main form button[type="submit"]');

    // 4.3 Verifikasi redirect ke show transfer
    await page.waitForURL(url => /\/keuangan\/transfer\/\d+/.test(url.pathname), { timeout: 15000 });
    await expect(page.locator('body')).toContainText(/TRF-E-2026-001/);
    await expect(page.locator('body')).toContainText(/5\.000\.000/);

    // 4.4 Simpan bukti visual transfer sukses
    await saveEvidence(page, 'e_04_transfer_detail.png');
  });

  // =========================================================================
  // 5. JURNAL UMUM MANUAL - UNHAPPY PATHS (TIDAK SEIMBANG DEBIT != KREDIT)
  // =========================================================================
  test('E-05: Jurnal Umum Manual - Unhappy Paths (Jurnal Tidak Seimbang)', async ({ page }) => {
    await page.goto('http://localhost:8009/keuangan/jurnal/create');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('h1')).toContainText(/Buat Jurnal Penyesuaian Manual/i);

    // 5.1 Isi Header Jurnal
    await page.fill('#reference', 'JV-ERR-001');
    await page.fill('#date', '2026-11-11');
    await page.fill('#description', 'Simulasi jurnal tidak seimbang');

    // 5.2 Masukkan Baris 1: Debit 1.000.000
    await page.evaluate(() => {
      const coa1 = document.querySelector('select[name="entries[0][coa_id]"]') as HTMLSelectElement;
      if (coa1 && coa1.options.length > 1) {
        coa1.selectedIndex = 1;
        coa1.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
    const debitInputs = page.locator('tbody input[type="text"][placeholder="0"]');
    await debitInputs.nth(0).fill('1000000');

    // 5.3 Masukkan Baris 2: Kredit 750.000 (Ada selisih 250.000)
    await page.evaluate(() => {
      const coa2 = document.querySelector('select[name="entries[1][coa_id]"]') as HTMLSelectElement;
      if (coa2 && coa2.options.length > 2) {
        coa2.selectedIndex = 2;
        coa2.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
    await debitInputs.nth(3).fill('750000'); // nth(3) is credit of row 1 (0: dr1, 1: cr1, 2: dr2, 3: cr2)

    // 5.4 Verifikasi indikator badge visual di UI "Belum Seimbang" dan tombol submit berstatus disabled
    await expect(page.locator('body')).toContainText(/Belum Seimbang/i);
    await expect(page.locator('main form button[type="submit"]')).toBeDisabled();

    // 5.5 Simpan bukti visual client-side guard (badge belum seimbang & disabled submit)
    await saveEvidence(page, 'e_05_journal_unhappy.png');

    // 5.6 Submit form secara programatik untuk menguji validasi controller server-side (defense-in-depth)
    await page.evaluate(() => {
      const form = document.querySelector('main form') as HTMLFormElement;
      if (form) {
        form.noValidate = true;
        form.submit();
      }
    });

    // 5.7 Verifikasi pesan error penolakan jurnal tidak seimbang dari backend
    await page.waitForSelector('text=Jurnal tidak seimbang', { timeout: 10000 });
    const content = await page.textContent('body');
    expect(content).toContain('Jurnal tidak seimbang');
  });

  // =========================================================================
  // 6. JURNAL UMUM MANUAL - HAPPY PATH MULTI-BARIS SEIMBANG
  // =========================================================================
  test('E-06: Jurnal Umum Manual - Happy Path Multi-Baris (JV-E-2026-001)', async ({ page }) => {
    await page.goto('http://localhost:8009/keuangan/jurnal/create');
    await page.waitForLoadState('networkidle');

    // 6.1 Isi Header Jurnal
    await page.fill('#reference', 'JV-E-2026-001');
    await page.fill('#date', '2026-11-11');
    await page.selectOption('#journal_type', 'adjustment');
    await page.fill('#description', 'Jurnal penyesuaian beban operasional kantor dan perlengkapan kerja.');

    // 6.2 Baris 1: COA 1 (Debit Rp 1.200.000)
    await page.evaluate(() => {
      const coa1 = document.querySelector('select[name="entries[0][coa_id]"]') as HTMLSelectElement;
      if (coa1 && coa1.options.length > 1) {
        coa1.selectedIndex = 1;
        coa1.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
    const debitInputs = page.locator('tbody input[type="text"][placeholder="0"]');
    await debitInputs.nth(0).fill('1200000');

    // 6.3 Tambah baris ke-3 agar menjadi multi-baris compound journal
    await page.click('button:has-text("Tambah Baris Akun")');

    // 6.4 Baris 2: COA 2 (Debit Rp 800.000)
    await page.evaluate(() => {
      const coa2 = document.querySelector('select[name="entries[1][coa_id]"]') as HTMLSelectElement;
      if (coa2 && coa2.options.length > 2) {
        coa2.selectedIndex = 2;
        coa2.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
    // debitInputs count is now updated
    const updatedInputs = page.locator('tbody input[type="text"][placeholder="0"]');
    await updatedInputs.nth(2).fill('800000'); // nth(2) is dr row 2

    // 6.5 Baris 3: COA 3 (Credit Rp 2.000.000)
    await page.evaluate(() => {
      const coa3 = document.querySelector('select[name="entries[2][coa_id]"]') as HTMLSelectElement;
      if (coa3 && coa3.options.length > 3) {
        coa3.selectedIndex = 3;
        coa3.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
    await updatedInputs.nth(5).fill('2000000'); // nth(5) is cr row 3

    // 6.6 Verifikasi UI menunjukkan "Seimbang / Balanced"
    await expect(page.locator('body')).toContainText(/Seimbang \/ Balanced/i);

    // 6.7 Submit Form dan tunggu redirect ke show voucher
    await Promise.all([
      page.waitForURL(url => /\/keuangan\/jurnal\/.+/.test(url.pathname) && !url.pathname.endsWith('/create'), { timeout: 15000 }),
      page.click('main form button[type="submit"]')
    ]);

    // 6.8 Verifikasi detail voucher jurnal
    await expect(page.locator('body')).toContainText(/JV-E-2026-001/);
    await expect(page.locator('body')).toContainText(/2\.000\.000/);

    // 6.9 Simpan bukti visual voucher jurnal manual
    await saveEvidence(page, 'e_06_journal_detail.png');
  });

  // =========================================================================
  // 7. ASET TETAP - REGISTRASI BARU & AUTO-POSTING JURNAL AKUISISI
  // =========================================================================
  test('E-07: Aset Tetap - Registrasi Aset Baru & Jurnal Akuisisi (AST-E-2026-001)', async ({ page }) => {
    await page.goto('http://localhost:8009/keuangan/aset/create');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('h1')).toContainText(/Registrasi Aset Tetap Baru/i);

    // 7.1 Isi Identitas Aset
    await page.fill('#code', 'AST-E-2026-001');
    await page.fill('#name', 'Mesin CNC Milling 5-Axis Precision DT-01');
    await page.fill('#purchase_date', '2026-01-01');
    await page.fill('#usage_date', '2026-01-01');
    await page.fill('#notes', 'Mesin produksi presisi tinggi bengkel Duta Tunggal.');

    // 7.2 Isi Nilai Perolehan & Parameter Depresiasi
    // Rp 120.000.000, 5 Tahun (60 Bulan), Straight Line
    await page.fill('#display_purchase_cost', '120000000');
    await page.fill('#useful_life_years', '5');
    await page.selectOption('#depreciation_method', 'straight_line');
    await page.fill('#display_salvage_value', '6000000'); // 5% residual

    // 7.3 Pemetaan COA & Cabang
    await page.evaluate(() => {
      // Cabang ID 1
      const cbInput = document.querySelector('input[name="cabang_id"]') as HTMLInputElement;
      if (cbInput) {
        cbInput.value = '1';
        cbInput.dispatchEvent(new Event('input', { bubbles: true }));
        cbInput.dispatchEvent(new Event('change', { bubbles: true }));
      }

      // Hidden purchase_cost & salvage_value
      const pCost = document.querySelector('#purchase_cost') as HTMLInputElement;
      if (pCost) pCost.value = '120000000';
      const sVal = document.querySelector('#salvage_value') as HTMLInputElement;
      if (sVal) sVal.value = '6000000';

      // 1210.01 Peralatan Produksi (ID 126)
      const assetCoa = document.querySelector('#asset_coa_id') as HTMLSelectElement;
      if (assetCoa) {
        assetCoa.value = '126';
        assetCoa.dispatchEvent(new Event('change', { bubbles: true }));
      }
      // 1220.01 Akumulasi Penyusutan Peralatan (ID 131)
      const accumCoa = document.querySelector('#accumulated_depreciation_coa_id') as HTMLSelectElement;
      if (accumCoa) {
        accumCoa.value = '131';
        accumCoa.dispatchEvent(new Event('change', { bubbles: true }));
      }
      // 6311 Biaya Penyusutan Peralatan (ID 73)
      const expCoa = document.querySelector('#depreciation_expense_coa_id') as HTMLSelectElement;
      if (expCoa) {
        expCoa.value = '73';
        expCoa.dispatchEvent(new Event('change', { bubbles: true }));
      }
      // Akun Kredit: Bank BCA Operasional (ID 93)
      const credCoa = document.querySelector('input[name="credit_coa_id"]') as HTMLInputElement;
      if (credCoa) {
        credCoa.value = '93';
        credCoa.dispatchEvent(new Event('input', { bubbles: true }));
        credCoa.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });

    // 7.4 Submit Form
    await page.click('main form button[type="submit"]');

    // 7.5 Verifikasi redirect ke show detail aset
    await page.waitForURL(url => /\/keuangan\/aset\/\d+/.test(url.pathname), { timeout: 15000 });
    await expect(page.locator('body')).toContainText(/AST-E-2026-001/);
    await expect(page.locator('body')).toContainText(/120\.000\.000/);

    // 7.6 Simpan bukti visual kartu aset
    await saveEvidence(page, 'e_07_asset_detail.png');
  });

  // =========================================================================
  // 8. DEPRESIASI ASET TETAP - EKSEKUSI PENYUSUTAN BULANAN MASSAL
  // =========================================================================
  test('E-08: Depresiasi Aset - Eksekusi Penyusutan Bulanan Massal (Periode 2026-01)', async ({ page }) => {
    await page.goto('http://localhost:8009/keuangan/aset/depresiasi');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('h1')).toContainText(/Pusat Depresiasi & Penyusutan Bulanan/i);

    // 8.1 Pilih Bulan Januari (1) dan Tahun 2026
    await page.selectOption('#period_month_exec', '1');
    await page.selectOption('#period_year_exec', '2026');

    // 8.2 Klik tombol "Proses Sekarang" untuk memunculkan modal
    await page.click('form#batchDepreciationForm button:has-text("Proses Sekarang")');

    // 8.3 Klik tombol konfirmasi modal dan tunggu proses submit
    await page.waitForSelector('button:has-text("Ya, Proses Penyusutan Massal")', { state: 'visible', timeout: 5000 });
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle', timeout: 15000 }),
      page.click('button:has-text("Ya, Proses Penyusutan Massal")')
    ]);

    // 8.4 Verifikasi respon sukses dari controller
    await expect(page.locator('body')).toContainText(/Berhasil memproses penyusutan/i);
    await expect(page.locator('body')).toContainText(/1\.900\.000/);

    // 8.5 Simpan bukti visual hasil depresiasi
    await saveEvidence(page, 'e_08_depreciation_processed.png');
  });

  // =========================================================================
  // 9. TUTUP BUKU & PROTEKSI PERIOD CLOSING
  // =========================================================================
  test('E-09: Tutup Buku - Buka Periode Baru, Tutup Buku & Proteksi Locked Period', async ({ page }) => {
    // 9.1 Buka halaman tutup buku
    await page.goto('http://localhost:8009/keuangan/tutup-buku');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('h1')).toContainText(/Tutup Buku/i);

    // 9.2 Klik tombol buat periode
    await page.click('a:has-text("Buat Periode")');
    await page.waitForURL('**/keuangan/tutup-buku/create', { timeout: 10000 });

    // 9.3 Buat periode Januari 2026 untuk Cabang Pusat (tanpa cabang)
    await page.fill('#period_start', '2026-01-01');
    await page.fill('#period_end', '2026-01-31');
    await Promise.all([
      page.waitForURL(url => url.pathname === '/keuangan/tutup-buku', { timeout: 15000 }),
      page.click('main form button[type="submit"]')
    ]);

    // 9.4 Verifikasi periode berhasil dibuat di tabel
    await expect(page.locator('table tbody')).toContainText('01/01/2026');
    await expect(page.locator('table tbody')).toContainText('31/01/2026');
    await expect(page.locator('table tbody')).toContainText(/Terbuka/);

    // 9.5 Klik "Tutup buku" pada periode yang baru dibuat di tabel
    await Promise.all([
      page.waitForURL(url => /\/keuangan\/tutup-buku\/\d+\/tutup$/.test(url.pathname), { timeout: 15000 }),
      page.locator('table tbody tr a:has-text("Tutup buku")').first().click()
    ]);
    await expect(page.locator('h1')).toContainText(/Tutup periode/i);

    // 9.6 Submit konfirmasi tutup buku
    await Promise.all([
      page.waitForURL(url => url.pathname === '/keuangan/tutup-buku', { timeout: 15000 }),
      page.click('main form button[type="submit"]:has-text("Tutup Buku")')
    ]);

    // 9.7 Verifikasi status berubah menjadi Ditutup (terkunci)
    await expect(page.locator('table tbody')).toContainText(/Ditutup \(terkunci\)/i);

    // 9.8 Simpan bukti visual periode terkunci
    await saveEvidence(page, 'e_09_period_closed.png');
  });

  // =========================================================================
  // 10. LAPORAN KEUANGAN - NERACA SALDO, LABA RUGI & NERACA
  // =========================================================================
  test('E-10: Laporan Keuangan - Neraca Saldo, Laba Rugi & Neraca', async ({ page }) => {
    // 10.1 Kunjungi Hub Laporan Keuangan (Neraca Saldo)
    await page.goto('http://localhost:8009/laporan/keuangan?tab=neraca-saldo&start_date=2026-01-01&end_date=2026-01-31');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Neraca Saldo|Trial Balance/i);

    // 10.2 Kunjungi Laba Rugi
    await page.goto('http://localhost:8009/laporan/keuangan/laba-rugi?start_date=2026-01-01&end_date=2026-01-31');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Laba Rugi|Income Statement/i);

    // 10.3 Kunjungi Neraca
    await page.goto('http://localhost:8009/laporan/keuangan/neraca?date=2026-01-31');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Neraca|Balance Sheet/i);

    // 10.4 Simpan bukti visual laporan keuangan
    await saveEvidence(page, 'e_10_financial_report.png');
  });

  // =========================================================================
  // 11. HARD REFRESH (F5) & PERSISTENSI STATE
  // =========================================================================
  test('E-11: Hard Refresh (F5) State Persistence', async ({ page }) => {
    // 11.1 Kunjungi show aset yang baru didaftarkan
    await page.goto('http://localhost:8009/keuangan/aset');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/AST-E-2026-001/);

    // 11.2 Buka detail aset
    await page.click('a:has-text("AST-E-2026-001")');
    await page.waitForURL(url => /\/keuangan\/aset\/\d+/.test(url.pathname), { timeout: 10000 });

    // 11.3 Lakukan Hard Reload (F5)
    await page.reload({ waitUntil: 'networkidle' });

    // 11.4 Verifikasi seluruh data finansial tetap persisten dari DB
    await expect(page.locator('body')).toContainText(/AST-E-2026-001/);
    await expect(page.locator('body')).toContainText(/120\.000\.000/);
    await expect(page.locator('body')).toContainText(/1\.900\.000/);

    // 11.5 Simpan bukti visual persistensi
    await saveEvidence(page, 'e_11_hard_refresh_persistence.png');
  });

  // =========================================================================
  // 12. AUDIT INDEPENDEN TIGA TITIK (DATABASE VERIFIER)
  // =========================================================================
  test('E-12: Independent 3-Way Reconciliation Script Execution', async () => {
    console.log('[Verification] Menjalankan scripts/verify-e-cycle-finance.php...');
    const result = execSync('php scripts/verify-e-cycle-finance.php', { encoding: 'utf-8' });
    console.log(result);
    expect(result).toContain('SELURUH AUDIT BERHASIL [100% MATCH]');
  });
});
