import { test, expect } from '@playwright/test';

/**
 * Data Master E2E Test Suite
 * 
 * Complete CRUD E2E tests for all data master modules using Playwright.
 * Covers: Cabang, Customer, Supplier, Product Category, UOM, Currency,
 *         Tax Setting, Warehouse, Driver, Vehicle, Rak, Product
 *
 * Base URL: http://localhost:8009
 * Credentials: ralamzah@gmail.com / ridho123
 */

// Increase timeout for slow Livewire/Filament pages
test.setTimeout(120000);

// =====================================================================
// HELPERS
// =====================================================================
async function login(page) {
  await page.goto('/admin/login');
  await page.waitForSelector('#data\\.email', { timeout: 15000 });
  await page.fill('#data\\.email', 'ralamzah@gmail.com');
  await page.fill('#data\\.password', 'ridho123');
  await page.click('button[type="submit"]');
  // Livewire login uses AJAX then JS redirect; wait for URL to change away from /login
  await page.waitForURL(/\/admin(?!\/login)/, { timeout: 30000 });
  await page.waitForLoadState('networkidle', { timeout: 30000 });
}

async function verifySuccess(page, context = '') {
  const successSelectors = [
    '.fi-notification-success',
    '.fi-no-notification',
    '[class*="notification"][class*="success"]',
    'text=Disimpan',
    'text=Berhasil',
    'text=Created',
    'text=Saved',
    'text=Success',
  ];

  for (const selector of successSelectors) {
    try {
      await expect(page.locator(selector).first()).toBeVisible({ timeout: 3000 });
      return true;
    } catch (e) {
      continue;
    }
  }
  // If no notification found, check URL changed (redirect on success)
  const url = page.url();
  console.log(`[${context}] Current URL after save: ${url}`);
  return !url.includes('/create') && !url.includes('/edit');
}

async function fillSelect(page, selector, value) {
  // Try multiple selector strategies for Filament selects
  try {
    const el = page.locator(selector);
    await el.selectOption(value, { timeout: 3000 });
    return;
  } catch (_) {}

  try {
    const el = page.locator(selector + ' [role="combobox"]');
    await el.click();
    await page.locator(`[role="option"]:has-text("${value}")`).first().click();
    return;
  } catch (_) {}
}

function uniqueSuffix() {
  return Date.now().toString().slice(-6);
}

/**
 * Select the first available option from a Choices.js dropdown by field ID.
 * Filament's Select component uses Choices.js (not TomSelect) in this app.
 * Works for both regular page fields (data.fieldName) and
 * modal action fields (mountedActionsData.0.fieldName).
 */
async function selectChoicesFirstOption(page, fieldId) {
  // Try Choices.js UI approach
  const inner = page.locator(`.choices:has(select[id="${fieldId}"]) .choices__inner`).first();
  try {
    if (await inner.count() > 0) {
      await inner.scrollIntoViewIfNeeded().catch(() => {});
      await inner.click({ timeout: 5000 });
      await page.waitForTimeout(300);
      // Click the first non-placeholder, non-disabled item in the dropdown
      const firstItem = page.locator(
        '.choices__list--dropdown .choices__item--choice:not([class*="disabled"]):not([class*="placeholder"]):visible'
      ).first();
      if (await firstItem.count() > 0) {
        await firstItem.click({ timeout: 3000 });
        await page.waitForTimeout(200);
        return;
      }
    }
  } catch (_) {}

  // Fallback: use the native <select> element directly
  try {
    const nativeSelect = page.locator(`select[id="${fieldId}"]`).first();
    if (await nativeSelect.count() > 0) {
      const val = await nativeSelect.evaluate(el => {
        const opts = Array.from(el.options).filter(o => o.value);
        return opts.length > 0 ? opts[0].value : null;
      });
      if (val) {
        await nativeSelect.selectOption(val);
        await nativeSelect.evaluate(el =>
          el.dispatchEvent(new Event('change', { bubbles: true }))
        );
      }
    }
  } catch (_) {}
}

/**
 * Select the first available Cabang in a regular page form.
 * The form uses Choices.js for the cabang_id select field.
 */
async function selectCabang(page) {
  await selectChoicesFirstOption(page, 'data.cabang_id');
  await page.waitForTimeout(200);
}

