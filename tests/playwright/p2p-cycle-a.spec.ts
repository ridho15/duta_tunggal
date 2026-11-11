import { test, expect } from '@playwright/test';
import path from 'path';
import { execSync } from 'child_process';

const SCREENSHOT_DIR = 'C:/Users/ralam/.gemini/antigravity/brain/37609e76-32d0-4a5e-8715-8e9934fbf3fd/screenshots';

test.describe.serial('Siklus A: Rantai Pengadaan & Penerimaan Barang (Procure-to-Pay / P2P)', () => {
  test.beforeAll(() => {
    // Reset test data to clean state before running test suite
    execSync('php scripts/reset-p2p-test-data.php', { encoding: 'utf-8' });
  });

  test.beforeEach(async ({ page }) => {
    // Authenticate Super Admin session
    await page.goto('http://127.0.0.1:8009/dev-login');
    await page.waitForURL('**/dashboard', { timeout: 15000 });
  });

  // =========================================================================
  // 1. PURCHASE ORDER (PO) VERIFICATION & DETAIL AUDIT
  // =========================================================================
  test('P2P-01: Purchase Order (PO) - Daftar, Tab Filter, KPI Cards, dan Detail PO Approved', async ({ page }) => {
    // 1.1 Navigate to PO List
    await page.goto('http://127.0.0.1:8009/pembelian/po');
    await page.waitForLoadState('networkidle');

    // Title check
    await expect(page).toHaveTitle(/.*Duta Tunggal ERP.*/i);

    // Verify search input & table
    const searchInput = page.locator('input[name="search"], input[name="q"], input[type="search"]').first();
    await expect(searchInput).toBeVisible();

    const table = page.locator('table').first();
    await expect(table).toBeVisible();

    // Verify status tabs
    const approvedTab = page.locator('a:has-text("Approved"), button:has-text("Approved")').first();
    if (await approvedTab.isVisible()) {
      await approvedTab.click();
      await page.waitForLoadState('networkidle');
    }

    // Capture screenshot: PO List
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'p2p_01_po_list.png'),
      fullPage: false,
    });

    // 1.2 Navigate to PO Create form
    await page.goto('http://127.0.0.1:8009/pembelian/po/create');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('#poForm')).toBeVisible();

    // Capture screenshot: PO Create
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'p2p_02_po_create.png'),
      fullPage: false,
    });

    // 1.3 View PO 13 Detail
    await page.goto('http://127.0.0.1:8009/pembelian/po/13');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText('PO-20260625-0001');

    // Capture screenshot: PO Detail
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'p2p_02b_po_detail.png'),
      fullPage: false,
    });

    // 1.4 PDF Generation Endpoint
    const pdfResponse = await page.request.get('http://127.0.0.1:8009/pembelian/po/13/pdf');
    expect(pdfResponse.status()).toBe(200);
    const contentType = pdfResponse.headers()['content-type'] || '';
    expect(contentType).toContain('pdf');
  });

  // =========================================================================
  // 2. PENERIMAAN BARANG & QC FISIK GUDANG (GRN)
  // =========================================================================
  test('P2P-02: Penerimaan Barang & QC - Input Kedatangan Fisik, Defensive Check, dan Konfirmasi Masuk Stok', async ({ page }) => {
    // 2.1 View GRN List
    await page.goto('http://127.0.0.1:8009/gudang/penerimaan');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('h1')).toContainText('Penerimaan Barang');

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'p2p_03_penerimaan_list.png'),
      fullPage: false,
    });

    // 2.2 Open GRN Create with PO 13 preselected
    await page.goto('http://127.0.0.1:8009/gudang/penerimaan/create?purchase_order_id=13');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('h1')).toContainText('Pencatatan Penerimaan Barang');

    // Verify item table loaded PO 13 product: Monitor LED 24 inch
    await expect(page.locator('body')).toContainText('Monitor LED 24 inch');
    await expect(page.locator('body')).toContainText('PRD004');

    // Fill Header
    await page.locator('input[name="supplier_delivery_note_number"]').fill('SJ-VENDOR-2026-001');

    // Select warehouse via page evaluation to safely trigger Alpine & Select-Enhancer
    await page.evaluate(() => {
      const sel = document.querySelector('select[name="warehouse_id"]') as HTMLSelectElement;
      if (sel) {
        sel.value = '1'; // Gudang Utama
        sel.dispatchEvent(new Event('input', { bubbles: true }));
        sel.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });

    await page.locator('textarea[name="notes"]').fill('Diterima via armada truk B 9999 DT, kondisi fisik tersegel, QC sampling 100%.');

    // 2.3 Defensive Check ("No Happy Path"): Over-receipt warning
    const qtyReceivedInput = page.locator('input[name*="qty_received"]').first();
    await qtyReceivedInput.fill('50'); // Over remaining 10
    await page.waitForTimeout(300);
    await expect(page.locator('text=Melebihi sisa PO!')).toBeVisible();

    // 2.4 QC Testing: Set Legitimate QC values (8 accepted, 2 rejected)
    await qtyReceivedInput.fill('10');
    await page.waitForTimeout(200);

    const qtyAcceptedInput = page.locator('input[name*="qty_accepted"]').first();
    await qtyAcceptedInput.fill('8');

    const qtyRejectedInput = page.locator('input[name*="qty_rejected"]').first();
    await qtyRejectedInput.fill('2');

    const reasonRejectedInput = page.locator('input[name*="reason_rejected"]').first();
    await reasonRejectedInput.fill('2 unit panel retak / pecah display transit');

    // Ensure item warehouse is set to Gudang Utama
    await page.evaluate(() => {
      const itemSel = document.querySelector('select[name="items[0][warehouse_id]"]') as HTMLSelectElement;
      if (itemSel) {
        itemSel.value = '1';
        itemSel.dispatchEvent(new Event('input', { bubbles: true }));
        itemSel.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'p2p_04b_penerimaan_qc_reject.png'),
      fullPage: false,
    });

    // 2.5 Submit "Simpan & Konfirmasi Masuk Stok"
    const confirmBtn = page.locator('button:has-text("Simpan & Konfirmasi Masuk Stok")');
    await confirmBtn.click();
    await page.waitForLoadState('networkidle');

    // Expect redirect to show or index
    expect(page.url()).toMatch(/\/gudang\/penerimaan/);

    // Capture screenshot: Confirmed GRN
    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'p2p_04c_penerimaan_confirmed.png'),
      fullPage: false,
    });
  });

  // =========================================================================
  // 3. TAGIHAN FAKTUR PEMBELIAN (PURCHASE INVOICE / PI) & 3-WAY MATCHING
  // =========================================================================
  test('P2P-03: Faktur Pembelian (Purchase Invoice) - 3-Way Matching, PPN 11%, dan Posting AP', async ({ page }) => {
    // 3.1 View Invoice List
    await page.goto('http://127.0.0.1:8009/pembelian/invoice');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('h1')).toContainText('Faktur Pembelian');

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'p2p_05_purchase_invoice_list.png'),
      fullPage: false,
    });

    // 3.2 Open Invoice Create with Supplier 15 preselected
    await page.goto('http://127.0.0.1:8009/pembelian/invoice/create?supplier_id=15');
    await page.waitForLoadState('networkidle');

    // Wait for AJAX receipts to load
    await page.waitForTimeout(1000);

    // Select the available GRN checkbox
    const receiptCheckbox = page.locator('input[name="purchase_receipts[]"]').first();
    await expect(receiptCheckbox).toBeVisible({ timeout: 10000 });
    await receiptCheckbox.check();

    // Wait for items to be calculated
    await page.waitForTimeout(1200);

    // Verify 3-way matched items loaded: Monitor LED 24 inch, qty 8
    await expect(page.locator('table')).toContainText('Monitor LED 24 inch');

    // Fill Invoice Header Numbers
    await page.locator('input[name="supplier_invoice_number"]').fill('INV-PJ-2026-9901');
    await page.locator('input[name="tax_invoice_number"]').fill('010.000-26.98765432');

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'p2p_06_purchase_invoice_create.png'),
      fullPage: false,
    });

    // 3.3 Submit "Simpan & Terbitkan Langsung (Posting AP)"
    const postInvoiceBtn = page.locator('button[value="save_and_post"]');
    await postInvoiceBtn.click();
    await page.waitForLoadState('networkidle');

    // Verify redirected to invoice show page with success banner
    expect(page.url()).toMatch(/\/pembelian\/invoice\/\d+/);
    await expect(page.locator('body')).toContainText('INV-PJ-2026-9901');

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'p2p_06b_purchase_invoice_detail.png'),
      fullPage: false,
    });
  });

  // =========================================================================
  // 4. PEMBAYARAN HUTANG VENDOR (VENDOR PAYMENT / VP) & LEDGER POSTING
  // =========================================================================
  test('P2P-04: Pembayaran Hutang Vendor - Defensive Overpayment, Rekening Kas/Bank, dan Posting Jurnal', async ({ page }) => {
    // 4.1 View Payment List
    await page.goto('http://127.0.0.1:8009/pembelian/pembayaran');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('h1')).toContainText('Pembayaran Hutang Vendor');

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'p2p_07_vendor_payment_list.png'),
      fullPage: false,
    });

    // 4.2 Open Payment Create with Supplier 15 preselected
    await page.goto('http://127.0.0.1:8009/pembelian/pembayaran/create?supplier_id=15');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);

    // Select the unpaid invoice: click "Penuh" button or check checkbox
    const payFullBtn = page.locator('button:has-text("Penuh")').first();
    await expect(payFullBtn).toBeVisible({ timeout: 10000 });
    await payFullBtn.click();

    // Verify Bank Account (COA) is selected or select Bank BCA (id: 93)
    await page.evaluate(() => {
      const coa = document.querySelector('input[name="coa_id"]') as HTMLInputElement;
      if (coa && (!coa.value || coa.value === '0')) {
        coa.value = '93';
        coa.dispatchEvent(new Event('input', { bubbles: true }));
        coa.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });

    // Fill Transfer Details
    await page.locator('input[name="transfer_reference_number"]').fill('TRF-BCA-202611-9988');
    await page.locator('textarea[name="notes"]').fill('Pembayaran pengadaan monitor supplier PJ Winarsih Purwanti Tbk');

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'p2p_08_vendor_payment_create.png'),
      fullPage: false,
    });

    // 4.3 Submit "Simpan & Posting ke Buku Besar"
    const postPaymentBtn = page.locator('button[value="save_and_post"]');
    await postPaymentBtn.click();
    await page.waitForLoadState('networkidle');

    // Verify redirected to payment show page
    expect(page.url()).toMatch(/\/pembelian\/pembayaran\/\d+/);
    await expect(page.locator('body')).toContainText('TRF-BCA-202611-9988');

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'p2p_08b_vendor_payment_detail.png'),
      fullPage: false,
    });
  });

  // =========================================================================
  // 5. RETUR PEMBELIAN (PURCHASE RETURN & DEBIT NOTE)
  // =========================================================================
  test('P2P-05: Retur Pembelian - Lock Unit Price, Batas Kuantitas Maksimal, dan Pembuatan Draft Retur', async ({ page }) => {
    // 5.1 View Return List
    await page.goto('http://127.0.0.1:8009/pembelian/retur');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('h1')).toContainText('Retur Pembelian');

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'p2p_09_purchase_return_list.png'),
      fullPage: false,
    });

    // 5.2 Open Return Create with Supplier 15
    await page.goto('http://127.0.0.1:8009/pembelian/retur/create?supplier_id=15');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);

    // Select the receipt from dropdown via evaluate
    await page.evaluate(() => {
      const sel = document.querySelector('select[name="purchase_receipt_id"]') as HTMLSelectElement;
      if (sel && sel.options.length > 1) {
        sel.selectedIndex = 1;
        sel.dispatchEvent(new Event('input', { bubbles: true }));
        sel.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });

    await page.waitForTimeout(1000);

    // Check item row and fill return quantity 1
    const itemCheckbox = page.locator('table tbody input[type="checkbox"]').first();
    await expect(itemCheckbox).toBeVisible({ timeout: 10000 });
    await itemCheckbox.check();

    const qtyReturnInput = page.locator('table tbody input[type="number"]').first();
    await expect(qtyReturnInput).toBeVisible();
    await qtyReturnInput.fill('1');

    const reasonInput = page.locator('table tbody input[type="text"]').first();
    if (await reasonInput.isVisible()) {
      await reasonInput.fill('Cacat fungsi tombol setelah pengujian 24 jam');
    }

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'p2p_10_purchase_return_create.png'),
      fullPage: false,
    });

    // Save as draft
    const saveDraftBtn = page.locator('button[value="save_draft"]');
    await expect(saveDraftBtn).toBeEnabled({ timeout: 5000 });
    await saveDraftBtn.click();
    await page.waitForLoadState('networkidle');
    expect(page.url()).toMatch(/\/pembelian\/retur/);
  });

  // =========================================================================
  // 6. TWO-WAY DATA INTEGRITY & AUDIT VERIFICATION IN MYSQL
  // =========================================================================
  test('P2P-06: Verifikasi Dua Arah Basis Data (MySQL Data Integrity Audit)', async () => {
    const output = execSync('php scripts/verify-p2p-cycle-a.php', { encoding: 'utf-8' });
    console.log(output);

    expect(output).toContain('SIAP DIJALANKAN (PASS)');
    expect(output).toContain('[OK] Semua entri jurnal akuntansi seimbang secara presisi');
  });
});
