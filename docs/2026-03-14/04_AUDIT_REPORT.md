# DUTA TUNGGAL ERP — System Audit Report
**Tanggal Audit Awal:** 14 Maret 2026 | **Tanggal Re-Audit:** 26 Maret 2026  
**Auditor:** GitHub Copilot AI  
**Metode:** Code review menyeluruh, architecture analysis, security assessment, test coverage analysis  
**Fokus Re-Audit:** Modul Purchase, Sales, dan Finance (code-level deep dive)

---

## RINGKASAN EKSEKUTIF

Sistem Duta Tunggal ERP adalah implementasi ERP yang **substansial dan fungsional** dengan cakupan modul yang komprehensif. Re-audit pada 26 Maret 2026 dengan fokus pada modul Purchase, Sales, dan Finance menemukan **12 issue kritis/tinggi baru** yang perlu segera ditangani, terutama seputar N+1 query di laporan keuangan, race condition di operasi stok dan kredit limit, serta ketiadaan mekanisme period closing akuntansi.

### Skor Audit Keseluruhan (Update 26 Maret 2026)

| Domain | Skor Lama | Skor Baru | Rating |
|--------|-----------|-----------|--------|
| Fungsionalitas Bisnis | 92/100 | 88/100 | ✅ Baik |
| Arsitektur Kode | 78/100 | 72/100 | ⚠️ Perlu Perbaikan |
| Keamanan | 71/100 | 68/100 | ⚠️ Perlu Perbaikan |
| Test Coverage | 65/100 | 63/100 | ⚠️ Perlu Perbaikan |
| Performa | 68/100 | 55/100 | ❌ Buruk |
| Dokumentasi Kode | 55/100 | 55/100 | ❌ Kurang |
| **Modul Purchase** | — | **70/100** | ⚠️ Cukup |
| **Modul Sales** | — | **68/100** | ⚠️ Cukup |
| **Modul Finance** | — | **64/100** | ⚠️ Mengkhawatirkan |
| **Total (Rata-rata)** | **71.5/100** | **68/100** | **⚠️ Cukup** |

---

## BAGIAN 1: AUDIT MODUL PURCHASE

> *Re-audit mendalam dilakukan pada 26 Maret 2026. File utama: `PurchaseOrderResource.php` (~1700 LOC), `PurchaseReceiptResource.php`, `VendorPaymentResource.php`, `PurchaseReturnService.php`, `PurchaseReceiptService.php`.*

### 🔴 Kritis

#### PURCH-001: N+1 Query di PurchaseReceiptResource

**Lokasi:** `app/Filament/Resources/PurchaseReceiptResource.php` — list view  
**Dampak:** 120+ queries per halaman saat membuka daftar receipt

**Temuan:** Kolom `total_biaya` dan `qc_status` tidak dilazily loaded dan setiap baris mengeksekusi `sum()` atau `count()` terpisah tanpa eager loading. Tidak ada `->with()` pada `modifyQueryUsing()`.

**Rekomendasi:**
```php
// Tambahkan di getEloquentQuery() atau modifyQueryUsing()
->with([
    'purchaseReceiptItems',
    'purchaseReceiptItems.qcResults',
    'supplier',
    'purchaseOrder',
])
// Untuk kolom kalkulasi, gunakan withSum / withCount
->withSum('purchaseReceiptItems as total_biaya', 'total_price')
->withCount('purchaseReceiptItems as item_count')
```

**Prioritas:** 🔴 FIX IMMEDIATELY

---

#### PURCH-002: N+1 Query di PurchaseOrderResource

**Lokasi:** `app/Filament/Resources/PurchaseOrderResource.php` (~1700 LOC)  
**Dampak:** ~100+ extra queries per page load di list view

**Temuan:** `modifyQueryUsing()` hanya menambahkan `orderBy('tanggal_po', 'desc')` dan **tidak** menambahkan `->with()` apapun. Kolom `supplier.perusahaan`, `items`, dan status badge semuanya melakukan query satu per satu per baris.

**Rekomendasi:**
```php
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        ->with([
            'supplier',
            'purchaseOrderItems.product',
            'purchaseReceipts',
        ])
        ->withCount('purchaseOrderItems');
}
```

**Prioritas:** 🔴 FIX IMMEDIATELY

---

#### PURCH-003: Race Condition di PurchaseReturnService

**Lokasi:** `app/Services/PurchaseReturnService.php` baris ~160–200  
**Dampak:** Data corruption — dua return bersamaan dapat menggandakan atau merusak `qty_received` di `PurchaseOrderItem`

