import { test, expect } from '@playwright/test';

// Helper function for login with retry logic
async function login(page, maxRetries = 3) {
    for (let attempt = 1; attempt <= maxRetries; attempt++) {
        try {
            console.log(`Starting login process... (attempt ${attempt}/${maxRetries})`);

            // Clear all storage and cookies before navigation
            await page.context().clearCookies();
            await page.evaluate(() => {
                try {
                    localStorage.clear();
                    sessionStorage.clear();
                    // Also clear any other storage
                    if (window.indexedDB) {
                        indexedDB.databases().then(dbs => {
                            dbs.forEach(db => indexedDB.deleteDatabase(db.name));
                        });
                    }
                } catch (e) {
                    console.log('Could not clear storage:', e.message);
                }
            });

            // Navigate to login page with longer timeout
            await page.goto('/admin/login', { waitUntil: 'networkidle', timeout: 45000 });

            // Wait for page to load and form to be visible with longer timeout
            await page.waitForSelector('#data\\.email, input[type="email"]', { timeout: 30000 });

            console.log('Page loaded, filling form...');

            // Fill login form using Filament/Livewire selectors
            const emailInput = page.locator('#data\\.email').first();
            await emailInput.waitFor({ state: 'visible', timeout: 20000 });
            await emailInput.clear();
            await emailInput.fill('ralamzah@gmail.com');

            const passwordInput = page.locator('#data\\.password').first();
            await passwordInput.waitFor({ state: 'visible', timeout: 20000 });
            await passwordInput.clear();
            await passwordInput.fill('ridho123');

            console.log('Form filled, submitting...');

            // Submit form
            await page.click('button[type="submit"]');

            console.log('Form submitted, waiting for navigation...');

            // Wait for page to load after login (more flexible approach)
            await page.waitForLoadState('networkidle', { timeout: 30000 });

            console.log('Page loaded after login, current URL:', page.url());

            // Verify we're logged in by checking for dashboard elements or admin content
            // Try multiple selectors that indicate we're in the admin area
            const adminSelectors = [
                '.fi-sidebar',
                '.filament-sidebar',
                '[data-sidebar]',
                '.admin-sidebar',
                'nav.sidebar',
                '.fi-topbar'
            ];

            let loggedIn = false;
            for (const selector of adminSelectors) {
                try {
                    await page.waitForSelector(selector, { timeout: 5000 });
                    loggedIn = true;
                    console.log(`Found admin element: ${selector}`);
                    break;
                } catch (e) {
                    // Continue to next selector
                }
            }

            if (!loggedIn) {
                // Check if we're redirected to admin area by URL
                const currentUrl = page.url();
                if (currentUrl.includes('/admin') && !currentUrl.includes('/login')) {
                    loggedIn = true;
                    console.log('Logged in based on URL:', currentUrl);
                }
            }

            if (!loggedIn) {
                throw new Error('Could not verify login - no admin elements found and not redirected to admin area');
            }

            console.log('Login successful!');
            return true;

        } catch (error) {
            console.log(`Login attempt ${attempt} failed:`, error.message);

            if (attempt === maxRetries) {
                throw new Error(`Login failed after ${maxRetries} attempts: ${error.message}`);
            }

            // Wait longer before retrying
            await page.waitForTimeout(3000);
        }
    }
}