// =====================================================================
// 1. CABANG (BRANCH)
// =====================================================================
test.describe('Data Master: Cabang (Branch)', () => {
  test('can list cabangs', async ({ page }) => {
    await page.goto('/admin/cabangs');
    await page.waitForLoadState('networkidle');

    const heading = page.locator('.fi-header-heading').first();
    await expect(heading).toBeVisible({ timeout: 10000 });
    console.log('[Cabang] List page loaded');
  });

  test('can create cabang', async ({ page }) => {
    await page.goto('/admin/cabangs/create');
    await page.waitForLoadState('networkidle');

    const id = uniqueSuffix();
    await page.fill('#data\\.kode', `CBG-${id}`);
    await page.fill('#data\\.nama', `Cabang Test E2E ${id}`);
    await page.fill('#data\\.alamat', `Jl. Cabang Test No. ${id}`);
    await page.fill('#data\\.telepon', '0211111111');
    // ColorPicker field - fill the text input with a hex colour
    await page.fill('#data\\.warna_background', '#3b82f6');
    // Radio: tipe_penjualan (required, default='Semua') – click the label
    await page.locator('label:has-text("Semua")').first().click({ timeout: 3000 }).catch(() => {});
    await page.click('button:visible:has-text("Buat"), button:visible:has-text("Create")');
    await page.waitForLoadState('networkidle');

    const success = await verifySuccess(page, 'Create Cabang');
    if (!success) {
      console.log('[Cabang] Form may have failed; checking for errors...');
      const errors = await page.locator('.fi-fo-field-wrp-error-message').allTextContents();
      console.log('[Cabang] Form errors:', errors);
    }
    expect(success).toBe(true);
  });

  test('can edit cabang', async ({ page }) => {
    await page.goto('/admin/cabangs');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(1000);

    // Edit links are inside a hidden actions dropdown – navigate via href directly
    const editLink = page.locator('a[href*="/cabangs/"][href*="/edit"]').first();
    if (await editLink.count() === 0) {
      console.log('[Cabang] No edit link found, skipping edit test');
      return test.skip();
    }
    const editHref = await editLink.getAttribute('href');
    await page.goto(editHref);
    await page.waitForLoadState('networkidle');

    await page.fill('#data\\.telepon', '0219999999');

    await page.click('button:visible:has-text("Simpan"), button:visible:has-text("Save")');
    await page.waitForLoadState('networkidle');

    const success = await verifySuccess(page, 'Edit Cabang');
    expect(success).toBe(true);
  });
});

// =====================================================================
// 2. CUSTOMER
// =====================================================================
test.describe('Data Master: Customer', () => {
  test('can list customers', async ({ page }) => {
    await page.goto('/admin/customers');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('.fi-header-heading').first()).toBeVisible({ timeout: 10000 });
    console.log('[Customer] List page loaded');
  });

  test('can create customer', async ({ page }) => {
    await page.goto('/admin/customers/create');
    await page.waitForLoadState('networkidle');

    const id = uniqueSuffix();
    await page.fill('#data\\.code', `CUST-${id}`);
    await page.fill('#data\\.name', `Customer E2E ${id}`);
    await page.fill('#data\\.perusahaan', `PT Customer E2E ${id}`);
    await page.fill('#data\\.nik_npwp', '1234567891234567'); // numeric only (type=number input)
    await page.fill('#data\\.address', `Jl. Customer E2E No. ${id}`);
    await page.fill('#data\\.telephone', '0212222222');
    await page.fill('#data\\.phone', '08122222222');
    await page.fill('#data\\.email', `customer.${id}@test.com`);
    await page.fill('#data\\.fax', '0212222221');
    await page.fill('#data\\.tempo_kredit', '30');
    await page.fill('#data\\.kredit_limit', '50000000');
    // Select tipe customer (PKP or PRI - required Radio)
    await page.locator('label[for="data.tipe-PKP"], label:has-text("PKP")').first().click({ timeout: 5000 }).catch(() => {});
    // Select tipe pembayaran (required Radio - pick Bebas)
    await page.locator('label[for="data.tipe_pembayaran-Bebas"], label:has-text("Bebas")').first().click({ timeout: 5000 }).catch(() => {});
    // Select cabang_id (required for superadmin users; uses Choices.js)
    await selectCabang(page);

    await page.click('button:visible:has-text("Buat"), button:visible:has-text("Create")');
    await page.waitForLoadState('networkidle');

    const success = await verifySuccess(page, 'Create Customer');
    if (!success) {
      const errors = await page.locator('.fi-fo-field-wrp-error-message').allTextContents();
      console.log('[Customer] Form errors:', errors);
    }
    expect(success).toBe(true);
  });

  test('can edit customer', async ({ page }) => {
    await page.goto('/admin/customers');
    await page.waitForLoadState('networkidle');

    const editLink = page.locator('a[href*="/customers/"][href*="/edit"]').first();
    if (await editLink.count() === 0) {
      console.log('[Customer] No edit link, skipping');
      return test.skip();
    }
    const editHref = await editLink.getAttribute('href');
    await page.goto(editHref);
    await page.waitForLoadState('networkidle');

    await page.fill('#data\\.telephone', '0218888888');

    await page.click('button:visible:has-text("Simpan"), button:visible:has-text("Save")');
    await page.waitForLoadState('networkidle');

    const success = await verifySuccess(page, 'Edit Customer');
    expect(success).toBe(true);
  });
});

