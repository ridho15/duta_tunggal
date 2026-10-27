const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');
const { execSync } = require('child_process');

(async () => {
    console.log('=== UAT MODUL 03: CHECKER GUDANG & RETUR BY DO (MOBILE 390px) ===');
    const screenshotsDir = '/Users/lrmcorporation/.gemini/antigravity-ide/brain/4af30cfa-9a9b-4a4d-b793-07da0cd22265/uat_modul_03_screenshots';
    if (!fs.existsSync(screenshotsDir)) {
        fs.mkdirSync(screenshotsDir, { recursive: true });
    }

    const browser = await chromium.launch({
        headless: true,
        executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    // Viewport mobile standar iPhone 12/13/14 (390 x 844 px)
    const context = await browser.newContext({
        viewport: { width: 390, height: 844 },
        isMobile: true,
        hasTouch: true,
        userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1'
    });
    const page = await context.newPage();

    try {
        // --- 1. LOGIN CHECKER GUDANG ---
        console.log('1. Login sebagai checker@example.com di layar mobile...');
        await page.goto('http://127.0.0.1:8009/login', { waitUntil: 'networkidle' });
        await page.fill('#login', 'checker@example.com');
        await page.fill('#password', 'password');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(1500);

        // --- 2. UAT-CHK-01: TAMPILAN ANTARMUKA SIAPKAN & KIRIM HP (390px) ---
        console.log('2. UAT-CHK-01: Mengakses /gudang/kiriman pada viewport 390px...');
        await page.goto('http://127.0.0.1:8009/gudang/kiriman', { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);
        await page.screenshot({ path: path.join(screenshotsDir, 'UAT-CHK-01-mobile-390px-kiriman-list.png'), fullPage: true });

        // Buka DO-TEST-CHK-01 (ID: 13)
        console.log('   Membuka detail checklist DO-TEST-CHK-01 (/gudang/kiriman/13/check)...');
        await page.goto('http://127.0.0.1:8009/gudang/kiriman/13/check', { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);
        await page.screenshot({ path: path.join(screenshotsDir, 'UAT-CHK-01-mobile-390px-check-detail.png'), fullPage: true });

        // --- 3. UAT-CHK-02: BLOKIR KIRIM JIKA CENTANG BELUM LENGKAP (NO HAPPY PATH) ---
        console.log('3. UAT-CHK-02: Menguji centang parsial (hanya 1 item dicentang)...');
        const checkboxes = page.locator('input.checker-box');
        const countBoxes = await checkboxes.count();
        console.log(`   Ditemukan ${countBoxes} checkbox barang.`);

        // Centang item pertama
        await checkboxes.nth(0).click();
        await page.waitForTimeout(500);

        const statusText1 = await page.locator('#checkedStatusText').innerText();
        const submitBtnDisabled1 = await page.locator('#submitKirimBtn').isDisabled();
        console.log(`   Progress text: "${statusText1}", Tombol Kirim Disabled: ${submitBtnDisabled1}`);
        await page.screenshot({ path: path.join(screenshotsDir, 'UAT-CHK-02-blocked-incomplete-check.png'), fullPage: true });

        // Centang item kedua sehingga semua tercentang
        console.log('   Mencentang item kedua sehingga seluruh barang terverifikasi...');
        await checkboxes.nth(1).click();
        await page.waitForTimeout(500);

        const statusText2 = await page.locator('#checkedStatusText').innerText();
        const submitBtnDisabled2 = await page.locator('#submitKirimBtn').isDisabled();
        console.log(`   Progress text: "${statusText2}", Tombol Kirim Disabled: ${submitBtnDisabled2}`);
        await page.screenshot({ path: path.join(screenshotsDir, 'UAT-CHK-02-complete-check-enabled.png'), fullPage: true });

        // Klik tombol Semua Sesuai - KIRIM
        console.log('   Mengeklik tombol "Semua sesuai – KIRIM"...');
        await page.click('#submitKirimBtn');
        await page.waitForTimeout(2000);
        await page.screenshot({ path: path.join(screenshotsDir, 'UAT-CHK-02-confirmed-status.png'), fullPage: true });

        // --- 4. UAT-CHK-03: VALIDASI INPUT QTY RETUR (OVERFLOW - NO HAPPY PATH) ---
        console.log('4. UAT-CHK-03: Membuka form retur DO-TEST-CHK-RETUR (/gudang/kiriman/14/retur)...');
        await page.goto('http://127.0.0.1:8009/gudang/kiriman/14/retur', { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);
        await page.screenshot({ path: path.join(screenshotsDir, 'UAT-CHK-03-return-form-mobile.png'), fullPage: true });

        // Coba kirim payload overflow (Kurang: 15, Rusak: 10, Total: 25 > Qty DO 20)
        console.log('   Menguji submit payload overflow (Kurang: 15 + Rusak: 10 = 25 > Qty DO 20)...');
        await page.evaluate(() => {
            const form = document.querySelector('form');
            const kurangInput = form.querySelector('input[name*="[kurang]"]');
            const rejectInput = form.querySelector('input[name*="[reject]"]');
            if (kurangInput) kurangInput.value = '15';
            if (rejectInput) rejectInput.value = '10';
            form.submit();
        });
        await page.waitForTimeout(2500);
        await page.screenshot({ path: path.join(screenshotsDir, 'UAT-CHK-03-return-overflow-validation.png'), fullPage: true });

        // --- 5. UAT-CHK-04 & UAT-CHK-05: RETUR DO VALID, APPROVAL, DO PENGGANTI & KARANTINA ---
        console.log('5. UAT-CHK-04: Mengisi retur valid (Kurang: 3 pcs, Rusak: 2 pcs, Diterima: 15 pcs)...');
        await page.goto('http://127.0.0.1:8009/gudang/kiriman/14/retur', { waitUntil: 'networkidle' });
        await page.waitForTimeout(500);

        // Isi Kurang: 3, Rusak: 2 via tombol stepper atau form value
        await page.evaluate(() => {
            const form = document.querySelector('form');
            const kurangInput = form.querySelector('input[name*="[kurang]"]');
            const rejectInput = form.querySelector('input[name*="[reject]"]');
            if (kurangInput) kurangInput.value = '3';
            if (rejectInput) rejectInput.value = '2';
        });
        await page.click('button[type="submit"]');
        await page.waitForTimeout(2500);
        await page.screenshot({ path: path.join(screenshotsDir, 'UAT-CHK-04-return-draft-created.png'), fullPage: true });

        // Setujui retur via backend Service seperti di desktop
        console.log('   Menyetujui dokumen retur di backend via ReturnProductService::updateQuantityFromModel...');
        execSync(`php artisan tinker --execute="
            \\$ret = App\\Models\\ReturnProduct::where('return_number', 'DO-TEST-CHK-RETURR')->first();
            if (\\$ret) {
                app(App\\Services\\ReturnProductService::class)->updateQuantityFromModel(\\$ret);
                echo 'Retur disetujui, DO pengganti dibuat.' . PHP_EOL;
            }
        "`, { encoding: 'utf-8' });

        // Muat ulang daftar kiriman untuk melihat DO pengganti DO-TEST-CHK-RETURC
        console.log('   Mengecek tampilan mobile untuk DO Pengganti (DO-TEST-CHK-RETURC)...');
        await page.goto('http://127.0.0.1:8009/gudang/kiriman', { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);
        await page.screenshot({ path: path.join(screenshotsDir, 'UAT-CHK-04-replacement-do-in-list.png'), fullPage: true });

        // Buka DO Pengganti jika ada
        const replDoItem = page.locator('a[href*="/gudang/kiriman/"]').filter({ hasText: 'DO-TEST-CHK-RETURC' }).first();
        if (await replDoItem.isVisible()) {
            await replDoItem.click();
            await page.waitForTimeout(1000);
            await page.screenshot({ path: path.join(screenshotsDir, 'UAT-CHK-04-replacement-do-detail.png'), fullPage: true });
        }

        // --- 6. UAT-CHK-06: BLOKIR RETUR PADA DO YANG SUDAH MEMILIKI SURAT JALAN AKTIF (NO HAPPY PATH) ---
        console.log('6. UAT-CHK-06: Mengakses form retur pada DO yang sudah memiliki Surat Jalan aktif (ID: 15)...');
        await page.goto('http://127.0.0.1:8009/gudang/kiriman/15/retur', { waitUntil: 'networkidle' });
        await page.waitForTimeout(1500);
        console.log('   URL saat ini setelah redirect blokir:', page.url());
        await page.screenshot({ path: path.join(screenshotsDir, 'UAT-CHK-06-blocked-return-with-active-sj.png'), fullPage: true });

        console.log('=== SEMUA PENGUJIAN BROWSER MODUL 03 SELESAI ===');
    } catch (err) {
        console.error('Error saat eksekusi pengujian:', err);
        await page.screenshot({ path: path.join(screenshotsDir, 'UAT-CHK-ERROR.png'), fullPage: true });
    } finally {
        await browser.close();
    }
})();
