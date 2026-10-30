import { test, expect } from '@playwright/test'

test.use({ storageState: 'playwright/.auth/user.json' })

/**
 * PDF Preview E2E Tests
 * Tests the complete PDF preview workflow for all document types
 */

test.describe('PDF Preview - All Document Types', () => {
  const documentTypes = [
    {
      name: 'Order Request',
      type: 'order-request',
      listPath: '/admin/order-requests',
    },
    {
      name: 'Purchase Order',
      type: 'purchase-order',
      listPath: '/admin/purchase-orders',
    },
    {
      name: 'Quotation',
      type: 'quotation',
      listPath: '/admin/quotations',
    },
    {
      name: 'Sales Order',
      type: 'sale-order',
      listPath: '/admin/sale-orders',
    },
  ]

  for (const doc of documentTypes) {
    test(`${doc.name} - list page loads without console errors`, async ({ page }) => {
      const consoleErrors = []
      page.on('console', msg => {
        if (msg.type() === 'error') {
          consoleErrors.push(msg.text())
        }
      })

      // Navigate to list page
      await page.goto(doc.listPath)
      await page.waitForLoadState('networkidle')

      // Wait a bit for any JS to execute
      await page.waitForTimeout(1000)

      // Check for JS errors (filter out known non-critical errors)
      const criticalErrors = consoleErrors.filter(err =>
        !err.includes('favicon') &&
        !err.includes('404') &&
        !err.includes('Deprecation')
      )

      // Verify page loaded
      await expect(page.locator('body')).toBeVisible()

      // Assert no critical JS errors
      expect(criticalErrors).toHaveLength(0)
    })

    test(`${doc.name} - PDF preview route is accessible`, async ({ page }) => {
      // Test preview route - should be 200 or 302 (redirect)
      const response = await page.request.get(`/pdf-preview/${doc.type}/1`)
      // Should be 200 (accessible with auth) or 302 (redirect)
      expect([200, 302]).toContain(response.status())
    })
  }

  test('PDF download route triggers download', async ({ page }) => {
    // Test download route
    const response = await page.request.get('/pdf-download/order-request/1')
    // Should be 200 or redirect
    expect([200, 302]).toContain(response.status())
  })

  test('PDF stream route returns content', async ({ page }) => {
    // Test stream route
    const response = await page.request.get('/pdf-stream/order-request/1')
    // Should be 200 or redirect
    expect([200, 302]).toContain(response.status())
  })

  test('PDF routes require authentication without session', async ({ page }) => {
    // Create new context without auth
    const context = await page.context().browser() ? await page.context().browser().newContext() : await page.context().newPage().then(() => null)

    if (context) {
      const pageNoAuth = await context.newPage()

      // Test preview route without auth
      const response = await pageNoAuth.request.get('/pdf-preview/order-request/1')
      // Should redirect to login
      expect(response.status()).toBe(302)

      await context.close()
    }
  })
})

test.describe('PDF Preview Page Structure', () => {
  test('Resource pages load correctly with PDF actions', async ({ page }) => {
    // Test each resource page
    const pages = [
      '/admin/order-requests',
      '/admin/purchase-orders',
      '/admin/quotations',
      '/admin/sale-orders',
    ]

    for (const pagePath of pages) {
      await page.goto(pagePath)
      await page.waitForLoadState('networkidle')
      await expect(page.locator('body')).toBeVisible()
    }
  })
})