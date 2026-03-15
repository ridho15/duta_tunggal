# DUTA TUNGGAL ERP — System Context Document
**Tanggal:** 14 Maret 2026  
**Versi Dokumen:** 1.0  
**Author:** GitHub Copilot AI Audit  

---

## 1. GAMBARAN UMUM SISTEM

### 1.1 Identitas Sistem

| Properti | Nilai |
|----------|-------|
| **Nama Sistem** | DUTA TUNGGAL ERP |
| **Perusahaan** | PT. Duta Tunggal |
| **Tujuan** | Enterprise Resource Planning — manajemen bisnis terpadu multi-cabang |
| **URL Produksi** | https://dutatunggal.digi-biosportex.site |
| **Framework** | Laravel 12 + Filament 3.3 |
| **Bahasa** | PHP 8.2+ |
| **Database** | MySQL (default) + SQLite (testing) |
| **Timezone** | Asia/Jakarta (WIB) |
| **Locale** | id (Indonesian) |
| **Tanggal Mulai Development** | ~Desember 2025 |
| **Tanggal Dokumen** | 14 Maret 2026 |

### 1.2 Deskripsi Bisnis

Duta Tunggal ERP adalah sistem manajemen bisnis terintegrasi yang mencakup:
- **Multi-cabang** — Setiap transaksi di-scope ke cabang (`cabang_id`) via Global Query Scope
- **Multi-warehouse** — Manajemen stok per gudang dan per rak
- **Bahasa Indonesia** — Seluruh UI, istilah bisnis, dan format mengikuti standar Indonesia
- **Akuntansi Double-Entry** — Setiap transaksi otomatis memposting jurnal akuntansi
- **RBAC** — Role-based access control dengan Spatie Permission
- **Approval Workflow** — Quotation → SO → DO → Invoice → Payment memiliki flow approval multi-level

---

## 2. ARSITEKTUR TEKNIS

### 2.1 Technology Stack

```
┌─────────────────────────────────────────────────────────┐
│                        BROWSER                          │
│              (Filament Livewire SPA)                    │
└────────────────────────┬────────────────────────────────┘
                         │ HTTP/HTTPS
┌────────────────────────▼────────────────────────────────┐
│               LARAVEL 12 APPLICATION                    │
│                                                         │
│  ┌──────────────┐  ┌──────────────┐  ┌───────────────┐  │
│  │   Filament   │  │  Livewire/   │  │   HTTP        │  │
│  │   Admin      │  │  Volt        │  │   Controllers │  │
│  │   Panel      │  │  Components  │  │               │  │
│  └──────┬───────┘  └──────┬───────┘  └──────┬────────┘  │
│         └─────────────────┴─────────────────┘           │
│                           │                             │
│  ┌────────────────────────▼────────────────────────┐    │
│  │              SERVICE LAYER                      │    │
│  │  50+ Service classes (business logic)           │    │
│  └────────────────────────┬────────────────────────┘    │
│                           │                             │
│  ┌─────────────┐  ┌───────▼───────┐  ┌──────────────┐  │
│  │  Observer   │  │   Eloquent    │  │   Policies   │  │
│  │  Layer      │  │   Models      │  │   (Spatie)   │  │
│  │  (30+ obs.) │  │   (60+ models)│  │              │  │
│  └─────────────┘  └───────┬───────┘  └──────────────┘  │
└───────────────────────────┼─────────────────────────────┘
                            │
┌───────────────────────────▼─────────────────────────────┐
│                    MySQL DATABASE                        │
│            (squashed schema + 45 migrations)            │
└─────────────────────────────────────────────────────────┘
```

### 2.2 Komponen Utama

| Komponen | Teknologi | Jumlah File |
|----------|-----------|-------------|
| Admin Panel | Filament 3.3 | 60+ Resources |
| Business Logic | Service Classes | 50+ Services |
| Database Layer | Eloquent ORM | 70+ Models |
| Event System | Laravel Observers | 30+ Observers |
| Authorization | Spatie Permission | 80+ Policies |
| UI Components | Livewire/Volt | 10+ Components |
| Testing | Pest + PHPUnit + Dusk | 175+ Test files |
| Export | Maatwebsite Excel + DomPDF | 14+ Exports |

### 2.3 Pola Arsitektur