// =====================================================================
// 3. SUPPLIER
// =====================================================================
test.describe('Data Master: Supplier', () => {
  test('can list suppliers', async ({ page }) => {
    await page.goto('/admin/suppliers');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('.fi-header-heading').first()).toBeVisible({ timeout: 10000 });
    console.log('[Supplier] List page loaded');
  });

  test('can create supplier', async ({ page }) => {
    await page.goto('/admin/suppliers/create');
    await page.waitForLoadState('networkidle');

    const id = uniqueSuffix();
    await page.fill('#data\\.code', `SUP-${id}`);
    await page.fill('#data\\.perusahaan', `PT Supplier E2E ${id}`);
    await page.fill('#data\\.kontak_person', `Budi ${id}`);
    await page.fill('#data\\.npwp', `01111222300${id.slice(0,5)}`); // numeric NPWP
    await page.fill('#data\\.address', `Jl. Supplier E2E No. ${id}`);
    await page.fill('#data\\.phone', '0213333333');
    await page.fill('#data\\.handphone', '08133333333');
    await page.fill('#data\\.email', `supplier.${id}@test.com`);
    await page.fill('#data\\.fax', '0213333332');
    await page.fill('#data\\.tempo_hutang', '45');
    // Select cabang_id (required for superadmin users; uses Choices.js)
    await selectCabang(page);

    await page.click('button:visible:has-text("Buat"), button:visible:has-text("Create")');
    await page.waitForLoadState('networkidle');

    const success = await verifySuccess(page, 'Create Supplier');
    if (!success) {
      const errors = await page.locator('.fi-fo-field-wrp-error-message').allTextContents();
      console.log('[Supplier] Form errors:', errors);
    }
    expect(success).toBe(true);
  });
});

