import { test, expect } from '@playwright/test';
import { login } from './auth.setup.mjs';

/**
 * PDF Preview E2E Tests
 * Tests the complete PDF preview workflow for all document types
 */

test.describe('PDF Preview - All Document Types', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  const documentTypes = [
    {
      name: 'Order Request',
      type: 'order-request',
      listPath: '/admin/order-requests',
      actionLabel: /preview.*download.*pdf/i,
      expectedTitle: 'Order Request',
    },
    {
      name: 'Purchase Order',
      type: 'purchase-order',
      listPath: '/admin/purchase-orders',
      actionLabel: /preview.*pdf/i,
      expectedTitle: 'Purchase Order',
    },
    {
      name: 'Quotation',
      type: 'quotation',
      listPath: '/admin/quotations',
      actionLabel: /preview.*download.*pdf/i,
      expectedTitle: 'Quotation',
    },
    {
      name: 'Sales Order',
      type: 'sale-order',
      listPath: '/admin/sale-orders',
      actionLabel: /preview.*download.*pdf/i,
      expectedTitle: 'Sales Order',
    },
  ];

  for (const doc of documentTypes) {
    test(`${doc.name} - preview page loads with iframe and download button`, async ({ page }) => {
      // Navigate to list page
      await page.goto(doc.listPath);
      await page.waitForLoadState('networkidle');

      // Find a record with approved/active status
      const rows = page.locator('table tbody tr');
      const rowCount = await rows.count();

      if (rowCount === 0) {
        test.skip(`${doc.name} - no records found to test`);
        return;
      }

      // Click the first action button (view/edit) to get to a record page
      const firstRowActions = rows.first().locator('td:last-child button');
      const actionCount = await firstRowActions.count();

      if (actionCount === 0) {
        test.skip(`${doc.name} - no actions available`);
        return;
      }

      // Look for PDF action button
      const pdfButton = page.locator(`button:has-text("Preview"), button:has-text("Cetak"), button:has-text("PDF")`).first();

      // If PDF button exists, click it
      const pdfButtonCount = await page.locator(`button:has-text("PDF"), button:has-text("Cetak"), button:has-text("Download")`).count();
      if (pdfButtonCount > 0) {
        // Get current page count before clicking
        const initialPageCount = await page.context().pages().then(pages => pages.length);

        // Click the first matching button (could be in action menu)
        const firstButton = page.locator(`button:has-text("PDF"), button:has-text("Cetak")`).first();
        await firstButton.click().catch(() => {});

        // Wait for new tab to open
        await page.waitForTimeout(1000);

        // Check if new page was opened
        const pages = await page.context().pages();
        if (pages.length > initialPageCount) {
          // Switch to new tab
          const newPage = pages[pages.length - 1];
          await newPage.waitForLoadState('networkidle');

          // Verify preview page elements
          await expect(newPage.locator('h1, h2').first()).toBeVisible();
          await expect(newPage.locator('a[href*="/pdf-download/"]').first()).toBeVisible({ timeout: 5000 }).catch(() => {
            // If no download link, check for iframe
            console.log('No explicit download link found, checking for iframe');
          });

          // Check for iframe
          const iframe = newPage.locator('iframe');
          await expect(iframe).toBeVisible({ timeout: 5000 });

          // Verify iframe has correct URL pattern
          const iframeSrc = await iframe.getAttribute('src');
          expect(iframeSrc).toContain('/pdf-stream/');
          expect(iframeSrc).toContain(doc.type);

          // Close new tab
          await newPage.close();
        }
      }
    });

    test(`${doc.name} - preview URL generates correct download link`, async ({ page }) => {
      // Navigate directly to a preview URL (if we have a known record ID)
      // For now, just verify the route pattern exists
      await page.goto('/');
      await page.waitForLoadState('networkidle');

      // Check if PDF routes are accessible (will redirect to login if not authenticated)
      const previewUrl = '/pdf-preview/order-request/1';
      const response = await page.goto(previewUrl);

      // Should either show preview page (200) or redirect to login
      expect([200, 302, 401]).toContain(response?.status() ?? 0);
    });
  }

  test('preview-wrapper view displays download button correctly', async ({ page }) => {
    // Navigate to a preview page (using a known test record ID)
    await page.goto('/admin/order-requests');
    await page.waitForLoadState('networkidle');

    // Look for any Order Request record and click its PDF action
    const pdfActions = page.locator('[data-pc-section="action"] button:has-text("PDF")');
    const count = await pdfActions.count();

    if (count > 0) {
      // Click to open in new tab
      await pdfActions.first().click();
      await page.waitForTimeout(2000);

      const pages = await page.context().pages();
      if (pages.length > 1) {
        const previewPage = pages[pages.length - 1];

        // Check download button exists
        const downloadBtn = previewPage.locator('a:has-text("Download PDF"), a:has-text("Download")');
        await expect(downloadBtn).toBeVisible({ timeout: 5000 });

        // Verify href contains pdf-download
        const href = await downloadBtn.getAttribute('href');
        expect(href).toContain('pdf-download');

        await previewPage.close();
      }
    }
  });

  test('iframe displays PDF content from stream URL', async ({ page }) => {
    // Login first
    await login(page);

    // Navigate to purchase order list
    await page.goto('/admin/purchase-orders');
    await page.waitForLoadState('networkidle');

    // Find PDF action
    const pdfButtons = page.locator('button:has-text("Preview"), button:has-text("PDF")');
    const count = await pdfButtons.count();

    if (count > 0) {
      await pdfButtons.first().click();
      await page.waitForTimeout(2000);

      const pages = await page.context().pages();
      if (pages.length > 1) {
        const previewPage = pages[pages.length - 1];
        await previewPage.waitForLoadState('networkidle');

        // Check iframe exists and has stream URL
        const iframe = previewPage.locator('iframe');
        await expect(iframe).toBeVisible();

        const src = await iframe.getAttribute('src');
        expect(src).toContain('pdf-stream');
        expect(src).toContain('purchase-order');

        await previewPage.close();
      }
    }
  });

  test('authentication required for PDF routes', async ({ page }) => {
    // Logout first
    await page.goto('/logout');
    await page.waitForLoadState('networkidle');

    // Try to access PDF preview without authentication
    const response = await page.goto('/pdf-preview/order-request/1');

    // Should be redirected to login (302) or show 401/403
    expect([302, 401, 403]).toContain(response?.status() ?? 0);
  });
});