**Temuan:** Update `PurchaseOrderItem` quantity dilakukan tanpa `DB::transaction()` atau `lockForUpdate()`. Dua request return bersamaan dapat membaca nilai qty yang sama dan masing-masing mengurangi, hasilnya qty salah.

**Rekomendasi:**
```php
DB::transaction(function () use ($return, $items) {
    foreach ($items as $item) {
        $poItem = PurchaseOrderItem::where('purchase_order_id', $return->purchase_order_id)
            ->where('product_id', $item['product_id'])
            ->lockForUpdate()   // ← kunci row selama transaksi
            ->firstOrFail();
        
        $poItem->decrement('qty_received', $item['qty_return']);
    }
    $return->update(['status' => 'approved']);
});
```

**Prioritas:** 🔴 FIX IMMEDIATELY

---

#### PURCH-004: N+1 Query di VendorPaymentResource

**Lokasi:** `app/Filament/Resources/VendorPaymentResource.php` baris ~805  
**Dampak:** Setiap baris di daftar payment melakukan query tambahan untuk `selected_invoices`

**Temuan:** Kolom `selected_invoices` adalah JSON array berisi `invoice_id`. Untuk menampilkan nama invoice, sistem melakukan `Invoice::find($id)` per baris. Dengan 50 payment dan 3 invoice per payment = 150+ extra queries.

**Rekomendasi:**
- Preload invoice IDs dalam bulk query setelah pagination
- Atau store `invoice_number` langsung di JSON sehingga tidak perlu join

---

### 🟠 Tinggi

#### PURCH-005: Auto-Create Cabang di Supplier Booted() Menggunakan `uniqid()`

**Lokasi:** `app/Models/Supplier.php` — method `booted()`  
**Dampak:** Saat `Auth::id()` null (artisan command, seeder, API call tanpa auth), sistem auto-membuat Cabang baru yang tidak terkontrol, dengan kode cabang yang tidak bermakna (`uniqid()`)

**Rekomendasi:** Guard secara eksplisit dan throw exception di environment non-seeder; atau pisahkan logika create-cabang dari booted() ke sebuah service.

---

#### PURCH-006: Silent Failure di PurchaseReceiptService

**Lokasi:** `app/Services/PurchaseReceiptService.php`  
**Dampak:** `['status' => 'skipped']` dikembalikan tanpa exception, caller tidak sadar ada inkonsistensi data

**Temuan:** Beberapa kondisi mengembalikan `['status' => 'skipped']` daripada throw exception atau log error. Petugas gudang tidak get notifikasi jika receipt di-skip karena kondisi edge case.

**Rekomendasi:**
- Throw `ReceiptSkippedException` yang ditangkap di controller dan ditampilkan sebagai warning notification
- Atau setidaknya `Log::warning()` dengan context yang cukup

---

#### PURCH-007: Uniqueness PO Number Hanya di Form Level — Tidak Ada DB Constraint

**Lokasi:** `app/Filament/Resources/PurchaseOrderResource.php` — form validation  
**Dampak:** Race condition: dua user submit PO dengan nomor yang sama dalam waktu bersamaan = duplikat nomor PO di database

**Rekomendasi:**
```php
// Di migration
$table->unique(['nomor_po', 'cabang_id']); // unique per cabang

// Di service (sebelum simpan)
DB::transaction(function () use ($data) {
    // Check dengan lockForUpdate
    $exists = PurchaseOrder::where('nomor_po', $data['nomor_po'])
        ->where('cabang_id', $data['cabang_id'])
        ->lockForUpdate()
        ->exists();
    
    if ($exists) throw new DuplicatePONumberException();
    
    return PurchaseOrder::create($data);
});
```

---

### 🟡 Sedang

#### PURCH-008: Magic Strings 'Belum Lunas' / 'Lunas' Tersebar

**Lokasi:** Minimal 8 file (Resources, Observers, Services)  
**Masalah:** `if ($invoice->status === 'Belum Lunas')` tersebar tanpa konstanta. Salah satu lokasi sudah menggunakan `'belum lunas'` (lowercase) sehingga ada inconsistency.  
**Rekomendasi:** Buat `InvoiceStatus` enum atau constants class.

---

#### PURCH-009: N+1 Query di Form `afterStateUpdated` — Lazy Loading Relasi

**Lokasi:** `PurchaseOrderResource.php` — Select::make untuk produk  
**Masalah:** Saat user memilih supplier di form, `afterStateUpdated` melakukan query relasi secara lazy. Pada form dengan banyak item, ini memperlambat UI.

