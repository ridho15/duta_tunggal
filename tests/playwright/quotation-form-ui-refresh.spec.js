import { test, expect } from '@playwright/test';

const APP_URL = 'http://127.0.0.1:8000';
const LOGIN_URL = `${APP_URL}/admin/login`;
const QUOTATION_CREATE_URL = `${APP_URL}/admin/quotations/create`;
const CREDENTIALS = {
  email: 'ralamzah@gmail.com',
  password: 'ridho123',
};

async function ensureLoggedIn(page) {
  await page.goto(QUOTATION_CREATE_URL, { waitUntil: 'domcontentloaded' });

  if (new URL(page.url()).pathname.endsWith('/login')) {
    await page.goto(LOGIN_URL, { waitUntil: 'domcontentloaded' });
    await page.locator('#data\\.email').fill(CREDENTIALS.email);
    await page.locator('#data\\.password').fill(CREDENTIALS.password);
    await page.locator('form').getByRole('button', { name: /masuk|login|sign in/i }).click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 30000 });
    await page.goto(QUOTATION_CREATE_URL, { waitUntil: 'domcontentloaded' });
  }

  await expect(page).toHaveURL(/\/admin\/quotations\/create/);
}

test.describe('Quotation Form UI refresh', () => {
  test('create page renders new quotation item UX and calculation correctly', async ({ page }) => {
    const pageErrors = [];
    const consoleWarnings = [];

    page.on('pageerror', (err) => pageErrors.push(String(err)));
    page.on('console', (msg) => {
      if (msg.type() === 'warning' || msg.type() === 'error') {
        consoleWarnings.push(msg.text());
      }
    });

    await ensureLoggedIn(page);
    await page.waitForLoadState('networkidle');

    await expect(page.getByRole('heading', { name: /Buat Quotation/i })).toBeVisible();

    // Validate tax type now uses bullet/radio options.
    await expect(page.getByRole('radio', { name: 'Non Pajak' })).toBeVisible();
    await expect(page.getByRole('radio', { name: /PPN Excluded/i })).toBeVisible();
    await expect(page.getByRole('radio', { name: /PPN Included/i })).toBeVisible();

    // Validate repeater is expandable/collapsible.
    await expect(page.getByRole('button', { name: /Sembunyikan/i }).first()).toBeVisible();

    // Fill key item fields and verify derived values.
    const qtyInput = page.getByRole('textbox', { name: /^Qty\*/i }).first();
    const unitPriceInput = page.getByRole('textbox', { name: /^Unit Price\*/i }).first();
    const discountInput = page.getByRole('spinbutton', { name: /^Discount \(%\)/i }).first();

    await qtyInput.fill('2');
    await unitPriceInput.fill('100000');
    await discountInput.fill('10');
    await discountInput.press('Tab');
    await page.waitForTimeout(400);

    const states = await page.evaluate(() => {
      const readBySuffix = (suffix) => {
        const el = Array.from(document.querySelectorAll('input')).find((x) => x.id.endsWith(suffix));
        if (!el) return null;
        const style = window.getComputedStyle(el);
        return {
          id: el.id,
          value: el.value,
          readOnly: !!el.readOnly,
          backgroundColor: style.backgroundColor,
          color: style.color,
          cursor: style.cursor,
        };
      };

      return {
        totalAmount: readBySuffix('total_amount'),
        totalPrice: readBySuffix('.total_price'),
        discountNominal: readBySuffix('.discount_nominal'),
        tax: readBySuffix('.tax'),
        taxNominal: readBySuffix('.tax_nominal'),
        subtotal: readBySuffix('.subtotal'),
      };
    });

    expect(states.totalPrice?.value).toBe('200.000');
    expect(states.discountNominal?.value).toBe('20.000');
    expect(states.taxNominal?.value).toBe('0');
    expect(states.subtotal?.value).toBe('180.000');
    expect(states.totalAmount?.value).toBe('180.000');

    const grayFields = [
      states.totalAmount,
      states.totalPrice,
      states.discountNominal,
      states.tax,
      states.taxNominal,
      states.subtotal,
    ];

    for (const field of grayFields) {
      expect(field?.readOnly).toBeTruthy();
      expect(field?.backgroundColor).toBe('rgb(243, 244, 246)');
      expect(field?.color).toBe('rgb(107, 114, 128)');
      expect(field?.cursor).toBe('not-allowed');
    }

    // Guard against the previous Alpine issue on quotation page.
    const runtimeProblems = [...pageErrors, ...consoleWarnings].filter((line) =>
      /textareaFormComponent|state is not defined/i.test(line),
    );

    expect(runtimeProblems, runtimeProblems.join('\n')).toHaveLength(0);
  });
});
