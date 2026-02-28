import { test as setup } from '@playwright/test';
import path from 'path';
import fs from 'fs';

const authFile = path.join(process.cwd(), 'playwright/.auth/user.json');

/**
 * Auth setup: log in once and save storage state so all E2E tests reuse it.
 * This avoids the per-test login overhead that slows down the single-threaded
 * PHP artisan serve and causes intermittent timeouts.
 */
setup('authenticate', async ({ page }) => {
  // Ensure auth directory exists
  const authDir = path.dirname(authFile);
  if (!fs.existsSync(authDir)) {
    fs.mkdirSync(authDir, { recursive: true });
  }

  await page.goto('/admin/login');
  await page.waitForSelector('#data\\.email', { timeout: 15000 });
  await page.fill('#data\\.email', 'e2e-test@duta-tunggal.test');
  await page.fill('#data\\.password', 'e2e-password-123');
  await page.click('button[type="submit"]');

  // Wait for redirect away from login page
  await page.waitForURL(/\/admin(?!\/login)/, { timeout: 30000 });
  await page.waitForLoadState('networkidle', { timeout: 30000 });

  // Save authentication state (cookies + localStorage) to file
  await page.context().storageState({ path: authFile });
  console.log(`[Auth Setup] Saved auth state to ${authFile}`);
});
