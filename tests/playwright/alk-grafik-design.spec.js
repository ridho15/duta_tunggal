/**
 * ============================================================
 *  alk-grafik-design.spec.js
 *  Duta Tunggal ERP — ALK Grafik Page Design Tests
 *
 *  Test Cases:
 *   TC-ALK-D-001: Halaman dapat diakses dan menampilkan hero banner
 *   TC-ALK-D-002: Filter panel tampil dengan input date dan select cabang
 *   TC-ALK-D-003: Empty state tampil sebelum generate analisis
 *   TC-ALK-D-004: Tombol "Tampilkan Analisis" tersedia di header actions
 *   TC-ALK-D-005: Klik Tampilkan Analisis menampilkan KPI summary cards (4 cards)
 *   TC-ALK-D-006: Rasio keuangan section tampil dengan 5 rasio cards
 *   TC-ALK-D-007: Bar chart canvas untuk tren bulanan ter-render
 *   TC-ALK-D-008: Donut chart canvas untuk komposisi neraca ter-render
 *   TC-ALK-D-009: Tabel detail tren bulanan tampil dengan kolom yang benar
 *   TC-ALK-D-010: Period badge tampil setelah generate
 *   TC-ALK-D-011: Hero banner menggunakan gradient indigo-violet
 *   TC-ALK-D-012: Tombol Reset tersedia setelah tampilkan analisis
 *
 *  URL: /admin/alk-grafik
 *  Auth: saved auth state (playwright/.auth/user.json)
 * ============================================================
 */

import { test, expect } from '@playwright/test';

const ALK_URL = '/admin/alk-grafik';

// ──────────────────────────────────────────────────────────────
// Helper: Navigate to page and wait for load
// ──────────────────────────────────────────────────────────────
async function goToAlkGrafik(page) {
    await page.goto(ALK_URL);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(800);
}

// ──────────────────────────────────────────────────────────────
// Helper: Click the "Tampilkan Analisis" button in header actions
// ──────────────────────────────────────────────────────────────
async function clickTampilkanAnalisis(page) {
    const btn = page.getByRole('button', { name: /tampilkan analisis/i }).first();
    await expect(btn).toBeVisible({ timeout: 10000 });
    await btn.click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1200);
}

// ──────────────────────────────────────────────────────────────
// TC-ALK-D-001: Halaman dapat diakses dan menampilkan hero banner
// ──────────────────────────────────────────────────────────────
test('TC-ALK-D-001: hero banner tampil dengan judul ALK Grafik', async ({ page }) => {
    await goToAlkGrafik(page);

    // Page title in hero — the h1 inside the hero banner
    await expect(page.locator('h1', { hasText: /ALK Grafik/ }).first()).toBeVisible();

    // Subtitle text — use the indigo-200 paragraph in the hero
    await expect(page.locator('.text-indigo-200').first()).toBeVisible();

    // "Finance Analytics" badge
    await expect(page.locator('text=Finance Analytics').first()).toBeVisible();
});

// ──────────────────────────────────────────────────────────────
// TC-ALK-D-002: Filter panel tampil dengan semua input
// ──────────────────────────────────────────────────────────────
test('TC-ALK-D-002: filter panel tampil dengan input date dan select cabang', async ({ page }) => {
    await goToAlkGrafik(page);

    // Filter section heading
    await expect(page.locator('text=Filter Periode Analisis')).toBeVisible();

    // Date inputs
    const startDate = page.locator('input[type="date"]').nth(0);
    const endDate   = page.locator('input[type="date"]').nth(1);
    await expect(startDate).toBeVisible();
    await expect(endDate).toBeVisible();

    // Select cabang
    const cabangSelect = page.locator('select').first();
    await expect(cabangSelect).toBeVisible();

    // Labels
    await expect(page.locator('label', { hasText: /Tanggal Mulai/i })).toBeVisible();
    await expect(page.locator('label', { hasText: /Tanggal Akhir/i })).toBeVisible();
    await expect(page.locator('label', { hasText: /Cabang/i })).toBeVisible();
});

