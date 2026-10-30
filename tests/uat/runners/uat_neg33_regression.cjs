/**
 * NEG-3.3 Regression Test (Re-verification setelah bug fix)
 */
const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const BASE_URL = 'http://127.0.0.1:8009';
const SS_DIR = path.join(__dirname, '..', 'uat_alur3_screenshots');

async function loginAs(page, email, password) {
  await page.goto(`${BASE_URL}/admin/login`, { waitUntil: 'networkidle' });
  await page.waitForSelector('input[type="email"]', { timeout: 15000 });
  await page.fill('input[type="email"]', email);
  await page.fill('input[type="password"]', password);
  const btn = page.locator('form button[type="submit"]').first();
  await btn.click();
  try {
    await page.waitForURL(`${BASE_URL}/admin`, { timeout: 20000 });
  } catch { await page.waitForTimeout(3000); }
}

(async () => {
  const browser = await chromium.launch({ headless: true, slowMo: 100 });

  console.log('=== NEG-3.3 REGRESSION TEST (Post Bug Fix) ===');

  // Test 1: Sales role should NOT see Approve button
  const ctx = await browser.newContext({ viewport: { width: 1400, height: 900 } });
  const page = await ctx.newPage();
  await loginAs(page, 'sales@example.com', 'password');
  await page.goto(`${BASE_URL}/admin/quotations/1`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(3000);
  await page.screenshot({ path: path.join(SS_DIR, 'neg_3_3_regression_sales_after_fix.png') });

  const approveBtn = page.locator('button:has-text("Approve"), button:has-text("Setujui")');
  const salesCount = await approveBtn.count();
  if (salesCount === 0) {
    console.log('✅ PASS: Tombol Approve TIDAK muncul untuk role Sales setelah bug fix');
  } else {
    const isDisabled = await approveBtn.first().isDisabled();
    console.log(isDisabled 
      ? '✅ PASS: Tombol Approve ada tapi disabled untuk Sales' 
      : '❌ FAIL: Tombol Approve MASIH aktif untuk Sales setelah fix!');
  }
  await ctx.close();

  // Test 2: Sales Manager SHOULD still see Approve button (no regression)
  const ctx2 = await browser.newContext({ viewport: { width: 1400, height: 900 } });
  const page2 = await ctx2.newPage();
  await loginAs(page2, 'sales_manager@example.com', 'password');
  await page2.goto(`${BASE_URL}/admin/quotations/1`, { waitUntil: 'networkidle' });
  await page2.waitForTimeout(3000);
  await page2.screenshot({ path: path.join(SS_DIR, 'neg_3_3_regression_manager_can_approve.png') });

  const mgApproveBtn = page2.locator('button:has-text("Approve"), button:has-text("Setujui")');
  const mgCount = await mgApproveBtn.count();
  if (mgCount > 0) {
    const isDisabled = await mgApproveBtn.first().isDisabled();
    console.log(!isDisabled
      ? '✅ PASS: Sales Manager MASIH bisa approve (tidak terkena dampak fix)'
      : '❌ FAIL: Sales Manager tidak bisa approve (fix terlalu agresif!)');
  } else {
    console.log('⚠️  WARN: Tombol Approve tidak muncul untuk Sales Manager — perlu cek');
  }
  await ctx2.close();

  await browser.close();
  console.log('\n📸 Screenshots tersimpan di:', SS_DIR);
})();
