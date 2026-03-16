/**
 * Shared currency test helpers.
 *
 * The Filament `indonesianMoney()` macro attaches an Alpine.js
 * `x-mask:dynamic` directive that reformats the raw digits using
 * `Intl.NumberFormat('id-ID')` on every keystroke.
 *
 * Playwright's `fill()` replaces the whole value at once without
 * firing keyboard events, so Alpine's mask never runs.
 * We must use `pressSequentially()` (or equivalent key-by-key typing)
 * so each digit event is dispatched and the mask can intercept it.
 */

import { expect } from '@playwright/test';

/**
 * Type a plain number into a masked input and verify the formatted display.
 *
 * @param {import('@playwright/test').Page} page
 * @param {import('@playwright/test').Locator} locator  – the input element
 * @param {string} rawValue                             – digits only, e.g. "100000"
 * @param {string} expectedFormatted                    – e.g. "100.000"
 */
export async function testCurrencyInput(page, locator, rawValue, expectedFormatted) {
  // Clear current value
  await locator.click({ clickCount: 3 });
  await locator.press('Control+a');
  await locator.press('Delete');

  // Simulate real keystrokes so Alpine mask fires on every input event
  await locator.pressSequentially(rawValue, { delay: 40 });

  // Small wait for Alpine/Livewire to process
  await page.waitForTimeout(300);

  await expect(locator).toHaveValue(expectedFormatted, {
    timeout: 5_000,
    message: `After typing "${rawValue}" expected "${expectedFormatted}"`,
  });
}

/**
 * Type, clear, retype — verify mask after second entry.
 */
export async function testCurrencyClearAndRetype(page, locator, rawValue, expectedFormatted) {
  // First entry
  await locator.click({ clickCount: 3 });
  await locator.press('Control+a');
  await locator.press('Delete');
  await locator.pressSequentially(rawValue, { delay: 40 });
  await page.waitForTimeout(200);

  // Clear
  await locator.press('Control+a');
  await locator.press('Delete');
  await page.waitForTimeout(100);

  // Second entry
  await locator.pressSequentially(rawValue, { delay: 40 });
  await page.waitForTimeout(300);

  await expect(locator).toHaveValue(expectedFormatted, {
    timeout: 5_000,
    message: `After retype of "${rawValue}" expected "${expectedFormatted}"`,
  });
}

/**
 * Simulate a paste event (Ctrl+V with clipboard).
 */
export async function testCurrencyPaste(page, locator, rawValue, expectedFormatted) {
  await locator.click({ clickCount: 3 });
  await page.evaluate((v) => navigator.clipboard.writeText(v), rawValue);
  await locator.press('Control+v');
  await page.waitForTimeout(400);

  await expect(locator).toHaveValue(expectedFormatted, {
    timeout: 5_000,
    message: `After pasting "${rawValue}" expected "${expectedFormatted}"`,
  });
}

/**
 * Standard set of edge-case values and their expected formatted counterparts.
 */
export const EDGE_CASES = [
  { raw: '1000',    formatted: '1.000'       },
  { raw: '10000',   formatted: '10.000'      },
  { raw: '100000',  formatted: '100.000'     },
  { raw: '1000000', formatted: '1.000.000'   },
];
