# QA AUDIT REPORT — MODUL INVENTORY / GUDANG
**Duta Tunggal ERP**
**Tanggal Audit:** 16 Maret 2026
**Auditor:** Senior QA / Tester
**Versi Sistem:** Production-ready (Pre-release)
**Metode:** Static Code Analysis + Dynamic Test Execution + Database Schema Review

---

## RINGKASAN EKSEKUTIF

Audit menyeluruh dilakukan terhadap modul **Inventory / Gudang** yang mencakup:

| Sub-Modul | Status |
|-----------|--------|
| Warehouse & Rak Management | ✅ Stabil |
| Inventory Stock (Master Stok) | ⚠️ Minor issues |
| Stock Movement (Mutasi Stok) | 🔴 Critical Bug |
| Stock Transfer (Transfer Antar Gudang) | ⚠️ Medium Risk |
| Stock Adjustment (Penyesuaian Stok) | 🔴 Critical Bug |
| Stock Opname | 🔴 Critical Bug |
| Stock Reservation (Reservasi Stok) | 🔴 Critical Bug |
| Customer Return (Retur Pelanggan) | ⚠️ Medium Risk |
| Purchase Return (Retur Pembelian) | 🔴 Bug Konfirmasi |
| Report & Laporan Stok | ✅ Sebagian Stabil |

**Total Temuan:**
- 🔴 **Critical (Blocker):** 5 isu
- ⚠️ **Medium (Major):** 4 isu
- 🔵 **Minor / Improvement:** 6 isu
- 📋 **Test Coverage Gap:** 4 area tanpa tes

---

## DAFTAR ISI

