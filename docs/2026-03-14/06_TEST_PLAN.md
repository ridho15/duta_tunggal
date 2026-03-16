# DUTA TUNGGAL ERP — Test Plan Document
**Tanggal:** 14 Maret 2026  
**Versi Dokumen:** 1.0  

---

## 1. GAMBARAN TEST STRATEGY

### 1.1 Piramida Testing

```
           /\
          /  \
         / E2E\        (Playwright / Dusk)
        /──────\       < 5% : Critical flows only
       /        \
      / Integration\   (Feature Tests)
     /──────────────\  ~60% : Module-level flows
    /                \
   /   Unit Tests     \ ~35% : Services, Helpers, Observers
  /────────────────────\
```

### 1.2 Status Test Suite Saat Ini

| Kategori | Jumlah File | Jumlah Test Cases | Coverage |
|----------|-------------|-------------------|----------|
| Feature Tests | ~143 files | ~1,800 cases | ~65% |
| Unit Tests | ~22 files | ~400 cases | ~70% |
| Browser (Dusk) | ~8 files | ~50 cases | ~10% |
| Playwright | 4 spec files | ~30 cases | ~5% |
| **Total** | **~177 files** | **~2,544 cases** | **~65%** |

### 1.3 Tools & Framework

| Tool | Versi | Penggunaan |
|------|-------|-----------|
| Pest PHP | ^3.8 | Unit + Feature testing |
| PHPUnit | (via Pest) | Test runner |
| Laravel Dusk | ^8.3 | Browser testing (Chrome) |
| Playwright | (JS) | E2E browser testing |
| SQLite | (in-memory) | Test database |
| Mockery | ^1.6 | Mocking |

---

## 2. KONFIGURASI TEST ENVIRONMENT

### 2.1 phpunit.xml

```xml
<phpunit bootstrap="vendor/autoload.php" colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
        <env name="CACHE_DRIVER" value="array"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        <env name="SESSION_DRIVER" value="array"/>
        <env name="MAIL_MAILER" value="array"/>
    </php>
</phpunit>
```

### 2.2 Menjalankan Tests

```bash
# Semua tests
php artisan test

# Hanya Feature tests
php artisan test --testsuite=Feature

# Hanya Unit tests
php artisan test --testsuite=Unit

# Filter by test name
php artisan test --filter=CustomerReturn

# Specific file
php artisan test tests/Feature/CustomerReturnFeatureTest.php

# Dengan coverage
php artisan test --coverage --min=70

# Parallel (lebih cepat)
php artisan test --parallel
```

---

## 3. UNIT TEST PLAN

### 3.1 Service Classes (Prioritas Tinggi)

#### 3.1.1 TaxService

**File:** `tests/Unit/TaxServiceTest.php`  
**Status:** ✅ Ada

**Test Cases yang HARUS Ada:**

```
✅ TC-TAX-001: PPN Exclusive — 11% dari Rp 1.000.000 = Rp 110.000
✅ TC-TAX-002: PPN Inclusive — gross Rp 1.110.000, DPP = Rp 1.000.000, PPN = Rp 110.000
✅ TC-TAX-003: PPN rate 0% menghasilkan PPN = 0  [Playwright E2E — PASSED 2026-03-14]
✅ TC-TAX-004: Nilai desimal — rounding ke 0 desimal (rupiah)  [Playwright E2E — PASSED 2026-03-14]
✅ TC-TAX-005: Large amounts — tidak ada floating point error pada nilai > Rp 1 miliar  [Playwright E2E — PASSED 2026-03-14]
✅ TC-TAX-006: PPN Excluded dengan diskon — DPP = (price * qty) - diskon  [Playwright E2E — PASSED 2026-03-14]
```

---

#### 3.1.2 BalanceSheetService

**File:** `tests/Unit/BalanceSheetServiceTest.php`  
**Status:** ✅ Ada

**Test Cases yang HARUS Ada:**

```
✅ TC-BS-001: Asset = Liabilities + Equity (balanced)
✅ TC-BS-002: Opening balance diperhitungkan dengan benar  [Playwright E2E — PASSED 2026-03-14]
✅ TC-BS-003: Jurnal dari multiple periods di-aggregate dengan benar  [Playwright E2E — PASSED 2026-03-14]
✅ TC-BS-004: Contra Account (Akumulasi Penyusutan) mengurangi Asset  [Playwright E2E — PASSED 2026-03-14]
✅ TC-BS-005: Balance Sheet untuk cabang tertentu (CabangScope)  [Playwright E2E — PASSED 2026-03-14]
✅ TC-BS-006: Balance Sheet kosong (tidak ada jurnal) — semua nilai 0  [Playwright E2E — PASSED 2026-03-14]
```

