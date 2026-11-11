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

test.describe.serial('Siklus C: Manufaktur & Perakitan Barang (BOM, SPK, Material Issue, Production, QC Manufaktur)', () => {
  let createdBomId: number | null = null;
  let createdSpkId: number | null = null;
  let createdMaterialIssueId: number | null = null;
  let createdProductionId: number | null = null;
  let createdQcId: number | null = null;

  test.beforeAll(() => {
    // 1. Reset test data to clean baseline state before running Siklus C
    console.log('[Setup] Menjalankan scripts/reset-manufaktur-test-data.php...');
    const out = execSync('php scripts/reset-manufaktur-test-data.php', { encoding: 'utf-8' });
    console.log(out);
  });

  test.beforeEach(async ({ page }) => {
    test.setTimeout(60000);
    // Authenticate Super Admin session
    await page.goto('http://127.0.0.1:8009/dev-login');
    await page.waitForURL('**/dashboard', { timeout: 15000 });
  });

  // =========================================================================
  // 1. RESEP PRODUKSI (BILL OF MATERIALS / BOM) - UNHAPPY & HAPPY PATH
  // =========================================================================
  test('C-01: Resep Produksi (BOM) - Unhappy Path Form Kosong & Happy Path Buat BOM-C-001', async ({ page }) => {
    // 1.1 Kunjungi daftar BOM
    await page.goto('http://127.0.0.1:8009/manufaktur/bom');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('h1')).toContainText(/Resep Produksi|Bill of Material/i);

    // 1.2 Unhappy Path: Validasi Form Kosong / Malformed Input ke Backend
    await page.goto('http://127.0.0.1:8009/manufaktur/bom/create');
    await page.waitForLoadState('networkidle');

    // Kosongkan nama BOM, hapus baris bahan baku, dan bypass HTML5 client-side tooltip
    await page.evaluate(() => {
      const form = document.getElementById('bomForm') as HTMLFormElement;
      if (form) form.noValidate = true;
      const codeInput = document.getElementById('bomCode') as HTMLInputElement;
      if (codeInput) codeInput.value = '';
      const namaInput = document.querySelector('input[name="nama_bom"]') as HTMLInputElement;
      if (namaInput) namaInput.value = '';
      const prodSelect = document.getElementById('finishedProductSelect') as HTMLInputElement;
      if (prodSelect) prodSelect.value = '';
      const materialsContainer = document.getElementById('materialsContainer');
      if (materialsContainer) materialsContainer.innerHTML = '';
    });

    // Klik tombol submit Simpan Resep BOM
    await page.click('button[type="submit"][form="bomForm"]');
    await page.waitForLoadState('networkidle');

    // Verifikasi pesan validasi error bahasa Indonesia muncul tanpa HTTP 500
    const errorAlert = page.locator('.bg-rose-50').first();
    await expect(errorAlert).toBeVisible();
    await expect(errorAlert).toContainText(/kesalahan pengisian|wajib|harus diisi/i);
    await saveEvidence(page, 'c_01_bom_unhappy_empty.png');

    // 1.3 Happy Path: Buat BOM-C-001 Resmi
    await page.goto('http://127.0.0.1:8009/manufaktur/bom/create');
    await page.waitForLoadState('networkidle');

    // Isi Kode BOM & Nama
    await page.fill('#bomCode', 'BOM-C-001');
    await page.fill('input[name="nama_bom"]', 'Panel Kontrol Industri Standard (Siklus C)');

    // Set Produk Jadi: ID 1 (Panel Kontrol Industri), Qty 1.00
    await page.evaluate(() => {
      const prodInput = document.getElementById('finishedProductSelect') as HTMLInputElement;
      if (prodInput) {
        prodInput.value = '1';
        prodInput.dispatchEvent(new Event('input', { bubbles: true }));
        prodInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
      const qtyInput = document.getElementById('finishedQuantity') as HTMLInputElement;
      if (qtyInput) {
        qtyInput.value = '1.00';
        qtyInput.dispatchEvent(new Event('input', { bubbles: true }));
      }
      const cabangInput = document.querySelector('input[name="cabang_id"]') as HTMLInputElement;
      if (cabangInput) {
        cabangInput.value = '1';
        cabangInput.dispatchEvent(new Event('input', { bubbles: true }));
        cabangInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
      const uomSelect = document.getElementById('finishedUomSelect') as HTMLSelectElement;
      if (uomSelect) {
        uomSelect.value = '1';
        uomSelect.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });

    // Isi Bahan Baku pada baris 0: Product ID 3 (Bahan Baku Plastik Granul), Qty 2.00, Harga 180.000
    await page.evaluate(() => {
      const matSelect = document.querySelector('select[name="items[0][product_id]"]') as HTMLSelectElement;
      if (matSelect) {
        matSelect.value = '3';
        matSelect.dispatchEvent(new Event('change', { bubbles: true }));
      }
      const qtyMat = document.getElementById('itemQty_0') as HTMLInputElement;
      if (qtyMat) {
        qtyMat.value = '2.00';
        qtyMat.dispatchEvent(new Event('input', { bubbles: true }));
      }
      const uomMat = document.getElementById('itemUom_0') as HTMLSelectElement;
      if (uomMat) {
        uomMat.value = '1';
        uomMat.dispatchEvent(new Event('change', { bubbles: true }));
      }
      const priceMat = document.getElementById('itemPrice_0') as HTMLInputElement;
      if (priceMat) {
        priceMat.value = '180000';
        priceMat.dispatchEvent(new Event('input', { bubbles: true }));
      }
      // Set Labor Cost Rp 50.000 & Overhead Cost Rp 25.000
      const labor = document.getElementById('laborCostInput') as HTMLInputElement;
      if (labor) {
        labor.value = '50000';
        labor.dispatchEvent(new Event('input', { bubbles: true }));
      }
      const overhead = document.getElementById('overheadCostInput') as HTMLInputElement;
      if (overhead) {
        overhead.value = '25000';
        overhead.dispatchEvent(new Event('input', { bubbles: true }));
      }
    });

    // Trigger recalculation in UI
    await page.evaluate(() => {
      if (typeof (window as any).recalculateTotals === 'function') {
        (window as any).recalculateTotals();
      }
    });

    await page.waitForTimeout(300);

    // Verifikasi total summary di DOM sebelum submit: 2*180k (360k) + 50k + 25k = Rp 435.000
    const grandTotalText = await page.locator('#summaryGrandTotal').innerText();
    expect(grandTotalText).toContain('435.000');

    // Submit BOM Form
    await page.click('button[type="submit"][form="bomForm"]');
    await page.waitForLoadState('networkidle');

    // Verifikasi alert sukses di halaman detail show
    const successMsg = page.locator('div[role="alert"]');
    await expect(successMsg).toBeVisible();
    await expect(successMsg).toContainText(/berhasil disimpan|berhasil dibuat/i);

    // Ambil BOM ID dari database
    const bomIdOut = execSync('php -r "require \'vendor/autoload.php\'; $a = require_once \'bootstrap/app.php\'; $a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); echo App\\Models\\BillOfMaterial::where(\'code\', \'BOM-C-001\')->value(\'id\');"', { encoding: 'utf-8' });
    createdBomId = parseInt(bomIdOut.trim(), 10);
    console.log(`[PASS] Created BOM ID: ${createdBomId}`);

    // Verifikasi tampilan detail BOM
    await expect(page.locator('h1')).toContainText('Panel Kontrol Industri Standard (Siklus C)');
    await expect(page.locator('p').filter({ hasText: 'BOM-C-001' }).first()).toBeVisible();
    await saveEvidence(page, 'c_02_bom_created_detail.png');
  });

  // =========================================================================
  // 2. SURAT PERINTAH KERJA (SPK / MO) - UNHAPPY & HAPPY PATH
  // =========================================================================
  test('C-02: Surat Perintah Kerja (SPK) - Unhappy Path Stok 0 & Happy Path Terbitkan SPK 5 Unit', async ({ page }) => {
    // 2.1 Unhappy Path: Validasi Stok Bahan Baku Tidak Cukup saat Mulai SPK
    // Buat draft SPK dummy dengan kuantitas ekstrem 999.999 unit (melebihi stok 50)
    await page.goto('http://127.0.0.1:8009/manufaktur/spk/create');
    await page.waitForLoadState('networkidle');

    await page.fill('#moNumberInput', 'MO-TEST-NO-STOCK');
    await page.evaluate(({ bomId }) => {
      const bomInput = document.getElementById('bomSelect') as HTMLInputElement;
      if (bomInput) {
        bomInput.value = String(bomId);
        bomInput.dispatchEvent(new Event('input', { bubbles: true }));
        bomInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
      const qtyInput = document.getElementById('targetQuantityInput') as HTMLInputElement;
      if (qtyInput) {
        qtyInput.value = '999999';
        qtyInput.dispatchEvent(new Event('input', { bubbles: true }));
      }
      const whInput = document.getElementById('warehouseSelect') as HTMLInputElement;
      if (whInput) {
        whInput.value = '1';
        whInput.dispatchEvent(new Event('input', { bubbles: true }));
        whInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
      const cabInput = document.getElementById('cabangSelect') as HTMLInputElement;
      if (cabInput) {
        cabInput.value = '1';
        cabInput.dispatchEvent(new Event('input', { bubbles: true }));
        cabInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
    }, { bomId: createdBomId });

    await page.click('button[type="submit"][form="spkForm"]');
    await page.waitForLoadState('networkidle');

    // Halaman detail SPK dummy
    const dummyMoUrl = page.url();
    console.log(`[Unhappy Path] Testing insufficient stock start on dummy SPK: ${dummyMoUrl}`);

    // Trigger start SPK via modal form
    await page.evaluate(() => {
      const form = document.querySelector('form[action*="/start"]') as HTMLFormElement;
      if (form) form.submit();
    });
    await page.waitForLoadState('networkidle');

    // Verifikasi pesan penolakan dari ManufacturingService: "Stok bahan baku di gudang tidak mencukupi"
    const noStockAlert = page.locator('div[role="alert"], .bg-rose-50, .text-rose-800, .bg-red-50').first();
    await expect(noStockAlert).toBeVisible();
    await expect(noStockAlert).toContainText(/tidak mencukupi/i);
    await saveEvidence(page, 'c_03_spk_unhappy_no_stock.png');

    // Hapus draft SPK dummy agar database bersih
    execSync('php -r "require \'vendor/autoload.php\'; $a = require_once \'bootstrap/app.php\'; $a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); App\\Models\\ManufacturingOrder::withoutGlobalScopes()->where(\'mo_number\', \'MO-TEST-NO-STOCK\')->forceDelete();"');

    // 2.2 Happy Path: Terbitkan SPK Resmi MO-C-001 untuk 5 Unit Finished Goods
    await page.goto('http://127.0.0.1:8009/manufaktur/spk/create');
    await page.waitForLoadState('networkidle');

    await page.fill('#moNumberInput', 'MO-C-001');
    await page.evaluate(({ bomId }) => {
      const bomInput = document.getElementById('bomSelect') as HTMLInputElement;
      if (bomInput) {
        bomInput.value = String(bomId);
        bomInput.dispatchEvent(new Event('input', { bubbles: true }));
        bomInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
      const qtyInput = document.getElementById('targetQuantityInput') as HTMLInputElement;
      if (qtyInput) {
        qtyInput.value = '5.00';
        qtyInput.dispatchEvent(new Event('input', { bubbles: true }));
      }
      const whInput = document.getElementById('warehouseSelect') as HTMLInputElement;
      if (whInput) {
        whInput.value = '1';
        whInput.dispatchEvent(new Event('input', { bubbles: true }));
        whInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
      const cabInput = document.getElementById('cabangSelect') as HTMLInputElement;
      if (cabInput) {
        cabInput.value = '1';
        cabInput.dispatchEvent(new Event('input', { bubbles: true }));
        cabInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
    }, { bomId: createdBomId });

    await page.click('button[type="submit"][form="spkForm"]');
    await page.waitForLoadState('networkidle');

    // Ambil ID SPK yang baru dibuat
    const spkIdOut = execSync('php -r "require \'vendor/autoload.php\'; $a = require_once \'bootstrap/app.php\'; $a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); echo App\\Models\\ManufacturingOrder::where(\'mo_number\', \'MO-C-001\')->value(\'id\');"', { encoding: 'utf-8' });
    createdSpkId = parseInt(spkIdOut.trim(), 10);
    console.log(`[PASS] Created SPK ID: ${createdSpkId}`);

    // Di halaman detail SPK (status Draft), periksa ketersediaan bahan baku di tabel
    await expect(page.locator('h1')).toContainText('MO-C-001');
    await expect(page.locator('text=Draft (Menunggu Start)')).toBeVisible();

    // Klik Mulai Produksi (Stok mencukupi: butuh 10, ada 50)
    await page.evaluate(() => {
      const form = document.querySelector('form[action*="/start"]') as HTMLFormElement;
      if (form) form.submit();
    });
    await page.waitForLoadState('networkidle');

    // Verifikasi status SPK telah berpindah ke In Progress
    const inProgressBadge = page.locator('text=Sedang Dikerjakan (In Progress)');
    await expect(inProgressBadge).toBeVisible();
    await saveEvidence(page, 'c_04_spk_in_progress.png');
  });

  // =========================================================================
  // 3. PENGELUARAN BAHAN BAKU (MATERIAL ISSUE) - UNHAPPY & HAPPY PATH
  // =========================================================================
  test('C-03: Pengeluaran Bahan Baku - Unhappy Path & Happy Path Selesaikan Pengeluaran 10 Unit', async ({ page }) => {
    // 3.1 Unhappy Path: Validasi Input Kosong
    await page.goto('http://127.0.0.1:8009/manufaktur/material-issue/create');
    await page.waitForLoadState('networkidle');

    // Kosongkan gudang dan submit draft
    await page.evaluate(() => {
      const form = document.getElementById('materialIssueForm') as HTMLFormElement;
      if (form) form.noValidate = true;
      const whInput = document.getElementById('warehouse_id') as HTMLInputElement;
      if (whInput) whInput.value = '';
      const tbody = document.getElementById('itemsTableBody');
      if (tbody) tbody.innerHTML = '';
    });

    await page.click('button[value="save_draft"]');
    await page.waitForLoadState('networkidle');

    const errAlert = page.locator('.bg-rose-50, .text-rose-800').first();
    await expect(errAlert).toBeVisible();
    await expect(errAlert).toContainText(/kesalahan|wajib|harus diisi/i);
    await saveEvidence(page, 'c_05_material_issue_unhappy.png');

    // 3.2 Happy Path: Tarik Bahan dari SPK MO-C-001 dan Buat MI-C-001
    await page.goto(`http://127.0.0.1:8009/manufaktur/material-issue/create?spk_id=${createdSpkId}`);
    await page.waitForLoadState('networkidle');

    await page.fill('#issue_number', 'MI-C-001');

    // Pastikan Gudang ID 1 & SPK terpilih
    await page.evaluate(({ spkId }) => {
      const whInput = document.getElementById('warehouse_id') as HTMLInputElement;
      if (whInput) {
        whInput.value = '1';
        whInput.dispatchEvent(new Event('input', { bubbles: true }));
        whInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
      const spkInput = document.getElementById('manufacturing_order_id') as HTMLInputElement;
      if (spkInput) {
        spkInput.value = String(spkId);
        spkInput.dispatchEvent(new Event('input', { bubbles: true }));
        spkInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
    }, { spkId: createdSpkId });

    // Klik tombol Tarik Kebutuhan Bahan SPK
    await page.click('#btnFetchSpkMaterials');
    await page.waitForTimeout(500);

    // Jika tabel belum terisi otomatis oleh JS fetch, tambahkan baris manual secara aman
    const rowCount = await page.locator('#itemsTableBody tr').count();
    if (rowCount === 0) {
      await page.click('#btnAddItemRow');
      await page.waitForTimeout(200);
      await page.evaluate(() => {
        const prodSel = document.querySelector('select[name="items[0][product_id]"]') as HTMLSelectElement;
        if (prodSel) {
          prodSel.value = '3';
          prodSel.dispatchEvent(new Event('change', { bubbles: true }));
        }
        const qtyInp = document.querySelector('input[name="items[0][quantity]"]') as HTMLInputElement;
        if (qtyInp) {
          qtyInp.value = '10.00';
          qtyInp.dispatchEvent(new Event('input', { bubbles: true }));
        }
        const costInp = document.querySelector('input[name="items[0][cost_per_unit]"]') as HTMLInputElement;
        if (costInp) {
          costInp.value = '180000';
          costInp.dispatchEvent(new Event('input', { bubbles: true }));
        }
      });
    }

    // Submit sebagai Draft
    await page.click('button[value="save_draft"]');
    await page.waitForLoadState('networkidle');

    // Ambil Material Issue ID
    const miIdOut = execSync('php -r "require \'vendor/autoload.php\'; $a = require_once \'bootstrap/app.php\'; $a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); echo App\\Models\\MaterialIssue::where(\'issue_number\', \'MI-C-001\')->value(\'id\');"', { encoding: 'utf-8' });
    createdMaterialIssueId = parseInt(miIdOut.trim(), 10);
    console.log(`[PASS] Created Material Issue ID: ${createdMaterialIssueId}`);

    await expect(page.locator('h1')).toContainText('MI-C-001');
    await saveEvidence(page, 'c_06_material_issue_draft.png');

    // 3.3 Konfirmasi & Setujui Gudang via Form Modal
    await page.evaluate(() => {
      const form = document.querySelector('form[action*="/confirm-warehouse"]') as HTMLFormElement;
      if (form) form.submit();
    });
    // Tunggu sampai alert konfirmasi berhasil muncul
    const confirmAlert = page.locator('div[role="alert"]');
    await expect(confirmAlert).toBeVisible({ timeout: 15000 });
    await expect(confirmAlert).toContainText(/berhasil disetujui/i);

    // 3.4 Selesaikan Pengeluaran Bahan (Potong Stok Fisik & Buat Jurnal BDP)
    await page.evaluate(() => {
      const form = document.querySelector('form[action*="/complete"]') as HTMLFormElement;
      if (form) form.submit();
    });
    // Verifikasi pesan sukses penyelesaian / dokumen telah selesai
    const successAlert = page.locator('div[role="alert"]');
    await expect(successAlert).toBeVisible({ timeout: 15000 });
    await expect(successAlert).toContainText(/berhasil diselesaikan|dimutasi|sudah berstatus Selesai/i);
    await expect(page.locator('text=Selesai').first()).toBeVisible();
    await saveEvidence(page, 'c_07_material_issue_completed.png');

    // Verifikasi side-effect: Stok bahan baku berkurang dari 50 menjadi 40
    const stockAfter = execSync('php -r "require \'vendor/autoload.php\'; $a = require_once \'bootstrap/app.php\'; $a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); echo App\\Models\\InventoryStock::where(\'product_id\', 3)->where(\'warehouse_id\', 1)->value(\'qty_available\');"', { encoding: 'utf-8' });
    expect(parseFloat(stockAfter.trim())).toBe(40);
    console.log('[PASS] Raw Material Stock verified decremented to 40 units in DB.');
  });

  // =========================================================================
  // 4. BUKTI HASIL PRODUKSI (PRODUCTION OUTPUT) - UNHAPPY & HAPPY PATH
  // =========================================================================
  test('C-04: Hasil Produksi - Unhappy Rapid Multi-click & Happy Path Catat Output 5 Unit', async ({ page }) => {
    // 4.1 Unhappy Path: Form Hasil Produksi tanpa input wajib & rapid double click
    await page.goto('http://127.0.0.1:8009/manufaktur/production/create');
    await page.waitForLoadState('networkidle');

    await page.evaluate(() => {
      const form = document.getElementById('productionForm') as HTMLFormElement;
      if (form) form.noValidate = true;
    });

    await page.fill('#production_number', '');
    await page.fill('#quantity_produced', '');

    // Rapid double-click on submit button
    await Promise.all([
      page.click('button[type="submit"]:has-text("Simpan Hasil Produksi")'),
      page.click('button[type="submit"]:has-text("Simpan Hasil Produksi")').catch(() => {}),
    ]);
    await page.waitForLoadState('networkidle');

    const errAlert = page.locator('.bg-rose-50, .text-rose-800').first();
    await expect(errAlert).toBeVisible();
    await expect(errAlert).toContainText(/kesalahan pengisian|wajib/i);
    await saveEvidence(page, 'c_08_production_unhappy.png');

    // 4.2 Happy Path: Catat Hasil Produksi PROD-C-001 (5 Unit Finished)
    await page.goto(`http://127.0.0.1:8009/manufaktur/production/create?spk_id=${createdSpkId}`);
    await page.waitForLoadState('networkidle');

    await page.fill('#production_number', 'PROD-C-001');
    await page.evaluate(({ spkId }) => {
      const spkInput = document.getElementById('manufacturing_order_id') as HTMLInputElement;
      if (spkInput) {
        spkInput.value = String(spkId);
        spkInput.dispatchEvent(new Event('input', { bubbles: true }));
        spkInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
      const qtyInput = document.getElementById('quantity_produced') as HTMLInputElement;
      if (qtyInput) {
        qtyInput.value = '5.00';
        qtyInput.dispatchEvent(new Event('input', { bubbles: true }));
      }
      const statusSelect = document.getElementById('status') as HTMLSelectElement;
      if (statusSelect) {
        statusSelect.value = 'finished';
        statusSelect.dispatchEvent(new Event('change', { bubbles: true }));
      }
    }, { spkId: createdSpkId });

    // Submit form produksi
    await page.click('button[type="submit"]:has-text("Simpan Hasil Produksi")');
    await page.waitForLoadState('networkidle');

    // Ambil Production ID
    const prodIdOut = execSync('php -r "require \'vendor/autoload.php\'; $a = require_once \'bootstrap/app.php\'; $a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); echo App\\Models\\Production::where(\'production_number\', \'PROD-C-001\')->value(\'id\');"', { encoding: 'utf-8' });
    createdProductionId = parseInt(prodIdOut.trim(), 10);
    console.log(`[PASS] Created Production ID: ${createdProductionId}`);

    // Verifikasi detail LHP menampilkan status Selesai (Finished)
    await expect(page.locator('h1')).toContainText('PROD-C-001');
    await expect(page.locator('text=Selesai (Finished)')).toBeVisible();

    // Verifikasi Quality Control otomatis terbit
    const qcIdOut = execSync(`php -r "require 'vendor/autoload.php'; $a = require_once 'bootstrap/app.php'; $a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); echo App\\Models\\QualityControl::where('from_model_type', App\\Models\\Production::class)->where('from_model_id', ${createdProductionId})->value('id');"`, { encoding: 'utf-8' });
    createdQcId = parseInt(qcIdOut.trim(), 10);
    expect(createdQcId).toBeGreaterThan(0);
    console.log(`[PASS] Automatically Generated QC ID: ${createdQcId}`);

    await saveEvidence(page, 'c_09_production_finished_detail.png');
  });

  // =========================================================================
  // 5. QUALITY CONTROL MANUFAKTUR - UNHAPPY & HAPPY PATH
  // =========================================================================
  test('C-05: Quality Control - Unhappy Path Over-Qty & Happy Path Loloskan 5 Unit ke Gudang', async ({ page }) => {
    // 5.1 Unhappy Path: Input kuantitas lolos (6) + tolak (0) melebihi total produksi (5)
    await page.goto(`http://127.0.0.1:8009/manufaktur/qc/${createdQcId}/process`);
    await page.waitForLoadState('networkidle');

    await page.evaluate(() => {
      const form = document.getElementById('processQcForm') as HTMLFormElement;
      if (form) form.noValidate = true;
    });

    await page.fill('#passed_quantity', '6.00');
    await page.fill('#rejected_quantity', '0.00');
    await page.click('button[type="submit"]:has-text("Selesaikan & Simpan QC")');
    await page.waitForLoadState('networkidle');

    const errAlert = page.locator('.bg-rose-50, .text-rose-800').first();
    await expect(errAlert).toBeVisible();
    await expect(errAlert).toContainText(/kesalahan|melebihi|tidak boleh lebih/i);
    await saveEvidence(page, 'c_10_qc_unhappy_overqty.png');

    // 5.2 Happy Path: Loloskan 5.00 Unit, Simpan ke Gudang Utama Rak A1
    await page.goto(`http://127.0.0.1:8009/manufaktur/qc/${createdQcId}/process`);
    await page.waitForLoadState('networkidle');

    await page.fill('#passed_quantity', '5.00');
    await page.fill('#rejected_quantity', '0.00');

    await page.evaluate(() => {
      const whInput = document.getElementById('warehouse_id') as HTMLInputElement;
      if (whInput) {
        whInput.value = '1';
        whInput.dispatchEvent(new Event('input', { bubbles: true }));
        whInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
      const rakSelect = document.getElementById('rak_id') as HTMLSelectElement;
      if (rakSelect) {
        rakSelect.value = '1';
        rakSelect.dispatchEvent(new Event('change', { bubbles: true }));
      }
      const inspInput = document.querySelector('input[name="inspected_by"]') as HTMLInputElement;
      if (inspInput) {
        inspInput.value = '1';
        inspInput.dispatchEvent(new Event('input', { bubbles: true }));
        inspInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });

    await page.fill('input[name="notes"]', 'Inspeksi QC mutu sempurna, uji fungsi kelistrikan 100% lulus standar pabrik.');

    // Submit proses inspeksi QC
    await page.click('button[type="submit"]:has-text("Selesaikan & Simpan QC")');
    await page.waitForLoadState('networkidle');

    // Verifikasi alert sukses dan status Selesai di halaman detail QC
    const successMsg = page.locator('div[role="alert"]');
    await expect(successMsg).toBeVisible();
    await expect(successMsg).toContainText(/berhasil diproses|tercatat otomatis/i);

    await expect(page.locator('text=Sudah Diproses (Selesai)')).toBeVisible();
    await saveEvidence(page, 'c_11_qc_processed_detail.png');

    // Verifikasi side-effect: Stok barang jadi bertambah dari 10 menjadi 15
    const fgStockAfter = execSync('php -r "require \'vendor/autoload.php\'; $a = require_once \'bootstrap/app.php\'; $a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); echo App\\Models\\InventoryStock::where(\'product_id\', 1)->where(\'warehouse_id\', 1)->value(\'qty_available\');"', { encoding: 'utf-8' });
    expect(parseFloat(fgStockAfter.trim())).toBe(15);
    console.log('[PASS] Finished Goods Stock verified incremented to 15 units in DB.');
  });

  // =========================================================================
  // 6. HARD REFRESH (F5) & PERSISTENCE STATE AUDIT
  // =========================================================================
  test('C-06: Hard Refresh (F5) State Persistence & Immutability Audit', async ({ page }) => {
    // 6.1 Buka halaman detail SPK lalu hard refresh
    await page.goto(`http://127.0.0.1:8009/manufaktur/spk/${createdSpkId}`);
    await page.waitForLoadState('networkidle');
    await page.reload({ waitUntil: 'networkidle' });

    await expect(page.locator('h1')).toContainText('MO-C-001');
    await expect(page.locator('text=Selesai Produksi')).toBeVisible();

    // 6.2 Buka halaman detail QC lalu hard refresh
    await page.goto(`http://127.0.0.1:8009/manufaktur/qc/${createdQcId}`);
    await page.waitForLoadState('networkidle');
    await page.reload({ waitUntil: 'networkidle' });

    await expect(page.locator('text=Sudah Diproses (Selesai)')).toBeVisible();
    await saveEvidence(page, 'c_12_hard_refresh_persistence.png');
    console.log('[PASS] State persistence verified after hard refresh (F5).');
  });

  // =========================================================================
  // 7. THREE-WAY RECONCILIATION & DATA INTEGRITY VERIFICATION
  // =========================================================================
  test('C-07: Three-Way Value Reconciliation & Journal Balance Verification', async () => {
    console.log('[Audit] Menjalankan scripts/verify-c-cycle-manufaktur.php...');
    const out = execSync('php scripts/verify-c-cycle-manufaktur.php', { encoding: 'utf-8' });
    console.log(out);
    expect(out).toContain('HASIL AUDIT DATA SIKLUS C: SEMUA DATA PRESISI & VALID (PASS)');
  });
});