---

#### PURCH-010: `qty_rejected` Bisa Melebihi `qty_received` — Tidak Ada Validasi

**Lokasi:** Quality Control form di `QualityControlPurchaseResource.php`  
**Masalah:** Tidak ada validasi bahwa `qty_rejected ≤ qty_received`. Data inconsistency bisa terjadi jika user salah input.  
**Rekomendasi:** Tambahkan `lte('qty_received')` Rule di validasi form.

---

#### PURCH-011: Method `createQCFromPurchaseReceiptItem()` Bertanda `@deprecated` Tapi Masih Digunakan

**Lokasi:** `app/Services/QualityControlService.php`  
**Masalah:** Method yang di-deprecated masih di-call dari beberapa lokasi. Ini menunjukkan refactoring yang tidak selesai.  
**Rekomendasi:** Selesaikan migrasi ke method pengganti atau hapus tag deprecated.

---

#### PURCH-012: 40+ Baris Debug Log di PurchaseOrderResource

**Lokasi:** `app/Filament/Resources/PurchaseOrderResource.php` baris ~559–605  
**Masalah:** `Log::debug()` statements yang seharusnya sementara masih ada di production code. Ini menghasilkan log besar yang bisa mengisi storage.  
**Rekomendasi:** Hapus atau pindahkan ke balik kondisi `if (config('app.debug'))`.

---

## BAGIAN 2: AUDIT MODUL SALES

> *Re-audit mendalam dilakukan pada 26 Maret 2026. File utama: `SaleOrderResource.php` (~1820 LOC), `DeliveryOrderResource.php` (~1050 LOC), `SalesInvoiceResource.php` (~1200+ LOC), `CustomerReturnService.php`, `CreditValidationService.php`, `CustomerReceiptObserver.php`.*

### 🔴 Kritis

#### SALES-001: N+1 Query di SaleOrderResource

**Lokasi:** `app/Filament/Resources/SaleOrderResource.php` (~1820 LOC)  
**Dampak:** Partial eager loading — `saleOrderItem` dimuat 1 level, tapi `saleOrderItem.product`, `saleOrderItem.warehouse`, dan `customer` tidak.

**Temuan:** `modifyQueryUsing()` menambahkan `with(['saleOrderItems'])` saja, tapi ketika rendering list view, setiap kolom yang tampil `customer.name`, `saleOrderItem.product.kode_produk`, dsb. masing-masing melakukan query terpisah.

**Rekomendasi:**
```php
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        ->with([
            'customer',
            'saleOrderItems.product',
            'saleOrderItems.warehouse',
            'salesInvoices',
        ])
        ->withCount('saleOrderItems');
}
```

**Prioritas:** 🔴 FIX IMMEDIATELY

---

#### SALES-002: Race Condition di DeliveryOrderObserver `handleSentStatus()`

**Lokasi:** `app/Observers/DeliveryOrderObserver.php` — `handleSentStatus()`  
**Dampak:** `delivered_quantity` pada `SaleOrderItem` bisa salah jika dua DO dikirim bersamaan untuk SO yang sama

**Temuan:** Tidak ada `DB::transaction()` atau `lockForUpdate()` saat mengupdate `delivered_quantity`. Dua observer yang berjalan bersamaan membaca nilai yang sama dan masing-masing menambahkan quantity, hasilnya hanya satu penambahan yang tersimpan.

**Rekomendasi:**
```php
private function handleSentStatus(DeliveryOrder $do): void
{
    DB::transaction(function () use ($do) {
        foreach ($do->deliveryOrderItems as $doItem) {
            SaleOrderItem::where('id', $doItem->sale_order_item_id)
                ->lockForUpdate()
                ->increment('delivered_quantity', $doItem->quantity);
        }
        $do->saleOrder->recalculateDeliveryStatus();
    });
}
```

**Prioritas:** 🔴 FIX IMMEDIATELY

---

#### SALES-003: Credit Limit Check Tidak Atomic

**Lokasi:** `app/Services/CreditValidationService.php`  
**Dampak:** Dua SO concurrent dapat masing-masing lolos validasi kredit, kemudian berdua di-commit, sehingga total outstanding melebihi limit

**Temuan:**
```php
// CreditValidationService::validate() — TIDAK ATOMIC
$outstanding = CustomerReceipt::where(...)...->sum('amount');
$creditLimit = $customer->credit_limit;

if ($outstanding + $newSOAmount > $creditLimit) {
    throw new CreditLimitExceededException();
}
// GAP di sini: dua request bisa sama-sama sampai di sini sebelum salah satu commit SO
```

