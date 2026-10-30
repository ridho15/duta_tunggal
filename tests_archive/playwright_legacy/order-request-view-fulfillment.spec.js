/**
 * order-request-view-fulfillment.spec.js
 *
 * Verifies that the Order Request view page shows fulfillment summary fields:
 *   - Qty Terpenuhi
 *   - Sisa Qty
 *
 * Uses the deterministic A4 fixtures:
 *   - OR-TEST-A4-PARTIAL  -> fulfilled_quantity = 4, remaining = 16
 *   - OR-TEST-A4-COMPLETE  -> fulfilled_quantity = 10, remaining = 0
 */

import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';

test.use({ storageState: 'playwright/.auth/user.json' });

function getOrderRequestId(requestNumber) {
  const output = execSync(
    `php artisan tinker --execute="echo DB::table('order_requests')->where('request_number', '${requestNumber}')->value('id');"`,
    { encoding: 'utf8' }
  ).trim();

  const id = Number(output);
  if (!Number.isInteger(id) || id <= 0) {
    throw new Error(`Unable to resolve id for ${requestNumber}. Output: ${output}`);
  }

  return id;
}


test.beforeAll(() => {
  execSync('php scripts/setup_order_request_a4_playwright_data.php', { stdio: 'inherit' });
});

test('view page shows fulfillment summary for partial and complete ORs', async ({ page }) => {
  const partialId = getOrderRequestId('OR-TEST-A4-PARTIAL');
  const completeId = getOrderRequestId('OR-TEST-A4-COMPLETE');

  await page.goto(`/admin/order-requests/${partialId}`);
  await page.waitForLoadState('networkidle');

  const partialBody = await page.textContent('body');
  expect(partialBody).toMatch(/Qty Diterima \(Penerimaan Barang\)/i);
  expect(partialBody).toMatch(/Sisa Qty Belum Diterima/i);
  expect(partialBody).toMatch(/4/i);
  expect(partialBody).toMatch(/16/i);

  await page.goto(`/admin/order-requests/${completeId}`);
  await page.waitForLoadState('networkidle');

  const completeBody = await page.textContent('body');
  expect(completeBody).toMatch(/Qty Diterima \(Penerimaan Barang\)/i);
  expect(completeBody).toMatch(/Sisa Qty Belum Diterima/i);
  expect(completeBody).toMatch(/10/i);
  expect(completeBody).toMatch(/0/i);
});

test('view page shows tax type and item pricing summary for taxable OR', async ({ page }) => {
  const taxId = getOrderRequestId('OR-TEST-A4-TAX');

  await page.goto(`/admin/order-requests/${taxId}`);
  await page.waitForLoadState('networkidle');

  const body = await page.textContent('body');
  expect(body).toMatch(/Tipe Pajak/i);
  expect(body).toMatch(/eklusif/i);
  expect(body).toMatch(/Harga Asli \(Master\)/i);
  expect(body).toMatch(/Harga Override/i);
  expect(body).toMatch(/Rp 100\.000/i);
  expect(body).toMatch(/Subtotal/i);
  expect(body).toMatch(/Rp 333\.000/i);
  expect(body).toMatch(/Qty Diterima \(Penerimaan Barang\)/i);
  expect(body).toMatch(/1/i);
  expect(body).toMatch(/Sisa Qty Belum Diterima/i);
  expect(body).toMatch(/2/i);
});