---

#### 3.1.3 InvoiceService

**File:** `tests/Unit/InvoiceServiceTest.php` (belum ada — BUAT BARU)  

**Test Cases yang HARUS Ada:**

```
✅ TC-INV-001: Generate nomor invoice format yang benar (INV-YYYYMM-XXXX) [Pest Feature — PASSED 2026-03-14]
✅ TC-INV-002: Generate nomor dengan PPN prefix berbeda dari non-PPN [Pest Feature — PASSED 2026-03-14]
✅ TC-INV-003: Auto-create AR saat invoice dibuat [Pest Feature — PASSED 2026-03-14]
✅ TC-INV-004: AR.remaining = total invoice saat pertama dibuat [Pest Feature — PASSED 2026-03-14]
✅ TC-INV-005: other_fee JSON array di-sum dengan benar ke total [Pest Feature — PASSED 2026-03-14]
✅ TC-INV-006: PPN calculation di invoice: dpp + ppn = total [Pest Feature — PASSED 2026-03-14]
✅ TC-INV-007: Invoice status berubah ke 'paid' saat AR.remaining = 0 [Pest Feature — PASSED 2026-03-14]
```

---

#### 3.1.4 StockReservationService

**File:** `tests/Unit/StockReservationServiceTest.php` (belum ada — BUAT BARU)

**Test Cases yang HARUS Ada:**

```
✅ TC-SR-001: Reserve stok menambah qty_reserved dan mengurangi qty_available [Pest Feature — PASSED 2026-03-14]
✅ TC-SR-002: Release reservation mengurangi qty_reserved dan menambah qty_available [Pest Feature — PASSED 2026-03-14]
✅ TC-SR-003: Over-reservation throw InsufficientStockException [Pest Feature — PASSED 2026-03-14]
✅ TC-SR-004: Reserve concurrent tidak menghasilkan negatif (dengan locking) [Pest Feature — PASSED 2026-03-14]
✅ TC-SR-005: Reservation di-release saat SO di-cancel [Pest Feature — PASSED 2026-03-14]
✅ TC-SR-006: Reservation di-consume (convert ke actual movement) saat DO approved [Pest Feature — PASSED 2026-03-14]
```

---

#### 3.1.5 CashBankService

**File:** `tests/Unit/CashBankServiceTest.php`  
**Status:** ✅ Ada

**Test Cases Tambahan:**

```
⬜ TC-CB-001: Transfer antar rekening (debit source, credit destination)
⬜ TC-CB-002: Voucher request integration — VR approved → cash transaction dibuat
⬜ TC-CB-003: Balance check sebelum pengeluaran
```

---

#### 3.1.6 CustomerReturnService (BARU)

**File:** `tests/Unit/CustomerReturnServiceTest.php` (belum ada — BUAT BARU)

**Test Cases yang HARUS Ada:**

```
⬜ TC-CR-001: Restore stock menghasilkan StockMovement dengan type 'return'
⬜ TC-CR-002: Restore partial — hanya accepted items yang masuk stok
⬜ TC-CR-003: Journal entry dibuat saat return completed
⬜ TC-CR-004: Invoice terkait di-update (partial refund)
⬜ TC-CR-005: AR terkait di-update setelah return
```

---

### 3.2 Observer Classes

#### 3.2.1 StockMovementObserver

**File:** `tests/Unit/Observers/StockMovementObserverTest.php` (belum ada — BUAT BARU)

```
⬜ TC-SMO-001: StockMovement IN → InventoryStock.qty_available naik
⬜ TC-SMO-002: StockMovement OUT → InventoryStock.qty_available turun
⬜ TC-SMO-003: StockMovement di warehouse yang tidak ada → create InventoryStock baru
⬜ TC-SMO-004: Stok tidak bisa negatif (throw exception atau prevent)
```

---

#### 3.2.2 InvoiceObserver

**File:** `tests/Unit/Observers/InvoiceObserverPostSalesTest.php`  
**Status:** ✅ Ada

