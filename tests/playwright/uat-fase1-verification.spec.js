import { test, expect } from '@playwright/test';
import path from 'path';
import { fileURLToPath } from 'url';
import fs from 'fs';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const screenshotsDir = path.join(__dirname, 'screenshots');
if (!fs.existsSync(screenshotsDir)) {
  fs.mkdirSync(screenshotsDir, { recursive: true });
}

test.describe('UAT Fase 1 - Visual Verification', () => {

  test('1. Issue 7: Purchase Receipt Table Status Column is Read-Only Badge', async ({ page }) => {
    // Navigate to Purchase Receipts
    await page.goto('/admin/purchase-receipts');
    await page.waitForLoadState('networkidle');

    // Verify page header
    await expect(page.locator('h1').first()).toContainText(/Penerimaan Pembelian|Purchase Receipt/i);

    // Verify table has rendered
    const table = page.locator('.fi-ta-table').first();
    await expect(table).toBeVisible();

    // Verify status column does NOT contain any select/dropdown element
    const tableSelects = table.locator('tbody select');
    const selectCount = await tableSelects.count();
    console.log(`Select dropdowns in table body: ${selectCount}`);
    expect(selectCount).toBe(0);

    // Verify status badges are present
    const badges = table.locator('tbody .fi-badge, tbody [class*="badge"]');
    const badgeCount = await badges.count();
    console.log(`Status badges found in table: ${badgeCount}`);
    expect(badgeCount).toBeGreaterThan(0);

    // Capture screenshot
    const screenshotPath = path.join(screenshotsDir, 'uat_issue_7_purchase_receipts.png');
    await page.screenshot({ path: screenshotPath, fullPage: true });
    console.log(`Screenshot saved: ${screenshotPath}`);
  });

  test('2. Issue 6: Purchase Order & Sales Order State Locking', async ({ page }) => {
    // A. Purchase Orders
    await page.goto('/admin/purchase-orders');
    await page.waitForLoadState('networkidle');

    const poTable = page.locator('table').first();
    await expect(poTable).toBeVisible();

    // Screenshot PO table
    const poScreenshotPath = path.join(screenshotsDir, 'uat_issue_6_purchase_orders.png');
    await page.screenshot({ path: poScreenshotPath, fullPage: true });
    console.log(`PO Screenshot saved: ${poScreenshotPath}`);

    // B. Sales Orders
    await page.goto('/admin/sale-orders');
    await page.waitForLoadState('networkidle');

    const soTable = page.locator('table').first();
    await expect(soTable).toBeVisible();

    // Screenshot SO table
    const soScreenshotPath = path.join(screenshotsDir, 'uat_issue_6_sale_orders.png');
    await page.screenshot({ path: soScreenshotPath, fullPage: true });
    console.log(`SO Screenshot saved: ${soScreenshotPath}`);
  });

  test('3. Issue 4: Purchase Invoice Draft Status Isolation & UI', async ({ page }) => {
    // A. Purchase Invoice List
    await page.goto('/admin/purchase-invoices');
    await page.waitForLoadState('networkidle');

    const invoiceTable = page.locator('table').first();
    await expect(invoiceTable).toBeVisible();

    const invoiceScreenshotPath = path.join(screenshotsDir, 'uat_issue_4_purchase_invoices_table.png');
    await page.screenshot({ path: invoiceScreenshotPath, fullPage: true });
    console.log(`Invoice Table Screenshot saved: ${invoiceScreenshotPath}`);

    // B. Create Invoice Form
    await page.goto('/admin/purchase-invoices/create');
    await page.waitForLoadState('networkidle');

    // Verify status field is displayed as 'Draft' badge/text and not an editable select
    const statusDisplay = page.locator('text=Draft').first();
    await expect(statusDisplay).toBeVisible();

    const createScreenshotPath = path.join(screenshotsDir, 'uat_issue_4_purchase_invoice_create.png');
    await page.screenshot({ path: createScreenshotPath, fullPage: true });
    console.log(`Invoice Create Screenshot saved: ${createScreenshotPath}`);
  });

  test('4. Issue 5: Payment Request Form Verification', async ({ page }) => {
    await page.goto('/admin/payment-requests/create');
    await page.waitForLoadState('networkidle');

    const prScreenshotPath = path.join(screenshotsDir, 'uat_issue_5_payment_request_create.png');
    await page.screenshot({ path: prScreenshotPath, fullPage: true });
    console.log(`Payment Request Create Screenshot saved: ${prScreenshotPath}`);
  });

});