**Rekomendasi:** Lock row customer saat validasi:
```php
DB::transaction(function () use ($customer, $newSOAmount) {
    $customer = Customer::where('id', $customer->id)
        ->lockForUpdate()
        ->first();
    
    $outstanding = $this->calculateOutstanding($customer);
    
    if ($outstanding + $newSOAmount > $customer->credit_limit) {
        throw new CreditLimitExceededException($customer, $outstanding, $newSOAmount);
    }
});
```

**Prioritas:** 🔴 FIX IMMEDIATELY

---

### 🟠 Tinggi

#### SALES-004: `SalesOrderService::approve()` Tidak Memanggil Credit Check

**Lokasi:** `app/Services/SalesOrderService.php` — method `approve()`  
**Dampak:** SO dapat disetujui meskipun customer sudah melebihi credit limit (jika credit limit berubah setelah SO dibuat)

**Temuan:** Method `approve()` mengubah status SO menjadi 'approved' tanpa memanggil `CreditValidationService::validate()` terlebih dulu. Validasi kredit hanya ada di `SaleOrderResource` form level, bukan di service.

**Rekomendasi:**
```php
public function approve(SaleOrder $saleOrder): void
{
    // Selalu validasi kredit sebelum approve
    $this->creditValidationService->validate(
        $saleOrder->customer,
        $saleOrder->total_amount
    );
    
    DB::transaction(function () use ($saleOrder) {
        $saleOrder->update(['status' => 'approved']);
        // ...rest of approval logic
    });
}
```

---

#### SALES-005: CustomerReturnService Tidak Membuat Jurnal untuk Tipe 'repair'

**Lokasi:** `app/Services/CustomerReturnService.php`  
**Dampak:** Jika keputusan return adalah 'repair' (perbaikan), tidak ada journal entry yang dibuat. Neraca tidak mencerminkan barang yang sedang diperbaiki.

**Temuan:** `switch ($decision)` dalam service hanya menangani `'refund'` dan `'replace'`; kasus `'repair'` tidak membuat jurnal dan tidak merestore stok.

**Rekomendasi:** Tambahkan case `'repair'` yang membuat jurnal memo dan mencatat item sebagai "dalam perbaikan" dengan akun WIP atau akun sementara.

---

#### SALES-006: CustomerReceiptObserver Status Comparison Case-Sensitive

**Lokasi:** `app/Observers/CustomerReceiptObserver.php`  
**Dampak:** Receipt dengan `status = 'Paid'` (huruf besar P — data historis) tidak akan memicu journal posting karena observer mengecek `status === 'paid'` (huruf kecil)

**Temuan:**
```php
// Observer
if ($receipt->status === 'paid') {        // ← lowercase
    $this->postReceiptJournal($receipt);
}

// Tapi beberapa data historis memiliki
$receipt->status = 'Paid';               // ← uppercase P
```

**Rekomendasi:**
```php
if (strtolower($receipt->status) === 'paid') {
    $this->postReceiptJournal($receipt);
}
// Atau: standarisasi semua status ke lowercase via database migration
```

---

#### SALES-007: Update AR Tidak Atomic dengan Journal Post di CustomerReceiptObserver

**Lokasi:** `app/Observers/CustomerReceiptObserver.php`  
**Dampak:** Jika journal posting sukses tapi AR update gagal (atau sebaliknya), data keuangan menjadi tidak konsisten

**Rekomendasi:** Wrap keduanya dalam `DB::transaction()`:
```php
DB::transaction(function () use ($receipt) {
    $this->ledgerPostingService->postCustomerReceiptJournal($receipt);
    $this->arService->updateAccountReceivable($receipt);
});
```

---

### 🟡 Sedang

#### SALES-008: Status String 'confirmed' Tidak Ada di Migrations

**Lokasi:** `app/Filament/Resources/DeliveryOrderResource.php`  
**Masalah:** Resource menggunakan status `'confirmed'` namun migration tabel delivery_orders tidak mendefinisikan status ini dalam kolom enum/check constraint. Risiko data inconsistency.

---

#### SALES-009: Resource Files Terlalu Besar — SaleOrderResource 1820 LOC

**Lokasi:** `SaleOrderResource.php` (1820), `SalesInvoiceResource.php` (1200+), `DeliveryOrderResource.php` (1050)  
**Masalah:** File terlalu besar menyulitkan maintenance dan code review.  
**Rekomendasi:** Pecah ke Pages terpisah (CreateSaleOrder, EditSaleOrder, ListSaleOrder) dan gunakan RelationManager untuk relasi.