1. **Observer Pattern** — Setiap mutation pada model (create/update/delete) ditangkap oleh Observer yang memicu side effects (jurnal, stok, notifikasi)
2. **Service Pattern** — Business logic dipisahkan dari Controller/Resource ke dalam Service classes
3. **Global Scopes** — `CabangScope` secara otomatis memfilter query berdasarkan cabang user yang login
4. **Polymorphic Relations** — Banyak relasi menggunakan morphable (Invoice dari SO atau PO, JournalEntry dari berbagai sumber, dll)
5. **Double-Entry Accounting** — Setiap transaksi finansial memposting ke `journal_entries` dengan validasi debit = kredit

---

## 3. MODUL BISNIS

### 3.1 Siklus Penjualan (Sales Cycle)

```
[Quotation] ──approve──► [Sale Order] ──approve──► [Warehouse Confirmation]
     │                         │                           │
 draft/request/                │                    confirmed/request
 approve/reject          Ambil Sendiri /                   │
                         Kirim Langsung          [Delivery Order]
                                                         │
                              [Surat Jalan] ◄────────────┘
                                    │
                             [Mark as Sent]
                                    │
                        [Sales Invoice] ──► [Account Receivable]
                                    │
                       [Customer Receipt] ──► [Journal Entry]
```

**Status Flow:**
- **Quotation:** `draft` → `request_approve` → `approve/reject`
- **SaleOrder:** `draft` → `request_approve` → `approved` → `closed/completed/canceled`
- **DeliveryOrder:** `draft` → `sent` → `received` → `approved/closed/reject/delivery_failed`

### 3.2 Siklus Pengadaan (Procurement Cycle)

```
[Order Request] ──approve──► [Purchase Order (Draft)]
       │                           │
  (multi-supplier)           ──approve──►
                            [Purchase Order (Approved)]
                                   │
                         [Purchase Receipt/GRN]
                                   │
                         [Quality Control (QC)]
                           passed ─┤─ rejected
                               │               │
                        [Inventory Stock]  [Purchase Return]
                               │
                    [Purchase Invoice] ──► [Account Payable]
                               │
                    [Payment Request] ──approve──►
                    [Vendor Payment] ──► [Journal Entry]
```

### 3.3 Siklus Manufaktur (Manufacturing Cycle)

```
[Bill of Material (BOM)] ──► [Production Plan]
                                   │
                          [Manufacturing Order]
                                   │
                           [Material Issue]
                           (WIP Journal Entry)
                                   │
                             [Production]
                                   │
                         [QC Manufacture]
                                   │
                      [Finished Goods Journal]
                                   │
                          [Inventory Stock]
```

### 3.4 Manajemen Inventori

```
Inbound: PurchaseReceipt (QC passed) + Production + StockAdjustment (+)
Outbound: DeliveryOrder (approved) + MaterialIssue + StockAdjustment (-)
Internal: StockTransfer (antar warehouse) + StockOpname (koreksi fisik)
```

### 3.5 Akuntansi & Keuangan

- **Chart of Accounts (COA)** — Hirarkis (parent/child), tipe: Asset/Liability/Equity/Revenue/Expense/Contra Asset
- **Journal Entries** — Double-entry, auto-posted dari semua modul
- **AR/AP Management** — Invoice → AR/AP → Receipt/Payment → Journal
- **Bank Reconciliation** — Rekonsiliasi rekening koran dengan transaksi sistem
- **Cash & Bank Transactions** — Input transaksi tunai/bank dengan COA mapping
- **Deposit** — Uang muka customer/supplier dengan tracking transaksi
- **Voucher Request** — Permintaan pengeluaran kas kecil dengan approval

---

## 4. ALUR DATA UTAMA

### 4.1 Alur Stok

```
StockMovement (log setiap pergerakan)
    │
    ├── Source: DeliveryOrder, PurchaseReceipt, StockTransfer,
    │           StockAdjustment, StockOpname, MaterialIssue, Production
    │
    ▼
InventoryStock (current qty per product per warehouse)
    ├── qty_available (stok tersedia)
    ├── qty_reserved (direservasi oleh SO yang belum DO)
    └── qty_min (minimum stok)
```

### 4.2 Alur Jurnal

