import { chromium } from 'playwright';

async function main() {
  const authFile = '/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/playwright/.auth/user.json';
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ storageState: authFile });
  const page = await context.newPage();

  await page.goto('http://localhost:8009/admin/order-requests');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(2000);

  await page.screenshot({ path: '/tmp/or-list-full.png', fullPage: false });
  console.log('Screenshot saved');

  // Find the row with OR-20260317-2993
  const rowCount = await page.locator('tr').filter({ hasText: 'OR-20260317-2993' }).count();
  console.log('Rows with OR-20260317-2993:', rowCount);

  if (rowCount > 0) {
    const targetRow = page.locator('tr').filter({ hasText: 'OR-20260317-2993' }).first();
    
    // Collect console.log and errors
    const logs = [];
    page.on('console', msg => logs.push(`[${msg.type()}] ${msg.text()}`));
    page.on('pageerror', err => logs.push(`[error] ${err.message}`));
    
    // Click the 3-dot dropdown button
    const dropdownTrigger = targetRow.locator('.fi-dropdown-trigger').first();
    await dropdownTrigger.click();
    await page.waitForTimeout(800);
    
    // Find the Approve item
    const approveItem = page.locator('.fi-dropdown-list-item').filter({
      has: page.locator('.fi-dropdown-list-item-label', { hasText: /^\s*Approve\s*$/ }),
    }).first();
    
    const itemCount = await approveItem.count();
    console.log('Approve item count:', itemCount);
    
    if (itemCount > 0) {
      const isVisible = await approveItem.isVisible();
      const wireClick = await approveItem.getAttribute('wire:click').catch(() => null);
      const labelText = await approveItem.locator('.fi-dropdown-list-item-label').textContent().catch(() => '');
      console.log(`Approve item - visible: ${isVisible}, wire:click: ${wireClick}, label: "${labelText.trim()}"`);
      
      await approveItem.click();
      console.log('Clicked Approve item');
      await page.waitForTimeout(4000);
      
      // Find the open action modal
      const openModal = page.locator('.fi-modal-open').first();
      const insideWindow = openModal.locator('.fi-modal-window').first();
      console.log('Window visible:', await insideWindow.isVisible());
      
      // Check all inputs
      const inputs = await openModal.locator('input').all();
      console.log('Total inputs:', inputs.length);
      for (let i = 0; i < Math.min(inputs.length, 25); i++) {
        const id = await inputs[i].getAttribute('id').catch(() => '');
        const val = await inputs[i].inputValue().catch(() => 'err');
        if (id && (id.includes('original_price') || id.includes('unit_price') || id.includes('total_cost') || id.includes('quantity') || id.includes('product_name') || id.includes('subtotal'))) {
          console.log(`Input[${i}]: id="${id}", value="${val}"`);
        }
      }
      
      // Check all input IDs (full list)
      const allIds = await openModal.locator('input').evaluateAll(els => els.map(el => el.id));
      console.log('All input IDs:', allIds.filter(id => id));
      
      await page.screenshot({ path: '/tmp/or-after-approve-click.png', fullPage: false });
      console.log('Screenshot saved');
    }
    
    console.log('Console logs:', logs.slice(0, 20));
  } else {
    const allText = await page.locator('tbody tr').allInnerTexts();
    console.log('All table rows text:', JSON.stringify(allText.slice(0, 5)));
    console.log('URL:', page.url());
  }

  await browser.close();
}
main().catch(console.error);