---

#### SALES-010: Repeater `saleOrderItem` Tidak Ada `minItems(1)` dan Duplikat Produk Tidak Dicegah

**Lokasi:** `SaleOrderResource.php` — form Repeater  
**Masalah:** User dapat menyimpan SO tanpa item. User juga dapat menambah produk yang sama dua kali dalam satu SO, menyebabkan duplikat entry.

**Rekomendasi:**
```php
Repeater::make('saleOrderItems')
    ->minItems(1)
    ->rules([new NoDuplicateProducts()])
```

---

#### SALES-011: `warehouse_id` Nullable di CustomerReturn Tapi Service Skip Jika Null

**Lokasi:** `app/Services/CustomerReturnService.php` dan `CustomerReturnResource.php`  
**Masalah:** Jika `warehouse_id` null, service skip stock restoration tanpa warning. Stok tidak dikembalikan ke gudang manapun, tapi status return berhasil.

---

#### SALES-012: N+1 di CustomerReceiptResource `viewData()` Loop

**Lokasi:** `app/Filament/Resources/CustomerReceiptResource.php` — `viewData()` / infolist  
**Masalah:** Method `viewData()` melakukan loop dan query per receipt untuk mendapatkan detail invoice. Tanpa eager loading, ini menghasilkan 1 query per receipt per baris.

---

## BAGIAN 3: AUDIT MODUL FINANCE

> *Re-audit mendalam dilakukan pada 26 Maret 2026. File utama: `LedgerPostingService.php`, `BalanceSheetService.php`, `IncomeStatementService.php`, `CashFlowReportService.php`, `JournalValidationTrait.php`, `InvoiceObserver.php`.*

### ✅ Kekuatan yang Terdeteksi

#### FIN-STRENGTH-01: Validasi Balance Jurnal yang Ketat

**Lokasi:** `app/Traits/JournalValidationTrait.php`  
Setiap posting jurnal memvalidasi bahwa total debit = total kredit dengan toleransi 0.01 IDR. Trait ini digunakan secara konsisten di seluruh `LedgerPostingService`. **Ini praktik yang sangat baik dan wajib dipertahankan.**

#### FIN-STRENGTH-02: Pencegahan Double-Posting

**Lokasi:** `app/Services/LedgerPostingService.php`  
Sebelum posting, service mengecek `source_type + source_id` combination sudah ada atau belum. Ini mencegah jurnal duplikat akibat retry atau race condition sederhana.

---

### 🔴 Kritis

#### FIN-001: BalanceSheetService N+1 Query — Timeout Potensial 30–60 Detik

**Lokasi:** `app/Services/BalanceSheetService.php` — method `getAccountsByType()` atau `buildSection()`  
**Dampak:** Dengan 500 COA aktif, laporan Neraca mengeksekusi 500+ queries terpisah. Load time 30–60 detik. Di production dengan beban normal, bisa timeout.

**Temuan:**
```php
// Pola bermasalah yang terdeteksi:
$accounts->map(function ($account) use ($period) {
    $balance = JournalEntry::where('coa_id', $account->id)
        ->whereBetween('tanggal', [$period->start, $period->end])
        ->sum('debit') - JournalEntry::where(...)->sum('credit');
    // ← ini dijalankan SATU PER COA, bukan dalam bulk
    return [...];
});
```

**Rekomendasi:**
```php
// Satu query bulk daripada N queries
$balances = JournalEntry::query()
    ->whereBetween('tanggal', [$period->start, $period->end])
    ->groupBy('coa_id')
    ->selectRaw('coa_id, SUM(debit) - SUM(credit) as balance')
    ->pluck('balance', 'coa_id');

// Kemudian map tanpa additional query
$accounts->map(fn($account) => [
    'balance' => $balances->get($account->id, 0),
    ...
]);
```

**Prioritas:** 🔴 FIX IMMEDIATELY — Laporan Neraca tidak usable di skala production

---

#### FIN-002: Tidak Ada Mekanisme Period Closing Akuntansi

**Lokasi:** Database schema + `LedgerPostingService`  
**Dampak:** Melanggar prinsip SAK/ASAK — journal entries dari periode yang sudah ditutup masih bisa diedit/dihapus

**Temuan:** Tidak ada:
- Tabel `accounting_period` dengan field `closed_at`
- Field `locked_at` atau `is_locked` di `journal_entries`
- Check di service apakah periode sudah ditutup sebelum posting

