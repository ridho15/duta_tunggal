import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const screenshotDir = path.resolve('tests/playwright/screenshots/sale-order-currency-prefix-precise');

function ensureScreenshotDir() {
    fs.mkdirSync(screenshotDir, { recursive: true });
}

async function login(page) {
    await page.goto('http://localhost:8009/admin/login', { waitUntil: 'networkidle' });

    if (!page.url().includes('/login')) {
        return;
    }

    const loginHeading = page.getByRole('heading', { name: /Masuk ke akun Anda/i });
    await loginHeading.waitFor({ state: 'visible', timeout: 15000 });

    const email = page.getByRole('textbox', { name: /Alamat email/i }).first();
    const password = page.getByRole('textbox', { name: /Kata sandi/i }).first();

    await email.fill('ralamzah@gmail.com');
    await password.fill('ridho123');
    await page.getByRole('button', { name: /masuk|login|sign in/i }).click();

    await page.waitForFunction(() => !window.location.pathname.endsWith('/login'), { timeout: 30000 });

    const loginError = page.getByText(/kredensial yang diberikan tidak dapat ditemukan|these credentials do not match/i).first();
    if (await loginError.isVisible().catch(() => false)) {
        throw new Error('Playwright login failed with invalid credentials for ralamzah@gmail.com.');
    }
}

async function getFieldPrefix(page, labelText) {
    return page.evaluate((labelTextArg) => {
        const label = Array.from(document.querySelectorAll('label')).find((node) => {
            return (node.textContent || '').trim() === labelTextArg;
        });

        const wrapper = label?.closest('.fi-fo-field-wrp');
        const prefixNode = wrapper?.querySelector('.fi-input-wrp-prefix, .fi-fo-field-wrp-prefix, [class*="prefix"]');

        return prefixNode?.textContent?.trim() || null;
    }, labelText);
}

test.describe('Sale Order currency prefix UI', () => {
    test('switching currency updates visible prefixes and screenshots', async ({ page }) => {
        ensureScreenshotDir();

        await login(page);
        await page.goto('http://localhost:8009/admin/sale-orders/1/edit', { waitUntil: 'networkidle' });
        await page.waitForLoadState('networkidle');

        const currencyWrapper = page.locator('.fi-fo-field-wrp').filter({ has: page.getByText('Currency', { exact: true }) }).first();
        await expect(currencyWrapper).toBeVisible();

        const before = {
            totalAmountPrefix: await getFieldPrefix(page, 'Total Amount'),
            unitPricePrefix: await getFieldPrefix(page, 'Unit Price'),
        };

        await page.screenshot({ path: path.join(screenshotDir, 'before-usd-switch.png'), fullPage: true });

        await expect(before.totalAmountPrefix).toBe('Rp');
        await expect(before.unitPricePrefix).toBe('Rp');

        const combobox = currencyWrapper.locator('[role="combobox"]').first();
        await combobox.click();
        await page.getByRole('option', { name: 'US Dollar (USD)' }).click();

        await expect.poll(async () => getFieldPrefix(page, 'Total Amount'), {
            timeout: 10000,
        }).toBe('$');

        await expect.poll(async () => getFieldPrefix(page, 'Unit Price'), {
            timeout: 10000,
        }).toBe('$');

        const after = {
            totalAmountPrefix: await getFieldPrefix(page, 'Total Amount'),
            unitPricePrefix: await getFieldPrefix(page, 'Unit Price'),
        };

        await page.screenshot({ path: path.join(screenshotDir, 'after-usd-switch.png'), fullPage: true });

        expect(after.totalAmountPrefix).toBe('$');
        expect(after.unitPricePrefix).toBe('$');
    });
});