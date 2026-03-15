import { test, expect } from '@playwright/test';
import path from 'path';
import { fileURLToPath } from 'url';
import fs from 'fs';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Ensure auth directory exists
const authDir = path.join(__dirname, '../../playwright/.auth');
if (!fs.existsSync(authDir)) {
  fs.mkdirSync(authDir, { recursive: true });
}

/**
 * Login setup — runs once and persists the Filament auth session.
 * Credentials: ralamzah@gmail.com / ridho123
 */
test('setup auth state', async ({ page }) => {
  await page.goto('/admin/login');
  await expect(page).toHaveTitle(/Masuk|Login/);

  await page.locator('#data\\.email').fill('ralamzah@gmail.com');
  await page.locator('#data\\.password').fill('ridho123');
  // Use the form's own submit button (not any Search button that may be on the page)
  await page.locator('form').getByRole('button', { name: /masuk|login|sign in/i }).click();

  // After login, wait until we're not on the login page (some redirects may already have happened)
  await page.waitForFunction(() => !window.location.pathname.endsWith('/login'), { timeout: 30_000 });

  await page.context().storageState({ path: 'playwright/.auth/user.json' });
});