```
⬜ TC-IO-001: Sales invoice → AR dibuat dengan jumlah yang benar
⬜ TC-IO-002: Purchase invoice → AP dibuat dengan jumlah yang benar
⬜ TC-IO-003: Invoice diedit → AR/AP di-update
```

---

### 3.3 Helper & Utility Classes

#### 3.3.1 MoneyHelper / formatAmount

**File:** `tests/Unit/RupiahFormatterTest.php`  
**Status:** ✅ Ada

```
✅ TC-MH-001: 1000000 → "1.000.000"
✅ TC-MH-002: 1500.50 → "1.500,50"
⬜ TC-MH-003: 0 → "0"
⬜ TC-MH-004: Negatif → "-1.000.000"
⬜ TC-MH-005: formatCurrency → "Rp 1.000.000"
```

---

### 3.4 Calculation Methods

#### 3.4.1 OrderRequest Total Calculation

```
⬜ TC-ORC-001: Unit price * quantity = subtotal
⬜ TC-ORC-002: PPN Exclusive: subtotal + (subtotal * ppn%) = total
⬜ TC-ORC-003: PPN Inclusive: total tetap, DPP = total / 1.11
⬜ TC-ORC-004: Override price (unit_price ≠ original_price) digunakan dengan benar
```

---

## 4. FEATURE TEST PLAN (INTEGRATION)

### 4.1 Siklus Penjualan Lengkap

#### 4.1.1 Alur: Quotation → Invoice → Customer Receipt

**File:** `tests/Feature/CompleteSalesFlowTest.php`  
**Status:** ✅ Ada

**Test Cases Tambahan yang Diperlukan:**

```
✅ TC-SALE-001: Quotation dibuat → approve → convert to SO
✅ TC-SALE-002: SO approved → DO dibuat otomatis
⬜ TC-SALE-003: SO Ambil Sendiri → DO tetap dibuat
⬜ TC-SALE-004: DO sent → SJ dibuat → mark as sent → Invoice
⬜ TC-SALE-005: Invoice dibuat → AR dibuat dengan amount yang benar
⬜ TC-SALE-006: Customer Receipt → AR.paid naik, AR.remaining turun
⬜ TC-SALE-007: Pelunasan penuh → AR.status = 'Lunas', Invoice.status = 'paid'
⬜ TC-SALE-008: Pelunasan partial → AR.status = 'Belum Lunas'

# PPN Tests
⬜ TC-SALE-009: Invoice dengan PPN 11% — tax amount = DPP * 11%
⬜ TC-SALE-010: Invoice non-PPN — tax = 0
⬜ TC-SALE-011: SO dengan mix PPN Inclusive / Exclusive items
```

---

#### 4.1.2 Alur: Customer Return

**File:** `tests/Feature/CustomerReturnFeatureTest.php`  
**Status:** ✅ Ada (perlu diperluas)

**Test Cases yang HARUS Ada:**

```
✅ TC-RET-001: Buat Customer Return dari invoice
✅ TC-RET-002: Status flow: pending → received → qc_inspection → approved → completed
⬜ TC-RET-003: Stock TIDAK dikembalikan selama QC inspection
⬜ TC-RET-004: Stock DIKEMBALIKAN saat status = completed
⬜ TC-RET-005: Partial return — hanya quantity accepted yang masuk stok
⬜ TC-RET-006: Return rejected — stok tidak bertambah
⬜ TC-RET-007: Journal entry dibuat saat return completed (debit: Retur Penjualan, kredit: Piutang)
⬜ TC-RET-008: AR/invoice di-update setelah return
⬜ TC-RET-009: Return number format: CR-{YEAR}-XXXX
⬜ TC-RET-010: Tidak bisa return lebih dari qty invoice
```

---

#### 4.1.3 Credit Limit Validation

**File:** `tests/Feature/SalesWorkflowAuditTest.php` (extend existing)

```
⬜ TC-CREDIT-001: SO dalam batas kredit → bisa di-approve
⬜ TC-CREDIT-002: SO melebihi kredit limit → warning/block  
⬜ TC-CREDIT-003: Customer dengan kredit_limit = 0 (unlimited) → selalu bisa approve
⬜ TC-CREDIT-004: Customer isSpecial → bypass credit limit
```

---

### 4.2 Siklus Pengadaan Lengkap

#### 4.2.1 Alur: Order Request → PO → GRN → QC → Invoice → Vendor Payment

**File:** `tests/Feature/CompleteProcurementFlowTest.php`  
**Status:** ✅ Ada

