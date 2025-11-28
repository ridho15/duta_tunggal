import { test } from '@playwright/test';

test('check total amount', async ({ page }) => {
  // Login
  await page.goto('http://localhost:8009/admin/login');
  // Filament login inputs use ids like `data.email` and `data.password`
  await page.fill('#data\\.email', 'ralamzah@gmail.com');
  await page.fill('#data\\.password', 'ridho123');
  // Submit and wait for the URL to change away from /admin/login
  await Promise.all([
    page.waitForURL('http://localhost:8009/**', { timeout: 10000 }),
    page.click('button[type="submit"]'),
  ]);

  // Small pause and log current URL to confirm login succeeded
  await page.waitForTimeout(500);
  console.log('AFTER_LOGIN_URL=' + page.url());

  // Ensure the admin sidebar link is present, click it to stabilize navigation
  const poIndexLink = page.locator('a[href="/admin/purchase-orders"]');
  try {
    await poIndexLink.waitFor({ state: 'visible', timeout: 8000 });
    await poIndexLink.click();
    // small wait for navigation to settle
    await page.waitForLoadState('networkidle', { timeout: 10000 });
  } catch (e) {
    // If the link isn't available, continue and try direct navigation
    console.log('PO index link not found after login, will try direct navigation');
  }

  // Go to Purchase Order detail (stable navigation)
  await page.goto('http://localhost:8009/admin/purchase-orders/1', { waitUntil: 'networkidle', timeout: 20000 });

  // Filament uses an input with id `data.total_amount` for the TextInput
  const selector = '#data\\.total_amount';
  const locator = page.locator(selector);
  await locator.waitFor({ state: 'visible', timeout: 10000 });

  // Try to read the input value
  const value = await locator.inputValue();
  console.log('PO_TOTAL_AMOUNT_RAW=' + value);

  // If inputValue is empty, try reading textContent
  if (!value) {
    const text = await locator.evaluate((el: any) => el.textContent || el.value || '');
    console.log('PO_TOTAL_AMOUNT_TEXT=' + text);

    // Dump outerHTML for debugging
    const outer = await locator.evaluate((el: any) => el.outerHTML);
    console.log('PO_TOTAL_AMOUNT_OUTER=' + outer);

    // Many Filament money inputs display formatted value in the placeholder when readOnly/disabled.
    const placeholder = await locator.getAttribute('placeholder');
    console.log('PO_TOTAL_AMOUNT_PLACEHOLDER=' + (placeholder ?? '')); 
  }
});