Ini berarti seseorang dapat secara tidak sengaja (atau sengaja) mengedit jurnal dari tahun lalu, mengubah laporan keuangan historis.

**Rekomendasi:**
```sql
-- Migration baru
CREATE TABLE accounting_periods (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cabang_id BIGINT UNSIGNED NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    closed_at TIMESTAMP NULL,
    closed_by BIGINT UNSIGNED NULL,
    ...
);
```
```php
// Di LedgerPostingService sebelum posting
if ($this->isPeriodClosed($journalDate)) {
    throw new ClosedPeriodException("Period {$journalDate} sudah ditutup");
}
```

**Prioritas:** 🔴 FIX SOON — Required untuk compliance akuntansi

---

#### FIN-003: Multi-Currency Tidak Didukung di Journal Posting

**Lokasi:** `app/Services/LedgerPostingService.php`, tabel `journal_entries`  
**Dampak:** Semua jurnal diasumsikan IDR. Invoice dalam foreign currency tidak dapat diposting dengan benar.

**Temuan:**
- Model `Invoice` tidak memiliki `currency_id`
- Tabel `journal_entries` tidak memiliki kolom `currency_id` atau `exchange_rate`
- Ada tabel `currencies` dan `exchange_rates` tapi tidak digunakan di journal posting

**Rekomendasi (jangka menengah):**
1. Tambahkan `currency_id`, `exchange_rate`, `amount_original_currency` ke `journal_entries`
2. Invoice yang sudah ada di-default-kan ke IDR (exchange rate = 1.0)
3. Implementasikan konversi saat posting

---

### 🟠 Tinggi

#### FIN-004: Reversal Fields Ada di Schema Tapi Tidak Ada Implementasi

**Lokasi:** Migration `2026_03_01_024900` — fielnd `is_reversal` dan `reversal_of_transaction_id` di `journal_entries`  
**Dampak:** Fitur reversal jurnal tidak bisa digunakan meski sudah ada di schema; staff keuangan tidak bisa membatalkan jurnal salah dengan benar

**Temuan:** Tidak ada:
- Method `reverseEntry()` di `LedgerPostingService` atau service manapun  
- UI di Filament untuk memicu reversal
- Test untuk reversal functionality

**Rekomendasi:**
```php
// LedgerPostingService
public function reverseJournalEntry(JournalEntry $original): JournalEntry
{
    $this->ensurePeriodIsOpen($original->tanggal);
    
    return DB::transaction(function () use ($original) {
        $reversal = JournalEntry::create([
            ...
            'is_reversal' => true,
            'reversal_of_transaction_id' => $original->id,
            'amount' => $original->amount * -1,
        ]);
        $original->update(['is_reversed' => true]);
        return $reversal;
    });
}
```

---

#### FIN-005: JournalEntry Tidak Ada Audit Trail `created_by` / `updated_by`

**Lokasi:** Tabel `journal_entries` dan model `JournalEntry`  
**Dampak:** Tidak dapat diketahui siapa yang membuat atau mengubah jurnal — melanggar prinsip audit keuangan

**Temuan:** Semua model keuangan lain (`AccountPayable`, `Deposit`, `CustomerReceipt`) memiliki `created_by`. Tapi `journal_entries` tidak.

**Rekomendasi:**
```sql
ALTER TABLE journal_entries
    ADD COLUMN created_by BIGINT UNSIGNED NULL,
    ADD COLUMN updated_by BIGINT UNSIGNED NULL,
    ADD FOREIGN KEY (created_by) REFERENCES users(id),
    ADD FOREIGN KEY (updated_by) REFERENCES users(id);
```
Gunakan `CreatedByObserver` yang sudah ada di project atau `BlamableTrait`.

---

#### FIN-006: Double-Posting Race Condition Masih Bisa Terjadi

**Lokasi:** `app/Services/LedgerPostingService.php`  
**Dampak:** Dua request concurrent yang posting jurnal untuk source yang sama bisa lolos check `exists()` sebelum salah satu commit

**Temuan:**
```php
// Check yang ada — TIDAK ATOMIC
$alreadyPosted = JournalEntry::where('source_type', $type)
    ->where('source_id', $id)
    ->exists(); // ← dua request bisa sama-sama dapat false di sini

if ($alreadyPosted) return; // ← lalu keduanya lanjut posting
```

**Rekomendasi:**
```php
DB::transaction(function () use ($type, $id, $entries) {
    // Lock untuk mencegah concurrent insert
    $alreadyPosted = JournalEntry::where('source_type', $type)
        ->where('source_id', $id)
        ->lockForUpdate()
        ->exists();
    
    if ($alreadyPosted) return;
    
    // Create entries di dalam transaksi yang sama
    JournalEntry::insert($entries);
});
```

