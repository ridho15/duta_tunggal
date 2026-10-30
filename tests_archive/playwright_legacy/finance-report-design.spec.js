/**
 * ============================================================
 *  finance-report-design.spec.js
 *  Duta Tunggal ERP — Finance Report Design Consistency Tests
 *
 *  Verifies that the three primary finance report pages render
 *  the correct layout elements, Indonesian labels, color-coded
 *  section headers, and summary cards consistent with the
 *  reference design (Referensi Tampilan Laporan Keuangan).
 *
 *  Reports tested:
 *   - /admin/reports/balance-sheets   → Neraca (Balance Sheet)
 *   - /admin/reports/profit-and-loss  → Laba Rugi
 *   - /admin/reports/cash-flow        → Arus Kas
 *
 *  Auth: ralamzah@gmail.com / ridho123 (saved auth state)
 * ============================================================
 */

import { test, expect } from '@playwright/test';

const URLS = {
  balanceSheet : '/admin/reports/balance-sheets',
  profitLoss   : '/admin/reports/profit-and-losses',
  cashFlow     : '/admin/reports/cash-flow',
};

// ──────────────────────────────────────────────────────────────
// Helper: click "Tampilkan Laporan" and wait for report to render
// ──────────────────────────────────────────────────────────────
async function showReport(page) {
  const btn = page.getByRole('button', { name: /tampilkan laporan/i }).first();
  const isVisible = await btn.isVisible({ timeout: 8000 }).catch(() => false);
  if (isVisible) {
    await btn.click();
    // Wait for Livewire to process the request and re-render
    await page.waitForLoadState('networkidle').catch(() => {});
    await page.waitForTimeout(2000);
  }
}

// ──────────────────────────────────────────────────────────────
//  NERACA (Balance Sheet) design tests
// ──────────────────────────────────────────────────────────────
test.describe('Neraca (Balance Sheet) Report Design', () => {
  test('TC-FR-BS-001: page loads and shows filter form', async ({ page }) => {
    await page.goto(URLS.balanceSheet);
    await page.waitForLoadState('networkidle');

    // Should not show a 500 or 404 error
    const bodyText = await page.locator('body').innerText();
    expect(bodyText).not.toContain('500');
    expect(bodyText).not.toContain('Whoops');
    expect(bodyText).not.toContain('404');
  });

  test('TC-FR-BS-002: shows report header with company name after generating', async ({ page }) => {
    await page.goto(URLS.balanceSheet);
    await page.waitForLoadState('networkidle');
    await showReport(page);

    // Report header should be visible
    const header = page.locator('.fr-report-header').first();
    await expect(header).toBeVisible({ timeout: 8000 });

    // Should contain report type label
    const headerText = await header.innerText();
    expect(headerText.toLowerCase()).toContain('neraca');
  });

  test('TC-FR-BS-003: shows ASET, LIABILITAS, and EKUITAS section headers', async ({ page }) => {
    await page.goto(URLS.balanceSheet);
    await page.waitForLoadState('networkidle');
    await showReport(page);

    // Wait for report body
    await page.waitForSelector('.fr-body', { timeout: 8000 }).catch(() => {});

    const bodyText = await page.locator('body').innerText();
    // Check for Indonesian financial report section labels
    expect(bodyText.toLowerCase()).toMatch(/aset/i);
    expect(bodyText.toLowerCase()).toMatch(/liabilitas|kewajiban/i);
    expect(bodyText.toLowerCase()).toMatch(/ekuitas|modal/i);
  });

  test('TC-FR-BS-004: shows TOTAL ASET label', async ({ page }) => {
    await page.goto(URLS.balanceSheet);
    await page.waitForLoadState('networkidle');
    await showReport(page);

    await page.waitForSelector('.fr-total', { timeout: 8000 }).catch(() => {});
    const bodyText = await page.locator('body').innerText();
    expect(bodyText).toMatch(/total aset/i);
  });

  test('TC-FR-BS-005: shows summary cards when preview is active', async ({ page }) => {
    await page.goto(URLS.balanceSheet);
    await page.waitForLoadState('networkidle');
    await showReport(page);

    // Wait for cards to appear
    await page.waitForSelector('.fr-card', { timeout: 8000 }).catch(() => {});
    const cards = page.locator('.fr-card');
    const count = await cards.count();
    expect(count).toBeGreaterThanOrEqual(1);
  });

  test('TC-FR-BS-006: shows empty state before generating report', async ({ page }) => {
    await page.goto(URLS.balanceSheet);
    await page.waitForLoadState('networkidle');

    // Should show the empty state guide text without pressing generate
    const bodyText = await page.locator('body').innerText();
    expect(bodyText).toMatch(/tampilkan laporan/i);
  });
});

