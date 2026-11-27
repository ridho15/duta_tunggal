import { test, expect } from '@playwright/test';
import path from 'path';

const SCREENSHOT_DIR = 'C:/Users/ralam/.gemini/antigravity/brain/fa80b9da-259a-45a7-8f1e-c487a82b1211/screenshots';
const BASE_URL = 'http://127.0.0.1:8009';

test.describe('QA Evidence: Pengaturan Peran & Hak Akses (Login Owner)', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(`${BASE_URL}/dev-login?role=Owner`);
    await page.waitForURL('**/dashboard', { timeout: 15000 });
  });

  test('01. Halaman Daftar Peran & Hak Akses (Owner)', async ({ page }) => {
    await page.goto(`${BASE_URL}/pengaturan/peran`);
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Peran|Hak Akses/i);
    await expect(page.locator('body')).toContainText('Owner');
    await expect(page.locator('body')).toContainText('Sales Manager');
    await expect(page.locator('body')).toContainText('Sales');

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'access_01_roles_index_owner.png'),
      fullPage: false,
    });
  });

  test('02. Edit Peran Sales Manager — Data Master Aktif (Khusus Manajer)', async ({ page }) => {
    await page.goto(`${BASE_URL}/pengaturan/peran/4/edit`);
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText('Sales Manager');
    await expect(page.locator('body')).toContainText(/Data Master/i);

    // Scroll to Data Master section if needed or capture checklist
    const masterSection = page.locator('text=Data Master').first();
    if (await masterSection.isVisible()) {
      await masterSection.scrollIntoViewIfNeeded();
    }

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'access_02_role_manager_checklist_master_enabled.png'),
      fullPage: false,
    });
  });

  test('03. Edit Peran Sales (Non-Manajer) — Data Master Dibatasi', async ({ page }) => {
    await page.goto(`${BASE_URL}/pengaturan/peran/5/edit`);
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText('Sales');

    const masterSection = page.locator('text=Data Master').first();
    if (await masterSection.isVisible()) {
      await masterSection.scrollIntoViewIfNeeded();
    }

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'access_03_role_sales_checklist_master_restricted.png'),
      fullPage: false,
    });
  });

  test('04. Tambah Peran dari Template (Sales)', async ({ page }) => {
    await page.goto(`${BASE_URL}/pengaturan/peran/create?template=5`);
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Tambah Peran|Template|Sales/i);

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'access_04_role_duplicate_template_form.png'),
      fullPage: false,
    });
  });

  test('05. Riwayat Perubahan Akses (Audit Trail)', async ({ page }) => {
    await page.goto(`${BASE_URL}/pengaturan/peran/5/edit`);
    await page.waitForLoadState('networkidle');

    // Scroll ke bagian Riwayat Perubahan jika ada di halaman edit peran
    const historyHeading = page.locator('text=/Riwayat/i').first();
    if (await historyHeading.isVisible()) {
      await historyHeading.scrollIntoViewIfNeeded();
    }

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'access_05_access_history_audit_trail.png'),
      fullPage: false,
    });
  });

  test('06. Pengaturan Hak Akses Khusus Pengguna (/pengaturan/pengguna/1/akses)', async ({ page }) => {
    await page.goto(`${BASE_URL}/pengaturan/pengguna/1/akses`);
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Akses/i);

    await page.screenshot({
      path: path.join(SCREENSHOT_DIR, 'access_06_user_access_override_matrix.png'),
      fullPage: false,
    });
  });
});
