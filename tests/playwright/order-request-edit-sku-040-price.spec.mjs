import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';

async function loginIfNeeded(page) {
  await page.goto('/admin/order-requests/20/edit', { waitUntil: 'networkidle' });

  if (page.url().includes('/login')) {
    await page.locator('#data\\.email').fill('ralamzah@gmail.com');
    await page.locator('#data\\.password').fill('ridho123');
    await page.locator('form').getByRole('button', { name: /masuk|login|sign in/i }).click();
    await page.waitForFunction(() => !window.location.pathname.includes('/login'), { timeout: 30_000 });
    await page.goto('/admin/order-requests/20/edit', { waitUntil: 'networkidle' });
  }
}

function readSku040DatabaseValue() {
  const output = execSync(
    "php artisan tinker --execute='echo json_encode(\\Illuminate\\Support\\Facades\\DB::table(\"order_request_items\") ->join(\"currencies\", \"currencies.id\", \"=\", \"order_request_items.currency_id\") ->where(\"order_request_items.id\", 56) ->select(\"order_request_items.unit_price\", \"order_request_items.original_price\", \"currencies.code as currency\", \"currencies.symbol as symbol\") ->first(), JSON_THROW_ON_ERROR);'",
    { encoding: 'utf8' }
  );

  return JSON.parse(output.trim());
}

test('order request edit page displays SKU-040 USD price with decimal places (29,80 not 30)', async ({ page }) => {
  await loginIfNeeded(page);

  const itemRow = page.locator('.fi-fo-repeater-item').filter({ hasText: 'SKU-040' }).first();
  await expect(itemRow).toBeVisible();

  const overrideField = itemRow
    .locator('.fi-fo-field-wrp')
    .filter({ has: page.locator('label:has-text("Harga Override")') })
    .first()
    .locator('input')
    .first();

  // Should display with 2 decimal places: 29,80 (not rounded to 30)
  await expect(overrideField).toHaveValue('29,80');

  const dbValue = readSku040DatabaseValue();
  expect(dbValue.unit_price).toBe('29.80');
  expect(dbValue.original_price).toBe('30.00');
  expect(dbValue.currency).toBe('USD');
  expect(dbValue.symbol).toBe('$');
});
