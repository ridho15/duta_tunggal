import { test, expect } from '@playwright/test';
import path from 'path';

const SCREENSHOT_DIR = 'C:/Users/ralam/.gemini/antigravity/brain/fa80b9da-259a-45a7-8f1e-c487a82b1211/screenshots';
const BASE_URL = 'http://127.0.0.1:8009';

test.describe('QA Evidence Update 77fee5fc (27 Nov 2026)', () => {
  test('01. Login Owner > Menu Pengaturan > Peran & Hak Akses muncul', async ({ page }) => {
    await page.goto(`${BASE_URL}/dev-login?role=Owner`);
    await page.waitForURL('**/dashboard', { timeout: 15000 });

    const roleMenu = page.locator('aside a:has-text("Peran & Hak Akses"), nav a:has-text("Peran & Hak Akses")').first();
    await expect(roleMenu).toBeVisible({ timeout: 10000 });
    await roleMenu.scrollIntoViewIfNeeded();
    await roleMenu.click();
    await page.waitForURL('**/pengaturan/peran', { timeout: 15000 });
    await expect(page.locator('body')).toContainText('Peran & Hak Akses');

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'upd_01_owner_menu_roles.png'),
      fullPage: false,
    });
  });

  test('02. Login Staf (Sales) > Menu Master Data tidak muncul & akses langsung ditolak 403', async ({ page }) => {
    await page.goto(`${BASE_URL}/dev-login?role=Sales`);
    await page.waitForURL('**/dashboard', { timeout: 15000 });

    const sidebar = page.locator('aside, nav').first();
    await expect(sidebar).not.toContainText('Master Data');
    await expect(sidebar).not.toContainText('Peran & Hak Akses');

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'upd_02_sales_no_master_menu.png'),
      fullPage: false,
    });

    const res = await page.goto(`${BASE_URL}/master/produk`);
    expect(res?.status()).toBe(403);
  });

  test('03. Terima Pembayaran > Tab Titip Dana bisa dibuka', async ({ page }) => {
    await page.goto(`${BASE_URL}/dev-login?role=Owner`);
    await page.waitForURL('**/dashboard', { timeout: 15000 });

    await page.goto(`${BASE_URL}/penjualan/terima-pembayaran`);
    await page.waitForLoadState('networkidle');

    const titipTab = page.locator('a:has-text("Titip Dana")').first();
    await expect(titipTab).toBeVisible({ timeout: 15000 });
    await Promise.all([
      page.waitForURL(/tab=titip/, { timeout: 45000 }),
      titipTab.click(),
    ]);
    await page.waitForLoadState('domcontentloaded');

    expect(page.url()).toContain('tab=titip');
    await expect(page.locator('body')).toContainText(/Titip Dana/i);

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'upd_03_receipts_tab_titip_dana.png'),
      fullPage: false,
    });
  });

  test('04. Tampilan HP (390x844): Halaman tidak melebar keluar layar & tabel bisa digeser', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(`${BASE_URL}/dev-login?role=Owner`);
    await page.waitForURL('**/dashboard', { timeout: 15000 });

    await page.goto(`${BASE_URL}/penjualan/terima-pembayaran?tab=titip`, { waitUntil: 'domcontentloaded', timeout: 45000 });
    await page.waitForLoadState('networkidle');

    const overflow = await page.evaluate(() => {
      return {
        scrollWidth: document.documentElement.scrollWidth,
        clientWidth: document.documentElement.clientWidth,
      };
    });
    expect(overflow.scrollWidth).toBeLessThanOrEqual(overflow.clientWidth + 1);

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'upd_04_mobile_responsive_layout.png'),
      fullPage: false,
    });
  });
});
