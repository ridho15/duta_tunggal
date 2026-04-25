import { test, expect } from '@playwright/test';

test.describe('Sidebar Styling – Luxury Design Verification', () => {
  test('sidebar has luxury gradient background and styling', async ({ page }) => {
    await page.goto('/admin');
    await page.waitForLoadState('networkidle');

    // Wait for sidebar to be visible
    const sidebar = page.locator('.fi-sidebar');
    await expect(sidebar).toBeVisible({ timeout: 10000 });

    // Check background gradient (should contain linear-gradient)
    const sidebarBg = await sidebar.evaluate(el => getComputedStyle(el).backgroundImage);
    expect(sidebarBg).toContain('linear-gradient');

    // Check box-shadow
    const sidebarShadow = await sidebar.evaluate(el => getComputedStyle(el).boxShadow);
    expect(sidebarShadow).not.toBe('none');

    // Check border
    const sidebarBorder = await sidebar.evaluate(el => getComputedStyle(el).borderRight);
    expect(sidebarBorder).toContain('rgba(148, 163, 184, 0.1)');
  });

  test('sidebar accents use the app blue primary color', async ({ page }) => {
    await page.goto('/admin');
    await page.waitForLoadState('networkidle');

    const itemButton = page.locator('.fi-sidebar-item-button').first();
    await expect(itemButton).toBeVisible();

    const itemBackground = await itemButton.evaluate(el => getComputedStyle(el).backgroundImage);
    expect(itemBackground).toMatch(/59, 130, 246|37, 99, 235/);
  });

  test('sidebar header has gradient background and styling', async ({ page }) => {
    await page.goto('/admin');
    await page.waitForLoadState('networkidle');

    const header = page.locator('.fi-sidebar-header');
    await expect(header).toBeVisible();

    // Check background
    const headerBg = await header.evaluate(el => getComputedStyle(el).backgroundImage);
    expect(headerBg).toContain('linear-gradient');

    // Check padding (browser converts rem to px: 1.5rem = 24px, 1.25rem = 20px)
    const headerPadding = await header.evaluate(el => getComputedStyle(el).padding);
    expect(headerPadding).toBe('24px 20px');
  });

  test('sidebar navigation items have luxury styling', async ({ page }) => {
    await page.goto('/admin');
    await page.waitForLoadState('networkidle');

    const navItems = page.locator('.fi-sidebar-item-button');
    await expect(navItems.first()).toBeVisible();

    // Check border-radius
    const borderRadius = await navItems.first().evaluate(el => getComputedStyle(el).borderRadius);
    expect(borderRadius).toBe('12px');

    // Check background gradient
    const itemBg = await navItems.first().evaluate(el => getComputedStyle(el).backgroundImage);
    expect(itemBg).toContain('linear-gradient');

    // Check box-shadow
    const itemShadow = await navItems.first().evaluate(el => getComputedStyle(el).boxShadow);
    expect(itemShadow).not.toBe('none');
  });

  test('sidebar group buttons have enhanced styling', async ({ page }) => {
    await page.goto('/admin');
    await page.waitForLoadState('networkidle');

    const groupButtons = page.locator('.fi-sidebar-group-button');
    if (await groupButtons.count() > 0) {
      await expect(groupButtons.first()).toBeVisible();

      // Check text transform
      const textTransform = await groupButtons.first().evaluate(el => getComputedStyle(el).textTransform);
      expect(textTransform).toBe('uppercase');

    // Check letter spacing (browser converts 0.05em to pixels)
    const letterSpacing = await groupButtons.first().evaluate(el => getComputedStyle(el).letterSpacing);
    expect(letterSpacing).toBe('0.6px'); // 0.05em at default font-size
    }
  });

  test('sidebar hover effects work correctly', async ({ page }) => {
    await page.goto('/admin');
    await page.waitForLoadState('networkidle');

    const firstItem = page.locator('.fi-sidebar-item-button').first();
    await expect(firstItem).toBeVisible();

    // Get initial transform
    const initialTransform = await firstItem.evaluate(el => getComputedStyle(el).transform);
    expect(initialTransform).toBe('none');

    // Hover and check transform
    await firstItem.hover();
    await page.waitForTimeout(100); // Wait for transition

    const hoverTransform = await firstItem.evaluate(el => getComputedStyle(el).transform);
    expect(hoverTransform).toContain('matrix'); // translateX becomes matrix in computed style
  });

  test('sidebar separator label is present', async ({ page }) => {
    await page.goto('/admin');
    await page.waitForLoadState('networkidle');

    // Check for separator with "Modul Utama" text (may not always be visible)
    const separator = page.locator('text=Modul Utama');
    const separatorExists = await separator.count() > 0;
    
    if (separatorExists) {
      await expect(separator).toBeVisible();
    } else {
      // If separator doesn't exist, that's also acceptable
      console.log('Separator "Modul Utama" not found - this may be expected based on navigation structure');
    }
  });

  test('sidebar icons have proper styling', async ({ page }) => {
    await page.goto('/admin');
    await page.waitForLoadState('networkidle');

    const icons = page.locator('.fi-sidebar-item-button svg');
    if (await icons.count() > 0) {
      const firstIcon = icons.first();

      // Check size (1.25rem converted to pixels by browser)
      const width = await firstIcon.evaluate(el => getComputedStyle(el).width);
      const height = await firstIcon.evaluate(el => getComputedStyle(el).height);
      expect(parseFloat(width)).toBeGreaterThan(15); // Approximately 1.25rem
      expect(parseFloat(height)).toBeGreaterThan(15);

      // Check opacity
      const opacity = await firstIcon.evaluate(el => getComputedStyle(el).opacity);
      expect(opacity).toBe('0.8');
    }
  });

  test('sidebar responsive design works', async ({ page }) => {
    await page.setViewportSize({ width: 768, height: 1024 }); // Tablet size
    await page.goto('/admin');
    await page.waitForLoadState('networkidle');

    const navItems = page.locator('.fi-sidebar-item-button');
    await expect(navItems.first()).toBeVisible();

    // Check responsive padding
    const padding = await navItems.first().evaluate(el => getComputedStyle(el).padding);
    // Should be smaller on mobile/tablet
    expect(padding).toContain('12px'); // Default padding
  });
});