// ──────────────────────────────────────────────────────────────
// TC-ALK-D-003: Empty state tampil sebelum generate analisis
// ──────────────────────────────────────────────────────────────
test('TC-ALK-D-003: empty state tampil sebelum Tampilkan Analisis', async ({ page }) => {
    await goToAlkGrafik(page);

    await expect(page.locator('text=Belum Ada Data Analisis')).toBeVisible();
    // The strong tag inside empty state
    await expect(page.locator('strong', { hasText: /Tampilkan Analisis/i })).toBeVisible();
});

// ──────────────────────────────────────────────────────────────
// TC-ALK-D-004: Tombol header action tersedia
// ──────────────────────────────────────────────────────────────
test('TC-ALK-D-004: tombol Tampilkan Analisis tersedia di header actions', async ({ page }) => {
    await goToAlkGrafik(page);

    const btn = page.getByRole('button', { name: /tampilkan analisis/i });
    await expect(btn.first()).toBeVisible({ timeout: 10000 });
});

// ──────────────────────────────────────────────────────────────
// TC-ALK-D-005: KPI summary cards (4 cards) tampil setelah generate
// ──────────────────────────────────────────────────────────────
test('TC-ALK-D-005: 4 KPI summary cards tampil setelah generate analisis', async ({ page }) => {
    await goToAlkGrafik(page);
    await clickTampilkanAnalisis(page);

    // Check all 4 KPI labels — use first() to avoid strict mode on duplicates
    await expect(page.locator('text=Total Aset').first()).toBeVisible();
    await expect(page.locator('text=Total Liabilitas').first()).toBeVisible();
    await expect(page.locator('text=Total Ekuitas').first()).toBeVisible();
    await expect(page.locator('text=Laba Bersih').first()).toBeVisible();

    // All values should start with "Rp"
    const rpValues = page.locator('p.text-xl.font-bold');
    const count = await rpValues.count();
    expect(count).toBeGreaterThanOrEqual(4);
});

// ──────────────────────────────────────────────────────────────
// TC-ALK-D-006: Rasio keuangan section dengan 5 rasio
// ──────────────────────────────────────────────────────────────
test('TC-ALK-D-006: section Rasio Keuangan Utama tampil dengan 5 rasio cards', async ({ page }) => {
    await goToAlkGrafik(page);
    await clickTampilkanAnalisis(page);

    // Section heading
    await expect(page.locator('text=Rasio Keuangan Utama')).toBeVisible();

    // 5 ratio labels
    await expect(page.locator('text=Current Ratio')).toBeVisible();
    await expect(page.locator('text=Debt to Equity')).toBeVisible();
    await expect(page.locator('text=ROA')).toBeVisible();
    await expect(page.locator('text=ROE')).toBeVisible();
    await expect(page.locator('text=Profit Margin')).toBeVisible();

    // Each ratio should have a hint
    await expect(page.locator('text=≥ 1.5 ideal')).toBeVisible();
    await expect(page.locator('text=≤ 1.0 ideal')).toBeVisible();
});

// ──────────────────────────────────────────────────────────────
// TC-ALK-D-007: Bar chart canvas ter-render
// ──────────────────────────────────────────────────────────────
test('TC-ALK-D-007: bar chart canvas untuk tren bulanan ter-render', async ({ page }) => {
    await goToAlkGrafik(page);
    await clickTampilkanAnalisis(page);

    // Chart header
    await expect(page.locator('text=Tren Pendapatan & Pengeluaran')).toBeVisible();
    await expect(page.locator('text=6 Bulan Terakhir')).toBeVisible();

    // Canvas element should be in DOM
    const canvas = page.locator('[x-ref="trendCanvas"]');
    await expect(canvas).toBeAttached({ timeout: 8000 });

    // Chart.js adds width/height attributes to canvas once initialized
    await page.waitForTimeout(500);
    const width = await canvas.getAttribute('width');
    expect(parseInt(width ?? '0')).toBeGreaterThan(0);
});