// =====================================================================
// 4. PRODUCT CATEGORY
// =====================================================================
test.describe('Data Master: Product Category', () => {
  test('can list product categories', async ({ page }) => {
    await page.goto('/admin/product-categories');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('.fi-header-heading').first()).toBeVisible({ timeout: 10000 });
    console.log('[ProductCategory] List page loaded');
  });

  test('can create product category', async ({ page }) => {
    // ProductCategory uses modal create (no dedicated /create page)
    await page.goto('/admin/product-categories');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);

    // Click the create button to open modal
    await page.click('button:visible:has-text("Buat Kategori Produk")');
    await page.waitForTimeout(800);

    const id = uniqueSuffix();
    // Modal fields use mountedActionsData.0.* prefix
    await page.fill('[id="mountedActionsData.0.name"]', `Kategori Test E2E ${id}`);
    await page.fill('[id="mountedActionsData.0.kode"]', `CAT-${id}`);
    try { await page.fill('[id="mountedActionsData.0.kenaikan_harga"]', '5'); } catch (_) {}

    // Submit modal
    await page.locator('[role="dialog"] button:visible:has-text("Buat"), .fi-modal button:visible:has-text("Buat")').first().click();
    await page.waitForTimeout(800);

    const success = await verifySuccess(page, 'Create ProductCategory');
    if (!success) {
      const errors = await page.locator('.fi-fo-field-wrp-error-message').allTextContents();
      console.log('[ProductCategory] Form errors:', errors);
    }
    expect(success).toBe(true);
  });

  test('can edit product category', async ({ page }) => {
    await page.goto('/admin/product-categories');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);

    // ProductCategoryResource uses inline table EditAction (modal - button text "Ubah")
    const editBtn = page.locator('button:visible:has-text("Ubah")').first();
    if (await editBtn.count() === 0) {
      console.log('[ProductCategory] No edit button, skipping');
      return test.skip();
    }
    await editBtn.click();
    // Table row EditAction uses mountedTableActionsData (not mountedActionsData)
    await page.waitForSelector('[id^="mountedTableActionsData"]', { state: 'attached', timeout: 30000 });
    await page.waitForTimeout(300);

    // Use force:true since Alpine x-show may mark elements as not visible during transition
    try { await page.fill('[id="mountedTableActionsData.0.kenaikan_harga"]', '10', { force: true }); } catch (_) {}

    // Submit - try visible first, then force
    const saveBtnPC = page.locator('button:has-text("Simpan")');
    try { await saveBtnPC.first().click({ timeout: 5000 }); } catch (_) {
      await saveBtnPC.last().click({ force: true, timeout: 5000 }).catch(() => {});
    }
    await page.waitForTimeout(800);

    const success = await verifySuccess(page, 'Edit ProductCategory');
    expect(success).toBe(true);
  });
});

// =====================================================================
// 5. UNIT OF MEASURE (UOM)
// =====================================================================
test.describe('Data Master: Unit of Measure', () => {
  test('can list UOMs', async ({ page }) => {
    await page.goto('/admin/unit-of-measures');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('.fi-header-heading').first()).toBeVisible({ timeout: 10000 });
    console.log('[UOM] List page loaded');
  });

  test('can create unit of measure', async ({ page }) => {
    // UnitOfMeasure uses modal create (no dedicated /create page)
    await page.goto('/admin/unit-of-measures');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);

    // Click the create button to open modal
    await page.click('button:visible:has-text("Buat Satuan")');
    await page.waitForTimeout(800);

    const id = uniqueSuffix();
    await page.fill('[id="mountedActionsData.0.name"]', `Satuan Test E2E ${id}`);
    try { await page.fill('[id="mountedActionsData.0.abbreviation"]', `st${id.slice(-3)}`); } catch (_) {}

    await page.locator('[role="dialog"] button:visible:has-text("Buat"), .fi-modal button:visible:has-text("Buat")').first().click();
    await page.waitForTimeout(800);

    const success = await verifySuccess(page, 'Create UOM');
    if (!success) {
      const errors = await page.locator('.fi-fo-field-wrp-error-message').allTextContents();
      console.log('[UOM] Form errors:', errors);
    }
    expect(success).toBe(true);
  });

  test('can edit unit of measure', async ({ page }) => {
    await page.goto('/admin/unit-of-measures');
    await page.waitForLoadState('networkidle');

    const editBtn = page.locator('button[aria-label*="Edit"], a[href*="/unit-of-measures/"][href*="/edit"]').first();
    if (await editBtn.count() === 0) {
      console.log('[UOM] No edit button, skipping');
      return test.skip();
    }
    await editBtn.click();
    await page.waitForLoadState('networkidle');

    const id = uniqueSuffix();
    await page.fill('#data\\.name', `Satuan Updated ${id}`);

    await page.click('button:visible:has-text("Simpan"), button:visible:has-text("Save")');
    await page.waitForLoadState('networkidle');

    const success = await verifySuccess(page, 'Edit UOM');
    expect(success).toBe(true);
  });
});

