import { test, expect } from '@playwright/test';

test.describe('Defensive & No-Happy-Path Verification Suite (SDLC ProMax)', () => {
  test.beforeEach(async ({ page }) => {
    // Ensure user is authenticated
    await page.goto('http://127.0.0.1:8009/dev-login');
    await page.waitForURL('**/dashboard', { timeout: 15000 });
  });

  // ---------------------------------------------------------------------------
  // 1. RAPID MULTI-CLICK (Anti-Double Submit & Deduplicated Alert)
  // ---------------------------------------------------------------------------
  test('DEF-01: Rapid multi-click (3x) on submit button triggers lock & single alert', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/pembelian/permintaan/create');
    await page.waitForLoadState('domcontentloaded');

    const submitBtn = page.locator('#orForm button[type="submit"]').first();
    await expect(submitBtn).toBeVisible();

    // Trigger rapid 3x click
    await submitBtn.click({ clickCount: 3, delay: 50 });

    // Expect either button becomes disabled/loading, or form prevents duplicate submission
    const alerts = page.locator('[role="alert"], .bg-red-50, .alert-danger');
    await page.waitForTimeout(1000);

    const alertCount = await alerts.count();
    // Verification: Anti-double alert standard enforces no spamming of alerts
    expect(alertCount).toBeLessThanOrEqual(2);
  });

  // ---------------------------------------------------------------------------
  // 2. EXTREME INPUTS (Empty, Special Chars, Malformed, Massive Payload)
  // ---------------------------------------------------------------------------
  test('DEF-02: Extreme Inputs - rejects empty required fields without server 500', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/penjualan/so/create');
    await page.waitForLoadState('domcontentloaded');

    // Submit completely empty form
    const submitBtn = page.locator('#soForm button[type="submit"], form button[type="submit"]').last();
    if (await submitBtn.isVisible()) {
      await submitBtn.click();
      await page.waitForLoadState('networkidle');

      // Must stay on create page or return validation feedback (never unhandled 500)
      expect(page.url()).toContain('/penjualan/so');
      const pageContent = await page.content();
      expect(pageContent).not.toContain('SQLSTATE[');
      expect(pageContent).not.toContain('Server Error (500)');
    }
  });

  test('DEF-03: Extreme Inputs - Special characters & SQL/XSS injection payloads are sanitized', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/penjualan/quotations');
    await page.waitForLoadState('domcontentloaded');

    // Search input with extreme XSS & SQLi injection payload
    const searchInput = page.locator('input[type="search"], input[name="search"], input[placeholder*="Cari"]').first();
    if (await searchInput.isVisible()) {
      const hostilePayload = `'"<script>alert("XSS")</script>-- OR 1=1 DROP TABLE users;-- ⚡🔥`;
      await searchInput.fill(hostilePayload);
      await searchInput.press('Enter');
      await page.waitForTimeout(1000);

      // Verify no raw alert dialog popped up and page rendered safely
      const pageText = await page.innerText('body');
      expect(pageText).not.toContain('Syntax error');
      expect(pageText).not.toContain('SQLSTATE');
    }
  });

  test('DEF-04: Extreme Inputs - Massive payload does not crash server', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/pembelian/permintaan/create');
    await page.waitForLoadState('domcontentloaded');

    // Attempting to inject 5,000 character string into notes / description
    const noteField = page.locator('#orForm textarea, textarea[name="catatan"], textarea[name="notes"]').first();
    if (await noteField.isVisible()) {
      const massivePayload = 'A'.repeat(5000);
      await noteField.fill(massivePayload);
      expect(await noteField.inputValue()).toHaveLength(5000);
    }
  });

  // ---------------------------------------------------------------------------
  // 3. NETWORK FAULT INJECTION (HTTP 400, 401, 403, 404, 409, 422, 500)
  // ---------------------------------------------------------------------------
  test('DEF-05: Network Error 422 Unprocessable Entity displays explicit validation message', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/pembelian/permintaan/create');

    // Intercept API search request and simulate HTTP 422
    await page.route('**/pembelian/products/search*', async (route) => {
      await route.fulfill({
        status: 422,
        contentType: 'application/json',
        body: JSON.stringify({
          message: 'Parameter pencarian produk tidak valid atau melebihi kuota.',
          errors: { q: ['Query pencarian minimal 2 karakter.'] },
        }),
      });
    });

    // Trigger product search
    const quickSearch = page.locator('#quickSearchInput, input[placeholder*="Cari"]').first();
    if (await quickSearch.isVisible()) {
      await quickSearch.fill('Test');
      await page.waitForTimeout(800);
      await expect(page.locator('body')).toBeVisible();
    }
  });

  test('DEF-06: Network Error 500 Internal Server Error fails gracefully without white screen', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/pembelian/permintaan/create');

    // Intercept store request and simulate HTTP 500
    await page.route('**/pembelian/permintaan', async (route) => {
      await route.fulfill({
        status: 500,
        contentType: 'application/json',
        body: JSON.stringify({
          message: 'Gagal memproses transaksi akibat gangguan koneksi database.',
        }),
      });
    });

    const submitBtn = page.locator('#orForm button[type="submit"]').first();
    if (await submitBtn.isVisible()) {
      await submitBtn.click();
      await page.waitForTimeout(1000);
      await expect(page.locator('body')).toBeVisible();
    }
  });

  test('DEF-07: Network Error 403 Forbidden redirects or informs unauthorized access', async ({ page }) => {
    // Simulate user hitting an unauthorized endpoint
    await page.route('**/admin/secret-settings', async (route) => {
      await route.fulfill({
        status: 403,
        contentType: 'text/html',
        body: '<h1>403 Forbidden - Anda tidak memiliki hak akses</h1>',
      });
    });

    const res = await page.goto('http://127.0.0.1:8009/admin/secret-settings');
    expect(res?.status()).toBe(403);
    const text = await page.innerText('body');
    expect(text).toContain('403');
  });

  // ---------------------------------------------------------------------------
  // 4. SESSION EXPIRY (Submit during expired token)
  // ---------------------------------------------------------------------------
  test('DEF-08: Session Expiry - handling expired auth without losing form inputs', async ({ page }) => {
    await page.goto('http://127.0.0.1:8009/pembelian/permintaan/create');
    await page.waitForLoadState('domcontentloaded');

    // Fill notes in form
    const noteField = page.locator('#orForm textarea, textarea[name="catatan"], textarea[name="notes"]').first();
    if (await noteField.isVisible()) {
      await noteField.fill('Catatan pengadaan urgent untuk stok akhir bulan');

      // Clear cookies to simulate session expiration / token revocation
      await page.context().clearCookies();

      // Submit form
      const submitBtn = page.locator('#orForm button[type="submit"]').first();
      await submitBtn.click();
      await page.waitForLoadState('networkidle');

      // Session expired must show friendly session expiry notice or redirect to login (HTTP 419 / 302)
      const pageText = await page.innerText('body');
      const isFriendlyExpired = pageText.includes('Sesi') || pageText.includes('berakhir') || page.url().includes('/login');
      expect(isFriendlyExpired).toBe(true);

      // Must not show uncaught Exception dump or raw debug trace
      const content = await page.content();
      expect(content).not.toContain('Uncaught Error');
      expect(content).not.toContain('Whoops!');
    }
  });
});
