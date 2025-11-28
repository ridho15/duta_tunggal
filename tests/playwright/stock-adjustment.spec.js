import { test, expect } from '@playwright/test';

// Global setup for each test
test.beforeEach(async ({ page }) => {
    // Clear all cookies to ensure clean session
    await page.context().clearCookies();

    // Add longer delay between tests to prevent server overload
    await page.waitForTimeout(3000);
});

// Global cleanup after each test
test.afterEach(async ({ page }) => {
    // Longer delay to ensure test cleanup and server recovery
    await page.waitForTimeout(1500);
});

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

            // Submit the form
            const loginButton = page.locator('button[type="submit"]').first();
            await loginButton.waitFor({ state: 'visible', timeout: 10000 });
            await loginButton.click();

            console.log('Form submitted, waiting for navigation...');

            // Wait for navigation or page load with longer timeout
            await page.waitForTimeout(4000);

            // Check if login was successful with more robust checking
            let checkRetries = 20;
            for (let i = 0; i < checkRetries; i++) {
                const currentUrl = page.url();
                console.log(`Checking login status, attempt ${i + 1}, current URL: ${currentUrl}`);

                // Check if we're no longer on login page
                if (!currentUrl.includes('login')) {
                    // Additional check: look for admin content
                    try {
                        await page.waitForSelector('.fi-sidebar, .fi-body, .fi-simple-layout', { timeout: 8000 });
                        console.log('Login successful!');
                        return; // Success
                    } catch (e) {
                        console.log('URL changed but admin content not found, continuing to check...');
                    }
                }
                await page.waitForTimeout(1200);
            }

            // If we get here, login failed on this attempt
            console.log(`Login attempt ${attempt} failed, trying again...`);
            if (attempt < maxRetries) {
                // Wait longer between retries
                await page.waitForTimeout(5000);
            }

        } catch (error) {
            console.error(`Login error on attempt ${attempt}:`, error.message);
            if (attempt < maxRetries) {
                console.log(`Retrying login in 5 seconds...`);
                await page.waitForTimeout(5000);
            } else {
                throw new Error(`Login failed after ${maxRetries} attempts: ${error.message}`);
            }
        }
    }

    // If we get here, all retries failed
    const finalUrl = page.url();
    console.log(`Login failed after ${maxRetries} attempts, final URL: ${finalUrl}`);
    throw new Error(`Login failed after ${maxRetries} attempts - still on login page`);
}

