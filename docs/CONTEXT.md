# Duta Tunggal ERP — Dokumen Konteks & Konsistensi Sistem

> Versi: 1.0.0 | Terakhir diperbarui: 2026-05-21

---

## 1. Gambaran Umum Sistem

**Duta Tunggal ERP** adalah sistem Enterprise Resource Planning berbasis web yang dibangun di atas:

| Layer | Teknologi |
|---|---|
| Framework Backend | Laravel 12 (PHP 8.2+) |
| Admin Panel | Filament 3.3 |
| Frontend Interaktif | Livewire / Volt / Flux |
| Database | MySQL (production), SQLite (testing) |
| Styling | Tailwind CSS |
| Testing | PestPHP + Laravel Dusk + Playwright |
| Export | Maatwebsite/Excel, barryvdh/laravel-dompdf |
| Permissions | Spatie Laravel Permission |
| Activity Log | Spatie Laravel Activitylog |
| PDF/Print | DomPDF + Milon Barcode |

Sistem ini dirancang untuk mengelola operasional perusahaan **multi-cabang** (multi-branch), mencakup modul Pembelian, Penjualan, Inventori, Produksi, Keuangan, dan Akuntansi.

---

## 2. Arsitektur & Struktur Direktori

```
app/
├── Console/           # Artisan commands (cron jobs)
├── Enums/             # Enumerasi PHP (PaymentStatus, dll)
├── Events/            # Event Laravel
├── Exports/           # Excel export classes
├── Filament/
│   ├── Actions/       # Custom Filament actions
│   ├── Pages/         # Filament custom pages (Dashboard, Reports)
│   ├── Resources/     # Resource CRUD untuk setiap modul
│   └── Widgets/       # Dashboard widgets
├── Forms/             # Custom Filament form components
├── Helpers/           # Global helper functions (MoneyHelper, dll)
├── Http/              # Controllers, Middleware, Requests
├── Infolists/         # Custom Filament infolist components
├── Listeners/         # Event listeners
├── Livewire/          # Livewire components
├── Models/
│   ├── Scopes/        # Global query scopes (CabangScope)
│   └── Reports/       # Report-specific models
├── Notifications/     # Laravel notifications
├── Observers/         # Model observers (auto journal, stock update)
├── Policies/          # Authorization policies
├── Providers/         # Service providers
├── Rules/             # Custom validation rules
├── Services/          # Business logic services
├── Support/           # Support classes
└── Traits/            # Reusable traits (LogsGlobalActivity, CascadesJournalEntries)

database/
├── migrations/        # Database migrations (chronologis)
├── seeders/           # Database seeders
└── factories/         # Model factories untuk testing
```

---

## 3. Konsep Inti Sistem

### 3.1 Multi-Cabang (Multi-Branch)

Seluruh sistem beroperasi dalam konsep **multi-cabang**. Setiap entitas data memiliki `cabang_id` yang menentukan kepemilikan data.

**Aturan Cabang:**

- Setiap `User` memiliki `cabang_id` (nullable). User tanpa `cabang_id` atau dengan `manage_type = 'all'` dapat mengakses semua data lintas cabang.
- `CabangScope` diterapkan sebagai **Global Scope** pada model-model utama: `SaleOrder`, `JournalEntry`, `Invoice`, `Supplier` (di-nonaktifkan — supplier bersifat global).
- `Product` menggunakan scope kustom `product_cabang` yang memfilter berdasarkan `cabang_id` user, dengan fallback ke produk tanpa `cabang_id`.
- `Cabang` memiliki kode invoice terpisah untuk transaksi pajak dan non-pajak.

```php
// CabangScope — berlaku otomatis jika user punya cabang_id
// User dengan manage_type yang mengandung 'all' → bypass scope
$manageType = $user->manage_type ?? [];
if (is_array($manageType) && in_array('all', $manageType)) {
    return; // akses semua cabang
}
```

### 3.2 Soft Deletes

**Seluruh model utama** menggunakan `SoftDeletes`. Data tidak pernah dihapus permanen kecuali secara eksplisit `forceDelete()`.

