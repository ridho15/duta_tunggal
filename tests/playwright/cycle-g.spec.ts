import { test, expect, Page } from '@playwright/test';
import path from 'path';
import fs from 'fs';
import { execSync } from 'child_process';

const BRAIN_SCREENSHOT_DIR = 'C:/Users/ralam/.gemini/antigravity/brain/37609e76-32d0-4a5e-8715-8e9934fbf3fd/screenshots';
const QA_EVIDENCE_DIR = path.join(process.cwd(), 'qa-evidence');

async function saveEvidence(page: Page, filename: string) {
  if (!fs.existsSync(QA_EVIDENCE_DIR)) {
    fs.mkdirSync(QA_EVIDENCE_DIR, { recursive: true });
  }
  await page.screenshot({ path: path.join(QA_EVIDENCE_DIR, filename), fullPage: false });

  if (!fs.existsSync(BRAIN_SCREENSHOT_DIR)) {
    fs.mkdirSync(BRAIN_SCREENSHOT_DIR, { recursive: true });
  }
  await page.screenshot({ path: path.join(BRAIN_SCREENSHOT_DIR, filename), fullPage: false });
}

async function selectSearchableOption(page: Page, fieldName: string, optionText: string) {
  const container = page.locator(`[x-data*="searchableSelect"]`).filter({
    has: page.locator(`input[name="${fieldName}"]`)
  }).first();
  const listbox = container.locator('ul[role="listbox"]');
  if (!(await listbox.isVisible())) {
    await container.locator('button[aria-haspopup="listbox"]').click();
  }
  await listbox.waitFor({ state: 'visible', timeout: 5000 });
  const searchInput = container.locator('input[placeholder*="Ketik untuk mencari"]');
  if (await searchInput.isVisible()) {
    await searchInput.fill(optionText);
    await page.waitForTimeout(200);
  }
  await container.locator('ul[role="listbox"] li', { hasText: optionText }).first().click();
}

