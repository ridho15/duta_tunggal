import { test, expect } from '@playwright/test';
import path from 'path';
import { fileURLToPath } from 'url';
import fs from 'fs';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const authDir = path.join(__dirname, '../../playwright/.auth');
if (!fs.existsSync(authDir)) {
  fs.mkdirSync(authDir, { recursive: true });
}

/**
 * Login setup — runs once and persists the Filament auth session.
 * Credentials: superadmin@gmail.com / superadmin
 */
test('setup auth state', async ({ page }) => {
  await page.goto('/admin/login');

  const currentPath = new URL(page.url()).pathname;
  if (!currentPath.endsWith('/login')) {
    await page.context().storageState({ path: 'playwright/.auth/user.json' });
    return;
  }

  await expect(page).toHaveTitle(/Masuk|Login|Duta Tunggal ERP/);

  await page.locator('#data\\.email').fill('superadmin@gmail.com');
  await page.locator('#data\\.password').fill('superadmin');
  await page.locator('form').getByRole('button', { name: /masuk|login|sign in/i }).click();

  await page.waitForFunction(() => !window.location.pathname.endsWith('/login'), { timeout: 30_000 });

  await page.context().storageState({ path: 'playwright/.auth/user.json' });
});