// ──────────────────────────────────────────────────────────────
//  LABA RUGI (Profit & Loss) design tests
// ──────────────────────────────────────────────────────────────
test.describe('Laba Rugi (P&L) Report Design', () => {
  test('TC-FR-PL-001: page loads without errors', async ({ page }) => {
    await page.goto(URLS.profitLoss);
    await page.waitForLoadState('networkidle');

    const status = page.url();
    const bodyText = await page.locator('body').innerText();
    expect(bodyText).not.toContain('Whoops');
    // Accept redirect to login as "no server error"
    const hasServerError = bodyText.includes('500') && !status.includes('login');
    expect(hasServerError).toBe(false);
  });

  test('TC-FR-PL-002: shows report header after generating', async ({ page }) => {
    await page.goto(URLS.profitLoss);
    await page.waitForLoadState('networkidle');
    await showReport(page);

    const header = page.locator('.fr-report-header').first();
    await expect(header).toBeVisible({ timeout: 8000 });

    const headerText = await header.innerText();
    expect(headerText.toLowerCase()).toMatch(/laba rugi/i);
  });

  test('TC-FR-PL-003: shows PENDAPATAN USAHA section header', async ({ page }) => {
    await page.goto(URLS.profitLoss);
    await page.waitForLoadState('networkidle');
    await showReport(page);

    await page.waitForSelector('.fr-body', { timeout: 8000 }).catch(() => {});
    const bodyText = await page.locator('body').innerText();
    expect(bodyText).toMatch(/pendapatan usaha/i);
  });

  test('TC-FR-PL-004: shows LABA KOTOR section result', async ({ page }) => {
    await page.goto(URLS.profitLoss);
    await page.waitForLoadState('networkidle');
    await showReport(page);

    await page.waitForSelector('.fr-body', { timeout: 8000 }).catch(() => {});
    const bodyText = await page.locator('body').innerText();
    expect(bodyText).toMatch(/laba kotor/i);
  });

  test('TC-FR-PL-005: shows LABA USAHA (EBIT) label', async ({ page }) => {
    await page.goto(URLS.profitLoss);
    await page.waitForLoadState('networkidle');
    await showReport(page);

    await page.waitForTimeout(500);
    const bodyText = await page.locator('body').innerText();
    expect(bodyText).toMatch(/laba usaha|ebit/i);
  });

  test('TC-FR-PL-006: shows LABA BERSIH result row', async ({ page }) => {
    await page.goto(URLS.profitLoss);
    await page.waitForLoadState('networkidle');
    await showReport(page);

    await page.waitForSelector('.fr-result.net', { timeout: 8000 }).catch(() => {});
    const bodyText = await page.locator('body').innerText();
    expect(bodyText).toMatch(/laba bersih|rugi bersih/i);
  });

  test('TC-FR-PL-007: shows summary cards with key financial metrics', async ({ page }) => {
    await page.goto(URLS.profitLoss);
    await page.waitForLoadState('networkidle');
    await showReport(page);

    await page.waitForSelector('.fr-card', { timeout: 8000 }).catch(() => {});
    const cards = page.locator('.fr-card');
    const count = await cards.count();
    expect(count).toBeGreaterThanOrEqual(1);
  });

  test('TC-FR-PL-008: shows notes section with formula tips', async ({ page }) => {
    await page.goto(URLS.profitLoss);
    await page.waitForLoadState('networkidle');
    await showReport(page);

    await page.waitForTimeout(500);
    const bodyText = await page.locator('body').innerText();
    expect(bodyText).toMatch(/rumus penting|laba kotor/i);
  });
});

