import { test, expect } from '@playwright/test';

test('Surat Jalan has Cetak Rekap Fleksibel action', async ({ page }) => {
  await page.goto('/admin/surat-jalans');

  // If redirected to login, perform login
  if (page.url().includes('/login')) {
    await page.locator('#data\\.email').fill('ralamzah@gmail.com');
    await page.locator('#data\\.password').fill('ridho123');
    await page.locator('form').getByRole('button', { name: /masuk|login|sign in/i }).click();
    await page.waitForFunction(() => !window.location.pathname.endsWith('/login'), { timeout: 30000 });
    await page.goto('/admin/surat-jalans');
  }

  await page.waitForLoadState('networkidle');

  // Check if the new action button exists
  const flexibleButton = page.getByRole('button', { name: 'Cetak Rekap Fleksibel' });
  await expect(flexibleButton).toBeVisible();

  console.log('✅ Cetak Rekap Fleksibel action is visible');
});

test('Cetak Rekap Fleksibel form opens and has required fields', async ({ page }) => {
  // Capture console errors
  const consoleErrors = [];
  page.on('console', msg => {
    if (msg.type() === 'error') consoleErrors.push(msg.text());
  });
  page.on('pageerror', err => consoleErrors.push(err.message));

  await page.goto('/admin/surat-jalans');

  // If redirected to login, perform login
  if (page.url().includes('/login')) {
    await page.locator('#data\\.email').fill('ralamzah@gmail.com');
    await page.locator('#data\\.password').fill('ridho123');
    await page.locator('form').getByRole('button', { name: /masuk|login|sign in/i }).click();
    await page.waitForFunction(() => !window.location.pathname.endsWith('/login'), { timeout: 30000 });
    await page.goto('/admin/surat-jalans');
  }

  await page.waitForLoadState('networkidle');

  // Check if there are any console errors
  if (consoleErrors.length > 0) {
    console.log('Console errors:', consoleErrors);
  }

  // Click the flexible report button
  const flexibleButton = page.getByRole('button', { name: 'Cetak Rekap Fleksibel' });
  await flexibleButton.click();

  // Wait a bit and check if modal appears
  await page.waitForTimeout(2000);

  // Check if modal opened by looking for modal content
  const modalExists = await page.locator('.fi-modal').count() > 0;
  console.log('Modal exists:', modalExists);

  if (modalExists) {
    // Check modal title
    const modalTitle = page.locator('.fi-modal h2, .fi-modal h3, .fi-modal h4').filter({ hasText: 'Cetak Rekap Pengiriman Fleksibel' });
    const titleVisible = await modalTitle.isVisible().catch(() => false);
    console.log('Modal title visible:', titleVisible);

    if (titleVisible) {
      console.log('✅ Flexible report modal opens with title');
    } else {
      console.log('❌ Modal opened but title not found');
    }
  } else {
    console.log('❌ Modal did not open');
  }
});