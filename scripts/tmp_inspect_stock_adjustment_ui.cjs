const { execSync } = require('node:child_process');
const { chromium } = require('playwright');

function queryAdjustmentStatus(number) {
  return execSync(`php artisan tinker --execute=\"echo DB::table('stock_adjustments')->where('adjustment_number', '${number}')->value('status');\"`, {
    encoding: 'utf8',
  }).trim();
}

(async () => {
  const browser = await chromium.launch({ headless: true })
  const context = await browser.newContext({ storageState: 'playwright/.auth/user.json' })
  const page = await context.newPage()

  page.on('console', (message) => {
    console.log('BROWSER_CONSOLE=' + message.type() + ':' + message.text())
  })

  page.on('pageerror', (error) => {
    console.log('PAGE_ERROR=' + error.message)
  })

  page.on('requestfailed', (request) => {
    console.log('REQUEST_FAILED=' + request.method() + ' ' + request.url() + ' ' + request.failure()?.errorText)
  })

  page.on('response', async (response) => {
    if (response.url().includes('/livewire/update')) {
      console.log('LIVEWIRE_RESPONSE=' + response.status())
    }
  })

  await page.goto('http://localhost:8009/admin/stock-adjustments')
  await page.waitForLoadState('networkidle')

  console.log('URL=' + page.url())
  console.log('BODY_START=' + JSON.stringify(((await page.locator('body').textContent()) || '').replace(/\s+/g, ' ').trim().slice(0, 500)))

  const row = page.locator('tr', { hasText: 'ADJ-PW-APPROVE-001' }).first()

  console.log('ROW=' + JSON.stringify(((await row.textContent()) || '').replace(/\s+/g, ' ').trim()))

  await row.locator('button').first().click()
  await page.waitForTimeout(300)

  console.log(
    'BUTTONS_OPEN=' + JSON.stringify(
      await page.getByRole('button').evaluateAll((nodes) =>
        nodes
          .map((node) => ({
            text: (node.innerText || '').trim(),
            visible: !!(node.offsetWidth || node.offsetHeight || node.getClientRects().length),
          }))
          .filter((item) => item.visible),
      ),
    ),
  )

  await page.getByRole('button', { name: 'Approve' }).click()
  await page.waitForTimeout(500)

  console.log(
    'DIALOGS=' + JSON.stringify(
      await page.locator('[role="dialog"]').evaluateAll((nodes) =>
        nodes.map((node) => (node.innerText || '').replace(/\s+/g, ' ').trim()),
      ),
    ),
  )

  console.log(
    'BUTTONS_AFTER=' + JSON.stringify(
      await page.getByRole('button').evaluateAll((nodes) =>
        nodes
          .map((node) => ({
            text: (node.innerText || '').trim(),
            visible: !!(node.offsetWidth || node.offsetHeight || node.getClientRects().length),
          }))
          .filter((item) => item.visible),
      ),
    ),
  )

  await page.getByRole('button', { name: 'Konfirmasi' }).click()
  await page.waitForTimeout(2500)

  console.log('STATUS_AFTER=' + queryAdjustmentStatus('ADJ-PW-APPROVE-001'))
  console.log('BODY_AFTER=' + JSON.stringify(((await page.locator('body').textContent()) || '').replace(/\s+/g, ' ').trim()))

  await browser.close()
})().catch((error) => {
  console.error(error)
  process.exit(1)
})