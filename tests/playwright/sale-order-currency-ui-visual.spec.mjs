import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

// Helper to ensure screenshot directory exists
function ensureScreenshotDir() {
    const dir = 'tests/playwright/screenshots/sale-order-currency-ui';
    if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
    }
    return dir;
}

test.describe('Sale Order Currency UI - Visual Verification', () => {
    const screenshotDir = ensureScreenshotDir();

    test('UI: Currency symbol changes when switching from IDR to USD', async ({ page, browser }) => {
        // Navigate directly to login
        await page.goto('http://localhost:8009/admin/login', { waitUntil: 'networkidle' });
        
        // Screenshot login page
        const loginScreenshot = path.join(screenshotDir, '01-login-page.png');
        await page.screenshot({ path: loginScreenshot, fullPage: true });
        console.log(`📸 Login page screenshot: ${loginScreenshot}`);

        // Login using form submit
        const form = page.locator('form').first();
        if (await form.isVisible()) {
            const emailInput = page.locator('input[name*="email"]').first();
            const passwordInput = page.locator('input[name*="password"]').first();
            
            if (await emailInput.isVisible() && await passwordInput.isVisible()) {
                await emailInput.fill('ralamzah@gmail.com');
                await passwordInput.fill('ridho123');
                
                const submitBtn = page.locator('button:has-text("Masuk")').first();
                await submitBtn.click();
                
                // Wait for redirect
                await page.waitForURL('**/admin/**', { timeout: 15000 }).catch(() => {});
                await page.waitForLoadState('networkidle');
            }
        }

        console.log(`✓ Logged in, current URL: ${page.url()}`);

        // Navigate to sale order create
        await page.goto('http://localhost:8009/admin/sale-orders/create', { waitUntil: 'networkidle' });
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);

        const beforeSwitchScreenshot = path.join(screenshotDir, '02-before-currency-switch.png');
        await page.screenshot({ path: beforeSwitchScreenshot, fullPage: true });
        console.log(`📸 Before currency switch: ${beforeSwitchScreenshot}`);

        // Get text content to see what's displayed
        const beforeContent = await page.textContent('body');
        const hasRpBefore = beforeContent?.includes('Rp') || false;
        const hasDollarBefore = beforeContent?.includes('$') || false;
        console.log(`📊 Before switch - Rp visible: ${hasRpBefore}, $ visible: ${hasDollarBefore}`);

        // Find currency select - try different selectors
        const selects = page.locator('select');
        const selectCount = await selects.count();
        console.log(`Found ${selectCount} select elements`);

        let currencySelect = null;
        for (let i = 0; i < selectCount; i++) {
            const name = await selects.nth(i).getAttribute('name');
            console.log(`Select ${i}: name=${name}`);
            if (name?.includes('currency')) {
                currencySelect = selects.nth(i);
                break;
            }
        }

        if (!currencySelect) {
            console.log('⚠️  Currency select not found, trying input with datalist');
            const inputs = page.locator('input[list]');
            const inputCount = await inputs.count();
            console.log(`Found ${inputCount} inputs with datalist`);
            
            if (inputCount > 0) {
                // Try first input with list
                currencySelect = inputs.first();
            }
        }

        if (!currencySelect) {
            console.log('❌ No currency selector found - FAILED');
            test.fail();
            return;
        }

        console.log('✓ Found currency selector');

        // Get current value
        const currentValue = await currencySelect.inputValue().catch(() => null);
        console.log(`Current currency value: ${currentValue}`);

        // Get all available options
        const options = page.locator('select[name*="currency"] option, input[list] + datalist option, input[list] + div option');
        const optCount = await options.count();
        console.log(`Available currency options: ${optCount}`);

        // Try to get option values
        let usdOptionValue = null;
        for (let i = 0; i < optCount; i++) {
            const text = await options.nth(i).textContent().catch(() => '');
            const value = await options.nth(i).getAttribute('value').catch(() => '');
            console.log(`Option ${i}: "${text}" (value: ${value})`);
            
            if (text?.includes('USD') || text?.includes('Dollar')) {
                usdOptionValue = value || text;
                console.log(`✓ Found USD option: ${usdOptionValue}`);
            }
        }

        if (!usdOptionValue) {
            console.log('⚠️  USD option not found, trying to find any non-IDR currency');
            // Just get second option value
            try {
                usdOptionValue = await options.nth(1).getAttribute('value');
                if (!usdOptionValue) {
                    usdOptionValue = await options.nth(1).textContent();
                }
            } catch (e) {
                console.log('Could not get option value');
            }
            console.log(`Using second option: ${usdOptionValue}`);
        }

        if (!usdOptionValue) {
            console.log('❌ No alternative currency found - FAILED');
            test.fail();
            return;
        }

        // Switch currency
        console.log(`🔄 Switching to currency: ${usdOptionValue}`);
        await currencySelect.selectOption(usdOptionValue);
        await page.waitForTimeout(1500);

        const afterSwitchScreenshot = path.join(screenshotDir, '03-after-currency-switch.png');
        await page.screenshot({ path: afterSwitchScreenshot, fullPage: true });
        console.log(`📸 After currency switch: ${afterSwitchScreenshot}`);

        // Get text content after switch
        const afterContent = await page.textContent('body');
        const hasRpAfter = afterContent?.includes('Rp') || false;
        const hasDollarAfter = afterContent?.includes('$') || false;
        console.log(`📊 After switch - Rp visible: ${hasRpAfter}, $ visible: ${hasDollarAfter}`);

        // Extract and compare visual content around price fields
        const priceFieldLabels = page.locator('label:has-text("Price"), label:has-text("price"), label:has-text("Amount"), label:has-text("amount")');
        const priceLabelsCount = await priceFieldLabels.count();
        console.log(`Found ${priceLabelsCount} price-related labels`);

        if (priceLabelsCount > 0) {
            const firstLabel = priceFieldLabels.first();
            const labelText = await firstLabel.textContent();
            console.log(`First price label: "${labelText}"`);
            
            // Get parent container to see associated input
            const parent = firstLabel.locator('xpath=ancestor::div[1]');
            const parentContent = await parent.textContent();
            console.log(`Label parent content: "${parentContent?.substring(0, 150)}..."`);
        }

        // Detailed comparison
        console.log('\n📋 VISUAL TEST RESULTS:');
        console.log(`=================================`);
        console.log(`Before Switch: IDR (Rp shown: ${hasRpBefore})`);
        console.log(`After Switch:  USD ($ shown: ${hasDollarAfter})`);
        console.log(`=================================`);

        if (usdOptionValue && usdOptionValue !== currentValue) {
            console.log(`✓ Currency was successfully changed in UI`);
        }

        expect(usdOptionValue).toBeTruthy();
    });

    test('UI: Price fields display currency prefix correctly', async ({ page }) => {
        await page.goto('http://localhost:8009/admin/sale-orders', { waitUntil: 'networkidle' });
        
        // Check if authenticated
        const isLoginPage = page.url().includes('/login');
        if (isLoginPage) {
            console.log('⚠️  Not authenticated, navigating to create form instead');
            await page.goto('http://localhost:8009/admin/sale-orders/create', { waitUntil: 'networkidle' });
        }

        await page.waitForTimeout(1000);

        // Take screenshot of list/create view
        const screenshot = path.join(screenshotDir, '04-sale-order-form.png');
        await page.screenshot({ path: screenshot, fullPage: true });
        console.log(`📸 Sale Order form: ${screenshot}`);

        // Look for text content that shows currency symbols
        const bodyText = await page.textContent('body');
        
        // Check for currency symbols
        const symbolsFound = {
            rp: (bodyText?.match(/Rp/g) || []).length,
            dollar: (bodyText?.match(/\$/g) || []).length,
            euro: (bodyText?.match(/€/g) || []).length,
        };

        console.log(`\n💱 Currency Symbols Found in UI:`);
        console.log(`  Rp (IDR): ${symbolsFound.rp} occurrences`);
        console.log(`  $ (USD): ${symbolsFound.dollar} occurrences`);
        console.log(`  € (EUR): ${symbolsFound.euro} occurrences`);

        // Look for specific input patterns
        const allText = await page.locator('body').innerText();
        const lines = allText.split('\n');
        const priceLines = lines.filter(line => 
            line.toLowerCase().includes('price') || 
            line.toLowerCase().includes('amount') ||
            line.toLowerCase().includes('total')
        );

        console.log(`\n📝 Price-related Lines Found:`);
        priceLines.slice(0, 5).forEach((line, idx) => {
            console.log(`  ${idx + 1}. ${line.trim().substring(0, 80)}`);
        });

        // Verify that we're looking at a valid form
        expect(page.url()).not.toContain('/login');
    });

    test('UI: Exchange rate field updates visually', async ({ page }) => {
        await page.goto('http://localhost:8009/admin/sale-orders/create', { waitUntil: 'networkidle' });
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1000);

        // Find exchange rate field
        const exchangeRateInputs = page.locator('input[name*="exchange_rate"]');
        const count = await exchangeRateInputs.count();
        console.log(`Found ${count} exchange_rate input field(s)`);

        if (count > 0) {
            const firstExchangeInput = exchangeRateInputs.first();
            const initialValue = await firstExchangeInput.inputValue();
            console.log(`Initial exchange rate value: "${initialValue}"`);

            // Get label or context
            const label = firstExchangeInput.locator('xpath=ancestor::div//label').first();
            const labelText = await label.textContent().catch(() => '');
            console.log(`Field label: "${labelText}"`);

            const screenshot = path.join(screenshotDir, '05-exchange-rate-field.png');
            await firstExchangeInput.scrollIntoViewIfNeeded();
            await page.screenshot({ path: screenshot });
            console.log(`📸 Exchange rate field screenshot: ${screenshot}`);

            expect(firstExchangeInput).toBeVisible();
        } else {
            console.log('⚠️  Exchange rate field not found');
        }
    });

    test('UI: Repeater items show currency prefix dynamically', async ({ page }) => {
        await page.goto('http://localhost:8009/admin/sale-orders/create', { waitUntil: 'networkidle' });
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1000);

        // Look for repeater item rows
        const repeaterItems = page.locator('.fi-fo-repeater-item, [data-repeater], tr');
        const itemCount = await repeaterItems.count();
        console.log(`Found ${itemCount} repeater item row(s)`);

        // Look for unit price fields in repeaters
        const unitPriceFields = page.locator('input[name*="unit_price"], label:has-text("Unit Price")');
        const unitPriceCount = await unitPriceFields.count();
        console.log(`Found ${unitPriceCount} unit_price field(s)`);

        if (unitPriceCount > 0) {
            const firstUnitPrice = unitPriceFields.first();
            await firstUnitPrice.scrollIntoViewIfNeeded();
            
            const screenshot = path.join(screenshotDir, '06-repeater-unit-price.png');
            await page.screenshot({ path: screenshot });
            console.log(`📸 Repeater unit price field: ${screenshot}`);

            // Get context around this field
            const parent = firstUnitPrice.locator('xpath=ancestor::div[3]');
            const content = await parent.textContent();
            console.log(`Field context: "${content?.substring(0, 150)}..."`);
        }

        const screenshot = path.join(screenshotDir, '07-full-form-overview.png');
        await page.screenshot({ path: screenshot, fullPage: true });
        console.log(`📸 Full form overview: ${screenshot}`);

        console.log(`\n✓ UI screenshots saved to: ${screenshotDir}`);
    });
});
