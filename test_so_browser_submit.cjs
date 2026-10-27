const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

(async () => {
    console.log('=== REAL BROWSER SUBMIT TEST: MODUL 01 SALES ORDER ===');
    const screenshotsDir = '/Users/lrmcorporation/.gemini/antigravity-ide/brain/4af30cfa-9a9b-4a4d-b793-07da0cd22265/uat_modul_01_screenshots';
    if (!fs.existsSync(screenshotsDir)) {
        fs.mkdirSync(screenshotsDir, { recursive: true });
    }

    const browser = await chromium.launch({
        headless: true,
        executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 }
    });
    const page = await context.newPage();

    try {
        // 1. Login Sales
        console.log('1. Mengakses http://127.0.0.1:8009/login...');
        await page.goto('http://127.0.0.1:8009/login', { waitUntil: 'networkidle' });
        await page.fill('#login', 'sales@example.com');
        await page.fill('#password', 'password');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(1500);
        console.log('   Login berhasil, URL saat ini:', page.url());

        // 2. Buka Form Create SO
        console.log('2. Membuka form /penjualan/so/create...');
        await page.goto('http://127.0.0.1:8009/penjualan/so/create', { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);
        await page.screenshot({ path: path.join(screenshotsDir, 'live_01_form_so_kosong.png'), fullPage: true });

        // 3. Pilih Customer: CUST-TEST-UAT-001 (PT UAT Pelanggan Kredit)
        console.log('3. Memilih customer PT UAT Pelanggan Kredit...');
        const custBtn = page.locator('div[x-data*="customer_id"] button').first();
        await custBtn.click();
        await page.waitForTimeout(500);

        const custSearch = page.locator('div[x-data*="customer_id"] input[placeholder*="Ketik untuk mencari"]').first();
        await custSearch.fill('PT UAT Pelanggan Kredit');
        await page.waitForTimeout(500);

        const custOption = page.locator('div[x-data*="customer_id"] li').filter({ hasText: 'PT UAT Pelanggan Kredit' }).first();
        await custOption.click();
        await page.waitForTimeout(500);

        const selectedCustId = await page.evaluate(() => document.getElementById('customerSelect')?.value);
        console.log('   Customer terpilih, value input customerSelect:', selectedCustId);

        // 4. Pilih Produk via Quick-Add Barcode/Search Box
        console.log('4. Memasukkan produk PROD-TEST-A via quickSearchInput...');
        const quickInput = page.locator('#quickSearchInput');
        await quickInput.fill('PROD-TEST-A');
        await page.waitForTimeout(400);
        await page.keyboard.press('Enter');
        await page.waitForTimeout(800);

        // 5. Isi Quantity = 10
        console.log('5. Mengisi quantity = 10...');
        const qtyInput = page.locator('input[name*="[quantity]"]').first();
        await qtyInput.fill('10');
        await qtyInput.dispatchEvent('input');
        await page.waitForTimeout(500);

        // 6. Isi Ongkos Kirim = 25000
        console.log('6. Mengisi ongkir = 25.000...');
        const ongkirDisplay = page.locator('#shippingCostDisplay');
        await ongkirDisplay.fill('25000');
        await ongkirDisplay.dispatchEvent('input');
        await page.waitForTimeout(500);

        // Ambil summary text
        const summaryTotal = await page.locator('#summaryTotal').innerText();
        console.log('   Ringkasan Total di UI:', summaryTotal);

        await page.screenshot({ path: path.join(screenshotsDir, 'live_02_form_so_terisi.png'), fullPage: true });

        // 7. Klik Submit: "Simpan & Ajukan Approve"
        console.log('7. Mengklik tombol "Simpan & Ajukan Approve"...');
        const submitBtn = page.locator('button:has-text("Simpan & Ajukan Approve")').first();
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle', timeout: 15000 }).catch(e => console.log('   Navigasi selesai/timeout:', e.message)),
            submitBtn.click()
        ]);

        console.log('8. URL setelah submit:', page.url());
        await page.waitForTimeout(2000);
        await page.screenshot({ path: path.join(screenshotsDir, 'live_03_so_setelah_submit.png'), fullPage: true });

        console.log('Browser test selesai.');
    } catch (err) {
        console.error('Error during test execution:', err);
        await page.screenshot({ path: path.join(screenshotsDir, 'live_error.png'), fullPage: true });
    } finally {
        await browser.close();
    }
})();