**Pola cascade delete yang konsisten:**
```php
// Setiap model menerapkan cascade di booted()
static::deleting(function ($model) {
    if ($model->isForceDeleting()) {
        $model->relatedItems()->forceDelete();
    } else {
        $model->relatedItems()->delete();
    }
});
static::restoring(function ($model) {
    $model->relatedItems()->withTrashed()->restore();
});
```

### 3.3 Observer Pattern

Logika otomatisasi ditangani melalui **Observers** yang terdaftar di `AppServiceProvider::boot()`. Setiap model bisnis utama memiliki observer-nya:

| Observer | Fungsi Utama |
|---|---|
| `PurchaseOrderObserver` | Update status PO, trigger asset creation |
| `PurchaseReceiptObserver` | Update inventory stock, trigger QC |
| `PurchaseReceiptItemObserver` | Update stock movement per item |
| `SaleOrderObserver` | Update status SO, stock reservation |
| `DeliveryOrderObserver` | Sync status DO dengan WC |
| `DeliveryOrderItemObserver` | Update stock on delivery |
| `InvoiceObserver` | Buat Account Receivable/Payable, posting jurnal |
| `VendorPaymentObserver` | Update AP, posting jurnal pembayaran |
| `CustomerReceiptObserver` | Update AR, posting jurnal penerimaan |
| `StockMovementObserver` | Update `InventoryStock` otomatis |
| `QualityControlObserver` | Update stock setelah QC lulus/gagal |
| `JournalEntryObserver` | Validasi periode akuntansi terbuka |
| `AssetObserver` | Hitung depresiasi aset |
| `ManufacturingOrderObserver` | Update status MO, trigger MaterialIssue |
| `MaterialIssueObserver` | Update stock bahan baku |

### 3.4 Trait Standar

Semua model bisnis menggunakan trait berikut:

```php
use SoftDeletes, HasFactory, LogsGlobalActivity;
```

- **`LogsGlobalActivity`** — mencatat semua aktivitas CRUD ke activity log (Spatie).
- **`CascadesJournalEntries`** — digunakan oleh model yang memiliki jurnal (PurchaseOrder, QualityControl). Memastikan jurnal di-cascade saat delete/restore.

---

## 4. Modul-Modul Sistem

### 4.1 Modul Pembelian (Procurement)

**Alur Utama:**
```
OrderRequest (permintaan pembelian)
  → PurchaseOrder (PO)
    → PurchaseReceipt (penerimaan barang)
      → QualityControl (QC barang masuk)
        → [LULUS] InventoryStock bertambah
        → [GAGAL] PurchaseReturn (retur ke supplier)
    → Invoice/PurchaseInvoice (faktur pembelian)
      → VendorPayment (pembayaran ke supplier)
        → AccountPayable (hutang dagang)
```

**Status PurchaseOrder:**
`draft` → `approved` → `partially_received` → `completed` → `closed`
(juga: `request_close`)

**Aturan PO:**
- Setiap PO memiliki `po_number` yang unik.
- PO bisa untuk **barang biasa** atau **aset** (`is_asset = true`). Jika aset, record `Asset` dibuat otomatis saat PO completed.
- PO mendukung **multi-currency** via tabel `purchase_order_currencies`.
- Field `top_type` mengatur tipe term of payment.
- Field `tempo_hutang` (hari) mengatur jangka tempo hutang.

### 4.2 Modul Penjualan (Sales)

**Alur Utama:**
```
Quotation (penawaran harga)
  → SaleOrder (SO)
    → WarehouseConfirmation (konfirmasi gudang)
      → DeliveryOrder (DO pengiriman)
        → DeliverySchedule (jadwal kirim)
          → SuratJalan (surat jalan)
    → Invoice/SalesInvoice (faktur penjualan)
      → CustomerReceipt (penerimaan pembayaran)
        → AccountReceivable (piutang dagang)
```

**Status SaleOrder:**
`draft` → `request_approve` → `approved` → `confirmed` → `completed`
(juga: `request_close`, `closed`, `canceled`, `reject`)

