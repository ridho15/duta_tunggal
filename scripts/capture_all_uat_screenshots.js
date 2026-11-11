import puppeteer from 'puppeteer-core';
import fs from 'fs';

(async () => {
    const rawIds = fs.readFileSync('/tmp/uat_doc_ids.json', 'utf8');
    const ids = JSON.parse(rawIds);
    console.log('Using IDs for screenshots:', ids);

    const browser = await puppeteer.launch({
        executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        headless: 'new',
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--window-size=1280,1000']
    });

    const page = await browser.newPage();
    await page.setViewport({ width: 1280, height: 1000 });

    // 1. Login Super Admin
    console.log('Logging in as Super Admin...');
    await page.goto('http://127.0.0.1:8009/login', { waitUntil: 'networkidle0' });
    await page.type('#login', 'superadmin@gmail.com');
    await page.type('#password', '12345678');
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle0' }),
        page.click('button[type="submit"]')
    ]);

    // 2. SO Detail with #shipTo & Approval status
    console.log(`Navigating to SO #${ids.so_id}...`);
    await page.goto(`http://127.0.0.1:8009/penjualan/so/${ids.so_id}`, { waitUntil: 'networkidle0' });
    await page.screenshot({
        path: '/Users/lrmcorporation/.gemini/antigravity/brain/157dfc6d-731c-4f66-adcb-a3c82dbb6767/uat_06_so_submitted.png',
        fullPage: false
    });
    console.log('uat_06_so_submitted.png captured');

    // 3. DO Detail with Warehouse & Items
    console.log(`Navigating to DO #${ids.do_id}...`);
    await page.goto(`http://127.0.0.1:8009/penjualan/do/${ids.do_id}`, { waitUntil: 'networkidle0' });
    await page.screenshot({
        path: '/Users/lrmcorporation/.gemini/antigravity/brain/157dfc6d-731c-4f66-adcb-a3c82dbb6767/uat_08_do_created.png',
        fullPage: false
    });
    console.log('uat_08_do_created.png captured');

    // 4. Surat Jalan PDF Print Preview
    console.log(`Navigating to Surat Jalan PDF #${ids.sj_id}...`);
    await page.goto(`http://127.0.0.1:8009/penjualan/surat-jalan/${ids.sj_id}/pdf`, { waitUntil: 'networkidle0' });
    await page.screenshot({
        path: '/Users/lrmcorporation/.gemini/antigravity/brain/157dfc6d-731c-4f66-adcb-a3c82dbb6767/uat_10_surat_jalan_pdf.png',
        fullPage: false
    });
    console.log('uat_10_surat_jalan_pdf.png captured');

    // 5. Sales Invoice Detail
    console.log(`Navigating to Invoice #${ids.invoice_id}...`);
    await page.goto(`http://127.0.0.1:8009/penjualan/invoice/${ids.invoice_id}`, { waitUntil: 'networkidle0' });
    await page.screenshot({
        path: '/Users/lrmcorporation/.gemini/antigravity/brain/157dfc6d-731c-4f66-adcb-a3c82dbb6767/uat_11_invoice_posted.png',
        fullPage: false
    });
    console.log('uat_11_invoice_posted.png captured');

    // 6. Customer Receipt Detail
    console.log(`Navigating to Customer Receipt #${ids.receipt_id}...`);
    await page.goto(`http://127.0.0.1:8009/penjualan/terima-pembayaran/${ids.receipt_id}`, { waitUntil: 'networkidle0' });
    await page.screenshot({
        path: '/Users/lrmcorporation/.gemini/antigravity/brain/157dfc6d-731c-4f66-adcb-a3c82dbb6767/uat_12_receipt_completed.png',
        fullPage: false
    });
    console.log('uat_12_receipt_completed.png captured');

    await browser.close();
    console.log('All UAT Screenshots captured successfully!');
})();