**Test Cases Tambahan:**

```
⬜ TC-PROC-001: Order Request dengan multi-supplier → generate multiple PO
⬜ TC-PROC-002: PO dibuat dalam status 'draft' (bukan auto-approve)
⬜ TC-PROC-003: approve_po action → PO status = 'approved'
⬜ TC-PROC-004: Order Request item fulfilled_quantity update saat PO dibuat
⬜ TC-PROC-005: GRN tanpa PO (langsung receipt)
⬜ TC-PROC-006: GRN dengan currency berbeda (USD, EUR) → konversi ke IDR
⬜ TC-PROC-007: QC passed → stok naik di warehouse yang dipilih
⬜ TC-PROC-008: QC failed → Purchase Return otomatis dibuat
⬜ TC-PROC-009: Partial QC (sebagian pass, sebagian fail)
⬜ TC-PROC-010: PO dengan flag is_asset → Asset dibuat

# Payment Tests
⬜ TC-PROC-011: Payment Request dibuat → pending_approval → approved → Vendor Payment
⬜ TC-PROC-012: Vendor Payment melunasi AP yang benar
⬜ TC-PROC-013: AP.status = 'Lunas' saat fully paid
⬜ TC-PROC-014: Import payment — PPh22 dan bea masuk di-journal
```

---

#### 4.2.2 Alur: Purchase Return

**File:** `tests/Feature/PurchaseReturnFeatureTest.php`  
**Status:** ✅ Ada

```
✅ TC-PR-001: QC reject → Purchase Return dibuat otomatis
⬜ TC-PR-002: Return number format: RN-YYYYMMDD-XXXX
⬜ TC-PR-003: Journal untuk purchase return (debit hutang, kredit persediaan)
⬜ TC-PR-004: AP affected after purchase return
```

---

### 4.3 Siklus Manufaktur

**File:** `tests/Feature/ManufacturingFlowTest.php`  
**Status:** ✅ Ada

```
✅ TC-MFG-001: BOM → Production Plan → MO
⬜ TC-MFG-002: Material Issue approved → WIP journal entry
⬜ TC-MFG-003: Material Issue completed → bahan baku keluar dari stok
⬜ TC-MFG-004: Production finished → barang jadi masuk stok
⬜ TC-MFG-005: Production QC passed → finished goods journal
⬜ TC-MFG-006: BOM cost calculation — labor + material + overhead = total
⬜ TC-MFG-007: MO dari SO yang di-confirm — production plan source_type = 'sale_order'
```

---

### 4.4 Manajemen Inventori

#### 4.4.1 Stock Transfer

**File:** `tests/Feature/StockTransferTest.php`  
**Status:** ✅ Ada

```
✅ TC-ST-001: Transfer dari warehouse A ke B
⬜ TC-ST-002: Stock di warehouse A turun, warehouse B naik
⬜ TC-ST-003: Journal entry untuk transfer (antar warehouse expenses/assets)
⬜ TC-ST-004: Transfer dengan rak (rack-level tracking)
⬜ TC-ST-005: Transfer tidak bisa dilakukan jika stok source tidak cukup
```

---

#### 4.4.2 Stock Opname

**File:** `tests/Feature/Filament/StockOpnameResourceTest.php`  
**Status:** ✅ Ada

```
✅ TC-SO-001: Create stock opname
✅ TC-SO-002: Items dengan system_qty dan actual_qty
⬜ TC-SO-003: Approve opname → StockMovement dibuat untuk selisih positif (actual > system)
⬜ TC-SO-004: Approve opname → StockMovement dibuat untuk selisih negatif (actual < system)
⬜ TC-SO-005: Approve opname → Journal entry untuk koreksi stok
⬜ TC-SO-006: InventoryStock.qty_available update setelah opname approved
```

---

### 4.5 Akuntansi & Keuangan

#### 4.5.1 Journal Entry Validation

**File:** `tests/Feature/JournalEntryTest.php`  
**Status:** ✅ Ada

```
✅ TC-JE-001: Debit = Credit (balanced entry)
⬜ TC-JE-002: Unbalanced entry → rejection atau exception
⬜ TC-JE-003: Journal dari Sales Invoice — correct COA mapping
⬜ TC-JE-004: Journal dari Vendor Payment — correct COA mapping
⬜ TC-JE-005: Journal Reversal — is_reversal = true, balances = inverse
⬜ TC-JE-006: Polymorphic source_type/source_id linked correctly
```