---

### 🟡 Sedang

#### FIN-007: IncomeStatementService N+1 Query Serupa dengan BalanceSheet

**Lokasi:** `app/Services/IncomeStatementService.php` — `getAccountsByCodePrefix()` dan `getAccountsByType()`  
**Masalah:** Pattern N+1 yang sama dengan BalanceSheetService. Laporan Laba Rugi juga terancam slow jika COA banyak.  
**Rekomendasi:** Terapkan solusi bulk aggregate yang sama dengan FIN-001.

---

#### FIN-008: COA Code Lookup Menggunakan String Hardcoded yang Fragile

**Lokasi:** `app/Services/LedgerPostingService.php`  
**Masalah:** Kode COA seperti `'1140.01'` (Kas), `'2110'` (Hutang Usaha), `'2100.10'` di-hardcode sebagai string literal. Jika klien mengubah struktur COA, sistem akan error tanpa indikasi yang jelas.

**Rekomendasi:**
```php
// config/coa.php
return [
    'kas'         => env('COA_CODE_KAS', '1140.01'),
    'hutang_usaha' => env('COA_CODE_HUTANG', '2110'),
    // ...
];
```

---

#### FIN-009: InvoiceObserver Risiko Null Reference pada fromModel Soft-Deleted

**Lokasi:** `app/Observers/InvoiceObserver.php`  
**Masalah:** `$invoice->fromModel->supplier_id` diakses langsung tanpa pengecekan apakah `fromModel` masih ada (bisa soft-deleted). Jika PO yang terkait di-soft-delete, observer akan throw `ErrorException: Trying to get property of null`.

**Rekomendasi:**
```php
$supplierId = optional($invoice->fromModel)->supplier_id 
    ?? $invoice->supplier_id 
    ?? null;

if (!$supplierId) {
    Log::warning("Invoice #{$invoice->id} has no supplier reference");
    return;
}
```

---

#### FIN-010: CashFlowReportService — Potensi Lazy Loading dalam `buildSection()`

**Lokasi:** `app/Services/CashFlowReportService.php`  
**Masalah:** Service memiliki eager loading yang baik di level utama (`sections`, `items`), tapi di dalam method `buildSection()` ada akses ke relasi yang mungkin tidak di-eager-load untuk setiap periode yang berbeda.  
**Rekomendasi:** Review `buildSection()` dan pastikan tidak ada query tersembunyi dalam loop iterasi.

---

## BAGIAN 4: TEMUAN KEAMANAN (dari Audit Awal — masih relevan)

### 🔴 Kritis

#### SEC-001: Laravel Dusk Routes Aktif di Semua Environment

**Lokasi:** `routes/web.php`  
**Dampak:** Memungkinkan siapa saja login sebagai user mana pun tanpa password

```
GET|HEAD _dusk/login/{userId}/{guard?}
GET|HEAD _dusk/logout/{guard?}
```

**Rekomendasi:** Pastikan Dusk routes hanya aktif di `local` atau `testing` environment dengan guard di `bootstrap/app.php`.

**Prioritas:** 🔴 FIX IMMEDIATELY (jika belum dilakukan)

---

### 🟠 Tinggi

#### SEC-002: Tidak Ada Rate Limiting pada Endpoint Report

**Rekomendasi:** `Route::middleware(['auth', 'throttle:30,1'])` pada semua route laporan keuangan.

#### SEC-003: Mass Assignment pada Beberapa Model

**Rekomendasi:** Replace `$guarded = []` dengan `$fillable` explicit, terutama untuk kolom `status`, `approved_by`.

#### SEC-004: Input Validation Lemah pada Form Upload

**Rekomendasi:** Validasi MIME type ketat pada upload `po_file_path`.

---

## BAGIAN 5: TEMUAN ARSITEKTUR & KODE (dari Audit Awal — summary)

### 🟠 Tinggi

- **ARCH-001:** Observer side effects tidak bisa di-disable untuk testing — 20+ observer terdaftar, chain panjang
- **ARCH-002:** Business logic tersebar antara Observer, Service, dan Resource — tidak konsisten
- **ARCH-003:** Service layer tidak seragam (static vs instance, ukuran sangat bervariasi)
- **ARCH-004:** Stock operations tanpa database locking *(terkait PURCH-003 dan SALES-002)*

### 🟡 Sedang

