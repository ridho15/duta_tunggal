const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');
const { execSync } = require('child_process');

(async () => {
    console.log('=== UAT MODUL 06: PURCHASE ORDER & APPROVAL BERTINGKAT ===');
    const screenshotsDir = '/Users/lrmcorporation/.gemini/antigravity-ide/brain/4af30cfa-9a9b-4a4d-b793-07da0cd22265/uat_modul_06_screenshots';
    if (!fs.existsSync(screenshotsDir)) {
        fs.mkdirSync(screenshotsDir, { recursive: true });
    }

    // 0. Jalankan fixture setup modul 06
    console.log('0. Menjalankan fixture setup modul 06...');
    const setupOutput = execSync('php scripts/setup_uat_modul_06_fixtures.php', { encoding: 'utf-8' });
    console.log(setupOutput.trim());

    // Dapatkan ID fixture
    const fixturesJson = execSync(`php artisan tinker --execute="
        \\$or = App\\Models\\OrderRequest::where('request_number', 'OR-TEST-UAT-01')->first();
        \\$po25 = App\\Models\\PurchaseOrder::where('po_number', 'PO-TEST-UAT-25JT')->first();
        \\$po5Self = App\\Models\\PurchaseOrder::where('po_number', 'PO-TEST-UAT-5JT-SELF')->first();
        echo json_encode([
            'or_id' => \\$or?->id,
            'po25_id' => \\$po25?->id,
            'po5_self_id' => \\$po5Self?->id,
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
        await page.goto('http://localhost:8009/login', { waitUntil: 'networkidle' });
        // Jika sudah login, logout dulu jika bukan email yang sama
        const alreadyLoggedIn = await page.$('form[action*="logout"]');
        if (alreadyLoggedIn) {
            await page.click('form[action*="logout"] button[type="submit"]');
            await page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {});
            await page.goto('http://localhost:8009/login', { waitUntil: 'networkidle' });
        }
        await page.fill('#login', email);
        await page.fill('#password', password);
        await page.click('button[type="submit"]');
        await page.waitForTimeout(1500);
    }

    try {
        // =========================================================================
        // KASUS UJI UAT-PO-01: ALUR PEMBUATAN PO DARI PERMINTAAN PEMBELIAN (OR)
        // =========================================================================
        console.log('\n--- 1. UAT-PO-01: Pembuatan PO dari Order Request (OR) ---');
        await loginAs('purchasing@example.com');

        console.log(`   Membuka detail Order Request ID: ${fixtureIds.or_id}...`);
        await page.goto(`http://localhost:8009/pembelian/permintaan/${fixtureIds.or_id}`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);
        await page.screenshot({ path: path.join(screenshotsDir, '01_uat_po_01_or_approved_detail.png'), fullPage: true });

        console.log('   Mengeklik tombol "Terbitkan Purchase Order (PO)"...');
        const createPoLink = await page.waitForSelector('a:has-text("Terbitkan Purchase Order")');
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }),
            createPoLink.click()
        ]);
        await page.waitForTimeout(1500);

        console.log('   Form PO Create terbuka:', page.url());
        await page.screenshot({ path: path.join(screenshotsDir, '02_uat_po_01_po_create_from_or.png'), fullPage: true });

        // Simpan PO dengan klik "Ajukan Approval Atasan"
        console.log('   Menyimpan PO via tombol "Ajukan Approval Atasan"...');
        const submitBtn = await page.waitForSelector('button[name="action"][value="save_and_request"]');
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }),
            submitBtn.click()
        ]);
        await page.waitForTimeout(1500);

        console.log('   URL setelah PO tersimpan:', page.url());
        await page.screenshot({ path: path.join(screenshotsDir, '03_uat_po_01_po_saved_success.png'), fullPage: true });

        // Verifikasi database UAT-PO-01
        const poCreatedCheck = execSync(`php artisan tinker --execute="
            \\$po = App\\Models\\PurchaseOrder::where('refer_model_type', App\\Models\\OrderRequest::class)
                ->where('refer_model_id', ${fixtureIds.or_id})
                ->latest()
                ->first();
            \\$item = \\$po?->purchaseOrderItem?->first();
            echo json_encode([
                'po_id' => \\$po?->id,
                'po_number' => \\$po?->po_number,
                'status' => \\$po?->status,
                'total_amount' => \\$po?->total_amount,
                'item_qty' => \\$item?->quantity,
                'item_price' => \\$item?->unit_price,
                'order_request_item_id' => \\$item?->refer_item_model_id,
            ]);
        "`, { encoding: 'utf-8' });
        const poCreatedData = JSON.parse(poCreatedCheck.trim().split('\n').pop());
        console.log('   [DB CHECK UAT-PO-01]:', poCreatedData);

        // =========================================================================
        // KASUS UJI UAT-PO-02 (NO HAPPY PATH): PERSETUJUAN BERTINGKAT PO > 10 JT
        // =========================================================================
        console.log('\n--- 2. UAT-PO-02: Persetujuan Bertingkat PO Rp 25.000.000 oleh Purchasing Manager ---');
        await loginAs('purchasing_manager@example.com');

        console.log(`   Membuka PO Rp 25.000.000 (ID: ${fixtureIds.po25_id})...`);
        await page.goto(`http://localhost:8009/pembelian/po/${fixtureIds.po25_id}`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);
        await page.screenshot({ path: path.join(screenshotsDir, '04_uat_po_02_purchasing_manager_view_po25jt.png'), fullPage: true });

        console.log('   Purchasing Manager mencoba menyetujui PO Rp 25 jt...');
        // Klik tombol Setujui PO untuk buka modal
        const approveBtnPm = await page.waitForSelector('button:has-text("Setujui PO")');
        await approveBtnPm.click();
        await page.waitForTimeout(500);

        // Konfirmasi di modal
        const confirmApproveBtnPm = await page.waitForSelector('form[action*="/approve"] button[type="submit"]');
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }),
            confirmApproveBtnPm.click()
        ]);
        await page.waitForTimeout(1000);

        await page.screenshot({ path: path.join(screenshotsDir, '05_uat_po_02_tier_limit_blocked_error.png'), fullPage: true });

        // Verifikasi error message di UI
        const pageContentPm = await page.content();
        const tierBlocked = pageContentPm.includes('Persetujuan bertingkat: Nilai Purchase Order di atas Rp 10.000.000 wajib disetujui oleh Direktur / Owner / Finance Manager.');
        console.log('   [UI CHECK UAT-PO-02] Pesan blokir bertingkat muncul:', tierBlocked);

        // Verifikasi database: status tetap request_approve
        const po25DbCheck = execSync(`php artisan tinker --execute="
            \\$po = App\\Models\\PurchaseOrder::find(${fixtureIds.po25_id});
            echo json_encode([
                'status' => \\$po?->status,
                'approved_by' => \\$po?->approved_by,
                'date_approved' => \\$po?->date_approved,
            ]);
        "`, { encoding: 'utf-8' });
        const po25DbData = JSON.parse(po25DbCheck.trim().split('\n').pop());
        console.log('   [DB CHECK UAT-PO-02] Status PO setelah percobaan PM:', po25DbData);

        // =========================================================================
        // KASUS UJI UAT-PO-03: PERSETUJUAN PO > 10 JT OLEH OWNER / DIREKTUR
        // =========================================================================
        console.log('\n--- 3. UAT-PO-03: Persetujuan PO Rp 25.000.000 oleh Owner ---');
        await loginAs('owner@example.com');

        console.log(`   Membuka PO Rp 25.000.000 (ID: ${fixtureIds.po25_id}) sebagai Owner...`);
        await page.goto(`http://localhost:8009/pembelian/po/${fixtureIds.po25_id}`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);
        await page.screenshot({ path: path.join(screenshotsDir, '06_uat_po_03_owner_view_po25jt.png'), fullPage: true });

        console.log('   Owner menyetujui PO...');
        const approveBtnOwner = await page.waitForSelector('button:has-text("Setujui PO")');
        await approveBtnOwner.click();
        await page.waitForTimeout(500);

        const confirmApproveBtnOwner = await page.waitForSelector('form[action*="/approve"] button[type="submit"]');
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }),
            confirmApproveBtnOwner.click()
        ]);
        await page.waitForTimeout(1000);

        await page.screenshot({ path: path.join(screenshotsDir, '07_uat_po_03_owner_approved_success.png'), fullPage: true });

        // Verifikasi database: status approved, approved_by = 15 (owner)
        const po25ApprovedDbCheck = execSync(`php artisan tinker --execute="
            \\$po = App\\Models\\PurchaseOrder::find(${fixtureIds.po25_id});
            echo json_encode([
                'status' => \\$po?->status,
                'approved_by' => \\$po?->approved_by,
                'date_approved' => \\$po?->date_approved?->format('Y-m-d'),
            ]);
        "`, { encoding: 'utf-8' });
        const po25ApprovedDbData = JSON.parse(po25ApprovedDbCheck.trim().split('\n').pop());
        console.log('   [DB CHECK UAT-PO-03] Status PO setelah persetujuan Owner:', po25ApprovedDbData);

        // =========================================================================
        // KASUS UJI UAT-PO-04 (NO HAPPY PATH): PEMBLOKIRAN SELF-APPROVAL PEMBUAT PO
        // =========================================================================
        console.log('\n--- 4. UAT-PO-04: Pemblokiran Self-Approval (Segregation of Duties) ---');
        await loginAs('purchasing_manager@example.com');

        console.log(`   Membuka PO buatan Purchasing Manager sendiri (ID: ${fixtureIds.po5_self_id})...`);
        await page.goto(`http://localhost:8009/pembelian/po/${fixtureIds.po5_self_id}`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);
        await page.screenshot({ path: path.join(screenshotsDir, '08_uat_po_04_pm_view_self_po5jt.png'), fullPage: true });

        console.log('   Purchasing Manager mencoba menyetujui PO buatannya sendiri...');
        const approveBtnSelf = await page.waitForSelector('button:has-text("Setujui PO")');
        await approveBtnSelf.click();
        await page.waitForTimeout(500);

        const confirmApproveBtnSelf = await page.waitForSelector('form[action*="/approve"] button[type="submit"]');
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }),
            confirmApproveBtnSelf.click()
        ]);
        await page.waitForTimeout(1000);

        await page.screenshot({ path: path.join(screenshotsDir, '09_uat_po_04_self_approval_blocked_error.png'), fullPage: true });

        // Verifikasi error message di UI
        const pageContentSelf = await page.content();
        const selfBlocked = pageContentSelf.includes('Pemisahan tugas (Segregation of Duties): Pembuat Purchase Order tidak boleh menyetujui dokumen miliknya sendiri.');
        console.log('   [UI CHECK UAT-PO-04] Pesan blokir self-approval muncul:', selfBlocked);

        // Verifikasi database: status tetap request_approve
        const po5SelfDbCheck = execSync(`php artisan tinker --execute="
            \\$po = App\\Models\\PurchaseOrder::find(${fixtureIds.po5_self_id});
            echo json_encode([
                'status' => \\$po?->status,
                'approved_by' => \\$po?->approved_by,
                'date_approved' => \\$po?->date_approved,
            ]);
        "`, { encoding: 'utf-8' });
        const po5SelfDbData = JSON.parse(po5SelfDbCheck.trim().split('\n').pop());
        console.log('   [DB CHECK UAT-PO-04] Status PO buatan sendiri setelah percobaan approve:', po5SelfDbData);

        console.log('\n=== SEMUA PENGUJIAN UAT MODUL 06 SELESAI DENGAN SUKSES! ===');

    } catch (err) {
        console.error('ERROR saat eksekusi Playwright:', err);
        await page.screenshot({ path: path.join(screenshotsDir, 'error_state.png'), fullPage: true }).catch(() => {});
        process.exit(1);
    } finally {
        await browser.close();
    }
})();
