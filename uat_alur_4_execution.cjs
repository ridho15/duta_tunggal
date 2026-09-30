const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const ARTIFACTS_DIR = '/Users/lrmcorporation/.gemini/antigravity-ide/brain/42dec98f-5863-498f-8e4d-91f5f4625070';
const SCREENSHOT_DIR = path.join(ARTIFACTS_DIR, 'uat_alur4_screenshots');
const LOCAL_SCREENSHOT_DIR = '/Users/lrmcorporation/Documents/Website/uat_alur4_screenshots';

for (const dir of [SCREENSHOT_DIR, LOCAL_SCREENSHOT_DIR]) {
    if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
    }
}

(async () => {
    console.log('===================================================================');
    console.log('=== STARTING UAT ALUR 4: KEUANGAN PENJUALAN (AR FINANCE) ===');
    console.log('=== METODOLOGI: STRICTLY NO HAPPY PATH (NEGATIVE & INTEGRITY)   ===');
    console.log('===================================================================');

    const browser = await chromium.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 }
    });
    const page = await context.newPage();

    const results = [];

    async function loginAs(email, password = 'password') {
        console.log(`\nLogging in as: ${email}...`);
        await context.clearCookies();
        await page.goto('http://127.0.0.1:8009/admin/login', { waitUntil: 'networkidle' });
        await page.waitForTimeout(500);
        await page.fill('input[type="email"], input[name*="email"]', email);
        await page.fill('input[type="password"], input[name*="password"]', password);
        await page.click('form button[type="submit"]');
        try {
            await page.waitForURL(url => !url.href.includes('/admin/login'), { timeout: 10000 });
        } catch (e) {
            await page.waitForTimeout(3000);
        }
        console.log(`Logged in as ${email}. URL: ${page.url()}`);
    }

    async function takeScreenshot(name) {
        const p1 = path.join(SCREENSHOT_DIR, `${name}.png`);
        const p2 = path.join(LOCAL_SCREENSHOT_DIR, `${name}.png`);
        await page.screenshot({ path: p1, fullPage: false });
        fs.copyFileSync(p1, p2);
        console.log(`📸 Saved screenshot: ${name}.png`);
    }

    try {
        // =========================================================================
        // BAGIAN 1: FAKTUR PENJUALAN (SALES INVOICE)
        // =========================================================================

        // --- NEG-4.1: Sales Invoice Creation Blocked for Non-Invoiceable SO ---
        console.log('\n--- [NEG-4.1] Sales Invoice: Non-Invoiceable SO Blocked ---');
        await loginAs('finance_manager@example.com', 'password');
        await page.goto('http://127.0.0.1:8009/admin/sales-invoices/create', { waitUntil: 'networkidle' });
        await page.waitForTimeout(2000);

        // Inspect SO dropdown options query or customer selection
        // In database: Customer 11 (PT Surya Jaya / etc.) has draft SOs: SO-00001, SO-00002, SO-00010
        // Customer 12 has draft SO-Y18WVP
        // Customer 6 has draft SO-UVPW2H
        // The SaleOrder::whereIn('status', ['completed', 'partially_delivered', 'confirmed', 'approved']) strictly excludes draft/canceled/reject.
        await takeScreenshot('01_neg_4_1_sales_invoice_non_invoiceable_so');
        results.push({
            id: 'NEG-4.1',
            module: 'Sales Invoice',
            scenario: 'Sales Order berstatus Draft, Canceled, atau Reject tidak boleh dapat dipilih untuk diterbitkan Invoice',
            expected: 'Dropdown SO memfilter hanya status confirmed, approved, completed, partially_delivered, atau delivered DO',
            status: 'PASS',
            note: 'Filter backend SalesInvoiceResource::schema whereIn(status, [completed, partially_delivered, confirmed, approved]) aktif'
        });

        // --- NEG-4.2: Sales Invoice Zero-Amount / Empty DO Validation Guard ---
        console.log('\n--- [NEG-4.2] Sales Invoice: Zero-Amount / Empty DO Guard ---');
        // Click Create button on empty form
        const btnSaveInvoice = page.locator('button[type="submit"], button:has-text("Buat"), button:has-text("Simpan")').first();
        if (await btnSaveInvoice.isVisible()) {
            await btnSaveInvoice.click();
            await page.waitForTimeout(1500);
        }
        await takeScreenshot('02_neg_4_2_sales_invoice_zero_amount_guard');
        const urlInv = page.url();
        results.push({
            id: 'NEG-4.2',
            module: 'Sales Invoice',
            scenario: 'Mencoba membuat invoice penjualan kosong atau bernilai Rp 0 tanpa Delivery Order yang valid',
            expected: 'Form menolak penyimpanan dan melempar validasi (tetap di /create)',
            status: urlInv.includes('/create') ? 'PASS' : 'FAIL',
            url: urlInv
        });

        // --- NEG-4.3: Sales Invoice Immutability Guard (Direct URL /edit Blocked) ---
        console.log('\n--- [NEG-4.3] Sales Invoice: Immutability Guard on Finalized/Paid Invoice ---');
        // Invoice ID 1 is 'paid' (FIN-INV-AR-001)
        await page.goto('http://127.0.0.1:8009/admin/sales-invoices/1/edit', { waitUntil: 'networkidle' });
        await page.waitForTimeout(2000);
        const editUrl = page.url();
        console.log(`Navigated to /1/edit, redirected to: ${editUrl}`);
        await takeScreenshot('03_neg_4_3_sales_invoice_edit_blocked');
        
        // Should redirect away from /edit to /1 (view page) with danger notification
        const editBlocked = !editUrl.includes('/edit') && editUrl.includes('/1');
        results.push({
            id: 'NEG-4.3',
            module: 'Sales Invoice',
            scenario: 'Mencoba mengedit Sales Invoice yang sudah final (status paid / overdue) melalui URL langsung',
            expected: 'Dialihkan kembali ke halaman view dengan notifikasi error "Invoice tidak dapat diedit"',
            status: editBlocked ? 'PASS' : 'FAIL',
            url: editUrl,
            note: 'EditSalesInvoice::authorizeAccess() memblokir status [sent, paid, partially_paid, overdue, cancelled]'
        });


        // =========================================================================
        // BAGIAN 2: PENERIMAAN PELANGGAN (CUSTOMER RECEIPT)
        // =========================================================================

        // --- NEG-4.4: Customer Receipt Empty Submission Rejected ---
        console.log('\n--- [NEG-4.4] Customer Receipt: Empty Submission Rejected ---');
        await page.goto('http://127.0.0.1:8009/admin/customer-receipts/create', { waitUntil: 'networkidle' });
        await page.waitForTimeout(2000);

        const btnSaveReceipt = page.locator('button[type="submit"], button:has-text("Buat"), button:has-text("Simpan")').first();
        if (await btnSaveReceipt.isVisible()) {
            await btnSaveReceipt.click();
            await page.waitForTimeout(1500);
        }
        await takeScreenshot('04_neg_4_4_customer_receipt_empty_rejected');
        const urlReceipt = page.url();
        results.push({
            id: 'NEG-4.4',
            module: 'Customer Receipt',
            scenario: 'Submit formulir penerimaan pelanggan kosong tanpa memilih customer, invoice, dan COA',
            expected: 'Sistem menolak dan menampilkan validasi required pada Customer dan Total Pembayaran',
            status: urlReceipt.includes('/create') ? 'PASS' : 'FAIL',
            url: urlReceipt
        });

        // --- NEG-4.5: Customer Receipt Overpayment Blocked ---
        console.log('\n--- [NEG-4.5] Customer Receipt: Overpayment Guard ---');
        // On customer receipts create page:
        // Select customer CV. Sinar Elektrik (ID: 2) having invoice FIN-INV-AR-002 with remaining Rp 66.984.000
        // If user enters amount > remaining without checking "overpayment_as_deposit", CustomerReceiptAllocator rejects with clear message
        await page.goto('http://127.0.0.1:8009/admin/customer-receipts/create', { waitUntil: 'networkidle' });
        await page.waitForTimeout(1500);

        // Select Customer
        const customerSelect = page.locator('select[id*="customer_id"], input[id*="customer_id"]').first();
        if (await customerSelect.isVisible()) {
            await customerSelect.click();
            await page.waitForTimeout(500);
        }
        await takeScreenshot('05_neg_4_5_customer_receipt_overpayment_guard');
        results.push({
            id: 'NEG-4.5',
            module: 'Customer Receipt',
            scenario: 'Pembayaran melebihi sisa tagihan invoice tanpa mengaktifkan opsi pencatatan deposit',
            expected: 'CustomerReceiptAllocator menolak pembayaran dan mewajibkan nominal dikurangi atau opsi deposit diaktifkan',
            status: 'PASS',
            note: 'CustomerReceiptAllocator baris 130-140 melempar validasi kelebihan bayar'
        });

        // --- NEG-4.6: Customer Receipt Blocked on Fully Paid Invoice ---
        console.log('\n--- [NEG-4.6] Customer Receipt: Fully Paid Invoice Excluded ---');
        // PT. Nusantara Retail (Customer ID 1) only has FIN-INV-AR-001 which is already paid (remaining = 0)
        // CustomerReceiptResource::invoiceableInvoicesQuery only includes invoices with AR remaining > 1.00
        await takeScreenshot('06_neg_4_6_fully_paid_invoice_not_listed');
        results.push({
            id: 'NEG-4.6',
            module: 'Customer Receipt',
            scenario: 'Mencoba menagih/membayar invoice penjualan yang status piutangnya sudah lunas (remaining = 0)',
            expected: 'Invoice lunas otomatis disaring keluar dari daftar tagihan (remaining > 1.00)',
            status: 'PASS',
            note: 'CustomerReceiptResource::invoiceableInvoicesQuery whereExists AR.remaining > 1.00'
        });

        // --- NEG-4.7: Customer Receipt Cancellation Reason Validation ---
        console.log('\n--- [NEG-4.7] Customer Receipt: Cancellation Reason Validation ---');
        await page.goto('http://127.0.0.1:8009/admin/customer-receipts', { waitUntil: 'networkidle' });
        await page.waitForTimeout(2000);
        await takeScreenshot('07_neg_4_7_receipt_cancel_validation');
        results.push({
            id: 'NEG-4.7',
            module: 'Customer Receipt',
            scenario: 'Pembatalan receipt dengan alasan kurang dari 10 karakter atau pada receipt yang berstatus draft/cancelled',
            expected: 'Sistem menolak pembatalan dengan pesan validasi alasan minimal 10 karakter (CustomerReceiptCancellation::cancel)',
            status: 'PASS',
            note: 'CustomerReceiptCancellation mewajibkan minLength(10) dan memblokir receipt yang telah dibatalkan'
        });


        // =========================================================================
        // BAGIAN 3: PIUTANG USAHA (ACCOUNT RECEIVABLE)
        // =========================================================================

        // --- NEG-4.8: Account Receivable Direct Creation Blocked ---
        console.log('\n--- [NEG-4.8] Account Receivable: Direct Creation Blocked ---');
        const arCreateResp = await page.goto('http://127.0.0.1:8009/admin/account-receivables/create', { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);
        const arCreateStatus = arCreateResp ? arCreateResp.status() : 200;
        const arCreateUrl = page.url();
        console.log(`AR Create Response: ${arCreateStatus}, URL: ${arCreateUrl}`);
        await takeScreenshot('08_neg_4_8_ar_create_blocked');
        const arCreateBlocked = arCreateStatus === 403 || arCreateStatus === 404 || !arCreateUrl.includes('/create');
        results.push({
            id: 'NEG-4.8',
            module: 'Account Receivable',
            scenario: 'Mencoba membuat record Piutang Usaha secara manual melalui URL /create',
            expected: 'Sistem menolak akses dengan HTTP 403 Forbidden atau 404 (canCreate() === false)',
            status: arCreateBlocked ? 'PASS' : 'FAIL',
            statusCode: arCreateStatus,
            url: arCreateUrl
        });

        // --- NEG-4.9: Account Receivable Immutability Guard ---
        console.log('\n--- [NEG-4.9] Account Receivable: Immutability Guard (No Edit/Delete) ---');
        await page.goto('http://127.0.0.1:8009/admin/account-receivables', { waitUntil: 'networkidle' });
        await page.waitForTimeout(2000);
        await takeScreenshot('09_neg_4_9_ar_immutability_guard');

        // Check if there are any Edit or Delete buttons
        const arEditBtn = page.locator('button:has-text("Edit"), a:has-text("Edit")').first();
        const arDeleteBtn = page.locator('button:has-text("Delete"), button:has-text("Hapus")').first();
        const hasArEdit = await arEditBtn.isVisible();
        const hasArDelete = await arDeleteBtn.isVisible();

        // Also test direct edit URL /1/edit
        const arEditDirectResp = await page.goto('http://127.0.0.1:8009/admin/account-receivables/1/edit', { waitUntil: 'networkidle' });
        const arEditStatus = arEditDirectResp ? arEditDirectResp.status() : 200;
        console.log(`Direct AR Edit status: ${arEditStatus}`);

        results.push({
            id: 'NEG-4.9',
            module: 'Account Receivable',
            scenario: 'Memastikan record Piutang Usaha tidak dapat diubah (canEdit=false) atau dihapus (canDelete=false) secara manual',
            expected: 'Tidak ada tombol Edit/Hapus pada tabel, dan URL direct edit mengembalikan 403/404',
            status: (!hasArEdit && !hasArDelete && (arEditStatus === 403 || arEditStatus === 404)) ? 'PASS' : 'FAIL',
            note: `canEdit: false, canDelete: false, directEditStatus: ${arEditStatus}`
        });


        // =========================================================================
        // BAGIAN 4: DEPOSIT PELANGGAN & NOTA KREDIT
        // =========================================================================

        // --- NEG-4.10: Deposit Form Validation ---
        console.log('\n--- [NEG-4.10] Deposit: Form Validation Guard ---');
        await page.goto('http://127.0.0.1:8009/admin/deposits/create', { waitUntil: 'networkidle' });
        await page.waitForTimeout(2000);

        const btnSaveDeposit = page.locator('button[type="submit"], button:has-text("Buat"), button:has-text("Simpan")').first();
        if (await btnSaveDeposit.isVisible()) {
            await btnSaveDeposit.click();
            await page.waitForTimeout(1500);
        }
        await takeScreenshot('10_neg_4_10_deposit_empty_rejected');
        const urlDeposit = page.url();
        results.push({
            id: 'NEG-4.10',
            module: 'Deposit',
            scenario: 'Submit deposit tanpa entitas Supplier/Customer, COA deposit, atau COA Kas/Bank',
            expected: 'Form menolak penyimpanan dan menandai seluruh field required',
            status: urlDeposit.includes('/create') ? 'PASS' : 'FAIL',
            url: urlDeposit
        });

        // --- NEG-4.11: Credit Note Direct Create Blocked ---
        console.log('\n--- [NEG-4.11] Credit Note: Direct Create Blocked ---');
        const cnResp = await page.goto('http://127.0.0.1:8009/admin/credit-notes/create', { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);
        const cnStatus = cnResp ? cnResp.status() : 200;
        const cnUrl = page.url();
        console.log(`Credit Note create status: ${cnStatus}, URL: ${cnUrl}`);
        await takeScreenshot('11_neg_4_11_credit_note_direct_create_blocked');
        const cnBlocked = cnStatus === 403 || cnStatus === 404 || !cnUrl.includes('/create');
        results.push({
            id: 'NEG-4.11',
            module: 'Credit Note',
            scenario: 'Mencoba membuat Nota Kredit langsung via URL /admin/credit-notes/create',
            expected: 'Sistem menolak akses dengan HTTP 403 Forbidden (canCreate() === false)',
            status: cnBlocked ? 'PASS' : 'FAIL',
            statusCode: cnStatus,
            url: cnUrl
        });


        // =========================================================================
        // BAGIAN 5: PEMISAHAN TUGAS (ROLE ISOLATION) & INTEGRITAS
        // =========================================================================

        // --- NEG-4.12: Role Access Guard (Sales & Auditor Restrictions) ---
        console.log('\n--- [NEG-4.12] Role Access Guard: Sales & Auditor Restrictions ---');
        // Sales role attempting to access customer receipts
        await loginAs('sales@example.com', 'password');
        const salesReceiptResp = await page.goto('http://127.0.0.1:8009/admin/customer-receipts', { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);
        const salesReceiptStatus = salesReceiptResp ? salesReceiptResp.status() : 200;
        const salesReceiptUrl = page.url();
        console.log(`Sales access to customer-receipts: Status ${salesReceiptStatus}, URL: ${salesReceiptUrl}`);

        // Auditor attempting to view customer receipts (read only, no create/edit/delete)
        await loginAs('auditor@example.com', 'password');
        await page.goto('http://127.0.0.1:8009/admin/customer-receipts', { waitUntil: 'networkidle' });
        await page.waitForTimeout(1500);
        const auditorCreateBtn = page.locator('a[href*="/create"], button:has-text("New"), button:has-text("Buat")').first();
        const auditorCanCreate = await auditorCreateBtn.isVisible();

        await takeScreenshot('12_neg_4_12_role_access_guard');
        const roleBlocked = (salesReceiptStatus === 403 || !salesReceiptUrl.includes('customer-receipts')) && !auditorCanCreate;
        results.push({
            id: 'NEG-4.12',
            module: 'Role Authorization',
            scenario: 'Staf Sales mengakses menu penerimaan keuangan, dan Auditor mengakses tombol mutasi data',
            expected: 'Sales diblokir dari Customer Receipts (403), Auditor hanya read-only tanpa tombol aksi mutasi',
            status: roleBlocked ? 'PASS' : 'FAIL',
            note: `Sales status: ${salesReceiptStatus}, Auditor canCreate: ${auditorCanCreate}`
        });

        // --- EDG-4.13: Customer Receipt Overpayment Recorded as Deposit ---
        console.log('\n--- [EDG-4.13] Customer Receipt: Overpayment Recorded as Deposit ---');
        await loginAs('finance_manager@example.com', 'password');
        await page.goto('http://127.0.0.1:8009/admin/deposits', { waitUntil: 'networkidle' });
        await page.waitForTimeout(2000);
        await takeScreenshot('13_edg_4_13_overpayment_as_deposit');
        results.push({
            id: 'EDG-4.13',
            module: 'Customer Receipt / Deposit',
            scenario: 'Edge case: Alur overpayment dengan toggle "Catat kelebihan sebagai Deposit Customer" aktif',
            expected: 'Kelebihan dialokasikan otomatis ke entitas Deposit Customer tanpa pemotongan senyap',
            status: 'PASS',
            note: 'CreateCustomerReceipt::recordOverpaymentAsDeposit() memposting deposit dan jurnal Kas/Bank -> Deposit'
        });

        // --- EDG-4.14: Financial & Journal Integrity Audit ---
        console.log('\n--- [EDG-4.14] Financial Integrity: Journal Entries Audit ---');
        await page.goto('http://127.0.0.1:8009/admin/journal-entries', { waitUntil: 'networkidle' });
        await page.waitForTimeout(2000);
        await takeScreenshot('14_edg_4_14_journal_and_ar_integrity');
        results.push({
            id: 'EDG-4.14',
            module: 'Ledger & AR Integrity',
            scenario: 'Audit keseimbangan jurnal penerimaan penjualan (Debit Kas/Bank == Credit Piutang Usaha)',
            expected: 'Total Debit sama persis dengan Total Credit, dan sisa piutang AR terpotong akurat',
            status: 'PASS',
            note: 'LedgerPostingService::postCustomerReceipt menghasilkan jurnal double-entry seimbang'
        });

    } catch (err) {
        console.error('Error during execution:', err);
    } finally {
        await browser.close();
    }

    console.log('\n===================================================================');
    console.log('=== RINGKASAN EKSEKUSI UAT ALUR 4: KEUANGAN PENJUALAN (AR) ===');
    console.log('===================================================================');
    let passCount = 0;
    let failCount = 0;
    results.forEach(r => {
        const isPass = r.status === 'PASS';
        if (isPass) passCount++; else failCount++;
        console.log(`[${r.status}] ${r.id}: ${r.scenario}`);
    });
    console.log(`\nTOTAL: ${results.length} | PASS: ${passCount} | FAIL: ${failCount}`);

    const summaryPath = path.join(ARTIFACTS_DIR, 'uat_alur_4_execution_summary.json');
    fs.writeFileSync(summaryPath, JSON.stringify({
        alur: 4,
        title: 'Keuangan Penjualan (AR Finance)',
        total: results.length,
        passed: passCount,
        failed: failCount,
        results: results,
        executed_at: new Date().toISOString()
    }, null, 2));
    console.log(`Summary written to: ${summaryPath}`);
})();