```
Setiap transaksi bisnis ──► JournalEntry records
    (via Observer atau Service)        │
                                       ├── debit side
                                       └── credit side
                                       
Validated: sum(debit) == sum(credit) [JournalValidationTrait]
                                       │
                                       ▼
                              ChartOfAccount balances
                          (digunakan untuk Balance Sheet,
                           P&L, Cash Flow, Buku Besar)
```

### 4.3 Alur Permission

```
User (authenticate) ──► Role (Spatie)
     │                     │
     │                     └── Permissions (per module per action)
     │
     ├── cabang_id (branch scoping)
     │       └── CabangScope auto-filters all queries
     │
     └── warehouse_id (default warehouse)
```

---

## 5. KONFIGURASI & ENVIRONMENT

### 5.1 Dependency Utama

| Package | Versi | Fungsi |
|---------|-------|--------|
| `laravel/framework` | ^12.0 | Core framework |
| `filament/filament` | ^3.3 | Admin panel |
| `livewire/volt` | ^1.7.0 | Single-file Livewire components |
| `livewire/flux` | ^2.1.1 | Flux UI components |
| `spatie/laravel-permission` | ^6.19 | RBAC |
| `spatie/laravel-activitylog` | * | Audit trail |
| `maatwebsite/excel` | ^3.1 | Excel import/export |
| `barryvdh/laravel-dompdf` | ^3.1 | PDF generation |
| `milon/barcode` | ^12.0 | Barcode |
| `saade/filament-autograph` | ^3.2 | Tanda tangan digital |

### 5.2 Konfigurasi Database

```
Default: MySQL
Host: (env: DB_HOST)
Database: u1605090_duta_tunggal
Charset: utf8mb4
Collation: utf8mb4_unicode_ci
Testing: SQLite (in-memory)
```

### 5.3 Konfigurasi Penting

| Config File | Setting Kunci |
|-------------|---------------|
| `config/app.php` | timezone=Asia/Jakarta, locale=id |
| `config/cashflow.php` | Cash flow sections configuration |
| `config/hpp.php` | HPP/COGS calculation config |
| `config/procurement.php` | Procurement workflow config |
| `database/schema/mysql-schema.sql` | Squashed base schema |

---

## 6. STRUKTUR FILE APLIKASI

### 6.1 Direktori Utama

```
app/
├── Console/          # Artisan commands
├── Events/           # Laravel events
├── Exports/          # 14 Excel/PDF export classes
├── Filament/
│   ├── Pages/        # Custom Filament pages (dashboard, reports)
│   ├── Resources/    # 65+ Filament CRUD resources
│   │   └── Reports/  # 8 report resources
│   └── Widgets/      # Dashboard widgets
├── Forms/            # Reusable Filament form components
├── Helpers/          # Global helper functions
├── Http/
│   ├── Controllers/  # HTTP controllers (7 files)
│   └── Middleware/   # Custom middleware
├── Infolists/        # Custom Filament infolist entries
├── Listeners/        # Event listeners
├── Livewire/         # Livewire components
│   ├── Auth/         # Authentication forms
│   └── Settings/     # User settings pages
├── Models/
│   ├── Scopes/       # Global query scopes (CabangScope)
│   └── Reports/      # Report configuration models
├── Notifications/    # Email/database notifications
├── Observers/        # 30+ model observers
├── Policies/         # 80+ authorization policies
├── Providers/        # Service providers
├── Rules/            # Custom validation rules
├── Services/         # 50+ business logic services
│   └── Reports/      # 2 report services
└── Traits/           # Reusable traits
```

---

## 7. KEAMANAN (SECURITY)

### 7.1 Authentication & Authorization

- **Authentication:** Filament's built-in auth (form di `/admin/login`)
- **Session Management:** PHP sessions dengan CSRF protection
- **Authorization:** Spatie Permission (roles & permissions per action)
- **Branch Scoping:** `CabangScope` memastikan user hanya melihat data cabangnya
- **Policy Layer:** 80+ policy files memproteksi setiap resource
- **Password Policy:** Laravel default (bcrypt)

### 7.2 Role Hierarchy

