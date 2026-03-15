# DUTA TUNGGAL ERP — Improvement Plan
**Tanggal:** 14 Maret 2026  
**Versi Dokumen:** 1.0  

---

## RINGKASAN RENCANA

Dokumen ini berisi rencana perbaikan terperinci berdasarkan hasil audit sistem tanggal 14 Maret 2026. Perbaikan dikelompokkan dalam 4 fase dengan prioritas berbeda.

### Timeline Overview

| Fase | Durasi | Fokus |
|------|--------|-------|
| **Fase 1 — KRITIS** | 1-2 hari | Security fixes yang tidak bisa ditunda |
| **Fase 2 — PENTING** | 1-2 minggu | Stabilitas, performa, dan coverage test |
| **Fase 3 — STANDARD** | 2-4 minggu | Code quality, refactoring, dan fitur |
| **Fase 4 — JANGKA PANJANG** | 1-3 bulan | Infrastruktur, dokumentasi, dan test suite |

---

## FASE 1 — PERBAIKAN KRITIS (1-2 HARI)

### P1-001: Nonaktifkan Dusk Routes di Production

**Issue Ref:** SEC-001  
**Prioritas:** 🔴 KRITIS  
**Estimasi:** 1 jam  

**Masalah:**  
Route `_dusk/login/{userId}` memungkinkan bypass authentication — siapapun yang mengetahui user ID dapat login sebagai user tersebut tanpa password.

**Langkah Perbaikan:**

1. Ubah `bootstrap/app.php` atau `app/Providers/RouteServiceProvider.php`:
```php
// Hanya register Dusk routes saat testing/local
if (app()->environment('local', 'testing')) {
    Route::middleware('web')->group(function () {
        // Dusk routes akan otomatis terdaftar oleh Dusk
    });
}
```

2. Verifikasi bahwa Dusk routes tidak muncul di `php artisan route:list` di production.

3. Tambahkan environment check di `DuskTestCase.php`:
```php
public static function setUpBeforeClass(): void
{
    // Pastikan environment bukan production
    if (env('APP_ENV') === 'production') {
        self::markTestSkipped('Dusk tests skipped in production');
    }
    parent::setUpBeforeClass();
}
```

**Test Verifikasi:**
- [ ] `php artisan route:list` tidak menampilkan `_dusk/` routes ketika `APP_ENV=production`
- [ ] Dusk tests tetap berjalan di environment `testing`

---

### P1-002: Tambahkan Rate Limiting pada Semua Endpoints Sensitif

**Issue Ref:** SEC-002  
**Prioritas:** 🔴 KRITIS  
**Estimasi:** 2 jam  

**Langkah Perbaikan:**

1. Update `routes/web.php`:
```php
Route::middleware(['auth', 'throttle:60,1'])->group(function () {
    Route::get('/reports/stock-report/preview', [StockReportController::class, 'preview']);
    Route::get('/reports/inventory-card/print', [InventoryCardController::class, 'printView']);
    Route::get('/reports/inventory-card/download-pdf', [InventoryCardController::class, 'downloadPdf']);
    Route::get('/reports/inventory-card/download-excel', [InventoryCardController::class, 'downloadExcel']);
});
```

2. Tambahkan rate limiter untuk auth routes di `bootstrap/app.php`:
```php
RateLimiter::for('login', function (Request $request) {
    return Limit::perMinute(5)->by($request->input('email'));
});
```

---

### P1-003: Perbaiki Database-Level Locking untuk Stock Operations

**Issue Ref:** ARCH-004, BIZ-002  
**Prioritas:** 🔴 KRITIS  
**Estimasi:** 4 jam  

**Masalah:** Race condition pada update `qty_available` dan `qty_reserved` di `InventoryStock`.

**Langkah Perbaikan:**

1. Update `StockMovementObserver.php` — gunakan pessimistic locking:
```php
DB::transaction(function() use ($movement) {
    $stock = InventoryStock::where('product_id', $movement->product_id)
        ->where('warehouse_id', $movement->warehouse_id)
        ->lockForUpdate()
        ->firstOrCreate([...]);
    
    // Update stock safely
    if ($movement->type === 'IN') {
        $stock->increment('qty_available', $movement->quantity);
    } else {
        $stock->decrement('qty_available', $movement->quantity);
    }
});
```

