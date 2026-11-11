import { test as setup, expect } from '@playwright/test';

const authFile = 'playwright/.auth/user.json';

setup('authenticate as admin', async ({ page }) => {
  // Navigate to dev-login to automatically authenticate the primary test user
  await page.goto('http://127.0.0.1:8009/dev-login');
  await page.waitForURL('**/dashboard', { timeout: 15000 });
  await expect(page).toHaveURL(/.*dashboard/);

  // Save auth storage state (cookies + localStorage)
  await page.context().storageState({ path: authFile });
});

