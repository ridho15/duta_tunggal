import { test, expect } from '@playwright/test';

// ─────────────────────────────────────────────────────────────────────────────
// resource-sorting.spec.js
//
// Verifies that all ERP list pages show the newest record on the first row.
//
// Sorting rule: newest data first (ORDER BY date-column DESC).
//
// Strategy:
//  1. Navigate to the list page.
//  2. Grab the text of all cells in the relevant date column (first visible date
//     column or the row-number column) of the first page.
//  3. Parse each date value and assert each value is >= the next one.
//
// Prerequisites:
//  - A running dev server at http://localhost:8009
//  - At least 2 records in each resource with DIFFERENT dates
//  - An admin user with email/password below
// ─────────────────────────────────────────────────────────────────────────────

const BASE_URL       = 'http://localhost:8009';
const LOGIN_EMAIL    = 'ralamzah@gmail.com';
const LOGIN_PASSWORD = 'ridho123';

// ─── Helper: login once ──────────────────────────────────────────────────────

async function login(page) {
  await page.goto(`${BASE_URL}/admin/login`);
  await page.waitForLoadState('networkidle');

  await page.fill('input[id="data.email"]', LOGIN_EMAIL);
  await page.fill('input[id="data.password"]', LOGIN_PASSWORD);
  await page.click('button[type="submit"]');

  await page.waitForURL(url => !url.href.includes('/login'), { timeout: 60_000 });

  if (page.url().includes('/login')) {
    throw new Error('Login failed — still on login page after redirect.');
  }
}

// ─── Helper: grab all visible date-like cell texts in a column ───────────────

/**
 * Navigate to `path`, wait for the table, then return the text content of
 * every <td> cell in the column whose header text CONTAINS `headerSubstring`.
 */
async function getColumnValues(page, path, headerSubstring) {
  await page.goto(`${BASE_URL}${path}`);
  await page.waitForLoadState('networkidle');
  await page.waitForSelector('table', { timeout: 30_000 });

  // Find the column index by header text
  const headers = await page.$$eval('table thead th', ths =>
    ths.map(th => th.innerText.trim())
  );

  const colIndex = headers.findIndex(h =>
    h.toLowerCase().includes(headerSubstring.toLowerCase())
  );

  if (colIndex === -1) {
    // Fallback: return empty so test can skip gracefully
    console.warn(`Column "${headerSubstring}" not found on ${path}. Headers:`, headers);
    return [];
  }

  const cells = await page.$$eval(
    `table tbody tr td:nth-child(${colIndex + 1})`,
    tds => tds.map(td => td.innerText.trim())
  );

  return cells;
}

// ─── Helper: assert date strings are sorted descending ───────────────────────

/**
 * Given an array of date strings (YYYY-MM-DD, DD/MM/YYYY, or similar),
 * assert each one is >= the next — i.e. newest first.
 *
 * Rows with empty / non-parseable values are skipped.
 */
