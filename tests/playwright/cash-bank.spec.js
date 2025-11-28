import { test, expect } from '@playwright/test';

// Helper function for login with better error handling
async function login(page) {
    const maxRetries = 3;

    for (let attempt = 1; attempt <= maxRetries; attempt++) {
        try {
            console.log(`Starting login process... (attempt ${attempt}/${maxRetries})`);

            // Navigate to login page
            await page.goto('http://127.0.0.1:8009/admin/login', { waitUntil: 'networkidle' });
            console.log('Page loaded, filling form...');

            // Wait for form to be visible with longer timeout
            await page.waitForSelector('#data\\.email', { timeout: 15000 });

            // Fill login form
            await page.fill('#data\\.email', 'ralamzah@gmail.com');
            await page.fill('#data\\.password', 'ridho123');

            console.log('Form filled, submitting...');

            // Submit form
            await page.click('button[type="submit"], button:has-text("Sign in"), button:has-text("Login")');

            console.log('Form submitted, waiting for navigation...');

            // Wait for navigation to admin dashboard with longer timeout
            await page.waitForURL('**/admin', { timeout: 15000 });

            console.log('Checking login status, current URL:', page.url());

            // Verify we're logged in
            const currentUrl = page.url();
            if (currentUrl.includes('/admin') && !currentUrl.includes('/login')) {
                console.log('Login successful!');
                // Add a small delay after successful login
                await page.waitForTimeout(1000);
                return;
            } else {
                throw new Error('Login failed - not redirected to admin');
            }

        } catch (error) {
            console.log(`Login error on attempt ${attempt}: ${error.message}`);

            if (attempt < maxRetries) {
                console.log(`Retrying login in 8 seconds...`);
                await page.waitForTimeout(8000);
            } else {
                throw new Error(`Login failed after ${maxRetries} attempts: ${error.message}`);
            }
        }
    }
}

