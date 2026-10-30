import { test, expect } from '@playwright/test';

test.use({ storageState: undefined });

const BASE = 'http://localhost:8009';

async function loginIfNeeded(page) {
  if (!page.url().includes('/admin/login')) {
    return;
  }

  await page.locator('#data\\.email').fill('ralamzah@gmail.com');
  await page.locator('#data\\.password').fill('ridho123');
  await page.locator('form').getByRole('button', { name: /masuk|login|sign in/i }).click();
  await page.waitForFunction(() => !window.location.pathname.endsWith('/login'), { timeout: 30_000 });
}

async function repeaterState(page) {
  return page.evaluate(() => {
    const isVisible = (element) => {
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();

      return style.display !== 'none'
        && style.visibility !== 'hidden'
        && rect.width > 0
        && rect.height > 0;
    };

    return [...document.querySelectorAll('.fi-fo-repeater-item')].map((item, index) => {
      const controls = [...item.querySelectorAll('input, textarea, select, button, [role="combobox"]')];
      const visibleControls = controls.filter(isVisible);

      return {
        index,
        text: item.innerText.replace(/\s+/g, ' ').trim().slice(0, 180),
        visibleControls: visibleControls.length,
        visibleInputs: [...item.querySelectorAll('input, textarea, select')].filter(isVisible).length,
        bodyVisible: [...item.children].some((child) => isVisible(child) && child.innerText.includes('Product')),
      };
    });
  });
}

test('edit order request #3 add item collapse state', async ({ page }) => {
  await page.goto(`${BASE}/admin/order-requests/3/edit`, { waitUntil: 'networkidle' });
  await loginIfNeeded(page);
  await page.goto(`${BASE}/admin/order-requests/3/edit`, { waitUntil: 'networkidle' });

  await expect(page).toHaveURL(/\/admin\/order-requests\/3\/edit/);
  await page.screenshot({ path: 'test-results/order-request-edit-before-add.png', fullPage: true });

  const before = await repeaterState(page);
  console.log('BEFORE_REPEATER_STATE=' + JSON.stringify(before));

  const addButtons = page.getByRole('button', { name: /Tambah Items/i });
  await expect(addButtons).toHaveCount(1);
  await expect(addButtons).toBeEnabled();
  await addButtons.click();
  await page.waitForTimeout(1_500);
  await expect(addButtons).toBeEnabled();

  await page.screenshot({ path: 'test-results/order-request-edit-after-add.png', fullPage: true });

  const after = await repeaterState(page);
  console.log('AFTER_REPEATER_STATE=' + JSON.stringify(after));

  expect(after.length).toBe(before.length + 1);
  expect(after.at(-1).visibleInputs).toBeGreaterThan(0);
});