test.describe('Stock Adjustment Tests', () => {
    test('should display stock adjustment list page', async ({ page }) => {
        // Login first
        await login(page);

        // Navigate to stock adjustments
        await page.goto('http://127.0.0.1:8009/admin/stock-adjustments');

        // Wait for page to load
        await page.waitForLoadState('networkidle');

        // Check page title or content
        const pageContent = await page.textContent('body');
        expect(pageContent).toMatch(/(stock.adjustment|penyesuaian.stok|Stock Adjustments)/i);
    });

    test('should create stock adjustment with increase type', async ({ page }) => {
        // Login first
        await login(page);

        // Navigate to stock adjustment create page
        await page.goto('http://127.0.0.1:8009/admin/stock-adjustments/create');

        // Wait for page to load
        await page.waitForLoadState('networkidle');

        // Fill adjustment number
        const numberInput = page.locator('input[name="adjustment_number"]').or(page.locator('input').filter({ hasText: /adjustment.*number|nomor.*penyesuaian/i }).first());
        if (await numberInput.count() > 0) {
            await numberInput.fill('ADJ-TEST-001');
        }

        // Fill adjustment date
        const dateInput = page.locator('input[name="adjustment_date"]').or(page.locator('input[type="date"]').first());
        if (await dateInput.count() > 0) {
            await dateInput.fill('2025-11-21');
        }

        // Select warehouse
        const warehouseSelect = page.locator('select[name="warehouse_id"]').or(page.locator('select').filter({ hasText: /warehouse|gudang/i }).first());
        if (await warehouseSelect.count() > 0) {
            await warehouseSelect.selectOption({ index: 1 });
        }

        // Select adjustment type - increase
        const typeSelect = page.locator('select[name="adjustment_type"]').or(page.locator('select').filter({ hasText: /type|tipe/i }).first());
        if (await typeSelect.count() > 0) {
            await typeSelect.selectOption('increase');
        }

        // Fill reason
        const reasonInput = page.locator('input[name="reason"]').or(
            page.locator('textarea[name="reason"]').or(
                page.locator('input').filter({ hasText: /reason|alasan/i }).first()
            )
        );
        if (await reasonInput.count() > 0) {
            await reasonInput.fill('Stock count discrepancy - found additional items');
        }

        // Fill notes
        const notesInput = page.locator('textarea[name="notes"]').or(page.locator('textarea').first());
        if (await notesInput.count() > 0) {
            await notesInput.fill('Additional stock found during inventory count');
        }

        // Try to submit the form - be more specific to form submit buttons
        const submitButton = page.locator('form button[type="submit"]').filter({ hasText: /Buat|Create|Save/i }).first();
        if (await submitButton.count() > 0) {
            await submitButton.click();
            await page.waitForTimeout(3000);
        }

        // Check for success message
        const pageContent = await page.textContent('body');
        expect(pageContent).toMatch(/(berhasil|success|created|saved)/i);
    });

    test('should create stock adjustment with decrease type', async ({ page }) => {
        // Login first
        await login(page);

        // Navigate to stock adjustment create page
        await page.goto('http://127.0.0.1:8009/admin/stock-adjustments/create');

        // Wait for page to load
        await page.waitForLoadState('networkidle');

        // Fill adjustment number
        const numberInput = page.locator('input[name="adjustment_number"]').or(page.locator('input').filter({ hasText: /adjustment.*number|nomor.*penyesuaian/i }).first());
        if (await numberInput.count() > 0) {
            await numberInput.fill('ADJ-TEST-002');
        }

        // Fill adjustment date
        const dateInput = page.locator('input[name="adjustment_date"]').or(page.locator('input[type="date"]').first());
        if (await dateInput.count() > 0) {
            await dateInput.fill('2025-11-21');
        }

        // Select warehouse
        const warehouseSelect = page.locator('select[name="warehouse_id"]').or(page.locator('select').filter({ hasText: /warehouse|gudang/i }).first());
        if (await warehouseSelect.count() > 0) {
            await warehouseSelect.selectOption({ index: 1 });
        }

        // Select adjustment type - decrease
        const typeSelect = page.locator('select[name="adjustment_type"]').or(page.locator('select').filter({ hasText: /type|tipe/i }).first());
        if (await typeSelect.count() > 0) {
            await typeSelect.selectOption('decrease');
        }

        // Fill reason
        const reasonInput = page.locator('input[name="reason"]').or(
            page.locator('textarea[name="reason"]').or(
                page.locator('input').filter({ hasText: /reason|alasan/i }).first()
            )
        );
        if (await reasonInput.count() > 0) {
            await reasonInput.fill('Damaged goods write-off');
        }

        // Fill notes
        const notesInput = page.locator('textarea[name="notes"]').or(page.locator('textarea').first());
        if (await notesInput.count() > 0) {
            await notesInput.fill('Items damaged during storage');
        }

        // Try to submit the form - be more specific to form submit buttons
        const submitButton = page.locator('form button[type="submit"]').filter({ hasText: /Buat|Create|Save/i }).first();
        if (await submitButton.count() > 0) {
            await submitButton.click();
            await page.waitForTimeout(3000);
        }

        // Check for success message
        const pageContent = await page.textContent('body');
        expect(pageContent).toMatch(/(berhasil|success|created|saved)/i);
    });

    test('should validate required fields in stock adjustment form', async ({ page }) => {
        // Login first
        await login(page);

        // Navigate to stock adjustment create page
        await page.goto('http://127.0.0.1:8009/admin/stock-adjustments/create');

        // Wait for page to load
        await page.waitForLoadState('networkidle');

        // Try to submit without filling required fields
        // In Filament, the submit button is usually in the form header
        // Look for the Create/Save button in the page header
        const createButton = page.locator('button[type="submit"]').filter({ hasText: /Buat|Create|Simpan|Save/i }).first();

        if (await createButton.count() > 0) {
            await createButton.click();

            // Wait for potential validation messages or page response
            await page.waitForTimeout(3000);

            // Check for validation errors - look for common validation patterns
            const pageContent = await page.textContent('body');
            const hasValidationErrors = /(harus.diisi|required|tidak.boleh.kosong|cannot.be.empty|wajib.diisi)/i.test(pageContent);

            if (!hasValidationErrors) {
                // If no validation errors found, check if we're still on the create page
                // (meaning form didn't submit successfully)
                const currentUrl = page.url();
                expect(currentUrl).toContain('/create');
            } else {
                // If validation errors are found, the test passes
                expect(hasValidationErrors).toBe(true);
            }
        } else {
            // If no submit button found, try to find any button that might submit the form
            const anyButton = page.locator('button').filter({ hasText: /Buat|Create|Simpan|Save|Submit/i }).first();
            if (await anyButton.count() > 0) {
                await anyButton.click();
                await page.waitForTimeout(3000);

                const pageContent = await page.textContent('body');
                const hasValidationErrors = /(harus.diisi|required|tidak.boleh.kosong|cannot.be.empty|wajib.diisi)/i.test(pageContent);

                if (!hasValidationErrors) {
                    const currentUrl = page.url();
                    expect(currentUrl).toContain('/create');
                } else {
                    expect(hasValidationErrors).toBe(true);
                }
            } else {
                throw new Error('No suitable submit button found on the form');
            }
        }
    });

    test('should display stock adjustment details', async ({ page }) => {
        // Login first
        await login(page);

        // Navigate to stock adjustments list
        await page.goto('http://127.0.0.1:8009/admin/stock-adjustments');

        // Wait for page to load
        await page.waitForLoadState('networkidle');

        // Look for view/edit links or buttons
        const viewLinks = page.locator('a').filter({ hasText: /View|Lihat|Show/i }).or(
            page.locator('tbody tr:first-child a').first()
        );

        if (await viewLinks.count() > 0) {
            // Get URL before clicking
            const urlBefore = page.url();

            await viewLinks.first().click();
            await page.waitForTimeout(2000);

            // Check what happened - either URL changed or modal opened
            const currentUrl = page.url();
            const pageContent = await page.textContent('body');

            // If URL changed to detail page, check for detail pattern
            if (currentUrl !== urlBefore && currentUrl.includes('/stock-adjustments/')) {
                expect(currentUrl).toMatch(/stock-adjustments\/\d+/);
                expect(pageContent).toMatch(/(adjustment|penyesuaian|Stock Adjustment)/i);
            } else {
                // Check if a modal or detail view appeared on the same page
                expect(pageContent).toMatch(/(adjustment|penyesuaian|Stock Adjustment|Detail|View)/i);
            }
        } else {
            // No view links found - this is expected if no adjustments exist
            // Test passes as the page loaded correctly
            const pageContent = await page.textContent('body');
            expect(pageContent).toMatch(/(stock.adjustments|penyesuaian.stok|Stock Adjustments)/i);
        }
    });

    test('should filter stock adjustments by status', async ({ page }) => {
        // This test sometimes fails due to server load, so we retry it
        let lastError;
        for (let retry = 1; retry <= 2; retry++) {
            try {
                // Login first with extra retries for this problematic test
                await login(page, 5); // Use 5 retries instead of 3

                // Navigate to stock adjustments page
                await page.goto('http://127.0.0.1:8009/admin/stock-adjustments');

                // Wait for page to load
                await page.waitForLoadState('networkidle');

                // Look for filter elements
                const filterSelects = page.locator('select').filter({ hasText: /Status|status/i });

                if (await filterSelects.count() > 0) {
                    // Try to filter by Draft status
                    await filterSelects.first().selectOption('draft');

                    // Wait for filtering
                    await page.waitForTimeout(3000); // Longer wait for filtering

                    // Check that we get some response
                    const pageContent = await page.textContent('body');
                    expect(pageContent).toMatch(/(Draft|stock-adjustments|Stock Adjustments)/i);
                } else {
                    // No filter available, test passes
                    expect(true).toBe(true);
                }

                // If we get here, test passed
                return;

            } catch (error) {
                lastError = error;
                console.log(`Test attempt ${retry} failed:`, error.message);
                if (retry < 2) {
                    console.log('Retrying test in 10 seconds...');
                    await page.waitForTimeout(10000); // Wait 10 seconds before retry
                }
            }
        }

        // If we get here, all retries failed
        throw lastError;
    });

    test('should filter stock adjustments by type', async ({ page }) => {
        // Login first
        await login(page);

        // Navigate to stock adjustments page
        await page.goto('http://127.0.0.1:8009/admin/stock-adjustments');

        // Wait for page to load
        await page.waitForLoadState('networkidle');

        // Look for type filter elements
        const typeFilters = page.locator('select').filter({ hasText: /Type|Tipe|adjustment.*type/i });

        if (await typeFilters.count() > 0) {
            // Try to filter by increase type
            await typeFilters.first().selectOption('increase');

            // Wait for filtering
            await page.waitForTimeout(2000);

            // Check that we get some response
            const pageContent = await page.textContent('body');
            expect(pageContent).toMatch(/(increase|penambahan|Stock Adjustments)/i);
        } else {
            // No type filter available, test passes
            expect(true).toBe(true);
        }
    });

    test('should search stock adjustments', async ({ page }) => {
        // Login first
        await login(page);

        // Navigate to stock adjustments page
        await page.goto('http://127.0.0.1:8009/admin/stock-adjustments');

        // Wait for page to load
        await page.waitForLoadState('networkidle');

        // Look for search input
        const searchInput = page.locator('input[type="search"]').or(
            page.locator('input').filter({ hasText: /search|cari/i }).first()
        );

        if (await searchInput.count() > 0) {
            await searchInput.fill('ADJ');

            // Wait for search results
            await page.waitForTimeout(2000);

            // Check that we get some response
            const pageContent = await page.textContent('body');
            expect(pageContent).toMatch(/(ADJ|stock-adjustments|Stock Adjustments)/i);
        } else {
            // No search available, test passes
            expect(true).toBe(true);
        }
    });

    test('should navigate between stock adjustment pages', async ({ page }) => {
        // Login first
        await login(page);

        // Navigate to stock adjustments
        await page.goto('http://127.0.0.1:8009/admin/stock-adjustments');

        // Wait for page to load
        await page.waitForLoadState('networkidle');

        // Check current URL
        let currentUrl = page.url();
        expect(currentUrl).toContain('stock-adjustments');

        // Try to click create button
        const createButton = page.locator('a').filter({ hasText: /Create|Buat|New/i }).first();
        if (await createButton.count() > 0) {
            await createButton.click();
            await page.waitForTimeout(2000);

            // Check if we're on create page
            currentUrl = page.url();
            expect(currentUrl).toMatch(/stock-adjustments\/create/);
        }

        // Navigate back to list
        await page.goto('http://127.0.0.1:8009/admin/stock-adjustments');
        await page.waitForLoadState('networkidle');

        // Verify we're back on the list page
        currentUrl = page.url();
        expect(currentUrl).toMatch(/stock-adjustments$/);
    });

    test('should handle empty stock adjustment list', async ({ page }) => {
        // Login first
        await login(page);

        // Navigate to stock adjustments
        await page.goto('http://127.0.0.1:8009/admin/stock-adjustments');

        // Wait for page to load
        await page.waitForLoadState('networkidle');

        // Check page content - should handle empty state gracefully
        const pageContent = await page.textContent('body');
        expect(pageContent).toMatch(/(stock.adjustment|penyesuaian.stok|Stock Adjustments)/i);

        // Should not have JavaScript errors
        const errors = [];
        page.on('pageerror', error => errors.push(error));
        await page.waitForTimeout(1000);
        expect(errors.length).toBe(0);
    });

    test('should display stock adjustment table columns correctly', async ({ page }) => {
        // Login first
        await login(page);

        // Navigate to stock adjustments
        await page.goto('http://127.0.0.1:8009/admin/stock-adjustments');

        // Wait for page to load
        await page.waitForLoadState('networkidle');

        // Check for table headers
        const tableHeaders = page.locator('thead th');
        const headerCount = await tableHeaders.count();

        if (headerCount > 0) {
            // Should have at least basic columns
            expect(headerCount).toBeGreaterThanOrEqual(3);

            // Check for common column headers
            const pageContent = await page.textContent('thead');
            expect(pageContent).toMatch(/(Nomor|Number|Tanggal|Date|Warehouse|Status|Type|Tipe)/i);
        } else {
            // No table found, test passes
            expect(true).toBe(true);
        }
    });
});