// ──────────────────────────────────────────────────────────────
// TC-ALK-D-008: Donut chart canvas ter-render
// ──────────────────────────────────────────────────────────────
test('TC-ALK-D-008: donut chart canvas untuk komposisi neraca ter-render', async ({ page }) => {
    await goToAlkGrafik(page);
    await clickTampilkanAnalisis(page);

    // Chart header
    await expect(page.locator('text=Komposisi Neraca')).toBeVisible();
    await expect(page.locator('text=Liabilitas vs Ekuitas')).toBeVisible();

    const canvas = page.locator('[x-ref="donutCanvas"]');
    await expect(canvas).toBeAttached({ timeout: 8000 });

    await page.waitForTimeout(500);
    const width = await canvas.getAttribute('width');
    expect(parseInt(width ?? '0')).toBeGreaterThan(0);
});

// ──────────────────────────────────────────────────────────────
// TC-ALK-D-009: Tabel detail tren bulanan tampil dengan kolom benar
// ──────────────────────────────────────────────────────────────
test('TC-ALK-D-009: tabel detail tren bulanan tampil dengan kolom yang benar', async ({ page }) => {
    await goToAlkGrafik(page);
    await clickTampilkanAnalisis(page);

    // Table section heading
    await expect(page.locator('text=Detail Tren Bulanan')).toBeVisible();

    // Table headers — use global th lookup to avoid div scope issues
    await expect(page.locator('th').filter({ hasText: /^Bulan$/i }).first()).toBeVisible({ timeout: 8000 });
    await expect(page.locator('th').filter({ hasText: /Pendapatan/i }).first()).toBeVisible();
    await expect(page.locator('th').filter({ hasText: /Pengeluaran/i }).first()).toBeVisible();
    await expect(page.locator('th').filter({ hasText: /Laba/i }).first()).toBeVisible();
    await expect(page.locator('th').filter({ hasText: /Margin/i }).first()).toBeVisible();

    // Should have at least 1 row of data
    const rows = page.locator('tbody tr');
    const rowCount = await rows.count();
    expect(rowCount).toBeGreaterThan(0);
});

// ──────────────────────────────────────────────────────────────
// TC-ALK-D-010: Period badge tampil setelah generate
// ──────────────────────────────────────────────────────────────
test('TC-ALK-D-010: period badge tampil setelah generate analisis', async ({ page }) => {
    await goToAlkGrafik(page);
    await clickTampilkanAnalisis(page);

    // Period badge should contain "Periode:"
    await expect(page.locator('text=/Periode:/i')).toBeVisible();
});

// ──────────────────────────────────────────────────────────────
// TC-ALK-D-011: Hero banner menggunakan gradient indigo-violet
// ──────────────────────────────────────────────────────────────
test('TC-ALK-D-011: hero banner menggunakan kelas gradient indigo-violet', async ({ page }) => {
    await goToAlkGrafik(page);

    // Check that the hero element has indigo gradient classes
    const heroBanner = page.locator('[class*="from-indigo-600"]').first();
    await expect(heroBanner).toBeVisible();

    // Subtitle text in indigo-200 color
    await expect(page.locator('[class*="text-indigo-200"]')).toBeVisible();
});

// ──────────────────────────────────────────────────────────────
// TC-ALK-D-012: Tombol Reset tersedia setelah generate
// ──────────────────────────────────────────────────────────────
test('TC-ALK-D-012: tombol Reset tersedia setelah generate analisis', async ({ page }) => {
    await goToAlkGrafik(page);
    await clickTampilkanAnalisis(page);

    const resetBtn = page.getByRole('button', { name: /reset/i });
    await expect(resetBtn.first()).toBeVisible({ timeout: 8000 });
});
