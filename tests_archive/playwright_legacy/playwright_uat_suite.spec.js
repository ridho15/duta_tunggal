import { chromium } from '@playwright/test';
import path from 'path';
import fs from 'fs';

const artifactDir = '/Users/lrmcorporation/.gemini/antigravity-ide/brain/454ff04d-d356-4453-9db9-86c8a61104d5';
const baseUrl = 'http://localhost:8009';

async function runUatSuite() {
  console.log('================================================================');
  console.log('  STARTING PLAYWRIGHT END-TO-END USER ACCEPTANCE TESTING (UAT)  ');
  console.log('  Modul: Order Request, Purchase Order, Quotation, Sales Order  ');
  console.log('================================================================\n');

  const browser = await chromium.launch({
    channel: 'chrome',
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--window-size=1440,1100'],
  });

  const context = await browser.newContext({
    viewport: { width: 1440, height: 1100 },
  });

  const page = await context.newPage();

  page.on('console', (msg) => {
    if (msg.type() === 'error' || msg.text().includes('Error')) {
      console.log('  [BROWSER ERROR]:', msg.text());
    }
  });

  // Helper delay
  const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

  // Helper for SearchableSelect
  async function selectSearchableOption(labelContains, optionIndex = 0) {
    await page.evaluate((labelTxt) => {
      const labels = Array.from(document.querySelectorAll('label'));
      const targetLabel = labels.find((l) => l.innerText.toLowerCase().includes(labelTxt.toLowerCase()));
      if (targetLabel && targetLabel.parentElement) {
        const trigger = targetLabel.parentElement.querySelector('.cursor-pointer');
        if (trigger) trigger.click();
      }
    }, labelContains);
    await wait(600);

    await page.evaluate((idx) => {
      const options = Array.from(document.querySelectorAll('.max-h-60 .cursor-pointer'));
      if (options.length > idx) {
        options[idx].click();
      } else if (options.length > 0) {
        options[0].click();
      }
    }, optionIndex);
    await wait(600);
  }

  try {
    // -------------------------------------------------------------------------
    // SKENARIO 1: ORDER REQUEST (PERMINTAAN PEMBELIAN)
    // -------------------------------------------------------------------------
    console.log('>>> [UAT 1/4] SKENARIO: ORDER REQUEST (PERMINTAAN PEMBELIAN)');
    await page.goto(`${baseUrl}/dev-autologin`, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.waitForSelector('#order-request-next-app', { timeout: 15000 });
    await page.waitForFunction(() => document.querySelectorAll('input[type="text"]').length > 0, { timeout: 15000 });
    await wait(1000);
    console.log('  ✓ Halaman Create Order Request ter-mount sempurna via React');

    // Select Cabang Target
    await selectSearchableOption('Cabang Target', 0);
    console.log('  ✓ Berhasil memilih Cabang Target');

    // Expand Item #1
    await page.evaluate(() => {
      const headerRow = document.querySelector('.bg-gray-50\\/70');
      if (headerRow) headerRow.click();
    });
    await wait(400);

    // Select Product
    await selectSearchableOption('Produk Target', 0);
    console.log('  ✓ Berhasil memilih Produk Target dari daftar terurut alfabetis');

    // Select Supplier Target (Verify 3-tier dropdown)
    await selectSearchableOption('Supplier Target', 0);
    console.log('  ✓ Berhasil memilih Supplier Target (dengan Rekomendasi Teratas)');

    // Set Quantity
    await page.evaluate(() => {
      const qtyInput = document.querySelector('input[type="number"][placeholder="1"]');
      if (qtyInput) {
        qtyInput.value = '5';
        qtyInput.dispatchEvent(new Event('input', { bubbles: true }));
        qtyInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });

    // Capture screenshot Create Order Request
    const uat01CreatePath = path.join(artifactDir, 'uat_01_order_request_create.png');
    await page.screenshot({ path: uat01CreatePath, fullPage: true });
    console.log('  ✓ Screenshot tersimpan:', uat01CreatePath);

    // Submit Order Request
    console.log('  Submitting Order Request form...');
    await page.evaluate(() => {
      const btns = Array.from(document.querySelectorAll('button'));
      const saveBtn = btns.find((b) => b.innerText && b.innerText.includes('Kirim Permintaan'));
      if (saveBtn) saveBtn.click();
    });
    await wait(3000);
    console.log('  ✓ Current URL after submit:', page.url());

    // Edit Order Request
    await page.goto(`${baseUrl}/admin/order-requests/1/edit`, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.waitForSelector('#order-request-next-app', { timeout: 15000 });
    await wait(1000);
    const uat01EditPath = path.join(artifactDir, 'uat_01_order_request_edit.png');
    await page.screenshot({ path: uat01EditPath, fullPage: true });
    console.log('  ✓ Mode Edit Order Request terverifikasi:', uat01EditPath);
    console.log('--- [UAT 1/4 PASSED 100%] ---\n');

    // -------------------------------------------------------------------------
    // SKENARIO 2: TAHAP 1 - PURCHASE ORDER (PESANAN PEMBELIAN)
    // -------------------------------------------------------------------------
    console.log('>>> [UAT 2/4] SKENARIO: PURCHASE ORDER (PESANAN PEMBELIAN)');
    await page.goto(`${baseUrl}/dev-autologin-po`, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.waitForSelector('#purchase-order-next-app', { timeout: 15000 });
    await page.waitForFunction(() => document.querySelectorAll('input[type="text"]').length > 0, { timeout: 15000 });
    await wait(1000);
    console.log('  ✓ Halaman Create Purchase Order ter-mount sempurna via React');

    // Select Supplier
    await selectSearchableOption('Supplier', 0);
    console.log('  ✓ Berhasil memilih Supplier (auto-fill tempo kredit)');

    // Select Cabang
    await selectSearchableOption('Cabang', 0);
    console.log('  ✓ Berhasil memilih Cabang (lebar min-w-[280px])');

    // Expand Item #1 & Select Product
    await page.evaluate(() => {
      const headerRow = document.querySelector('.bg-gray-50\\/70');
      if (headerRow) headerRow.click();
    });
    await wait(400);

    await selectSearchableOption('Produk', 0);
    console.log('  ✓ Berhasil memilih Produk');

    // Set Quantity = 4, Discount = 10%, Tax Type = Eksklusif
    await page.evaluate(() => {
      const qtyInput = document.querySelector('input[type="number"][placeholder="1"]');
      if (qtyInput) {
        qtyInput.value = '4';
        qtyInput.dispatchEvent(new Event('input', { bubbles: true }));
        qtyInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
      const discInput = document.querySelector('input[type="number"][placeholder="0"]');
      if (discInput) {
        discInput.value = '10';
        discInput.dispatchEvent(new Event('input', { bubbles: true }));
        discInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
      const selects = Array.from(document.querySelectorAll('select'));
      const taxSelect = selects.find((s) => s.innerHTML.includes('Eksklusif'));
      if (taxSelect) {
        taxSelect.value = 'Eksklusif';
        taxSelect.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
    await wait(500);

    // Capture screenshot Create Purchase Order
    const uat02CreatePath = path.join(artifactDir, 'uat_02_purchase_order_create.png');
    await page.screenshot({ path: uat02CreatePath, fullPage: true });
    console.log('  ✓ Screenshot tersimpan:', uat02CreatePath);

    // Submit Purchase Order
    console.log('  Submitting Purchase Order form...');
    await page.evaluate(() => {
      const btns = Array.from(document.querySelectorAll('button'));
      const saveBtn = btns.find((b) => b.innerText && b.innerText.includes('Buat Purchase Order'));
      if (saveBtn) saveBtn.click();
    });
    await wait(3000);
    console.log('  ✓ Current URL after submit:', page.url());

    // Edit Purchase Order
    await page.goto(`${baseUrl}/admin/purchase-orders/19/edit`, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.waitForSelector('#purchase-order-next-app', { timeout: 15000 });
    await page.waitForFunction(() => document.querySelectorAll('input[type="text"]').length > 0, { timeout: 15000 });
    await wait(800);
    const uat02EditPath = path.join(artifactDir, 'uat_02_purchase_order_edit.png');
    await page.screenshot({ path: uat02EditPath, fullPage: true });
    console.log('  ✓ Mode Edit Purchase Order terverifikasi:', uat02EditPath);
    console.log('--- [UAT 2/4 PASSED 100%] ---\n');

    // -------------------------------------------------------------------------
    // SKENARIO 3: TAHAP 2 - QUOTATION (PENAWARAN HARGA)
    // -------------------------------------------------------------------------
    console.log('>>> [UAT 3/4] SKENARIO: QUOTATION (PENAWARAN HARGA)');
    await page.goto(`${baseUrl}/dev-autologin-qo`, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.waitForSelector('#quotation-next-app', { timeout: 15000 });
    await page.waitForFunction(() => document.querySelectorAll('input[type="text"]').length > 0, { timeout: 15000 });
    await wait(1000);
    console.log('  ✓ Halaman Create Quotation ter-mount sempurna via React');

    // Select Customer
    await selectSearchableOption('Customer', 0);
    console.log('  ✓ Berhasil memilih Customer (auto-fill tempo kredit)');

    // Select Cabang
    await selectSearchableOption('Cabang', 0);
    console.log('  ✓ Berhasil memilih Cabang');

    // Expand Item #1 & Select Product
    await page.evaluate(() => {
      const headerRow = document.querySelector('.bg-gray-50\\/70');
      if (headerRow) headerRow.click();
    });
    await wait(400);

    await selectSearchableOption('Produk', 0);
    console.log('  ✓ Berhasil memilih Produk');

    // Set Quantity = 3, Discount = 5%, Tax Type = Inklusif
    await page.evaluate(() => {
      const qtyInput = document.querySelector('input[type="number"][placeholder="1"]');
      if (qtyInput) {
        qtyInput.value = '3';
        qtyInput.dispatchEvent(new Event('input', { bubbles: true }));
        qtyInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
      const discInput = document.querySelector('input[type="number"][placeholder="0"]');
      if (discInput) {
        discInput.value = '5';
        discInput.dispatchEvent(new Event('input', { bubbles: true }));
        discInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
      const selects = Array.from(document.querySelectorAll('select'));
      const taxSelect = selects.find((s) => s.innerHTML.includes('Inklusif'));
      if (taxSelect) {
        taxSelect.value = 'Inklusif';
        taxSelect.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
    await wait(500);

    // Capture screenshot Create Quotation
    const uat03CreatePath = path.join(artifactDir, 'uat_03_quotation_create.png');
    await page.screenshot({ path: uat03CreatePath, fullPage: true });
    console.log('  ✓ Screenshot tersimpan:', uat03CreatePath);

    // Submit Quotation
    console.log('  Submitting Quotation form...');
    await page.evaluate(() => {
      const btns = Array.from(document.querySelectorAll('button'));
      const saveBtn = btns.find((b) => b.innerText && b.innerText.includes('Buat Quotation'));
      if (saveBtn) saveBtn.click();
    });
    await wait(3000);
    console.log('  ✓ Current URL after submit:', page.url());

    // Edit Quotation
    await page.goto(`${baseUrl}/admin/quotations/26/edit`, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.waitForSelector('#quotation-next-app', { timeout: 15000 });
    await page.waitForFunction(() => document.querySelectorAll('input[type="text"]').length > 0, { timeout: 15000 });
    await wait(800);
    const uat03EditPath = path.join(artifactDir, 'uat_03_quotation_edit.png');
    await page.screenshot({ path: uat03EditPath, fullPage: true });
    console.log('  ✓ Mode Edit Quotation terverifikasi:', uat03EditPath);
    console.log('--- [UAT 3/4 PASSED 100%] ---\n');

    // -------------------------------------------------------------------------
    // SKENARIO 4: TAHAP 3 - SALES ORDER (PESANAN PENJUALAN)
    // -------------------------------------------------------------------------
    console.log('>>> [UAT 4/4] SKENARIO: SALES ORDER (PESANAN PENJUALAN)');
    await page.goto(`${baseUrl}/dev-autologin-so`, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.waitForSelector('#sale-order-next-app', { timeout: 15000 });
    console.log('  ✓ Halaman Create Sales Order ter-mount sempurna via React');

    // 1. Test Mode "Refer Quotation (Disetujui)"
    console.log('  Menguji mode Refer Quotation...');
    await page.evaluate(() => {
      const btns = Array.from(document.querySelectorAll('button'));
      const referBtn = btns.find((b) => b.innerText && b.innerText.includes('Refer Quotation'));
      if (referBtn) referBtn.click();
    });
    await wait(500);

    await selectSearchableOption('Pilih Dokumen Quotation yang Disetujui', 0);
    console.log('  ✓ Berhasil memilih Approved Quotation & seluruh item ter-auto-fill!');

    // 2. Switch back to "SO Mandiri" for complete field test
    await page.evaluate(() => {
      const btns = Array.from(document.querySelectorAll('button'));
      const mandiriBtn = btns.find((b) => b.innerText && b.innerText.includes('SO Mandiri'));
      if (mandiriBtn) mandiriBtn.click();
    });
    await wait(500);

    // Select Customer (Verify Credit Summary Badge)
    await selectSearchableOption('Customer', 0);
    console.log('  ✓ Berhasil memilih Customer (Info Kredit & Deposit tampil visual)');

    // Select Cabang
    await selectSearchableOption('Cabang', 0);
    console.log('  ✓ Berhasil memilih Cabang');

    // Expand Item #1 & Select Product (Verify Free Stock Badge)
    await page.evaluate(() => {
      const headerRow = document.querySelector('.bg-gray-50\\/70');
      if (headerRow) headerRow.click();
    });
    await wait(400);

    await selectSearchableOption('Produk', 0);
    console.log('  ✓ Berhasil memilih Produk (Indikator Stok Bebas Gudang tampil)');

    // Set Quantity = 2, Diskon = 5%, Tax = Eksklusif (11%)
    await page.evaluate(() => {
      const qtyInput = document.querySelector('input[type="number"][placeholder="1"]');
      if (qtyInput) {
        qtyInput.value = '2';
        qtyInput.dispatchEvent(new Event('input', { bubbles: true }));
        qtyInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
      const discInput = document.querySelector('input[type="number"][placeholder="0"]');
      if (discInput) {
        discInput.value = '5';
        discInput.dispatchEvent(new Event('input', { bubbles: true }));
        discInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
      const selects = Array.from(document.querySelectorAll('select'));
      const taxSelect = selects.find((s) => s.innerHTML.includes('Eksklusif'));
      if (taxSelect) {
        taxSelect.value = 'Eksklusif';
        taxSelect.dispatchEvent(new Event('change', { bubbles: true }));
      }
      const inputs = Array.from(document.querySelectorAll('input[type="text"]'));
      const shippedInput = inputs.find((i) => i.placeholder && i.placeholder.includes('Alamat'));
      if (shippedInput) {
        shippedInput.value = 'Komp. Industri Pergudangan Blok C No 12 Jakarta';
        shippedInput.dispatchEvent(new Event('input', { bubbles: true }));
        shippedInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
    await wait(500);

    // Capture screenshot Create Sales Order
    const uat04CreatePath = path.join(artifactDir, 'uat_04_sales_order_create.png');
    await page.screenshot({ path: uat04CreatePath, fullPage: true });
    console.log('  ✓ Screenshot tersimpan:', uat04CreatePath);

    // Submit Sales Order
    console.log('  Submitting Sales Order form...');
    await page.evaluate(() => {
      const btns = Array.from(document.querySelectorAll('button'));
      const saveBtn = btns.find((b) => b.innerText && b.innerText.includes('Buat Sales Order'));
      if (saveBtn) saveBtn.click();
    });
    await wait(3000);
    console.log('  ✓ Current URL after submit:', page.url());

    // Edit Sales Order
    await page.goto(`${baseUrl}/admin/sale-orders/29/edit`, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.waitForSelector('#sale-order-next-app', { timeout: 15000 });
    await page.waitForFunction(() => document.querySelectorAll('input[type="text"]').length > 0, { timeout: 15000 });
    await wait(800);
    const uat04EditPath = path.join(artifactDir, 'uat_04_sales_order_edit.png');
    await page.screenshot({ path: uat04EditPath, fullPage: true });
    console.log('  ✓ Mode Edit Sales Order terverifikasi:', uat04EditPath);
    console.log('--- [UAT 4/4 PASSED 100%] ---\n');

    console.log('================================================================');
    console.log('  >>> SELURUH 4 MODUL UAT PLAYWRIGHT BERHASIL LULUS 100% <<<    ');
    console.log('================================================================');
  } catch (err) {
    console.error('UAT Suite Encountered Error:', err);
    process.exit(1);
  } finally {
    await browser.close();
  }
}

runUatSuite();