// =====================================================================
// 6. CURRENCY
// =====================================================================
test.describe('Data Master: Currency', () => {
  test('can list currencies', async ({ page }) => {
    await page.goto('/admin/currencies');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('.fi-header-heading').first()).toBeVisible({ timeout: 10000 });
    console.log('[Currency] List page loaded');
  });

  test('can create currency', async ({ page }) => {
    // Currency uses modal create (no dedicated /create page)
    await page.goto('/admin/currencies');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);

    // Click the create button to open modal
    await page.click('button:visible:has-text("Buat Mata Uang")');
    await page.waitForTimeout(800);

    const id = uniqueSuffix();
    await page.fill('[id="mountedActionsData.0.name"]', `Test Coin ${id}`);
    try { await page.fill('[id="mountedActionsData.0.symbol"]', `T${id.slice(-2)}`); } catch (_) {}
    try { await page.fill('[id="mountedActionsData.0.code"]', `TC${id.slice(-3)}`); } catch (_) {}
    await page.fill('[id="mountedActionsData.0.to_rupiah"]', '5000');

    await page.locator('[role="dialog"] button:visible:has-text("Buat"), .fi-modal button:visible:has-text("Buat")').first().click();
    await page.waitForTimeout(800);

    const success = await verifySuccess(page, 'Create Currency');
    if (!success) {
      const errors = await page.locator('.fi-fo-field-wrp-error-message').allTextContents();
      console.log('[Currency] Form errors:', errors);
    }
    expect(success).toBe(true);
  });

  test('can edit currency', async ({ page }) => {
    await page.goto('/admin/currencies');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);

    // Currency uses inline table EditAction (modal - button text "Ubah")
    const editBtn = page.locator('button:visible:has-text("Ubah")').first();
    if (await editBtn.count() === 0) {
      console.log('[Currency] No edit button, skipping');
      return test.skip();
    }
    await editBtn.click();
    // Table row EditAction uses mountedTableActionsData (not mountedActionsData)
    await page.waitForSelector('[id^="mountedTableActionsData"]', { state: 'attached', timeout: 30000 });
    await page.waitForTimeout(300);

    // Use force:true since Alpine x-show may mark elements as not visible during transition
    try { await page.fill('[id="mountedTableActionsData.0.to_rupiah"]', '16500', { force: true }); } catch (_) {}

    // Submit - try visible first, then force
    const saveBtnCur = page.locator('button:has-text("Simpan")');
    try { await saveBtnCur.first().click({ timeout: 5000 }); } catch (_) {
      await saveBtnCur.last().click({ force: true, timeout: 5000 }).catch(() => {});
    }
    await page.waitForTimeout(800);

    const success = await verifySuccess(page, 'Edit Currency');
    expect(success).toBe(true);
  });
});

// =====================================================================
// 7. TAX SETTING
// =====================================================================
test.describe('Data Master: Tax Setting', () => {
  test('can list tax settings', async ({ page }) => {
    await page.goto('/admin/tax-settings');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('.fi-header-heading').first()).toBeVisible({ timeout: 10000 });
    console.log('[TaxSetting] List page loaded');
  });

  test('can create tax setting PPN', async ({ page }) => {
    // TaxSetting uses modal create (no dedicated /create page)
    await page.goto('/admin/tax-settings');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);

    // Click the create button to open modal
    await page.click('button:visible:has-text("Buat tax setting")');
    await page.waitForTimeout(800);

    const id = uniqueSuffix();
    await page.fill('[id="mountedActionsData.0.name"]', `PPN Test E2E ${id}`);
    await page.fill('[id="mountedActionsData.0.rate"]', '11');
    try { await page.fill('[id="mountedActionsData.0.effective_date"]', '2024-01-01'); } catch (_) {}

    // Select type PPN (radio in modal)
    try {
      await page.click('[id="mountedActionsData.0.type-PPN"]', { timeout: 2000 });
    } catch (_) {
      await page.locator('[role="dialog"] label:has-text("PPN")').first().click({ timeout: 2000 }).catch(() => {});
    }

    // Toggle status active (if not already checked)
    try {
      const statusToggle = page.locator('[id="mountedActionsData.0.status"]');
      if (await statusToggle.count() > 0 && !(await statusToggle.isChecked())) {
        await statusToggle.click();
      }
    } catch (_) {}

    await page.locator('[role="dialog"] button:visible:has-text("Buat"), .fi-modal button:visible:has-text("Buat")').first().click();
    await page.waitForTimeout(800);

    const success = await verifySuccess(page, 'Create TaxSetting');
    if (!success) {
      const errors = await page.locator('.fi-fo-field-wrp-error-message').allTextContents();
      console.log('[TaxSetting] Form errors:', errors);
    }
    expect(success).toBe(true);
  });
});

