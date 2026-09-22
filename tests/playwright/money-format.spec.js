/**
 * FOCUSED TEST — Phase 3: verify $money() mask fix
 * Root cause was $input.map(...) — String has no .map() method,
 * causing Alpine mask to silently fail.  Fix: $money($input, ',', '.', 0)
 */
import { test, expect } from '@playwright/test';

test('biaya: $money mask formats 100000 → 100.000', async ({ page }) => {
  // Capture console errors to detect any remaining JS failures
  const consoleErrors = [];
  page.on('console', msg => {
    if (msg.type() === 'error') consoleErrors.push(msg.text());
  });
  page.on('pageerror', err => consoleErrors.push(err.message));

  await page.goto('/admin/products/create');
  if (page.url().includes('/login')) {
    await page.locator('#data\\.email').waitFor({ state: 'visible', timeout: 15_000 });
    await page.locator('#data\\.email').fill('ralamzah@gmail.com');
    await page.locator('#data\\.password').fill('ridho123');
    await page.locator('button[type="submit"]').click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 30_000 });
    await page.goto('/admin/products/create');
  }
  await page.waitForLoadState('networkidle');

  const biaya = page.locator('#data\\.biaya');
  await biaya.waitFor({ state: 'visible', timeout: 15_000 });
  await biaya.scrollIntoViewIfNeeded();

  // Clear field, then type digit by digit
  await biaya.click({ clickCount: 3 });
  await page.keyboard.press('Delete');
  await biaya.pressSequentially('100000', { delay: 80 });
  await page.waitForTimeout(600);

  const value = await biaya.inputValue();

  console.log('\n===== RESULT =====');
  console.log('Value after typing 100000 :', JSON.stringify(value));
  if (consoleErrors.length) {
    console.log('JS errors :', consoleErrors);
  } else {
    console.log('JS errors : none');
  }
  console.log('==================\n');

  expect(value, `Expected "100.000", received "${value}"`).toBe('100.000');
});



