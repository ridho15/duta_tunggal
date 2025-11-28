import { test, expect } from '@playwright/test';

test.describe('Chart of Accounts Management', () => {
  test.beforeEach(async ({ page }) => {
    // Login
    await page.goto('http://localhost:8003/admin/login');
    await page.fill('input[name="email"]', 'ralamzah@gmail.com');
    await page.fill('input[name="password"]', 'ridho123');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin**');
  });

  test('can create chart of account', async ({ page }) => {
    await page.goto('http://localhost:8003/admin/chart-of-accounts');

    // Click create button
    await page.click('text=Create');

    // Fill form
    await page.fill('input[name="code"]', '1110.01');
    await page.fill('input[name="name"]', 'Kas Besar');
    await page.selectOption('select[name="type"]', 'Asset');
    await page.check('input[name="is_active"]');

    // Submit
    await page.click('button[type="submit"]');

    // Verify
    await expect(page.locator('text=Kas Besar')).toBeVisible();
    await expect(page.locator('text=1110.01')).toBeVisible();
  });

  test('can update chart of account', async ({ page }) => {
    await page.goto('http://localhost:8003/admin/chart-of-accounts');

    // Find and click edit on first COA
    await page.click('tr:first-child .edit-button');

    // Update name
    await page.fill('input[name="name"]', 'Kas Besar Updated');

    // Submit
    await page.click('button[type="submit"]');

    // Verify
    await expect(page.locator('text=Kas Besar Updated')).toBeVisible();
  });

  test('can delete chart of account', async ({ page }) => {
    // First create a test COA
    await page.goto('http://localhost:8003/admin/chart-of-accounts');
    await page.click('text=Create');
    await page.fill('input[name="code"]', '9999.99');
    await page.fill('input[name="name"]', 'Test COA for Delete');
    await page.selectOption('select[name="type"]', 'Asset');
    await page.click('button[type="submit"]');

    // Now delete it
    await page.click('tr:has-text("Test COA for Delete") .delete-button');
    await page.click('button:has-text("Confirm")');

    // Verify
    await expect(page.locator('text=Test COA for Delete')).not.toBeVisible();
  });

  test('validates coa code uniqueness', async ({ page }) => {
    await page.goto('http://localhost:8003/admin/chart-of-accounts');

    // Create first COA
    await page.click('text=Create');
    await page.fill('input[name="code"]', '8888.88');
    await page.fill('input[name="name"]', 'Unique Test COA');
    await page.selectOption('select[name="type"]', 'Asset');
    await page.click('button[type="submit"]');

    // Try to create another with same code
    await page.click('text=Create');
    await page.fill('input[name="code"]', '8888.88');
    await page.fill('input[name="name"]', 'Duplicate Test COA');
    await page.selectOption('select[name="type"]', 'Asset');
    await page.click('button[type="submit"]');

    // Should show error
    await expect(page.locator('text=The code has already been taken')).toBeVisible();
  });

  test('validates account type hierarchy', async ({ page }) => {
    await page.goto('http://localhost:8003/admin/chart-of-accounts');

    // Create parent
    await page.click('text=Create');
    await page.fill('input[name="code"]', '7777');
    await page.fill('input[name="name"]', 'Parent Account');
    await page.selectOption('select[name="type"]', 'Asset');
    await page.click('button[type="submit"]');

    // Create child
    await page.click('text=Create');
    await page.fill('input[name="code"]', '7777.01');
    await page.fill('input[name="name"]', 'Child Account');
    await page.selectOption('select[name="type"]', 'Asset');
    await page.selectOption('select[name="parent_id"]', 'Parent Account');
    await page.click('button[type="submit"]');

    // Verify hierarchy
    await expect(page.locator('text=Child Account')).toBeVisible();
    await expect(page.locator('text=Parent Account')).toBeVisible();
  });
});