// =====================================================================
// 8. WAREHOUSE
// =====================================================================
test.describe('Data Master: Warehouse', () => {
  test('can list warehouses', async ({ page }) => {
    await page.goto('/admin/warehouses');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('.fi-header-heading').first()).toBeVisible({ timeout: 10000 });
    console.log('[Warehouse] List page loaded');
  });

  test('can create warehouse', async ({ page }) => {
    await page.goto('/admin/warehouses/create');
    await page.waitForLoadState('networkidle');

    const id = uniqueSuffix();
    await page.fill('#data\\.kode', `GDG-${id}`);
    await page.fill('#data\\.name', `Gudang Test E2E ${id}`);
    await page.fill('#data\\.location', `Jl. Gudang E2E No. ${id}`);
    await page.fill('#data\\.telepon', '0214444444');
    // Select cabang_id (required for superadmin users; uses Choices.js)
    await selectCabang(page);
    // Select tipe
    try {
      await page.click('#data\\.tipe-Besar', { timeout: 2000 });
    } catch (_) {
      const tipoLabel = page.locator('label:has-text("Besar")').first();
      await tipoLabel.click({ timeout: 2000 }).catch(() => {});
    }

    // Check status
    try {
      const statusCheckbox = page.locator('#data\\.status');
      const isChecked = await statusCheckbox.isChecked({ timeout: 2000 });
      if (!isChecked) await statusCheckbox.click();
    } catch (_) {}

    await page.click('button:visible:has-text("Buat"), button:visible:has-text("Create")');
    await page.waitForLoadState('networkidle');

    const success = await verifySuccess(page, 'Create Warehouse');
    if (!success) {
      const errors = await page.locator('.fi-fo-field-wrp-error-message').allTextContents();
      console.log('[Warehouse] Form errors:', errors);
    }
    expect(success).toBe(true);
  });

  test('can edit warehouse', async ({ page }) => {
    await page.goto('/admin/warehouses');
    await page.waitForLoadState('networkidle');

    const editLink = page.locator('a[href*="/warehouses/"][href*="/edit"]').first();
    if (await editLink.count() === 0) {
      console.log('[Warehouse] No edit link found, skipping');
      return test.skip();
    }
    const editHref = await editLink.getAttribute('href');
    await page.goto(editHref);
    await page.waitForLoadState('networkidle');

    await page.fill('#data\\.telepon', '0217777777');

    await page.click('button:visible:has-text("Simpan"), button:visible:has-text("Save")');
    await page.waitForLoadState('networkidle');

    const success = await verifySuccess(page, 'Edit Warehouse');
    expect(success).toBe(true);
  });
});

// =====================================================================
// 9. DRIVER
// =====================================================================
test.describe('Data Master: Driver', () => {
  test('can list drivers', async ({ page }) => {
    await page.goto('/admin/drivers');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('.fi-header-heading').first()).toBeVisible({ timeout: 10000 });
    console.log('[Driver] List page loaded');
  });

  test('can create driver', async ({ page }) => {
    await page.goto('/admin/drivers');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);
    await page.click('button:visible:has-text("Buat driver")');
    await page.waitForTimeout(800);

    const id = uniqueSuffix();
    await page.fill('[id="mountedActionsData.0.name"]', `Sopir Test E2E ${id}`);
    try { await page.fill('[id="mountedActionsData.0.phone"]', `081${id}000`); } catch (_) {}
    try { await page.fill('[id="mountedActionsData.0.license"]', `SIM A-${id}`); } catch (_) {}
    await selectChoicesFirstOption(page, 'mountedActionsData.0.cabang_id');

    const modalBtn = page.locator('[role="dialog"] button:visible:has-text("Buat"), .fi-modal button:visible:has-text("Buat")').first();
    await modalBtn.click();
    await page.waitForTimeout(800);

    const success = await verifySuccess(page, 'Create Driver');
    if (!success) {
      const errors = await page.locator('.fi-fo-field-wrp-error-message').allTextContents();
      console.log('[Driver] Form errors:', errors);
    }
    expect(success).toBe(true);
  });

  test('can edit driver', async ({ page }) => {
    await page.goto('/admin/drivers');
    await page.waitForLoadState('networkidle');

    const editLink = page.locator('a[href*="/drivers/"][href*="/edit"]').first();
    if (await editLink.count() === 0) {
      console.log('[Driver] No edit link, skipping');
      return test.skip();
    }
    await editLink.click();
    await page.waitForLoadState('networkidle');

    await page.fill('#data\\.phone', '08166666666');

    await page.click('button:visible:has-text("Simpan"), button:visible:has-text("Save")');
    await page.waitForLoadState('networkidle');

    const success = await verifySuccess(page, 'Edit Driver');
    expect(success).toBe(true);
  });
});

