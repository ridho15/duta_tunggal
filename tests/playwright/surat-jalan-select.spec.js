import { test, expect } from '@playwright/test';

test('Surat Jalan edit should preload delivery order select values', async ({ page }) => {
  const consoleErrors = [];
  page.on('console', (msg) => {
    if (msg.type() === 'error') consoleErrors.push(msg.text());
  });
  page.on('pageerror', (err) => consoleErrors.push(err.message));

  await page.goto('/admin/surat-jalans/2/edit');

  // If redirected to login, perform login
  if (page.url().includes('/login')) {
    await page.locator('#data\\.email').fill('ralamzah@gmail.com');
    await page.locator('#data\\.password').fill('ridho123');
    await page.locator('form').getByRole('button', { name: /masuk|login|sign in/i }).click();
    await page.waitForFunction(() => !window.location.pathname.endsWith('/login'), { timeout: 30000 });
    await page.goto('/admin/surat-jalans/2/edit');
  }

  await page.waitForLoadState('networkidle');

  const label = await page.locator('text=Delivery Order').first();
  const fieldWrapperHTML = await label.evaluate((el) => {
    const wrapper = el.closest('[class*="fi-fo-field"]');
    return wrapper ? wrapper.outerHTML.substring(0, 800) : null;
  });
  console.log('Delivery Order field wrapper HTML (truncated):', fieldWrapperHTML);

  // Find the select control within that wrapper (Filament uses an input[type="search"] inside)
  const choicesContainers = await page.evaluate(() => {
    return Array.from(document.querySelectorAll('.choices')).slice(0, 3).map(el => el.outerHTML.substring(0, 1000));
  });
  console.log('First few .choices containers:', choicesContainers);

  const inputs = await page.evaluate(() => {
    return Array.from(document.querySelectorAll('input, select, textarea')).
      filter(el => el.name && el.name.includes('data')).
      map(el => ({ name: el.name, value: el.value, type: el.type }));
  });
  console.log('Inputs containing "data" in name:', inputs.slice(0, 30));

  const selectedChips = await page.locator('div.choices__item--selectable[aria-selected="true"]').allTextContents();
  console.log('Selected Delivery Order chips:', selectedChips);

  expect(selectedChips.length, 'Expected at least one selected Delivery Order chip').toBeGreaterThan(0);
});