**Aturan SO:**
- SO selalu terhubung ke `Customer` dan opsional ke `Quotation`.
- SO mendukung multi-currency (`currency_id`, `exchange_rate`).
- `tipe_pengiriman`: `Ambil Sendiri` atau `Kirim Langsung`.
- Field `tempo_pembayaran` mengatur jangka piutang.
- **Stock validation** — SO dicek apakah stok cukup sebelum approved.
- **Warehouse Allocation** — item SO bisa dialokasikan ke gudang berbeda via `SaleOrderItemWarehouseAllocation`.

### 4.3 Modul Inventori (Inventory)

**Model Utama:**
- `InventoryStock` — stok aktual per produk per gudang (dan rak).
- `StockMovement` — log semua pergerakan stok (masuk/keluar).
- `StockReservation` — reservasi stok untuk SO yang belum dikirim.
- `StockTransfer` — transfer stok antar gudang.
- `StockAdjustment` — penyesuaian stok manual.
- `StockOpname` — stock opname/stocktaking.

**Aturan Stok:**
- `InventoryStock.freeQtyFor($productId, $warehouseId)` — stok tersedia (total - reserved).
- Semua perubahan stok dicatat di `StockMovement` dengan referensi ke model sumber (polymorphic `from_model`).
- `StockMovement` dapat berasal dari: SO, PO, DO, PurchaseReceipt, StockTransfer, ManufacturingOrder, MaterialIssue, StockAdjustment, QualityControl, PurchaseReturn.

### 4.4 Modul Produksi (Manufacturing)

**Alur Utama:**
```
BillOfMaterial (BOM — resep produk)
  → ProductionPlan (rencana produksi)
    → ManufacturingOrder (MO)
      → MaterialIssue (pengeluaran bahan baku)
        → WarehouseConfirmation (konfirmasi gudang)
      → Production (proses produksi)
        → QualityControl (QC produk jadi)
```

**Aturan Produksi:**
- `BillOfMaterial` mendefinisikan komponen dan kuantitas yang dibutuhkan.
- `MaterialIssue` mengkonsumsi stok bahan baku saat disetujui melalui WC.
- Produk yang dihasilkan masuk ke stok via `QualityControl`.

### 4.5 Modul Keuangan & Akuntansi (Finance & Accounting)

**Model Utama:**
- `ChartOfAccount` (COA) — daftar akun dengan tipe: `Asset`, `Liability`, `Equity`, `Revenue`, `Expense`, `Contra Asset`.
- `JournalEntry` — seluruh transaksi keuangan diposting sebagai jurnal.
- `AccountPayable` — hutang dagang ke supplier.
- `AccountReceivable` — piutang dagang dari customer.
- `CashBankAccount` — rekening kas/bank.
- `CashBankTransaction` — transaksi kas/bank.
- `BankReconciliation` — rekonsiliasi bank.
- `AccountingPeriod` — periode akuntansi (bulan/tahun buku).
- `Asset` & `AssetDepreciation` — aset tetap dan depresiasi.
- `Deposit` — deposit/uang muka supplier/customer.
- `VoucherRequest` — permohonan biaya.

**Laporan Keuangan:**
- Balance Sheet (Neraca)
- Income Statement (Laba Rugi)
- Trial Balance (Neraca Saldo)
- Profit & Loss per Divisi/Cabang
- Ageing Schedule (AR/AP)

**Aturan Akuntansi:**
- Jurnal hanya bisa dibuat dalam **periode akuntansi yang terbuka**. `AccountingPeriod::ensureDateIsOpen()` dipanggil di setiap create/update/delete JournalEntry.
- `LedgerPostingService` menangani semua posting jurnal otomatis.
- COA terhubung ke produk untuk akun inventori, penjualan, COGS, dll.

### 4.6 Modul Aset Tetap (Fixed Assets)

- `Asset` — aset tetap perusahaan, terhubung ke PO (`is_asset = true`).
- `AssetDepreciation` — jadwal dan nilai depresiasi per periode.
- `AssetTransfer` — transfer aset antar cabang/lokasi.
- `AssetDisposal` — pelepasan/penjualan aset.

### 4.7 Warehouse Confirmation (WC)