---

#### 4.5.2 Bank Reconciliation

**File:** `tests/Feature/AutoBankReconciliationTest.php`  
**Status:** ✅ Ada

```
✅ TC-RECON-001: Bank reconciliation basic
⬜ TC-RECON-002: Difference = statement_balance - book_balance
⬜ TC-RECON-003: Items matched → difference = 0 pada completed recon
```

---

#### 4.5.3 Deposit Management

**File:** `tests/Feature/DepositFeatureTest.php`  
**Status:** ✅ Ada

```
✅ TC-DEP-001: Customer deposit dibuat
✅ TC-DEP-002: Deposit digunakan untuk pelunasan
⬜ TC-DEP-003: Deposit.used_amount naik saat digunakan
⬜ TC-DEP-004: Deposit.remaining_amount = amount - used_amount
⬜ TC-DEP-005: Deposit.status = 'closed' saat remaining = 0
⬜ TC-DEP-006: Journal entry untuk deposit creation (debit kas, kredit deposit)
⬜ TC-DEP-007: Supplier deposit (bukan hanya customer)
```

---

#### 4.5.4 Voucher Request

**File:** `tests/Feature/VoucherRequestFeatureTest.php`  
**Status:** ✅ Ada

```
✅ TC-VR-001: Create voucher request
⬜ TC-VR-002: Approval flow: draft → pending_approval → approved
⬜ TC-VR-003: Setelah approved → CashBankTransaction dibuat
⬜ TC-VR-004: CashBankTransaction linked ke voucher_request_id
⬜ TC-VR-005: Reject → tidak ada cash transaction
```

---

### 4.6 Aset

**File:** `tests/Feature/AssetPurchaseWorkflowTest.php`  
**Status:** ✅ Ada

```
✅ TC-ASSET-001: Asset creation dari PO (is_asset = true)
✅ TC-ASSET-002: Depresiasi dihitung saat asset dibuat
⬜ TC-ASSET-003: Monthly depreciation amount = purchase_cost / (12 * useful_life)
⬜ TC-ASSET-004: AssetDepreciation records dibuat selama masa manfaat
⬜ TC-ASSET-005: Disposal — book_value = 0 setelah disposal
⬜ TC-ASSET-006: Disposal gain/loss = proceeds - book_value
⬜ TC-ASSET-007: Transfer aset antar cabang — asset.cabang_id terupdate
⬜ TC-ASSET-008: Journal entry untuk setiap depreciation period
```

---

### 4.7 RBAC & Multi-Cabang

**File:** `tests/Feature/PermissionRoleBranchTaxAuditTest.php`  
**Status:** ✅ Ada

```
✅ TC-RBAC-001: Superadmin bisa akses semua resource
✅ TC-RBAC-002: User cabang A tidak bisa lihat data cabang B (CabangScope)
⬜ TC-RBAC-003: Role 'Sales' tidak bisa edit PPN di SO
⬜ TC-RBAC-004: Role 'gudang' tidak bisa approve financial documents
⬜ TC-RBAC-005: Permission 'approve delivery order' diperlukan untuk DO approval
⬜ TC-RBAC-006: User bisa punya multiple roles
⬜ TC-RBAC-007: Superadmin bypass CabangScope (bisa lihat semua cabang)
```

---

## 5. E2E TEST PLAN (BROWSER)

### 5.1 Playwright Test Cases (Prioritas)

**Target:** Tambahkan 10+ Playwright spec files

#### 5.1.1 Authentication Flow

**File:** `tests/playwright/auth.spec.js`

```
⬜ TC-E2E-AUTH-001: Login dengan email + password valid → redirect ke dashboard
⬜ TC-E2E-AUTH-002: Login dengan password salah → error message
⬜ TC-E2E-AUTH-003: Logout → redirect ke login page
⬜ TC-E2E-AUTH-004: Akses halaman admin tanpa login → redirect ke login
```

---

#### 5.1.2 Sales Order Flow (Critical Path)

**File:** `tests/playwright/sales-order.spec.js`

```
⬜ TC-E2E-SO-001: Buat Sales Order → isi form → submit
⬜ TC-E2E-SO-002: SO list menampilkan SO yang baru dibuat
⬜ TC-E2E-SO-003: Request Approve SO di UI
⬜ TC-E2E-SO-004: Approve SO → cek status berubah di list
⬜ TC-E2E-SO-005: Navigasi dari SO ke Delivery Order terkait
```