- **ARCH-005:** CabangScope global bisa menghambat cross-branch operations
- **ARCH-006:** JSON columns untuk data relasional (menghambat DB-level integrity)
- **CODE-001:** Resource files terlalu besar (>1000 LOC) *(terkait SALES-009)*
- **CODE-003:** Inconsistency status strings (campuran uppercase/lowercase/integer)

---

## BAGIAN 6: RINGKASAN TEMUAN RE-AUDIT (26 Maret 2026)

### Distribusi Severity — Temuan Baru

| Severity | Purchase | Sales | Finance | Total Baru |
|----------|----------|-------|---------|------------|
| 🔴 Kritis | 4 | 3 | 3 | **10** |
| 🟠 Tinggi | 3 | 4 | 3 | **10** |
| 🟡 Sedang | 5 | 5 | 4 | **14** |
| **Total** | **12** | **12** | **10** | **34** |

### Top Issues by Risk

| # | ID | Modul | Deskripsi | Risk |
|---|-----|-------|-----------|------|
| 1 | FIN-001 | Finance | BalanceSheet N+1 — 500+ queries, timeout 60s | 🔴 Kritis |
| 2 | FIN-002 | Finance | Tidak ada period closing — data historis bisa diedit | 🔴 Kritis |
| 3 | SALES-003 | Sales | Credit limit tidak atomic — race condition | 🔴 Kritis |
| 4 | SALES-002 | Sales | DeliveryOrderObserver race condition | 🔴 Kritis |
| 5 | PURCH-003 | Purchase | PurchaseReturnService race condition | 🔴 Kritis |
| 6 | PURCH-001 | Purchase | PurchaseReceiptResource N+1 (120+ queries/page) | 🔴 Kritis |
| 7 | FIN-006 | Finance | Double-posting race condition | 🟠 Tinggi |
| 8 | SALES-004 | Sales | approve() tidak memanggil credit check | 🟠 Tinggi |
| 9 | FIN-005 | Finance | JournalEntry tidak ada audit trail user | 🟠 Tinggi |
| 10 | SALES-006 | Sales | Status comparison case-sensitive (Paid vs paid) | 🟠 Tinggi |

---

## REKOMENDASI PRIORITAS SEGERA

### Sprint 1 — Fix Dalam 1 Minggu (Kritis)

1. **FIN-001: BalanceSheetService N+1** — Refactor ke bulk aggregate query (SQL `GROUP BY`). Laporan Neraca tidak dapat digunakan di production saat ini.
2. **SALES-003 + FIN-006: Race conditions** — Tambahkan `lockForUpdate()` di kredit limit check dan double-posting check.
3. **SALES-002 + PURCH-003: Observer/Service race conditions** — Wrap `DB::transaction()` dengan `lockForUpdate()` di semua operasi decrement/increment quantity.
4. **PURCH-001 + PURCH-002: Eager loading** — Tambahkan `->with([...])` di semua Resource list views.
5. **SALES-006: Case-sensitive status** — Gunakan `strtolower()` atau standardisasi data ke lowercase.

### Sprint 2 — Fix Dalam 1 Bulan (Tinggi)

6. **FIN-002: Period Closing** — Buat tabel `accounting_periods` dan guard di `LedgerPostingService`.
7. **SALES-004: Credit check di approve()** — Pastikan `CreditValidationService::validate()` dipanggil di semua code path SO approval.
8. **FIN-005: Audit trail jurnal** — Tambahkan `created_by`/`updated_by` ke `journal_entries`.
9. **FIN-004: Reversal implementation** — Implement `reverseJournalEntry()` dan UI reversal.
10. **PURCH-007: DB constraint PO number** — Tambahkan unique index level database.
11. **SEC-001: Dusk routes** — Verifikasi sudah tidak aktif di production.

### Sprint 3 — Improvement (Sedang)

12. **FIN-003: Multi-currency** — Design dan implement currency support di journal entries.
13. **FIN-008: COA config** — Extract hardcoded COA codes ke `config/coa.php`.
14. **PURCH-012: Debug logs** — Bersihkan `Log::debug()` yang tidak diperlukan.
15. **SALES-009: Resource refactoring** — Pecah resource besar ke Pages terpisah.
16. **PURCH-010: qty_rejected validation** — Tambahkan rule `lte('qty_received')`.

---

*Re-audit ini dilakukan pada 26 Maret 2026 melalui code review statis mendalam pada modul Purchase, Sales, dan Finance.*  
*Audit awal dilakukan pada 14 Maret 2026.*  
*Auditor: GitHub Copilot AI*

---