`WarehouseConfirmation` adalah entitas **polimorfik** yang digunakan untuk konfirmasi gudang dari berbagai sumber:

| Source | `confirmable_type` |
|---|---|
| Sales Order | `App\Models\SaleOrder` |
| Manufacturing Order | `App\Models\ManufacturingOrder` |
| Material Issue | `App\Models\MaterialIssue` |
| Delivery Order | `App\Models\DeliveryOrder` |

**Status WC:** `pending` → `confirmed` / `rejected` / `partial_confirmed`

---

## 5. Sistem Pajak

### Tipe Pajak (`tipe_pajak`)

Seluruh sistem menggunakan field `tipe_pajak` dengan nilai **lowercase**:

| Nilai | Deskripsi |
|---|---|
| `ppn` | PPN standar (biasanya 11%) |
| `non_ppn` | Tanpa PPN |
| `ppn_bm` | PPN + PPnBM |
| `pph22` | PPh Pasal 22 (impor) |

**Aturan Konsistensi Pajak:**
- Semua kolom `tipe_pajak` **wajib lowercase** (migration 2026-05-11 menormalisasi ini).
- `TaxService::normalizeType()` digunakan untuk normalisasi nilai pajak.
- Invoice memiliki `ppn_rate` (persentase) dan `dpp` (dasar pengenaan pajak).
- Invoice impor mendukung `pph22_amount` dan `bea_masuk_amount`.

---

## 6. Sistem Kode & Nomor Dokumen

Setiap transaksi memiliki nomor dokumen unik:

| Entitas | Field Nomor | Contoh Format |
|---|---|---|
| Purchase Order | `po_number` | — |
| Sale Order | `so_number` | — |
| Delivery Order | `do_number` | — |
| Invoice (Sales) | `invoice_number` | — |
| Invoice (Purchase) | `invoice_number` | — |
| Quality Control | `qc_number` | — |
| Stock Transfer | `transfer_number` | — |
| Stock Adjustment | `adjustment_number` | — |
| Manufacturing Order | `mo_number` | — |
| Material Issue | `issue_number` | — |
| Customer Receipt | `receipt_number` | — |
| Vendor Payment | — | — |
| Voucher Request | — | via `VoucherNumberSequence` |
| User | `kode_user` | `USR-0001` |
| Deposit | — | via `DepositNumberGenerator` |

`VoucherNumberSequence` digunakan untuk menghasilkan nomor voucher yang berurutan dan tidak duplikat.

---

## 7. Sistem Mata Uang (Multi-Currency)

- Tabel `currencies` menyimpan kurs mata uang dengan field `to_rupiah` (nilai konversi ke IDR).
- `JournalEntry` menyimpan: `currency_id`, `exchange_rate`, `amount_original_currency`.
- Saat membuat jurnal, sistem otomatis meresolve currency dari source dokumen:
  1. Currency eksplisit di source
  2. Currency dari PurchaseOrderCurrency
  3. Currency dari Invoice → PO
  4. Currency dari VendorPayment → Invoice → PO
  5. Fallback ke **IDR**
- Sales Invoice selalu diposting dalam **IDR** di jurnal, meski SO menggunakan foreign currency.

---

## 8. Aturan Pemrograman & Konsistensi Kode

### 8.1 Model Layer

- **Selalu gunakan `->withDefault()`** pada `belongsTo` relationships untuk menghindari null pointer.
- **Hindari menambahkan global scope ganda** — cek AppServiceProvider dan model sebelum menambahkan observer/scope baru.
- **Field money** disimpan sebagai `decimal(15,2)` untuk transaksi umum, dan beberapa field sensitif menggunakan `decimal(20,8)` untuk presisi tinggi.
- Accessor `getTotalAmountAttribute()` pada PurchaseOrder mengembalikan angka dengan 2 desimal sebagai string.

### 8.2 Service Layer

Seluruh logika bisnis kompleks **wajib** berada di dalam `app/Services/`. Resource Filament hanya boleh memanggil service, bukan mengandung logika bisnis langsung.

