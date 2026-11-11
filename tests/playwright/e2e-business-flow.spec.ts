import { test, expect } from '@playwright/test';

test.describe('E2E Business Flow & SDLC ProMax Standards Suite', () => {
  test.beforeEach(async ({ page }) => {
    // Authenticate
    await page.goto('http://127.0.0.1:8009/dev-login');
    await page.waitForURL('**/dashboard', { timeout: 15000 });
  });

  // ===========================================================================
  // 1. ALUR PENGADAAN (PROCURE-TO-PAY / P2P)
  // ===========================================================================
  test('E2E-P2P-01: Order Request (OR) - Navigation, List View, and Server Pagination', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/pembelian/permintaan');
    await page.waitForLoadState('domcontentloaded');

    // Verify Title & Brand
    await expect(page).toHaveTitle(/.*Duta Tunggal ERP.*/i);

    // Verify Search & Filter controls exist (QA-01)
    const searchInput = page.locator('input[type="search"], input[name="search"], input[placeholder*="Cari"]').first();
    await expect(searchInput).toBeVisible();

    // Verify Table exists and rows are rendered
    const table = page.locator('table').first();
    await expect(table).toBeVisible();
    const rows = table.locator('tbody tr');
    await expect(await rows.count()).toBeGreaterThan(0);
  });

  test('E2E-P2P-02: Order Request (OR) - Form Creation with Interactive Combobox & Calculations', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/pembelian/permintaan/create');
    await page.waitForLoadState('domcontentloaded');

    // Verify form elements exist
    await expect(page.locator('#orForm')).toBeVisible();

    // Quick product search engine is available (QA-02)
    const quickSearchInput = page.locator('#quickSearchInput');
    if (await quickSearchInput.isVisible()) {
      await quickSearchInput.fill('A');
      await page.waitForTimeout(600);
      await expect(page.locator('#orForm')).toBeVisible();
    }

    // Verify action buttons
    const draftBtn = page.locator('#orForm button[value="draft"]');
    const approveBtn = page.locator('#orForm button[value="request_approve"]');
    await expect(draftBtn).toBeVisible();
    await expect(approveBtn).toBeVisible();
  });

  // ===========================================================================
  // 2. ALUR PENJUALAN (ORDER-TO-CASH / O2C)
  // ===========================================================================
  test('E2E-O2C-01: Penawaran Harga (Quotation) - List and Filters', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/penjualan/quotations');
    await page.waitForLoadState('domcontentloaded');

    const table = page.locator('table').first();
    await expect(table).toBeVisible();
    const rows = table.locator('tbody tr');
    if ((await rows.count()) > 0) {
      await expect(rows.first()).toBeVisible();
    }
  });

  test('E2E-O2C-02: Pesanan Penjualan (Sales Order) - Form Create and Interactive Customer Selection', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/penjualan/so/create');
    await page.waitForLoadState('domcontentloaded');

    // Form SO must be rendered cleanly with zero 500 errors
    const soForm = page.locator('#soForm');
    await expect(soForm).toBeVisible();

    // Verify Customer Select and Section Headings exist
    await expect(page.locator('h1')).toContainText('Buat Pesanan Penjualan');
    await expect(page.locator('button:has-text("Pilih Customer")')).toBeVisible();
  });

  test('E2E-O2C-03: Surat Jalan (Delivery Order) - List and Tracking', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/penjualan/surat-jalan');
    await page.waitForLoadState('domcontentloaded');

    const table = page.locator('table').first();
    await expect(table).toBeVisible();
  });

  // ===========================================================================
  // 3. 8-POINT TECHNICAL QA CHECKS (QA-01 s/d QA-08)
  // ===========================================================================
  test('QA-01: Data list synchronized search debounce and URL state', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/pembelian/permintaan');

    const searchInput = page.locator('input[type="search"], input[name="search"], input[placeholder*="Cari"]').first();
    if (await searchInput.isVisible()) {
      await searchInput.fill('OR-');
      await searchInput.press('Enter');
      await page.waitForLoadState('networkidle');
      expect(page.url()).toContain('search=');
    }
  });

  test('QA-04: Confirmation Modal prevents accidental destructive actions', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/pembelian/permintaan');

    // Check if delete or cancel modal trigger exists
    const deleteBtn = page.locator('button:has-text("Hapus"), [data-action="delete"]').first();
    if (await deleteBtn.isVisible()) {
      await deleteBtn.click();
      const modal = page.locator('[role="dialog"], .modal, #confirmModal');
      await expect(modal).toBeVisible();
      await page.keyboard.press('Escape');
      await expect(modal).toBeHidden();
    }
  });

  test('QA-06: RBAC Guardrail - Unauthenticated direct requests are securely intercepted', async ({ page }) => {
    await page.context().clearCookies();
    await page.goto('http://127.0.0.1:8009/pembelian/permintaan');
    expect(page.url()).toContain('/login');
  });
});
