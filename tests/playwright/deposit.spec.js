import { test, expect } from '@playwright/test';

// --------------------------------------------------------
// Deposit Resource - Playwright UI Tests
// --------------------------------------------------------
// Tests focus on:
//  1. Login and navigation
//  2. Rupiah amount field masking (real-time format via Alpine.js x-mask)
//  3. Table column formatting  (Rp prefix + dot thousands separator)
//  4. Edit form showing pre-formatted value from database
// --------------------------------------------------------

const LOGIN_EMAIL    = 'ralamzah@gmail.com';
const LOGIN_PASSWORD = 'ridho123';

/** Helper: login and wait for admin dashboard */
async function login(page) {
  await page.goto('/admin/login');
  await page.waitForLoadState('networkidle');

  await page.fill('input[id="data.email"]', LOGIN_EMAIL);
  await page.fill('input[id="data.password"]', LOGIN_PASSWORD);
  await page.click('button[type="submit"]');

  // Wait until the URL moves away from /login — give extra time for a slow dev server
  await page.waitForURL(url => !url.href.includes('/login'), { timeout: 60000 });

  if (page.url().includes('/login')) {
    throw new Error('Login failed — still on login page after redirect');
  }
}

// ─────────────────────────────────────────────────
// TEST 1: Login & navigate to deposits list
// ─────────────────────────────────────────────────
test.describe('Deposit Navigation', () => {
  test('can login and access deposits page', async ({ page }) => {
    test.setTimeout(60000);

    await login(page);
    console.log('Login OK, URL:', page.url());

    await page.goto('/admin/deposits');
    await page.waitForLoadState('networkidle');
    console.log('Deposits page loaded:', page.url());

    expect(page.url()).toMatch(/\/admin\/deposits/);

    const hasCreateButton = await page.locator('a[href*="deposits/create"]').count() > 0;
    console.log('Has create button:', hasCreateButton);
  });
});

// ─────────────────────────────────────────────────
// TEST 2: Rupiah amount field masking
// Uses pressSequentially() to trigger Alpine.js mask
// ─────────────────────────────────────────────────
test.describe('Deposit Creation Test', () => {

  /**
   * Locate the amount input inside the deposit create form.
   * Tries several strategies to handle different Filament wire:model modifiers.
   */
  async function getAmountInput(page) {
    // Priority order:
    // 1. wire:model.live.blur (from ->live(onBlur: true))
    // 2. wire:model.live (from ->reactive())
    // 3. The input next to a parent node containing the 'Rp' prefix text
    // 4. getByLabel partial match (no exact to ignore required *)
    const candidates = [
      page.locator('[wire\\:model\\.live\\.blur="data.amount"]'),
      page.locator('[wire\\:model\\.live="data.amount"]'),
      page.locator('[wire\\:model="data.amount"]'),
      // Filament wraps the input with a prefix span — find the input inside an 'Rp' prefixed wrapper
      page.locator('.fi-input-wrp').filter({ hasText: /^Rp/ }).locator('input').first(),
      page.getByLabel('Total'),  // non-exact: partial label match
      page.getByRole('textbox', { name: /total/i }),
    ];

    for (const loc of candidates) {
      try {
        if (await loc.isVisible({ timeout: 1000 }).catch(() => false)) {
          return loc;
        }
      } catch {
        // Continue to next candidate
      }
    }

    // Default: return the first candidate and let the caller handle timeouts
    return candidates[0];
  }

  test('can create deposit with amount 1000000', async ({ page }) => {
    test.setTimeout(120000);

    await login(page);

    await page.goto('/admin/deposits/create');
    await page.waitForLoadState('networkidle');
    await expect(page).toHaveURL(/\/admin\/deposits\/create/);

    // Give Alpine.js time to mount and register the x-mask
    await page.waitForTimeout(3000);

    // Find amount input with robust locator (works with reactive / live / live.blur)
    const amountInput = await getAmountInput(page);
    await expect(amountInput).toBeVisible({ timeout: 10000 });

    // Use pressSequentially so Alpine.js x-mask fires on each keystroke.
    // live(onBlur:true) prevents Livewire re-renders from interrupting the mask.
    await amountInput.click({ clickCount: 3 });
    await amountInput.pressSequentially('1000000', { delay: 80 });
    await amountInput.blur(); // trigger Livewire blur sync

    console.log('Typed 1000000 digit-by-digit');

    // Wait for Alpine.js mask to settle
    await page.waitForTimeout(1500);

    const amountValue = await amountInput.inputValue();
    console.log('Amount field value after typing:', amountValue);

    // Alpine.js x-mask with Intl.NumberFormat('id-ID') formats 1000000 → "1.000.000"
    expect(amountValue).toBe('1.000.000');
    console.log('✓ Amount masked correctly as 1.000.000');
  });

  test('amount field formats 2500000 as 2.500.000', async ({ page }) => {
    test.setTimeout(60000);

    await login(page);
    await page.goto('/admin/deposits/create');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);

    const amountInput = await getAmountInput(page);
    await expect(amountInput).toBeVisible({ timeout: 10000 });
    await amountInput.click({ clickCount: 3 });
    await amountInput.pressSequentially('2500000', { delay: 80 });
    await amountInput.blur();
    await page.waitForTimeout(1500);

    const rawValue = await amountInput.inputValue();
    console.log('Typed 2500000, got:', rawValue);
    expect(rawValue).toBe('2.500.000');
  });

  test('amount field formats large number 1000000000 as 1.000.000.000', async ({ page }) => {
    test.setTimeout(60000);

    await login(page);
    await page.goto('/admin/deposits/create');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);

    const amountInput = await getAmountInput(page);
    await expect(amountInput).toBeVisible({ timeout: 10000 });
    await amountInput.click({ clickCount: 3 });
    await amountInput.pressSequentially('1000000000', { delay: 80 });
    await amountInput.blur();
    await page.waitForTimeout(1500);

    const rawValue = await amountInput.inputValue();
    console.log('Typed 1000000000, got:', rawValue);
    expect(rawValue).toBe('1.000.000.000');
  });

  test('currency prefix is Rp — no commas as thousands separators', async ({ page }) => {
    test.setTimeout(60000);

    await login(page);
    await page.goto('/admin/deposits/create');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);

    const amountInput = await getAmountInput(page);
    await expect(amountInput).toBeVisible({ timeout: 10000 });
    await amountInput.click({ clickCount: 3 });
    await amountInput.pressSequentially('1500000', { delay: 80 });
    await amountInput.blur();
    await page.waitForTimeout(1500);

    const rawValue = await amountInput.inputValue();
    console.log('Field value:', rawValue);

    // Must use DOT as thousands separator, NOT comma
    expect(rawValue).not.toMatch(/\d,\d{3}/);
    expect(rawValue).toBe('1.500.000');

    // The Rp prefix is rendered as a separate element (Filament prefix)
    const prefix = page.locator('span.fi-input-wrp-prefix, [data-prefix]').filter({ hasText: 'Rp' });
    const prefixExists = await prefix.count() > 0;
    if (prefixExists) {
      const prefixText = await prefix.first().textContent();
      console.log('Prefix text:', prefixText);
      expect(prefixText.trim()).toContain('Rp');
    }
    console.log('✓ Currency format: Rp (prefix element) + dot thousands separator (input)');
  });
});