**Services penting:**
- `LedgerPostingService` — posting semua jurnal akuntansi
- `QualityControlService` — proses QC lengkap
- `PurchaseReceiptService` — penerimaan barang + update stok
- `SalesOrderService` — alur penjualan
- `DeliveryOrderService` — proses pengiriman
- `StockReservationService` — manajemen reservasi stok
- `ManufacturingJournalService` — jurnal manufaktur
- `LegacyTransactionArchiveService` — migrasi data legacy

### 8.3 Format Uang (Money Format)

- **Tampilan:** Gunakan `MoneyHelper::rupiah($value)` atau macro `->rupiah()` untuk Filament TextColumn/TextEntry.
- **Input Form:** Gunakan macro `->indonesianMoney()` pada `TextInput` untuk format Rupiah dengan separator titik ribuan dan koma desimal.
- **Locale:** `id` (Indonesia) — separator ribuan `.`, desimal `,`.
- **Parsing:** `MoneyHelper::safeParse($value)` untuk konversi string format Indonesia ke float.
- **Default Filament:** `Table::$defaultCurrency = 'IDR'` dan `Table::$defaultNumberLocale = 'id'` diset global.

### 8.4 Relasi Polimorfik

Sistem banyak menggunakan relasi polimorfik (`morphTo`, `morphMany`, `morphOne`) untuk fleksibilitas:

| Relasi | Contoh Penggunaan |
|---|---|
| `JournalEntry.source` | Sumber jurnal (PO, Invoice, dll) |
| `Invoice.fromModel` | Sumber invoice (SO, PO, DO) |
| `StockMovement.fromModel` | Sumber pergerakan stok |
| `WarehouseConfirmation.confirmable` | Sumber konfirmasi gudang |
| `Deposit.from_model` | Sumber deposit |
| `QualityControl.fromModel` | Sumber item QC |

### 8.5 Filament Resources

Setiap modul memiliki `Resource` di `app/Filament/Resources/`. Pola standar:

- Resource besar (OrderRequestResource, PurchaseOrderResource) dapat mencapai 100k+ byte karena kompleksitas form.
- Setiap resource memiliki sub-direktori untuk Pages: `ListXxx`, `CreateXxx`, `EditXxx`, `ViewXxx`.
- Custom Actions berada di `app/Filament/Actions/`.

### 8.6 Authorization (Permissions)

Menggunakan **Spatie Laravel Permission** dengan model `Role` dan `Permission`.

- Semua pengguna dapat mengakses panel Filament (`canAccessPanel()` return `true`).
- Izin granular diimplementasikan via Policies di `app/Policies/`.
- `User::hasPermissionTo()` di-override untuk menangkap `PermissionDoesNotExist` exception dengan graceful fallback `false`.

---

## 9. Konvensi Database

### 9.1 Kolom Standar

Hampir semua tabel mengikuti konvensi:

```sql
id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
cabang_id       BIGINT UNSIGNED NULLABLE FK(cabangs.id)
created_at      TIMESTAMP
updated_at      TIMESTAMP
deleted_at      TIMESTAMP NULLABLE  -- soft delete
```

### 9.2 Kolom Audit

Transaksi penting memiliki kolom audit:

```sql
created_by      BIGINT UNSIGNED NULLABLE FK(users.id)
approved_by     BIGINT UNSIGNED NULLABLE FK(users.id)
approved_at / date_approved
completed_by    BIGINT UNSIGNED NULLABLE FK(users.id)
completed_at
closed_by       BIGINT UNSIGNED NULLABLE FK(users.id)
closed_at
```

### 9.3 Naming Convention

- Tabel: `snake_case` plural (`purchase_orders`, `sale_order_items`)
- Kolom foreign key: `{model}_id` (`supplier_id`, `cabang_id`)
- Kolom status: selalu `status` dengan nilai string lowercase
- Tabel pivot: `{model1}_{model2}` alphabetical (`product_supplier`, `delivery_sales_orders`)
- Kolom Indonesia boleh dalam Bahasa Indonesia (`tipe_pajak`, `tempo_hutang`, `nama`, `perusahaan`)

### 9.4 Index Performa

