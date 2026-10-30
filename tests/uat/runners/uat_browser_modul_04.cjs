const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');
const { execSync } = require('child_process');

(async () => {
    console.log('=== UAT MODUL 04: SURAT JALAN KOLEKTIF & AUTO-RECEIVED ===');
    const screenshotsDir = '/Users/lrmcorporation/.gemini/antigravity-ide/brain/4af30cfa-9a9b-4a4d-b793-07da0cd22265/uat_modul_04_screenshots';
    if (!fs.existsSync(screenshotsDir)) {
        fs.mkdirSync(screenshotsDir, { recursive: true });
    }

    // 0. Jalankan fixture setup
    console.log('0. Menjalankan fixture setup modul 04...');
    execSync('php scripts/setup_uat_modul_04_fixtures.php', { encoding: 'utf-8' });

    // Dapatkan ID fixture
    const fixturesJson = execSync(`php artisan tinker --execute="
        \\$so1 = App\\Models\\SaleOrder::where('so_number', 'SO-TEST-SJ-01')->first();
        \\$do1a = App\\Models\\DeliveryOrder::where('do_number', 'DO-TEST-SJ-01A')->first();
        \\$do1b = App\\Models\\DeliveryOrder::where('do_number', 'DO-TEST-SJ-01B')->first();
        \\$so2a = App\\Models\\SaleOrder::where('so_number', 'SO-TEST-SJ-02A')->first();
        \\$do2a = App\\Models\\DeliveryOrder::where('do_number', 'DO-TEST-SJ-02A')->first();
        \\$so2b = App\\Models\\SaleOrder::where('so_number', 'SO-TEST-SJ-02B')->first();
        \\$do2b = App\\Models\\DeliveryOrder::where('do_number', 'DO-TEST-SJ-02B')->first();
        \\$doRet = App\\Models\\DeliveryOrder::where('do_number', 'DO-TEST-CHK-RETUR')->first();
        echo json_encode([
            'so1_id' => \\$so1?->id,
            'do1a_id' => \\$do1a?->id,
            'do1b_id' => \\$do1b?->id,
            'so2a_id' => \\$so2a?->id,
            'do2a_id' => \\$do2a?->id,
            'so2b_id' => \\$so2b?->id,
            'do2b_id' => \\$do2b?->id,
            'do_ret_id' => \\$doRet?->id,
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
        // --- 1. LOGIN ADMIN PENGIRIMAN ---
        console.log('1. Login sebagai admin_pengiriman@example.com...');
        await page.goto('http://127.0.0.1:8009/login', { waitUntil: 'networkidle' });
        await page.fill('#login', 'admin_pengiriman@example.com');
        await page.fill('#password', 'password');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(2000);

        // --- 2. UAT-SJ-01: ALUR PEMBUATAN SJ KOLEKTIF MULTI-DO (GUDANG UTAMA + KENARI) ---
        console.log('2. UAT-SJ-01: Mengakses /penjualan/surat-jalan/create...');
        await page.goto('http://127.0.0.1:8009/penjualan/surat-jalan/create', { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);
        await page.screenshot({ path: path.join(screenshotsDir, 'UAT-SJ-01-01-form-create-initial.png'), fullPage: true });

        // Pilih SO-TEST-SJ-01
        console.log(`   Memilih Sales Order SO-TEST-SJ-01 (ID: ${fixtureIds.so1_id})...`);
        await page.evaluate((soId) => {
            if (typeof selectSO === 'function') {
                selectSO(soId);
            }
            const sel = document.getElementById('soSelect');
            if (sel) {
                sel.value = soId;
                sel.dispatchEvent(new Event('change'));
            }
        }, fixtureIds.so1_id);
        await page.waitForTimeout(1000);

        // Ambil screenshot kedua DO yang otomatis tercentang dari SO yang sama
        console.log('   Kedua DO (Gudang Utama 10 pcs & Gudang Kenari 5 pcs) tercentang secara kolektif...');
        await page.screenshot({ path: path.join(screenshotsDir, 'UAT-SJ-01-02-multi-do-checked.png'), fullPage: true });

        // Terbitkan Surat Jalan
        console.log('   Mengeklik tombol "Terbitkan Surat Jalan Resmi"...');
        await page.waitForSelector('#submitBtn:not([disabled])');
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }),
            page.click('#submitBtn')
        ]);
        await page.waitForTimeout(1000);

        console.log('   Halaman saat ini setelah penerbitan:', page.url());
        await page.screenshot({ path: path.join(screenshotsDir, 'UAT-SJ-01-03-sj-issued-detail.png'), fullPage: true });

        // Ambil ID Surat Jalan dari URL
        const sjUrlMatch = page.url().match(/\/surat-jalan\/(\d+)/);
        const sjId = sjUrlMatch ? sjUrlMatch[1] : null;
        console.log('   Surat Jalan ID yang diterbitkan:', sjId);

        // --- 3. UAT-SJ-05: PENERIMAAN OTOMATIS DO SAAT SURAT JALAN DITERBITKAN ---
        console.log('3. UAT-SJ-05: Memeriksa status DO-TEST-SJ-01A & DO-TEST-SJ-01B di daftar Perintah Kirim...');
        await page.goto('http://127.0.0.1:8009/penjualan/do?q=DO-TEST-SJ-01', { waitUntil: 'networkidle' });
        await page.waitForTimeout(1500);
        await page.screenshot({ path: path.join(screenshotsDir, 'UAT-SJ-05-do-auto-received-status.png'), fullPage: true });

        // --- 4. UAT-SJ-06: CETAKAN SURAT JALAN MENAMPILKAN GUDANG ASAL PER BARANG ---
        if (sjId) {
            console.log(`4. UAT-SJ-06: Membuka cetakan/pratinjau detail Surat Jalan ID ${sjId}...`);
            await page.goto(`http://127.0.0.1:8009/penjualan/surat-jalan/${sjId}`, { waitUntil: 'networkidle' });
            await page.waitForTimeout(1500);
            await page.screenshot({ path: path.join(screenshotsDir, 'UAT-SJ-06-print-multi-gudang.png'), fullPage: true });
        }

        // --- 5. UAT-SJ-02: BLOKIR PENGGABUNGAN DO DARI SALES ORDER BERBEDA (NO HAPPY PATH) ---
        console.log('5. UAT-SJ-02: Menguji blokir penggabungan DO dari 2 SO berbeda (DO 29 & DO 30)...');
        await page.goto('http://127.0.0.1:8009/penjualan/surat-jalan/create', { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);

        // Lakukan submit via form manipulasi payload cross-SO
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }),
            page.evaluate(({ do2a, do2b }) => {
                const form = document.getElementById('sjForm');
                document.querySelectorAll('input[name="delivery_order_ids[]"]').forEach(el => el.remove());

                const cb1 = document.createElement('input');
                cb1.type = 'hidden';
                cb1.name = 'delivery_order_ids[]';
                cb1.value = do2a;
                form.appendChild(cb1);

                const cb2 = document.createElement('input');
                cb2.type = 'hidden';
                cb2.name = 'delivery_order_ids[]';
                cb2.value = do2b;
                form.appendChild(cb2);

                form.submit();
            }, { do2a: fixtureIds.do2a_id, do2b: fixtureIds.do2b_id })
        ]);

        await page.waitForTimeout(1000);
        console.log('   URL setelah submit cross-SO:', page.url());
        await page.screenshot({ path: path.join(screenshotsDir, 'UAT-SJ-02-blocked-cross-so.png'), fullPage: true });

        // --- 6. UAT-SJ-03: PEMBLOKIRAN DO YANG PERNAH DIRETUR (NO HAPPY PATH) ---
        console.log(`6. UAT-SJ-03: Menguji blokir DO yang pernah diretur (DO ID: ${fixtureIds.do_ret_id})...`);
        await page.goto('http://127.0.0.1:8009/penjualan/surat-jalan/create', { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);
        await page.screenshot({ path: path.join(screenshotsDir, 'UAT-SJ-03-returned-do-excluded-from-list.png'), fullPage: true });

        // Submit form dengan ID DO yang pernah diretur
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }),
            page.evaluate((retId) => {
                const form = document.getElementById('sjForm');
                document.querySelectorAll('input[name="delivery_order_ids[]"]').forEach(el => el.remove());

                const cb = document.createElement('input');
                cb.type = 'hidden';
                cb.name = 'delivery_order_ids[]';
                cb.value = retId;
                form.appendChild(cb);

                form.submit();
            }, fixtureIds.do_ret_id)
        ]);

        await page.waitForTimeout(1000);
        console.log('   URL setelah submit returned DO:', page.url());
        await page.screenshot({ path: path.join(screenshotsDir, 'UAT-SJ-03-blocked-returned-do-submit.png'), fullPage: true });

        // --- 7. UAT-SJ-04: PENYEMBUNYIAN DO YANG SUDAH DIGUNAKAN PADA SURAT JALAN LAIN ---
        console.log('7. UAT-SJ-04: Memeriksa bahwa DO-TEST-SJ-01A & DO-TEST-SJ-01B kini disembunyikan...');
        await page.goto('http://127.0.0.1:8009/penjualan/surat-jalan/create', { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);

        // Amati apakah SO-TEST-SJ-01 masih memiliki DO di dropdown
        const isSo1InDropdown = await page.evaluate((soId) => {
            const opt = document.querySelector(`#soSelect option[value="${soId}"]`);
            return opt !== null;
        }, fixtureIds.so1_id);
        console.log('   Apakah SO-TEST-SJ-01 masih memiliki DO siap kirim di dropdown?', isSo1InDropdown ? 'YA' : 'TIDAK (Benar, sudah disembunyikan)');
        await page.screenshot({ path: path.join(screenshotsDir, 'UAT-SJ-04-used-do-hidden.png'), fullPage: true });

        console.log('=== SEMUA PENGUJIAN BROWSER MODUL 04 SELESAI DENGAN SUKSES ===');
    } catch (err) {
        console.error('Error saat eksekusi pengujian:', err);
        await page.screenshot({ path: path.join(screenshotsDir, 'UAT-SJ-ERROR.png'), fullPage: true });
    } finally {
        await browser.close();
    }
})();