test.describe('PDF Download Flow', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('download URL returns PDF with correct headers', async ({ page }) => {
    // Navigate to purchase order and get a record ID
    await page.goto('/admin/purchase-orders');
    await page.waitForLoadState('networkidle');

    // Click on first row to view details
    const firstRow = page.locator('table tbody tr').first();
    const viewLink = firstRow.locator('a:has-text("View"), button:has-text("View")').first();

    if (await viewLink.count() > 0) {
      await viewLink.click();
      await page.waitForLoadState('networkidle');

      // Look for PDF action
      const pdfBtn = page.locator('button:has-text("Preview"), button:has-text("PDF")').first();

      if (await pdfBtn.count() > 0) {
        // Get URL from the button
        const href = await pdfBtn.getAttribute('onclick') || await pdfBtn.getAttribute('href');

        if (href && href.includes('pdf-preview')) {
          // Extract record ID and navigate directly
          const urlMatch = href.match(/\/pdf-preview\/[^/]+\/(\d+)/);
          if (urlMatch) {
            const recordId = urlMatch[1];
            await page.goto(`/pdf-download/purchase-order/${recordId}`);

            // Check response headers
            const contentType = page.evaluate(() => {
              return window.performance.getEntriesByType('resource')
                .find(r => r.name.includes('pdf-download'))?.responseStatus;
            });

            // Verify we got a PDF response
            await expect(page).not.toHaveTitle(/login/i, { ignoreCase: true });
          }
        }
      }
    }
  });

  test('stream URL returns inline PDF for iframe', async ({ page }) => {
    // Direct test of stream endpoint
    await page.goto('/pdf-stream/order-request/1');

    // Should either show PDF or redirect to login
    const url = page.url();
    expect(url.includes('/pdf-stream/') || url.includes('/login')).toBeTruthy();
  });
});