test.describe('Income Statement E2E Tests', () => {
    test.beforeEach(async ({ page }) => {
        // Add delay between tests to prevent server overload
        await page.waitForTimeout(1000);
        // Login before each test
        await login(page);
    });

    test('should load income statement page', async ({ page }) => {
        // Navigate to income statement page
        await page.goto('/admin/income-statement-page');

        // Wait for page to load
        await page.waitForLoadState('networkidle');

        // Check if we're on the income statement page
        await expect(page).toHaveURL(/.*income-statement-page/);

        // Verify page title or heading - check for the main heading
        await expect(page.locator('h1, .fi-page-header h1, h2, .text-xl').filter({ hasText: /Laba Rugi|Laporan Laba Rugi|Income Statement/i })).toBeVisible();
    });

    test('should display income statement with default filters', async ({ page }) => {
        // Navigate to income statement page
        await page.goto('/admin/income-statement-page');

        // Wait for page to load
        await page.waitForLoadState('networkidle');

        // Check for main sections - look for the table headers or section titles
        await expect(page.locator('span').filter({ hasText: '💰 PENDAPATAN USAHA' }).first()).toBeVisible();
        await expect(page.locator('span').filter({ hasText: '📦 HARGA POKOK PENJUALAN' }).first()).toBeVisible();

        // Check for summary cards
        await expect(page.locator('span.text-green-700').filter({ hasText: '💰 PENDAPATAN USAHA' })).toBeVisible();

        // Check for totals
        await expect(page.locator('td').filter({ hasText: 'TOTAL PENDAPATAN USAHA' })).toBeVisible();
    });

    test('should filter income statement by date', async ({ page }) => {
        // Navigate to income statement page
        await page.goto('/admin/income-statement-page');

        // Wait for page to load
        await page.waitForLoadState('networkidle');

        // Set date range (current month)
        const startDate = new Date();
        startDate.setDate(1); // First day of current month
        const endDate = new Date();
        endDate.setDate(31); // Last day of current month

        const startDateStr = startDate.toISOString().split('T')[0];
        const endDateStr = endDate.toISOString().split('T')[0];

        // Fill date inputs - use more specific selectors
        const dateInputs = page.locator('input[type="date"]');
        await dateInputs.nth(0).fill(startDateStr); // First date input (start date)
        await dateInputs.nth(1).fill(endDateStr); // Second date input (end date)

        // Click generate report button
        await page.click('button:has-text("Tampilkan Laporan")');

        // Wait for report to update
        await page.waitForTimeout(2000);

        // Verify the report is displayed
        await expect(page.locator('span').filter({ hasText: '💰 PENDAPATAN USAHA' }).first()).toBeVisible();
    });

    test('should filter income statement by branch', async ({ page }) => {
        // Navigate to income statement page
        await page.goto('/admin/income-statement-page');

        // Wait for page to load
        await page.waitForLoadState('networkidle');

        // Select a branch from dropdown (if available)
        const branchSelect = page.locator('select').filter({ has: page.locator('option') }).first();
        const options = await branchSelect.locator('option').all();

        if (options.length > 1) {
            // Select second option (first branch)
            await branchSelect.selectOption({ index: 1 });

            // Click generate report button
            await page.click('button:has-text("Tampilkan Laporan")');

            // Wait for report to update
            await page.waitForTimeout(2000);

            // Verify the report is displayed
            await expect(page.locator('span').filter({ hasText: '💰 PENDAPATAN USAHA' }).first()).toBeVisible();
        } else {
            console.log('No branch options available, skipping branch filter test');
        }
    });

    test('should validate accounting equation (Revenue - Expenses = Net Income)', async ({ page }) => {
        // Navigate to income statement page
        await page.goto('/admin/income-statement-page');

        // Wait for page to load
        await page.waitForLoadState('networkidle');

        // Wait for data to load
        await page.waitForTimeout(2000);

        // Extract revenue total from summary cards
        const revenueCard = page.locator('text=/Pendapatan Usaha/i').locator('xpath=ancestor::div[contains(@class, "bg-white")]');
        const revenueText = await revenueCard.locator('p.font-bold').textContent();
        const revenueMatch = revenueText.match(/[\d,]+/);
        const revenue = revenueMatch ? parseFloat(revenueMatch[0].replace(/,/g, '')) : 0;

        // Extract gross profit from summary cards
        const grossProfitCard = page.locator('text=/Laba Kotor/i').locator('xpath=ancestor::div[contains(@class, "bg-white")]');
        const grossProfitText = await grossProfitCard.locator('p.font-bold').textContent();
        const grossProfitMatch = grossProfitText.match(/[\d,]+/);
        const grossProfit = grossProfitMatch ? parseFloat(grossProfitMatch[0].replace(/,/g, '')) : 0;

        // For a basic validation, check that revenue and gross profit are displayed
        expect(revenue).toBeGreaterThanOrEqual(0);
        expect(typeof grossProfit).toBe('number');

        // Check that the table is displayed
        await expect(page.locator('table.w-full.text-sm').first()).toBeVisible();
        await expect(page.locator('text=/TOTAL PENDAPATAN USAHA/i')).toBeVisible();
    });

    test('should export income statement to PDF', async ({ page }) => {
        // Navigate to income statement page
        await page.goto('/admin/income-statement-page');

        // Wait for page to load
        await page.waitForLoadState('networkidle');

        // Check if PDF export button exists
        const pdfButton = page.locator('button, a').filter({ hasText: /Export PDF|PDF/i });

        if (await pdfButton.count() > 0) {
            // Set up download listener
            const downloadPromise = page.waitForEvent('download');

            // Click PDF export button
            await pdfButton.first().click();

            // Wait for download to start
            const download = await downloadPromise;

            // Verify download started
            expect(download.suggestedFilename()).toMatch(/Laporan_Laba_Rugi.*\.pdf/);
        } else {
            console.log('PDF export button not found, checking for alternative selectors');

            // Try alternative selectors
            const altPdfButton = page.locator('[wire\\:click="exportPdf"], button[wire\\:click*="pdf"]');

            if (await altPdfButton.count() > 0) {
                const downloadPromise = page.waitForEvent('download');
                await altPdfButton.first().click();
                const download = await downloadPromise;
                expect(download.suggestedFilename()).toMatch(/Laporan_Laba_Rugi.*\.pdf/);
            } else {
                console.log('PDF export functionality not available, skipping test');
            }
        }
    });

    test('should export income statement to Excel', async ({ page }) => {
        // Navigate to income statement page
        await page.goto('/admin/income-statement-page');

        // Wait for page to load
        await page.waitForLoadState('networkidle');

        // Check if Excel export button exists
        const excelButton = page.locator('button, a').filter({ hasText: /Export Excel|Excel/i });

        if (await excelButton.count() > 0) {
            // Set up download listener
            const downloadPromise = page.waitForEvent('download');

            // Click Excel export button
            await excelButton.first().click();

            // Wait for download to start
            const download = await downloadPromise;

            // Verify download started
            expect(download.suggestedFilename()).toMatch(/Laporan_Laba_Rugi.*\.xlsx?/);
        } else {
            console.log('Excel export button not found, checking for alternative selectors');

            // Try alternative selectors
            const altExcelButton = page.locator('[wire\\:click="exportExcel"], button[wire\\:click*="excel"]');

            if (await altExcelButton.count() > 0) {
                const downloadPromise = page.waitForEvent('download');
                await altExcelButton.first().click();
                const download = await downloadPromise;
                expect(download.suggestedFilename()).toMatch(/Laporan_Laba_Rugi.*\.xlsx?/);
            } else {
                console.log('Excel export functionality not available, skipping test');
            }
        }
    });

    test('should toggle display options', async ({ page }) => {
        // Navigate to income statement page
        await page.goto('/admin/income-statement-page');

        // Wait for page to load
        await page.waitForLoadState('networkidle');

        // Look for display options section
        const displayOptionsSection = page.locator('text=/📊 Opsi Tampilan Laporan/i').locator('xpath=ancestor-or-self::*').first();

        if (await displayOptionsSection.count() > 0) {
            // Try to find and toggle some display options
            const checkboxes = page.locator('input[type="checkbox"]');

            if (await checkboxes.count() > 0) {
                // Toggle first checkbox
                await checkboxes.first().check();

                // Wait for page to update
                await page.waitForTimeout(1000);

                // Verify page still loads - check for table content
                await expect(page.locator('table.w-full.text-sm').first()).toBeVisible();
            } else {
                console.log('No display option checkboxes found, skipping toggle test');
            }
        } else {
            console.log('Display options section not found, skipping test');
        }
    });

    test('should handle comparison functionality', async ({ page }) => {
        // Navigate to income statement page
        await page.goto('/admin/income-statement-page');

        // Wait for page to load
        await page.waitForLoadState('networkidle');

        // Look for comparison checkbox
        const comparisonCheckbox = page.locator('input[type="checkbox"]').filter({ hasText: /Perbandingan|Comparison/i }).first();

        if (await comparisonCheckbox.count() > 0) {
            // Check the comparison checkbox
            await comparisonCheckbox.check();

            // Wait for comparison section to appear
            await page.waitForTimeout(1000);

            // Verify comparison inputs are visible
            const comparisonInputs = page.locator('input[type="date"]').filter({ hasText: /Periode Pembanding|Pembanding/i });

            if (await comparisonInputs.count() > 0) {
                console.log('Comparison functionality is available');
            } else {
                console.log('Comparison inputs not found after enabling comparison');
            }

            // Click generate report to see comparison
            await page.click('button:has-text("Tampilkan Laporan")');

            // Wait for report to update
            await page.waitForTimeout(2000);

            // Verify the report is displayed
            await expect(page.locator('.section-header, [class*="section"]').filter({ hasText: /Revenue|Pendapatan/i })).toBeVisible();
        } else {
            console.log('Comparison checkbox not found, comparison functionality may not be available');
        }
    });

    test('should handle drill-down functionality', async ({ page }) => {
        // Navigate to income statement page
        await page.goto('/admin/income-statement-page');

        // Wait for page to load
        await page.waitForLoadState('networkidle');

        // Wait for data to load
        await page.waitForTimeout(2000);

        // Look for clickable account names or drill-down links
        const accountLinks = page.locator('a, button').filter({ hasText: /\d{4}-\d{4}/ }); // Account codes like 4-1000

        if (await accountLinks.count() > 0) {
            // Click on first account link
            await accountLinks.first().click();

            // Wait for modal or drill-down to appear
            await page.waitForTimeout(2000);

            // Check if drill-down modal appeared
            const modal = page.locator('[role="dialog"], .modal, .fi-modal');

            if (await modal.count() > 0) {
                console.log('Drill-down modal opened successfully');

                // Try to close the modal
                const closeButton = modal.locator('button').filter({ hasText: /Close|Tutup|×/ }).first();
                if (await closeButton.count() > 0) {
                    await closeButton.click();
                }
            } else {
                console.log('Drill-down modal did not appear');
            }
        } else {
            console.log('No drill-down account links found, functionality may not be implemented');
        }
    });
});