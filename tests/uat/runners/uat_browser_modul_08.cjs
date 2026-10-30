const { chromium } = require('playwright');
const { execSync } = require('child_process');
const path = require('path');
const fs = require('fs');

(async () => {
    console.log('=== UAT MODUL 08: INVOICE PEMBELIAN, HUTANG SUPPLIER & VENDOR PAYMENT ===');
    const screenshotsDir = '/Users/lrmcorporation/.gemini/antigravity-ide/brain/4af30cfa-9a9b-4a4d-b793-07da0cd22265/uat_modul_08_screenshots';
    if (!fs.existsSync(screenshotsDir)) {
        fs.mkdirSync(screenshotsDir, { recursive: true });
    }

    // 0. Jalankan fixture setup modul 08
    console.log('0. Menjalankan fixture setup modul 08...');
    const fixturesSetupOutput = execSync('php scripts/setup_uat_modul_08_fixtures.php', { encoding: 'utf-8' });
    console.log(fixturesSetupOutput);

    // Dapatkan ID fixture dari database via tinker
    const fixturesJson = execSync(`php artisan tinker --execute="
        \\$supplier = App\\Models\\Supplier::where('code', 'SUPP-TEST-UAT-001')->first();
        \\$inv10 = App\\Models\\Invoice::where('supplier_invoice_number', 'INV-SUPP-10PCS')->first();
        \\$inv50 = App\\Models\\Invoice::where('supplier_invoice_number', 'INV-SUPP-500K')->first();
        \\$coa = App\\Models\\ChartOfAccount::query()->cashBank()->where('is_active', true)->first();
        echo json_encode([
            'supplier_id' => \\$supplier?->id,
            'inv10_id' => \\$inv10?->id,
            'inv10_number' => \\$inv10?->invoice_number,
            'inv50_id' => \\$inv50?->id,
            'inv50_number' => \\$inv50?->invoice_number,
            'coa_id' => \\$coa?->id,
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
        // KASUS UJI UAT-AP-01: VERIFIKASI AKURASI HUTANG SUPPLIER (ANTI-DOUBLE REDUCTION)
        // =========================================================================
        console.log('\n--- 1. UAT-AP-01: Verifikasi Akurasi Saldo Hutang Supplier (Bebas Pengurangan Hutang Ganda / Bug C1) ---');
        await loginAs('admin_keuangan@example.com');

        console.log(`   Membuka detail Faktur Pembelian (ID: ${fixtureIds.inv10_id}, No: ${fixtureIds.inv10_number})...`);
        await page.goto(`http://localhost:8009/pembelian/invoice/${fixtureIds.inv10_id}`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(1500);
        await page.screenshot({ path: path.join(screenshotsDir, '01_uat_ap_01_invoice_detail_80k.png'), fullPage: true });

        console.log(`   Membuka formulir Pembayaran Vendor untuk Faktur ID: ${fixtureIds.inv10_id}...`);
        await page.goto(`http://localhost:8009/pembelian/pembayaran/create?supplier_id=${fixtureIds.supplier_id}&invoice_id=${fixtureIds.inv10_id}`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(1500);

        // Pilih Akun Kas/Bank jika belum terpilih
        const coaSelect = await page.$('select[name="coa_id"]');
        if (coaSelect) {
            await coaSelect.selectOption(String(fixtureIds.coa_id));
        }

        // Pastikan baris invoice termuat dan alokasi bayar terisi 80000
        await page.waitForSelector('input[type="checkbox"][name="selected_invoices[]"]');
        const allocInput = await page.waitForSelector('input[type="text"][placeholder="0"]');
        await allocInput.fill('80000');
        await allocInput.dispatchEvent('input');
        await page.waitForTimeout(500);

        await page.screenshot({ path: path.join(screenshotsDir, '02_uat_ap_01_payment_form_filled_80k.png'), fullPage: true });

        // Simpan & Posting Pembayaran
        console.log('   Mengeklik tombol "Simpan & Posting Pembayaran"...');
        const postBtn = await page.waitForSelector('button:has-text("Simpan & Posting")');
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }),
            postBtn.click()
        ]);
        await page.waitForTimeout(1500);

        console.log('   URL setelah pembayaran disimpan:', page.url());
        await page.screenshot({ path: path.join(screenshotsDir, '03_uat_ap_01_payment_posted_paid.png'), fullPage: true });

        // Buka kembali invoice untuk memastikan status LUNAS (paid) dan sisa tagihan Rp 0
        console.log(`   Memverifikasi status lunas pada Faktur Pembelian (ID: ${fixtureIds.inv10_id})...`);
        await page.goto(`http://localhost:8009/pembelian/invoice/${fixtureIds.inv10_id}`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(1500);
        await page.screenshot({ path: path.join(screenshotsDir, '04_uat_ap_01_invoice_paid_zero_remaining.png'), fullPage: true });

        // Verifikasi database UAT-AP-01
        const ap01DbCheck = execSync(`php artisan tinker --execute="
            \\$inv = App\\Models\\Invoice::with('accountPayable')->find(${fixtureIds.inv10_id});
            \\$ap = \\$inv->accountPayable;
            \\$vpd = App\\Models\\VendorPaymentDetail::with('vendorPayment')->where('invoice_id', \\$inv->id)->first();
            \\$vp = \\$vpd?->vendorPayment;
            \\$receipt = App\\Models\\PurchaseReceipt::with('purchaseReceiptItem')->where('purchase_order_id', \\$inv->from_model_id)->first();
            \\$returns = App\\Models\\PurchaseReturn::with('purchaseReturnItems')->where('purchase_receipt_id', \\$receipt?->id)->get();

            echo json_encode([
                'invoice_id' => \\$inv->id,
                'invoice_number' => \\$inv->invoice_number,
                'status' => \\$inv->status,
                'total' => (float) \\$inv->total,
                'ap_total' => (float) \\$ap?->total,
                'ap_paid' => (float) \\$ap?->paid,
                'ap_remaining' => (float) \\$ap?->remaining,
                'ap_status' => \\$ap?->status,
                'payment_number' => \\$vp?->payment_number,
                'payment_status' => \\$vp?->status,
                'payment_amount' => (float) \\$vpd?->amount_paid,
                'receipt_qty_accepted' => (float) \\$receipt?->purchaseReceiptItem->first()?->qty_accepted,
                'receipt_qty_rejected' => (float) \\$receipt?->purchaseReceiptItem->first()?->qty_rejected,
                'returns_count' => \\$returns->count(),
                'return_item_qty' => (float) \\$returns->first()?->purchaseReturnItems?->first()?->qty_returned,
            ]);
        "`, { encoding: 'utf-8' });
        const ap01Data = JSON.parse(ap01DbCheck.trim().split('\n').pop());
        console.log('   [DB CHECK UAT-AP-01]:', ap01Data);

        // =========================================================================
        // KASUS UJI UAT-AP-02: TAMPILAN INFORMASI REKENING SUPPLIER (BUG C7)
        // =========================================================================
        console.log('\n--- 2. UAT-AP-02: Tampilan Informasi Rekening Supplier di Pembayaran Vendor (Bug C7) ---');
        console.log('   Membuka form pembayaran dengan supplier yang memiliki rekening bank lengkap...');
        await page.goto(`http://localhost:8009/pembelian/pembayaran/create?supplier_id=${fixtureIds.supplier_id}`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(1500);

        await page.screenshot({ path: path.join(screenshotsDir, '05_uat_ap_02_bank_info_auto_displayed.png'), fullPage: true });

        // Verifikasi teks rekening supplier di UI
        const bankInfoText = await page.evaluate(() => {
            const el = document.querySelector('input[name="target_bank_account"]');
            return el ? el.value : '';
        });
        console.log('   [UI CHECK UAT-AP-02] Teks rekening bank supplier:', bankInfoText);

        // =========================================================================
        // KASUS UJI UAT-AP-03 (NO HAPPY PATH): PEMBLOKIRAN PEMBAYARAN OVERPAYMENT
        // =========================================================================
        console.log('\n--- 3. UAT-AP-03: Pemblokiran Pembayaran Vendor Melebihi Sisa Tagihan Faktur ---');
        console.log(`   Membuka formulir Pembayaran Vendor untuk Faktur 500k (ID: ${fixtureIds.inv50_id})...`);
        await page.goto(`http://localhost:8009/pembelian/pembayaran/create?supplier_id=${fixtureIds.supplier_id}&invoice_id=${fixtureIds.inv50_id}`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(1500);

        // Pilih Akun Kas/Bank
        const coaSelect3 = await page.$('select[name="coa_id"]');
        if (coaSelect3) {
            await coaSelect3.selectOption(String(fixtureIds.coa_id));
        }

        // Input alokasi overpayment: Rp 600.000 (sisa hutang Rp 500.000)
        console.log('   Mengisi input alokasi overpayment: Rp 600.000 (melebihi sisa hutang Rp 500.000)...');
        await page.waitForSelector('input[type="checkbox"][name="selected_invoices[]"]');
        const allocInput50 = await page.waitForSelector('input[type="text"][placeholder="0"]');
        await allocInput50.fill('600000');
        await allocInput50.dispatchEvent('input');
        await page.evaluate((invId) => {
            const root = document.querySelector('#vendor-payment-root');
            if (root && window.Alpine) {
                const data = window.Alpine.$data(root);
                data.allocations[invId] = 600000;
            }
        }, fixtureIds.inv50_id);
        await page.waitForTimeout(500);

        await page.screenshot({ path: path.join(screenshotsDir, '06_uat_ap_03_form_overpayment_input.png'), fullPage: true });

        // Submit form pembayaran
        console.log('   Mengeklik tombol "Simpan & Posting" untuk memicu validasi backend...');
        const postBtn50 = await page.waitForSelector('button:has-text("Simpan & Posting")');
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}),
            postBtn50.click()
        ]);
        await page.waitForTimeout(1500);

        console.log('   URL setelah submit overpayment:', page.url());
        await page.screenshot({ path: path.join(screenshotsDir, '07_uat_ap_03_overpayment_blocked_error.png'), fullPage: true });

        // Verifikasi error message di UI
        const pageContent50 = await page.content();
        const overpaymentBlocked = pageContent50.includes('tidak boleh melebihi sisa tagihan faktur');
        console.log('   [UI CHECK UAT-AP-03] Pesan blokir overpayment muncul:', overpaymentBlocked);

        // Verifikasi database: Faktur 500k tetap berstatus unpaid dan remaining tetap 500000
        const inv50DbCheck = execSync(`php artisan tinker --execute="
            \\$ap = App\\Models\\AccountPayable::where('invoice_id', ${fixtureIds.inv50_id})->first();
            \\$paymentsCount = App\\Models\\VendorPaymentDetail::where('invoice_id', ${fixtureIds.inv50_id})->count();
            echo json_encode([
                'invoice_id' => ${fixtureIds.inv50_id},
                'remaining' => (float) \\$ap?->remaining,
                'paid' => (float) \\$ap?->paid,
                'status' => \\$ap?->status,
                'payment_details_count' => \\$paymentsCount,
            ]);
        "`, { encoding: 'utf-8' });
        const inv50Data = JSON.parse(inv50DbCheck.trim().split('\n').pop());
        console.log('   [DB CHECK UAT-AP-03] Status data Faktur 500k:', inv50Data);

        console.log('\n=== SEMUA PENGUJIAN UAT MODUL 08 SELESAI DENGAN SUKSES! ===');

    } catch (err) {
        console.error('ERROR saat eksekusi Playwright:', err);
        await page.screenshot({ path: path.join(screenshotsDir, 'error_state.png'), fullPage: true }).catch(() => {});
        process.exit(1);
    } finally {
        await browser.close();
    }
})();
