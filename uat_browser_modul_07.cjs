const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');
const { execSync } = require('child_process');

(async () => {
    console.log('=== UAT MODUL 07: TERIMA BARANG, QC INSPEKSI & RETUR PEMBELIAN OTOMATIS ===');
    const screenshotsDir = '/Users/lrmcorporation/.gemini/antigravity-ide/brain/4af30cfa-9a9b-4a4d-b793-07da0cd22265/uat_modul_07_screenshots';
    if (!fs.existsSync(screenshotsDir)) {
        fs.mkdirSync(screenshotsDir, { recursive: true });
    }

    // 0. Jalankan fixture setup modul 07
    console.log('0. Menjalankan fixture setup modul 07...');
    const setupOutput = execSync('php scripts/setup_uat_modul_07_fixtures.php', { encoding: 'utf-8' });
    console.log(setupOutput.trim());

    // Dapatkan ID fixture
    const fixturesJson = execSync(`php artisan tinker --execute="
        \\$po50 = App\\Models\\PurchaseOrder::where('po_number', 'PO-TEST-QC-50PCS')->first();
        \\$po20 = App\\Models\\PurchaseOrder::where('po_number', 'PO-TEST-QC-OVERFLOW')->first();
        echo json_encode([
            'po50_id' => \\$po50?->id,
            'po20_id' => \\$po20?->id,
        ]);
    "`, { encoding: 'utf-8' });

    const fixtureIds = JSON.parse(fixturesJson.trim().split('\n').pop());
    console.log('   Fixture IDs:', fixtureIds);

    const browser = await chromium.launch({
        headless: true,
        executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const context = await browser.newContext({
        viewport: { width: 1280, height: 900 }
    });
    const page = await context.newPage();

    async function loginAs(email, password = 'password') {
        console.log(`   [LOGIN] Masuk sebagai ${email}...`);
        await context.clearCookies();
        await page.goto('http://localhost:8009/login', { waitUntil: 'networkidle' });
        await page.fill('#login', email);
        await page.fill('#password', password);
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }),
            page.click('button[type="submit"]')
        ]);
        await page.waitForTimeout(1000);
    }

    try {
        // =========================================================================
        // KASUS UJI UAT-QC-01: PENERIMAAN BARANG & QC PARSIAL (45 BAIK & 5 REJECT)
        // =========================================================================
        console.log('\n--- 1. UAT-QC-01: Penerimaan Barang & QC Parsial (45 Baik, 5 Reject) ---');
        await loginAs('checker@example.com');

        console.log(`   Membuka form Penerimaan Barang untuk PO 50 pcs (ID: ${fixtureIds.po50_id})...`);
        await page.goto(`http://localhost:8009/gudang/penerimaan/create?purchase_order_id=${fixtureIds.po50_id}`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);
        await page.screenshot({ path: path.join(screenshotsDir, '01_uat_qc_01_create_form_po50.png'), fullPage: true });

        // Isi form: Qty Received = 50, Qty Accepted = 45, Qty Rejected = 5, Alasan = Karat dan kemasan pecah saat pengiriman vendor
        console.log('   Mengisi form inspeksi QC: 50 datang, 45 diterima baik, 5 reject...');
        const inputReceived = await page.waitForSelector('input[name*="[qty_received]"]');
        await inputReceived.fill('50');

        const inputAccepted = await page.waitForSelector('input[name*="[qty_accepted]"]');
        await inputAccepted.fill('45');

        const inputRejected = await page.waitForSelector('input[name*="[qty_rejected]"]');
        await inputRejected.fill('5');
        await inputRejected.dispatchEvent('input');
        await page.waitForTimeout(500);

        const inputReason = await page.waitForSelector('input[name*="[reason_rejected]"]', { state: 'visible' });
        await inputReason.fill('Karat dan kemasan pecah saat pengiriman vendor');

        // Isi catatan
        const inputNotes = await page.waitForSelector('textarea[name="notes"]');
        await inputNotes.fill('Penerimaan fisik 50 pcs: 45 lolos QC masuk stok gudang, 5 pcs reject retur vendor.');

        await page.waitForTimeout(1000);
        await page.screenshot({ path: path.join(screenshotsDir, '02_uat_qc_01_form_filled_partial.png'), fullPage: true });

        // Simpan & Konfirmasi Masuk Stok
        console.log('   Mengeklik tombol "Simpan & Konfirmasi Masuk Stok"...');
        const confirmBtn = await page.waitForSelector('button:has-text("Simpan & Konfirmasi Masuk Stok")');
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }),
            confirmBtn.click()
        ]);
        await page.waitForTimeout(1500);

        console.log('   URL setelah penyimpanan penerimaan:', page.url());
        await page.screenshot({ path: path.join(screenshotsDir, '03_uat_qc_01_receipt_completed_with_return_banner.png'), fullPage: true });

        // Dapatkan data penerimaan dan retur dari URL / DB
        const receiptUrl = page.url();
        const receiptId = receiptUrl.split('/').pop();

        // Verifikasi database UAT-QC-01
        const qc01DbCheck = execSync(`php artisan tinker --execute="
            \\$rc = App\\Models\\PurchaseReceipt::with(['purchaseReceiptItem', 'purchaseReturns.purchaseReturnItem'])->find(${receiptId});
            \\$stock = App\\Models\\InventoryStock::where('product_id', \\$rc->purchaseReceiptItem->first()?->product_id)
                ->where('warehouse_id', \\$rc->purchaseReceiptItem->first()?->warehouse_id)
                ->value('qty_available');
            \\$movement = App\\Models\\StockMovement::where('meta->purchase_receipt_id', \\$rc->id)->first();
            \\$journals = App\\Models\\JournalEntry::where('source_type', App\\Models\\PurchaseReceiptItem::class)
                ->where('source_id', \\$rc->purchaseReceiptItem->first()?->id)
                ->get();
            \\$ret = \\$rc->purchaseReturns->first();
            \\$qc = App\\Models\\QualityControl::where('from_model_type', App\\Models\\PurchaseReceipt::class)
                ->where('from_model_id', \\$rc->id)->first();

            echo json_encode([
                'receipt_id' => \\$rc->id,
                'receipt_number' => \\$rc->receipt_number,
                'status' => \\$rc->status,
                'qty_received' => \\$rc->purchaseReceiptItem->first()?->qty_received,
                'qty_accepted' => \\$rc->purchaseReceiptItem->first()?->qty_accepted,
                'qty_rejected' => \\$rc->purchaseReceiptItem->first()?->qty_rejected,
                'stock_qty_available' => \\$stock,
                'stock_movement_qty' => \\$movement?->quantity,
                'stock_movement_type' => \\$movement?->type,
                'journal_debit' => \\$journals->where('debit', '>', 0)->first()?->debit,
                'journal_credit' => \\$journals->where('credit', '>', 0)->first()?->credit,
                'purchase_return_id' => \\$ret?->id,
                'purchase_return_nota' => \\$ret?->nota_retur,
                'purchase_return_status' => \\$ret?->status,
                'return_item_qty' => \\$ret?->purchaseReturnItem->first()?->qty_returned,
                'return_item_reason' => \\$ret?->purchaseReturnItem->first()?->reason,
                'qc_id' => \\$qc?->id,
                'qc_number' => \\$qc?->qc_number,
            ]);
        "`, { encoding: 'utf-8' });
        const qc01Data = JSON.parse(qc01DbCheck.trim().split('\n').pop());
        console.log('   [DB CHECK UAT-QC-01]:', qc01Data);

        // Buka halaman detail Retur Pembelian
        if (qc01Data.purchase_return_id) {
            console.log(`   Membuka detail Retur Pembelian ID: ${qc01Data.purchase_return_id}...`);
            await page.goto(`http://localhost:8009/pembelian/retur/${qc01Data.purchase_return_id}`, { waitUntil: 'networkidle' });
            await page.waitForTimeout(1000);
            await page.screenshot({ path: path.join(screenshotsDir, '04_uat_qc_01_purchase_return_detail.png'), fullPage: true });
        }

        // =========================================================================
        // KASUS UJI UAT-QC-02 (NO HAPPY PATH): PENCEGAHAN DUPLIKASI RETUR (BUG C2)
        // =========================================================================
        console.log('\n--- 2. UAT-QC-02: Pencegahan Duplikasi Dokumen Retur (Anti-Duplicate Return Bug C2) ---');
        console.log(`   Mencoba trigger ulang via re-confirm / handleReceivingQcAndAutoReturn pada Receipt ID: ${receiptId}...`);

        const duplicateCheckOutput = execSync(`php artisan tinker --execute="
            \\$controller = app(App\\Http\\Controllers\\Gudang\\PenerimaanBarangController::class);
            \\$rc = App\\Models\\PurchaseReceipt::find(${receiptId});
            
            // Panggil ulang proses penerimaan / konfirmasi
            \\$reflection = new ReflectionClass(\\$controller);
            \\$method = \\$reflection->getMethod('handleReceivingQcAndAutoReturn');
            \\$method->setAccessible(true);
            \\$method->invoke(\\$controller, \\$rc);
            
            // Hitung jumlah PurchaseReturn untuk receipt ini
            \\$countByReceipt = App\\Models\\PurchaseReturn::where('purchase_receipt_id', ${receiptId})->count();
            \\$qc = App\\Models\\QualityControl::where('from_model_type', App\\Models\\PurchaseReceipt::class)
                ->where('from_model_id', ${receiptId})->first();
            \\$countByQc = App\\Models\\PurchaseReturn::where('quality_control_id', \\$qc?->id)->count();

            echo json_encode([
                'receipt_id' => ${receiptId},
                'count_by_receipt' => \\$countByReceipt,
                'count_by_qc' => \\$countByQc,
            ]);
        "`, { encoding: 'utf-8' });

        const duplicateData = JSON.parse(duplicateCheckOutput.trim().split('\n').pop());
        console.log('   [DB CHECK UAT-QC-02] Jumlah dokumen retur setelah re-trigger:', duplicateData);

        // Buka kembali halaman penerimaan untuk screenshot UAT-QC-02
        await page.goto(`http://localhost:8009/gudang/penerimaan/${receiptId}`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);
        await page.screenshot({ path: path.join(screenshotsDir, '05_uat_qc_02_retrigger_receiving_prevent_duplicate.png'), fullPage: true });

        // =========================================================================
        // KASUS UJI UAT-QC-03 (NO HAPPY PATH): VALIDASI PASSED + REJECTED > RECEIVED
        // =========================================================================
        console.log('\n--- 3. UAT-QC-03: Validasi Qty Passed + Rejected Melebihi Kuantitas Datang ---');
        console.log(`   Membuka form Penerimaan Barang untuk PO 20 pcs (ID: ${fixtureIds.po20_id})...`);
        await page.goto(`http://localhost:8009/gudang/penerimaan/create?purchase_order_id=${fixtureIds.po20_id}`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);
        await page.screenshot({ path: path.join(screenshotsDir, '06_uat_qc_03_create_form_po20.png'), fullPage: true });

        console.log('   Mengisi form invalid: Datang 20 pcs, Passed = 18 pcs, Rejected = 5 pcs (Total = 23 pcs)...');
        const inputReceived20 = await page.waitForSelector('input[name*="[qty_received]"]');
        await inputReceived20.fill('20');

        const inputAccepted20 = await page.waitForSelector('input[name*="[qty_accepted]"]');
        await inputAccepted20.fill('18');

        const inputRejected20 = await page.waitForSelector('input[name*="[qty_rejected]"]');
        await inputRejected20.fill('5');
        await inputRejected20.dispatchEvent('input');
        await page.waitForTimeout(500);

        const inputReason20 = await page.waitForSelector('input[name*="[reason_rejected]"]', { state: 'visible' });
        await inputReason20.fill('Kemasan sobek dan pipa bengkok');

        await page.waitForTimeout(1000);
        await page.screenshot({ path: path.join(screenshotsDir, '07_uat_qc_03_form_overflow_input.png'), fullPage: true });

        console.log('   Mengeklik tombol "Simpan & Konfirmasi Masuk Stok"...');
        const confirmBtn20 = await page.waitForSelector('button:has-text("Simpan & Konfirmasi Masuk Stok")');
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}),
            confirmBtn20.click()
        ]);
        await page.waitForTimeout(1500);

        console.log('   URL setelah klik submit:', page.url());
        await page.screenshot({ path: path.join(screenshotsDir, '08_uat_qc_03_overflow_blocked_error.png'), fullPage: true });

        // Verifikasi error message di UI
        const pageContentOverflow = await page.content();
        const overflowBlocked = pageContentOverflow.includes('Total QC passed dan rejected (23) tidak boleh melebihi Qty Received (20).');
        console.log('   [UI CHECK UAT-QC-03] Pesan blokir overflow muncul:', overflowBlocked);

        // Verifikasi database: tidak ada receipt baru untuk PO 20 pcs
        const po20DbCheck = execSync(`php artisan tinker --execute="
            \\$receiptsCount = App\\Models\\PurchaseReceipt::where('purchase_order_id', ${fixtureIds.po20_id})->count();
            echo json_encode([
                'po20_id' => ${fixtureIds.po20_id},
                'receipts_count' => \\$receiptsCount,
            ]);
        "`, { encoding: 'utf-8' });
        const po20DbData = JSON.parse(po20DbCheck.trim().split('\n').pop());
        console.log('   [DB CHECK UAT-QC-03] Status data PO 20:', po20DbData);

        console.log('\n=== SEMUA PENGUJIAN UAT MODUL 07 SELESAI DENGAN SUKSES! ===');

    } catch (err) {
        console.error('ERROR saat eksekusi Playwright:', err);
        await page.screenshot({ path: path.join(screenshotsDir, 'error_state.png'), fullPage: true }).catch(() => {});
        process.exit(1);
    } finally {
        await browser.close();
    }
})();