2. Update `StockReservationService.php`:
```php
DB::transaction(function() use ($product, $warehouse, $qty) {
    $stock = InventoryStock::lockForUpdate()
        ->where('product_id', $product->id)
        ->where('warehouse_id', $warehouse->id)
        ->first();
    
    if ($stock->qty_available < $qty) {
        throw new InsufficientStockException("Stok tidak mencukupi");
    }
    
    $stock->decrement('qty_available', $qty);
    $stock->increment('qty_reserved', $qty);
});
```

---

## FASE 2 — PERBAIKAN PENTING (1-2 MINGGU)

### P2-001: Testkan Retur Customer Secara End-to-End

**Issue Ref:** BIZ-003, TEST-002  
**Prioritas:** 🟠 TINGGI  
**Estimasi:** 1-2 hari  

**Skenario yang Harus Ditest:**

1. **Happy Path:** Customer return lengkap (pending → received → qc → approved → completed)
2. **Partial Return:** Hanya sebagian item yang dikembalikan
3. **Stock Restoration:** Verifikasi stok benar-benar dikembalikan ke gudang
4. **Journal Entry:** Setiap perubahan status harus menghasilkan jurnal yang benar
5. **Edge Cases:**
   - Return invoice dengan PPN berbeda
   - Return ketika stok di gudang sudah berbeda
   - Concurrent returns dari customer yang sama

**File Test yang Perlu Dibuat/Diupdate:**
- `tests/Feature/CustomerReturnFeatureTest.php` — expand existing
- `tests/Feature/CustomerReturnJournalTest.php` — buat baru, khusus jurnal
- `tests/Feature/ERP/CustomerReturnTest.php` — end-to-end flow

---

### P2-002: Implementasikan Journal Reversal UI

**Issue Ref:** BIZ-004  
**Prioritas:** 🟠 TINGGI  
**Estimasi:** 1 hari  

**Masalah:** Kolom `is_reversal` dan `reversal_of_transaction_id` sudah ada di schema tapi belum ada UI/service untuk melakukan reversal.

**Langkah:**
1. Buat action `Reverse Journal` di `JournalEntryResource.php`
2. Service method: `JournalEntryService::reverseJournal(JournalEntry $entry): void`
3. Logic: buat jurnal baru dengan debit/credit dibalik, set `is_reversal = true`, link ke original

---

### P2-003: Optimasi N+1 Query pada Resource List Views

**Issue Ref:** SEC-005, PERF-001  
**Prioritas:** 🟠 TINGGI  
**Estimasi:** 2-3 hari  

**Resource yang Perlu Dioptimasi:**

| Resource | Relasi yang Perlu Eager Load |
|----------|------------------------------|
| DeliveryOrderResource | saleOrders, driver, vehicle, warehouse |
| SaleOrderResource | customer, quotation, saleOrderItems |
| PurchaseOrderResource | supplier, purchaseOrderItems |
| InvoiceResource | customer, invoiceItems |
| AccountReceivableResource | invoice, customer |
| AccountPayableResource | invoice, supplier |
| StockMovementResource | product, warehouse |

**Implementasi:**
```php
// Dalam Resource::getEloquentQuery()
return parent::getEloquentQuery()
    ->with(['saleOrders.customer', 'driver', 'vehicle', 'warehouse'])
    ->withCount('saleOrders');
```

---

### P2-004: Implementasikan Closing Period Akuntansi

**Issue Ref:** BIZ-006  
**Prioritas:** 🟠 TINGGI  
**Estimasi:** 3-5 hari  

**Fitur yang Diperlukan:**
1. **Locking Periode** — Cegah edit/delete journal entry di periode yang sudah ditutup
2. **Closing Entries** — Posting closing entries (tutup akun Revenue & Expense ke Retained Earnings)
3. **Opening Balance** — Carry forward saldo ke periode baru

**Migration yang Diperlukan:**
```sql
CREATE TABLE accounting_periods (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    status ENUM('open', 'closed') DEFAULT 'open',
    closed_by BIGINT UNSIGNED,
    closed_at TIMESTAMP NULL,
    cabang_id BIGINT UNSIGNED,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

**Model Changes:**
```php
// JournalEntry — validasi periode
protected static function booted(): void
{
    static::creating(function (JournalEntry $entry) {
        $period = AccountingPeriod::where('cabang_id', $entry->cabang_id)
            ->coveringDate($entry->date)
            ->first();
        
        if ($period && $period->status === 'closed') {
            throw new ClosedPeriodException("Periode {$entry->date} sudah ditutup");
        }
    });
}
```

---

### P2-005: Perbaiki Status Inconsistency

**Issue Ref:** CODE-003  
**Prioritas:** 🟠 TINGGI  
**Estimasi:** 1-2 hari  

**Langkah:**

1. Buat Enum Classes untuk semua status:
```php
// app/Enums/SaleOrderStatus.php
enum SaleOrderStatus: string
{
    case DRAFT = 'draft';
    case REQUEST_APPROVE = 'request_approve';
    case APPROVED = 'approved';
    case CLOSED = 'closed';
    case COMPLETED = 'completed';
    case CANCELED = 'canceled';
}

