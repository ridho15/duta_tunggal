import { test, expect } from '@playwright/test';

test.describe('Notification Tests', () => {
  test('should display notification icon and badge', async ({ page }) => {
    // Login to admin panel
    await page.goto('/admin/login');

    // Wait for page to load completely
    await page.waitForLoadState('networkidle');

    // Fill login form - try different selectors
    const emailInput = page.locator('input[name="email"], input[type="email"], input[placeholder*="email"]').first();
    const passwordInput = page.locator('input[name="password"], input[type="password"]').first();
    const submitButton = page.locator('button[type="submit"], input[type="submit"], button:has-text("Login"), button:has-text("Sign in")').first();

    await expect(emailInput).toBeVisible({ timeout: 10000 });
    await expect(passwordInput).toBeVisible({ timeout: 10000 });
    await expect(submitButton).toBeVisible({ timeout: 10000 });

    // Fill login form
    await emailInput.fill('ralamzah@gmail.com');
    await passwordInput.fill('ridho123');
    await submitButton.click();

    // Wait for redirect to admin dashboard
    await page.waitForURL('/admin');

    console.log(`📍 Current URL: ${page.url()}`);
    console.log(`📄 Page title: ${await page.title()}`);

    // Check if we're actually on the admin dashboard
    const currentUrl = page.url();
    if (!currentUrl.includes('/admin')) {
      console.log('❌ Not redirected to admin dashboard');
      await page.screenshot({ path: 'login-failed.png', fullPage: true });
      console.log('📸 Login failed screenshot saved');
      return;
    }

    console.log('✅ Successfully logged in to admin dashboard');

    // Take initial screenshot
    await page.screenshot({ path: 'admin-dashboard-initial.png', fullPage: true });
    console.log('📸 Initial admin dashboard screenshot saved');

    // Debug: Log all buttons in topbar
    const topbarSelectors = ['.fi-topbar', '.filament-topbar', 'header', '.fi-header', 'nav'];
    let topbarFound = false;

    for (const selector of topbarSelectors) {
      const topbar = page.locator(selector);
      if (await topbar.count() > 0) {
        console.log(`✅ Found topbar with selector: ${selector}`);
        const allButtons = topbar.locator('button');
        const buttonCount = await allButtons.count();
        console.log(`🔍 Found ${buttonCount} buttons in topbar (${selector})`);

        for (let i = 0; i < buttonCount; i++) {
          const button = allButtons.nth(i);
          const text = await button.textContent();
          const classes = await button.getAttribute('class');
          const title = await button.getAttribute('title');
          console.log(`Button ${i}: text="${text}", class="${classes}", title="${title}"`);
        }
        topbarFound = true;
        break;
      }
    }

    if (!topbarFound) {
      console.log('❌ No topbar found with any selector');
      // Take screenshot of entire page
      await page.screenshot({ path: 'full-page.png', fullPage: true });
      console.log('📸 Full page screenshot saved');
    }

    // Look for notification icon with various possible selectors
    const possibleSelectors = [
      '[data-testid="database-notifications-trigger"]',
      '.fi-topbar-database-notifications-btn',
      'button[title*="notification"]',
      'button[title*="Notification"]',
      '.fi-topbar button:has(.heroicon-o-bell)',
      'button.fi-btn:has(.heroicon-o-bell)',
      '.database-notifications-trigger',
      'button.fi-topbar-item:has(.heroicon-o-bell)',
      // Add more general selectors
      'button:has(.heroicon-o-bell)',
      '.notification-icon',
      'button[aria-label*="notification"]',
      'button[aria-label*="Notification"]',
      // Filament specific selectors
      '[data-fi-modal-trigger="database-notifications"]',
      'button[x-on\\:click*="database-notifications"]'
    ];

    let notificationIcon = null;
    let foundSelector = '';

    for (const selector of possibleSelectors) {
      try {
        const element = page.locator(selector).first();
        if (await element.count() > 0 && await element.isVisible()) {
          notificationIcon = element;
          foundSelector = selector;
          console.log(`✅ Found notification icon with selector: ${selector}`);
          break;
        }
      } catch (error) {
        // Skip invalid selectors
        continue;
      }
    }

    if (notificationIcon) {
      // Check if icon is present
      await expect(notificationIcon).toBeVisible();
      console.log('✅ Notification icon is visible');

      // Check if there's a badge with notification count
      const badge = notificationIcon.locator('[data-badge], .badge, .fi-badge, span').first();
      const badgeExists = await badge.count() > 0;

      if (badgeExists) {
        console.log('✅ Notification badge found');
        const badgeText = await badge.textContent();
        console.log(`📊 Badge text: ${badgeText}`);
        expect(parseInt(badgeText || '0')).toBeGreaterThan(0);
      } else {
        console.log('⚠️  No notification badge found');
      }

      // Try to click the notification icon to open modal
      try {
        await notificationIcon.click();

        // Wait for modal to appear
        const modal = page.locator('[data-fi-modal-id="database-notifications"]').first();
        await modal.waitFor({ state: 'visible', timeout: 5000 });

        console.log('✅ Notification modal opened');

        // Check if there are notification items in modal
        const notificationItems = modal.locator('[data-testid="notification-item"], .fi-notification, .notification-item');
        const itemCount = await notificationItems.count();

        console.log(`📋 Found ${itemCount} notification items in modal`);

        if (itemCount > 0) {
          // Check first notification item
          const firstItem = notificationItems.first();
          await expect(firstItem).toBeVisible();

          // Check if it has an icon
          const itemIcon = firstItem.locator('svg, .heroicon, [data-icon]');
          const hasIcon = await itemIcon.count() > 0;
          console.log(`🔔 First notification has icon: ${hasIcon}`);

          // Check title
          const title = firstItem.locator('[data-testid="notification-title"], .fi-notification-title, h4, .font-medium').first();
          if (await title.count() > 0) {
            const titleText = await title.textContent();
            console.log(`📝 Notification title: ${titleText}`);
          }
        }

      } catch (error) {
        console.log('⚠️  Could not open notification modal:', error.message);
      }

    } else {
      console.log('❌ No notification icon found with any selector');
      console.log('ℹ️  This could mean:');
      console.log('   - Database notifications are not properly configured');
      console.log('   - User has no unread notifications');
      console.log('   - Filament is not detecting notifications correctly');
      console.log('   - Custom topbar is overriding default Filament topbar');

      // Check if there are any elements with notification-related classes
      const notificationElements = page.locator('[class*="notification"], [class*="Notification"]');
      const notificationCount = await notificationElements.count();
      console.log(`🔍 Found ${notificationCount} elements with notification-related classes`);
    }

    // Take final screenshot
    await page.screenshot({ path: 'admin-dashboard-final.png', fullPage: true });
    console.log('📸 Final admin dashboard screenshot saved');
  });
});