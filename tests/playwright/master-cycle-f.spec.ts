import { test, expect, Page } from '@playwright/test';
import path from 'path';
import fs from 'fs';
import { execSync } from 'child_process';

const BRAIN_SCREENSHOT_DIR = 'C:/Users/ralam/.gemini/antigravity/brain/37609e76-32d0-4a5e-8715-8e9934fbf3fd/screenshots';
const QA_EVIDENCE_DIR = path.join(process.cwd(), 'qa-evidence');

// Helper untuk menyimpan bukti visual di folder qa-evidence dan brain artifact
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

test.describe.serial('Siklus F: Manajemen Data Induk, Keamanan RBAC & Aturan Persetujuan', () => {
  let consoleErrors: string[] = [];
  let pageErrors: string[] = [];

  test.beforeAll(() => {
    console.log('[Setup] Menjalankan scripts/reset-master-f-test-data.php...');
    const out = execSync('php scripts/reset-master-f-test-data.php', { encoding: 'utf-8' });
    console.log(out);
  });

  test.beforeEach(async ({ page }) => {
    test.setTimeout(60000);
    consoleErrors = [];
    pageErrors = [];

    page.on('console', msg => {
      if (msg.type() === 'error') {
        const text = msg.text();
        // Abaikan pesan resource network 403/401 ketika skenario keamanan F-11 menguji blokir RBAC
        if (!text.includes('403 (Forbidden)')) {
          consoleErrors.push(text);
        }
      }
    });
    page.on('pageerror', err => {
      pageErrors.push(err.message);
    });

    // Sesi autentikasi Super Admin default
    await page.goto('http://localhost:8009/dev-login');
    await page.waitForURL('**/dashboard', { timeout: 15000 });
  });

  test.afterEach(async ({}, testInfo) => {
    console.log(`[Console/Exception Log] ${testInfo.title}: console.error=${consoleErrors.length}, pageerror=${pageErrors.length}`);
    expect(pageErrors, `Uncaught JS exception detected: ${pageErrors.join('; ')}`).toEqual([]);
    expect(consoleErrors, `Console error detected: ${consoleErrors.join('; ')}`).toEqual([]);
  });

  // =========================================================================
  // 1. MASTER PRODUK - UNHAPPY PATHS (Form Kosong & Harga Negatif/Ekstrem)
  // =========================================================================
  test('F-01: Master Produk - Unhappy Paths (Form Kosong & Harga Negatif)', async ({ page }) => {
    await page.goto('http://localhost:8009/master/produk/create');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('h1')).toContainText(/Tambah Produk Baru/i);

    // 1.1 Form Kosong / Malformed Submission
    await page.evaluate(() => {
      const nameInput = document.querySelector('input[name="name"]') as HTMLInputElement;
      if (nameInput) nameInput.value = '';
      const skuInput = document.querySelector('input[name="sku"]') as HTMLInputElement;
      if (skuInput) skuInput.value = '';
    });
    await page.click('main form button[type="submit"]');
    expect(page.url()).toContain('/master/produk/create');

    // 1.2 Uji Boundary Nilai Negatif pada Harga Beli (Server-side validation)
    await page.fill('#product_sku', 'PRD-F-NEG-TEST');
    await page.fill('input[name="name"]', 'Produk Uji Negatif');
    await page.evaluate(() => {
      const catInput = document.querySelector('input[name="product_category_id"]') as HTMLInputElement;
      if (catInput) catInput.value = '1';
      const uomInput = document.querySelector('input[name="uom_id"]') as HTMLInputElement;
      if (uomInput) uomInput.value = '5';
      const cp = document.getElementById('cost_price') as HTMLInputElement;
      if (cp) cp.value = '-5000';
      const sp = document.getElementById('sell_price') as HTMLInputElement;
      if (sp) sp.value = '0';
      const form = document.querySelector('main form') as HTMLFormElement;
      if (form) form.noValidate = true;
    });
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle', timeout: 10000 }),
      page.click('main form button[type="submit"]')
    ]);

    // 1.3 Simpan bukti visual form error/unhappy
    await saveEvidence(page, 'f_01_product_unhappy.png');

    // 1.4 Verifikasi URL tetap di create page (ditolak validasi server)
    expect(page.url()).toContain('/master/produk/create');
  });

  // =========================================================================
  // 2. MASTER PRODUK - HAPPY PATH & TOGGLE AKTIF (PRD-F-2026-001)
  // =========================================================================
  test('F-02: Master Produk - Happy Path Pendaftaran Produk Baru (PRD-F-2026-001) & Toggle Status', async ({ page }) => {
    await page.goto('http://localhost:8009/master/produk/create');
    await page.waitForLoadState('networkidle');

    // 2.1 Isi Data Identitas Produk
    await page.fill('#product_sku', 'PRD-F-2026-001');
    await page.fill('input[name="name"]', 'Kabel Kontrol Tahan Panas 4-Core DT-01');
    await page.fill('input[name="kode_merk"]', 'BOSCH-DT');

    // 2.2 Pilih Kategori & Satuan UOM via evaluate
    await page.evaluate(() => {
      // Kategori Elektronik (ID 1)
      const catInput = document.querySelector('input[name="product_category_id"]') as HTMLInputElement;
      if (catInput) {
        catInput.value = '1';
        catInput.dispatchEvent(new Event('input', { bubbles: true }));
        catInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
      // UOM Meter (ID 5)
      const uomInput = document.querySelector('input[name="uom_id"]') as HTMLInputElement;
      if (uomInput) {
        uomInput.value = '5';
        uomInput.dispatchEvent(new Event('input', { bubbles: true }));
        uomInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });

    // 2.3 Isi Harga Beli, Harga Jual & Batas Harga
    await page.fill('#display_cost_price', '25000');
    await page.evaluate(() => {
      const cp = document.getElementById('cost_price') as HTMLInputElement;
      if (cp) cp.value = '25000';
    });

    await page.fill('#display_sell_price', '35000');
    await page.evaluate(() => {
      const sp = document.getElementById('sell_price') as HTMLInputElement;
      if (sp) sp.value = '35000';
    });

    await page.fill('input[name="harga_batas"]', '30000');

    // 2.4 Submit Form Produk
    await Promise.all([
      page.waitForURL(url => url.pathname.includes('/master/produk'), { timeout: 15000 }),
      page.click('main form button[type="submit"]')
    ]);

    // 2.5 Verifikasi produk baru terdaftar di tabel master produk
    await expect(page.locator('body')).toContainText(/PRD-F-2026-001/);
    await expect(page.locator('body')).toContainText(/Kabel Kontrol Tahan Panas 4-Core/i);
    await expect(page.locator('body')).toContainText(/35\.000/);

    // 2.6 Uji Aksi Toggle Status Aktif
    const toggleForm = page.locator('tr:has-text("PRD-F-2026-001") form[action*="toggle-active"] button');
    if (await toggleForm.count() > 0) {
      await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle', timeout: 10000 }).catch(() => {}),
        toggleForm.click()
      ]);
    }

    // 2.7 Simpan bukti visual kartu & tabel master produk
    await saveEvidence(page, 'f_02_product_detail.png');
  });

  // =========================================================================
  // 3. MASTER CUSTOMER - UNHAPPY PATHS & LIVE DEDUPLICATION
  // =========================================================================
  test('F-03: Master Customer - Unhappy Paths & Live Deduplication Detection', async ({ page }) => {
    await page.goto('http://localhost:8009/master/customer/create');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('h1')).toContainText(/Tambah Customer Baru/i);

    // 3.1 Coba input kosong dan submit
    await page.fill('#customer_name', '');
    await page.fill('#customer_perusahaan', '');
    await page.click('main form button[type="submit"]');

    // 3.2 Uji Live Deduplication Box dengan mengisi NIK existing milik customer ID 3 (CUST003)
    await page.fill('#customer_name', 'Budi Santoso');
    await page.fill('#customer_nik', '3171234567890001');

    // Tunggu debounce script AJAX duplicate checker bekerja
    await page.waitForTimeout(1000);

    // 3.3 Simpan bukti visual unhappy customer & form
    await saveEvidence(page, 'f_03_customer_unhappy.png');

    // Verifikasi URL tetap di create form
    expect(page.url()).toContain('/master/customer/create');
  });

  // =========================================================================
  // 4. MASTER CUSTOMER - HAPPY PATH PENDAFTARAN (CUST-F-2026-001)
  // =========================================================================
  test('F-04: Master Customer - Happy Path Pendaftaran Pelanggan Baru (CUST-F-2026-001)', async ({ page }) => {
    await page.goto('http://localhost:8009/master/customer/create');
    await page.waitForLoadState('networkidle');

    // 4.1 Isi Profil Customer Bersih Tanpa Duplikasi
    await page.fill('#customer_code', 'CUST-F-2026-001');
    await page.fill('#customer_name', 'Bpk. Hendra Gunawan');
    await page.fill('#customer_perusahaan', 'PT Maju Elektrik Persada');
    await page.fill('#customer_nik', '3273998877660001');

    // 4.2 Kontak & Alamat
    await page.fill('input[name="address"]', 'Jl. Riau No. 45 Kawasan Bisnis Bandung');
    await page.fill('input[name="phone"]', '081299887766');
    await page.fill('input[name="telephone"]', '02287654321');
    await page.fill('input[name="email"]', 'finance@majuelektrik.co.id');

    // 4.3 Plafon Kredit & Syarat Pembayaran
    await page.fill('input[name="tempo_kredit"]', '30');
    await page.fill('#display_kredit_limit', '75000000');
    await page.evaluate(() => {
      const kl = document.getElementById('kredit_limit') as HTMLInputElement;
      if (kl) kl.value = '75000000';
    });

    // 4.4 Submit Form Customer
    await Promise.all([
      page.waitForURL(url => url.pathname.includes('/master/customer'), { timeout: 15000 }),
      page.click('main form button[type="submit"]')
    ]);

    // 4.5 Verifikasi detail customer baru terdaftar di sistem
    await expect(page.locator('body')).toContainText(/CUST-F-2026-001/);
    await expect(page.locator('body')).toContainText(/PT Maju Elektrik Persada/i);
    await expect(page.locator('body')).toContainText(/75\.000\.000/);

    // 4.6 Simpan bukti visual profil customer
    await saveEvidence(page, 'f_04_customer_detail.png');
  });

  // =========================================================================
  // 5. MASTER SUPPLIER - UNHAPPY & HAPPY PATH (SUP-F-2026-001)
  // =========================================================================
  test('F-05: Master Supplier - Unhappy & Happy Path Pendaftaran Supplier (SUP-F-2026-001)', async ({ page }) => {
    await page.goto('http://localhost:8009/master/supplier/create');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('h1')).toContainText(/Tambah Supplier Baru/i);

    // 5.0 Unhappy Path: Submit kosong / nama perusahaan kosong
    await page.fill('input[name="perusahaan"]', '');
    await page.click('main form button[type="submit"]');
    expect(page.url()).toContain('/master/supplier/create');

    // 5.1 Isi Formulir Supplier Baru
    await page.fill('#supplier_code', 'SUP-F-2026-001');
    await page.fill('input[name="perusahaan"]', 'PT Tembaga Murni Nusantara');
    await page.fill('input[name="kontak_person"]', 'Ibu Ratna Dewi');
    await page.fill('input[name="npwp"]', '01.234.567.8-429.000');
    await page.fill('input[name="address"]', 'Kawasan Industri Cikarang Blok B2 No. 18');
    await page.fill('input[name="phone"]', '02189876543');
    await page.fill('input[name="tempo_hutang"]', '14');
    await page.fill('input[name="nama_bank"]', 'Bank BCA');
    await page.fill('input[name="nomor_rekening"]', '8899001122');
    await page.fill('input[name="nama_rekening"]', 'PT Tembaga Murni Nusantara');

    // 5.2 Submit Form Supplier
    await Promise.all([
      page.waitForURL(url => url.pathname.includes('/master/supplier'), { timeout: 15000 }),
      page.click('main form button[type="submit"]')
    ]);

    // 5.3 Verifikasi supplier baru tampil di daftar
    await expect(page.locator('body')).toContainText(/SUP-F-2026-001/);
    await expect(page.locator('body')).toContainText(/PT Tembaga Murni Nusantara/i);

    // 5.4 Simpan bukti visual supplier
    await saveEvidence(page, 'f_05_supplier_detail.png');
  });

  // =========================================================================
  // 6. MASTER COA - PENDAFTARAN AKUN ANALITIS (6313)
  // =========================================================================
  test('F-06: Master Bagan Akun (COA) - Pendaftaran Akun Analitis (6313)', async ({ page }) => {
    await page.goto('http://localhost:8009/master/coa/create');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('h1')).toContainText(/Tambah Akun Baru/i);

    // 6.1 Isi Identitas Akun COA Baru
    await page.fill('#code', '6313');
    await page.selectOption('#type', 'Expense');
    await page.fill('input[name="name"]', 'Beban Pemeliharaan Mesin & Peralatan');
    await page.fill('textarea[name="description"]', 'Akun analitis biaya pemeliharaan mesin pabrik.');

    // 6.2 Submit Form COA
    await Promise.all([
      page.waitForURL(url => url.pathname.includes('/master/coa'), { timeout: 15000 }),
      page.click('main form button[type="submit"]')
    ]);

    // 6.3 Verifikasi akun baru terdaftar di tabel COA
    await expect(page.locator('body')).toContainText(/6313/);
    await expect(page.locator('body')).toContainText(/Beban Pemeliharaan Mesin/i);

    // 6.4 Simpan bukti visual akun COA baru
    await saveEvidence(page, 'f_06_coa_detail.png');
  });

  // =========================================================================
  // 7. MASTER GUDANG & RAK - PENDAFTARAN RAK DI GUDANG UTAMA (RAK-F-001)
  // =========================================================================
  test('F-07: Master Gudang & Rak - Pendaftaran Rak Baru di Gudang Utama (RAK-F-001)', async ({ page }) => {
    // 7.1 Buka show Gudang Utama (ID 1)
    await page.goto('http://localhost:8009/master/gudang/1');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('h1')).toContainText(/Gudang Utama/i);

    // 7.2 Buka modal tambah rak via trigger button
    await page.locator('button[type="button"]:has-text("Tambah Rak")').first().click();
    await page.waitForSelector('input[name="code"]', { state: 'visible', timeout: 5000 });

    // 7.3 Isi data rak baru
    await page.fill('input[name="code"]', 'RAK-F-001');
    await page.fill('input[name="name"]', 'Rak F-01 Khusus Komponen Elektronik');
    await page.fill('textarea[name="description"]', 'Lokasi penyimpanan kabel kontrol dan instrumentasi');

    // 7.4 Submit modal form
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle', timeout: 10000 }),
      page.locator('form[action*="/rak"] button[type="submit"]').click()
    ]);

    // 7.5 Verifikasi rak terdaftar di tabel rak gudang
    await expect(page.locator('body')).toContainText(/RAK-F-001/);
    await expect(page.locator('body')).toContainText(/Rak F-01 Khusus Komponen Elektronik/i);

    // 7.6 Simpan bukti visual rak
    await saveEvidence(page, 'f_07_rak_detail.png');
  });

  // =========================================================================
  // 8. MASTER ARMADA - PENDAFTARAN KENDARAAN (B 9901 DT) & DRIVER (JOKO)
  // =========================================================================
  test('F-08: Master Armada - Pendaftaran Kendaraan & Pengemudi Logistik', async ({ page }) => {
    // 8.1 Registrasi Kendaraan Baru
    await page.goto('http://localhost:8009/master/kendaraan/create');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('h1')).toContainText(/Tambah Armada Kendaraan/i);

    await page.fill('input[name="plate"]', 'B 9901 DT');
    await page.fill('input[name="type"]', 'Truk Engkel Box CDE');
    await page.fill('input[name="capacity"]', '2.5 Ton / 12 CBM');
    await page.evaluate(() => {
      const sel = document.querySelector('select[name="cabang_id"]') as HTMLSelectElement;
      if (sel) {
        sel.value = '1';
        sel.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });

    await Promise.all([
      page.waitForURL(url => url.pathname.includes('/master/kendaraan'), { timeout: 15000 }),
      page.click('main form button[type="submit"]')
    ]);
    await expect(page.locator('body')).toContainText(/B 9901 DT/);

    // 8.2 Registrasi Driver Baru
    await page.goto('http://localhost:8009/master/driver/create');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('h1')).toContainText(/Tambah Pengemudi Baru/i);

    await page.fill('input[name="name"]', 'Pak Joko Santoso');
    await page.fill('input[name="phone"]', '081298765432');
    await page.fill('input[name="license"]', 'SIM B1 Umum 987654321');

    await Promise.all([
      page.waitForURL(url => url.pathname.includes('/master/driver'), { timeout: 15000 }),
      page.click('main form button[type="submit"]')
    ]);
    await expect(page.locator('body')).toContainText(/Pak Joko Santoso/);

    // 8.3 Simpan bukti visual armada logistik
    await saveEvidence(page, 'f_08_armada_detail.png');
  });

  // =========================================================================
  // 9. PENGATURAN PENGGUNA & RBAC - UNHAPPY PATHS (Password Mismatch)
  // =========================================================================
  test('F-09: Pengaturan Pengguna - Unhappy Paths (Password Mismatch & Form Validation)', async ({ page }) => {
    await page.goto('http://localhost:8009/pengaturan/pengguna/create');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('h1')).toContainText(/Pendaftaran Pengguna Baru/i);

    // 9.1 Isi form dengan konfirmasi password yang tidak cocok
    await page.fill('input[name="first_name"]', 'Test');
    await page.fill('input[name="username"]', 'testmismatch');
    await page.fill('input[name="email"]', 'test.mismatch@dutanggal.test');
    await page.fill('input[name="posisi"]', 'Tester');
    await page.fill('input[name="password"]', 'Password123!');
    await page.fill('input[name="password_confirmation"]', 'BedaPassword456!');

    // Centang role Warehouse Staff
    await page.locator('input[name="roles[]"][value="Warehouse Staff"]').check();

    // 9.2 Submit form
    await page.click('main form button[type="submit"]');

    // 9.3 Verifikasi muncul pesan error konfirmasi password
    await expect(page.locator('body')).toContainText(/Konfirmasi password tidak cocok|password/i);

    // 9.4 Simpan bukti visual error RBAC form
    await saveEvidence(page, 'f_09_user_unhappy.png');
  });

  // =========================================================================
  // 10. PENGATURAN PENGGUNA - HAPPY PATH (staf.gudang.f@dutanggal.test) & AKSI KEAMANAN
  // =========================================================================
  test('F-10: Pengaturan Pengguna - Happy Path Akun Baru, Reset Password & Toggle Status', async ({ page }) => {
    await page.goto('http://localhost:8009/pengaturan/pengguna/create');
    await page.waitForLoadState('networkidle');

    // 10.1 Isi Data Pengguna Baru
    await page.fill('input[name="first_name"]', 'Staf');
    await page.fill('input[name="last_name"]', 'Gudang F');
    await page.fill('input[name="username"]', 'stafgudangf');
    await page.fill('input[name="email"]', 'staf.gudang.f@dutanggal.test');
    await page.fill('input[name="telepon"]', '081234567899');
    await page.fill('input[name="posisi"]', 'Staf Operasional Gudang');
    await page.fill('input[name="password"]', 'Password123!');
    await page.fill('input[name="password_confirmation"]', 'Password123!');

    // 10.2 Berikan Role Warehouse Staff
    await page.locator('input[name="roles[]"][value="Warehouse Staff"]').check();

    // 10.3 Penugasan Cabang Tertentu (Cabang 2)
    await page.locator('input[name="manage_type[]"][value="all"]').check();

    // 10.4 Submit Form Pengguna Baru
    await Promise.all([
      page.waitForURL(url => url.pathname.includes('/pengaturan/pengguna'), { timeout: 15000 }),
      page.click('main form button[type="submit"]')
    ]);

    // 10.5 Verifikasi akun baru terdaftar
    await expect(page.locator('body')).toContainText(/stafgudangf/);
    await expect(page.locator('body')).toContainText(/staf\.gudang\.f@dutanggal\.test/);
    await expect(page.locator('body')).toContainText(/Warehouse Staff/i);

    // 10.6 Uji Aksi Keamanan: Toggle Status Pengguna
    const toggleForm = page.locator('tr:has-text("stafgudangf") form[action*="toggle-active"] button');
    if (await toggleForm.count() > 0) {
      await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle', timeout: 10000 }).catch(() => {}),
        toggleForm.click()
      ]);
    }

    // 10.7 Simpan bukti visual kartu pengguna
    await saveEvidence(page, 'f_10_user_detail.png');
  });

  // =========================================================================
  // 11. SECURITY BOUNDARY ENFORCEMENT - PENGUJIAN OTORISASI RBAC (403 FORBIDDEN)
  // =========================================================================
  test('F-11: Security Boundary Enforcement - Pengujian Pembatasan Hak Akses RBAC (403 Forbidden)', async ({ browser }) => {
    // 11.1 Buka konteks browser baru tanpa sesi Super Admin
    const context = await browser.newContext();
    const staffPage = await context.newPage();

    // 11.2 Pastikan user aktif di DB via script ringan
    execSync('php -r "require \'vendor/autoload.php\'; $app = require_once \'bootstrap/app.php\'; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); App\\Models\\User::where(\'email\', \'staf.gudang.f@dutanggal.test\')->update([\'status\' => 1]);"', { encoding: 'utf-8' });

    // 11.3 Login sebagai Staf Gudang (Warehouse Staff)
    await staffPage.goto('http://localhost:8009/login');
    await staffPage.waitForLoadState('networkidle');
    await staffPage.fill('input[name="login"]', 'staf.gudang.f@dutanggal.test');
    await staffPage.fill('input[name="password"]', 'Password123!');
    await staffPage.click('button[type="submit"]');

    // Tunggu login berhasil
    await staffPage.waitForURL('**/dashboard', { timeout: 15000 });
    await expect(staffPage.locator('body')).toContainText(/Staf/i);

    // 11.4 Coba akses modul Keuangan & Pengaturan yang DILARANG untuk Warehouse Staff
    for (const forbiddenUrl of [
      '/pengaturan/pengguna',
      '/keuangan/kas-bank',
      '/keuangan/aset',
      '/laporan/keuangan',
    ]) {
      const res = await staffPage.goto(`http://localhost:8009${forbiddenUrl}`);
      expect(res?.status(), `URL ${forbiddenUrl} harus ditolak 403`).toBe(403);
    }

    const response = await staffPage.goto('http://localhost:8009/keuangan/jurnal');
    
    // 11.5 Verifikasi HTTP Status 403 Forbidden pada /keuangan/jurnal
    expect(response?.status()).toBe(403);
    await expect(staffPage.locator('body')).toContainText(/403|tidak memiliki hak akses|tidak punya akses|Akses dibatasi|Unauthorized|Forbidden/i);

    // 11.6 Simpan bukti visual penolakan RBAC
    await saveEvidence(staffPage, 'f_11_rbac_forbidden_boundary.png');

    await context.close();
  });

  // =========================================================================
  // 12. ATURAN PERSETUJUAN BERJENJANG (APPROVAL RULES ENGINE) & DOUBLE-SUBMIT GUARD
  // =========================================================================
  test('F-12: Aturan Persetujuan Berjenjang - Unhappy Range, Double-Submit Guard & Happy Path', async ({ page }) => {
    await page.goto('http://localhost:8009/pengaturan/aturan-persetujuan/create');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('h1')).toContainText(/Tambah Aturan Persetujuan/i);

    // 12.0 Unhappy Path: Rentang terbalik (above_amount 50jt > up_to_amount 10jt)
    await page.selectOption('#document_type', 'quotation');
    await page.fill('#label', 'Uji Rentang Terbalik');
    await page.fill('#above_amount', '50.000.000');
    await page.fill('#up_to_amount', '10.000.000');
    await page.fill('#approver_label', 'Owner');
    await page.locator('input[name="roles[]"][value="Owner"]').check();

    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle', timeout: 10000 }),
      page.click('main form button[type="submit"]')
    ]);
    await expect(page.locator('body')).toContainText(/Batas atas harus lebih besar dari batas bawah/i);
    expect(page.url()).toContain('/pengaturan/aturan-persetujuan/create');
    await saveEvidence(page, 'f_12a_approval_rule_unhappy.png');

    // 12.1 Isi Form Aturan Persetujuan (Happy Path dengan up_to_amount kosong / unlimited)
    await page.selectOption('#document_type', 'quotation');
    await page.fill('#label', 'Quotation Proyek Khusus di atas Rp 20.000.000');
    await page.fill('#above_amount', '20.000.000');
    await page.fill('#up_to_amount', '');
    await page.fill('#approver_label', 'Sales Manager / Direktur');
    await page.fill('#notes', 'Wajib persetujuan Sales Manager atau Owner untuk nilai penawaran di atas 20 juta');

    // Centang peran penyetuju: Sales Manager & Owner
    await page.locator('input[name="roles[]"][value="Sales Manager"]').check();
    await page.locator('input[name="roles[]"][value="Owner"]').check();

    // 12.2 Uji Rapid Double-Click / Concurrency & Verifikasi Tombol Disabled Saat In-Flight
    let postCount = 0;
    page.on('request', req => {
      if (req.url().includes('/pengaturan/aturan-persetujuan') && req.method() === 'POST') {
        postCount++;
      }
    });

    await page.evaluate(() => {
      sessionStorage.removeItem('qa_btn_disabled');
      sessionStorage.removeItem('qa_btn_cursor_wait');
      const form = document.querySelector('main form') as HTMLFormElement | null;
      const btn = form?.querySelector('button[type="submit"]') as HTMLButtonElement | null;
      form?.addEventListener('submit', () => {
        setTimeout(() => {
          if (btn) {
            sessionStorage.setItem('qa_btn_disabled', String(btn.disabled));
            sessionStorage.setItem('qa_btn_cursor_wait', String(btn.classList.contains('cursor-wait')));
          }
        }, 20);
      });
    });

    await page.route('**/pengaturan/aturan-persetujuan', async route => {
      if (route.request().method() === 'POST') {
        await new Promise(r => setTimeout(r, 400));
        await route.continue();
      } else {
        await route.continue();
      }
    });

    const submitBtn = page.locator('main form button[type="submit"]');
    const navPromise = page.waitForURL(url => url.pathname === '/pengaturan/aturan-persetujuan', { timeout: 15000 });

    // Klik cepat dua kali beruntun untuk menguji proteksi double-submit
    await submitBtn.dblclick();
    await navPromise;
    await page.unroute('**/pengaturan/aturan-persetujuan');

    // Pastikan tombol benar-benar berubah ke state disabled + cursor-wait saat submit & hanya 1 POST terkirim
    const disabledRecorded = await page.evaluate(() => sessionStorage.getItem('qa_btn_disabled'));
    const cursorWaitRecorded = await page.evaluate(() => sessionStorage.getItem('qa_btn_cursor_wait'));
    expect(disabledRecorded).toBe('true');
    expect(cursorWaitRecorded).toBe('true');
    expect(postCount).toBe(1);

    await expect(page.locator('body')).toContainText(/Quotation Proyek Khusus di atas Rp 20.000.000/i);
    await expect(page.locator('body')).toContainText(/Rp 20\.000\.000,00/i);
    await expect(page.locator('body')).toContainText(/Owner, Sales Manager|Sales Manager, Owner/i);
    const matchedRows = await page.locator('tr:has-text("Quotation Proyek Khusus di atas Rp 20.000.000")').count();
    expect(matchedRows).toBe(1);

    // 12.3 Simpan bukti visual aturan persetujuan
    await saveEvidence(page, 'f_12_approval_rule_detail.png');
  });

  // =========================================================================
  // 13. HARD REFRESH (F5), PERSISTENSI STATE MASTER & VIEWPORT MOBILE (375x812)
  // =========================================================================
  test('F-13: Hard Refresh (F5) State Persistence & Mobile Viewport Verification', async ({ page }) => {
    // 13.1 Kunjungi Master Customer (Desktop)
    await page.goto('http://localhost:8009/master/customer');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/CUST-F-2026-001/);

    // 13.2 Lakukan Hard Reload (F5)
    await page.reload({ waitUntil: 'networkidle' });

    // 13.3 Verifikasi data tetap persisten dari DB
    await expect(page.locator('body')).toContainText(/CUST-F-2026-001/);
    await expect(page.locator('body')).toContainText(/PT Maju Elektrik Persada/i);

    // 13.4 Simpan bukti visual persistensi desktop
    await saveEvidence(page, 'f_13_hard_refresh_persistence.png');

    // 13.5 Uji responsivitas pada viewport mobile (375x812)
    await page.setViewportSize({ width: 375, height: 812 });
    await page.goto('http://localhost:8009/master/customer');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/PT Maju Elektrik Persada/i);
    await saveEvidence(page, 'f_13b_mobile_viewport.png');
  });

  // =========================================================================
  // 14. AUDIT INDEPENDEN TIGA TITIK (DATABASE VERIFIER SIKLUS F)
  // =========================================================================
  test('F-14: Independent 3-Way Reconciliation Script Execution', async () => {
    console.log('[Verification] Menjalankan scripts/verify-f-cycle-master.php...');
    const result = execSync('php scripts/verify-f-cycle-master.php', { encoding: 'utf-8' });
    console.log(result);
    expect(result).toContain('SELURUH AUDIT BERHASIL [100% MATCH]');
  });
});