// app/Enums/DeliveryOrderStatus.php
enum DeliveryOrderStatus: string
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case RECEIVED = 'received';
    case APPROVED = 'approved';
    case CLOSED = 'closed';
    case REJECT = 'reject';
    case DELIVERY_FAILED = 'delivery_failed';
}
```

2. Update SuratJalan `status = 1` → gunakan string enum yang bermakna.

---

### P2-006: Implementasikan Query Caching untuk Laporan

**Issue Ref:** PERF-001, PERF-002  
**Prioritas:** 🟠 TINGGI  
**Estimasi:** 2-3 hari  

**Langkah:**

1. Aktifkan Redis cache (atau file cache untuk development):
```php
// config/cache.php
'default' => env('CACHE_DRIVER', 'redis'),
```

2. Add caching ke report services:
```php
// BalanceSheetService.php
public function generate(string $period, int $cabangId): array
{
    $cacheKey = "balance_sheet_{$period}_{$cabangId}";
    
    return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($period, $cabangId) {
        return $this->generateUncached($period, $cabangId);
    });
}
```

3. Invalidate cache ketika journal entries berubah.

---

### P2-007: Validasi dan Sanitasi Input yang Lebih Ketat

**Issue Ref:** SEC-003, SEC-004  
**Prioritas:** 🟠 TINGGI  
**Estimasi:** 2-3 hari  

**Area yang Perlu Diperbaiki:**

1. **File Upload Validation** — semua upload harus ada MIME validation
2. **Numeric Fields** — pastikan tidak bisa diisi nilai negatif yang tidak valid
3. **Text Fields** — tambahkan `maxLength()` pada field yang kritis
4. **Review `$fillable`** — audit semua model, pastikan tidak ada kolom sensitif yang bisa di-mass-assign

---

## FASE 3 — PERBAIKAN STANDARD (2-4 MINGGU)

### P3-001: Refactor Resource Files yang Terlalu Besar

**Issue Ref:** CODE-001  
**Prioritas:** 🟡 SEDANG  
**Estimasi:** 1-2 minggu  

**Target Refactoring:**

| Resource | Ukuran Estimasi | Pemecahan yang Diusulkan |
|----------|-----------------|--------------------------|
| SaleOrderResource | >800 baris | Pisahkan Form, Table, Actions ke files terpisah |
| PurchaseOrderResource | >700 baris | Pisahkan ke sub-classes |
| DeliveryOrderResource | >600 baris | Pisahkan ke RelationManagers |

**Pola yang Direkomendasikan:**
```
Resources/SaleOrderResource/
├── SaleOrderResource.php          (koordinator utama)
├── Pages/
│   ├── ListSaleOrders.php
│   ├── CreateSaleOrder.php
│   ├── EditSaleOrder.php
│   └── ViewSaleOrder.php
├── Forms/
│   └── SaleOrderForm.php          (extracted form schema)
└── RelationManagers/
    ├── SaleOrderItemsRelationManager.php
    └── DeliveryOrdersRelationManager.php