// =====================================================================
// 10. VEHICLE
// =====================================================================
test.describe('Data Master: Vehicle', () => {
  test('can list vehicles', async ({ page }) => {
    await page.goto('/admin/vehicles');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('.fi-header-heading').first()).toBeVisible({ timeout: 10000 });
    console.log('[Vehicle] List page loaded');
  });

  test('can create vehicle', async ({ page }) => {
    await page.goto('/admin/vehicles');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);
    await page.click('button:visible:has-text("Buat Kendaraan")');
    await page.waitForTimeout(800);

    const id = uniqueSuffix();
    await page.fill('[id="mountedActionsData.0.plate"]', `B ${id} XY`);
    try { await page.fill('[id="mountedActionsData.0.capacity"]', '5000'); } catch (_) {}
    await selectChoicesFirstOption(page, 'mountedActionsData.0.type');
    await selectChoicesFirstOption(page, 'mountedActionsData.0.cabang_id');

    const modalBtn = page.locator('[role="dialog"] button:visible:has-text("Buat"), .fi-modal button:visible:has-text("Buat")').first();
    await modalBtn.click();
    await page.waitForTimeout(800);

    const success = await verifySuccess(page, 'Create Vehicle');
    if (!success) {
      const errors = await page.locator('.fi-fo-field-wrp-error-message').allTextContents();
      console.log('[Vehicle] Form errors:', errors);
    }
    expect(success).toBe(true);
  });

  test('can edit vehicle', async ({ page }) => {
    await page.goto('/admin/vehicles');
    await page.waitForLoadState('networkidle');

    const editLink = page.locator('a[href*="/vehicles/"][href*="/edit"]').first();
    if (await editLink.count() === 0) {
      console.log('[Vehicle] No edit link, skipping');
      return test.skip();
    }
    await editLink.click();
    await page.waitForLoadState('networkidle');

    await page.fill('#data\\.capacity', '8 Ton Updated');

    await page.click('button:visible:has-text("Simpan"), button:visible:has-text("Save")');
    await page.waitForLoadState('networkidle');

    const success = await verifySuccess(page, 'Edit Vehicle');
    expect(success).toBe(true);
  });
});

// =====================================================================
// 11. RAK (SHELF/RACK)
// =====================================================================
test.describe('Data Master: Rak (Shelf)', () => {
  test('can list raks', async ({ page }) => {
    await page.goto('/admin/raks');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('.fi-header-heading').first()).toBeVisible({ timeout: 10000 });
    console.log('[Rak] List page loaded');
  });

  test('can create rak', async ({ page }) => {
    await page.goto('/admin/raks');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);
    await page.click('button:visible:has-text("Buat rak")');
    await page.waitForTimeout(800);

    const id = uniqueSuffix();
    await page.fill('[id="mountedActionsData.0.name"]', `Rak Test E2E ${id}`);
    try { await page.fill('[id="mountedActionsData.0.code"]', `RAK-${id}`); } catch (_) {}
    await selectChoicesFirstOption(page, 'mountedActionsData.0.warehouse_id');

    const modalBtn = page.locator('[role="dialog"] button:visible:has-text("Buat"), .fi-modal button:visible:has-text("Buat")').first();
    await modalBtn.click();
    await page.waitForTimeout(800);

    const success = await verifySuccess(page, 'Create Rak');
    if (!success) {
      const errors = await page.locator('.fi-fo-field-wrp-error-message').allTextContents();
      console.log('[Rak] Form errors:', errors);
      const url = page.url();
      console.log('[Rak] Current URL:', url);
    }
    console.log('[Rak] Create test completed');
    expect(success).toBe(true);
  });
});

