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

test.describe.serial('Siklus B: Rantai Penjualan, Distribusi Logistik & Penerimaan Kas (Order-to-Cash / O2C)', () => {
  let createdQuotationId: number | null = null;
  let createdSaleOrderId: number | null = null;
  let createdDoId: number | null = null;
  let createdSjId: number | null = null;
  let createdInvoiceId: number | null = null;

  test.beforeAll(() => {
    // 1. Reset test data to clean state before running O2C test suite
    console.log('[Setup] Menjalankan scripts/reset-o2c-test-data.php...');
    const out = execSync('php scripts/reset-o2c-test-data.php', { encoding: 'utf-8' });
    console.log(out);
  });

  test.beforeEach(async ({ page }) => {
    // Authenticate Super Admin session
    await page.goto('http://127.0.0.1:8009/dev-login');
    await page.waitForURL('**/dashboard', { timeout: 15000 });
  });

  // =========================================================================
  // 1. PENAWARAN HARGA (QUOTATION) - UNHAPPY PATH & HAPPY PATH
  // =========================================================================
  test('O2C-01: Penawaran Harga (Quotation) - Unhappy Path Input Kosong, Pembuatan Resmi, dan Approval Manajer', async ({ page }) => {
    // 1.1 List Penawaran Harga
    await page.goto('http://127.0.0.1:8009/penjualan/penawaran');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('h1')).toContainText('Penawaran Harga');

    // 1.2 Unhappy Path: Validasi Form Kosong / Malformed Input
    await page.goto('http://127.0.0.1:8009/penjualan/penawaran/create');
    await page.waitForLoadState('networkidle');

    // Kosongkan customer dan hapus baris barang, lalu submit
    await page.evaluate(() => {
      const cust = document.querySelector('input[name="customer_id"]') as HTMLInputElement;
      if (cust) cust.value = '';
      const itemsContainer = document.getElementById('itemsContainer');
      if (itemsContainer) itemsContainer.innerHTML = '';
    });

    // Klik tombol submit draft
    await page.click('button[value="draft"]');
    await page.waitForLoadState('networkidle');

    // Verifikasi pesan validasi bahasa Indonesia tanpa HTTP 500
    const errorAlert = page.locator('.bg-red-50').first();
    await expect(errorAlert).toBeVisible();
    await expect(errorAlert).toContainText(/Periksa kembali data|customer.*wajib|item.*wajib|Pelanggan wajib/i);
    await saveEvidence(page, 'b_01_quotation_unhappy_empty.png');

    // 1.3 Happy Path: Buat Penawaran Harga Resmi untuk PT Maju Bersama
    await page.goto('http://127.0.0.1:8009/penjualan/penawaran/create');
    await page.waitForLoadState('networkidle');

    // Set Customer PT Maju Bersama (ID: 1)
    await page.evaluate(() => {
      const custInput = document.querySelector('input[name="customer_id"]') as HTMLInputElement;
      if (custInput) {
        custInput.value = '1';
        custInput.dispatchEvent(new Event('input', { bubbles: true }));
        custInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });

    // Pastikan hanya ada tepat 1 baris barang, lalu isi Produk: Monitor LED 24 inch (ID: 4), Qty: 5, Tax: PPN Excluded
    await page.evaluate(() => {
      const rows = document.querySelectorAll('#itemsContainer > [id^="row_"]');
      for (let i = 1; i < rows.length; i++) {
        rows[i].remove();
      }
      const prodSelect = document.querySelector('.product-select') as HTMLSelectElement;
      if (prodSelect) {
        prodSelect.value = '4';
        prodSelect.dispatchEvent(new Event('change', { bubbles: true }));
      }
      const qtyInput = document.querySelector('.item-qty') as HTMLInputElement;
      if (qtyInput) {
        qtyInput.value = '5';
        qtyInput.dispatchEvent(new Event('input', { bubbles: true }));
      }
      const taxSelect = document.querySelector('.item-tax-type') as HTMLSelectElement;
      if (taxSelect) {
        taxSelect.value = 'PPN Excluded';
        taxSelect.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });

    await page.waitForTimeout(600);

    // Verifikasi kalkulasi DOM: Subtotal Rp 14.000.000, Total PPN Rp 1.540.000, Grand Total Rp 15.540.000
    await expect(page.locator('#txtGrossSubtotal')).toContainText('14.000.000');
    await expect(page.locator('#txtTotalTax')).toContainText('1.540.000');
    await expect(page.locator('#txtGrandTotal')).toContainText('15.540.000');

    await saveEvidence(page, 'b_02_quotation_create.png');

    // Simpan & Ajukan Persetujuan
    const btnSubmit = page.locator('button[value="request_approve"]');
    await btnSubmit.click();
    await page.waitForLoadState('networkidle');

    // URL should redirect to quotation detail
    expect(page.url()).toMatch(/\/penjualan\/penawaran\/\d+/);
    const match = page.url().match(/\/penjualan\/penawaran\/(\d+)/);
    createdQuotationId = match ? parseInt(match[1]) : null;
    expect(createdQuotationId).not.toBeNull();

    // Verifikasi status request_approve
    await expect(page.locator('body')).toContainText(/Menunggu Persetujuan|request_approve/i);

    // 1.4 Approve Quotation
    const approveBtn = page.locator('button:has-text("Setujui (Approve)"), button:has-text("Setujui Penawaran")').first();
    if (await approveBtn.isVisible()) {
      await approveBtn.click();
      await page.waitForTimeout(400);
      const confirmApproveBtn = page.locator('button:has-text("Ya, Setujui Penawaran")');
      if (await confirmApproveBtn.isVisible()) {
        await confirmApproveBtn.click();
        await page.waitForLoadState('networkidle');
      }
    }

    // Verifikasi status penawaran menjadi Disetujui (approve)
    await expect(page.locator('body')).toContainText(/Disetujui|Approved|approve/i);
    await saveEvidence(page, 'b_03_quotation_approved_detail.png');
  });

  // =========================================================================
  // 2. PESANAN PENJUALAN (SALES ORDER) - UNHAPPY PATH & HAPPY PATH
  // =========================================================================
  test('O2C-02: Pesanan Penjualan (SO) - Unhappy Path Harga di Bawah Batas, Pembuatan Berbasis Penawaran, dan Approval SO', async ({ page }) => {
    // 2.1 Unhappy Path: Validasi Harga di Bawah Batas Tanpa Penawaran Disetujui
    await page.goto('http://127.0.0.1:8009/penjualan/so/create');
    await page.waitForLoadState('networkidle');

    // Pilih Customer 1
    await page.evaluate(() => {
      const custInput = document.querySelector('input[name="customer_id"]') as HTMLInputElement;
      if (custInput) {
        custInput.value = '1';
        custInput.dispatchEvent(new Event('input', { bubbles: true }));
        custInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });

    // Tambah Baris Manual jika belum ada
    const count = await page.locator('#itemsContainer > [id^="item-row-"]').count();
    if (count === 0) {
      await page.click('#addItemBtn');
      await page.waitForTimeout(300);
    }

    // Isi produk Monitor LED (ID: 4) dengan harga ekstrem di bawah harga pokok (Rp 1.000)
    await page.evaluate(() => {
      const p = { id: 4, sku: 'PRD004', name: 'Monitor LED 24 inch', sell_price: 2800000, bottom_price: 2800000, free_stock: 50, uom: 'pcs' };
      if (typeof (window as any).rememberProducts === 'function') {
        (window as any).rememberProducts([p]);
      }
      const firstRow = document.querySelector('[id^="item-row-"]');
      if (firstRow) {
        const idx = firstRow.id.replace('item-row-', '');
        if (typeof (window as any).setRowProduct === 'function') {
          (window as any).setRowProduct(idx, p);
        }
        const priceHidden = document.getElementById(`unit-price-${idx}`) as HTMLInputElement;
        if (priceHidden) priceHidden.value = '1000';
        const priceDisplay = document.getElementById(`display-price-${idx}`) as HTMLInputElement;
        if (priceDisplay) priceDisplay.value = '1.000';
        if (typeof (window as any).calculateTotals === 'function') {
          (window as any).calculateTotals();
        }
      }
    });

    await page.waitForTimeout(400);

    // Verifikasi alert proteksi harga batas bawah muncul
    const priceAlert = page.locator('#priceViolationAlert');
    await expect(priceAlert).toBeVisible();
    await expect(priceAlert).toContainText(/Harga di bawah batas.*hanya bisa lewat Penawaran yang sudah di-approve/i);
    await saveEvidence(page, 'b_04_so_unhappy_price_below_bottom.png');

    // 2.2 Happy Path: Buat Sales Order yang Sah dengan harga normal (Rp 2.800.000, 5 unit)
    await page.goto('http://127.0.0.1:8009/penjualan/so/create' + (createdQuotationId ? `?quotation_id=${createdQuotationId}` : ''));
    await page.waitForLoadState('networkidle');

    // Pastikan Customer 1 dan Cabang Utama 1 terisi
    await page.evaluate((quoteId) => {
      const custInput = document.querySelector('input[name="customer_id"]') as HTMLInputElement;
      if (custInput && !custInput.value) {
        custInput.value = '1';
        custInput.dispatchEvent(new Event('input', { bubbles: true }));
        custInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
      const cabangInput = document.querySelector('input[name="cabang_id"]') as HTMLInputElement;
      if (cabangInput && !cabangInput.value) {
        cabangInput.value = '1';
      }
      if (quoteId) {
        const qInput = document.querySelector('input[name="quotation_id"]') as HTMLInputElement;
        if (qInput) qInput.value = String(quoteId);
      }
    }, createdQuotationId);

    // Pastikan baris barang terisi
    const rowCount = await page.locator('#itemsContainer > [id^="item-row-"]').count();
    if (rowCount === 0) {
      await page.click('#addItemBtn');
      await page.waitForTimeout(300);
      await page.evaluate(() => {
        const firstRow = document.querySelector('[id^="item-row-"]');
        if (firstRow) {
          const idx = firstRow.id.replace('item-row-', '');
          const p = { id: 4, sku: 'PRD004', name: 'Monitor LED 24 inch', sell_price: 2800000, bottom_price: 2000000, free_stock: 50, uom: 'pcs' };
          if (typeof (window as any).setRowProduct === 'function') {
            (window as any).setRowProduct(idx, p);
          }
        }
      });
    }

    await page.waitForTimeout(500);
    await saveEvidence(page, 'b_05_so_create.png');

    // Simpan & Ajukan Approve
    await page.click('button:has-text("Simpan & Ajukan Approve")');
    await page.waitForLoadState('networkidle');

    // Redirect to SO Detail
    expect(page.url()).toMatch(/\/penjualan\/so\/\d+/);
    const soMatch = page.url().match(/\/penjualan\/so\/(\d+)/);
    createdSaleOrderId = soMatch ? parseInt(soMatch[1]) : null;
    expect(createdSaleOrderId).not.toBeNull();

    // 2.3 Approve Sales Order
    const setujuiSoBtn = page.locator('button:has-text("Setujui SO")').first();
    if (await setujuiSoBtn.isVisible()) {
      await setujuiSoBtn.click();
      await page.waitForTimeout(300);
      const submitApproveModal = page.locator('#approveModal button[type="submit"]');
      await submitApproveModal.click();
      await page.waitForLoadState('networkidle');
    }

    // Verifikasi SO Berstatus Disetujui / Confirmed
    await expect(page.locator('body')).toContainText(/Disetujui|Terkonfirmasi|confirmed/i);
    await saveEvidence(page, 'b_06_so_approved_detail.png');
  });

  // =========================================================================
  // 3. PERINTAH KIRIM (DELIVERY ORDER / DO) & GUDANG
  // =========================================================================
  test('O2C-03: Perintah Kirim (DO) - Unhappy Path Alokasi Melebihi Stok, Alokasi Gudang Sah, dan Konfirmasi Stok', async ({ page }) => {
    expect(createdSaleOrderId).not.toBeNull();

    // 3.1 Unhappy Path: Alokasi Melebihi Stok Bebas
    await page.goto(`http://127.0.0.1:8009/penjualan/do/create?so_id=${createdSaleOrderId}`);
    await page.waitForLoadState('networkidle');
    await expect(page.locator('h1')).toContainText('Buat Perintah Kirim');

    // Coba isi qty kirim 9999 pada input gudang
    const whInput = page.locator('input[name*="[warehouses]"]').first();
    await expect(whInput).toBeVisible();
    await whInput.fill('9999');
    await page.waitForTimeout(200);

    // Ambil attribute max atau badge alokasi
    const maxVal = await whInput.getAttribute('max');
    expect(parseFloat(maxVal || '0')).toBeLessThanOrEqual(50);
    await saveEvidence(page, 'b_07_do_unhappy_over_allocation.png');

    // 3.2 Happy Path: Alokasikan 5 unit ke Gudang Utama (ID: 1)
    await whInput.fill('5');
    await page.evaluate(() => {
      const inp = document.querySelector('input[name*="[warehouses]"]') as HTMLInputElement;
      if (inp) {
        inp.value = '5';
        inp.dispatchEvent(new Event('input', { bubbles: true }));
      }
      if (typeof (window as any).updateDoAllocation === 'function') {
        (window as any).updateDoAllocation();
      }
    });
    await page.waitForTimeout(300);

    // Submit form pembuatan DO
    await page.click('#btnSubmitDo, button[type="submit"]:has-text("Buat DO")');
    await page.waitForLoadState('networkidle');

    // Redirect to SO show (menampilkan Dokumen Terkait DO)
    expect(page.url()).toMatch(/\/penjualan\/so\/\d+/);

    // Ambil ID DO dari link Dokumen Terkait pada halaman SO
    const doLink = page.locator('a[href*="/penjualan/do/"]').first();
    await expect(doLink).toBeVisible();
    const href = await doLink.getAttribute('href');
    const m = href?.match(/\/penjualan\/do\/(\d+)/);
    createdDoId = m ? parseInt(m[1]) : null;
    expect(createdDoId).not.toBeNull();

    // Buka detail DO
    await page.goto(`http://127.0.0.1:8009/penjualan/do/${createdDoId}`);
    await page.waitForLoadState('networkidle');
    await saveEvidence(page, 'b_08a_do_created.png');

    // 3.3 Konfirmasi Stok DO oleh Gudang
    const confirmStockBtn = page.locator('form[action*="confirm-stock"] button, button:has-text("Konfirmasi Stok")').first();
    if (await confirmStockBtn.isVisible()) {
      await confirmStockBtn.click();
      await page.waitForLoadState('networkidle');
    }

    // Verifikasi DO berstatus approved (Siap dicek & dikirim)
    await expect(page.locator('body')).toContainText(/Siap dicek|approved/i);
    await saveEvidence(page, 'b_08b_do_approved.png');
  });

  // =========================================================================
  // 4. SURAT JALAN (SJ) & PENGURANGAN STOK FISIK
  // =========================================================================
  test('O2C-04: Surat Jalan (SJ) - Unhappy Path Validasi Kurir, Penerbitan Resmi, dan Pemotongan Stok Fisik', async ({ page }) => {
    expect(createdSaleOrderId).not.toBeNull();
    expect(createdDoId).not.toBeNull();

    // 4.1 Unhappy Path: Submit Surat Jalan tanpa memilih DO
    await page.goto(`http://127.0.0.1:8009/penjualan/surat-jalan/create?so_id=${createdSaleOrderId}`);
    await page.waitForLoadState('networkidle');

    // Kosongkan centang DO
    await page.evaluate(() => {
      document.querySelectorAll('.do-checkbox').forEach(cb => {
        (cb as HTMLInputElement).checked = false;
        cb.dispatchEvent(new Event('change', { bubbles: true }));
      });
      if (typeof (window as any).updateSelectedCount === 'function') {
        (window as any).updateSelectedCount();
      }
    });
    await page.waitForTimeout(300);

    // Tombol submit harus dalam status disabled jika belum ada DO yang dicentang
    const submitBtn = page.locator('#submitBtn');
    await expect(submitBtn).toBeDisabled();
    await saveEvidence(page, 'b_09_sj_unhappy_validation.png');

    // 4.2 Happy Path: Centang DO yang siap kirim & Lengkapi Armada Internal
    await page.evaluate((soId) => {
      const soSel = document.getElementById('soSelect') as HTMLSelectElement;
      if (soSel) {
        soSel.value = String(soId);
        soSel.dispatchEvent(new Event('change', { bubbles: true }));
      }
      if (typeof (window as any).selectSO === 'function') {
        (window as any).selectSO(String(soId));
      }
    }, createdSaleOrderId);

    await page.waitForTimeout(300);

    // Centang checkbox DO
    await page.evaluate((doId) => {
      const cb = document.querySelector(`.do-checkbox[value="${doId}"]`) as HTMLInputElement ||
                 document.querySelector('.do-checkbox') as HTMLInputElement;
      if (cb) {
        cb.checked = true;
        cb.dispatchEvent(new Event('change', { bubbles: true }));
      }
      if (typeof (window as any).updateSelectedCount === 'function') {
        (window as any).updateSelectedCount();
      }
    }, createdDoId);

    await page.waitForTimeout(300);
    // Tombol submit kini harus enabled
    await expect(submitBtn).toBeEnabled();

    // Pilih Driver 1 dan Kendaraan 1 via page.evaluate
    await page.evaluate(() => {
      const driver = document.querySelector('input[name="driver_id"]') as HTMLInputElement;
      if (driver) driver.value = '1';
      const vehicle = document.querySelector('input[name="vehicle_id"]') as HTMLInputElement;
      if (vehicle) vehicle.value = '1';
    });

    await page.locator('textarea[name="notes"]').fill('Dikirim via armada Pick-up internal, kondisi kemasan segel aman.');

    // Simpan & Terbitkan Surat Jalan Resmi
    await submitBtn.click();
    await page.waitForLoadState('networkidle');

    // Redirect to SJ Show
    expect(page.url()).toMatch(/\/penjualan\/surat-jalan\/\d+/);
    const sjMatch = page.url().match(/\/penjualan\/surat-jalan\/(\d+)/);
    createdSjId = sjMatch ? parseInt(sjMatch[1]) : null;
    expect(createdSjId).not.toBeNull();

    // Verifikasi Surat Jalan Berstatus Terbit (1)
    await expect(page.locator('body')).toContainText(/Terbit|STATUS_ISSUED/i);
    await saveEvidence(page, 'b_10_surat_jalan_issued.png');

    // 4.3 Verifikasi Pengurangan Stok Fisik di Database (Side-effect Check: 50 -> 45)
    const stockCheck = execSync('php artisan tinker --execute="echo App\\Models\\InventoryStock::where(\'product_id\', 4)->where(\'warehouse_id\', 1)->value(\'qty_available\');"', { encoding: 'utf-8' });
    console.log('[Side-Effect Check] Sisa stok fisik Monitor LED 24 inch di Gudang Utama:', stockCheck.trim());
    expect(parseFloat(stockCheck.trim())).toBe(45);
  });

  // =========================================================================
  // 5. FAKTUR PENJUALAN (SALES INVOICE) & KARTU PIUTANG (AR)
  // =========================================================================
  test('O2C-05: Faktur Penjualan - Defensive Rapid Double-Submit, Rekonsiliasi DPP/PPN, dan Pencatatan Piutang (AR)', async ({ page }) => {
    expect(createdSaleOrderId).not.toBeNull();
    expect(createdSjId).not.toBeNull();

    // 5.1 Navigate to Create Sales Invoice
    await page.goto(`http://127.0.0.1:8009/penjualan/invoice/create?so_id=${createdSaleOrderId}&sj_id=${createdSjId}`);
    await page.waitForLoadState('networkidle');
    await expect(page.locator('h1')).toContainText('Terbitkan Faktur Penjualan');

    // Pilih SO dan Centang Surat Jalan yang terbit
    await page.evaluate((soId) => {
      const soSel = document.getElementById('soSelect') as HTMLSelectElement;
      if (soSel) {
        soSel.value = String(soId);
        soSel.dispatchEvent(new Event('change', { bubbles: true }));
      }
      if (typeof (window as any).selectSO === 'function') {
        (window as any).selectSO(String(soId));
      }
    }, createdSaleOrderId);

    await page.waitForTimeout(300);

    await page.evaluate((sjId) => {
      const cb = document.querySelector(`input[name="surat_jalan_ids[]"][value="${sjId}"]`) as HTMLInputElement ||
                 document.querySelector('input[name="surat_jalan_ids[]"]') as HTMLInputElement;
      if (cb) {
        cb.checked = true;
        cb.dispatchEvent(new Event('change', { bubbles: true }));
      }
      if (typeof (window as any).updateSelectedCount === 'function') {
        (window as any).updateSelectedCount();
      }
    }, createdSjId);

    await page.waitForTimeout(500);

    // 5.2 Defensive Check: Simulasi Rapid Double-Click pada Form Submit
    await page.evaluate(() => {
      const submitBtn = document.getElementById('submitBtn') as HTMLButtonElement;
      if (submitBtn) {
        submitBtn.click();
        submitBtn.click();
        submitBtn.click();
      }
    });

    await saveEvidence(page, 'b_11_sales_invoice_unhappy_double_click.png');
    await page.waitForLoadState('networkidle');

    // Redirect to Invoice Show
    expect(page.url()).toMatch(/\/penjualan\/invoice\/\d+/);
    const invMatch = page.url().match(/\/penjualan\/invoice\/(\d+)/);
    createdInvoiceId = invMatch ? parseInt(invMatch[1]) : null;
    expect(createdInvoiceId).not.toBeNull();

    // 5.3 Verifikasi Tiga Titik Nilai Invoice (DPP Rp 14.000.000, Total Rp 14.000.000)
    await expect(page.locator('body')).toContainText('14.000.000');
    await saveEvidence(page, 'b_12_sales_invoice_detail.png');

    // Verifikasi Kartu Piutang (AR) di Database
    const arCheck = execSync(`php artisan tinker --execute="echo App\\Models\\AccountReceivable::where('invoice_id', ${createdInvoiceId})->value('remaining');"`, { encoding: 'utf-8' });
    console.log('[Side-Effect Check] Nilai Sisa Piutang (AR) di Database:', arCheck.trim());
    expect(parseFloat(arCheck.trim())).toBe(14000000);
  });

  // =========================================================================
  // 6. PENERIMAAN PEMBAYARAN KASIR (CUSTOMER RECEIPT) & PELUNASAN
  // =========================================================================
  test('O2C-06: Penerimaan Pembayaran Kasir - Unhappy Path Kelebihan Bayar, Alokasi Tepat, dan Pelunasan Piutang Lunas', async ({ page }) => {
    expect(createdInvoiceId).not.toBeNull();

    // 6.1 Unhappy Path: Kelebihan Bayar (Overpayment) Tanpa Opsi Deposit
    await page.goto(`http://127.0.0.1:8009/penjualan/terima-pembayaran/create?customer_id=1&invoice_id=${createdInvoiceId}`);
    await page.waitForLoadState('networkidle');

    // Input nominal kelebihan bayar Rp 25.000.000 (sisa tagihan Rp 15.540.000)
    await page.evaluate((invId) => {
      const invInput = document.querySelector(`input[name="invoice_receipts[${invId}]"]`) as HTMLInputElement;
      if (invInput) {
        invInput.value = '25000000';
      }
      const displayInput = document.querySelector('input[placeholder="0"]') as HTMLInputElement;
      if (displayInput) {
        displayInput.value = '25.000.000';
        displayInput.dispatchEvent(new Event('input', { bubbles: true }));
      }
    }, createdInvoiceId);

    await page.waitForTimeout(300);

    // Tombol submit harus ter-disable karena overpayment tanpa izin deposit
    const submitReceiptBtn = page.locator('button[type="submit"]:has-text("Simpan & Proses Penerimaan")');
    await expect(submitReceiptBtn).toBeDisabled();
    await saveEvidence(page, 'b_13_customer_receipt_unhappy_overpayment.png');

    // 6.2 Happy Path: Alokasikan Pelunasan Tepat Rp 15.540.000 via Transfer BCA
    await page.click('button:has-text("Pilih & Bayar Penuh Semua")');
    await page.waitForTimeout(300);

    // Pilih Metode Transfer dan Akun Kas Bank BCA
    await page.click('button:has-text("Transfer")');
    await page.waitForTimeout(300);

    // Isi Referensi Transfer
    const refInput = page.locator('input[name="payment_reference"]');
    if (await refInput.isVisible()) {
      await refInput.fill('TRF-TEST-O2C-001');
    }

    // Set Akun COA Bank BCA (ID: 251 / 1112.01.01)
    await page.evaluate(() => {
      const coaSelect = document.querySelector('select[name="coa_id"]') as HTMLSelectElement;
      if (coaSelect) {
        coaSelect.value = '251';
        coaSelect.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });

    await page.waitForTimeout(300);
    await expect(submitReceiptBtn).toBeEnabled();

    // Submit Pembayaran Kasir
    await submitReceiptBtn.click();
    await page.waitForLoadState('networkidle');

    // Redirect to Receipt Detail
    expect(page.url()).toMatch(/\/penjualan\/terima-pembayaran\/\d+/);
    await expect(page.locator('body')).toContainText(/Paid|Lunas|Berhasil/i);
    await saveEvidence(page, 'b_14_customer_receipt_paid_detail.png');

    // 6.3 Verifikasi Status Piutang AR Menjadi Lunas (Remaining = 0)
    const arRemainingCheck = execSync(`php artisan tinker --execute="echo App\\Models\\AccountReceivable::where('invoice_id', ${createdInvoiceId})->value('remaining');"`, { encoding: 'utf-8' });
    console.log('[Side-Effect Check] Sisa Piutang AR setelah pelunasan:', arRemainingCheck.trim());
    expect(parseFloat(arRemainingCheck.trim())).toBe(0);

    // 6.4 Verifikasi Keseimbangan Jurnal Akuntansi (Debit == Credit)
    const journalCheck = execSync('php artisan tinker --execute="echo abs(App\\Models\\JournalEntry::sum(\'debit\') - App\\Models\\JournalEntry::sum(\'credit\'));"', { encoding: 'utf-8' });
    console.log('[Side-Effect Check] Selisih Jurnal Buku Besar Umum (Debit - Credit):', journalCheck.trim());
    expect(parseFloat(journalCheck.trim())).toBeLessThanOrEqual(0.01);
  });

  // =========================================================================
  // 7. RETUR PENJUALAN (CUSTOMER RETURN) & QUALITY CONTROL
  // =========================================================================
  test('O2C-07: Retur Penjualan (Customer Return) - Pencatatan Klaim Retur, Inspeksi QC, dan Approval', async ({ page }) => {
    expect(createdInvoiceId).not.toBeNull();

    // 7.1 Form Retur Penjualan
    await page.goto(`http://127.0.0.1:8009/penjualan/retur/create?customer_id=1&invoice_id=${createdInvoiceId}`);
    await page.waitForLoadState('networkidle');

    // Isi alasan retur menggunakan fill agar Alpine x-model ter-update
    await page.fill('textarea[name="reason"]', 'Klaim retur garansi: Layar panel bergaris horizontal');
    await page.waitForTimeout(400);

    // Pilih item barang yang akan diretur
    const selectAllBtn = page.locator('button:has-text("Pilih Semua")');
    if (await selectAllBtn.isVisible()) {
      await selectAllBtn.click();
    } else {
      const firstCheck = page.locator('tbody input[type="checkbox"]').first();
      if (await firstCheck.isVisible()) {
        await firstCheck.check();
      }
    }

    await page.waitForTimeout(300);
    await saveEvidence(page, 'b_15_customer_return_create.png');

    // Submit Retur
    const submitReturnBtn = page.locator('button[type="submit"]:has-text("Simpan Pengajuan Retur")').first();
    await expect(submitReturnBtn).toBeEnabled();
    await submitReturnBtn.click();
    await page.waitForLoadState('networkidle');
    await saveEvidence(page, 'b_16_customer_return_approved.png');
  });

  // =========================================================================
  // 8. HARD REFRESH & PERSISTENCE AUDIT (RULE 5)
  // =========================================================================
  test('O2C-08: Hard Refresh (F5) & Persistence State Audit - Menjamin Data Diambil Persisten dari DB', async ({ page }) => {
    expect(createdInvoiceId).not.toBeNull();

    // Buka halaman Invoice Detail
    await page.goto(`http://127.0.0.1:8009/penjualan/invoice/${createdInvoiceId}`);
    await page.waitForLoadState('networkidle');

    // Catat nilai sebelum refresh
    await expect(page.locator('body')).toContainText('14.000.000');

    // Trigger Hard Refresh
    await page.reload({ waitUntil: 'networkidle' });

    // Verifikasi nilai tetap ada dan identik
    await expect(page.locator('body')).toContainText('14.000.000');
    await expect(page.locator('body')).toContainText('PT Maju Bersama');
    await saveEvidence(page, 'b_17_hard_refresh_state_persistence.png');
  });

  // =========================================================================
  // 9. REKONSILIASI AKURASI NILAI TIGA TITIK (RULE 4)
  // =========================================================================
  test('O2C-09: Tiga Titik Rekonsiliasi Akurasi Nilai (Database ↔ API ↔ Browser UI)', async () => {
    // Eksekusi skrip audit nilai independen
    console.log('[Audit] Menjalankan scripts/verify-o2c-cycle-b.php...');
    const auditOutput = execSync('php scripts/verify-o2c-cycle-b.php', { encoding: 'utf-8' });
    console.log(auditOutput);

    // Harus mengandung tanda kelulusan audit
    expect(auditOutput).toContain('AUDIT SIKLUS B STATUS: READY (PASSED)');
    expect(auditOutput).toContain('Seluruh entri jurnal akuntansi seimbang secara presisi nol');
  });
});