---

#### 5.1.3 Invoice Creation

**File:** `tests/playwright/invoice.spec.js`

```
⬜ TC-E2E-INV-001: Buat invoice dari DO yang sudah sent
⬜ TC-E2E-INV-002: PPN dihitung otomatis saat rate diubah
⬜ TC-E2E-INV-003: other_fee (biaya lain) bisa ditambahkan dan total terupdate
⬜ TC-E2E-INV-004: Generate invoice number button berfungsi
```

---

#### 5.1.4 Format Angka (Sudah Ada)

**File:** `tests/playwright/money-format.spec.js`  
**Status:** ✅ Ada

```
✅ TC-E2E-FMT-001: Format angka Rupiah (titik sebagai pemisah ribuan, koma untuk desimal)
✅ TC-E2E-FMT-002: Currency format dengan prefix "Rp"
```

---

#### 5.1.5 Laporan Balance Sheet

**File:** `tests/playwright/balance-sheet.spec.js`

```
⬜ TC-E2E-BS-001: Halaman balance sheet terbuka tanpa error
⬜ TC-E2E-BS-002: Filter periode mengubah data yang ditampilkan
⬜ TC-E2E-BS-003: Export Excel tersedia dan bisa diunduh
⬜ TC-E2E-BS-004: Total Assets = Total Liabilities + Equity
```

---

### 5.2 Dusk Test Cases

**File:** `tests/Browser/`

```
⬜ TC-DUSK-001: Login → dashboard terbuka dengan widget
⬜ TC-DUSK-002: Buat Customer dari form → muncul di list
⬜ TC-DUSK-003: Navigasi menu semua modul tidak ada yang 404
⬜ TC-DUSK-004: Order Request form → tambah item → submit
```

---

## 6. PERFORMANCE TEST PLAN

### 6.1 Benchmark Halaman Report

**Target Tool:** `php artisan test` dengan `measureTime()` atau k6

| Halaman | Target Load Time | Test Scenario |
|---------|-----------------|---------------|
| Balance Sheet (1 bulan) | < 2 detik | 1 user, 1 bulan data |
| Balance Sheet (1 tahun) | < 5 detik | 1 user, 1 tahun data |
| Buku Besar (1 COA, 1 bulan) | < 2 detik | Single COA |
| Kartu Persediaan (1 produk) | < 3 detik | Single product |
| P&L 1 bulan | < 3 detik | Standard period |

---

### 6.2 Load Test Scenario

**Scenario:** 20 concurrent users, mix operations:
- 10 users browsing list pages (SO, DO, Invoice)
- 5 users generating reports
- 5 users creating new transactions

**Expected:** Response time < 3 detik, no timeout errors, no database deadlocks

---

## 7. REGRESSION TEST PLAN

### 7.1 Bug Fixes yang Harus Selalu Di-Regression-Test

| Bug | Test File | Test Case |
|-----|-----------|-----------|
| `other_fee` INTEGER bukan JSON array | `tests/Feature/InvoiceEditAndDeliveryOrderTest.php` | TC-INV-OFEE-001 |
| Race condition DO kosong saat WC approved | `tests/Feature/SalesOrderToDeliveryOrderCompleteTest.php` | TC-DO-RACE-001 |
| PPN dihitung sebagai absolute bukan persentase | `tests/Unit/TaxServiceTest.php` | TC-TAX-002 |
| Vendor payment test stale invoice_id | `tests/Feature/VendorPaymentTest.php` | TC-VP-001 |
| DO untuk Ambil Sendiri tidak dibuat | `tests/Feature/SalesOrderSelfPickupApprovedTest.php` | TC-SO-PICKUP-001 |

### 7.2 Flow Regression Tests (Run Setiap Release)

```bash
# Run critical flow tests
php artisan test tests/Feature/CompleteSalesFlowTest.php \
    tests/Feature/CompleteProcurementFlowTest.php \
    tests/Feature/ManufacturingFlowTest.php \
    tests/Feature/JournalEntryBalanceValidationTest.php \
    tests/Unit/TaxServiceTest.php \
    tests/Unit/BalanceSheetServiceTest.php
```

---

## 8. TEST CASE TEMPLATE

### 8.1 Feature Test Template

