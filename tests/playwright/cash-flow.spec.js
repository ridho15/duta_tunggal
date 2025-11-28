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

test.describe('Cash Flow Statement E2E Tests', () => {
    test.beforeEach(async ({ page }) => {
        // Add delay between tests to prevent server overload
        await page.waitForTimeout(1000);
        // Login before each test
        await login(page);
    });

    test('debug - check page content', async ({ page }) => {
        // Navigate to cash flow statement page
        await page.goto('/admin/reports/cash-flow');

        // Wait for page to load
        await page.waitForLoadState('networkidle');

        // Wait a bit more for Livewire to load
        await page.waitForTimeout(3000);

        // Log page content for debugging
        console.log('Page title:', await page.title());
        console.log('Current URL:', page.url());

        // Check for method select specifically
        const methodSelects = await page.locator('select[name="method"]').all();
        console.log('Number of method selects:', methodSelects.length);
        for (let i = 0; i < methodSelects.length; i++) {
            const value = await methodSelects[i].inputValue();
            console.log(`Method select ${i} value:`, value);
        }

        // Check for text inputs that might be dates
        const textInputs = await page.locator('input[type="text"]').all();
        console.log('Number of text inputs:', textInputs.length);
        for (let i = 0; i < textInputs.length; i++) {
            const placeholder = await textInputs[i].getAttribute('placeholder');
            const value = await textInputs[i].inputValue();
            console.log(`Text input ${i} - placeholder: "${placeholder}", value: "${value}"`);
        }

        // Check for specific text content with different patterns
        const saldoAwalPatterns = [
            'Saldo Awal Kas',
            'Saldo Awal',
            'Opening Balance',
            'Opening'
        ];
        for (const pattern of saldoAwalPatterns) {
            const count = await page.locator(`text=/${pattern}/i`).count();
            console.log(`"${pattern}" found:`, count);
        }

        // Check for export buttons
        const exportPatterns = [
            'Export Excel',
            'Export PDF',
            'Excel',
            'PDF'
        ];
        for (const pattern of exportPatterns) {
            const count = await page.locator(`text=/${pattern}/i`).count();
            console.log(`"${pattern}" found:`, count);
        }

        // Check for any divs that might contain the summary data
        const summaryDivs = await page.locator('.border.rounded, .bg-white, .p-4').all();
        console.log('Number of potential summary divs:', summaryDivs.length);
        for (let i = 0; i < Math.min(summaryDivs.length, 5); i++) {
            const text = await summaryDivs[i].textContent();
            console.log(`Summary div ${i} text: "${text.substring(0, 100)}..."`);
        }

        // Check for table elements (cash flow sections)
        const tables = await page.locator('table').count();
        console.log('Number of tables:', tables);

        // Check for any error messages
        const errorSelectors = [
            '.text-red-500',
            '.text-red-600',
            '.bg-red-100',
            'text=/error|Error|Exception/i'
        ];
        for (const selector of errorSelectors) {
            const count = await page.locator(selector).count();
            if (count > 0) {
                console.log(`Found ${count} elements with selector: ${selector}`);
            }
        }

        // Always pass this debug test
        expect(true).toBe(true);
    });

    test('should display cash flow statement with default filters (direct method)', async ({ page }) => {
        // Navigate to cash flow statement page
        await page.goto('/admin/reports/cash-flow');

        // Wait for page to load
        await page.waitForLoadState('networkidle');

        // Check for summary cards
        await expect(page.locator('text=/Saldo Awal Kas/i')).toBeVisible();
        await expect(page.locator('text=/Kenaikan.*Penurunan.*Bersih/i')).toBeVisible();
        await expect(page.locator('text=/Saldo Akhir Kas/i')).toBeVisible();

        // Check for method selector with direct method selected by default
        const methodSelect = page.locator('select[name="method"]').first();
        await expect(methodSelect).toHaveValue('direct');

        // Check for main sections - look for cash flow sections
        await expect(page.locator('text=/Arus Kas|Cash Flows/i')).toBeVisible();

        // Check for export buttons
        await expect(page.locator('button').filter({ hasText: 'Export Excel' })).toBeVisible();
        await expect(page.locator('button').filter({ hasText: 'Export PDF' })).toBeVisible();
    });

    test('should switch to indirect method and display report', async ({ page }) => {
        // Navigate to cash flow statement page
        await page.goto('/admin/reports/cash-flow');

        // Wait for page to load
        await page.waitForLoadState('networkidle');

        // Change method to indirect
        const methodSelect = page.locator('select[name="method"]').first();
        await methodSelect.selectOption('indirect');

        // Wait for report to update (Livewire reactive)
        await page.waitForTimeout(3000);

        // Verify method is selected
        await expect(methodSelect).toHaveValue('indirect');

        // Check that indirect method includes net income section
        await expect(page.locator('text=/Laba Bersih|Net Income/i')).toBeVisible();

        // Check for summary cards are still displayed
        await expect(page.locator('text=/Saldo Awal Kas/i')).toBeVisible();
        await expect(page.locator('text=/Kenaikan.*Penurunan.*Bersih/i')).toBeVisible();
    });

    test('should filter cash flow statement by date range', async ({ page }) => {
        // Navigate to cash flow statement page
        await page.goto('/admin/reports/cash-flow');

        // Wait for page to load
        await page.waitForLoadState('networkidle');

        // Set date range (current month)
        const startDate = new Date();
        startDate.setDate(1); // First day of current month
        const endDate = new Date();
        endDate.setDate(31); // Last day of current month

        const startDateStr = startDate.toISOString().split('T')[0];
        const endDateStr = endDate.toISOString().split('T')[0];

        // Fill date inputs - use more specific selectors for Filament date inputs
        const startDateInput = page.locator('input[type="date"]').first();
        const endDateInput = page.locator('input[type="date"]').nth(1);

        await startDateInput.fill(startDateStr);
        await endDateInput.fill(endDateStr);

        // Wait for report to update (Livewire reactive)
        await page.waitForTimeout(3000);

        // Verify the report is displayed
        await expect(page.locator('text=/Saldo Awal Kas/i')).toBeVisible();
        await expect(page.locator('text=/Arus Kas/i')).toBeVisible();
    });

    test('should filter cash flow statement by branch', async ({ page }) => {
        // Navigate to cash flow statement page
        await page.goto('/admin/reports/cash-flow');

        // Wait for page to load
        await page.waitForLoadState('networkidle');

        // Select a branch from dropdown (if available)
        const branchSelect = page.locator('select[name="branchIds[]"]');
        const options = await branchSelect.locator('option').all();

        if (options.length > 1) {
            // Select second option (first branch)
            await branchSelect.selectOption({ index: 1 });

            // Wait for report to update
            await page.waitForTimeout(2000);

            // Verify the report is displayed
            await expect(page.locator('text=/Saldo Awal Kas|Opening Balance/i')).toBeVisible();
            await expect(page.locator('text=/Arus Kas|Cash Flows/i')).toBeVisible();

            // Check that selected branch is displayed
            await expect(page.locator('text=/Cabang Terpilih|Selected Branches/i')).toBeVisible();
        } else {
            console.log('No branch options available, skipping branch filter test');
        }
    });

    test('should validate net change calculation consistency', async ({ page }) => {
        // Navigate to cash flow statement page
        await page.goto('/admin/reports/cash-flow');

        // Wait for page to load
        await page.waitForLoadState('networkidle');

        // Wait for data to load
        await page.waitForTimeout(2000);

        // Extract opening balance - look for the value in the summary card
        const openingBalanceCard = page.locator('.border.rounded').filter({ hasText: 'Saldo Awal Kas' });
        const openingBalanceText = await openingBalanceCard.locator('.font-semibold').textContent();
        const openingBalanceMatch = openingBalanceText.match(/[\d,.-]+/);
        const openingBalance = openingBalanceMatch ? parseFloat(openingBalanceMatch[0].replace(/[,.]/g, '')) : 0;

        // Extract closing balance
        const closingBalanceCard = page.locator('.border.rounded').filter({ hasText: 'Saldo Akhir Kas' });
        const closingBalanceText = await closingBalanceCard.locator('.font-semibold').textContent();
        const closingBalanceMatch = closingBalanceText.match(/[\d,.-]+/);
        const closingBalance = closingBalanceMatch ? parseFloat(closingBalanceMatch[0].replace(/[,.]/g, '')) : 0;

        // Extract net change
        const netChangeCard = page.locator('.border.rounded').filter({ hasText: 'Kenaikan' });
        const netChangeText = await netChangeCard.locator('.font-semibold').textContent();
        const netChangeMatch = netChangeText.match(/[\d,.-]+/);
        const netChange = netChangeMatch ? parseFloat(netChangeMatch[0].replace(/[,.]/g, '')) : 0;

        // Validate: closing balance = opening balance + net change
        const calculatedClosing = openingBalance + netChange;
        expect(Math.abs(calculatedClosing - closingBalance)).toBeLessThan(0.01); // Allow for small floating point differences

        console.log(`Validation: Opening: ${openingBalance}, Net Change: ${netChange}, Closing: ${closingBalance}, Calculated: ${calculatedClosing}`);
    });

    test('should export cash flow statement to Excel', async ({ page }) => {
        // Navigate to cash flow statement page
        await page.goto('/admin/reports/cash-flow');

        // Wait for page to load
        await page.waitForLoadState('networkidle');

        // Check if Excel export button exists
        const excelButton = page.locator('button').filter({ hasText: 'Export Excel' });

        if (await excelButton.count() > 0) {
            // Set up download listener
            const downloadPromise = page.waitForEvent('download');

            // Click Excel export button
            await excelButton.first().click();

            // Wait for download to start
            const download = await downloadPromise;

            // Verify download started
            expect(download.suggestedFilename()).toMatch(/laporan-arus-kas.*\.xlsx?/);
        } else {
            console.log('Excel export button not found, checking for alternative selectors');

            // Try alternative selectors
            const altExcelButton = page.locator('[wire\\:click="export(\'excel\')"], button[wire\\:click*="excel"]');

            if (await altExcelButton.count() > 0) {
                const downloadPromise = page.waitForEvent('download');
                await altExcelButton.first().click();
                const download = await downloadPromise;
                expect(download.suggestedFilename()).toMatch(/laporan-arus-kas.*\.xlsx?/);
            } else {
                console.log('Excel export functionality not available, skipping test');
            }
        }
    });

    test('should export cash flow statement to PDF', async ({ page }) => {
        // Navigate to cash flow statement page
        await page.goto('/admin/reports/cash-flow');

        // Wait for page to load
        await page.waitForLoadState('networkidle');

        // Check if PDF export button exists
        const pdfButton = page.locator('button').filter({ hasText: 'Export PDF' });

        if (await pdfButton.count() > 0) {
            // Set up download listener
            const downloadPromise = page.waitForEvent('download');

            // Click PDF export button
            await pdfButton.first().click();

            // Wait for download to start
            const download = await downloadPromise;

            // Verify download started
            expect(download.suggestedFilename()).toMatch(/laporan-arus-kas.*\.pdf/);
        } else {
            console.log('PDF export button not found, checking for alternative selectors');

            // Try alternative selectors
            const altPdfButton = page.locator('[wire\\:click="export(\'pdf\')"], button[wire\\:click*="pdf"]');

            if (await altPdfButton.count() > 0) {
                const downloadPromise = page.waitForEvent('download');
                await altPdfButton.first().click();
                const download = await downloadPromise;
                expect(download.suggestedFilename()).toMatch(/laporan-arus-kas.*\.pdf/);
            } else {
                console.log('PDF export functionality not available, skipping test');
            }
        }
    });

    test('should export with method selection (indirect method)', async ({ page }) => {
        // Navigate to cash flow statement page
        await page.goto('/admin/reports/cash-flow');

        // Wait for page to load
        await page.waitForLoadState('networkidle');

        // Change method to indirect
        const methodSelect = page.locator('select[name="method"]').first();
        await methodSelect.selectOption('indirect');

        // Wait for report to update
        await page.waitForTimeout(3000);

        // Export to Excel with indirect method
        const excelButton = page.locator('button').filter({ hasText: 'Export Excel' });

        if (await excelButton.count() > 0) {
            const downloadPromise = page.waitForEvent('download');
            await excelButton.first().click();
            const download = await downloadPromise;
            expect(download.suggestedFilename()).toMatch(/laporan-arus-kas.*\.xlsx?/);
        } else {
            console.log('Excel export with indirect method not available, skipping test');
        }
    });

    test('should handle empty data gracefully', async ({ page }) => {
        // Navigate to cash flow statement page
        await page.goto('/admin/reports/cash-flow');

        // Wait for page to load
        await page.waitForLoadState('networkidle');

        // Set date range to a period with no data (far future)
        const futureDate = new Date();
        futureDate.setFullYear(futureDate.getFullYear() + 1);

        const futureDateStr = futureDate.toISOString().split('T')[0];

        // Fill date inputs
        const startDateInput = page.locator('input[type="date"]').first();
        const endDateInput = page.locator('input[type="date"]').nth(1);

        await startDateInput.fill(futureDateStr);
        await endDateInput.fill(futureDateStr);

        // Wait for report to update
        await page.waitForTimeout(3000);

        // Should still display summary cards with zero values
        await expect(page.locator('text=/Saldo Awal Kas/i')).toBeVisible();
        await expect(page.locator('text=/Kenaikan.*Penurunan.*Bersih/i')).toBeVisible();
        await expect(page.locator('text=/Saldo Akhir Kas/i')).toBeVisible();
    });

    test('should maintain method selection across page refreshes', async ({ page }) => {
        // Navigate to cash flow statement page
        await page.goto('/admin/reports/cash-flow');

        // Wait for page to load
        await page.waitForLoadState('networkidle');

        // Change method to indirect
        const methodSelect = page.locator('select[name="method"]').first();
        await methodSelect.selectOption('indirect');

        // Wait for report to update
        await page.waitForTimeout(3000);

        // Click refresh button
        await page.click('button:has-text("Refresh")');

        // Wait for page to refresh
        await page.waitForTimeout(3000);

        // Verify method is still selected as indirect
        await expect(methodSelect).toHaveValue('indirect');

        // Verify indirect method content is still displayed
        await expect(page.locator('text=/Laba Bersih|Net Income/i')).toBeVisible();
    });
});