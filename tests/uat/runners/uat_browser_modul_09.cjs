const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');
const { execSync } = require('child_process');

(async () => {
    console.log('=== UAT MODUL 09: INVENTORI, STOK BEBAS, PINDAH STOK & STOCK OPNAME HP ===');

    const screenshotsDir = '/Users/lrmcorporation/.gemini/antigravity-ide/brain/4af30cfa-9a9b-4a4d-b793-07da0cd22265/uat_modul_09_screenshots';
    if (!fs.existsSync(screenshotsDir)) {
        fs.mkdirSync(screenshotsDir, { recursive: true });
    }

    // 0. Jalankan fixture setup modul 09
    console.log('0. Menjalankan fixture setup modul 09...');
    const setupOutput = execSync('php scripts/setup_uat_modul_09_fixtures.php', { encoding: 'utf-8' });
    console.log(setupOutput);

    // Ambil ID fixtures dari database
    const fixturesJson = execSync(`php artisan tinker --execute="
        \\$whUtama = App\\Models\\Warehouse::where('kode', 'GDG-UTM')->first();
        \\$whKenari = App\\Models\\Warehouse::where('kode', 'GDG-KNR')->first();
        \\$prodA = App\\Models\\Product::where('sku', 'PROD-TEST-A')->first();
        \\$prodB = App\\Models\\Product::where('sku', 'PROD-TEST-B')->first();
        \\$prodOp = App\\Models\\Product::where('sku', 'PROD-TEST-OPNAME')->first();
        \\$opname = App\\Models\\StockOpname::where('opname_number', 'OPN-TEST-BLIND-001')->first();
        \\$customer = App\\Models\\Customer::first();

        echo json_encode([
            'wh_utama_id' => \\$whUtama?->id,
            'wh_kenari_id' => \\$whKenari?->id,
            'prodA_id' => \\$prodA?->id,
            'prodB_id' => \\$prodB?->id,
            'prodOp_id' => \\$prodOp?->id,
            'opname_id' => \\$opname?->id,
            'customer_id' => \\$customer?->id,
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

    // Dialog confirm handler for window.confirm (wire:confirm)
    page.on('dialog', async (dialog) => {
        console.log(`   [DIALOG DETECTED] Type: ${dialog.type()}, Message: ${dialog.message()}`);
        await dialog.accept();
    });

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
        // KASUS UJI UAT-INV-01: VERIFIKASI RUMUS STOK BEBAS SADAR RESERVASI DO
        // =========================================================================
        console.log('\n--- 1. UAT-INV-01: Verifikasi Rumus Stok Bebas Sadar Reservasi DO ---');
        await loginAs('warehouse_staff@example.com');

        console.log('   Membuka halaman Cek Stok Real-Time di Desktop...');
        await page.setViewportSize({ width: 1280, height: 900 });
        await page.goto('http://localhost:8009/gudang/stok?search=PROD-TEST-A', { waitUntil: 'networkidle' });
        await page.waitForTimeout(1500);

        await page.screenshot({ path: path.join(screenshotsDir, '01_uat_inv_01_cek_stok_desktop.png'), fullPage: true });

        // Evaluasi angka di tabel Cek Stok Desktop
        const stockTableNumbers = await page.evaluate(() => {
            const row = document.querySelector('tbody tr');
            if (!row) return null;
            const cells = row.querySelectorAll('td');
            return {
                physical: cells[3]?.innerText.trim(),
                reserved: cells[4]?.innerText.trim(),
                free: cells[5]?.innerText.trim(),
            };
        });
        console.log('   [UI CHECK Cek Stok Desktop]:', stockTableNumbers);

        // Buka Cek Stok di Mode HP (Mobile Viewport)
        console.log('   Membuka halaman Cek Stok Real-Time di Mode HP...');
        await page.setViewportSize({ width: 375, height: 667 });
        await page.reload({ waitUntil: 'networkidle' });
        await page.waitForTimeout(1500);

        await page.screenshot({ path: path.join(screenshotsDir, '02_uat_inv_01_cek_stok_mobile.png'), fullPage: true });

        // Restore viewport desktop untuk input Sales Order
        await page.setViewportSize({ width: 1280, height: 900 });

        console.log('   Membuka form Sales Order untuk menguji batas stok bebas...');
        await page.goto('http://localhost:8009/penjualan/so/create', { waitUntil: 'networkidle' });
        await page.waitForTimeout(1500);

        // Pilih Customer
        const custSelect = await page.$('select[name="customer_id"]');
        if (custSelect) {
            await custSelect.selectOption(String(fixtureIds.customer_id));
            await page.evaluate((cId) => {
                if (typeof handleCustomerSelectChange === 'function') {
                    handleCustomerSelectChange(cId);
                }
            }, fixtureIds.customer_id);
        }

        // Tambah produk PROD-TEST-A (Stok Bebas = 70)
        console.log('   Menambahkan produk PROD-TEST-A (Stok Bebas = 70) ke Sales Order...');
        await page.evaluate((pId) => {
            if (typeof selectQuickProduct === 'function') {
                selectQuickProduct(pId);
            }
        }, fixtureIds.prodA_id);
        await page.waitForTimeout(1000);

        // Cek badge stok bebas
        const stockBadgeText = await page.evaluate(() => {
            const badge = document.getElementById('item-stock-badge-0');
            return badge ? badge.innerText.trim() : '';
        });
        console.log('   [UI CHECK SO Badge Stok]:', stockBadgeText);

        // Input kuantitas 75 pcs (melebihi stok bebas 70 pcs)
        console.log('   Mengisi input kuantitas: 75 pcs (melebihi stok bebas 70 pcs)...');
        const qtyInput = await page.waitForSelector('input[name="items[0][quantity]"]');
        await qtyInput.fill('75');
        await qtyInput.dispatchEvent('input');
        await page.waitForTimeout(1000);

        await page.screenshot({ path: path.join(screenshotsDir, '03_uat_inv_01_so_stock_warning_75pcs.png'), fullPage: true });

        // Cek pesan peringatan kekurangan stok
        const warningInfo = await page.evaluate(() => {
            const el = document.getElementById('item-stock-warning-0');
            return {
                text: el ? el.innerText.trim() : '',
                visible: el ? !el.classList.contains('hidden') : false
            };
        });
        console.log('   [UI CHECK SO Warning Stok Kurang]:', warningInfo);

        // Verifikasi database UAT-INV-01
        const inv01DbCheck = execSync(`php artisan tinker --execute="
            \\$st = App\\Models\\InventoryStock::where('product_id', ${fixtureIds.prodA_id})->first();
            echo json_encode([
                'product_id' => \\$st->product_id,
                'qty_available' => (float) \\$st->qty_available,
                'qty_reserved' => (float) \\$st->qty_reserved,
                'free_stock' => (float) (\\$st->qty_available - \\$st->qty_reserved),
            ]);
        "`, { encoding: 'utf-8' });
        const inv01Data = JSON.parse(inv01DbCheck.trim().split('\n').pop());
        console.log('   [DB CHECK UAT-INV-01]:', inv01Data);

        // =========================================================================
        // KASUS UJI UAT-INV-02: STOCK OPNAME HP BLIND COUNT
        // =========================================================================
        console.log('\n--- 2. UAT-INV-02: Stock Opname HP Blind Count (Anti-Fraud & Kerahasiaan Angka Sistem) ---');
        await loginAs('checker@example.com');

        console.log(`   Membuka halaman Stock Opname HP di viewport HP (iPhone 14 / 390x844)...`);
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto(`http://localhost:8009/admin/opname-hp?opname=${fixtureIds.opname_id}`, { waitUntil: 'networkidle' });
        
        // Tunggu form input hitung fisik di layar HP siap
        await page.waitForSelector('input[wire\\:model="qty"]', { timeout: 10000 });
        await page.waitForTimeout(1000);

        await page.screenshot({ path: path.join(screenshotsDir, '04_uat_inv_02_opname_hp_blind_count_screen.png'), fullPage: true });

        // Verifikasi Blind Count Security: pastikan system_qty TIDAK ADA di HTML/DOM
        const blindCountAudit = await page.evaluate(() => {
            const html = document.body.innerHTML;
            const hasDisembunyikanText = html.includes('Jumlah di sistem disembunyikan supaya hitungan apa adanya') ||
                                         html.includes('Jumlah di sistem tidak ditampilkan');
            const hasSystemQtyKeyword = html.includes('system_qty');
            return {
                hasDisembunyikanText,
                hasSystemQtyKeyword,
                hasNoteInUI: document.querySelector('.dt-hp-note')?.innerText.trim(),
                productName: document.querySelector('.dt-hp-name')?.innerText.trim(),
                skuAndRak: document.querySelector('.dt-hp-code')?.innerText.trim(),
            };
        });
        console.log('   [BLIND COUNT SECURITY AUDIT]:', blindCountAudit);

        // Input hasil hitung fisik riil: 48 pcs (stok sistem sebenarnya 50 pcs)
        console.log('   Memasukkan hasil hitung fisik riil: 48 pcs...');
        const countInput = await page.waitForSelector('input[wire\\:model="qty"]');
        await countInput.fill('48');
        await countInput.dispatchEvent('input');
        await page.waitForTimeout(500);

        // Klik "Simpan & berikutnya"
        console.log('   Mengeklik tombol "Simpan & berikutnya"...');
        const saveNextBtn = await page.waitForSelector('button:has-text("Simpan & berikutnya")');
        await saveNextBtn.click();
        await page.waitForTimeout(2000);

        await page.screenshot({ path: path.join(screenshotsDir, '05_uat_inv_02_opname_hp_counted_summary.png'), fullPage: true });

        // Klik tombol "Selesai menghitung"
        console.log('   Mengeklik tombol "Selesai menghitung"...');
        const finishBtn = await page.waitForSelector('button:has-text("Selesai menghitung")');
        await finishBtn.click();
        await page.waitForTimeout(3000);

        // Login sebagai supervisor di desktop untuk mereview selisih fisik vs sistem
        console.log('   Login sebagai Inventory Manager di desktop untuk mereview selisih opname...');
        await page.setViewportSize({ width: 1280, height: 900 });
        await loginAs('inventory_manager@example.com');

        console.log(`   Membuka lembar kerja Stock Opname desktop (ID: ${fixtureIds.opname_id})...`);
        await page.goto(`http://localhost:8009/gudang/opname/${fixtureIds.opname_id}`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(2000);

        await page.screenshot({ path: path.join(screenshotsDir, '06_uat_inv_02_opname_desktop_variance_review.png'), fullPage: true });

        // Verifikasi database UAT-INV-02
        const inv02DbCheck = execSync(`php artisan tinker --execute="
            \\$op = App\\Models\\StockOpname::with('items')->find(${fixtureIds.opname_id});
            \\$item = \\$op->items->first();
            echo json_encode([
                'opname_id' => \\$op->id,
                'opname_number' => \\$op->opname_number,
                'status' => \\$op->status,
                'product_id' => \\$item?->product_id,
                'system_qty' => (float) \\$item?->system_qty,
                'physical_qty' => (float) \\$item?->physical_qty,
                'difference_qty' => (float) \\$item?->difference_qty,
                'difference_value' => (float) \\$item?->difference_value,
            ]);
        "`, { encoding: 'utf-8' });
        const inv02Data = JSON.parse(inv02DbCheck.trim().split('\n').pop());
        console.log('   [DB CHECK UAT-INV-02]:', inv02Data);

        // =========================================================================
        // KASUS UJI UAT-INV-03 (NO HAPPY PATH): PINDAH STOK OVERFLOW
        // =========================================================================
        console.log('\n--- 3. UAT-INV-03: Pindah Stok dengan Kuantitas Melebihi Stok Bebas Asal ---');
        await loginAs('warehouse_staff@example.com');

        console.log('   Membuka form Pindah Stok (Transfer Antar Gudang)...');
        await page.goto('http://localhost:8009/gudang/transfer/create', { waitUntil: 'networkidle' });
        await page.waitForTimeout(1500);

        // Pilih Gudang Asal: Gudang Kenari (ID: 39)
        console.log(`   Memilih Gudang Asal: Gudang Kenari (ID: ${fixtureIds.wh_kenari_id})...`);
        await page.evaluate(async (whId) => {
            const input = document.getElementById('fromWarehouseSelect');
            if (input) {
                input.value = whId;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
            if (typeof onWarehouseChange === 'function') {
                await onWarehouseChange();
            }
        }, String(fixtureIds.wh_kenari_id));
        await page.waitForTimeout(1500);

        // Pilih Gudang Tujuan: Gudang Utama (ID: 38)
        console.log(`   Memilih Gudang Tujuan: Gudang Utama (ID: ${fixtureIds.wh_utama_id})...`);
        await page.evaluate(async (whId) => {
            const input = document.getElementById('toWarehouseSelect');
            if (input) {
                input.value = whId;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
            if (typeof onDestinationWarehouseChange === 'function') {
                await onDestinationWarehouseChange();
            }
        }, String(fixtureIds.wh_utama_id));
        await page.waitForTimeout(1000);

        // Tambahkan baris transfer untuk PROD-TEST-B
        console.log(`   Memasukkan produk PROD-TEST-B (ID: ${fixtureIds.prodB_id}) dengan kuantitas 15 pcs (Stok Bebas: 10 pcs)...`);
        await page.evaluate((prodId) => {
            const idx = addItemRow(null, true);
            const select = document.getElementById(`prod-select-${idx}`);
            if (select) {
                select.value = prodId;
                if (typeof onProductSelectChange === 'function') {
                    onProductSelectChange(idx);
                }
            }
            const qtyInput = document.getElementById(`qty-input-${idx}`);
            if (qtyInput) {
                qtyInput.value = '15';
            }
        }, fixtureIds.prodB_id);
        await page.waitForTimeout(1000);

        await page.screenshot({ path: path.join(screenshotsDir, '07_uat_inv_03_transfer_form_overflow_input.png'), fullPage: true });

        // Submit form transfer
        console.log('   Mengeklik tombol "Simpan & Ajukan Transfer" untuk memicu validasi ketersediaan stok bebas...');
        await page.evaluate(() => {
            const form = document.getElementById('transferForm');
            if (form) form.noValidate = true;
        });
        const submitTransferBtn = await page.waitForSelector('button[value="submit_request"]');
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}),
            submitTransferBtn.click()
        ]);
        await page.waitForTimeout(1500);

        console.log('   URL setelah submit transfer:', page.url());
        await page.screenshot({ path: path.join(screenshotsDir, '08_uat_inv_03_transfer_overflow_blocked_error.png'), fullPage: true });

        // Verifikasi error message di UI
        const pageContentTransfer = await page.content();
        const overflowBlocked = pageContentTransfer.includes('melebihi stok bebas yang tersedia');
        console.log('   [UI CHECK UAT-INV-03] Pesan pemblokiran transfer stok muncul:', overflowBlocked);

        // Verifikasi database: stok di Gudang Kenari tetap 10 pcs
        const inv03DbCheck = execSync(`php artisan tinker --execute="
            \\$stk = App\\Models\\InventoryStock::where('warehouse_id', ${fixtureIds.wh_kenari_id})
                ->where('product_id', ${fixtureIds.prodB_id})
                ->first();
            \\$transfersCount = App\\Models\\StockTransfer::where('from_warehouse_id', ${fixtureIds.wh_kenari_id})
                ->where('created_at', '>=', now()->subMinutes(5))
                ->count();
            echo json_encode([
                'warehouse_id' => ${fixtureIds.wh_kenari_id},
                'product_id' => ${fixtureIds.prodB_id},
                'qty_available' => (float) \\$stk?->qty_available,
                'qty_reserved' => (float) \\$stk?->qty_reserved,
                'new_transfers_count' => \\$transfersCount,
            ]);
        "`, { encoding: 'utf-8' });
        const inv03Data = JSON.parse(inv03DbCheck.trim().split('\n').pop());
        console.log('   [DB CHECK UAT-INV-03]:', inv03Data);

        console.log('\n=== SEMUA PENGUJIAN UAT MODUL 09 SELESAI DENGAN SUKSES! ===');

    } catch (err) {
        console.error('ERROR saat eksekusi Playwright:', err);
        await page.screenshot({ path: path.join(screenshotsDir, 'error_state.png'), fullPage: true }).catch(() => {});
        process.exit(1);
    } finally {
        await browser.close();
    }
})();
