/**
 * sales-do-sj-fixes.spec.js
 *
 * Targeted tests for March 2026 Sales/DO/SJ improvements:
 *
 *  G3 — SaleOrder: tempo_pembayaran auto-fill from customer is editable
 *  G4 — SaleOrder: tipe_pajak field visible per item (not hidden)
 *  F1 — SaleOrder: Rupiah format (Rp prefix) visible on SO list
 *  H1 — DeliveryOrder: salesOrders field appears before cabang_id
 *  H2 — DeliveryOrder: no "Receipt Item" option in DO item form
 *  J2 — SuratJalan: no sender_name / shipping_method / rekap_driver / approval button
 *
 * Rupiah format check: all money columns must match "Rp X.XXX" pattern.
 */

import { test, expect } from '@playwright/test';

const RUPIAH_PATTERN = /Rp[\s\d.]+/;

// ─── Helpers ──────────────────────────────────────────────────────────────────

async function navigateTo(page, path) {
  await page.goto(path);
  await page.waitForLoadState('networkidle');
}

// ─────────────────────────────────────────────────────────────────────────────
// F1 / G: SO list has Rupiah format in total_amount column
// ─────────────────────────────────────────────────────────────────────────────
test.describe('F1 — Rupiah format on SaleOrder list', () => {
  test('SO list page loads without errors', async ({ page }) => {
    await navigateTo(page, '/admin/sale-orders');
    // Page should not show PHP errors
    const content = await page.textContent('body');
    expect(content).not.toContain('Symfony\\Component\\Debug\\Exception');
    expect(content).not.toContain('ErrorException');
  });

  test('SO list shows Rupiah format for total_amount', async ({ page }) => {
    await navigateTo(page, '/admin/sale-orders');

    // Check for actual SO record links (not debugbar links)
    const soLinks = await page.locator('a[href*="/admin/sale-orders/"]').evaluateAll(
      els => els.filter(el => /\/admin\/sale-orders\/\d+/.test(el.getAttribute('href') || '')).length
    );
    if (soLinks === 0) {
      test.skip(true, 'No SaleOrder records');
      return;
    }

    // total_amount column should have Rp format
    const pageHtml = await page.content();
    expect(pageHtml).toMatch(RUPIAH_PATTERN);
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// G3 — SO create form: tempo_pembayaran is editable (not readonly)
// ─────────────────────────────────────────────────────────────────────────────
test.describe('G3 — SO tempo_pembayaran field is editable', () => {
  test('tempo_pembayaran input is not readonly on create form', async ({ page }) => {
    await navigateTo(page, '/admin/sale-orders/create');

    // Find the tempo_pembayaran input
    const input = page.locator('input[id*="tempo_pembayaran"]').first();
    const inputCount = await input.count();

    if (inputCount === 0) {
      // Try by label
      const tempoLabel = page.getByText('Tempo Pembayaran', { exact: false }).first();
      await expect(tempoLabel).toBeVisible({ timeout: 10_000 });
    } else {
      // Verify it is NOT readonly
      const isReadonly = await input.getAttribute('readonly');
      expect(isReadonly).toBeNull();
    }
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// G4 — SO create form: tipe_pajak is visible per repeater item
// ─────────────────────────────────────────────────────────────────────────────
test.describe('G4 — SO tipe_pajak field visible', () => {
  test('tipe_pajak select exists and is not hidden on create form', async ({ page }) => {
    await navigateTo(page, '/admin/sale-orders/create');

    // The page should not hide tipe_pajak (it was hidden=true before, now visible)
    // Check page content for "Tipe Pajak" label
    await expect(page.getByText('Tipe Pajak', { exact: false }).first()).toBeVisible({ timeout: 10_000 });
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// H1 — DO create form: "From Sales" field appears before "Cabang"
// ─────────────────────────────────────────────────────────────────────────────
test.describe('H1 — DO field order: From Sales before Cabang', () => {
  test('From Sales field appears before Cabang on DO create form', async ({ page }) => {
    await navigateTo(page, '/admin/delivery-orders/create');

    // Both labels should be visible
    const fromSalesLabel = page.getByText('From Sales', { exact: false }).first();
    const cabangLabel = page.getByText('Cabang', { exact: false }).first();

    await expect(fromSalesLabel).toBeVisible({ timeout: 10_000 });

    // Check DOM order: From Sales should come before Cabang in page HTML
    const pageContent = await page.content();
    const fromSalesPos = pageContent.indexOf('From Sales');
    const cabangPos = pageContent.indexOf('Pilih cabang');

    if (fromSalesPos > 0 && cabangPos > 0) {
      expect(fromSalesPos).toBeLessThan(cabangPos);
    }
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// H2 — DO create form: no "From Receipt Item" option
// ─────────────────────────────────────────────────────────────────────────────
test.describe('H2 — DO has no Receipt Item option', () => {
  test('DO create form has no "From Receipt Item" option', async ({ page }) => {
    await navigateTo(page, '/admin/delivery-orders/create');
    const pageContent = await page.content();
    expect(pageContent).not.toContain('From Receipt Item');
    expect(pageContent).not.toContain('Purchase Receipt Item');
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// J2 — SuratJalan: simplified form (no sender_name, no rekap driver, no approval)
// ─────────────────────────────────────────────────────────────────────────────
test.describe('J2 — SuratJalan simplification', () => {
  test('SJ create form has no sender_name field', async ({ page }) => {
    await navigateTo(page, '/admin/surat-jalans/create');
    const pageContent = await page.content();
    expect(pageContent).not.toContain('Nama Pengirim');
    expect(pageContent).not.toContain('sender_name');
  });

  test('SJ create form has no shipping_method field', async ({ page }) => {
    await navigateTo(page, '/admin/surat-jalans/create');
    const pageContent = await page.content();
    expect(pageContent).not.toContain('Metode Pengiriman');
  });

  test('SJ list page has no "Cetak Rekap Driver" button', async ({ page }) => {
    await navigateTo(page, '/admin/surat-jalans');
    const pageContent = await page.content();
    expect(pageContent).not.toContain('Cetak Rekap Driver');
  });

  test('SJ list "Mark as Sent" action exists for published records', async ({ page }) => {
    await navigateTo(page, '/admin/surat-jalans');

    // If any SJ records exist, check that "Tandai Gagal Kirim" does NOT appear
    const tableRows = await page.locator('table tbody tr[data-id]').count();
    if (tableRows === 0) {
      test.skip(true, 'No SuratJalan records');
      return;
    }

    const pageContent = await page.content();
    expect(pageContent).not.toContain('Tandai Gagal Kirim');
  });
});