test.describe.serial('Siklus G: Sub-Alur Logistik Lanjutan, Hulu Pengadaan, Nota Kredit & Siklus Akhir Aset', () => {
  let consoleErrors: string[] = [];
  let pageErrors: string[] = [];
  let createdOrNumber = '';

  test.beforeAll(() => {
    console.log('[Setup] Menjalankan scripts/reset-cycle-g-test-data.php...');
    const out = execSync('php scripts/reset-cycle-g-test-data.php', { encoding: 'utf-8' });
    console.log(out);
  });

  test.beforeEach(async ({ page }) => {
    test.setTimeout(60000);
    consoleErrors = [];
    pageErrors = [];

    page.on('console', msg => {
      if (msg.type() === 'error') {
        const text = msg.text();
        if (!text.includes('403 (Forbidden)')) {
          consoleErrors.push(text);
        }
      }
    });
    page.on('pageerror', err => {
      pageErrors.push(err.message);
    });

    await page.goto('http://localhost:8009/dev-login');
    await page.waitForURL('**/dashboard', { timeout: 15000 });
  });

  test.afterEach(async ({}, testInfo) => {
    console.log(`[Console/Exception Log] ${testInfo.title}: console.error=${consoleErrors.length}, pageerror=${pageErrors.length}`);
    expect(pageErrors, `Uncaught JS exception detected: ${pageErrors.join('; ')}`).toEqual([]);
    expect(consoleErrors, `Console error detected: ${consoleErrors.join('; ')}`).toEqual([]);
  });

  // =========================================================================
  // G-01: HULU PENGADAAN (OR) - UNHAPPY VALIDATION (BARIS ITEM KOSONG)
  // =========================================================================
  test('G-01: Permintaan Pembelian (OR) - Unhappy Validation Item Kosong', async ({ page }) => {
    await page.goto('http://localhost:8009/pembelian/permintaan/create');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('h1')).toContainText(/Permintaan Pembelian/i);

    // Submit form tanpa memilih produk pada baris item (#orForm)
    await page.evaluate(() => {
      const form = document.getElementById('orForm') as HTMLFormElement | null;
      if (form) {
        form.noValidate = true;
      }
    });
    await page.click('button[name="action"][value="draft"]');
    await page.waitForLoadState('networkidle');

    expect(page.url()).toContain('/pembelian/permintaan/create');
    await expect(page.locator('body')).toContainText(/Produk|Daftar Barang|wajib diisi/i);
    await saveEvidence(page, 'g_01_or_unhappy.png');
  });

  // =========================================================================
  // G-02: HULU PENGADAAN (OR) - QUICK ADD, DOUBLE-SUBMIT GUARD & APPROVE
  // =========================================================================
  test('G-02: Permintaan Pembelian (OR) - Quick Add, Submit & Approve', async ({ page }) => {
    await page.goto('http://localhost:8009/pembelian/permintaan/create');
    await page.waitForLoadState('networkidle');

    createdOrNumber = await page.locator('input[name="request_number"]').inputValue();
    expect(createdOrNumber).toMatch(/^OR-/);
    await page.fill('input[name="purpose"]', 'Pengadaan Monitor LED Cadangan Siklus G');

    // Quick-add search PRD004 -> klik hasil pertama
    await page.fill('#quickSearchInput', 'PRD004');
    await page.waitForSelector('#quick-result-0', { timeout: 10000 });
    await page.click('#quick-result-0');
    await expect(page.locator('#quickAddFeedback')).toContainText(/PRD004|Monitor/i);

    // Pastikan kuantitas = 5 pada item #1
    const qtyInput = page.locator('input[name="items[0][quantity]"]');
    await qtyInput.fill('5');

    // Double-submit tracker
    let postCount = 0;
    page.on('request', req => {
      if (req.url().endsWith('/pembelian/permintaan') && req.method() === 'POST') {
        postCount++;
      }
    });

    const submitBtn = page.locator('button[name="action"][value="request_approve"]');
    await Promise.all([
      page.waitForURL(url => /\/pembelian\/permintaan\/\d+$/.test(url.pathname), { timeout: 15000 }),
      submitBtn.dblclick()
    ]);

    expect(postCount).toBeLessThanOrEqual(1);
    await expect(page.locator('body')).toContainText(createdOrNumber);
    await expect(page.locator('body')).toContainText(/Menunggu Approval/i);

    // Approve Order Request via modal
    await page.click('button:has-text("Setujui Permintaan")');
    await page.waitForSelector('button:has-text("Ya, Setujui Permintaan")', { timeout: 5000 });
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle', timeout: 15000 }),
      page.click('button:has-text("Ya, Setujui Permintaan")')
    ]);
    await page.waitForTimeout(150);

    await expect(page.locator('body')).toContainText(/Disetujui/i);
    await expect(page.locator('body')).toContainText(/Terbitkan Purchase Order \(PO\)/i);
    await saveEvidence(page, 'g_02_or_approved.png');
  });

  // =========================================================================
  // G-03: LOGISTIK LANJUTAN - CHECKER KIRIMAN GUDANG & PICKING LIST (MOBILE)
  // =========================================================================
  test('G-03: Siapkan & Kirim Gudang (Checker) - Checklist Guard & Mobile Viewport', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    await page.goto('http://localhost:8009/gudang/kiriman?warehouse_id=1&tab=siap_dicek');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText('DO-G-2026-001');

    // Masuk ke halaman check untuk DO-G-2026-001
    const doCard = page.locator('div, a').filter({ hasText: 'DO-G-2026-001' }).first();
    const checkLink = page.locator('a[href*="/gudang/kiriman/"][href*="/check"]').filter({
      hasText: /Mulai cek|Cek/i
    });
    // Cari link check di blok kartu DO-G-2026-001
    const targetCard = page.locator('.rounded-2xl, .border').filter({ hasText: 'DO-G-2026-001' }).last();
    await targetCard.locator('a[href*="/check"]').click();
    await page.waitForLoadState('networkidle');

    await expect(page.locator('h1')).toContainText('DO-G-2026-001');

    // Unhappy guard: sebelum centang barang, tombol KIRIM harus disabled
    const kirimBtn = page.locator('#submitKirimBtn');
    await expect(kirimBtn).toBeDisabled();
    await expect(page.locator('#checkedStatusText')).toContainText('0 dari');
    await saveEvidence(page, 'g_03a_kiriman_unhappy_disabled.png');

    // Centang semua checkbox checker
    const checkboxes = page.locator('input.checker-box[name="checked_items[]"]');
    const count = await checkboxes.count();
    expect(count).toBeGreaterThan(0);
    for (let i = 0; i < count; i++) {
      await checkboxes.nth(i).check();
    }

    await expect(kirimBtn).toBeEnabled();
    await Promise.all([
      page.waitForURL(url => url.pathname === '/gudang/kiriman', { timeout: 15000 }),
      kirimBtn.click()
    ]);

    await expect(page.locator('body')).toContainText(/DO-G-2026-001|berhasil|dicek/i);
    await saveEvidence(page, 'g_03b_kiriman_checked_mobile.png');

    // Cek Picking List view
    await page.goto('http://localhost:8009/gudang/kiriman/picking-list?warehouse_id=1');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Picking List|Siapkan/i);
  });

  // =========================================================================
  // G-04: JADWAL KIRIM & ARMADA - CREATE, START (ON THE WAY), COMPLETE (DELIVERED)
  // =========================================================================
  test('G-04: Jadwal Pengiriman Armada - Create, Start & Complete (DS-G-2026-001)', async ({ page }) => {
    await page.goto('http://localhost:8009/penjualan/jadwal-kirim/create');
    await page.waitForLoadState('networkidle');

    await page.fill('input[name="schedule_number"]', 'DS-G-2026-001');
    await selectSearchableOption(page, 'driver_id', 'Pak Joko Santoso');
    await selectSearchableOption(page, 'vehicle_id', 'B 9901 DT');

    // Pilih DO-G-2026-001
    const doLabel = page.locator('label').filter({ hasText: 'DO-G-2026-001' }).first();
    await doLabel.locator('input[type="checkbox"][name="delivery_order_ids[]"]').check();

    await Promise.all([
      page.waitForURL(url => /\/penjualan\/jadwal-kirim\/\d+$/.test(url.pathname), { timeout: 15000 }),
      page.click('button:has-text("Simpan & Terbitkan Jadwal")')
    ]);

    await expect(page.locator('body')).toContainText('DS-G-2026-001');
    await expect(page.locator('body')).toContainText(/Menunggu Keberangkatan/i);

    // Guard check: Tombol "Tandai Selesai & Diterima" tidak tersedia saat pending
    await expect(page.locator('button:has-text("Tandai Selesai & Diterima")')).toHaveCount(0);

    // 1. Mulai Pengiriman -> on_the_way
    await page.click('button:has-text("Mulai Pengiriman")');
    await page.waitForSelector('button:has-text("Ya, Mulai Pengiriman")', { timeout: 5000 });
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle', timeout: 15000 }),
      page.click('button:has-text("Ya, Mulai Pengiriman")')
    ]);
    await page.waitForTimeout(150);
    await expect(page.locator('body')).toContainText(/Dalam Perjalanan|Sedang Dikirim/i);

    // 2. Tandai Selesai & Diterima -> delivered + DO completed
    await page.click('button:has-text("Tandai Selesai & Diterima")');
    await page.waitForSelector('button:has-text("Ya, Selesaikan Jadwal")', { timeout: 5000 });
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle', timeout: 15000 }),
      page.click('button:has-text("Ya, Selesaikan Jadwal")')
    ]);
    await page.waitForTimeout(150);

    await expect(page.locator('body')).toContainText(/Selesai|Terkirim/i);
    await expect(page.locator('body')).toContainText('DO-G-2026-001');
    await saveEvidence(page, 'g_04_schedule_delivered.png');
  });

  // =========================================================================
  // G-05: NOTA KREDIT PENJUALAN - LIVE TAX PREVIEW & UNHAPPY VALIDATION
  // =========================================================================
  test('G-05: Nota Kredit Penjualan - Live Preview PPN & Unhappy Guards', async ({ page }) => {
    await page.goto('http://localhost:8009/penjualan/nota-kredit/create?customer_id=1');
    await page.waitForLoadState('networkidle');

    // Pilih Invoice INV-G-2026-001 (select[data-searchable] disembunyikan oleh select-enhancer)
    await page.waitForFunction(() => {
      const sel = document.querySelector('select[name="invoice_id"]') as HTMLSelectElement | null;
      return Boolean(sel && Array.from(sel.options).some(o => (o.textContent || '').includes('INV-G-2026-001')));
    }, { timeout: 10000 });

    await page.evaluate(() => {
      const sel = document.querySelector('select[name="invoice_id"]') as HTMLSelectElement;
      const opt = Array.from(sel.options).find(o => (o.textContent || '').includes('INV-G-2026-001'));
      if (opt) {
        sel.value = opt.value;
        sel.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });

    // Tunggu rincian item termuat dari AJAX
    await page.waitForSelector('input[name^="quantities["]', { timeout: 10000 });
    const qtyInput = page.locator('input[name^="quantities["]').first();
    await qtyInput.fill('2');
    await qtyInput.dispatchEvent('input');

    // Verifikasi fix preview bug: Subtotal 2.000.000, PPN 220.000, Grand Total 2.220.000
    await expect(page.locator('body')).toContainText('2.000.000');
    await expect(page.locator('body')).toContainText('220.000');
    await expect(page.locator('body')).toContainText('2.220.000');
    await saveEvidence(page, 'g_05a_cn_live_preview.png');

    // Unhappy 1: Alasan < 10 karakter memblokir submit button
    await page.fill('textarea[name="reason"]', 'Rusak');
    await expect(page.locator('button:has-text("Simpan Draf Nota Kredit")')).toBeDisabled();

    // Unhappy 2: Bypass DOM max & isi qty melebihi sisa creditable (99) + reason valid untuk uji server validation
    await page.fill('textarea[name="reason"]', 'Uji penolakan kuantitas melebihi sisa tagihan oleh backend');
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle', timeout: 15000 }),
      page.evaluate(() => {
        const q = document.querySelector('input[name^="quantities["]') as HTMLInputElement | null;
        if (q) {
          q.removeAttribute('max');
          q.value = '99';
        }
        const form = document.querySelector('main form[action*="nota-kredit"], main form') as HTMLFormElement | null;
        if (form) {
          form.noValidate = true;
          form.submit();
        }
      })
    ]);
    expect(page.url()).toContain('/penjualan/nota-kredit/create');
    await expect(page.locator('body')).toContainText(/melebihi sisa|kesalahan/i);
  });

  // =========================================================================
  // G-06: NOTA KREDIT PENJUALAN - CREATE DRAFT, ISSUE & JURNAL BALIK OTOMATIS
  // =========================================================================
  test('G-06: Nota Kredit Penjualan - Draft, Terbit (Issue) & Balanced Journal', async ({ page }) => {
    await page.goto('http://localhost:8009/penjualan/nota-kredit/create?customer_id=1');
    await page.waitForLoadState('networkidle');

    await page.waitForFunction(() => {
      const sel = document.querySelector('select[name="invoice_id"]') as HTMLSelectElement | null;
      return Boolean(sel && Array.from(sel.options).some(o => (o.textContent || '').includes('INV-G-2026-001')));
    }, { timeout: 10000 });

    await page.evaluate(() => {
      const sel = document.querySelector('select[name="invoice_id"]') as HTMLSelectElement;
      const opt = Array.from(sel.options).find(o => (o.textContent || '').includes('INV-G-2026-001'));
      if (opt) {
        sel.value = opt.value;
        sel.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
    await page.waitForSelector('input[name^="quantities["]', { timeout: 10000 });

    const qtyInput = page.locator('input[name^="quantities["]').first();
    await qtyInput.fill('2');
    await qtyInput.dispatchEvent('input');
    await page.fill('textarea[name="reason"]', 'Koreksi kuantitas cacat 2 unit pada faktur INV-G-2026-001');

    const submitBtn = page.locator('button:has-text("Simpan Draf Nota Kredit")');
    await expect(submitBtn).toBeEnabled();
    await Promise.all([
      page.waitForURL(url => /\/penjualan\/nota-kredit\/\d+$/.test(url.pathname), { timeout: 15000 }),
      submitBtn.click()
    ]);

    await expect(page.locator('body')).toContainText('Draf');
    await expect(page.locator('body')).toContainText('2.220.000');

    // Terbitkan Nota Kredit via modal
    await page.click('button:has-text("Terbitkan Nota Kredit")');
    await page.waitForSelector('button:has-text("Ya, Terbitkan Sekarang")', { timeout: 5000 });
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle', timeout: 15000 }),
      page.click('button:has-text("Ya, Terbitkan Sekarang")')
    ]);
    await page.waitForTimeout(150);

    await expect(page.locator('body')).toContainText(/Terbit \(Issued\)/i);
    await expect(page.locator('body')).toContainText(/4120\.10|2120\.06|1120/);
    await saveEvidence(page, 'g_06_cn_issued.png');
  });

  // =========================================================================
  // G-07: MUTASI ASET ANTAR CABANG - UNHAPPY SAME CABANG & HAPPY COMPLETE
  // =========================================================================
  test('G-07: Mutasi Aset Antar Cabang - Unhappy Same Branch & Complete (AST-G-2026-001)', async ({ page }) => {
    await page.goto('http://localhost:8009/keuangan/aset/transfer/create');
    await page.waitForLoadState('networkidle');

    // Pilih AST-G-2026-001 via native select (bypass/sync select-enhancer cleanly)
    const assetOptionVal = await page.locator('#assetSelect option', { hasText: 'AST-G-2026-001' }).getAttribute('value');
    expect(assetOptionVal).toBeTruthy();
    await page.evaluate((val) => {
      const sel = document.getElementById('assetSelect') as HTMLSelectElement;
      if (sel && val) {
        sel.value = val;
        sel.dispatchEvent(new Event('change', { bubbles: true }));
      }
    }, assetOptionVal);

    // Unhappy: pilih cabang tujuan = Cabang 1 (sama dengan cabang asal AST-G-2026-001)
    await selectSearchableOption(page, 'to_cabang_id', 'Cabang Bima');
    await page.fill('textarea[name="reason"]', 'Uji penolakan mutasi ke cabang yang sama persis');
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle', timeout: 15000 }),
      page.click('button:has-text("Simpan & Ajukan Mutasi")')
    ]);
    await page.waitForTimeout(200);
    await expect(page.locator('body')).toContainText(/Cabang tujuan harus berbeda/i);
    await saveEvidence(page, 'g_07a_transfer_unhappy.png');

    // Happy: pilih cabang tujuan = Cabang Surabaya (Cabang 2)
    await page.evaluate((val) => {
      const sel = document.getElementById('assetSelect') as HTMLSelectElement;
      if (sel && val) {
        sel.value = val;
        sel.dispatchEvent(new Event('change', { bubbles: true }));
      }
    }, assetOptionVal);
    await selectSearchableOption(page, 'to_cabang_id', 'Cabang Surabaya');
    await page.fill('textarea[name="reason"]', 'Relokasi Forklift Listrik Siklus G ke Cabang Surabaya');

    await Promise.all([
      page.waitForURL(url => /\/keuangan\/aset\/transfer\/\d+$/.test(url.pathname), { timeout: 15000 }),
      page.click('button:has-text("Simpan & Ajukan Mutasi")')
    ]);

    await expect(page.locator('body')).toContainText(/Menunggu Persetujuan/i);

    // Approve Mutasi
    await page.click('button:has-text("Setujui Mutasi (Approve)")');
    await page.waitForSelector('button:has-text("Ya, Setujui Mutasi")', { timeout: 5000 });
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle', timeout: 15000 }),
      page.click('button:has-text("Ya, Setujui Mutasi")')
    ]);
    await page.waitForTimeout(150);
    await expect(page.locator('body')).toContainText(/Disetujui/i);

    // Complete Mutasi
    await page.click('button:has-text("Konfirmasi Selesai Serah Terima (Complete)")');
    await page.waitForSelector('button:has-text("Ya, Selesaikan Mutasi")', { timeout: 5000 });
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle', timeout: 15000 }),
      page.click('button:has-text("Ya, Selesaikan Mutasi")')
    ]);
    await page.waitForTimeout(150);
    await expect(page.locator('body')).toContainText(/Selesai \(Completed\)/i);
    await saveEvidence(page, 'g_07b_transfer_completed.png');
  });

  // =========================================================================
  // G-08: PELEPASAN / DISPOSAL ASET TETAP - GAIN JOURNAL & ANTI-DOUBLE DISPOSAL
  // =========================================================================
  test('G-08: Pelepasan Aset Tetap (Sale Disposal & Gain Journal) AST-G-2026-002', async ({ page }) => {
    await page.goto('http://localhost:8009/keuangan/aset/disposal/create');
    await page.waitForLoadState('networkidle');

    const assetVal = await page.locator('#assetSelect option', { hasText: 'AST-G-2026-002' }).getAttribute('value');
    expect(assetVal).toBeTruthy();
    await page.evaluate((val) => {
      const sel = document.getElementById('assetSelect') as HTMLSelectElement;
      if (sel && val) {
        sel.value = val;
        sel.dispatchEvent(new Event('change', { bubbles: true }));
      }
    }, assetVal);

    await page.selectOption('#disposalTypeSelect', 'sale');
    await page.fill('#salePriceInput', '45000000');
    await page.locator('#salePriceInput').dispatchEvent('input');

    // Pastikan estimasi keuntungan (45jt - 40jt NBV = +5jt) muncul secara live
    await expect(page.locator('#gainLossLabel')).toContainText(/Keuntungan Pelepasan/i);
    await expect(page.locator('#gainLossValue')).toContainText('5.000.000');

    await page.fill('textarea[name="notes"]', 'Penjualan unit Mesin Generator Cadangan Siklus G secara tunai');

    await Promise.all([
      page.waitForURL(url => /\/keuangan\/aset\/disposal\/\d+$/.test(url.pathname), { timeout: 15000 }),
      page.click('button:has-text("Proses Pelepasan & Posting Jurnal")')
    ]);

    await expect(page.locator('body')).toContainText(/Disposed/i);
    await expect(page.locator('body')).toContainText('55.000.000');
    await expect(page.locator('body')).toContainText(/4 Baris Jurnal \(Seimbang\)/i);
    await saveEvidence(page, 'g_08a_disposal_detail.png');

    // Unhappy: pastikan aset yang sudah disposed tidak muncul lagi di dropdown disposal
    await page.goto('http://localhost:8009/keuangan/aset/disposal/create');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('#assetSelect option', { hasText: 'AST-G-2026-002' })).toHaveCount(0);
  });

  // =========================================================================
  // G-09: RBAC SECURITY BOUNDARY CHECK (403 FORBIDDEN LINTAS PERAN)
  // =========================================================================
  test('G-09: RBAC Security Boundary Check (Warehouse Staff & Sales)', async ({ browser }) => {
    const context = await browser.newContext({ storageState: { cookies: [], origins: [] } });
    const staffPage = await context.newPage();

    await staffPage.goto('http://localhost:8009/login');
    await staffPage.fill('input[name="login"]', 'warehouse_staff@example.com');
    await staffPage.fill('input[name="password"]', 'password');
    await staffPage.click('button[type="submit"]');
    await staffPage.waitForURL('**/dashboard', { timeout: 15000 });

    // Warehouse Staff DILARANG mengakses Nota Kredit, Jadwal Kirim, Disposal, Transfer Aset
    for (const forbiddenUrl of [
      '/penjualan/nota-kredit',
      '/penjualan/jadwal-kirim',
      '/keuangan/aset/disposal',
      '/keuangan/aset/transfer',
    ]) {
      const res = await staffPage.goto(`http://localhost:8009${forbiddenUrl}`);
      expect(res?.status(), `URL ${forbiddenUrl} wajib ditolak 403`).toBe(403);
    }

    // Warehouse Staff BOLEH akses /gudang/kiriman
    const allowedRes = await staffPage.goto('http://localhost:8009/gudang/kiriman');
    expect(allowedRes?.status()).toBe(200);

    // Uji login Sales (dilarang ke Gudang Kiriman & Nota Kredit)
    const salesCtx = await browser.newContext({ storageState: { cookies: [], origins: [] } });
    const salesPage = await salesCtx.newPage();
    await salesPage.goto('http://localhost:8009/login');
    await salesPage.fill('input[name="login"]', 'sales@example.com');
    await salesPage.fill('input[name="password"]', 'password');
    await salesPage.click('button[type="submit"]');
    await salesPage.waitForURL('**/dashboard', { timeout: 15000 });

    const forbiddenGudang = await salesPage.goto('http://localhost:8009/gudang/kiriman');
    expect(forbiddenGudang?.status()).toBe(403);
    const forbiddenCn = await salesPage.goto('http://localhost:8009/penjualan/nota-kredit');
    expect(forbiddenCn?.status()).toBe(403);
    await saveEvidence(salesPage, 'g_09_rbac_403_boundary.png');

    await context.close();
    await salesCtx.close();
  });

  // =========================================================================
  // G-10: HARD REFRESH PERSISTENCE & 3-WAY INDEPENDENT VERIFICATION
  // =========================================================================
  test('G-10: Hard Refresh Persistence & Verifikasi Independen DB (verify-g-cycle.php)', async ({ page }) => {
    for (const check of [
      { url: '/pembelian/permintaan', expected: createdOrNumber || 'Pengadaan Monitor LED Cadangan Siklus G' },
      { url: '/penjualan/jadwal-kirim', expected: 'DS-G-2026-001' },
      { url: '/penjualan/nota-kredit', expected: 'PT Maju Bersama' },
      { url: '/keuangan/aset/transfer', expected: 'AST-G-2026-001' },
      { url: '/keuangan/aset/disposal', expected: 'AST-G-2026-002' },
    ]) {
      await page.goto(`http://localhost:8009${check.url}`);
      await page.waitForLoadState('networkidle');
      await page.reload({ waitUntil: 'networkidle' });
      await expect(page.locator('body')).toContainText(check.expected);
    }

    await saveEvidence(page, 'g_10_hard_refresh_persistence.png');

    console.log('[Audit] Menjalankan scripts/verify-g-cycle.php...');
    const auditOut = execSync('php scripts/verify-g-cycle.php', { encoding: 'utf-8' });
    console.log(auditOut);
    expect(auditOut).toContain('100% EXACT MATCH / REKONSILIASI SEMPURNA');
  });
});

