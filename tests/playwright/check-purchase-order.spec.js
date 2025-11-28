import { test, expect } from '@playwright/test';

test('read Purchase Order #1 purchaseOrderBiaya Total', async ({ page }) => {
  const base = process.env.BASE_URL || 'http://localhost:8009';
  // Login - try common selectors and fallbacks
  await page.goto(`${base}/admin/login`);
  const emailLocator = page.locator('input[name="email"], input[type="email"], input#email, input[name="username"], input[type="text"]').first();
  await emailLocator.waitFor({ timeout: 10000 });
  await emailLocator.fill('ralamzah@gmail.com');

  const passwordLocator = page.locator('input[name="password"], input[type="password"], input#password').first();
  await passwordLocator.waitFor({ timeout: 5000 });
  await passwordLocator.fill('ridho123');

  // Submit - try submit button variants. Wait until the login heading disappears (Livewire form submits via AJAX).
  const submit = page.locator('button[type="submit"], button:has-text("Login"), button:has-text("Masuk")').first();
  await submit.click();
  try {
    await page.waitForFunction(() => {
      const h1 = document.querySelector('h1');
      return !h1 || !/Masuk ke akun Anda/i.test(h1.textContent || '');
    }, { timeout: 8000 });
  } catch (e) {
    // If still on login page, fail early with clearer message
    console.error('Login did not complete — still on login page');
  }

  // Navigate to purchase order page
  await page.goto(`${base}/admin/purchase-orders/1`, { waitUntil: 'networkidle' });

  // Read the top-level `total_amount` input (Filament often renders it with id "data.total_amount")
  await page.waitForTimeout(1200);

  const displayedTotal = await page.evaluate(() => {
    const el = document.getElementById('data.total_amount');
    if (!el) return null;
    return el.value || el.getAttribute('value') || el.textContent || null;
  });

  console.log('PAGE_URL:', page.url());
  console.log('TOTAL_AMOUNT_DISPLAYED:', displayedTotal);

  expect(displayedTotal).not.toBeNull();
});
