import { test, expect } from '@playwright/test';

test.describe('Journal Entries Filter Test', () => {
  test('verify source_type filter shows only Invoice entries', async ({ page }) => {
    // Login first
    await page.goto('http://127.0.0.1:8009/admin/login');
    await page.waitForSelector('#data\\.email', { timeout: 10000 });
    await page.fill('#data\\.email', 'ralamzah@gmail.com');
    await page.fill('#data\\.password', 'ridho123');
    await page.click('button[type="submit"]');

    // Wait for login to complete
    await page.waitForTimeout(3000);

    // Navigate to journal entries page with source_type filter only
    const filterUrl = 'http://127.0.0.1:8009/admin/journal-entries?tableFilters[source_type][value]=App%5CModels%5CInvoice';
    await page.goto(filterUrl);
    await page.waitForLoadState('networkidle');

    console.log('Navigated to journal entries with source_type filter');

    // Wait for table to load
    await page.waitForTimeout(2000);

    // Get all table rows
    const tableRows = page.locator('tbody tr');
    const rowCount = await tableRows.count();

    console.log(`Found ${rowCount} journal entries with source_type=Invoice filter`);

    // Should have 11 entries based on our database query
    expect(rowCount).toBe(11);

    // Verify filter badge is applied
    const filterBadges = page.locator('.fi-badge');
    const sourceTypeBadge = filterBadges.filter({ hasText: 'Invoice' });
    expect(await sourceTypeBadge.count()).toBe(1);

    console.log('✅ Source type filter works correctly');
  });

  test('verify manual filter application for source_id', async ({ page }) => {
    // Login first
    await page.goto('http://127.0.0.1:8009/admin/login');
    await page.waitForSelector('#data\\.email', { timeout: 10000 });
    await page.fill('#data\\.email', 'ralamzah@gmail.com');
    await page.fill('#data\\.password', 'ridho123');
    await page.click('button[type="submit"]');

    // Wait for login to complete
    await page.waitForTimeout(3000);

    // Navigate to journal entries page
    await page.goto('http://127.0.0.1:8009/admin/journal-entries');
    await page.waitForLoadState('networkidle');

    // First apply source_type filter
    const sourceTypeFilter = page.locator('[data-field-wrapper="source_type"]');
    await sourceTypeFilter.click();
    await page.waitForTimeout(500);

    const invoiceOption = page.locator('[role="option"]').filter({ hasText: 'Invoice' });
    await invoiceOption.click();
    await page.waitForTimeout(1000);

    // Now apply source_id filter
    const sourceIdFilter = page.locator('[data-field-wrapper="source_id"]');
    await sourceIdFilter.click();
    await page.waitForTimeout(500);

    // Fill the source_id input
    const sourceIdInput = page.locator('input[name="source_id"]');
    await sourceIdInput.fill('60');
    await page.waitForTimeout(500);

    // Submit the filter
    const applyButton = page.locator('button').filter({ hasText: 'Apply' });
    await applyButton.click();
    await page.waitForTimeout(2000);

    // Check results
    const tableRows = page.locator('tbody tr');
    const rowCount = await tableRows.count();

    console.log(`Found ${rowCount} journal entries after applying source_id=60 filter`);

    // Should have 4 entries
    expect(rowCount).toBe(4);

    // Verify filter badges
    const filterBadges = page.locator('.fi-badge');
    const sourceTypeBadge = filterBadges.filter({ hasText: 'Invoice' });
    const sourceIdBadge = filterBadges.filter({ hasText: '60' });

    expect(await sourceTypeBadge.count()).toBe(1);
    expect(await sourceIdBadge.count()).toBe(1);

    console.log('✅ Manual filter application works correctly');
    console.log('✅ Both source_type and source_id filters applied successfully');
  });
});