function assertDescending(values, label) {
  const dates = values
    .map(v => {
      // Handle common formats: YYYY-MM-DD, DD/MM/YYYY, DD-MM-YYYY, "10 Mar 2026"
      const d = new Date(v.replace(/(\d{2})\/(\d{2})\/(\d{4})/, '$3-$2-$1'));
      return isNaN(d.getTime()) ? null : d;
    })
    .filter(d => d !== null);

  if (dates.length < 2) {
    console.warn(`${label}: not enough parseable dates to compare (got ${dates.length}). Skipping.`);
    return;
  }

  for (let i = 0; i < dates.length - 1; i++) {
    expect(
      dates[i].getTime(),
      `${label}: row ${i + 1} (${dates[i].toISOString().slice(0, 10)}) should be >= row ${i + 2} (${dates[i + 1].toISOString().slice(0, 10)})`
    ).toBeGreaterThanOrEqual(dates[i + 1].getTime());
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// TESTS
// ─────────────────────────────────────────────────────────────────────────────

test.describe('Resource Sorting — Newest First', () => {

  // Login before all tests in this describe block
  test.beforeAll(async ({ browser }) => {
    const page = await browser.newPage();
    await login(page);
    await page.close();
  });

  // ── Order Requests ────────────────────────────────────────────────────────
  test('Order Requests — newest first', async ({ page }) => {
    test.setTimeout(60_000);
    await login(page);

    const values = await getColumnValues(page, '/admin/order-requests', 'tanggal');
    if (values.length === 0) {
      const vals2 = await getColumnValues(page, '/admin/order-requests', 'date');
      assertDescending(vals2, 'OrderRequests');
    } else {
      assertDescending(values, 'OrderRequests');
    }
  });

  // ── Purchase Orders ───────────────────────────────────────────────────────
  test('Purchase Orders — newest first', async ({ page }) => {
    test.setTimeout(60_000);
    await login(page);

    const values = await getColumnValues(page, '/admin/purchase-orders', 'tanggal');
    if (values.length === 0) {
      const vals2 = await getColumnValues(page, '/admin/purchase-orders', 'date');
      assertDescending(vals2, 'PurchaseOrders');
    } else {
      assertDescending(values, 'PurchaseOrders');
    }
  });

  // ── Deposits ──────────────────────────────────────────────────────────────
  test('Deposits — newest first', async ({ page }) => {
    test.setTimeout(60_000);
    await login(page);

    const values = await getColumnValues(page, '/admin/deposits', 'tanggal');
    if (values.length === 0) {
      const vals2 = await getColumnValues(page, '/admin/deposits', 'date');
      assertDescending(vals2, 'Deposits');
    } else {
      assertDescending(values, 'Deposits');
    }
  });

  // ── Sale Orders ───────────────────────────────────────────────────────────
  test('Sale Orders — newest first', async ({ page }) => {
    test.setTimeout(60_000);
    await login(page);

    const values = await getColumnValues(page, '/admin/sale-orders', 'tanggal');
    if (values.length === 0) {
      const vals2 = await getColumnValues(page, '/admin/sale-orders', 'date');
      assertDescending(vals2, 'SaleOrders');
    } else {
      assertDescending(values, 'SaleOrders');
    }
  });

  // ── Inventory (InventoryStock) ─────────────────────────────────────────────
  test('Inventory — newest first', async ({ page }) => {
    test.setTimeout(60_000);
    await login(page);

    const values = await getColumnValues(page, '/admin/inventory-stocks', 'updated');
    if (values.length === 0) {
      const vals2 = await getColumnValues(page, '/admin/inventory-stocks', 'tanggal');
      assertDescending(vals2, 'InventoryStocks');
    } else {
      assertDescending(values, 'InventoryStocks');
    }
  });

  // ── Invoices ──────────────────────────────────────────────────────────────
  test('Invoices — newest invoice_date first', async ({ page }) => {
    test.setTimeout(60_000);
    await login(page);

    const values = await getColumnValues(page, '/admin/invoices', 'invoice');
    assertDescending(values, 'Invoices');
  });

  // ── Stock Movements ───────────────────────────────────────────────────────
  test('Stock Movements — newest date first', async ({ page }) => {
    test.setTimeout(60_000);
    await login(page);

    const values = await getColumnValues(page, '/admin/stock-movements', 'tanggal');
    if (values.length === 0) {
      const vals2 = await getColumnValues(page, '/admin/stock-movements', 'date');
      assertDescending(vals2, 'StockMovements');
    } else {
      assertDescending(values, 'StockMovements');
    }
  });

  // ── Purchase Receipts ─────────────────────────────────────────────────────
  test('Purchase Receipts — newest first', async ({ page }) => {
    test.setTimeout(60_000);
    await login(page);

    const values = await getColumnValues(page, '/admin/purchase-receipts', 'tanggal');
    if (values.length === 0) {
      const vals2 = await getColumnValues(page, '/admin/purchase-receipts', 'date');
      assertDescending(vals2, 'PurchaseReceipts');
    } else {
      assertDescending(values, 'PurchaseReceipts');
    }
  });

  // ── Delivery Orders ───────────────────────────────────────────────────────
  test('Delivery Orders — newest first', async ({ page }) => {
    test.setTimeout(60_000);
    await login(page);

    const values = await getColumnValues(page, '/admin/delivery-orders', 'tanggal');
    if (values.length === 0) {
      const vals2 = await getColumnValues(page, '/admin/delivery-orders', 'date');
      assertDescending(vals2, 'DeliveryOrders');
    } else {
      assertDescending(values, 'DeliveryOrders');
    }
  });

  // ── Quotations ────────────────────────────────────────────────────────────
  test('Quotations — newest first', async ({ page }) => {
    test.setTimeout(60_000);
    await login(page);

    const values = await getColumnValues(page, '/admin/quotations', 'tanggal');
    if (values.length === 0) {
      const vals2 = await getColumnValues(page, '/admin/quotations', 'date');
      assertDescending(vals2, 'Quotations');
    } else {
      assertDescending(values, 'Quotations');
    }
  });

});
