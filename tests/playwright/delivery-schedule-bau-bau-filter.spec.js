import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';

const CABANG_LABEL = 'Bau Bau';
const USED_SJ = 'SJ-PW-BAU-USED-001';
const AVAILABLE_SJ = 'SJ-PW-BAU-AVAILABLE-001';

test.beforeAll(() => {
  execSync('php scripts/setup_delivery_schedule_bau_bau_playwright_data.php', { stdio: 'inherit' });
});

async function openCreatePage(page) {
  await page.goto('/admin/delivery-schedules/create');
  if (page.url().includes('/login')) {
    await page.locator('#data\\.email').fill('superadmin@gmail.com');
    await page.locator('#data\\.password').fill('superadmin');
    await page.locator('form').getByRole('button', { name: /masuk|login|sign in/i }).click();
    await page.waitForFunction(() => !window.location.pathname.endsWith('/login'), { timeout: 30_000 });
    await page.goto('/admin/delivery-schedules/create');
  }
  await page.waitForLoadState('networkidle');
}

async function selectChoicesOption(page, labelText, optionText) {
  const trigger = page.getByText(labelText, { exact: false }).first();
  await expect(trigger).toBeVisible({ timeout: 10_000 });
  await trigger.click({ force: true });

  const searchInput = page.locator('.choices__input--cloned:visible').first();
  if (await searchInput.count()) {
    await searchInput.fill(optionText);
    await page.waitForTimeout(400);
  }

  const option = page
    .locator('.choices__list--dropdown .choices__item--choice:not(.choices__placeholder):not(.is-disabled):visible')
    .filter({ hasText: optionText })
    .first();

  await expect(option).toBeVisible({ timeout: 10_000 });
  await option.click({ force: true });
  await page.waitForTimeout(600);
}

test('Bau Bau cabang excludes used Surat Jalan from create delivery schedule form', async ({ page }) => {
  const consoleErrors = [];
  page.on('console', (msg) => {
    if (msg.type() === 'error') consoleErrors.push(msg.text());
  });
  page.on('pageerror', (err) => consoleErrors.push(err.message));

  await openCreatePage(page);

  await selectChoicesOption(page, 'Cabang', CABANG_LABEL);

  await page.getByText('Surat Jalan', { exact: false }).first().click({ force: true });
  await page.waitForTimeout(500);

  const dropdown = page.locator('.choices__list--dropdown:visible').last();
  await expect(dropdown).toBeVisible({ timeout: 10_000 });

  const optionTexts = await dropdown
    .locator('.choices__item--choice:not(.choices__placeholder):not(.is-disabled)')
    .evaluateAll((options) => options.map((option) => (option.textContent || '').trim()).filter(Boolean));

  expect(optionTexts).toContain(AVAILABLE_SJ);
  expect(optionTexts).not.toContain(USED_SJ);

  if (consoleErrors.length) {
    const nonTrivial = consoleErrors.filter((message) => !/favicon|chunk|Loading chunk/i.test(message));
    expect(nonTrivial, `Unexpected console errors: ${nonTrivial.join(' | ')}`).toHaveLength(0);
  }
});