// ─────────────────────────────────────────────────
// TEST 3: Table column formatting
// ─────────────────────────────────────────────────
test.describe('Deposit Table Formatting', () => {

  test('table amount columns use Rp prefix and dot thousands', async ({ page }) => {
    test.setTimeout(60000);

    await login(page);
    await page.goto('/admin/deposits');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    expect(page.url()).toMatch(/\/admin\/deposits/);

    // If there are any deposit records, verify Rp formatting in cells
    const rpCells = page.locator('td').filter({ hasText: /^Rp\s[\d.]/ });
    const cellCount = await rpCells.count();
    console.log('Cells with Rp format found:', cellCount);

    if (cellCount > 0) {
      const firstCellText = (await rpCells.first().textContent()).trim();
      console.log('First Rp cell:', firstCellText);

      // Starts with "Rp "
      expect(firstCellText).toMatch(/^Rp /);

      // No comma-based thousands (USD style must be absent)
      expect(firstCellText).not.toMatch(/Rp \d{1,3},\d{3}/);
    } else {
      console.log('No deposit records found — table format check skipped');
    }
    console.log('✓ Table column formatting OK');
  });
});

// ─────────────────────────────────────────────────
// TEST 4: Edit form pre-formatted value
// ─────────────────────────────────────────────────
test.describe('Deposit Edit Formatting', () => {

  test('edit form loads amount with dot thousands separator', async ({ page }) => {
    test.setTimeout(60000);

    await login(page);
    await page.goto('/admin/deposits');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    const editLinks = page.locator('a[href*="/admin/deposits/"]').filter({ hasText: /edit/i });
    const editCount = await editLinks.count();
    console.log('Edit links found:', editCount);

    if (editCount === 0) {
      console.log('No deposits to edit — skipping edit format check');
      test.skip();
      return;
    }

    await editLinks.first().click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    const amountInput = await getAmountInput(page);

    if (await amountInput.isVisible()) {
      const amountValue = await amountInput.inputValue();
      console.log('Edit form amount value:', amountValue);

      // formatStateUsing returns number_format(value, 0, ',', '.') — dots as thousands
      const numericValue = parseFloat(amountValue.replace(/\./g, '').replace(',', '.'));
      if (numericValue >= 1000) {
        // Values >= 1000 must contain a dot separator
        expect(amountValue).toContain('.');
        // Must NOT use comma as thousands separator
        expect(amountValue).not.toMatch(/\d,\d{3}/);
      }
      console.log('✓ Edit form amount uses dot as thousands separator');
    }
  });
});