# Pemahaman Sistem Duta Tunggal ERP

> Dokumen ini adalah hasil pembacaan langsung terhadap kode sumber (bukan ringkasan dokumen lain).
> Tanggal analisis: **19 September 2026** · Branch: `chore/cleanup-artifacts` · Commit HEAD: `3b2e467`
>
> Catatan: `docs/CONTEXT.md` adalah dokumen *aturan & konvensi* (versi 1.0.0, 21 Mei 2026). Dokumen ini
> adalah dokumen *pemahaman & peta sistem* pada kondisi kode saat ini, termasuk beberapa titik di mana
> kondisi kode sudah bergerak dari apa yang tertulis di `CONTEXT.md` (lihat §13).

---

## Daftar Isi

1. [Ringkasan Eksekutif](#1-ringkasan-eksekutif)
2. [Tumpukan Teknologi (Terverifikasi)](#2-tumpukan-teknologi-terverifikasi)
3. [Arsitektur Aplikasi](#3-arsitektur-aplikasi)
4. [Skala & Peta Domain](#4-skala--peta-domain)
5. [Struktur Navigasi Panel Admin](#5-struktur-navigasi-panel-admin)
6. [Alur Bisnis Per Modul](#6-alur-bisnis-per-modul)
7. [Mesin Lintas-Modul (Cross-Cutting Engines)](#7-mesin-lintas-modul-cross-cutting-engines)
8. [Kontrol Internal & Otorisasi](#8-kontrol-internal--otorisasi)
9. [Lapisan Pelaporan](#9-lapisan-pelaporan)
10. [Subsistem Migrasi Data Legacy](#10-subsistem-migrasi-data-legacy)
11. [Testing & Keamanan Basis Data Uji](#11-testing--keamanan-basis-data-uji)
12. [Penilaian: Kekuatan & Risiko](#12-penilaian-kekuatan--risiko)
13. [Temuan: Selisih antara Dokumentasi dan Kode](#13-temuan-selisih-antara-dokumentasi-dan-kode)
14. [Panduan Orientasi: "Mau Ubah X, Lihat Di Mana?"](#14-panduan-orientasi-mau-ubah-x-lihat-di-mana)

---

## 1. Ringkasan Eksekutif

**Duta Tunggal ERP** adalah ERP *full-cycle* berbasis web untuk perusahaan dagang + manufaktur
multi-cabang di Indonesia. Cakupannya menyeluruh dari hulu ke hilir:

```
Pengadaan → Penerimaan & QC → Inventori → Produksi → Penjualan → Pengiriman
        → Penagihan → Pembayaran → Buku Besar → Laporan Keuangan → Aset Tetap
```

Tiga karakter yang paling menentukan bentuk sistem ini:

1. **Accounting-first.** Hampir setiap peristiwa operasional (barang lulus QC, DO terkirim,
   invoice dibuat, pembayaran diterima) secara otomatis melahirkan `JournalEntry`. Buku besar bukan
   modul tambahan — ia adalah **hilir wajib** dari seluruh transaksi. Konsekuensinya: perubahan di
   modul operasional manapun berpotensi menggeser laporan keuangan.

2. **Observer-driven automation.** Otomasi tidak dipicu dari controller/resource, melainkan dari
   **30 Model Observer**. Ini membuat logika terpasang konsisten di semua jalur masuk (UI Filament,
   API, Artisan command, seeder), tetapi juga berarti alur eksekusi sebenarnya **tidak terlihat**
   dari membaca resource saja.

3. **Arsitektur hibrida Filament + React.** Empat form transaksi terberat (Order Request, Purchase
   Order, Quotation, Sales Order) sudah **dikeluarkan dari Livewire repeater** dan diganti React
   island yang berkomunikasi lewat REST API v1. Sisanya (±60 modul) tetap Filament murni.

Skala kode: **104 model**, **117 tabel**, **64 Filament Resource**, **69 Service**, **30 Observer**,
**76 Policy**, **~344 file test**.

---

## 2. Tumpukan Teknologi (Terverifikasi)

| Lapisan | Teknologi | Catatan |
|---|---|---|
| Runtime | PHP **8.2+** | |
| Framework | **Laravel 12** | |
| Admin Panel | **Filament 3.3** | panel tunggal `admin` di path `/admin` |
| UI reaktif | Livewire + Volt + Flux | dipakai halaman auth/settings & seluruh Filament |
| UI performa tinggi | **React 19 + TypeScript** (Vite) | 4 island form transaksi |
| Prototipe terpisah | **Next.js 14** di `frontend/` | lihat catatan di §3.4 |
| Database | **MySQL** (`duta_tunggal`) | testing: MySQL `duta_tunggal_test` |
| Styling | Tailwind CSS 3.4 | + `@tailwindcss/forms`, `typography` |
| Izin akses | `spatie/laravel-permission` ^6.19 | |
| Audit trail | `spatie/laravel-activitylog` | via trait `LogsGlobalActivity` |
| Ekspor | `maatwebsite/excel` ^3.1 | 19 kelas Export |
| PDF & Barcode | `barryvdh/laravel-dompdf`, `milon/barcode` | |
| Tanda tangan | `saade/filament-autograph` | komponen `SignaturePad` |
| Test | **PestPHP 3.8**, Laravel Dusk 8.3, Playwright 1.58 | |
| Queue / Cache / Session | driver `database` (semua) | tanpa Redis |
| Deployment | Docker (`Dockerfile`, `docker-compose.duta-tunggal.yml`) | |

---

## 3. Arsitektur Aplikasi

### 3.1 Pembagian Lapisan

```
┌──────────────────────────────────────────────────────────────────┐
│  PRESENTASI                                                      │
│  Filament Resources (64) · Hub Pages (36) · Widgets (33)         │
│  React Islands (4) · Blade PDF views · Livewire Auth/Settings    │
└────────────────────────────┬─────────────────────────────────────┘
                             │  resource HANYA memanggil service
┌────────────────────────────▼─────────────────────────────────────┐
│  BISNIS                                                          │
│  Services (69) · Support helpers (14) · Enums · Rules            │
│  → LedgerPostingService, QualityControlService, dst.             │
└────────────────────────────┬─────────────────────────────────────┘
                             │
┌────────────────────────────▼─────────────────────────────────────┐
│  OTOMASI REAKTIF  ← inti sistem                                  │
│  Observers (30) · Traits (CascadesJournalEntries, …)             │
│  Memicu: jurnal, mutasi stok, AR/AP, status kaskade             │
└────────────────────────────┬─────────────────────────────────────┘
                             │
┌────────────────────────────▼─────────────────────────────────────┐
│  DATA                                                            │
│  Models (104) · CabangScope · SoftDeletes cascade · 117 tabel    │
└──────────────────────────────────────────────────────────────────┘
```

Aturan yang dipegang konsisten: **Filament Resource tidak boleh memuat logika bisnis** — ia
memanggil Service. Yang tidak selalu terlihat: banyak efek samping justru terjadi di **Observer**,
bukan di Service yang dipanggil resource.

### 3.2 Arsitektur Hibrida React (bagian paling khas)

Masalah yang diselesaikan: Livewire repeater pada form transaksi 10–50+ baris membuat setiap
ketikan memicu roundtrip AJAX dengan serialisasi ribuan node DOM → lag 5–15 detik.

Solusi yang dijalankan (didokumentasikan lengkap di `SLMJ_HIGH_PERFORMANCE_FORMS_GUIDE.md`):

```
Filament Admin Shell  (auth, permission, sidebar, breadcrumb)
        │ mount blade: <div id="{module}-next-app" data-edit-id="...">
        ▼
React Island (client-side, 0 ms input latency)
   · kalkulasi live subtotal/diskon/DPP/PPN di browser
   · SearchableSelect (combobox) menggantikan Select Livewire
   · komponen Grand Total baku akuntansi
        │ axios JSON
        ▼
API v1  (routes/api.php)
   GET  /dependencies     → pre-cache master data sekali muat
   GET  /generate-number  → penomoran real-time
   POST /  ·  PUT /{id}   → CRUD atomik dalam DB transaction
```

Modul yang sudah dimigrasi (masing-masing punya `index.tsx`, `*App.tsx`, `*HeaderForm.tsx`,
`*ItemTable.tsx`, `*GrandTotal.tsx`, `*FloatingSummary.tsx`, `*Toolbar.tsx`, `calculations.ts`,
`types.ts` di `resources/js/components/`):

| Modul | Mount point | API Controller |
|---|---|---|
| Order Request | `#order-request-next-app` | `OrderRequestApiController` |
| Purchase Order | `#purchase-order-next-app` | `PurchaseOrderApiController` |
| Quotation | `#quotation-next-app` | `QuotationApiController` |
| Sales Order | `#sale-order-next-app` | `SaleOrderApiController` |

Titik pasang: Blade override halaman `create-*` dan `edit-*` di
`resources/views/filament/resources/{modul}-resource/pages/`.

**Implikasi penting:** untuk 4 modul ini, **logika kalkulasi ada di dua tempat** — TypeScript
(`calculations.ts`, untuk preview) dan PHP (Service/Observer, untuk penyimpanan otoritatif). Rumus
keduanya harus dijaga sinkron; TypeScript tidak boleh dianggap sumber kebenaran.

### 3.3 Halaman "Hub"

Navigasi tidak langsung menabrak 64 resource. Ada **lapisan Hub Page** sebagai pintu masuk per
domain: `DashboardHubPage`, `SalesHubPage`, `PurchaseHubPage`, `DeliveryHubPage`,
`AccountingHubPage`, `InventoryHubPage`, `WarehouseHubPage`, `ManufacturingHubPage`,
`MasterDataHubPage`, `PaymentHubPage`, `FinanceSalesHubPage`, `FinancePurchaseHubPage`,
`FinanceReportHubPage`, `AssetManagementHubPage`, `UserRolesManagementHubPage`.

Catatan teknis: `discoverPages()` **dimatikan** di `AdminPanelProvider` (konflik komponen Livewire)
— setiap page baru **wajib didaftarkan manual** di array `->pages([...])`. Ini jebakan umum:
membuat file page baru saja tidak akan memunculkannya.

### 3.4 Folder `frontend/` (Next.js) — status tidak aktif di runtime

`frontend/` berisi aplikasi Next.js 14 terpisah dengan React 18 dan hanya komponen Order Request
(`OrderRequestHeaderForm`, `OrderRequestItemCard`, `useOrderRequestForm`, `calculations.ts`).
Ini **bukan** bagian dari aplikasi yang dijalankan: build produksi memakai Vite di root
(`resources/js/app.js`), dan versi React-nya pun berbeda (root: React 19). Kesimpulan saya:
`frontend/` adalah **prototipe/laboratorium awal** dari arsitektur React island yang kemudian
dipindahkan ke `resources/js/components/`. Aman diperlakukan sebagai artefak historis.

---

## 4. Skala & Peta Domain

**117 tabel** (lihat `database/schema/mysql-schema.sql`), **104 model**. Pengelompokan domain:

### Master Data (18)
`Product`, `ProductCategory`, `ProductUnitConversion`, `ProductStandardCost`, `UnitOfMeasure`,
`Supplier`, `Customer`, `Warehouse`, `Rak`, `Cabang`, `Driver`, `Vehicle`, `Currency`,
`ChartOfAccount`, `TaxSetting`, `User`, `Role`, `Permission`

### Pengadaan (16)
`OrderRequest` + `Item`, `PurchaseOrder` + `Item`/`Currency`/`Biaya`, `PurchaseReceipt` +
`Item`/`ItemNominal`/`Photo`/`ItemPhoto`/`Biaya`, `PurchaseReturn` + `Item`, `QualityControl`

### Penjualan (12)
`Quotation` + `Item`, `SaleOrder` + `Item`/`ItemWarehouseAllocation`, `Invoice` + `Item`,
`OtherSale`, `CustomerReturn` + `Item`, `ReturnProduct` + `Item`

### Pengiriman (8)
`DeliveryOrder` + `Item`/`ItemWarehouseSource`/`Log`/`ApprovalLog`, `DeliverySalesOrder`,
`DeliverySchedule`, `SuratJalan` + `SuratJalanDeliveryOrder`

### Inventori (12)
`InventoryStock`, `StockMovement`, `StockReservation`, `StockTransfer` + `Item`,
`StockAdjustment` + `Item`, `StockOpname` + `Item`, `WarehouseConfirmation` +
`Item`/`Warehouse`

### Produksi (9)
`BillOfMaterial` + `Item`, `ProductionPlan`, `ManufacturingOrder`, `Production`,
`ProductionCostEntry`, `MaterialIssue` + `Item`, `CostVariance`

### Keuangan & Akuntansi (21)
`JournalEntry`, `AccountingPeriod`, `AccountPayable`, `AccountReceivable`, `AgeingSchedule`,
`CashBankAccount`, `CashBankTransaction` + `Detail`, `CashBankTransfer`, `BankReconciliation`,
`VendorPayment` + `Detail`, `CustomerReceipt` + `Item`, `PaymentRequest`, `Deposit` + `Log`,
`VoucherRequest`, `VoucherNumberSequence`, `IncomeStatementItem`

### Aset Tetap (4)
`Asset`, `AssetDepreciation`, `AssetTransfer`, `AssetDisposal`

### Legacy & Utilitas (4)
`LegacyTransactionArchive`, `AppSetting`, `TestModel` *(stub, tidak dipakai produksi)*, model
`Reports/*` (7 model konfigurasi pemetaan laporan: Cash Flow & HPP)

---

## 5. Struktur Navigasi Panel Admin

Panel: **satu panel** (`admin`), path `/admin`, tema **Light dipaksa** (`darkMode(false)`),
warna primer Blue, sidebar collapsible, notifikasi database dengan polling 30 detik.

Grup navigasi terdaftar di provider: Dashboard, Penjualan, Pembelian, Pengiriman, Akuntansi,
Inventory, Master Data, Manajemen User dan Role, Manufaktur.

Distribusi resource nyata per `$navigationGroup`:

| Grup | Resource |
|---|---|
| **Master Data** (13) | Product, ProductCategory, Supplier, Customer, Warehouse, Cabang, Driver, Vehicle, Rak, UnitOfMeasure, Currency, ChartOfAccount, TaxSetting |
| **Pembelian** (6) | OrderRequest, PurchaseOrder, QualityControlPurchase, PurchaseReceipt, PurchaseReturn, PurchaseReceiptItem |
| **Penjualan** (2) | Quotation, SaleOrder |
| **Pengiriman** (4) | DeliveryOrder, SuratJalan, DeliverySchedule, DeliveryOrderApprovalLog |
| **Gudang** (7) | StockTransfer, StockAdjustment, InventoryStock, WarehouseConfirmation, StockMovement, StockOpname, ReturnProduct |
| **Manufaktur** (6) | BillOfMaterial, ProductionPlan, MaterialIssue, ManufacturingOrder, Production, QualityControlManufacture |
| **Akuntansi Keuangan** (4) | JournalEntry, BankReconciliation, AgeingSchedule, VoucherRequest |
| **Keuangan Penjualan** (3) | SalesInvoice, AccountReceivable, OtherSale |
| **Keuangan Pembelian** (2) | AccountPayable, PurchaseInvoice |
| **Pembayaran Keuangan** (6) | PaymentRequest, VendorPayment, CustomerReceipt, CashBankTransfer, Deposit, CashBankTransaction |
| **Finance** (3) | Invoice, CashBankAccount, DepositAdjustment |
| **Asset Management** (3) | Asset, AssetTransfer, AssetDisposal |
| **User Roles Management** (3) | User, Role, Permission |
| **Retur Pelanggan** (1) | CustomerReturn |
| **Legacy Migration** (1) | LegacyTransactionArchive |

⚠️ **Inkonsistensi nyata:** nama grup pada resource **tidak seluruhnya cocok** dengan yang
dideklarasikan di `AdminPanelProvider`. Provider mendaftarkan `'Akuntansi'`, `'Inventory'`, dan
`'Manajemen User dan Role'`; resource memakai `'Akuntansi Keuangan'`, `'Gudang'`, dan
`'User Roles Management'`. Grup yang tidak terdaftar tetap muncul di sidebar, tapi **tanpa ikon
dan di luar urutan yang diinginkan**. Ada juga tiga grup finansial yang terpisah-pisah
(`Finance`, `Keuangan Penjualan`, `Keuangan Pembelian`, `Pembayaran Keuangan`) yang secara
konseptual saling tumpang tindih.

---

## 6. Alur Bisnis Per Modul

Status di bawah ini dikutip langsung dari `$fillable`/konstanta model, bukan asumsi.

### 6.1 Pengadaan (Procurement)

```
OrderRequest                     status: draft → approved → rejected → closed
  │   (approval per-item: OrderRequestItem.status / approval_status)
  ▼
PurchaseOrder                    status: draft → approved → partially_received
  │                                      → completed → closed  (+ request_close)
  │   flag: is_asset, is_import · multi-currency via purchase_order_currencies
  ▼
PurchaseReceipt (GRN)            status: draft → partial → completed
  │
  ▼
QualityControl                   qty: quantity_received / passed_quantity / rejected_quantity
  ├─ LULUS  → StockMovement masuk → InventoryStock naik → jurnal Persediaan
  └─ GAGAL  → PurchaseReturn (retur ke supplier)
  │
  ▼
Invoice (Purchase)               status: draft → sent → partially_paid → paid → overdue
  │   from_model: PurchaseOrder ATAU PurchaseReceipt
  ▼
PaymentRequest                   status: draft → pending → approved → partial → paid → rejected
  ▼
VendorPayment                    status: Draft → Partial → Paid
  ▼
AccountPayable  →  jurnal pelunasan utang
```

**Titik kontrol kuantitas.** `App\Support\OrderRequestQuantityLock` adalah penjaga berlapis yang
mencegah *over-ordering* dan *over-receiving*:
- Total qty PO aktif tidak boleh melebihi qty OR (`validatePurchaseOrderItem`, `validatePurchaseOrderApproval`).
- `qty_accepted` ≤ `qty_received`; `qty_accepted + qty_rejected` ≤ `qty_received`.
- Qty penerimaan dibatasi oleh sisa PO **dan** sisa OR sekaligus (diambil `min()` dari keduanya).
- Item OR ber-status `rejected` dihitung **nol** sisa — tidak ikut membuka kuota PO/penerimaan.
- Status PO yang dianggap "tidak aktif" (tidak mengunci kuota): `draft`, `closed`, `cancelled`,
  `canceled`, `rejected`.

**Pengakuan persediaan terjadi di QC, bukan di invoice.** Ini keputusan desain penting:
`LedgerPostingService::postInvoice()` secara sengaja **melewati** debit persediaan untuk invoice
pembelian, karena persediaan sudah diakui saat QC lulus. Invoice pembelian hanya mengurus
PPN Masukan, Utang Dagang, dan penutup akun "Pembelian Belum Tertagih" (`2100.10`).

Konfigurasi yang relevan (`config/procurement.php`):
- `PROCUREMENT_AUTO_CREATE_INVOICE` (default **false**) — pembuatan invoice otomatis saat GRN selesai.
- `PROCUREMENT_AUTO_POST_RETRIES` = 3 dengan backoff `200,500,1000` ms.
- `DO_APPROVAL_REQUIRED` (default **true**) — DO wajib disetujui sebelum bisa "Sent".

### 6.2 Penjualan (Sales)

```
Quotation                 status: draft → request_approve → approve → reject
  ▼
SaleOrder                 status: draft → request_approve → approved → confirmed
  │                               → completed  (+ request_close, closed, canceled, reject, received)
  │   tipe_pengiriman: "Ambil Sendiri" | "Kirim Langsung"
  │   multi-currency (currency_id, exchange_rate decimal:8)
  │   alokasi per-gudang: SaleOrderItemWarehouseAllocation
  │   gerbang kredit: CreditValidationService
  ├─────────────────────────────┐
  ▼                             ▼
WarehouseConfirmation      Invoice (Sales)  → AccountReceivable
  ▼                             ▼
DeliveryOrder              CustomerReceipt  → jurnal penerimaan kas
  ▼
DeliverySchedule → SuratJalan
```

Jalur **"Ambil Sendiri"** berbeda dari **"Kirim Langsung"**: pengurangan stok untuk self-pickup
ditangani `SaleOrderObserver::handleStockReductionForSelfPickup()` saat SO `completed`, bukan
lewat DO.

### 6.3 Pengiriman (Delivery) — alur DO-centric

```
DeliveryOrder   status: draft → request_stock → request_approve → approved
                        → sent → received → completed
                        (+ supplier, request_close, closed, reject)
```

DO mengumpulkan `WarehouseConfirmation` polimorfik (satu per gudang sumber).
`DeliveryOrder::updateStatusFromWarehouseConfirmations()` menyimpulkan status otomatis:

| Kondisi WC | Status DO |
|---|---|
| belum ada WC | `request_stock` |
| **semua** `confirmed` | `approved` |
| **ada** yang `rejected` | `reject` |
| masih ada `pending` | tetap `request_stock` |

**Kapan stok benar-benar berkurang** (ini pernah jadi sumber bug, lihat `PLAN.md`):
`DeliveryOrderObserver::createStockMovementsForShippingStart()` membuat `StockMovement` saat
status menjadi **`sent`** — jadi `qty_available` turun di titik "Mulai Pengiriman".
`StockReservation` **tidak** dihapus saat `sent`; ia baru dilepas saat `completed`.
`handleCompletedStatus()` sengaja **tidak** membuat `StockMovement` lagi agar tidak duplikat.
Jurnal pengiriman tetap dibuat saat `completed`.

`DeliveryOrderService::postDeliveryOrder()` punya penjaga idempoten: memeriksa
`StockMovement` dengan `from_model_type = DeliveryOrderItem::class` dan `type = 'sales'` sebelum
membuat yang baru.

### 6.4 Inventori

| Model | Peran |
|---|---|
| `InventoryStock` | posisi stok per **produk × gudang × rak**; kolom `qty_available`, `qty_reserved`, `qty_min` |
| `StockMovement` | **satu-satunya** pintu perubahan stok (buku besar stok) |
| `StockReservation` | penahan stok untuk SO/DO/MaterialIssue yang belum keluar |
| `StockTransfer` | mutasi antar gudang |
| `StockAdjustment` | koreksi manual |
| `StockOpname` | stock-taking / opname fisik |

**Aturan mutlak:** jangan pernah meng-update `InventoryStock` secara langsung. Buat
`StockMovement`, lalu `StockMovementObserver` yang menyesuaikan stok (dengan `lockForUpdate()`).

Rumus ketersediaan: `free_qty = qty_available − qty_reserved`. Helper agregat:
`InventoryStock::freeQtyFor($productId, $warehouseId, $rakId)`.

Sumber `StockMovement` yang dikenali (polimorfik `from_model`): SaleOrder, PurchaseOrder,
DeliveryOrder/Item, PurchaseReceipt/Item, StockTransfer/Item, ManufacturingOrder, MaterialIssue,
StockAdjustment, QualityControl, PurchaseReturn.

⚠️ **Jebakan `rak_id`:** `StockMovementObserver::adjustAvailableStockByKey()` mencari
`InventoryStock` dengan pencocokan **product + warehouse + rak_id**. Jika `rak_id` pada movement
tidak cocok dengan baris stok, stok **tidak akan ter-update** dan gagalnya senyap.

### 6.5 Produksi (Manufacturing)

```
BillOfMaterial (+ Item)          resep: komponen & qty per unit output
  ▼
ProductionPlan                   rencana produksi
  ▼
ManufacturingOrder               status: draft → in_progress → completed
  ├─ MaterialIssue               status: draft → pending_approval → approved → completed
  │    │  (status item ikut berjenjang: draft→pending_approval→approved→completed)
  │    └─ WarehouseConfirmation → StockReservation → konsumsi bahan baku
  └─ Production
       └─ QualityControl (produk jadi) → StockMovement masuk
```

Akuntansi produksi ditangani `ManufacturingJournalService` (862 baris) + `CostVariance` +
`ProductionCostEntry` + `ProductStandardCost` — artinya sistem menganut **standard costing**
dengan perhitungan varians, bukan sekadar actual costing.

Perhitungan HPP/COGM dikonfigurasi lewat `config/hpp.php` berbasis **prefix kode COA**
(bahan baku `1140.01`, upah langsung `5120`, WIP `1140.02`, overhead: listrik pabrik `5130`,
penyusutan mesin `5140`, perawatan `5150`).

### 6.6 Keuangan & Akuntansi

Pusatnya `LedgerPostingService` (1.377 baris) dengan empat pintu posting utama:

| Method | Untuk |
|---|---|
| `postInvoice()` | invoice **pembelian** saja (invoice penjualan ditangani `InvoiceObserver`) |
| `postVendorPayment()` | pembayaran ke supplier |
| `postCustomerReceipt()` | penerimaan dari pelanggan |
| `postDeposit()` | deposit / uang muka |
| `reverseJournalEntries()` / `reverseInvoiceJournalEntries()` | pembalikan jurnal |

Pelindung yang sudah terpasang di service ini:
- **Guard anti-double-posting atomik**: cek `exists()` dengan `lockForUpdate()` di dalam
  transaksi, sehingga dua request bersamaan tidak bisa sama-sama lolos.
- **Pemisahan tanggung jawab tegas**: invoice penjualan di-*skip* eksplisit (`from_model_type ===
  SaleOrder`) agar tidak berbenturan dengan `InvoiceObserver::postSalesInvoice()`.
- **Konversi mata uang sekali di hulu**: nilai asing dikalikan kurs **satu kali** sebelum menulis
  jurnal, sehingga buku besar & laporan selalu IDR.

`AccountingPeriod::ensureDateIsOpen($date, $cabangId)` melempar `ClosedPeriodException` bila
periode (per cabang!) sudah `closed`. Dipanggil dari `JournalEntryObserver` pada
create/update/delete.

`ChartOfAccount` bersifat hierarkis (`parent_id`) dengan tipe: `Asset`, `Liability`, `Equity`,
`Revenue`, `Expense`, `Contra Asset`. Accessor `normal_balance` menetapkan Asset/Expense = debit,
sisanya = kredit.

### 6.7 Aset Tetap

`PurchaseOrder.is_asset = true` → saat PO completed, record `Asset` dibuat otomatis
(`PurchaseOrderObserver` → `AssetService`). Jurnal invoice mengarahkan debit ke
`config('coa.fixed_asset')` = `1500` (bukan persediaan). Siklus lanjutan:
`AssetDepreciationService` (dijadwalkan via command `GenerateMonthlyDepreciation`),
`AssetTransferService`, `AssetDisposalService`. Nilai aset memakai presisi `decimal(20,2)`.

---

## 7. Mesin Lintas-Modul (Cross-Cutting Engines)

### 7.1 Multi-Cabang

`CabangScope` (global scope) memfilter `cabang_id` sesuai user, dengan **dua jalur bypass**:

```php
if (!$user || !$user->cabang_id) return;              // user tanpa cabang → lihat semua
if (in_array('all', $user->manage_type)) return;      // manage_type 'all' → lihat semua
$builder->where($table.'.cabang_id', $user->cabang_id);
```

Detail yang mudah terlewat: `manage_type` disimpan sebagai **string comma-separated** di DB tapi
diakses sebagai **array** lewat accessor (`getManageTypeAttribute` → `explode(',')`). Cast array
sengaja dinonaktifkan di `casts()`.

Model yang memakai scope ini antara lain `SaleOrder`, `DeliveryOrder`, `Invoice`, `JournalEntry`.
**`Supplier` sengaja tidak di-scope** — supplier bersifat global lintas cabang.
`Product` memakai scope kustom `product_cabang` dengan fallback ke produk tanpa `cabang_id`.

`Cabang` juga memegang identitas dokumen per cabang: `kode_invoice_pajak`,
`kode_invoice_non_pajak`, `kode_invoice_pajak_walkin`, `label_invoice_*`,
`logo_invoice_non_pajak`, `nama_kwitansi`, plus `kenaikan_harga` dan flag
`lihat_stok_cabang_lain`.

`JournalBranchResolver` menurunkan `cabang_id` (juga `department_id`, `project_id`) dari dokumen
sumber saat jurnal dibuat — ada juga *safety net* di `JournalEntry::creating()`.

### 7.2 Pola Observer (30 observer)

Ada **tiga** pola pendaftaran yang hidup berdampingan:

1. **`AppServiceProvider::boot()`** — 28 observer (jalur utama & terdokumentasi).
2. **Di dalam model** — `InventoryStock::boot()` mendaftarkan `InventoryStockObserver`;
   `StockTransferItem::boot()` mendaftarkan `StockTransferItemObserver`.
3. **Lewat trait** — `LogsGlobalActivity::bootLogsGlobalActivity()` memasang
   `GlobalActivityObserver` ke **setiap model** yang memakai trait tersebut (mayoritas model).

Konsekuensi: pernyataan "observer hanya didaftarkan di AppServiceProvider" di `CONTEXT.md`
**tidak lagi akurat**. Sebelum menambah observer, periksa ketiga lokasi ini agar tidak terpasang ganda.

### 7.3 Mata Uang & Strategi "IDR Anchor"

Masalah yang dipecahkan: konversi bolak-balik menimbulkan selisih pembulatan
(Rp 1.000.000 → $66,67 → Rp 1.000.050).

Solusinya tiga lapis:
1. **Kolom anchor** `*_idr` (`unit_price_idr`, `original_price_idr`) menyimpan nilai IDR asli.
2. **Konversi selalu diturunkan dari anchor**, bukan dari nilai asing yang sudah dibulatkan di UI.
3. **Aritmetika presisi tinggi** — `CurrencyConversionResolver::convertToIdrHighPrecision()` /
   `convertFromIdrHighPrecision()` memakai `bcmath` (≥10 desimal), pembulatan hanya di output akhir.

Presisi kolom:

| Jenis | Presisi | Keterangan |
|---|---|---|
| Semua nominal uang (IDR & valas) | `decimal(15,2)` | **standar wajib** |
| `journal_entries.debit` / `credit` | `decimal(20,2)` | pengecualian: nilai ledger besar |
| Nilai aset & depresiasi | `decimal(20,2)` | pengecualian: nilai kapital |
| `exchange_rate` | `decimal(18,8)` | pengecualian: presisi FX |
| `amount_original_currency` | `decimal(20,4)` | pengecualian: kalkulasi intermediate |

`Currency` menyimpan `to_rupiah`. Resolusi kurs untuk jurnal berjenjang:
currency eksplisit di sumber → `PurchaseOrderCurrency` → Invoice→PO → VendorPayment→Invoice→PO →
fallback **IDR**. **Invoice penjualan selalu diposting dalam IDR** walau SO memakai valas.

### 7.4 Pajak

Nilai `tipe_pajak` **wajib lowercase**: `ppn`, `non_ppn`, `ppn_bm`, `pph22` (dinormalisasi oleh
migration `2026_05_11_000001_normalize_tax_type_columns_to_lowercase`). Normalisasi runtime lewat
`TaxService::normalizeType()`; helper tambahan `TaxTypeHelper`, `TaxDefaultResolver`,
`SalesInvoiceTaxResolver`.

Invoice menyimpan `ppn_rate`, `dpp` (dasar pengenaan pajak), dan untuk impor:
`pph22_amount`, `bea_masuk_amount`. Tarif dinamis lewat
`TaxSetting::activeRate('PPN'|'PPH'|'Custom')` yang mengambil rate aktif dengan
`effective_date <= now()` terbaru — jadi perubahan tarif pajak bersifat *time-aware*, bukan
konstanta.

Form React membedakan pajak **inklusif vs eksklusif** di `calculations.ts`: mode `inklusif`
membuat `subtotal = after_discount` (pajak sudah termasuk), mode lain menambahkan `tax_nominal`.

### 7.5 Penomoran Dokumen

Dua mekanisme:
- **`SequentialNumberGenerator::generate($table, $column, $prefix, $digits, $dateFormat, $date)`** —
  mengabaikan global scope & soft-delete (`DB::table()`) supaya nomor tidak pernah terpakai ulang,
  mencari sequence maksimum, lalu loop verifikasi anti-tabrakan pada lingkungan konkuren.
- **`VoucherNumberSequence`** (tabel tersendiri) untuk nomor voucher.
- **`DepositNumberGenerator`** untuk deposit.

Nomor per entitas: `po_number`, `so_number`, `do_number`, `invoice_number`, `qc_number`,
`transfer_number`, `adjustment_number`, `mo_number`, `issue_number`, `receipt_number`,
`request_number` (PaymentRequest), `kode_user` (`USR-0001`, auto di `User::creating()`).

### 7.6 Soft Delete & Cascade

Semua model bisnis utama memakai `SoftDeletes`. Pola kaskade seragam di `booted()`:

```php
static::deleting(fn($m) => $m->isForceDeleting()
    ? $m->relatedItems()->forceDelete()
    : $m->relatedItems()->delete());
static::restoring(fn($m) => $m->relatedItems()->withTrashed()->restore());
```

Trait `CascadesJournalEntries` melakukan hal yang sama untuk `JournalEntry` pada model yang
berjurnal (`PurchaseOrder`, `DeliveryOrder`, `QualityControl`, `ManufacturingOrder`).

### 7.7 Relasi Polimorfik

Dipakai intensif sebagai tulang punggung fleksibilitas:

| Relasi | Menunjuk ke |
|---|---|
| `JournalEntry.source` | PO, Invoice, VendorPayment, QC, DO, … |
| `Invoice.fromModel` | SaleOrder, PurchaseOrder, PurchaseReceipt |
| `StockMovement.fromModel` | 10+ tipe dokumen |
| `WarehouseConfirmation.confirmable` | SaleOrder, ManufacturingOrder, MaterialIssue, **DeliveryOrder** |
| `QualityControl.fromModel` | sumber item QC |
| `Deposit.from_model` | sumber deposit |
| `PurchaseOrder.referModel` / `PurchaseOrderItem.referItemModel` | OrderRequest / OrderRequestItem |

### 7.8 Format Uang di UI

Macro Filament global didaftarkan di `AppServiceProvider::registerFilamentMacros()`:

- `TextColumn::rupiah()` dan `TextEntry::rupiah()` → `MoneyHelper::rupiah()`
- `Sum::rupiah()` untuk summarizer kolom tabel
- `TextInput::indonesianMoney()` → mask `$money($input, ',', '.', 2)`, prefix `Rp`,
  auto-pad desimal saat blur, validasi format, dan `dehydrateStateUsing` → `MoneyHelper::safeParse()`
- Global: `Table::$defaultCurrency = 'IDR'`, `Table::$defaultNumberLocale = 'id'`

**Selalu** parse input uang dengan `MoneyHelper::safeParse()` (format Indonesia: `.` ribuan,
`,` desimal).

---

## 8. Kontrol Internal & Otorisasi

### 8.1 Izin (Spatie Permission)

Matriks izin dibangkitkan dari `HelperController::listPermission()`: **73 grup sumber daya** ×
aksi (`view any`, `view`, `create`, `update`, `delete`, `restore`, `force-delete`, plus
`response`/`request` untuk dokumen ber-approval) ≈ **500+ izin**, masing-masing dengan deskripsi
Bahasa Indonesia dari `HelperController::permissionDescriptions()`.

**17 role** di `RoleSeeder`: Owner, Super Admin, Admin, Sales Manager, Sales, Kasir,
Inventory Manager, Admin Inventory, Checker, Finance Manager, Admin Keuangan, Accounting,
Purchasing, Purchasing Manager, Warehouse Staff, Delivery Driver, Customer Service, Auditor,
IT Support.

Penegakan di **76 Policy**. `User::canAccessPanel()` mengembalikan `true` untuk semua —
jadi pintu panel terbuka, pembatasan sepenuhnya di level policy. `User::hasPermissionTo()`
di-override untuk menangkap `PermissionDoesNotExist` dan mengembalikan `false` secara graceful
(mencegah crash pada izin yang belum di-seed).

### 8.2 Kontrol Persetujuan Berjenjang

`ApprovalControlService` menegakkan dua kaidah kontrol internal sekaligus:

**(a) Anti-self-approval (Segregation of Duties).** Pembuat dokumen tidak boleh menyetujui
dokumennya sendiri. Pengecualian override sistem hanya untuk `Super Admin` & `Owner`.
Resolusi pembuat bersifat per-tipe: `OrderRequest`/`SaleOrder` → `created_by`,
`PaymentRequest` → `requested_by`.

**(b) Batas nominal berjenjang.** Ambang `TIER_1_MAX_AMOUNT = Rp 10.000.000`:

| Dokumen | ≤ Rp 10 juta | > Rp 10 juta |
|---|---|---|
| Order Request | Purchasing Manager / Inventory Manager / + top tier | **top tier saja** |
| Sales Order | Sales Manager / + top tier | **top tier saja** |
| Payment Request | Finance Manager / Accounting / + top tier | **top tier saja** |

Top tier = `Super Admin`, `Owner`, `Finance Manager`, `Admin`.

Setiap pemeriksaan mengembalikan `['allowed' => bool, 'reason' => string|null]` dengan alasan
berbahasa Indonesia yang siap ditampilkan ke user.

### 8.3 Kontrol Kredit Pelanggan

`CreditValidationService::canCustomerMakePurchase()` aktif hanya untuk customer
`tipe_pembayaran === 'Kredit'`, dan memeriksa tiga hal:
1. **Limit kredit** — `checkCreditLimit()` dibungkus `DB::transaction` + `lockForUpdate()` pada
   baris customer, sehingga dua SO bersamaan tidak bisa sama-sama lolos (race condition tertutup).
2. **Tagihan jatuh tempo** — `checkOverdueCredits()` memblokir bila ada invoice overdue.
3. **Peringatan dini** — pemakaian kredit 80–99% memunculkan warning (tidak memblokir).

### 8.4 Audit Trail

`LogsGlobalActivity` + `GlobalActivityObserver` mencatat seluruh CRUD ke `activity_log` (Spatie).
Kolom audit standar di dokumen transaksional: `created_by`, `approved_by`/`approve_by`,
`approved_at`/`date_approved`, `completed_by`, `completed_at`, `closed_by`, `closed_at`,
`request_close_by`, `reject_by`, `reject_at`. Tanda tangan persetujuan disimpan
(`approval_signature`, `approval_signed_at`) via komponen `SignaturePad`.

---

## 9. Lapisan Pelaporan

Pelaporan digarap sebagai subsistem tersendiri: **12 report service** + **19 kelas Export** +
**4 controller preview** + **~15 halaman Filament** + **33 widget dashboard**.

**Laporan keuangan inti:** Balance Sheet (Neraca), Income Statement (Laba Rugi), Trial Balance,
Buku Besar, Profit & Loss Multi-Divisi, Financial Statement, Drill-Down Financial Report,
ALK Grafik, Journal Consolidation, Cash Flow, Ageing Schedule (AR/AP), HPP/COGM.

**Laporan operasional:** Sales Report, Purchase Report, Inventory Report, Stock Report,
Inventory Card (kartu stok), Production Report, Delivery Order/Schedule Recap,
Vendor-Customer Summary, Deposit Summary.

Pola yang dipakai: setiap laporan punya *preview* HTML (route `/reports/*/preview`) + unduhan
**PDF** (DomPDF) + **Excel** (Maatwebsite), semuanya di bawah middleware `['auth','throttle:60,1']`.

Menarik: konfigurasi laporan **Cash Flow** dan **HPP** disimpan sebagai **data**, bukan kode —
7 model di `app/Models/Reports/` (`CashFlowSection`, `CashFlowItem`, `CashFlowItemPrefix`,
`CashFlowItemSource`, `CashFlowCashAccount`, `HppPrefix`, `HppOverheadItem`,
`HppOverheadItemPrefix`) memetakan prefix COA ke baris laporan. Artinya struktur laporan bisa
dikonfigurasi tanpa deploy.

Widget dashboard mencakup: `PenjualanOverview`, `Penjualan7HariChart`,
`PenjualanPerKategoriChart`, `ProdukTerlarisChart`, `TopCustomerChart`, `UmurPiutangChart`,
`UmurHutangChart`, `ArApChart`, `SaldoStatsOverview`, `CreditStatsOverview`,
`StockMinimumTable`, `PoBelumSelesaiTable`, `SoBelumSelesaiTable`, `DoBelumSelesaiTable`,
`MutasiMasuk/KeluarBelumSelesaiTable`, `OverdueCreditTable`, `TopTagihanOutstanding`, dll.

---

## 10. Subsistem Migrasi Data Legacy

Ini subsistem berukuran serius — bukan skrip sekali pakai — untuk memindahkan data dari ERP lama:

**11 service rehydration:** `LegacyInventoryMigrationService` (1.405 baris — file terbesar di
`app/Services`), `LegacyOrderRequestRehydrationService`, `LegacyPurchaseOrderRehydrationService`,
`LegacyPurchaseInvoiceRehydrationService`, `LegacyQuotationRehydrationService`,
`LegacySalesOrderRehydrationService`, `LegacySaleInvoiceRehydrationService`,
`LegacyStockAdjustmentRehydrationService`, `LegacyStockTransferRehydrationService`,
`LegacyTransactionArchiveService`, `LegacyCategoryApprovalService`.

**~25 Artisan command** dengan pola kerja bertahap yang rapi:
`LegacyImportWorkbook` / `LegacyImportMasterData` / `LegacyImportTransactionArchive` (impor) →
`LegacyPrepareCategoryApproval` / `LegacyPrefillProductApprovalFile` (siapkan) →
`LegacySimulateCategoryApproval` (simulasi) → `LegacyApplyCategoryApproval` (terapkan) →
`LegacyAuditMerge` / `LegacyExportConflictReport` / `LegacyCompareImportedSources` (audit) →
`LegacyConsolidateStaging` / `LegacyConsolidateApprovedProductBucket` (konsolidasi).
Tersedia juga `LegacyResetErpData` untuk reset.

Pendukung: model `LegacyTransactionArchive` + resource-nya (grup navigasi "Legacy Migration"),
serta kolom `legacy_*` di model-model utama sebagai jejak referensi sistem lama.

Di luar itu ada **command audit & perbaikan data** yang mencerminkan kematangan operasional:
`AuditInventoryConsistency`, `AuditProductCoaMappings`, `AuditPurchaseInvoices`,
`ReconcileOrderRequestFulfillment`, `ReconcilePurchaseReceiptStock`,
`RepairOrderRequestCurrencyAnchors`, `RepairPurchaseOrderTotal`, `RepairQcReceiptCurrencies`,
`BackfillJournalBranch`, `BackfillProductCoaMappings`, `SyncArApCommand`,
`ReversePurchaseInvoiceJournal`, `VendorPaymentRollback`.

**Penjadwalan (`app/Console/Kernel.php`):** hanya **satu** cron aktif —
`invoices:check-overdue` setiap hari 00:05 (menandai invoice lewat jatuh tempo sebagai `overdue`).
`GenerateMonthlyDepreciation` terdaftar sebagai command tapi **belum dijadwalkan** — depresiasi
bulanan saat ini harus dipicu manual.

---

## 11. Testing & Keamanan Basis Data Uji

**~344 file test:** 284 Feature, 60 Unit, 2 Browser (Dusk), plus E2E Playwright di `playwright/`.

Cakupan test memperlihatkan area yang paling dijaga: currency/presisi (≥10 file khusus,
mis. `CurrencyConversionPrecisionTest`, `CurrencyRoundtripFormTest`,
`CurrencyFiveDollarVerificationTest`), balance sheet & konsistensi akuntansi
(`BalanceSheetConsistencyTest`, `AccountingPeriodClosingTest`), alur end-to-end
(`CompleteDeliveryOrderFlowTest`, `CompleteProcurementAccountingFlowTest`,
`CompleteSalesFlowFilamentTest`), dan turunan cabang (`BranchInheritanceFlowTest`).

**Pengaman database uji (penting).** `tests/TestCase.php` memuat *hard guard*: saat
`APP_ENV=testing`, test **dibatalkan** kecuali nama database berakhiran `_test`. Database aplikasi
lokal `duta_tunggal` **tidak boleh** dipakai untuk test otomatis.

Perintah yang aman (membersihkan config cache lebih dulu):

```bash
composer test:safe -- tests/Feature/PurchaseOrderTotalCalculationTest.php
```

```bash
composer test:unit-safe -- tests/Unit/PurchaseOrderItemNavigatorTest.php
```

Siapkan database uji sekali:

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS duta_tunggal_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Menjalankan dev stack lengkap (server + queue + vite):

```bash
composer dev
```

---

## 12. Penilaian: Kekuatan & Risiko

### Kekuatan

1. **Integritas akuntansi ditangani serius.** Guard anti-double-posting berbasis
   `lockForUpdate()`, penguncian periode akuntansi per cabang, mekanisme reversal jurnal, dan
   strategi IDR anchor untuk presisi valas — ini bukan pola ERP amatir.
2. **Kontrol internal riil.** Anti-self-approval, persetujuan berjenjang berbasis nominal, dan
   penguncian limit kredit dengan row-level lock adalah kontrol yang biasanya hanya ada di ERP komersial.
3. **Pemisahan lapisan konsisten.** Aturan "resource tidak memuat logika bisnis" ditegakkan luas;
   69 service membuat logika dapat diuji terpisah dari UI.
4. **Solusi performa yang tepat sasaran.** Migrasi 4 form terberat ke React island menyelesaikan
   masalah nyata (lag 5–15 detik) tanpa menulis ulang keseluruhan sistem.
5. **Cakupan test tinggi pada area berisiko.** Test terkonsentrasi di currency, akuntansi, dan alur
   end-to-end — tepat di tempat yang paling mahal bila salah.
6. **Kedewasaan operasional.** Adanya command audit/reconcile/repair menunjukkan sistem ini sudah
   menghadapi data produksi nyata dan menyiapkan perkakas pemulihannya.

### Risiko & Utang Teknis

1. **Filament Resource raksasa.** `PurchaseOrderResource` 3.517 baris, `OrderRequestResource`
   3.375, `SaleOrderResource` 2.408, `QuotationResource` 2.092. Ukuran ini membuat perubahan
   berisiko dan review sulit. (Catatan: justru keempat ini yang formnya sudah pindah ke React —
   sisa kode PHP-nya kandidat kuat untuk dipangkas.)
2. **Otomasi tersembunyi di observer.** 30 observer dengan 3 pola pendaftaran berbeda membuat alur
   efek samping sulit dilacak. Risiko terbesar: memasang observer ganda tanpa sadar, atau mengubah
   observer dan memicu regresi di modul yang tampak tidak berhubungan.
3. **Duplikasi logika kalkulasi PHP ↔ TypeScript.** Empat `calculations.ts` menduplikasi rumus
   subtotal/diskon/pajak yang juga ada di PHP. Tidak ada mekanisme yang memaksa keduanya sinkron —
   ini sumber bug laten yang paling mungkin muncul di masa depan.
4. **Kegagalan senyap pada `rak_id`.** `StockMovementObserver` mencocokkan `rak_id` secara ketat;
   ketidakcocokan menyebabkan stok tidak ter-update **tanpa error**. Perlu logging/alert eksplisit.
5. **Navigasi tidak konsisten.** Nama grup di resource tidak seluruhnya cocok dengan deklarasi di
   `AdminPanelProvider` (§5), dan `discoverPages()` dimatikan sehingga page baru wajib didaftarkan
   manual — mudah terlupa.
6. **Kode mati & anomali penamaan.**
   - `app/Observers/ProductSupplierObserver.php` — **tidak pernah didaftarkan di mana pun** (kode mati).
   - `app/Observers/DepositLogObserser.php` — salah ketik nama kelas (`Observser`).
   - `app/Observers/ManufacturingOrder.php` — kelas observer dinamai seperti model, sehingga harus
     di-alias `ObserversManufacturingOrder` saat di-import.
   - `app/Models/TestModel.php` — stub kosong ikut terbawa di direktori produksi.
   - `app/Filament/Pages/ViewAgeingReport.php.backup`, `TestGroupedPage.php` — artefak sisa.
7. **Logging debug di jalur produksi.** `LedgerPostingService` masih memuat
   `Log::info('DEBUG: Invoice type check', ...)` pada jalur posting yang dieksekusi setiap invoice.
8. **Penjadwalan minim.** Hanya `invoices:check-overdue` yang terjadwal.
   `GenerateMonthlyDepreciation` belum masuk scheduler, padahal depresiasi bulanan bersifat rutin.
9. **Tanpa Redis.** Queue, cache, dan session semuanya di driver `database`. Pada beban tinggi,
   ini menjadikan MySQL titik kontensi tunggal.
10. **Pesan commit tidak informatif.** 5 dari 6 commit terakhir berjudul `"Update"`, menyulitkan
    pelacakan riwayat perubahan dan bisect saat mencari regresi.

### Kondisi Working Tree Saat Ini

Working tree memuat **85 file berubah (+2.354 / −762)** yang belum di-commit. Berdasarkan pola
perubahannya, ini adalah **implementasi perbaikan 17 bug sedang UAT** yang diaudit di
`docs/audit_uat_bug_sedang.md` (tanggal audit 18 September 2026 — sehari sebelum analisis ini).

Indikator bahwa perbaikan sudah diterapkan, terverifikasi langsung di kode:
- `config/coa.php` → `'inventory' => '1140.10'` (Persediaan Barang Dagangan), memperbaiki Isu 15
  yang sebelumnya menunjuk `1140.01` (Bahan Baku).
- `app/Console/Kernel.php` → jadwal harian `invoices:check-overdue`, memperbaiki Isu 8.
- Kluster perubahan pada `VendorPaymentResource` + `CreateVendorPayment` (+115/+42 baris) sejalan
  dengan Isu 10 (prefill pembayaran vendor & referensi jurnal).
- `OrderRequestService` (+259/−?) dan `OrderRequestQuantityLock` (+11) sejalan dengan Isu 1–4
  (kalkulasi ulang qty, status item, sisa item rejected, tombol reject).

**Saya belum menjalankan test suite**, jadi saya tidak dapat memastikan status hijau/merah dari
perubahan ini. Verifikasi disarankan sebelum commit:

```bash
composer test:safe
```

### Perubahan Arsitektural yang Sudah Direncanakan (Belum Dikerjakan)

`docs/rencana_tahapan_perbaikan_feedback_customer.md` memuat rencana perbaikan **7 poin feedback
customer** yang secara eksplisit menyatakan *belum mengubah kode sebelum persetujuan*. Dua di
antaranya adalah perubahan arsitektur yang akan berdampak luas dan perlu diketahui siapa pun yang
menyentuh modul pengadaan:

| Poin | Rencana | Dampak |
|:--:|---|---|
| **1** *(prioritas tertinggi)* | **QC diubah menjadi Header–Detail**: tabel baru `quality_control_items`; `QualityControl` menjadi header yang mereferensikan `purchase_order_id`. Satu kedatangan barang = **1 nomor QC + 1 nomor GRN** untuk seluruh item. | Besar. Saat ini `quality_controls` adalah 1 baris per produk, sehingga PO 30 item menghasilkan 30 QC + 30 GRN. Perubahan ini menyentuh `QualityControlService`, `PurchaseReceiptService`, mutasi stok, dan penjurnalan sekaligus. |
| **2** | Validasi kuantitas dipindahkan dari saat *Complete* ke saat **Simpan Draft**, dengan rumus sisa `Qty PO − Qty GRN − Qty terkunci draft QC lain`; draft QC otomatis dibatalkan lewat observer saat PO `completed`/`closed`. | Menengah. Menambah observer baru pada `PurchaseOrder`. |
| **3** | Menghapus validasi keras di `CreatePurchaseInvoice.php` yang mewajibkan `selected_order_request`; invoice merefer **PO + GRN**, OR hanya filter opsional. | Membuka jalur invoice untuk Direct PO (tanpa OR) yang saat ini mustahil. |
| **4** | `warehouse_id` di header PO menjadi **wajib** (1 PO = 1 gudang); cabang akuntansi PO dikunci ke Pusat; `cabang_id` per baris item dihapus. | Menyentuh `CabangScope`/`JournalBranchResolver` secara tidak langsung. |
| **5** | `warehouse_id` di form QC dikunci mengikuti `PO->warehouse_id`; otorisasi QC dibatasi `Auth::user()->warehouse_id` (kecuali Super Admin/Owner). | Menambah dimensi otorisasi baru berbasis gudang, di samping cabang. |
| **6** | Form OR disederhanakan: pemohon hanya mengisi nama barang, qty, `required_date`, `purpose`, catatan. Harga/kurs/pajak/diskon/supplier dipindah ke tahap PO. | Berdampak ke React island Order Request **dan** `OrderRequestResource`. |
| **7** | Tindak lanjut barang reject (`wait_next_delivery`, `return_supplier`, `reduce_stock`) disatukan ke tabel multi-item QC. | Bergantung pada penyelesaian Poin 1. |

Implikasi praktis: **jangan memulai pekerjaan besar di modul QC/PO/OR tanpa membaca dokumen
rencana ini lebih dulu** — beberapa struktur yang saya deskripsikan di §6.1 memang dijadwalkan
berubah.

---

## 13. Temuan: Selisih antara Dokumentasi dan Kode

Poin-poin di `docs/CONTEXT.md` (v1.0.0, 21 Mei 2026) yang sudah tidak sesuai kondisi kode
19 September 2026:

| # | Pernyataan di `CONTEXT.md` | Kondisi kode sekarang |
|:--:|---|---|
| 1 | "Database: MySQL (production), **SQLite** (testing)"; ".env.testing menggunakan SQLite in-memory" | `phpunit.xml` dan `.env.testing` keduanya memakai **MySQL** `duta_tunggal_test`. Tidak ada SQLite in-memory. |
| 2 | "Observer hanya didaftarkan di `AppServiceProvider::boot()`. Jangan tambahkan `static::observe()` di dalam model" | Ada **3 pola** aktif: provider (28), di dalam model (`InventoryStock`, `StockTransferItem`), dan lewat trait (`LogsGlobalActivity` → `GlobalActivityObserver` ke semua model). |
| 3 | Tidak disebut sama sekali | **Arsitektur hibrida React** untuk 4 form transaksi + **API v1** — ini perubahan arsitektural besar yang belum masuk dokumen konteks. |
| 4 | Tidak disebut | **`ApprovalControlService`** (anti-self-approval + tier nominal Rp 10 juta) dan **`CreditValidationService`** — dua mekanisme kontrol internal utama. |
| 5 | Tabel §6 "Sistem Kode & Nomor Dokumen" berisi banyak sel kosong ("—") | Format nomor nyata dibangkitkan `SequentialNumberGenerator` dengan prefix + segmen tanggal; `PaymentRequest` punya `request_number` yang belum terdaftar di tabel tersebut. |
| 6 | "`config('coa.inventory')`" tanpa nilai | Nilainya sudah diubah ke `1140.10` sebagai bagian perbaikan UAT Isu 15. |
| 7 | Menyebut `ManufacturingOrderObserver` | Nama kelas sebenarnya `App\Observers\ManufacturingOrder` (tanpa sufiks `Observer`). |

**Saran:** naikkan `CONTEXT.md` ke v1.1.0 dengan menambahkan bagian arsitektur React island +
API v1, memperbaiki bagian testing, dan mengoreksi aturan pendaftaran observer.

---

## 14. Panduan Orientasi: "Mau Ubah X, Lihat Di Mana?"

| Kebutuhan | Berkas / Lokasi |
|---|---|
| Menambah/mengubah posting jurnal | [app/Services/LedgerPostingService.php](app/Services/LedgerPostingService.php) |
| Jurnal produksi / varians biaya | [app/Services/ManufacturingJournalService.php](app/Services/ManufacturingJournalService.php) |
| Akuntansi invoice pembelian | [app/Services/PurchaseInvoiceAccountingService.php](app/Services/PurchaseInvoiceAccountingService.php) |
| Jurnal invoice penjualan | [app/Observers/InvoiceObserver.php](app/Observers/InvoiceObserver.php) (`postSalesInvoice`) |
| Mengubah perilaku stok | [app/Observers/StockMovementObserver.php](app/Observers/StockMovementObserver.php) — **jangan** update `InventoryStock` langsung |
| Aturan QC masuk/keluar | [app/Services/QualityControlService.php](app/Services/QualityControlService.php) |
| Kuota qty OR → PO → GRN | [app/Support/OrderRequestQuantityLock.php](app/Support/OrderRequestQuantityLock.php) |
| Aturan siapa boleh approve | [app/Services/ApprovalControlService.php](app/Services/ApprovalControlService.php) |
| Limit kredit & overdue customer | [app/Services/CreditValidationService.php](app/Services/CreditValidationService.php) |
| Pemetaan COA default | [config/coa.php](config/coa.php) · aset: [config/asset.php](config/asset.php) |
| Konfigurasi HPP/COGM | [config/hpp.php](config/hpp.php) |
| Flag otomasi pengadaan | [config/procurement.php](config/procurement.php) |
| Filter data per cabang | [app/Models/Scopes/CabangScope.php](app/Models/Scopes/CabangScope.php) |
| Resolusi cabang jurnal | [app/Services/JournalBranchResolver.php](app/Services/JournalBranchResolver.php) |
| Format & parsing uang | [app/Helpers/MoneyHelper.php](app/Helpers/MoneyHelper.php) + macro di [AppServiceProvider](app/Providers/AppServiceProvider.php) |
| Presisi konversi valas | [app/Support/CurrencyConversionResolver.php](app/Support/CurrencyConversionResolver.php) |
| Normalisasi pajak | [app/Services/TaxService.php](app/Services/TaxService.php), [app/Support/TaxTypeHelper.php](app/Support/TaxTypeHelper.php) |
| Penomoran dokumen | [app/Services/SequentialNumberGenerator.php](app/Services/SequentialNumberGenerator.php) |
| Form React (PO/OR/Quotation/SO) | `resources/js/components/{Modul}Form/` + `app/Http/Controllers/Api/` |
| Blueprint arsitektur React | [SLMJ_HIGH_PERFORMANCE_FORMS_GUIDE.md](SLMJ_HIGH_PERFORMANCE_FORMS_GUIDE.md) |
| Registrasi observer & macro | [app/Providers/AppServiceProvider.php](app/Providers/AppServiceProvider.php) |
| Navigasi, grup, halaman panel | [app/Providers/Filament/AdminPanelProvider.php](app/Providers/Filament/AdminPanelProvider.php) — page baru **wajib** didaftarkan manual |
| Matriks izin akses | [app/Http/Controllers/HelperController.php](app/Http/Controllers/HelperController.php) (`listPermission`) |
| Definisi role | [database/seeders/RoleSeeder.php](database/seeders/RoleSeeder.php) |
| Skema penuh database | [database/schema/mysql-schema.sql](database/schema/mysql-schema.sql) (117 tabel) |
| Aturan & konvensi kode | [docs/CONTEXT.md](docs/CONTEXT.md) |
| Daftar bug UAT & rencana perbaikan | [docs/audit_uat_bug_sedang.md](docs/audit_uat_bug_sedang.md) |
| Rencana perubahan arsitektur QC/PO/OR | [docs/rencana_tahapan_perbaikan_feedback_customer.md](docs/rencana_tahapan_perbaikan_feedback_customer.md) — **baca dulu** sebelum kerja besar di modul pengadaan |
| Prosedur deploy produksi | [docs/production-deployment.md](docs/production-deployment.md) |
| Aturan keamanan database test | [TESTING_SAFETY.md](TESTING_SAFETY.md) |

---

## Lima Hal yang Paling Penting Diingat

1. **Jangan sentuh `InventoryStock` langsung.** Buat `StockMovement`, biarkan observer bekerja.
   Dan pastikan `rak_id` cocok, atau perubahan stok gagal tanpa pesan error.
2. **Periode akuntansi harus terbuka** sebelum menyentuh `JournalEntry` — pengecekan bersifat
   **per cabang**, bukan global.
3. **Nilai uang: `decimal(15,2)`**, dengan lima pengecualian sah (jurnal, aset, kurs,
   `amount_original_currency`). Konversi valas **selalu** diturunkan dari kolom IDR anchor.
4. **Cari efek samping di Observer, bukan di Resource.** Sebelum menambah observer, cek ketiga
   pola pendaftaran agar tidak terpasang ganda.
5. **Untuk 4 form React, logika kalkulasi ada di dua bahasa.** Setiap perubahan rumus harus
   diterapkan di `calculations.ts` **dan** sisi PHP.