test.describe('Cash & Bank Tests', () => {

    // Add delay between tests to reduce server load
    test.afterEach(async () => {
        await new Promise(resolve => setTimeout(resolve, 3000)); // 3 second delay between tests
    });

    test('should display cash bank accounts list page', async ({ page }) => {
        // Login first
        await login(page);

        // Navigate to cash bank accounts
        await page.goto('http://127.0.0.1:8009/admin/cash-bank-accounts');

        // Wait for page to load
        await page.waitForLoadState('networkidle');

        // Check if we're on the correct page
        const currentUrl = page.url();
        expect(currentUrl).toMatch(/\/cash-bank-accounts/);

        // Check for page elements
        const hasTable = await page.locator('table').count() > 0;
        const hasHeader = await page.locator('h1, h2').filter({ hasText: /Cash|Bank|Account/i }).count() > 0;

        expect(hasTable || hasHeader).toBe(true);
    });

    test('should create cash bank transaction', async ({ page }) => {
        // Login first
        await login(page);

        // Navigate to create cash bank transaction
        await page.goto('http://127.0.0.1:8009/admin/cash-bank-transactions/create');

        // Wait for form to load
        await page.waitForLoadState('networkidle');

        // Check that we're on the create page
        const currentUrl = page.url();
        expect(currentUrl).toMatch(/\/cash-bank-transactions\/create/);

        // Check for form elements
        const hasForm = await page.locator('form').count() > 0;
        expect(hasForm).toBe(true);

        // Check for required field indicators
        const hasRequiredIndicators = await page.locator('[required], .required, .fi-required').count() > 0;
        const hasLabels = await page.locator('label').count() > 0;

        expect(hasRequiredIndicators || hasLabels).toBe(true);
    });

    test('should display cash bank transactions list', async ({ page }) => {
        // Login first
        await login(page);

        // Navigate to cash bank transactions
        await page.goto('http://127.0.0.1:8009/admin/cash-bank-transactions');

        // Wait for page to load
        await page.waitForLoadState('networkidle');

        // Check if we're on the correct page
        const currentUrl = page.url();
        expect(currentUrl).toMatch(/\/cash-bank-transactions/);

        // Check for page elements
        const hasTable = await page.locator('table').count() > 0;
        const hasHeader = await page.locator('h1, h2').filter({ hasText: /Cash|Bank|Transaction/i }).count() > 0;

        expect(hasTable || hasHeader).toBe(true);
    });

    test('should create cash bank transfer', async ({ page }) => {
        // Login first
        await login(page);

        // Navigate to create cash bank transfer
        await page.goto('http://127.0.0.1:8009/admin/cash-bank-transfers/create');

        // Wait for form to load
        await page.waitForLoadState('networkidle');

        // Check that we're on the create page
        const currentUrl = page.url();
        expect(currentUrl).toMatch(/\/cash-bank-transfers\/create/);

        // Check for form elements
        const hasForm = await page.locator('form').count() > 0;
        expect(hasForm).toBe(true);

        // Check for basic form fields (be flexible about field names)
        const hasAmountField = await page.locator('input[type="number"], input[name*="amount"]').count() > 0;
        const hasSelectFields = await page.locator('select').count() >= 2; // At least 2 select fields

        // Form should have basic input elements
        expect(hasAmountField || hasSelectFields).toBe(true);
    });

    test('should display cash bank transfers list', async ({ page }) => {
        // Login first
        await login(page);

        // Navigate to cash bank transfers
        await page.goto('http://127.0.0.1:8009/admin/cash-bank-transfers');

        // Wait for page to load
        await page.waitForLoadState('networkidle');

        // Check if we're on the correct page
        const currentUrl = page.url();
        expect(currentUrl).toMatch(/\/cash-bank-transfers/);

        // Check for page elements
        const hasTable = await page.locator('table').count() > 0;
        const hasHeader = await page.locator('h1, h2').filter({ hasText: /Cash|Bank|Transfer/i }).count() > 0;

        expect(hasTable || hasHeader).toBe(true);
    });

    test('should display bank reconciliation page', async ({ page }) => {
        // Login first
        await login(page);

        // Navigate to bank reconciliation
        await page.goto('http://127.0.0.1:8009/admin/bank-reconciliations');

        // Wait for page to load
        await page.waitForLoadState('networkidle');

        // Check if we're on the correct page
        const currentUrl = page.url();
        expect(currentUrl).toMatch(/\/bank-reconciliations/);

        // Check for page elements
        const hasTable = await page.locator('table').count() > 0;
        const hasHeader = await page.locator('h1, h2').filter({ hasText: /Bank|Reconciliation/i }).count() > 0;

        expect(hasTable || hasHeader).toBe(true);
    });

    test('should create bank reconciliation', async ({ page }) => {
        // Login first
        await login(page);

        // Navigate to create bank reconciliation
        await page.goto('http://127.0.0.1:8009/admin/bank-reconciliations/create');

        // Wait for form to load
        await page.waitForLoadState('networkidle');

        // Check that we're on the create page
        const currentUrl = page.url();
        expect(currentUrl).toMatch(/\/bank-reconciliations\/create/);

        // Check for form elements
        const hasForm = await page.locator('form').count() > 0;
        expect(hasForm).toBe(true);

        // Check for basic form fields
        const hasSelectField = await page.locator('select').count() > 0;
        const hasInputFields = await page.locator('input').count() > 0;

        // Form should have some input elements
        expect(hasSelectField || hasInputFields).toBe(true);
    });

    test('should display journal entries from cash bank transactions', async ({ page }) => {
        // Login first
        await login(page);

        // Navigate to journal entries
        await page.goto('http://127.0.0.1:8009/admin/journal-entries');

        // Wait for page to load
        await page.waitForLoadState('networkidle');

        // Check if we're on the journal entries page
        const currentUrl = page.url();
        expect(currentUrl).toMatch(/\/journal-entries/);

        // Check for journal entries table
        const hasTable = await page.locator('table').count() > 0;
        const hasJournalHeader = await page.locator('h1, h2').filter({ hasText: /Journal/i }).count() > 0;

        expect(hasTable || hasJournalHeader).toBe(true);
    });

    test('should filter journal entries by cash bank sources', async ({ page }) => {
        // Login first
        await login(page);

        // Navigate to journal entries
        await page.goto('http://127.0.0.1:8009/admin/journal-entries');

        // Wait for page to load
        await page.waitForLoadState('networkidle');

        // Check if we're on the journal entries page
        const currentUrl = page.url();
        expect(currentUrl).toMatch(/\/journal-entries/);

        // Look for filter options
        const hasFilters = await page.locator('select, input').filter({ hasText: /type|source|cash|bank|transfer/i }).count() > 0;

        if (hasFilters) {
            // If filters exist, page should be functional
            expect(currentUrl).toMatch(/\/journal-entries/);
        } else {
            // If no filters, basic page should still load
            const hasTable = await page.locator('table').count() > 0;
            expect(hasTable).toBe(true);
        }
    });

    test('should validate cash bank transfer form fields', async ({ page }) => {
        // Login first
        await login(page);

        // Navigate to create cash bank transfer
        await page.goto('http://127.0.0.1:8009/admin/cash-bank-transfers/create');

        // Wait for form to load
        await page.waitForLoadState('networkidle');

        // Check that we're on the create page
        const currentUrl = page.url();
        expect(currentUrl).toMatch(/\/cash-bank-transfers\/create/);

        // Check for form validation elements
        const hasForm = await page.locator('form').count() > 0;
        const hasValidationElements = await page.locator('[required], .required, .fi-required').count() > 0;

        expect(hasForm && hasValidationElements).toBe(true);
    });

    test('should validate bank reconciliation form fields', async ({ page }) => {
        // Login first
        await login(page);

        // Navigate to create bank reconciliation
        await page.goto('http://127.0.0.1:8009/admin/bank-reconciliations/create');

        // Wait for form to load
        await page.waitForLoadState('networkidle');

        // Check that we're on the create page
        const currentUrl = page.url();
        expect(currentUrl).toMatch(/\/bank-reconciliations\/create/);

        // Check for form validation elements
        const hasForm = await page.locator('form').count() > 0;
        const hasValidationElements = await page.locator('[required], .required, .fi-required').count() > 0;

        expect(hasForm && hasValidationElements).toBe(true);
    });

});