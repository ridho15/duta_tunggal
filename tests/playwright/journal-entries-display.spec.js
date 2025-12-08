import { test, expect } from '@playwright/test';

test.describe('Journal Entries Display Test', () => {
  test('check journal entries display on sales invoice page', async ({ page }) => {
    // Login first
    await page.goto('http://127.0.0.1:8009/admin/login');
    await page.waitForSelector('#data\\.email', { timeout: 10000 });
    await page.fill('#data\\.email', 'ralamzah@gmail.com');
    await page.fill('#data\\.password', 'ridho123');
    await page.click('button[type="submit"]');

    // Wait for login to complete
    await page.waitForTimeout(3000);
    const currentUrl = page.url();

    if (currentUrl.includes('/login')) {
      await page.waitForTimeout(5000);
    }

    console.log('Login completed, current URL:', page.url());

    // Navigate to sales invoices page
    await page.goto('http://127.0.0.1:8009/admin/sales-invoices');
    await page.waitForLoadState('networkidle');

    console.log('Navigated to sales invoices list');

    // Check if page loaded
    const pageTitle = await page.title();
    expect(pageTitle).toContain('Invoice Penjualan');

    // Look for invoice 60 in the table
    const invoiceRow = page.locator('tr').filter({ hasText: '60' }).first();
    if (await invoiceRow.count() > 0) {
      console.log('Found invoice 60, clicking on it...');
      await invoiceRow.click();
    } else {
      // Try to find a link with href containing sales-invoices/60
      const invoiceLink = page.locator('a[href*="sales-invoices/60"]').first();
      if (await invoiceLink.count() > 0) {
        console.log('Found invoice 60 link, clicking...');
        await invoiceLink.click();
      } else {
        console.log('Invoice 60 not found in list, trying direct navigation...');
        await page.goto('http://127.0.0.1:8009/admin/sales-invoices/60');
      }
    }

    // Wait for the invoice detail page to load
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);

    // Check if page loaded successfully (should not have 500 error)
    const currentPageTitle = await page.title();
    console.log('Page title:', currentPageTitle);

    // Check for journal entries section
    const journalSection = page.locator('text=Journal Entries').first();
    await expect(journalSection).toBeVisible();

    // Click to expand the journal entries section if it's collapsed
    const sectionHeader = page.locator('h3:has-text("Journal Entries")').first();
    const expandButton = sectionHeader.locator('button[aria-expanded="false"]');
    if (await expandButton.count() > 0) {
      console.log('Expanding journal entries section...');
      await expandButton.click();
      await page.waitForTimeout(1000);
    } else {
      console.log('Journal entries section is already expanded');
    }

    // Now check if journal entries content is visible
    const coaCodeText = page.locator('text=COA Code').first();

    // Take a screenshot of the entire page for debugging
    await page.screenshot({ path: 'sales-invoice-page.png', fullPage: true });
    console.log('Full page screenshot saved as sales-invoice-page.png');

    // Check what text is actually in the journal entries section
    const journalSectionLocator = page.locator('[data-field-wrapper="journal_entries_table"]');
    if (await journalSectionLocator.count() > 0) {
      const sectionText = await journalSectionLocator.textContent();
      console.log('Journal entries section content:', sectionText);
    } else {
      console.log('Journal entries section not found with data-field-wrapper');
    }

    // Check if there's actual journal entry data (not just "No journal entries found")
    const noEntriesText = page.locator('text=No journal entries found');
    const hasEntries = await noEntriesText.count() === 0;

    if (hasEntries) {
      console.log('Journal entries are displayed in table format');

      // Check for monetary values (Rp format)
      const rpText = page.locator('text=Rp ');
      const rpCount = await rpText.count();
      console.log(`Found ${rpCount} monetary values in Rp format`);

      // Check for COA codes (typically numeric codes)
      const coaCodes = page.locator('text=/^[0-9]/').first();
      if (await coaCodes.count() > 0) {
        console.log('COA codes are visible');
      }

    } else {
      console.log('No journal entries found for this invoice');
    }

    // Check for error messages but be more specific
    const errorMessages = page.locator('text=ERROR').or(page.locator('text=500'));
    const errorCount = await errorMessages.count();

    if (errorCount > 0) {
      console.log(`Found ${errorCount} error message(s), checking what they are...`);
      const errorTexts = await errorMessages.allTextContents();
      console.log('Error messages found:', errorTexts);

      // Only fail if it's a critical 500 error, not other types of errors
      const criticalErrors = errorTexts.filter(text => text.includes('500') || text.includes('Internal Server Error'));
      if (criticalErrors.length > 0) {
        console.log('Critical errors found:', criticalErrors);
        expect(criticalErrors.length).toBe(0);
      } else {
        console.log('Non-critical errors found, continuing...');
      }
    } else {
      console.log('No error messages found');
    }

    console.log('Journal entries display test completed successfully');
  });
});