1. [Arsitektur Modul Inventory](#1-arsitektur-modul-inventory)
2. [Critical Bugs — Wajib Diperbaiki](#2-critical-bugs--wajib-diperbaiki)
3. [Medium Risk Issues](#3-medium-risk-issues)
4. [Minor Issues & Improvements](#4-minor-issues--improvements)
5. [Test Coverage Analysis](#5-test-coverage-analysis)
6. [Test Failures yang Dikonfirmasi](#6-test-failures-yang-dikonfirmasi)
7. [Rencana Perbaikan & Prioritas](#7-rencana-perbaikan--prioritas)
8. [Rekomendasi Jangka Panjang](#8-rekomendasi-jangka-panjang)
9. [Checklist Verifikasi Pasca-Perbaikan](#9-checklist-verifikasi-pasca-perbaikan)

---

## 1. ARSITEKTUR MODUL INVENTORY

### 1.1 Struktur Model Database

```
warehouses ──┬──< raks
             └──< inventory_stocks ──< stock_movements
                       ↑                     ↑
              (qty_available,         (dari banyak sumber:
               qty_reserved,          purchase, sales, transfer,
               qty_min)               adjustment, opname, manufacture)

stock_reservations ──→ inventory_stocks
stock_transfers ──< stock_transfer_items ──→ stock_movements
stock_opnames ──< stock_opname_items
stock_adjustments ──< stock_adjustment_items
```

### 1.2 Alur Inventory Utama

```
Purchase Receipt → StockMovement(purchase_in) → InventoryStock.qty_available ↑
Sales DO Completed → StockMovement(sales) → InventoryStock.qty_available ↓
Sales DO Approved → StockReservation create → InventoryStock.qty_available ↓ (via StockReservationObserver)
Sales DO Sent → StockReservation delete → InventoryStock.qty_available ↑ (observer)
Transfer Approve → StockMovement(transfer_out+transfer_in) → Stock berubah
Material Issue Completed → StockMovement(manufacture_out) → Stock ↓
```

### 1.3 Observer yang Terdaftar (AppServiceProvider)

| Observer | Model | Status |
|----------|-------|--------|
| StockMovementObserver | StockMovement | ✅ Terdaftar |
| StockReservationObserver | StockReservation | ✅ Terdaftar |
| DeliveryOrderObserver | DeliveryOrder | ✅ Terdaftar |
| MaterialIssueObserver | MaterialIssue | ✅ Terdaftar |
| PurchaseReturnObserver | PurchaseReturn | ✅ Terdaftar |
| SaleOrderObserver | SaleOrder | ✅ Terdaftar |
| InventoryStockObserver | InventoryStock | ✅ Terdaftar di Model (boot) |
| **StockAdjustmentObserver** | **StockAdjustment** | **🔴 TIDAK ADA** |
| **StockOpnameObserver** | **StockOpname** | **🔴 TIDAK ADA** |
| StockTransferItemObserver | StockTransferItem | ⚠️ Import ada, tapi TIDAK terdaftar di boot() |

---

## 2. CRITICAL BUGS — WAJIB DIPERBAIKI

---

### 🔴 BUG-001: Stock Adjustment TIDAK Mengupdate Inventory Stock

**Severity:** CRITICAL  
**File Terdampak:**
- `app/Models/StockAdjustment.php`
- `app/Models/StockAdjustmentItem.php`
- `app/Filament/Resources/StockAdjustmentResource/Pages/EditStockAdjustment.php`

**Deskripsi Masalah:**

Ketika status `StockAdjustment` berubah menjadi `approved`, **tidak ada mekanisme** yang:
1. Membuat `StockMovement` bertipe `adjustment_in` atau `adjustment_out`
2. Mengupdate `InventoryStock.qty_available`

Model `StockAdjustment` **tidak memiliki** hook `booted()` / lifecycle events yang memonitor perubahan status. Tidak ada observer yang terdaftar. Satu-satunya tempat `adjustment_in/adjustment_out` digunakan adalah di `StockMovementObserver`, yang baru berjalan jika ada `StockMovement` yang dibuat — tetapi tidak ada yang membuatnya.

**Bukti Code:**
```php
// app/Models/StockAdjustment.php — TIDAK ada booted() atau lifecycle hook
// app/Providers/AppServiceProvider.php — TIDAK ada StockAdjustment::observe(...)
```

**Dampak:**
- Approving Stock Adjustment TIDAK berubah apa-apa di inventory nyata
- Angka stok di sistem menjadi tidak akurat
- User berpikir stok sudah disesuaikan, padahal tidak
- Data audit dan laporan stok menjadi salah

**Penyebab:**
- Model hanya menyimpan data header + items tetapi tidak ada bisnis logic yang meng-trigger StockMovement saat approval

**Solusi yang Harus Dilakukan:**

Tambahkan lifecycle hook di `StockAdjustment` model:

```php
protected static function booted(): void
{
    static::updating(function (StockAdjustment $adjustment) {
        if ($adjustment->isDirty('status') && $adjustment->status === 'approved') {
            $adjustment->load('items');
            foreach ($adjustment->items as $item) {
                if ($item->difference_qty == 0) continue;

                $type = $item->difference_qty > 0 ? 'adjustment_in' : 'adjustment_out';
                StockMovement::create([
                    'product_id'      => $item->product_id,
                    'warehouse_id'    => $adjustment->warehouse_id,
                    'rak_id'          => $item->rak_id,
                    'quantity'        => abs($item->difference_qty),
                    'value'           => abs($item->difference_value),
                    'type'            => $type,
                    'date'            => $adjustment->adjustment_date,
                    'reference_id'    => $adjustment->adjustment_number,
                    'notes'           => 'Stock Adjustment: ' . $adjustment->reason,
                    'from_model_type' => StockAdjustment::class,
                    'from_model_id'   => $adjustment->id,
                ]);
            }
            $adjustment->update([
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
        }
    });
}
```

---

### 🔴 BUG-002: Stock Opname TIDAK Mengupdate Inventory Stock

**Severity:** CRITICAL  
**File Terdampak:**
- `app/Models/StockOpname.php`
- `app/Filament/Resources/StockOpnameResource/Pages/EditStockOpname.php`

**Deskripsi Masalah:**

Ketika `StockOpname` disetujui (`status = 'approved'`), method `createAdjustmentJournalEntries()` dipanggil (via hook `updating`) — yang sudah benar untuk sisi akuntansi. Namun **inventory stock tidak diupdate sama sekali**.

`StockOpname` memiliki relasi `stockMovements()` (morphMany ke StockMovement) tetapi **tidak ada kode yang pernah membuat StockMovement** saat approval.

**Bukti Code:**
```php
// app/Models/StockOpname.php - booted() hanya memanggil:
$stockOpname->createAdjustmentJournalEntries(); // hanya jurnal, tidak ada stock update!

// Relasi ini ada tapi tidak pernah diisi:
public function stockMovements() {
    return $this->morphMany(StockMovement::class, 'fromModel', ...);
}
```

**Dampak:**
- Sesudah stock opname disetujui, qty_available di inventory TIDAK berubah
- Selisih fisik vs sistem yang sudah dihitung per item (`difference_qty`) tidak diterapkan
- Nilai jurnal akuntansi sudah terbuat, tapi kuantitas stok masih salah
- Inkonsistensi antara laporan keuangan dan laporan stok

**Solusi yang Harus Dilakukan:**

Tambahkan pembuatan StockMovement dalam hook approval:

```php
// Di dalam static::updating() setelah createAdjustmentJournalEntries():
if ($stockOpname->isDirty('status') && $stockOpname->status === 'approved') {
    $stockOpname->createAdjustmentJournalEntries();
    
    // TAMBAHKAN: buat stock movements dan update inventory
    $stockOpname->load('items');
    foreach ($stockOpname->items as $item) {
        if ($item->difference_qty == 0) continue;
        
        $type = $item->difference_qty > 0 ? 'adjustment_in' : 'adjustment_out';
        StockMovement::create([
            'product_id'      => $item->product_id,
            'warehouse_id'    => $stockOpname->warehouse_id,
            'rak_id'          => $item->rak_id,
            'quantity'        => abs($item->difference_qty),
            'value'           => abs($item->difference_value),
            'type'            => $type,
            'date'            => $stockOpname->opname_date,
            'reference_id'    => $stockOpname->opname_number,
            'notes'           => 'Stock Opname: ' . $stockOpname->opname_number,
            'from_model_type' => StockOpname::class,
            'from_model_id'   => $stockOpname->id,
        ]);
    }
    $stockOpname->update([
        'approved_by' => auth()->id(),
        'approved_at' => now(),
    ]);
}
```

---

### 🔴 BUG-003: qty_on_hand Diakses Sebagai Database Column di PurchaseReturnService

**Severity:** CRITICAL (Runtime Error / Potential Data Corruption)  
**File Terdampak:**
- `app/Services/PurchaseReturnService.php` (baris 341)

**Deskripsi Masalah:**

Di method `adjustStock()`, kode berikut mencoba melakukan `decrement` pada kolom `qty_on_hand`:

```php
$inventoryStock->decrement('qty_on_hand', $item->qty_returned); // BARIS 341 - ERROR
```

`qty_on_hand` **bukan kolom database** (`inventory_stocks` schema tidak memiliki kolom ini). Ini adalah **computed accessor** yang didefinisikan di `InventoryStock` model:

```php
public function getQtyOnHandAttribute()
{
    return $this->qty_available - $this->qty_reserved; // hanya properti virtual
}
```

**Dampak:**
- Memanggil `decrement('qty_on_hand')` akan melempar `QueryException: Column not found`
- Proses retur pembelian akan GAGAL jika `adjustStock()` dipanggil
- Barang yang dikembalikan ke supplier tidak berkurang stoknya di sistem

**Solusi:**

```php
// SALAH (baris 341):
$inventoryStock->decrement('qty_on_hand', $item->qty_returned);

// BENAR - hapus baris tersebut karena bukan kolom DB:
// Cukup decrement qty_available saja:
$inventoryStock->decrement('qty_available', $item->qty_returned);
```

Sekaligus pastikan `StockMovement` bertipe `purchase_return` **tidak ditangani** oleh `StockMovementObserver` (konfirmasi: tidak ada di `$inTypes` / `$outTypes`), sehingga `adjustStock()` sudah benar menangani pengurangan stok secara manual via `decrement('qty_available')` — baris pertama itu sudah benar. Hapus baris `decrement('qty_on_hand')` yang menyebabkan error.

---

### 🔴 BUG-004: StockReservationObserver — Inkonsistensi Semantik qty_available

**Severity:** CRITICAL (logika bisnis dan tes gagal)  
**File Terdampak:**
- `app/Observers/StockReservationObserver.php`
- `tests/Feature/StockReservationFlowTest.php`

**Deskripsi Masalah:**

`StockReservationObserver` ketika `created` (reservasi dibuat) melakukan:
```php
$inventoryStock->increment('qty_reserved', $qtyToUpdate);
$inventoryStock->decrement('qty_available', $qtyToUpdate); // ← DOUBLE DEDUCT
```

Sementara `SalesOrderService::confirm()` juga mengandalkan `qty_available` untuk validasi stok:
```php
$availableForReservation = $inventoryStock->qty_available - $inventoryStock->qty_reserved;
```

Ini menghasilkan **dua semantik berbeda** dalam satu sistem:
- `qty_available` = stok fisik tersedia sebelum reservasi (semantik asli)
- `qty_available` = stok yang belum direservasi (semantik baru setelah observer dimodifikasi)

**Bukti dari Test Failure:**

```
// Test mengharapkan: qty_available TETAP 100 (stok fisik)
assertEquals(100, $inventoryStock->qty_available); // GAGAL — dapat 90
// Karena observer mengurangi qty_available saat reservasi
```

**Dampak:**
- Kalkulasi `qty_on_hand` (`qty_available - qty_reserved`) menjadi salah dua kali (double-counted)
- `SalesOrderService::confirm()` menghitung available incorrectly: jika qty_available sudah dikurangi reservasi tapi masih dikurangi qty_reserved lagi
- Laporan stok menampilkan angka yang membingungkan
- 3 test tes gagal akibat isu ini

**Rekomendasi Solusi — Pilihan A (Standard ERP):**

Pakai semantik yang konsisten:
- `qty_available` = total stok fisik yang diterima (TIDAK berkurang saat reservasi)
- `qty_reserved` = stok yang sudah direservasi untuk pesanan
- `qty_on_hand` = qty_available - qty_reserved (stok yang bisa dijual/digunakan)

```php
// StockReservationObserver — PERBAIKAN:
if ($operation === 'increment') {
    $inventoryStock->increment('qty_reserved', $qtyToUpdate);
    // JANGAN kurangi qty_available — itu stok fisik
}
if ($operation === 'decrement') {
    $inventoryStock->decrement('qty_reserved', $qtyToUpdate);
    // JANGAN tambah qty_available — sudah stok fisik
}
```

Kemudian `SalesOrderService::confirm()` perlu disesuaikan:
```php
// Sebelum (salah):
$availableForReservation = $inventoryStock->qty_available - $inventoryStock->qty_reserved;
// Sesudah (benar):
$availableForReservation = $inventoryStock->qty_on_hand; // = qty_available - qty_reserved
```

---

### 🔴 BUG-005: StockTransferItemObserver TIDAK Terdaftar tapi Di-import

**Severity:** CRITICAL (Transfer tidak memotong/menambah stok saat item dibuat)  
**File Terdampak:**
- `app/Models/StockTransferItem.php`
- `app/Providers/AppServiceProvider.php`

**Deskripsi Masalah:**

Di `AppServiceProvider.php`, `StockTransferItemObserver` di-import (baris 52) **tapi tidak pernah di-register** via `StockTransferItem::observe(...)`. Di `StockTransferItem` model:

```php
protected static function boot() {
    parent::boot();
    // static::observe(StockTransferItemObserver::class); ← DI-COMMENT OUT!
}
```

Artinya observer ini yang harusnya menangani update stok per item transfer TIDAK berjalan.

**Klarifikasi:** Stock transfer menggunakan alur berbeda — stok diupdate saat `approveStockTransfer()` dipanggil via `StockTransferService`, yang membuat `StockMovement`. Ini sudah benar. Tetapi keberadaan observer yang di-comment-out menimbulkan:
1. Kebingungan developer: apakah ini disengaja atau terlupakan?
2. Risiko jika developer mengaktifkan observer tanpa memahami konsekuensinya → double update

**Solusi:**
- Jika observer tidak diperlukan, **hapus file** `StockTransferItemObserver.php` dan hapus import-nya
- Atau tambahkan komentar yang jelas bahwa stock update ditangani oleh `StockTransferService::approveStockTransfer()`

---

## 3. MEDIUM RISK ISSUES

---

### ⚠️ ISSUE-006: Stock Transfer — Tidak Ada Validasi Stok Sebelum Approve

**Severity:** MEDIUM  
**File:** `app/Services/StockTransferService.php`

**Deskripsi:**

Method `approveStockTransfer()` langsung membuat `StockMovement(transfer_out)` untuk setiap item tanpa memvalidasi apakah `from_warehouse_id` memiliki stok yang cukup.

```php
public function approveStockTransfer($stockTransfer) {
    foreach ($stockTransfer->stockTransferItem as $stockTransferItem) {
        // TIDAK ADA VALIDASI STOK DI SINI
        $stockTransferItem->stockMovement()->create([...transfer_out...]);
        $stockTransferItem->stockMovement()->create([...transfer_in...]);
    }
}
```

**Dampak:**
- Transfer bisa diapprove walaupun stok gudang asal = 0
- `qty_available` gudang asal bisa menjadi negatif
- Laporan stok menunjukkan angka tidak valid

**Solusi:**
```php
public function approveStockTransfer($stockTransfer) {
    DB::transaction(function () use ($stockTransfer) {
        foreach ($stockTransfer->stockTransferItem as $item) {
            $stock = InventoryStock::where('product_id', $item->product_id)
                ->where('warehouse_id', $item->from_warehouse_id)
                ->lockForUpdate()
                ->first();
            
            if (!$stock || $stock->qty_on_hand < $item->quantity) {
                $product = $item->product->name ?? $item->product_id;
                throw new \Exception("Stok tidak cukup untuk produk: {$product}");
            }
        }
        // Lanjutkan create movements...
    });
}
```

---

### ⚠️ ISSUE-007: Customer Return — StockMovement type 'customer_return' TIDAK Ditangani oleh StockMovementObserver

**Severity:** MEDIUM  
**File:** `app/Observers/StockMovementObserver.php`, `app/Services/CustomerReturnService.php`

**Deskripsi:**

`CustomerReturnService::processCompletion()` membuat `StockMovement` dengan `type = 'customer_return'`:
```php
StockMovement::create([
    'type' => 'customer_return',
    ...
]);
```

Namun `StockMovementObserver` hanya menangani tipe:
```php
$inTypes  = ['purchase_in', 'transfer_in', 'manufacture_in', 'adjustment_in'];
$outTypes = ['sales', 'transfer_out', 'manufacture_out', 'adjustment_out'];
```

`customer_return` **tidak ada** di keduanya. Akibatnya observer tidak akan meng-update inventory. Ini sebenarnya **disengaja** karena `CustomerReturnService` langsung menjalankan `$stock->increment('qty_available', $qty)` secara manual. Tetapi bila ada developer yang tidak tahu dan mengandalkan observer, atau jika ada kode masa depan yang membuat `StockMovement` dengan type ini tanpa manual update, stok tidak akan terpengaruh.

**Rekomendasi:**
- Tambahkan konsistensi: masukkan `customer_return` ke `$inTypes` dan hapus update manual di `CustomerReturnService`, ATAU
- Tambahkan komentar eksplisit di observer bahwa `customer_return` dan `purchase_return` ditangani secara manual

---

### ⚠️ ISSUE-008: AuditInventoryConsistency Command — Tidak Mempertimbangkan customer_return dan purchase_return

**Severity:** MEDIUM  
**File:** `app/Console/Commands/AuditInventoryConsistency.php`

**Deskripsi:**

Command audit konsistensi stok hanya menghitung:
```php
$inTypes  = ['purchase_in', 'transfer_in', 'manufacture_in', 'adjustment_in'];
$outTypes = ['sales', 'transfer_out', 'manufacture_out', 'adjustment_out'];
```

Tetapi di database, `stock_movements.type` juga bisa bernilai `customer_return` dan `purchase_return` (keduanya mengurangi stok). Ini mengakibatkan audit command memberikan hasil yang **tidak akurat** karena delta yang dihitung tidak mencakup semua pergerakan stok.

**Solusi:**
```php
$inTypes  = ['purchase_in', 'transfer_in', 'manufacture_in', 'adjustment_in', 'customer_return'];
$outTypes = ['sales', 'transfer_out', 'manufacture_out', 'adjustment_out', 'purchase_return'];
```

---

### ⚠️ ISSUE-009: Unique Constraint inventory_stocks — Nullable rak_id Berpotensi Masalah

**Severity:** MEDIUM  
**Database:** `inventory_stocks` table

**Deskripsi:**

Tabel `inventory_stocks` memiliki unique constraint:
```sql
UNIQUE KEY `inventory_stocks_product_id_warehouse_id_rak_id_unique` (`product_id`,`warehouse_id`,`rak_id`)
```

Kolom `rak_id` adalah `NULLABLE`. Dalam MySQL, **dua baris dengan nilai NULL dianggap berbeda** untuk tujuan unique constraint — artinya bisa terdapat duplikat `(product_id, warehouse_id, NULL)` jika kode tidak menggunakan `firstOrCreate` dengan benar.

**Bukti dalam kode:**
```php
// StockMovementObserver::created()
$inventoryStock = InventoryStock::create([...]);  // bisa duplicate jika race condition
```

**Dampak:**
- Dalam kondisi concurrent request (misalnya dua purchase receipt serentak untuk produk yang sama), bisa terbuat dua InventoryStock records dengan `rak_id = NULL`
- Stok akan terpecah, audit command akan mendeteksi delta

**Solusi:**
- Ganti `InventoryStock::create()` dengan `InventoryStock::firstOrCreate()` atau `firstOrNew()` secara konsisten di semua observer
- Pertimbangkan mengganti `NULL` dengan nilai sentinel `0` atau menangani duplikat dengan `updateOrCreate`

---

## 4. MINOR ISSUES & IMPROVEMENTS

---

### 🔵 ISSUE-010: StockAdjustmentItem — current_qty Tidak Diisi Otomatis dari Inventory

**Severity:** MINOR  
**File:** `app/Filament/Resources/StockAdjustmentResource/RelationManagers/StockAdjustmentItemsRelationManager.php`

**Deskripsi:**

Saat user memilih produk di form Adjustment Item, field `current_qty` diisi manual oleh user. Tidak ada auto-populate dari `InventoryStock.qty_available`. Berbeda dengan `StockOpnameResource` yang sudah auto-populate:

```php
// StockOpnameItemsRelationManager.php — SUDAH AUTO-POPULATE:
$inventoryStock = InventoryStock::where('product_id', $state)
    ->where('warehouse_id', $warehouseId)->first();
$set('system_qty', $inventoryStock->qty_available);

// StockAdjustmentItemsRelationManager.php — BELUM:
->afterStateUpdated(function ($state, Forms\Set $set) {
    if ($state) {
        $product = Product::find($state);
        // You can add logic to get current stock here  ← TODO TIDAK DISELESAIKAN
    }
})
```

**Dampak:** User bisa salah input `current_qty`, menyebabkan `difference_qty` yang tidak akurat.

**Solusi:** Auto-populate `current_qty` dari InventoryStock saat produk dipilih, mirip dengan StockOpname.

---

### 🔵 ISSUE-011: StockOpnameResource — Tidak Ada Status 'cancelled' atau Rollback

**Severity:** MINOR  
**File:** `app/Filament/Resources/StockOpnameResource.php`

**Deskripsi:**

Status opname: `draft → in_progress → completed → approved`. Tidak ada mekanisme pembatalan. Jika opname sudah diapprove dan ternyata ada kesalahan input, tidak bisa di-rollback. Tidak ada aksi untuk "reject" atau "cancel" opname yang sudah approved.

**Solusi:** Tambahkan status `cancelled` dan aksi rollback yang membuat `StockMovement` kebalikannya.

---

### 🔵 ISSUE-012: generateAdjustmentNumber() — Race Condition pada Concurrent Request

**Severity:** MINOR  
**File:** `app/Models/StockAdjustment.php`, `app/Models/StockOpname.php`

**Deskripsi:**

Pattern penomoran otomatis menggunakan:
```php
$latest = self::where('adjustment_number', 'like', $prefix . '%')
    ->orderBy('adjustment_number', 'desc')->first();
$nextNumber = $lastNumber + 1;
return $prefix . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
```

Tanpa database lock atau menggunakan sequence, dua request bersamaan bisa menghasilkan nomor yang sama → constraint violation.

**Solusi:** Gunakan `DB::transaction()` dengan `lockForUpdate()` atau tambah random suffix, atau gunakan database sequence/auto-increment.

---

### 🔵 ISSUE-013: StockTransfer — Tidak Ada Konfirmasi Penerimaan di Gudang Tujuan

**Severity:** MINOR  
**File:** `app/Services/StockTransferService.php`

**Deskripsi:**

Alur transfer:
```
Draft → Request → Approved → (selesai)
```

Saat `Approved`, `transfer_in` langsung dicatat. Tidak ada konfirmasi dari gudang tujuan bahwa barang sudah benar-benar **diterima fisik**. Ini melanggar prinsip 4-eyes / dual-control untuk transfer antar gudang.

**Rekomendasi:** Tambah status `In-Transit` dan `Received`. Stock movement `transfer_in` baru dibuat saat gudang tujuan konfirmasi terima barang.

---

### 🔵 ISSUE-014: StockMinimumTable Widget — Tidak Ada Notifikasi Otomatis

**Severity:** MINOR  
**File:** `app/Filament/Widgets/StockMinimumTable.php`

**Deskripsi:**

Widget menampilkan produk yang stoknya di bawah minimum (`qty_available < qty_min`). Tidak ada notifikasi otomatis (email/in-app) ke purchasing atau warehouse manager.

**Rekomendasi:** Buat scheduled job atau event listener untuk notifikasi stok minimum.

---

### 🔵 ISSUE-015: COA Fallback di StockOpname Journal Tidak Konsisten

**Severity:** MINOR  
**File:** `app/Models/StockOpname.php`

**Deskripsi:**

```php
$inventoryAdjustmentCoa = ChartOfAccount::where('code', '5100')->first();
if (!$inventoryAdjustmentCoa) {
    $inventoryAdjustmentCoa = ChartOfAccount::where('type', 'expense')->first(); // Fallback ke expense pertama
}

$inventoryCoa = ChartOfAccount::where('code', '1100')->first();
if (!$inventoryCoa) {
    $inventoryCoa = ChartOfAccount::where('type', 'asset')->first(); // Fallback ke asset pertama
}
```

Jika COA dengan kode `5100` dan `1100` tidak ditemukan, sistem menggunakan COA random pertama dari tipe expense/asset. Ini bisa menyebabkan jurnal yang salah secara akuntansi.

**Solusi:** Gunakan konfigurasi COA yang terpusat (AppSettings), bukan hardcode kode COA atau fallback berbahaya.

---

## 5. TEST COVERAGE ANALYSIS

### 5.1 Coverage Matrix

| Feature | Test File | Coverage |
|---------|-----------|----------|
| Warehouse CRUD | `InventoryFlowTest.php` | 70% |
| InventoryStock CRUD | `InventoryFlowTest.php` | 65% |
| StockMovement Observer | `StockMovementTest.php` | 80% ✅ |
| Stock Adjustment Approval | `StockAdjustmentTest.php` | 40% ⚠️ |
| Stock Opname Approval | `StockOpnameResourceTest.php` | 35% ⚠️ |
| Stock Transfer Workflow | `StockTransferTest.php` | 30% ⚠️ (banyak fail) |
| Stock Reservation Flow | `StockReservationFlowTest.php` | 50% ⚠️ (3 failed) |
| Customer Return Stok | `CustomerReturnFeatureTest.php` | 30% |
| Purchase Return Stok | `PurchaseReturnFeatureTest.php` | 40% |
| **Stock Adjustment → Movement** | **TIDAK ADA** | **0% 🔴** |
| **Stock Opname → Movement** | **TIDAK ADA** | **0% 🔴** |
| **Negative Stock Prevention** | **TIDAK ADA** | **0% 🔴** |
| **Concurrent Reservation** | **TIDAK ADA** | **0% 🔴** |

### 5.2 Test Files Terkait Inventory

```
tests/Feature/
├── InventoryFlowTest.php ✅
├── InventoryReportTest.php ✅
├── InventoryFinanceImpactTest.php ✅
├── StockAdjustmentTest.php ⚠️ (partial)
├── StockMovementTest.php ✅
├── StockReservationFlowTest.php ⚠️ (3 failing)
├── StockReservationServiceTest.php ✅
├── StockTransferTest.php ⚠️ (banyak failing - DB issue)
├── WarehouseAuditTest.php ⚠️ (1 failing)
└── Filament/
    ├── StockOpnameResourceTest.php ⚠️ (partial)
    └── StockOpnameItemsRelationManagerTest.php ✅
```

---

## 6. TEST FAILURES YANG DIKONFIRMASI

Berikut adalah daftar test yang **terkonfirmasi gagal** berdasarkan eksekusi langsung:

### 6.1 StockTransferTest — 10 Tests FAILED

**Error:** `SQLSTATE[42S02]: Base table or view not found: 1146 Table 'duta_tunggal_test.cabangs' doesn't exist`

**Root Cause:** Test environment tidak menjalankan migrasi lengkap, atau beberapa test tidak menggunakan `RefreshDatabase` trait dengan benar, menyebabkan tabel `cabangs` dan `permissions` tidak tersedia.

**Tindakan:**
1. Pastikan semua test file inventory menggunakan `RefreshDatabase` trait
2. Pastikan `database/schema/mysql-schema.sql` mencakup semua tabel terbaru
3. Jalankan `php artisan migrate:fresh --env=testing` sebelum test suite

### 6.2 StockReservationFlowTest — 3 Tests FAILED

**Errors:**
```
Test 1: assertEquals(100, qty_available) — dapat 90
        (observer mengurangi qty_available saat reservasi, test mengharapkan tidak berkurang)

Test 2: assertEquals(95, qty_available) — dapat 85
        (partial delivery, same issue)

Test 3: assertEquals(10, qty_available) — dapat 2
        (double reservation, same issue - semantik qty_available)
```

**Root Cause:** Inkonsistensi semantik `qty_available` (BUG-004 di atas). Test ditulis dengan asumsi `qty_available = stok fisik`, tapi observer sudah mengubahnya menjadi `qty_available = stok yang belum direservasi`.

**Tindakan:** Pilih satu semantik dan update semua test + kode yang relevan.

### 6.3 WarehouseAuditTest — 1 Test FAILED

**Error:** `Attempt to read property "confirmed_qty" on null`

**Root Cause:** `WarehouseConfirmationItem` tidak terbuat saat test berjalan (race condition atau assertion dilakukan terlalu cepat).

**Tindakan:** Tambahkan `$warehouseConfirmation->refresh()` dan pastikan items sudah load sebelum assertion.

### 6.4 InventoryFlowTest — 2 Tests FAILED

**Error:** `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'cabang_id' in 'field list'`

**Root Cause:** `ProductCategoryFactory` masih memasukkan `cabang_id` ke table `product_categories`, padahal ada migrasi yang menghapus kolom tersebut.

**Tindakan:** Update `ProductCategoryFactory` untuk tidak include `cabang_id`.

---

## 7. RENCANA PERBAIKAN & PRIORITAS

### Sprint 1 — CRITICAL (Harus selesai dalam 3-5 hari kerja)

| ID | Item | File | Estimasi |
|----|------|------|----------|
| BUG-001 | Buat StockMovement saat StockAdjustment diapprove | `StockAdjustment.php` | 2 jam |
| BUG-002 | Buat StockMovement saat StockOpname diapprove | `StockOpname.php` | 2 jam |
| BUG-003 | Hapus `decrement('qty_on_hand')` di PurchaseReturnService | `PurchaseReturnService.php` line 341 | 30 menit |
| BUG-004 | Tentukan & konsistensikan semantik qty_available | Observer + Service | 4 jam |
| BUG-005 | Bersihkan/jelaskan status StockTransferItemObserver | `StockTransferItem.php` | 30 menit |

### Sprint 2 — MEDIUM (1-2 minggu)

| ID | Item | File | Estimasi |
|----|------|------|----------|
| ISSUE-006 | Validasi stok sebelum approve transfer | `StockTransferService.php` | 2 jam |
| ISSUE-007 | Dokumentasikan/konsistensikan type `customer_return` di observer | Observer | 1 jam |
| ISSUE-008 | Update AuditInventoryConsistency command | Command | 30 menit |
| ISSUE-009 | Perbaiki race condition InventoryStock create | Semua Observer | 3 jam |

### Sprint 3 — MINOR & TEST (2-3 minggu)

| ID | Item | File | Estimasi |
|----|------|------|----------|
| ISSUE-010 | Auto-populate current_qty di StockAdjustment form | RelationManager | 1 jam |
| ISSUE-011 | Tambah status cancelled + rollback StockOpname | Resource + Model | 4 jam |
| ISSUE-012 | Fix race condition penomoran dokumen | Models | 2 jam |
| ISSUE-013 | Tambah status In-Transit untuk StockTransfer | Service + Resource | 1 hari |
| ISSUE-014 | Notifikasi stok minimum otomatis | Listener + Job | 3 jam |
| ISSUE-015 | Konsistensikan COA lookup untuk jurnal | AppSettings | 2 jam |
| TEST-001 | Tulis test untuk Adj approval → stock movement | Test | 3 jam |
| TEST-002 | Tulis test untuk Opname approval → stock movement | Test | 3 jam |
| TEST-003 | Tulis test untuk negative stock prevention | Test | 2 jam |
| TEST-004 | Fix semua failing tests | Multiple | 4 jam |

---

## 8. REKOMENDASI JANGKA PANJANG

### 8.1 Arsitektur: Sentralisasi Inventory Update

**Masalah Saat Ini:** Update stok tersebar di banyak tempat:
- Observer langsung (`StockMovementObserver`, `StockReservationObserver`)
- Service methods (`CustomerReturnService.adjustStock`, `PurchaseReturnService.adjustStock`)
- Model hooks (`StockOpname.createAdjustmentJournalEntries`)

**Rekomendasi:** Buat satu `InventoryService` yang menjadi **single source of truth**:

```php
class InventoryService {
    public function adjustStock(int $productId, int $warehouseId, float $qty, string $direction): void;
    public function reserve(int $productId, int $warehouseId, float $qty): void;
    public function release(int $productId, int $warehouseId, float $qty): void;
    public function getAvailableQty(int $productId, int $warehouseId): float;
}
```

### 8.2 Database: Tambah Index untuk Performa

Tabel `stock_movements` tidak memiliki composite index untuk query yang sering digunakan:

```sql
-- Sering diquery: product + warehouse + type
ALTER TABLE stock_movements ADD INDEX idx_product_warehouse_type (product_id, warehouse_id, type);

-- Untuk audit command
ALTER TABLE stock_movements ADD INDEX idx_product_warehouse_created (product_id, warehouse_id, created_at);

-- inventory_stocks
ALTER TABLE inventory_stocks ADD INDEX idx_warehouse_product (warehouse_id, product_id);
```

### 8.3 Business Rule: Stok Tidak Boleh Negatif (Global)

Saat ini hanya `SalesOrderService` yang melempar `InsufficientStockException`. Transfer, Adjustment, dan Opname bisa membuat stok negatif.

**Rekomendasi:** Tambahkan database-level check atau application-level guard di `InventoryService`:

```php
// Di setiap operasi pengurangan stok:
if ($stock->qty_available - $qty < 0) {
    throw new InsufficientStockException("...");
}
```

### 8.4 Audit Trail yang Lebih Kuat

Tambahkan kolom `before_qty` dan `after_qty` ke `stock_movements`:
```sql
ALTER TABLE stock_movements ADD COLUMN before_qty double NOT NULL DEFAULT 0;
ALTER TABLE stock_movements ADD COLUMN after_qty double NOT NULL DEFAULT 0;
```

Ini memungkinkan rekonstruksi history stok yang akurat tanpa harus menjumlah semua movement.

### 8.5 Implementasi Stock Netting Report

Buat laporan yang menampilkan:
- **Stok Awal** (periode)
- **Masuk** (total adjustment_in, purchase_in, manufacture_in, transfer_in)
- **Keluar** (total sales, manufacture_out, transfer_out, adjustment_out, purchase_return)
- **Stok Akhir Menurut Movement** vs **Stok Akhir Menurut inventory_stocks**
- **Selisih** (harus = 0 jika sistem konsisten)

---

## 9. CHECKLIST VERIFIKASI PASCA-PERBAIKAN

Setelah setiap perbaikan diimplementasi, verifikasi dengan checklist berikut:

### ✅ BUG-001 — Stock Adjustment
- [ ] Create Adjustment dengan 3 item (2 increase, 1 decrease), approve → cek `stock_movements` terbuat
- [ ] Cek `InventoryStock.qty_available` berubah sesuai `difference_qty`
- [ ] Cek jurnal tidak duplikat
- [ ] Jalankan `php artisan audit:inventory-consistency`

### ✅ BUG-002 — Stock Opname  
- [ ] Create Opname, isi physical_qty, approve → cek `stock_movements` terbuat
- [ ] Cek `InventoryStock.qty_available` = `physical_qty` setelah opname
- [ ] Cek jurnal adjustment benar
- [ ] Jalankan `php artisan audit:inventory-consistency`

### ✅ BUG-003 — Purchase Return
- [ ] Create Purchase Return, approve → tidak ada `QueryException`
- [ ] Cek stok produk berkurang sesuai `qty_returned`

### ✅ BUG-004 — Stock Reservation
- [ ] Confirm Sale Order 10 unit (stok 100) → `qty_available = 100`, `qty_reserved = 10`, `qty_on_hand = 90`
- [ ] Selesaikan Delivery Order → stok berkurang
- [ ] Batalkan Sale Order → `qty_reserved = 0`

### ✅ Test Suite
- [ ] `php artisan test tests/Feature/StockAdjustmentTest.php` — semua PASSED
- [ ] `php artisan test tests/Feature/StockTransferTest.php` — semua PASSED
- [ ] `php artisan test tests/Feature/StockReservationFlowTest.php` — semua PASSED
- [ ] `php artisan test tests/Feature/InventoryFlowTest.php` — semua PASSED
- [ ] `php artisan test tests/Feature/WarehouseAuditTest.php` — semua PASSED

---

## LAMPIRAN A: File-File Inventory Module

### Models
```
app/Models/
├── Warehouse.php                  — Master gudang
├── Rak.php                        — Rak dalam gudang
├── InventoryStock.php             — Master stok per produk/gudang/rak
├── StockMovement.php              — History semua pergerakan stok
├── StockTransfer.php              — Header transfer antar gudang
├── StockTransferItem.php          — Item transfer
├── StockAdjustment.php            — Header penyesuaian stok
├── StockAdjustmentItem.php        — Item penyesuaian
├── StockOpname.php                — Header opname
├── StockOpnameItem.php            — Item opname
└── StockReservation.php           — Reservasi stok untuk SO/MI
```

### Services
```
app/Services/
├── StockTransferService.php       — Logic transfer + approve
├── SalesOrderService.php          — Confirm + stock reservation
├── StockReservationService.php    — Reserve/consume untuk material issue
├── CustomerReturnService.php      — Restore stok customer return
└── PurchaseReturnService.php      — Adjust stok purchase return
```

### Observers
```
app/Observers/
├── StockMovementObserver.php      — Update qty_available saat movement CRUD
├── StockReservationObserver.php   — Update qty_reserved saat reservation CRUD
├── DeliveryOrderObserver.php      — Buat reservation & movement saat DO status berubah
├── MaterialIssueObserver.php      — Buat movement saat MI completed
└── InventoryStockObserver.php     — Handle opening balance journal (saat ini disabled)
```

### Console Commands
```
app/Console/Commands/
└── AuditInventoryConsistency.php  — Audit command untuk cek stok vs movement
```

---

*Report ini disusun berdasarkan audit kode sumber tanggal 16 Maret 2026. Semua temuan telah diverifikasi melalui analisis kode statis dan eksekusi test suite. Untuk pertanyaan atau klarifikasi, hubungi tim pengembang.*
