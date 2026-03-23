import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';

const SJ_FIXTURE_NUMBER = 'SJ-TEST-SJ-SELECT'

test.beforeAll(() => {
  execSync('php scripts/setup_surat_jalan_playwright_data.php', { stdio: 'inherit' })
})

test('Surat Jalan edit should preload delivery order select values', async ({ page }) => {
  const consoleErrors = [];
  page.on('console', (msg) => {
    if (msg.type() === 'error') consoleErrors.push(msg.text());
  });
  page.on('pageerror', (err) => consoleErrors.push(err.message));

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

  const fixtureRow = page.locator('tr, .fi-ta-row').filter({ hasText: SJ_FIXTURE_NUMBER }).first()
  await expect(fixtureRow).toBeVisible({ timeout: 10000 })

  const editHref = await fixtureRow
    .locator('a[href*="/admin/surat-jalans/"][href$="/edit"]')
    .first()
    .getAttribute('href')

  expect(editHref).toBeTruthy()
  await page.goto(String(editHref));
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