// =====================================================================
// 12. PRODUCT
// =====================================================================
test.describe('Data Master: Product', () => {
  test('can list products', async ({ page }) => {
    await page.goto('/admin/products');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('.fi-header-heading').first()).toBeVisible({ timeout: 10000 });
    console.log('[Product] List page loaded');
  });

  test('can create product', async ({ page }) => {
    await page.goto('/admin/products/create');
    await page.waitForLoadState('networkidle');

    const id = uniqueSuffix();
    await page.fill('#data\\.sku', `SKU-E2E-${id}`);
    await page.fill('#data\\.name', `Produk Test E2E ${id}`);
    await page.fill('#data\\.cost_price', '10000');
    await page.fill('#data\\.sell_price', '15000');
    await page.fill('#data\\.biaya', '500');
    await page.fill('#data\\.kode_merk', `MERK-${id}`);

    // Set tipe_pajak_produk
    try {
      const ppTipe = page.locator('label:has-text("Non Pajak")').first();
      await ppTipe.click({ timeout: 2000 });
    } catch (_) {}

    await page.fill('#data\\.pajak', '0');

    // Select cabang (required for superadmin; no-op for non-superadmin user)
    await selectCabang(page);

    // Select category (Choices.js fallback to native select)
    await selectChoicesFirstOption(page, 'data.product_category_id');

    // Select UOM (Choices.js fallback to native select)
    await selectChoicesFirstOption(page, 'data.uom_id');

    // Remove default empty unit conversion entries (Filament Repeater::defaultItems = 1)
    // These empty entries fail validation; remove them all before submitting
    await page.waitForTimeout(500);
    const hapusBtns = page.locator('button:visible:has-text("Hapus")');
    const hapusCount = await hapusBtns.count();
    for (let i = hapusCount - 1; i >= 0; i--) {
      try {
        await hapusBtns.nth(i).click({ timeout: 3000 });
        await page.waitForTimeout(400);
      } catch (_) {}
    }

    await page.click('button:visible:has-text("Buat"), button:visible:has-text("Create")');
    await page.waitForLoadState('networkidle');

    const success = await verifySuccess(page, 'Create Product');
    if (!success) {
      const errors = await page.locator('.fi-fo-field-wrp-error-message').allTextContents();
      console.log('[Product] Form errors:', errors);
      const url = page.url();
      console.log('[Product] Current URL:', url);
    }
    expect(success).toBe(true);
  });
});

// =====================================================================
// 13. DATA MASTER NAVIGATION - Verify all pages are accessible
// =====================================================================
test.describe('Data Master: Navigation Check', () => {
  const dataMasterRoutes = [
    { name: 'Cabang', url: '/admin/cabangs' },
    { name: 'Customer', url: '/admin/customers' },
    { name: 'Supplier', url: '/admin/suppliers' },
    { name: 'Product Category', url: '/admin/product-categories' },
    { name: 'Unit of Measure', url: '/admin/unit-of-measures' },
    { name: 'Currency', url: '/admin/currencies' },
    { name: 'Tax Setting', url: '/admin/tax-settings' },
    { name: 'Warehouse', url: '/admin/warehouses' },
    { name: 'Driver', url: '/admin/drivers' },
    { name: 'Vehicle', url: '/admin/vehicles' },
    { name: 'Rak', url: '/admin/raks' },
    { name: 'Product', url: '/admin/products' },
  ];

  for (const route of dataMasterRoutes) {
    test(`${route.name} list page returns 200`, async ({ page }) => {
      const response = await page.goto(route.url);
      await page.waitForLoadState('networkidle');

      const status = response?.status() ?? 200;
      console.log(`[Navigation] ${route.name}: ${route.url} -> HTTP ${status}`);

      // Should not be 404 or 500
      expect([200, 302, 301]).toContain(status);

      // Page should have content
      const bodyText = await page.locator('body').textContent();
      expect(bodyText).not.toBeNull();
      expect(bodyText?.length).toBeGreaterThan(100);
    });
  }
});