```

---

### P3-002: Implementasikan PHP Enum untuk Semua Status dan Tipe

**Issue Ref:** CODE-003, CODE-004  
**Prioritas:** 🟡 SEDANG  
**Estimasi:** 3-5 hari  

**Enum yang Perlu Dibuat:**
```
app/Enums/
├── SaleOrderStatus.php
├── DeliveryOrderStatus.php
├── PurchaseOrderStatus.php
├── QuotationStatus.php
├── OrderRequestStatus.php
├── CustomerReturnStatus.php
├── QualityControlStatus.php
├── StockTransferStatus.php
├── MaterialIssueStatus.php
├── ManufacturingOrderStatus.php
├── InvoiceStatus.php
├── VoucherRequestStatus.php
├── AssetStatus.php
├── PurchaseReceiptStatus.php
└── PaymentRequestStatus.php
```

---

### P3-003: Implementasikan Language Files

**Issue Ref:** CODE-002  
**Prioritas:** 🟡 SEDANG  
**Estimasi:** 3-5 hari  

**Struktur:**
```
lang/id/
├── sales.php         (labels untuk modul penjualan)
├── procurement.php   (labels modul pengadaan)
├── inventory.php     (labels inventori)
├── finance.php       (labels keuangan)
├── manufacturing.php (labels manufaktur)
├── assets.php        (labels aset)
└── validation.php    (pesan validasi)
```

---

### P3-004: Centralized Document Number Service

**Issue Ref:** ARCH-007  
**Prioritas:** 🟡 SEDANG  
**Estimasi:** 2-3 hari  

**Implementasi:**
```php
// app/Services/DocumentNumberService.php
class DocumentNumberService
{
    public function generate(string $prefix, ?int $cabangId = null): string
    {
        $date = now()->format('Ymd');
        $key = "{$prefix}_{$date}";
        
        // Atomic counter using database sequence
        DB::transaction(function() use ($key, &$number) {
            $counter = DocumentCounter::where('key', $key)
                ->lockForUpdate()
                ->firstOrCreate(['key' => $key, 'value' => 0]);
            
            $counter->increment('value');
            $number = str_pad($counter->value, 4, '0', STR_PAD_LEFT);
        });
        
        return "{$prefix}-{$date}-{$number}";
    }
}
```

---

### P3-005: Tambahkan PHPDoc pada Service Classes

**Issue Ref:** DOC-001  
**Prioritas:** 🟡 SEDANG  
**Estimasi:** 3-5 hari  

**Target Services yang Paling Kritis untuk Didokumentasikan:**
1. `InvoiceService` — kompleks, sering digunakan
2. `SalesOrderService` — entry point penjualan
3. `PurchaseOrderService` — entry point pengadaan
4. `JournalEntryAggregationService` — digunakan di laporan
5. `BalanceSheetService` — laporan keuangan kritis
6. `TaxService` — kalkulasi pajak

---

### P3-006: Implementasikan Queue untuk Operasi Berat

**Issue Ref:** PERF-003, PERF-005  
**Prioritas:** 🟡 SEDANG  
**Estimasi:** 3-5 hari  

**Operasi yang Harus Dipindah ke Queue:**
1. Excel/PDF export yang besar (>1000 rows)
2. Notifikasi database (hanya notifikasi, bukan business logic)
3. Activity logging
4. Email notifications
5. Batch journal posting

**Implementasi:**
```php
// app/Jobs/GenerateExcelReportJob.php
class GenerateExcelReportJob implements ShouldQueue
{
    use Dispatchable, Queue;
    
    public function __construct(
        private string $reportType,
        private array $params,
        private int $userId
    ) {}
    
    public function handle(): void
    {
        // Generate report
        // Notify user via Filament notification
    }
}
```

---

### P3-007: Perbaiki Inconsistency Tipe Data di JSON Columns

**Issue Ref:** ARCH-006  
**Prioritas:** 🟡 SEDANG  
**Estimasi:** 2-3 hari  

**Langkah:**
1. Audit semua JSON columns di database
2. Tambahkan Model casts:
```php
protected $casts = [
    'other_fee' => 'array',
    'selected_invoices' => 'array',
    'invoice_receipts' => 'array',
    'delivery_orders' => 'array',
];
```
3. Tambahkan validation bahwa default value selalu `[]` bukan `null` atau `0`.

---

### P3-008: Tambahkan Database Indexes untuk Query Performance

**Issue Ref:** PERF-004  
**Prioritas:** 🟡 SEDANG  
**Estimasi:** 1-2 hari  

**Migration yang Diperlukan:**
```php
// Sales performance indexes
Schema::table('sale_orders', function (Blueprint $table) {
    $table->index(['customer_id', 'status']);
    $table->index(['cabang_id', 'order_date']);
});

Schema::table('delivery_orders', function (Blueprint $table) {
    $table->index(['status', 'delivery_date']);
    $table->index(['cabang_id', 'delivery_date']);
});

Schema::table('journal_entries', function (Blueprint $table) {
    $table->index(['source_type', 'source_id']);
    $table->index(['coa_id', 'date']);
    $table->index(['cabang_id', 'date']);
});

Schema::table('stock_movements', function (Blueprint $table) {
    $table->index(['product_id', 'warehouse_id']);
    $table->index(['from_model_type', 'from_model_id']);
});