Migration `2026-03-10_add_created_at_indexes_for_sorting_performance` menambahkan index pada kolom `created_at` untuk tabel-tabel besar, karena Filament default mengurutkan berdasarkan `created_at DESC`.

---

## 10. Sistem Legacy & Migrasi Data

Sistem memiliki fitur **rehydration data legacy** dari sistem ERP lama:

- `LegacyTransactionArchive` — arsip transaksi lama yang di-import
- `LegacyInventoryMigrationService` — migrasi data inventori
- `LegacySalesOrderRehydrationService` — rehydrasi SO lama
- `LegacyPurchaseOrderRehydrationService` — rehydrasi PO lama
- `LegacyQuotationRehydrationService` — rehydrasi quotation lama
- `LegacyOrderRequestRehydrationService` — rehydrasi order request lama

Setiap model utama memiliki field `legacy_*` untuk menyimpan referensi dari sistem lama.

---

## 11. Testing

### Stack Testing:
- **Unit/Feature Tests:** PestPHP (`tests/`)
- **Browser Tests:** Laravel Dusk (`tests/Browser/`)
- **E2E Tests:** Playwright (`playwright/`)

### Environment Testing:
- `.env.testing` menggunakan SQLite in-memory
- `.env.dusk.local` untuk Dusk browser testing

---

## 12. Aturan Penting & Gotcha

1. **Jangan registrasi observer ganda.** Observer hanya didaftarkan di `AppServiceProvider::boot()`. Jangan tambahkan `static::observe()` di dalam model itu sendiri (lihat komentar di `Invoice.php`).

2. **`CabangScope` pada Supplier dinonaktifkan** — Supplier bersifat global dan dapat melayani semua cabang. Jangan tambahkan scope kembali kecuali ada kebutuhan spesifik.

3. **Periode Akuntansi harus terbuka** sebelum membuat/mengubah/menghapus `JournalEntry`. Sistem akan throw exception jika periode tertutup.

4. **Format pajak lowercase** — selalu gunakan `ppn`, `non_ppn`, `ppn_bm`, bukan uppercase. Migration normalisasi sudah ada di `2026-05-11`.

5. **`PurchaseOrder::saving()`** memiliki defensive check yang menghapus kolom tidak dikenal dari schema untuk menghindari SQL error dengan data legacy.

6. **`WarehouseConfirmation` harus menggunakan polymorphic** — jangan menggunakan `sale_order_id` langsung, gunakan `confirmable_type` + `confirmable_id`.

7. **Stock movement selalu lewat observer** — jangan update `InventoryStock` secara manual. Selalu buat `StockMovement` dan biarkan `StockMovementObserver` yang mengupdate stok.

8. **Money parsing** — selalu gunakan `MoneyHelper::safeParse()` untuk parsing angka dari form input (format Indonesia menggunakan koma sebagai desimal).

9. **Product COA resolution** menggunakan fallback hierarki: COA eksplisit di produk → COA default dari config `coa.product` → null. Gunakan method `resolve*CoaOrDefault()`.

10. **`manage_type`** pada User disimpan sebagai comma-separated string di DB, tapi diakses sebagai array via accessor. Nilai `'all'` berarti bypass semua cabang scope.

---

## 13. Referensi File Kunci

| File | Fungsi |
|---|---|
| `app/Providers/AppServiceProvider.php` | Registrasi semua observer & Filament macros |
| `app/Models/Scopes/CabangScope.php` | Global scope untuk filter multi-cabang |
| `app/Services/LedgerPostingService.php` | Semua logika posting jurnal akuntansi |
| `app/Services/QualityControlService.php` | Logika QC lengkap (purchase & manufacture) |
| `app/Services/PurchaseReceiptService.php` | Penerimaan barang + update inventori |
| `app/Helpers/helpers.php` | Global helper functions |
| `app/Helpers/MoneyHelper.php` | Format & parse nilai uang |
| `config/coa.php` | Default COA mapping untuk produk |
| `config/asset.php` | Default COA untuk aset tetap |
| `database/migrations/` | Semua perubahan skema database (chronologis) |