| Role | Deskripsi |
|------|-----------|
| `superadmin` | Akses penuh, bypass semua permission |
| `admin` | Akses penuh dalam cabang |
| `owner` | Akses approve + view financial data |
| `pimpinan` | Akses laporan dan approval |
| `manager` | Manajemen operasional |
| `Finance Manager` | Akses modul keuangan |
| `kepala gudang` | Manajemen gudang & stok |
| `gudang` | Operator gudang |
| `Sales` | Modul penjualan (PPN di-lock) |
| `staff` | Akses terbatas |

### 7.3 Kerentanan yang Perlu Diperhatikan

1. **Dusk routes aktif di production** — `_dusk/login/{userId}` teregistrasi selalu (harus hanya di testing)
2. **Debug routes** — `exports/download/{filename}` hanya tersedia di `local` env ✅ 
3. **Input validation** — Beberapa form menggunakan `nullable()` tanpa validasi format yang ketat

---

## 8. INTEGRASI & OUTPUT

### 8.1 Output Dokumen

| Dokumen | Format | Controller/Resource |
|---------|--------|---------------------|
| Invoice Penjualan | PDF (DomPDF) | SalesInvoiceResource |
| Surat Jalan | PDF | SuratJalanResource |
| Rekap Driver | PDF | SuratJalanResource |
| Balance Sheet | Excel + PDF | BalanceSheetExport |
| P&L / Laba Rugi | Excel + PDF | IncomeStatementExport |
| Ageing Schedule | Excel + PDF | AgeingReportExport |
| Kartu Persediaan | Excel + PDF | InventoryCardController |
| Stock Report | HTML Preview | StockReportController |
| Purchase Report | Excel | PurchaseReportExport |
| Sales Report | Excel | SalesReportExport |
| HPP Report | Custom | HppResource |

### 8.2 Notifikasi

- **Database notifications** — Filament notification bell
- **Jenis notifikasi:** Journal created/updated/deleted, Voucher approval, SJ approved

---

## 9. STATUS DEPLOYMENT

### 9.1 Lingkungan

| Environment | Database | URL |
|-------------|----------|-----|
| Production | MySQL (`u1605090_duta_tunggal`) | dutatunggal.digi-biosportex.site |
| Local Dev | MySQL/SQLite | localhost |
| Testing | SQLite (in-memory) | CLI |

### 9.2 Perintah Penting

```bash
php artisan migrate              # Jalankan migrasi
php artisan migrate:fresh --seed # Reset + seed data
php artisan test                 # Jalankan semua test
php artisan test --filter=Feature # Jalankan feature tests
php artisan filament:optimize    # Cache Filament
php artisan storage:link         # Link storage folder
```

---

## 10. RINGKASAN STATUS SISTEM

### 10.1 Fitur Implementasi

| Modul | Status | Catatan |
|-------|--------|---------|
| Penjualan (Quotation → Invoice) | ✅ Lengkap | Multi-step approval, PPN handling |
| Pengadaan (OR → PO → GRN → Invoice) | ✅ Lengkap | Multi-supplier, QC integration |
| Manufaktur (BOM → MO → Production) | ✅ Lengkap | Journal otomatis |
| Inventori (Stok, Transfer, Opname) | ✅ Lengkap | Real-time tracking |
| Akuntansi (COA, Jurnal, AR/AP) | ✅ Lengkap | Double-entry validated |
| Pelaporan (B/S, P&L, Cash Flow) | ✅ Lengkap | Excel + PDF export |
| Aset (Depresiasi, Disposal, Transfer) | ✅ Lengkap | Straight-line depreciation |
| Retur Customer | ✅ Baru (Mar '26) | QC-based lifecycle |
| Retur Pembelian | ✅ Lengkap | QC-triggered automation |
| RBAC & Multi-Cabang | ✅ Lengkap | Spatie + CabangScope |

### 10.2 Metrik Codebase

| Metrik | Angka |
|--------|-------|
| Total file PHP di app/ | 716 |
| Model classes | ~70 |
| Filament Resources | ~65 |
| Service classes | ~50 |
| Observer classes | ~30 |
| Policy classes | ~80 |
| Migration files | 45 (+ squashed schema) |
| Test files (Feature+Unit) | 175+ |
| Total test cases | 2,544 |

---

*Dokumen ini merupakan konteks sistem lengkap untuk Duta Tunggal ERP per 14 Maret 2026.*