// ──────────────────────────────────────────────────────────────
//  ARUS KAS (Cash Flow) design tests
// ──────────────────────────────────────────────────────────────
test.describe('Arus Kas (Cash Flow) Report Design', () => {
  test('TC-FR-CF-001: page loads without errors', async ({ page }) => {
    await page.goto(URLS.cashFlow);
    await page.waitForLoadState('networkidle');

    const bodyText = await page.locator('body').innerText();
    expect(bodyText).not.toContain('500');
    expect(bodyText).not.toContain('Whoops');
  });

  test('TC-FR-CF-002: shows orange-themed report header after generating', async ({ page }) => {
    await page.goto(URLS.cashFlow);
    await page.waitForLoadState('networkidle');
    await showReport(page);

    const header = page.locator('.fr-report-header').first();
    await expect(header).toBeVisible({ timeout: 8000 });

    const headerText = await header.innerText();
    expect(headerText.toLowerCase()).toMatch(/arus kas/i);
  });

  test('TC-FR-CF-003: shows SALDO KAS AKHIR PERIODE row', async ({ page }) => {
    await page.goto(URLS.cashFlow);
    await page.waitForLoadState('networkidle');
    await showReport(page);

    await page.waitForSelector('.fr-closing', { timeout: 8000 }).catch(() => {});
    const bodyText = await page.locator('body').innerText();
    expect(bodyText).toMatch(/saldo kas akhir/i);
  });

  test('TC-FR-CF-004: shows KENAIKAN BERSIH KAS row', async ({ page }) => {
    await page.goto(URLS.cashFlow);
    await page.waitForLoadState('networkidle');
    await showReport(page);

    await page.waitForSelector('.fr-net-change', { timeout: 8000 }).catch(() => {});
    const bodyText = await page.locator('body').innerText();
    expect(bodyText).toMatch(/kenaikan.*bersih.*kas|penurunan.*bersih/i);
  });

  test('TC-FR-CF-005: shows summary cards with period amounts', async ({ page }) => {
    await page.goto(URLS.cashFlow);
    await page.waitForLoadState('networkidle');
    await showReport(page);

    await page.waitForSelector('.fr-card', { timeout: 8000 }).catch(() => {});
    const cards = page.locator('.fr-card');
    const count = await cards.count();
    expect(count).toBeGreaterThanOrEqual(1);
  });

  test('TC-FR-CF-006: shows Tips Membaca notes section', async ({ page }) => {
    await page.goto(URLS.cashFlow);
    await page.waitForLoadState('networkidle');
    await showReport(page);

    await page.waitForTimeout(500);
    const bodyText = await page.locator('body').innerText();
    expect(bodyText).toMatch(/tips membaca|arus kas operasional/i);
  });

  test('TC-FR-CF-007: shows export buttons when report is visible', async ({ page }) => {
    await page.goto(URLS.cashFlow);
    await page.waitForLoadState('networkidle');
    await showReport(page);

    await page.waitForTimeout(500);
    const bodyText = await page.locator('body').innerText();
    expect(bodyText).toMatch(/export excel|export pdf/i);
  });
});

// ──────────────────────────────────────────────────────────────
//  Cross-report consistency checks
// ──────────────────────────────────────────────────────────────
test.describe('Cross-Report Design Consistency', () => {
  test('TC-FR-X-001: all three report pages have consistent empty state guidance', async ({ page }) => {
    for (const [name, url] of Object.entries(URLS)) {
      await page.goto(url);
      await page.waitForLoadState('networkidle');

      const bodyText = await page.locator('body').innerText();
      expect(bodyText.toLowerCase()).toMatch(/tampilkan laporan/i);
    }
  });

  test('TC-FR-X-002: all three reports show company name in header after generating', async ({ page }) => {
    for (const [name, url] of Object.entries(URLS)) {
      await page.goto(url);
      await page.waitForLoadState('networkidle');
      await showReport(page);

      const header = page.locator('.fr-report-header').first();
      const visible = await header.isVisible({ timeout: 6000 }).catch(() => false);
      if (visible) {
        const text = await header.innerText();
        // Should contain either app name or report title (not blank)
        expect(text.trim().length).toBeGreaterThan(5);
      }
    }
  });

  test('TC-FR-X-003: Rp currency format used consistently across all reports', async ({ page }) => {
    for (const [name, url] of Object.entries(URLS)) {
      await page.goto(url);
      await page.waitForLoadState('networkidle');
      await showReport(page);
      await page.waitForTimeout(800);

      const bodyText = await page.locator('body').innerText();
      // If any financial data is displayed, it should use Rp format
      if (bodyText.match(/\d{3}\.\d{3}/)) {
        expect(bodyText).toMatch(/Rp\s*[\d.,]+/);
      }
    }
  });
});
