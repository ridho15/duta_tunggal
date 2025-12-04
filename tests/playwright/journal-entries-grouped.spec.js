import { test, expect } from '@playwright/test';

// Helper function for login with retry logic
async function login(page, maxRetries = 3) {
    for (let attempt = 1; attempt <= maxRetries; attempt++) {
        try {
            console.log(`Starting login process... (attempt ${attempt}/${maxRetries})`);

            // Navigate to login page
            await page.goto('http://127.0.0.1:8009/admin/login', { waitUntil: 'networkidle', timeout: 30000 });

            // Clear local storage now that we're on a page
            await page.evaluate(() => {
                try {
                    localStorage.clear();
                    sessionStorage.clear();
                } catch (e) {
                    console.log('Could not clear storage:', e.message);
                }
            });

            // Wait for page to load and form to be visible
            await page.waitForSelector('#data\\.email, input[type="email"]', { timeout: 25000 });

            console.log('Page loaded, filling form...');

            // Fill login form using Filament/Livewire selectors
            const emailInput = page.locator('#data\\.email').first();
            await emailInput.waitFor({ state: 'visible', timeout: 15000 });
            await emailInput.clear();
            await emailInput.fill('ralamzah@gmail.com');

            const passwordInput = page.locator('#data\\.password').first();
            await passwordInput.waitFor({ state: 'visible', timeout: 15000 });
            await passwordInput.clear();
            await passwordInput.fill('ridho123');

            console.log('Form filled, submitting...');

            // Submit form
            await page.click('button[type="submit"]');

            console.log('Form submitted, waiting for navigation...');

            // Wait for navigation to admin dashboard
            await page.waitForURL('**/admin', { timeout: 10000 });

            console.log('Checking login status, attempt 1, current URL:', page.url());

            // Verify we're logged in by checking for dashboard elements
            await page.waitForSelector('.filament-page, .fi-sidebar, [data-testid="sidebar"]', { timeout: 15000 });

            console.log('Login successful!');
            return true;

        } catch (error) {
            console.log(`Login attempt ${attempt} failed:`, error.message);

            if (attempt === maxRetries) {
                throw new Error(`Login failed after ${maxRetries} attempts: ${error.message}`);
            }

            // Wait before retrying
            await page.waitForTimeout(2000);
        }
    }
}

test.describe('Journal Entries Grouped Page Test', () => {
    test.setTimeout(120000); // 2 minutes timeout

    test('should access journal-entries/grouped page without 404 error', async ({ page }) => {
        console.log('Starting Journal Entries Grouped page test...');

        // Step 1: Login to admin panel
        console.log('Step 1: Logging in...');
        await login(page);

        // Verify we're on admin dashboard
        await expect(page).toHaveURL(/.*\/admin/);
        console.log('✅ Successfully logged in and on admin dashboard');

        // Step 2: First navigate to journal entries list
        console.log('Step 2: Navigating to journal entries list first...');
        await page.goto('http://127.0.0.1:8009/admin/journal-entries', {
            waitUntil: 'networkidle',
            timeout: 30000
        });

        // Verify we're on journal entries list
        await expect(page).toHaveURL(/.*\/admin\/journal-entries/);
        console.log('✅ Successfully accessed journal entries list');

        // Step 3: Now try to navigate to grouped page
        console.log('Step 3: Navigating to journal-entries/grouped page...');
        await page.goto('http://127.0.0.1:8009/admin/journal-entries/grouped', {
            waitUntil: 'networkidle',
            timeout: 30000
        });

        // Step 4: Check what we actually get
        console.log('Step 4: Checking actual page content...');

        const currentURL = page.url();
        console.log('Current URL:', currentURL);

        const pageContent = await page.textContent('body');
        console.log('Page content preview:', pageContent.substring(0, 200));

        // Check if we get a 404
        if (pageContent.includes('404') || pageContent.includes('Not Found')) {
            console.log('❌ Page shows 404 error');
            // Take screenshot for debugging
            await page.screenshot({ path: 'test-results/grouped-page-404.png' });
            throw new Error('Page returned 404 error');
        }

        // Check if redirected to login
        if (currentURL.includes('/admin/login')) {
            console.log('❌ Redirected to login page');
            throw new Error('Redirected to login page - authentication failed');
        }

        // If we get here, page loaded successfully
        console.log('✅ Page loaded without 404 or redirect');

        // Try to find any content that indicates successful loading
        const hasContent = pageContent.length > 1000; // Reasonable content length
        if (hasContent) {
            console.log('✅ Page has substantial content');
        }

        console.log('🎉 Test completed successfully!');
    });

    test('should access grouped page via button from list page', async ({ page }) => {
        console.log('Testing access to grouped page via button from list page...');

        // Step 1: Login
        await login(page);
        console.log('✅ Logged in successfully');

        // Step 2: Navigate to journal entries list
        await page.goto('http://127.0.0.1:8009/admin/journal-entries', {
            waitUntil: 'networkidle',
            timeout: 30000
        });
        console.log('✅ Navigated to journal entries list');

        // Step 3: Look for "Grouped View" button
        const groupedButton = page.locator('text=Grouped View').first();
        const buttonExists = await groupedButton.count() > 0;

        if (!buttonExists) {
            console.log('❌ Grouped View button not found');
            await page.screenshot({ path: 'test-results/no-grouped-button.png' });
            throw new Error('Grouped View button not found on journal entries list page');
        }

        console.log('✅ Grouped View button found');

        // Step 4: Click the Grouped View button
        await groupedButton.click();
        console.log('✅ Clicked Grouped View button');

        // Step 5: Wait for navigation and check result
        await page.waitForTimeout(2000);

        const currentURL = page.url();
        console.log('Current URL after click:', currentURL);

        const pageContent = await page.textContent('body');
        console.log('Page content preview:', pageContent.substring(0, 200));

        // Check for 404
        if (pageContent.includes('404') || pageContent.includes('Not Found')) {
            console.log('❌ Page shows 404 error after button click');
            await page.screenshot({ path: 'test-results/grouped-button-404.png' });
            throw new Error('Grouped View button leads to 404 page');
        }

        // Check if we're on the grouped page
        if (currentURL.includes('/admin/journal-entries/grouped')) {
            console.log('✅ Successfully navigated to grouped page via button');
        } else {
            console.log('❌ Not on grouped page. Current URL:', currentURL);
            throw new Error('Button did not navigate to grouped page');
        }

        console.log('🎉 Test completed successfully via button navigation!');
    });
});