```php
<?php

use App\Models\User;
use App\Models\SaleOrder;
// ... other imports

it('deskripsi test case singkat', function () {
    // Arrange — Setup data
    $user = User::factory()->create();
    actingAs($user);
    
    $customer = Customer::factory()->create([
        'cabang_id' => $user->cabang_id,
    ]);
    
    // Act — Execute action
    $response = $this->post('/admin/sale-orders', [...]);
    
    // Assert — Verify outcome
    $response->assertSuccessful();
    $this->assertDatabaseHas('sale_orders', [
        'customer_id' => $customer->id,
        'status' => 'draft',
    ]);
});
```

---

### 8.2 Service Unit Test Template

```php
<?php

use App\Services\TaxService;

it('menghitung PPN exclusive dengan benar', function () {
    // Arrange
    $service = new TaxService();
    $amount = 1_000_000;
    $rate = 11;
    
    // Act
    $result = $service->calculateExclusive($amount, $rate);
    
    // Assert
    expect($result['dpp'])->toBe(1_000_000);
    expect($result['ppn'])->toBe(110_000);
    expect($result['total'])->toBe(1_110_000);
});
```

---

## 9. COVERAGE TARGET

### 9.1 Target Coverage per Modul

| Modul | Current | Target Q1 | Target Q2 |
|-------|---------|-----------|-----------|
| TaxService | 70% | 95% | 95% |
| InvoiceService | 50% | 80% | 90% |
| SalesOrderService | 60% | 80% | 90% |
| PurchaseOrderService | 65% | 85% | 90% |
| StockReservationService | 40% | 75% | 85% |
| CustomerReturnService | 20% | 70% | 85% |
| BalanceSheetService | 55% | 80% | 90% |
| ManufacturingService | 60% | 80% | 85% |
| Observers (semua) | 45% | 70% | 80% |
| **Overall** | **~65%** | **75%** | **85%** |

---

### 9.2 Coverage Report Command

```bash
php artisan test --coverage --min=70 > docs/coverage-$(date +%Y%m%d).txt
```

---

## 10. TEST EXECUTION CHECKLIST

### 10.1 Pre-Release Checklist

- [ ] Semua Unit Tests pass (`php artisan test --testsuite=Unit`)
- [ ] Semua Feature Tests pass (`php artisan test --testsuite=Feature`)
- [ ] Coverage minimal 70% terpenuhi
- [ ] Tidak ada N+1 queries baru (via Laravel Telescope)
- [ ] Regression tests untuk bug fixes yang ada di release ini

### 10.2 Monthly Test Audit

- [ ] Review test coverage report
- [ ] Identifikasi module dengan coverage < target
- [ ] Tambahkan test cases untuk area yang belum covered
- [ ] Update test plan dokumen ini

---

## 11. TEST PRIORITIZATION MATRIX

### Prioritas Pembuatan Test Baru

| Test | Prioritas | Estimasi | Status |
|------|-----------|----------|--------|
| CustomerReturnServiceTest (Unit) | 🔴 KRITIS | 4 jam | ⬜ Belum |
| CustomerReturnJournalTest (Feature) | 🔴 KRITIS | 4 jam | ⬜ Belum |
| StockReservationServiceTest (Unit) | 🔴 KRITIS | 3 jam | ⬜ Belum |
| InvoiceServiceTest (Unit) | 🟠 TINGGI | 3 jam | ⬜ Belum |
| JournalReversalTest (Feature) | 🟠 TINGGI | 4 jam | ⬜ Belum |
| StockMovementObserverTest (Unit) | 🟠 TINGGI | 2 jam | ⬜ Belum |
| AssetDepreciationAccuracyTest | 🟠 TINGGI | 3 jam | ⬜ Belum |
| Playwright: Sales Order Flow | 🟡 SEDANG | 4 jam | ⬜ Belum |
| Playwright: Invoice Creation | 🟡 SEDANG | 3 jam | ⬜ Belum |
| Playwright: Balance Sheet | 🟡 SEDANG | 3 jam | ⬜ Belum |
| AccountingPeriodClosingTest | 🟡 SEDANG | 5 jam | ⬜ Belum |
| MultiCurrencyTest | 🟢 RENDAH | 4 jam | ⬜ Belum |
| LoadTest (k6) | 🟢 RENDAH | 8 jam | ⬜ Belum |

---

*Dokumen test plan ini dibuat pada 14 Maret 2026. Update setiap Sprint/Release cycle.*