Schema::table('invoices', function (Blueprint $table) {
    $table->index(['from_model_type', 'from_model_id']);
    $table->index(['status', 'due_date']);
    $table->index(['cabang_id', 'invoice_date']);
});
```

---

## FASE 4 — JANGKA PANJANG (1-3 BULAN)

### P4-001: Migrasi JSON Columns ke Proper Tables

**Issue Ref:** ARCH-006  
**Prioritas:** 🟢 RENDAH  
**Estimasi:** 1-2 minggu  

**Tables Target:**
- `invoice_delivery_orders` (pivot) — mengganti `invoices.delivery_orders` (JSON)
- `customer_receipt_invoices` (pivot) — mengganti `customer_receipts.selected_invoices` (JSON)
- `vendor_payment_invoices` (pivot) — mengganti `vendor_payments.selected_invoices` (JSON)

---

### P4-002: Implementasikan API Layer

**Prioritas:** 🟢 RENDAH  
**Estimasi:** 2-4 minggu  

**Motivasi:** Saat ini semua akses via Filament admin. Jika di masa depan diperlukan mobile app atau third-party integration, API layer sangat diperlukan.

**Implementasi:**
- RESTful API dengan Laravel Sanctum untuk authentication
- API versioning (`/api/v1/`)
- Rate limiting per API key
- OpenAPI/Swagger documentation

---

### P4-003: Implementasikan Multi-Currency yang Lebih Solid

**Issue Ref:** BIZ-005  
**Prioritas:** 🟢 RENDAH  
**Estimasi:** 1-2 minggu  

**Yang Diperlukan:**
1. Exchange rate history table
2. Konversi otomatis saat posting jurnal
3. Laporan keuangan dalam IDR (dengan kurs pada tanggal transaksi)
4. Revaluation period-end untuk foreign currency balances

---

### P4-004: Load Testing & Stress Testing

**Issue Ref:** TEST-003  
**Prioritas:** 🟢 RENDAH  
**Estimasi:** 3-5 hari  

**Tools:** k6, Apache JMeter, atau Laravel Telescope + Debugbar profiling

**Skenario Test:**
- 50 concurrent users mengakses dashboard
- 10 concurrent users membuat invoice
- Report generation under load

---

### P4-005: Implementasikan Monitoring & Alerting

**Prioritas:** 🟢 RENDAH  
**Estimasi:** 1-2 minggu  

**Yang Diperlukan:**
1. Laravel Telescope untuk application monitoring (dev)
2. Sentry untuk error tracking (production)
3. Database slow query logging
4. Uptime monitoring
5. Disk usage alerts

---

### P4-006: Buat Comprehensive API Documentation

**Issue Ref:** DOC-002, DOC-003  
**Prioritas:** 🟢 RENDAH  
**Estimasi:** 1 minggu  

**Dokumen yang Perlu Dibuat:**
1. Permission dictionary (semua nama permission + deskripsi)
2. Observer side effects diagram
3. Business flow diagrams (flowchart tiap modul)
4. Developer onboarding guide

---

## CHECKLIST EKSEKUSI FASE 1

**Target Selesai: 15 Maret 2026**

- [ ] **P1-001** — Nonaktifkan Dusk Routes di production
  - [ ] Update environment check
  - [ ] Verify dengan `route:list`
  - [ ] Deploy ke production

- [ ] **P1-002** — Tambahkan Rate Limiting
  - [ ] Update `routes/web.php`
  - [ ] Test throttle behavior
  
- [ ] **P1-003** — Database-level Locking untuk Stock
  - [ ] Update `StockMovementObserver`
  - [ ] Update `StockReservationService`
  - [ ] Run existing stock tests untuk verifikasi

---

## CHECKLIST EKSEKUSI FASE 2

**Target Selesai: 28 Maret 2026**

- [ ] **P2-001** — Test Customer Return end-to-end
- [ ] **P2-002** — Journal Reversal UI
- [ ] **P2-003** — Optimasi N+1 query (5 resource prioritas)
- [ ] **P2-004** — Accounting Period Closing
- [ ] **P2-005** — Status Enum classes
- [ ] **P2-006** — Query caching untuk reports
- [ ] **P2-007** — Input validation hardening

---

## METRIK KEBERHASILAN

| Metrik | Baseline (14 Mar) | Target (28 Mar) | Target (30 Apr) |
|--------|-------------------|-----------------|-----------------|
| Critical security issues | 2 | 0 | 0 |
| Test coverage (Feature) | ~65% | 75% | 85% |
| Average page load (report pages) | ~3-5s | ~1-2s | <1s |
| N+1 query violations | ~15 | <5 | 0 |
| PHPDoc coverage (Services) | ~10% | 30% | 70% |

---

*Rencana perbaikan ini dibuat pada 14 Maret 2026 berdasarkan audit sistem komprehensif.*
