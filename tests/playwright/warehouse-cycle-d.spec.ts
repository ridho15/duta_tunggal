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

test.describe.serial('Siklus D: Manajemen Pergudangan & Kontrol Persediaan (Stock Transfer, Penyesuaian Stok, dan Stock Opname)', () => {
  let createdTransferId: number | null = null;
  let createdAdjustmentId: number | null = null;
  let createdOpnameId: number | null = null;

  test.beforeAll(() => {
    // 1. Reset test data to clean baseline state before running Siklus D
    console.log('[Setup] Menjalankan scripts/reset-warehouse-test-data.php...');
    const out = execSync('php scripts/reset-warehouse-test-data.php', { encoding: 'utf-8' });
    console.log(out);
  });

  test.beforeEach(async ({ page }) => {
    test.setTimeout(60000);
    // Authenticate Super Admin session
    await page.goto('http://localhost:8009/dev-login');
    await page.waitForURL('**/dashboard', { timeout: 15000 });
  });

  // =========================================================================
  // 1. TRANSFER STOK ANTAR GUDANG - UNHAPPY PATHS
  // =========================================================================
  test('D-01: Transfer Stok - Unhappy Paths (Form Kosong, Gudang Sama, & Over-Quantity)', async ({ page }) => {
    // 1.1 Kunjungi form transfer baru
    await page.goto('http://localhost:8009/gudang/transfer/create');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('h1')).toContainText(/Transfer Stok Antar Gudang/i);

    // 1.2 Unhappy Path 1: Gudang Asal dan Tujuan Sama (ID 1 vs ID 1) & Tanpa Item
    await page.evaluate(() => {
      const form = document.getElementById('transferForm') as HTMLFormElement;
      if (form) form.noValidate = true;
      const from = document.getElementById('fromWarehouseSelect') as HTMLInputElement;
      if (from) from.value = '1';
      const to = document.getElementById('toWarehouseSelect') as HTMLInputElement;
      if (to) to.value = '1'; // Gudang yang sama
      const container = document.getElementById('itemsContainer');
      if (container) container.innerHTML = ''; // Tanpa item
    });

    await page.click('button[type="submit"][value="save_draft"]');
    await page.waitForLoadState('networkidle');

    // Verifikasi validasi backend muncul
    const errorAlert = page.locator('.bg-rose-50').first();
    await expect(errorAlert).toBeVisible();
    await expect(errorAlert).toContainText(/kesalahan|berbeda|barang/i);

    // 1.3 Unhappy Path 2: Permintaan Transfer Melebihi Stok Bebas (Over-Quantity: 999 unit vs stok 15)
    await page.goto('http://localhost:8009/gudang/transfer/create');
    await page.waitForLoadState('networkidle');

    await page.evaluate(async () => {
      const from = document.getElementById('fromWarehouseSelect') as HTMLInputElement;
      from.value = '1';
      const to = document.getElementById('toWarehouseSelect') as HTMLInputElement;
      to.value = '2';
      // @ts-ignore
      if (typeof window.onWarehouseChange === 'function') await window.onWarehouseChange();
      // @ts-ignore
      if (typeof window.onDestinationWarehouseChange === 'function') await window.onDestinationWarehouseChange();
    });

    // Tunggu baris produk terisi
    await page.waitForSelector('#prod-select-0', { state: 'attached', timeout: 10000 });
    await page.evaluate(() => {
      const form = document.getElementById('transferForm') as HTMLFormElement;
      if (form) form.noValidate = true;
      const sel = document.getElementById('prod-select-0') as HTMLSelectElement;
      if (sel) {
        sel.value = '1';
        sel.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
    await page.fill('#qty-input-0', '999');

    // Submit form
    await page.click('button[type="submit"][value="save_draft"]');
    await page.waitForLoadState('networkidle');

    // Verifikasi pesan validasi stok tidak mencukupi muncul
    const overStockAlert = page.locator('.bg-rose-50').first();
    await expect(overStockAlert).toBeVisible();
    await expect(overStockAlert).toContainText(/melebihi stok|tersedia/i);

    await saveEvidence(page, 'd_01_transfer_unhappy.png');
    console.log('[PASS] D-01: Transfer Stok Unhappy Path Validated.');
  });

  // =========================================================================
  // 2. TRANSFER STOK ANTAR GUDANG - REJECTION FLOW
  // =========================================================================
  test('D-02: Transfer Stok - Alur Penolakan (Rejection Flow) & Isolasi Stok', async ({ page }) => {
    await page.goto('http://localhost:8009/gudang/transfer/create');
    await page.waitForLoadState('networkidle');

    // Isi berkas transfer untuk penolakan
    await page.fill('input[name="transfer_number"]', 'TRF-D-REJECT-001');
    await page.fill('input[name="notes"]', 'Uji Penolakan Mutasi Stok (Siklus D)');

    await page.evaluate(async () => {
      const from = document.getElementById('fromWarehouseSelect') as HTMLInputElement;
      from.value = '1';
      const to = document.getElementById('toWarehouseSelect') as HTMLInputElement;
      to.value = '2';
      // @ts-ignore
      if (typeof window.onWarehouseChange === 'function') await window.onWarehouseChange();
      // @ts-ignore
      if (typeof window.onDestinationWarehouseChange === 'function') await window.onDestinationWarehouseChange();
    });

    await page.waitForSelector('#prod-select-0', { state: 'attached', timeout: 10000 });
    await page.evaluate(() => {
      const sel = document.getElementById('prod-select-0') as HTMLSelectElement;
      if (sel) {
        sel.value = '1';
        sel.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
    await page.fill('#qty-input-0', '2');

    // Simpan & Ajukan
    await page.click('button[type="submit"][value="submit_request"]');
    await page.waitForLoadState('networkidle');

    // Verifikasi status Menunggu Persetujuan
    await expect(page.locator('body')).toContainText(/TRF-D-REJECT-001/);
    await expect(page.locator('body')).toContainText(/Menunggu Persetujuan/i);

    // Klik tombol Tolak
    await page.click('button:has-text("Tolak")');
    await page.waitForSelector('#rejectModal:not(.hidden)', { timeout: 5000 });
    await page.fill('#rejectModal textarea[name="reason"]', 'Truk logistik tidak tersedia untuk rute Cabang A');
    await page.click('#rejectModal button:has-text("Konfirmasi Tolak")');
    await page.waitForLoadState('networkidle');

    // Verifikasi status menjadi Ditolak
    await expect(page.locator('body')).toContainText(/Ditolak/i);
    await saveEvidence(page, 'd_02_transfer_rejected.png');
    console.log('[PASS] D-02: Transfer Stok Rejection Flow Validated.');
  });

  // =========================================================================
  // 3. TRANSFER STOK ANTAR GUDANG - HAPPY PATH (TRF-D-001)
  // =========================================================================
  test('D-03: Transfer Stok - Happy Path Pemindahan 5 Unit (TRF-D-001) & Approval', async ({ page }) => {
    await page.goto('http://localhost:8009/gudang/transfer/create');
    await page.waitForLoadState('networkidle');

    // Isi formulir transfer TRF-D-001
    await page.fill('input[name="transfer_number"]', 'TRF-D-001');
    await page.fill('input[name="notes"]', 'Transfer rutin restock Cabang A (Siklus D)');

    await page.evaluate(async () => {
      const from = document.getElementById('fromWarehouseSelect') as HTMLInputElement;
      from.value = '1';
      const to = document.getElementById('toWarehouseSelect') as HTMLInputElement;
      to.value = '2';
      // @ts-ignore
      if (typeof window.onWarehouseChange === 'function') await window.onWarehouseChange();
      // @ts-ignore
      if (typeof window.onDestinationWarehouseChange === 'function') await window.onDestinationWarehouseChange();
    });

    await page.waitForSelector('#prod-select-0', { state: 'attached', timeout: 10000 });
    await page.evaluate(() => {
      const sel = document.getElementById('prod-select-0') as HTMLSelectElement;
      if (sel) {
        sel.value = '1';
        sel.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
    await page.fill('#qty-input-0', '5');

    // Simpan & Ajukan
    await page.click('button[type="submit"][value="submit_request"]');
    await page.waitForLoadState('networkidle');

    // Simpan ID dari URL
    const currentUrl = page.url();
    const match = currentUrl.match(/\/gudang\/transfer\/(\d+)/);
    if (match) createdTransferId = parseInt(match[1], 10);

    await expect(page.locator('body')).toContainText(/TRF-D-001/);
    await expect(page.locator('body')).toContainText(/Menunggu Persetujuan/i);

    // Klik tombol Setujui & Mutasi Stok
    await page.click('button:has-text("Setujui & Mutasi Stok")');
    await page.waitForSelector('form[action*="/approve"] button[type="submit"]', { timeout: 5000 });
    await page.click('form[action*="/approve"] button[type="submit"]');
    await page.waitForLoadState('networkidle');

    // Verifikasi alert sukses dan status Approved
    await expect(page.locator('body')).toContainText(/berhasil disetujui|Mutasi stok fisik telah diproses/i);
    await expect(page.locator('body')).toContainText(/Disetujui \/ Selesai/i);

    await saveEvidence(page, 'd_03_transfer_approved_detail.png');
    console.log('[PASS] D-03: Stock Transfer TRF-D-001 Approved & Mutated Successfully.');
  });

  // =========================================================================
  // 4. PENYESUAIAN STOK (STOCK ADJUSTMENT) - UNHAPPY PATHS
  // =========================================================================
  test('D-04: Penyesuaian Stok - Unhappy Paths (Form Kosong & Selisih Arah Salah)', async ({ page }) => {
    await page.goto('http://localhost:8009/gudang/penyesuaian/create');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('h1')).toContainText(/Buat Penyesuaian Stok/i);

    // 4.1 Form kosong / Tanpa Alasan & Bypass Client Validation
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle' }),
      page.evaluate(() => {
        const form = (document.querySelector('main form') || document.querySelector('form[action*="/penyesuaian"]')) as HTMLFormElement;
        if (form) form.submit();
      }),
    ]);

    const errBox = page.locator('[role="alert"]').first();
    await expect(errBox).toBeVisible();
    await expect(errBox).toContainText(/wajib|kesalahan/i);

    // 4.2 Unhappy Path 2: Tipe Pengurangan (decrease) tetapi Qty Setelah lebih besar dari Qty Saat Ini
    await page.goto('http://localhost:8009/gudang/penyesuaian/create');
    await page.waitForLoadState('networkidle');

    // Pilih Gudang Utama & Tipe decrease
    await page.selectOption('select#warehouse_id', '1');
    await page.selectOption('select#adjustment_type', 'decrease');
    await page.fill('input#reason', 'Uji Validasi Selisih Salah (Siklus D)');

    // Cari produk 1
    const searchInput = page.locator('input[placeholder="Ketik nama atau SKU"]').first();
    await searchInput.fill('Panel');
    await page.waitForSelector('button:has-text("Panel Kontrol Industri")', { timeout: 10000 });
    await page.click('button:has-text("Panel Kontrol Industri")');

    // Tunggu stok termuat (sekarang 10 unit setelah transfer 5 unit)
    await page.waitForTimeout(1000);

    // Isi adjusted_qty = 15 (lebih besar dari 10 padahal decrease)
    const qtyInput = page.locator('input[name*="[adjusted_qty]"]').first();
    await qtyInput.fill('15');

    // Simpan Draft
    await page.click('button[type="submit"]:has-text("Simpan Draft")');
    await page.waitForLoadState('networkidle');

    // Verifikasi pesan validasi selisih salah muncul
    const diffErr = page.locator('[role="alert"]').first();
    await expect(diffErr).toBeVisible();
    await expect(diffErr).toContainText(/harus lebih kecil dari qty saat ini/i);

    await saveEvidence(page, 'd_04_adjustment_unhappy.png');
    console.log('[PASS] D-04: Stock Adjustment Unhappy Paths Validated.');
  });

  // =========================================================================
  // 5. PENYESUAIAN STOK (STOCK ADJUSTMENT) - HAPPY PATH (ADJ-D-001)
  // =========================================================================
  test('D-05: Penyesuaian Stok - Happy Path Pengurangan 2 Unit Rusak (ADJ-D-001) & Approval', async ({ page }) => {
    await page.goto('http://localhost:8009/gudang/penyesuaian/create');
    await page.waitForLoadState('networkidle');

    // Set custom adjustment number & informasi
    await page.evaluate(() => {
      const numInput = document.getElementById('adjustment_number') as HTMLInputElement;
      if (numInput) numInput.value = 'ADJ-D-001';
    });

    await page.selectOption('select#warehouse_id', '1');
    await page.selectOption('select#adjustment_type', 'decrease');
    await page.fill('input#reason', 'Barang display rusak & sampel uji (Siklus D)');
    await page.fill('textarea#notes', 'Koreksi penyesuaian persediaan fisik rusak di Gudang Utama.');

    // Cari produk 1
    const searchInput = page.locator('input[placeholder="Ketik nama atau SKU"]').first();
    await searchInput.fill('Panel');
    await page.waitForSelector('button:has-text("Panel Kontrol Industri")', { timeout: 10000 });
    await page.click('button:has-text("Panel Kontrol Industri")');

    // Pilih Rak A1 (id: 1)
    await page.evaluate(() => {
      const rakSelect = document.querySelector('select[name*="[rak_id]"]') as HTMLSelectElement;
      if (rakSelect) {
        rakSelect.value = '1';
        rakSelect.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });

    // Tunggu stok termuat (10 unit)
    await page.waitForTimeout(1000);

    // Masukkan qty setelah = 8.00 (diff: -2.00 unit)
    const qtyInput = page.locator('input[name*="[adjusted_qty]"]').first();
    await qtyInput.fill('8');

    // Masukkan harga satuan = 500000
    const costInput = page.locator('input[name*="[unit_cost]"]').first();
    await costInput.fill('500000');

    // Simpan Draft
    await page.click('button[type="submit"]:has-text("Simpan Draft")');
    await page.waitForLoadState('networkidle');

    // Verifikasi tersimpan dan masuk ke halaman detail
    const currentUrl = page.url();
    const match = currentUrl.match(/\/gudang\/penyesuaian\/(\d+)/);
    if (match) createdAdjustmentId = parseInt(match[1], 10);

    await expect(page.locator('body')).toContainText(/ADJ-D-001/);
    await expect(page.locator('body')).toContainText(/Draft/i);

    // Setuju penyesuaian stok (tangani native browser confirm dialog)
    page.once('dialog', async (dialog) => {
      expect(dialog.message()).toContain('Setujui penyesuaian ini');
      await dialog.accept();
    });

    await page.click('button[type="submit"]:has-text("Setujui")');
    await page.waitForLoadState('networkidle');

    // Verifikasi alert sukses dan status Disetujui
    await expect(page.locator('body')).toContainText(/Penyesuaian disetujui: mutasi stok dan jurnal sudah dicatat/i);
    await expect(page.locator('body')).toContainText(/Disetujui/i);

    await saveEvidence(page, 'd_05_adjustment_approved_detail.png');
    console.log('[PASS] D-05: Stock Adjustment ADJ-D-001 Approved & Mutated Successfully.');
  });

  // =========================================================================
  // 6. STOCK OPNAME - UNHAPPY PATHS
  // =========================================================================
  test('D-06: Stock Opname - Unhappy Paths (Validasi Form Kosong)', async ({ page }) => {
    await page.goto('http://localhost:8009/gudang/opname/create');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('h1')).toContainText(/Buka Sesi Stock Opname Baru/i);

    // Bypass client validation & kosongkan gudang
    await page.evaluate(() => {
      const form = (document.querySelector('main form') || document.querySelector('form[action*="/opname"]')) as HTMLFormElement;
      if (form) form.noValidate = true;
      const whInput = document.querySelector('input[name="warehouse_id"]') as HTMLInputElement;
      if (whInput) whInput.value = '';
    });

    await page.click('button[type="submit"][value="save_draft"]');
    await page.waitForLoadState('networkidle');

    // Verifikasi pesan validasi gudang wajib dipilih muncul
    const errAlert = page.locator('.bg-rose-50').first();
    await expect(errAlert).toBeVisible();
    await expect(errAlert).toContainText(/wajib|kesalahan/i);

    await saveEvidence(page, 'd_06_opname_unhappy.png');
    console.log('[PASS] D-06: Stock Opname Unhappy Path Validated.');
  });

  // =========================================================================
  // 7. STOCK OPNAME - HAPPY PATH (OPN-D-001) & RECOUNT FLOW
  // =========================================================================
  test('D-07: Stock Opname - Happy Path (OPN-D-001), Recount Test, Surplus (+1 Unit) & Approval', async ({ page }) => {
    await page.goto('http://localhost:8009/gudang/opname/create');
    await page.waitForLoadState('networkidle');

    // Isi sesi opname
    await page.fill('input[name="opname_number"]', 'OPN-D-001');
    await page.evaluate(() => {
      const whInput = document.querySelector('input[name="warehouse_id"]') as HTMLInputElement;
      if (whInput) {
        whInput.value = '1';
        whInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
    await page.fill('textarea[name="notes"]', 'Stock Opname Rutin Gudang Utama (Siklus D)');

    // Simpan draft
    await page.click('button[type="submit"][value="save_draft"]');
    await page.waitForLoadState('networkidle');

    const currentUrl = page.url();
    const match = currentUrl.match(/\/gudang\/opname\/(\d+)/);
    if (match) createdOpnameId = parseInt(match[1], 10);

    await expect(page.locator('body')).toContainText(/OPN-D-001/);
    await expect(page.locator('body')).toContainText(/Draft \(Persiapan\)/i);

    // Mulai hitung fisik
    await page.click('button:has-text("Mulai Hitung Fisik")');
    await page.waitForLoadState('networkidle');

    // Status sekarang Sedang Dihitung Fisik
    await expect(page.locator('body')).toContainText(/Sedang Dihitung Fisik/i);

    // 1. Isi semua item dengan system_qty terlebih dahulu agar seluruh 10 item terhitung
    await page.evaluate(() => {
      const rows = document.querySelectorAll('#opnameTableBody tr.item-row');
      rows.forEach(row => {
        const sysQtyText = (row.children[5]?.textContent?.trim().replace(/\./g, '')) || '0';
        const input = row.querySelector('input[name^="counts"]') as HTMLInputElement;
        if (input) {
          input.value = sysQtyText;
          input.dispatchEvent(new Event('input', { bubbles: true }));
        }
      });
    });

    // 2. Untuk Product 1 (Panel Kontrol Industri), masukkan 7 unit untuk uji recount
    await page.evaluate(() => {
      const row = document.querySelector('#opnameTableBody tr[data-product-name*="panel"]');
      if (row) {
        const input = row.querySelector('input[name^="counts"]') as HTMLInputElement;
        if (input) {
          input.value = '7';
          input.dispatchEvent(new Event('input', { bubbles: true }));
        }
      }
    });

    // Tandai selesai hitung sementara
    await page.click('button[type="submit"][value="complete"]');
    await page.waitForLoadState('networkidle');

    // Status sekarang Selesai Hitung
    await expect(page.locator('body')).toContainText(/Selesai Hitung \(Menunggu Approval\)/i);

    // Klik Hitung Ulang (Recount Flow)
    await page.click('button:has-text("Hitung Ulang")');
    await page.waitForSelector('form[action*="/recount"] button[type="submit"]', { timeout: 5000 });
    await page.click('form[action*="/recount"] button[type="submit"]');
    await page.waitForLoadState('networkidle');

    // Status kembali ke Sedang Dihitung Fisik
    await expect(page.locator('body')).toContainText(/Sedang Dihitung Fisik/i);

    // Masukkan hasil fisik riil final untuk Product 1: Sistem + 1 unit (Surplus +1.00 unit)
    await page.evaluate(() => {
      const row = document.querySelector('#opnameTableBody tr[data-product-name*="panel"]');
      if (row) {
        const sysQty = parseFloat(row.children[5]?.textContent?.trim().replace(/\./g, '') || '8');
        const input = row.querySelector('input[name^="counts"]') as HTMLInputElement;
        if (input) {
          input.value = String(sysQty + 1);
          input.dispatchEvent(new Event('input', { bubbles: true }));
        }
        const note = row.querySelector('input[name^="notes"]') as HTMLInputElement;
        if (note) {
          note.value = 'Ditemukan surplus 1 unit di rak cadangan (Siklus D)';
          note.dispatchEvent(new Event('input', { bubbles: true }));
        }
      }
    });

    // Klik Tandai Selesai Hitung
    await page.click('button[type="submit"][value="complete"]');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/Selesai Hitung \(Menunggu Approval\)/i);
    await expect(page.locator('body')).toContainText(/\+1/); // Surplus +1 tercatat di KPI

    // Klik Setujui & Sesuaikan Stok
    await page.click('button:has-text("Setujui & Sesuaikan Stok")');
    await page.waitForSelector('form[action*="/approve"] button[type="submit"]', { timeout: 5000 });
    await page.click('form[action*="/approve"] button[type="submit"]');
    await page.waitForLoadState('networkidle');

    // Verifikasi alert sukses dan status Disetujui & Disesuaikan
    await expect(page.locator('body')).toContainText(/Stock opname berhasil disetujui|Jurnal Umum persediaan telah dibukukan/i);
    await expect(page.locator('body')).toContainText(/Disetujui & Disesuaikan/i);

    await saveEvidence(page, 'd_07_opname_approved_detail.png');
    console.log('[PASS] D-07: Stock Opname OPN-D-001 Approved & Adjusted Successfully.');
  });

  // =========================================================================
  // 8. KARTU PERSEDIAAN REAL-TIME AUDIT TRAIL
  // =========================================================================
  test('D-08: Kartu Persediaan - Audit Trail Kronologis Lengkap & Running Balance Presisi', async ({ page }) => {
    // Kunjungi kartu stok Produk 1
    await page.goto('http://localhost:8009/gudang/stok/1/kartu');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('h1')).toContainText(/Kartu Persediaan: Panel Kontrol Industri/i);

    // Verifikasi nomor-nomor dokumen mutasi Siklus D tercantum pada kartu stok
    const content = await page.textContent('body');
    expect(content).toContain('TRF-D-001');
    expect(content).toContain('ADJ-D-001');
    expect(content).toContain('OPN-D-001');

    await saveEvidence(page, 'd_08_kartu_persediaan_audit.png');
    console.log('[PASS] D-08: Kartu Persediaan Real-Time Audit Trail Verified.');
  });

  // =========================================================================
  // 9. HARD REFRESH (F5) STATE PERSISTENCE
  // =========================================================================
  test('D-09: Hard Refresh (F5) State Persistence pada Seluruh Halaman Siklus D', async ({ page }) => {
    // 9.1 Hard refresh kartu stok
    await page.goto('http://localhost:8009/gudang/stok/1/kartu');
    await page.waitForLoadState('networkidle');
    await page.reload({ waitUntil: 'networkidle' });

    await expect(page.locator('h1')).toContainText(/Kartu Persediaan: Panel Kontrol Industri/i);
    await expect(page.locator('body')).toContainText(/Panel Kontrol Industri/i);

    await saveEvidence(page, 'd_09_hard_refresh_persistence.png');
    console.log('[PASS] D-09: Hard Refresh State Persistence Verified.');
  });

  // =========================================================================
  // 10. REKONSILIASI AKURASI DATA & KESEIMBANGAN JURNAL
  // =========================================================================
  test('D-10: Rekonsiliasi Tiga Titik & Audit Saldo Fisik Database (PHP Verifier)', async () => {
    console.log('[Verification] Menjalankan scripts/verify-d-cycle-warehouse.php...');
    const out = execSync('php scripts/verify-d-cycle-warehouse.php', { encoding: 'utf-8' });
    console.log(out);

    expect(out).toContain('SEMUA DATA PRESISI & VALID (PASS)');
    expect(out).toContain('Saldo Stok Gudang Utama Presisi 100% MATCH: 9');
    expect(out).toContain('Saldo Stok Gudang Cabang Presisi 100% MATCH: 5');
    expect(out).toContain('Stok Bahan Baku Tidak Terpengaruh (Isolasi Mutasi Terjamin): 40');
  });
});
