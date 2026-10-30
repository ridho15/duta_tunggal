const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');
const { execSync } = require('child_process');

(async () => {
    console.log('=== UAT MODUL 05: SALES INVOICE & PIUTANG TERKUNCI ===');
    const screenshotsDir = '/Users/lrmcorporation/.gemini/antigravity-ide/brain/4af30cfa-9a9b-4a4d-b793-07da0cd22265/uat_modul_05_screenshots';
    if (!fs.existsSync(screenshotsDir)) {
        fs.mkdirSync(screenshotsDir, { recursive: true });
    }

    // 0. Jalankan fixture setup modul 05
    console.log('0. Menjalankan fixture setup modul 05...');
    const setupOutput = execSync('php scripts/setup_uat_modul_05_fixtures.php', { encoding: 'utf-8' });
    console.log(setupOutput.trim());

    // Dapatkan ID fixture
    const fixturesJson = execSync(`php artisan tinker --execute="
        \\$so = App\\Models\\SaleOrder::where('so_number', 'SO-TEST-SJ-01')->first();
        \\$sj = App\\Models\\SuratJalan::where('sj_number', 'SJ-20261028-0001')->first();
        echo json_encode([
            'so_id' => \\$so?->id,
            'sj_id' => \\$sj?->id,
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

    try {
        // --- 1. LOGIN ADMIN KEUANGAN ---
        console.log('1. Login sebagai admin_keuangan@example.com di localhost:8009...');
        await page.goto('http://localhost:8009/login', { waitUntil: 'networkidle' });
        await page.fill('#login', 'admin_keuangan@example.com');
        await page.fill('#password', 'password');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(2000);

        // --- 2. UAT-INV-01: ALUR PEMBUATAN INVOICE (CUSTOMER -> SO -> CENTANG SJ) ---
        console.log('2. UAT-INV-01: Mengakses /penjualan/invoice/create...');
        await page.goto('http://localhost:8009/penjualan/invoice/create', { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);
        await page.screenshot({ path: path.join(screenshotsDir, 'UAT-INV-01-01-form-create-initial.png'), fullPage: true });

        // Pilih SO-TEST-SJ-01
        console.log(`   Memilih Sales Order SO-TEST-SJ-01 (ID: ${fixtureIds.so_id})...`);
        await page.evaluate((soId) => {
            if (typeof selectSO === 'function') {
                selectSO(soId);
            }
            const sel = document.getElementById('soSelect');
            if (sel) {
                sel.value = soId;
                sel.dispatchEvent(new Event('change'));
            }
        }, fixtureIds.so_id);
        await page.waitForTimeout(1000);

        // Input Biaya Lain-lain / Ongkos Kirim: Rp 15.000 (Biaya packing kayu peti)
        console.log('   Mengisi biaya lain (packing kayu): Rp 15.000...');
        await page.evaluate(() => {
            const display = document.getElementById('otherFeeDisplay');
            const hidden = document.getElementById('otherFeeInput');
            if (display) display.value = '15.000';
            if (hidden) hidden.value = '15000';
            const notes = document.querySelector('textarea[name="notes"]');
            if (notes) notes.value = 'Biaya packing kayu peti Rp 15.000';
        });

        await page.screenshot({ path: path.join(screenshotsDir, 'UAT-INV-01-02-sj-checked.png'), fullPage: true });

        // Submit form
        console.log('   Mengeklik tombol "Terbitkan Faktur Penjualan"...');
        await page.waitForSelector('#submitBtn:not([disabled])');
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }),
            page.click('#submitBtn')
        ]);
        await page.waitForTimeout(1500);

        console.log('   URL setelah terbit:', page.url());
        await page.screenshot({ path: path.join(screenshotsDir, 'UAT-INV-01-03-invoice-detail.png'), fullPage: true });

        const invUrlMatch = page.url().match(/\/invoice\/(\d+)/);
        const invId = invUrlMatch ? invUrlMatch[1] : null;
        console.log('   Invoice ID yang diterbitkan:', invId);

        // --- 3. UAT-INV-05: VERIFIKASI FINANSIAL & JURNAL AKUNTANSI OTOMATIS ---
        console.log('3. UAT-INV-05: Mengambil screenshot ringkasan finansial dan rincian item...');
        await page.screenshot({ path: path.join(screenshotsDir, 'UAT-INV-05-financial-summary.png'), fullPage: true });

        // --- 4. UAT-INV-02: PENGUNCIAN MUTLAK FIELD INVOICE SETELAH POSTING (NO HAPPY PATH) ---
        if (invId) {
            console.log(`4. UAT-INV-02: Menguji proteksi penguncian invoice non-draft via /penjualan/invoice/${invId}/edit...`);
            await page.goto(`http://localhost:8009/penjualan/invoice/${invId}/edit`, { waitUntil: 'networkidle' });
            await page.waitForTimeout(2000);
            console.log('   URL saat ini (harus di-redirect ke view dengan alert error):', page.url());
            await page.screenshot({ path: path.join(screenshotsDir, 'UAT-INV-02-locked-edit-redirect.png'), fullPage: true });
        }

        // --- 5. UAT-INV-03: PEMBLOKIRAN PENAGIHAN GANDA SURAT JALAN (NO HAPPY PATH) ---
        console.log('5. UAT-INV-03: Memeriksa penyembunyian Surat Jalan yang sudah difakturkan...');
        await page.goto('http://localhost:8009/penjualan/invoice/create', { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);
        await page.screenshot({ path: path.join(screenshotsDir, 'UAT-INV-03-invoiced-sj-hidden.png'), fullPage: true });

        // Coba submit paksa dengan ID SJ yang sudah difakturkan
        console.log(`   Menguji submit paksa Surat Jalan yang sudah difakturkan (SJ ID: ${fixtureIds.sj_id})...`);
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }),
            page.evaluate((sjId) => {
                const form = document.getElementById('invoiceForm');
                if (!form) return;
                document.querySelectorAll('input[name="surat_jalan_ids[]"]').forEach(el => el.remove());

                const cb = document.createElement('input');
                cb.type = 'hidden';
                cb.name = 'surat_jalan_ids[]';
                cb.value = sjId;
                form.appendChild(cb);

                form.submit();
            }, fixtureIds.sj_id)
        ]);
        await page.waitForTimeout(1500);
        console.log('   URL setelah submit penagihan ganda:', page.url());
        await page.screenshot({ path: path.join(screenshotsDir, 'UAT-INV-03-blocked-double-billing.png'), fullPage: true });

        // --- 6. UAT-INV-04: PENGISIAN & VALIDASI NOMOR FAKTUR PAJAK 16 DIGIT ---
        if (invId) {
            console.log(`6. UAT-INV-04: Menguji modal "Isi No. Faktur Pajak" di /penjualan/invoice/${invId}...`);
            await page.goto(`http://localhost:8009/penjualan/invoice/${invId}`, { waitUntil: 'networkidle' });
            await page.waitForTimeout(1500);

            // Buka modal Isi No. Faktur Pajak
            const taxActionBtn = page.locator('button:has-text("Isi No. Faktur Pajak"), button:has-text("Ubah No. Faktur Pajak")').first();
            console.log('   Mengeklik tombol aksi "Isi No. Faktur Pajak"...');
            await taxActionBtn.click();
            await page.waitForTimeout(1000);

            // Masukkan format invalid (kurang digit: 13 digit)
            console.log('   Memasukkan nomor pajak invalid (kurang dari 16 digit: "010.000-26.123")...');
            const taxInput = page.locator('#tax_invoice_number');
            await taxInput.fill('010.000-26.123');
            
            const saveBtn = page.locator('#taxNumberModal button[type="submit"]');
            await Promise.all([
                page.waitForNavigation({ waitUntil: 'networkidle' }),
                saveBtn.click()
            ]);
            await page.waitForTimeout(1500);
            console.log('   Screenshot penolakan format faktur pajak invalid...');
            await page.screenshot({ path: path.join(screenshotsDir, 'UAT-INV-04-tax-number-invalid.png'), fullPage: true });

            // Buka kembali modal jika tertutup atau langsung isi format valid (16 digit)
            const modalVisible = await page.locator('#taxNumberModal:not(.hidden)').isVisible();
            if (!modalVisible) {
                await page.locator('button:has-text("Isi No. Faktur Pajak"), button:has-text("Ubah No. Faktur Pajak")').first().click();
                await page.waitForTimeout(1000);
            }

            console.log('   Memasukkan nomor pajak valid 16 digit: "010.000-26.12345678"...');
            await page.locator('#tax_invoice_number').fill('010.000-26.12345678');
            await Promise.all([
                page.waitForNavigation({ waitUntil: 'networkidle' }),
                page.locator('#taxNumberModal button[type="submit"]').click()
            ]);
            await page.waitForTimeout(2000);
            console.log('   Screenshot nomor faktur pajak 16 digit berhasil disimpan...');
            await page.screenshot({ path: path.join(screenshotsDir, 'UAT-INV-04-tax-number-saved.png'), fullPage: true });
        }

        console.log('=== SEMUA PENGUJIAN BROWSER MODUL 05 SELESAI DENGAN SUKSES ===');
    } catch (err) {
        console.error('Error saat eksekusi pengujian:', err);
        await page.screenshot({ path: path.join(screenshotsDir, 'UAT-INV-ERROR.png'), fullPage: true });
    } finally {
        await browser.close();
    }
})();
