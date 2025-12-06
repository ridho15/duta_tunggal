import { test, expect } from '@playwright/test';

test.describe('Account Payables Verification Test', () => {
  // Helper function for login
  async function login(page) {
    await page.goto('http://127.0.0.1:8009/admin/login');
    await page.waitForLoadState('networkidle');
    await page.fill('#data\\.email', 'ralamzah@gmail.com');
    await page.fill('#data\\.password', 'ridho123');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin**', { timeout: 15000 });
    await page.waitForLoadState('networkidle');
    // Additional wait to ensure login is complete
    await page.waitForTimeout(2000);
  }

  test('verify widget totals match table records', async ({ page }) => {
    console.log('🧪 Testing: Account Payables widget and table verification');

    await login(page);

    // Direct navigation to Account Payables page
    console.log('Navigating to account payables page...');
    await page.goto('http://127.0.0.1:8009/admin/account-payables');
    await page.waitForLoadState('networkidle');

    // Wait for page content to load
    await page.waitForTimeout(3000);

    // Debug: Check page content
    const pageContent = await page.content();
    console.log('Page title:', await page.title());
    console.log('Page contains "Account Payable":', pageContent.includes('Account Payable'));

    // Look for any stats or widgets
    const statsSelectors = [
      '.fi-stats-overview-stat',
      '.stats-overview-widget',
      '[class*="stat"]',
      '[class*="widget"]'
    ];

    for (const selector of statsSelectors) {
      const count = await page.locator(selector).count();
      if (count > 0) {
        console.log(`Found ${count} elements with selector: ${selector}`);
        const firstElement = await page.locator(selector).first();
        const text = await firstElement.textContent();
        console.log(`First element text: ${text}`);
      }
    }

    // Get widget values using the correct selector
    const statElements = page.locator('[class*="stat"]');
    const statCount = await statElements.count();
    console.log('Found', statCount, 'stat elements');

    let totalAmountWidget = null;
    let recordCountWidget = null;

    if (statCount > 0) {
      // Get first stat (Total Amount)
      const firstStat = statElements.first();
      const statText = await firstStat.textContent();
      console.log('First stat text:', statText);

      // Extract values from text
      const amountMatch = statText.match(/Rp\s+([\d.,]+)/);
      const recordMatch = statText.match(/(\d+)\s+records?/);

      totalAmountWidget = amountMatch ? amountMatch[1] : null;
      recordCountWidget = recordMatch ? recordMatch[1] : null;

      console.log('Extracted Total Amount:', totalAmountWidget);
      console.log('Extracted Record Count:', recordCountWidget);
    }

    // Extract numeric values from widget
    const totalAmountValue = totalAmountWidget ? parseFloat(totalAmountWidget.replace(/[^\d.,]/g, '').replace(/\./g, '').replace(',', '.')) : 0;
    const recordCountValue = recordCountWidget ? parseInt(recordCountWidget.replace(/\D/g, '')) : 0;

    console.log('Parsed Total Amount:', totalAmountValue);
    console.log('Parsed Record Count:', recordCountValue);

    // Count actual data table rows by looking for invoice numbers
    let tableRows = 0;
    try {
      // Look for rows containing invoice numbers (TEST-, INV-, etc.)
      const invoiceCells = page.locator('td').filter({ hasText: /TEST-|INV-/ });
      tableRows = await invoiceCells.count();
      console.log('Rows with invoice numbers:', tableRows);

      if (tableRows === 0) {
        // Alternative: count visible rows in the data table
        const visibleRows = page.locator('.fi-ta-row').filter({ hasText: /\w+/ });
        tableRows = await visibleRows.count();
        console.log('Visible rows with text:', tableRows);
      }
    } catch (e) {
      console.log('Error counting table rows:', e.message);
    }

    // Verify that widget record count matches table row count
    if (recordCountValue > 0 || tableRows > 0) {
      expect(recordCountValue).toBe(tableRows);
    } else {
      console.log('No data found in both widget and table');
    }

    // Additional verification: check if table has data
    if (tableRows > 0) {
      // Check if first row has invoice number
      const firstRowInvoice = await page.locator('table tbody tr').first().locator('td').first().textContent();
      expect(firstRowInvoice).toBeTruthy();
      console.log('First row invoice number:', firstRowInvoice);
    }

    // Take final screenshot for verification
    await page.screenshot({ path: 'account-payables-verification.png', fullPage: true });

    console.log('✅ Account Payables verification test completed successfully');
  });
});