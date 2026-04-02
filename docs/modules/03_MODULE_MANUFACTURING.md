# Dokumentasi Modul Manufacturing / Produksi

**Versi Dokumen:** 1.0  
**Tanggal:** 30 Maret 2026  
**Aplikasi:** Duta Tunggal ERP

---

## 1. Gambaran Umum

Modul **Manufacturing / Produksi** mengelola seluruh siklus produksi — mulai dari perencanaan (Production Plan), formula produksi (Bill of Material), pengambilan bahan baku (Material Issue), pelaksanaan produksi (Manufacturing Order & Production), hingga Quality Control barang jadi.

### Alur Bisnis Utama

```
Bill of Material (BOM) — Formula Produksi
           ↓
Production Plan (Rencana Produksi)
    ↓ [Jadwalkan]
Material Issue (Pengambilan Bahan Baku) — auto dibuat dari BOM
    ↓ [Request Approval → Approve → Selesai]
Manufacturing Order (MO) — auto dibuat setelah MI selesai
    ↓ [Produksi]
Production (Pelaksanaan Produksi)
    ↓ [Finished]
Quality Control Manufacture (QC) — auto dibuat setelah Production finished
    ↓ [Process QC]
Barang Jadi masuk ke Stok Gudang
```

---

## 2. Sub-Modul & Fitur

### 2.1 Bill of Material (BOM) — Formula Produksi

**File:** `app/Filament/Resources/BillOfMaterialResource.php`  
**Navigasi:** Grup `Manufacturing Order`, Sort 4  
**Nomor Dokumen:** Format `BOM-YYYYMMDD-XXXX`

#### Tujuan
Mendefinisikan "resep" produksi — material apa saja, berapa quantity, dan berapa biayanya untuk menghasilkan satu produk jadi.

#### Field Formulir

| Field | Keterangan |
|---|---|
| `code` | Kode BOM unik, auto-generate |
| `nama_bom` | Nama BOM |
| `cabang_id` | Cabang |
| `product_id` | Produk jadi (`is_manufacture = true`); auto-isi UOM dan konversi satuan |
| `uom_id` | Satuan untuk produk jadi |
| `quantity` | Quantity yang dihasilkan per run |
| `labor_cost` | Biaya tenaga kerja langsung |
| `overhead_cost` | Biaya overhead |
| `material_cost_display` | Total biaya bahan (read-only, auto-hitung) |
| `total_cost_display` | Total biaya = material + labor + overhead (read-only) |
| `work_in_progress_coa_id` | COA Pos Sementara Produksi (WIP) |
| `is_active` | Toggle aktif/nonaktif |
| **Repeater: Items (Bahan Baku)** | Satu baris per material |
| ↳ `product_id` | Produk bahan baku |
| ↳ `uom_id` | Satuan bahan |
| ↳ `quantity` | Jumlah yang dibutuhkan |
| ↳ `unit_price` | Harga satuan (auto-isi dari `cost_price`, read-only) |
| ↳ `subtotal` | Subtotal per baris (read-only) |

**Catatan:** Harga disesuaikan otomatis jika satuan yang dipilih adalah satuan alternatif dengan faktor konversi.

#### Table Columns
Code, Nama BOM, Cabang, Produk, Quantity, UOM, Biaya TKL, Overhead, Biaya Material, Total Biaya Produksi, COA Barang Jadi, COA WIP, Status Aktif, Daftar Material.

#### Aksi
View, Edit, Delete (standard). Tidak ada aksi status khusus.

---

### 2.2 Production Plan (Rencana Produksi)

**File:** `app/Filament/Resources/ProductionPlanResource.php`  
**Navigasi:** Grup `Manufacturing Order`, Sort 2, Label `Rencana Produksi`  
**Nomor Dokumen:** Format `PPYYYYMMDDxxxx`

#### Tujuan
Dokumen perencanaan produksi tingkat atas yang menggerakkan pembuatan Manufacturing Order dan Material Issue.

#### Field Formulir

| Field | Keterangan |
|---|---|
| `plan_number` | Nomor rencana, auto-generate |
| `name` | Nama pekerjaan |
| `source_type` | Radio: `sale_order` / `manual` |
| `sale_order_id` | SO referensi (hanya untuk tipe sale_order, SO approved/confirmed) |
| `bill_of_material_id` | BOM (hanya untuk tipe manual, BOM aktif) |
| `product_id` | Produk jadi (auto-isi dari BOM atau SO item) |
| `quantity` | Qty yang akan diproduksi |
| `uom_id` | Satuan |
| `warehouse_id` | Gudang produksi (untuk sumber material & penyimpanan barang jadi) |
| `start_date` / `end_date` | Jadwal produksi (`start_date` wajib < `end_date`) |
| `auto_schedule` | Checkbox: jika dicentang, status langsung ke `scheduled` |
| `notes` | Catatan |

#### Aksi / Actions

| Aksi | Syarat | Efek |
|---|---|---|
| **Jadwalkan** | Status `draft` | Validasi BOM; status → `scheduled`; auto-buat `MaterialIssue` dari BOM |
| **Cancel Plan** | Status `scheduled` / `in_progress` | Modal: lihat jumlah MO & MI terkait; status → `cancelled`; batalkan MO dan tolak MI draft |
| **Buat MO** | Status `scheduled`, belum ada MO | Modal: cek stok material; buat `ManufacturingOrder` dengan bahan dari BOM |

#### Status Workflow

```
draft → scheduled → in_progress → completed
                ↘ cancelled
```

- `scheduled` → `in_progress`: otomatis ketika MO terkait mulai
- `in_progress` → `completed`: otomatis ketika semua MO selesai

---

### 2.3 Manufacturing Order (MO)

**File:** `app/Filament/Resources/ManufacturingOrderResource.php`  
**Navigasi:** Grup `Manufacturing Order`, Sort 1  
**Nomor Dokumen:** Format `MO-YYYYMMDD-XXXX`

#### Tujuan
Instruksi produksi yang dibuat dari Production Plan. Menentukan material yang dibutuhkan dan jadwal produksi.

#### Field Formulir

| Field | Keterangan |
|---|---|
| `mo_number` | Nomor MO unik, auto-generate |
| `cabang_id` | Cabang |
| `production_plan_id` | Production Plan terkait (difilter ke status `in_progress`) |
| `start_date` / `end_date` | Jadwal produksi (auto-isi dari Plan) |
| **Repeater: Items** | Daftar material (read-only, dari MI selesai atau BOM) |
| ↳ `product_id`, `uom_id`, `quantity`, `notes` | Data material |

#### Aksi / Actions

| Aksi | Syarat | Efek |
|---|---|---|
| **Produksi** (Start Production) | Status `draft` + permission | Cek stok material; status → `in_progress`; auto-buat record `Production` |

#### Status Workflow

```
draft → in_progress → completed
```

#### Observer (`ManufacturingOrder`)

| Event | Aksi |
|---|---|
| `updated` → `in_progress` | Jika Production Plan masih `scheduled` → set ke `in_progress` |
| `updated` → `completed` | Cek semua MO dalam Plan; jika semua selesai → Plan → `completed` |

---

### 2.4 Material Issue (Pengambilan Bahan Baku)

**File:** `app/Filament/Resources/MaterialIssueResource.php`  
**Navigasi:** Grup `Manufacturing Order`, Sort 5, Label `Pengambilan Bahan Baku`  
**Nomor Dokumen:** Issue: `MI-YYYYMMDD-XXXX` / Return: `MR-YYYYMMDD-XXXX`

#### Tujuan
Dokumen pengambilan fisik bahan baku dari gudang untuk produksi. Mendukung tipe `issue` (ambil) dan `return` (kembalikan).

#### Field Formulir — Header

| Field | Keterangan |
|---|---|
| `issue_number` | Nomor MI/MR, auto-generate |
| `type` | Select: `issue` / `return` |
| `production_plan_id` | Rencana Produksi (untuk tipe issue); auto-load item BOM |
| `warehouse_id` | Gudang sumber/tujuan |
| `issue_date` | Tanggal (≤ hari ini) |

#### Field Formulir — Item Repeater

| Field | Keterangan |
|---|---|
| `product_id` | Bahan baku (`is_raw_material = true`) |
| `uom_id` | Satuan |
| `quantity` | Qty (dari BOM, read-only) |
| `cost_per_unit` | Biaya per unit (dari `cost_price`) |
| `warehouse_id` | Gudang per item (hanya gudang dengan stok tersedia) |
| `rak_id` | Rak (hanya rak dengan stok) |
| `available_stock_display` | Stok tersedia (hijau ≥ kebutuhan, merah < kebutuhan) |

#### Aksi / Actions

| Aksi | Syarat | Efek |
|---|---|---|
| **Request Approval** | Status `draft`, belum ada `approved_by` | Validasi stok; status → `pending_approval`; cari approver; notifikasi |
| **Approve** | Status `pending_approval` + permission `approve warehouse` | Validasi stok; catat approver; status → `approved` |
| **Reject** | Status `pending_approval` | Modal alasan; status → `draft`; hapus approver |
| **Selesai** | Status `approved` + permission | Validasi stok final; status → `completed` |
| **Generate Jurnal** | Status `completed`, belum ada jurnal | `ManufacturingJournalService::generateJournalForMaterialIssue()` |

#### Status Workflow

```
draft → pending_approval → approved → completed
                        ↘ rejected (kembali ke draft)
```

#### Observer (`MaterialIssueObserver`) — Sisi Efek Status

| Transisi | Aksi yang Dipicu |
|---|---|
| → `pending_approval` | Cascade semua item ke `pending_approval` |
| → `approved` | `StockReservationService::reserveStockForMaterialIssue()` |
| → `completed` | Items → completed; Konsumsi stok; Buat StockMovement; Buat Jurnal; Buat MO (jika belum ada); Buat ProductionCostEntry |

#### Integrasi Akuntansi (tipe issue, via `ManufacturingJournalService`)
```
Debit  WIP / BDP (1140.02 atau BOM.wip_coa)    [total_cost]
  Credit  Persediaan Bahan Baku (per item COA)  [cost per item]
```

#### Integrasi Akuntansi (tipe return)
```
Debit  Persediaan Bahan Baku (per item COA)     [cost per item]
  Credit  WIP / BDP (1140.02)                   [total]
```

---

### 2.5 Production (Pelaksanaan Produksi)

**File:** `app/Filament/Resources/ProductionResource.php`  
**Navigasi:** Grup `Manufacturing Order`, Sort 3  
**Nomor Dokumen:** Format `PRO-YYYYMMDD-XXXX`

#### Tujuan
Record pelaksanaan produksi aktual, terkait dengan Manufacturing Order. Dibuat otomatis oleh sistem dari MO.

#### Field Formulir

| Field | Keterangan |
|---|---|
| `production_number` | Nomor produksi, auto-generate |
| `manufacturing_order_id` | MO terkait (disabled, di-set otomatis) |
| `production_date` | Tanggal produksi (wajib) |

#### Aksi / Actions

| Aksi | Syarat | Efek |
|---|---|---|
| **Finished** | Status `draft` | Konfirmasi; set MO → `completed`; set Production → `finished`; notifikasi tim QC |

#### Status Workflow
`draft` → `finished`

#### Observer (`ProductionObserver`)

| Event | Aksi |
|---|---|
| `updated` → `finished` | Auto-buat QC (`QualityControlService::createQCFromProduction()`) |
| `updated` → `finished` | Cek semua Production di MO; jika semua finished → MO → `completed` |

---

### 2.6 Quality Control Manufacture (QC Produksi)

**File:** `app/Filament/Resources/QualityControlManufactureResource.php`  
**Navigasi:** Grup `Manufacturing Order`, Sort 6  
**Nomor Dokumen:** Format `QC-M-YYYYMMDD-XXXX`

#### Tujuan
Inspeksi kualitas barang jadi setelah produksi selesai. Hasilnya menentukan berapa qty barang masuk ke stok.

#### Field Formulir

| Field | Keterangan |
|---|---|
| `from_model_id` | Pilih Production yang sudah `finished`/`completed` (belum ada QC) |
| `qc_number` | Nomor QC, auto-generate |
| `product_name` | Nama produk (dari production, disabled) |
| `warehouse_id` | Gudang tempat barang jadi disimpan (auto-isi dari production) |
| `rak_id` | Rak dalam gudang |
| `passed_quantity` | Qty lolos QC |
| `rejected_quantity` | Qty ditolak (passed + rejected ≤ quantity_produced) |
| `total_inspected` | Total diperiksa (disabled, auto-hitung) |
| `inspected_by` | User pemeriksa |
| `date_send_stock` | Tanggal pengiriman ke stok |
| `notes` | Catatan umum |
| `reason_reject` | Alasan penolakan |

#### Aksi / Actions

| Aksi | Syarat | Efek |
|---|---|---|
| **Process QC** | Status = false (belum diproses) | `QualityControlService::completeQualityControl()` → update stok barang jadi; notifikasi tim gudang |

#### Status Workflow
`false` (Belum diproses) → `true` (Sudah diproses)

#### Integrasi Akuntansi (saat QC diproses, via `ManufacturingJournalService::generateJournalForProductionCompletion`)
```
Debit  Barang Jadi (1140.03 atau BOM.fg_coa)   [nilai total]
  Credit  WIP / BDP (1140.02 atau BOM.wip_coa) [nilai total]
```

---

## 3. Services

| Service | Method | Fungsi |
|---|---|---|
| `ManufacturingService` | `generateMoNumber()` | Generate nomor MO unik |
| `ManufacturingService` | `generateIssueNumber(type)` | Generate nomor MI/MR |
| `ManufacturingService` | `createMaterialIssueForProductionPlan()` | Auto-buat MI + items dari BOM |
| `ManufacturingService` | `checkStockMaterial(mo)` | Validasi stok bahan vs kebutuhan BOM |
| `ProductionService` | `generateProductionNumber()` | Generate PRO-YYYYMMDD-XXXX |
| `ProductionPlanService` | `generatePlanNumber()` | Generate PPYYYYMMDDxxxx |
| `ProductionPlanService` | `getSaleOrderOptions()` | Daftar SO approved/confirmed untuk dropdown |
| `ProductionPlanService` | `getBillOfMaterialOptions()` | Daftar BOM aktif untuk dropdown |
| `BillOfMaterialService` | `generateCode()` | Generate BOM-YYYYMMDD-XXXX |
| `ManufacturingJournalService` | `generateJournalForMaterialIssue()` | Jurnal Dr WIP / Cr Bahan Baku |
| `ManufacturingJournalService` | `generateJournalForMaterialReturn()` | Jurnal Dr Bahan Baku / Cr WIP |
| `ManufacturingJournalService` | `generateJournalForProductionCompletion()` | Jurnal Dr Barang Jadi / Cr WIP |
| `ManufacturingJournalService` | `allocateLaborAndOverhead()` | Alokasi biaya TKL & overhead ke WIP |
| `ManufacturingJournalService` | `getBDPBalance()` | Cek saldo WIP |

---

## 4. Observers & Events

| Observer | Event | Aksi yang Dipicu |
|---|---|---|
| `ManufacturingOrder` | `updated` → `in_progress` | ProductionPlan → `in_progress` |
| `ManufacturingOrder` | `updated` → `completed` | Cek semua MO; jika selesai → ProductionPlan → `completed` |
| `ProductionObserver` | `updated` → `finished` | Auto-buat QC dari production |
| `ProductionObserver` | `updated` → `finished` | Jika semua Production finished → MO → `completed` |
| `MaterialIssueObserver` | `updated` → `pending_approval` | Cascade items → `pending_approval` |
| `MaterialIssueObserver` | `updated` → `approved` | Reserve stok (`StockReservationService`) |
| `MaterialIssueObserver` | `updated` → `completed` | Konsumsi stok; StockMovements; Jurnal; Buat MO |
| `MaterialIssueItemObserver` | `updated` → `completed` | Jika semua item selesai → MI → `completed` |
| `MaterialIssueItemObserver` | `updated` → `pending_approval` | Jika semua item tidak draft → MI → `pending_approval` |

---

## 5. Pola Jurnal Akuntansi

| Transaksi | Debit | Kredit |
|---|---|---|
| Material Issue (ambil bahan) | WIP/BDP (1140.02) | Persediaan Bahan Baku (per COA item) |
| Material Return (kembalikan bahan) | Persediaan Bahan Baku | WIP/BDP (1140.02) |
| Production Completion (selesai produksi) | Barang Jadi (1140.03) | WIP/BDP (1140.02) |
| Labor & Overhead Allocation | WIP/BDP (1140.02) | COA Biaya/Kas |

### Hierarki COA (Fallback Otomatis)

| Tujuan | Hierarki Kode COA |
|---|---|
| WIP / BDP | BOM.wip_coa → 1140.02 → 1150 → 1140.03 → 1140 |
| Bahan Baku | Item COA → Product COA → 1140.10 → 1140.01 → 1140 |
| Barang Jadi | BOM.fg_coa → 1140.03 |

---

## 6. Alur Lengkap Manufacturing

```
1. BOM dibuat (formula produksi)
   
2. Production Plan dibuat
   → sumber: Sales Order atau manual
   
3. Production Plan [Jadwalkan]
   → status: scheduled
   → Material Issue otomatis dibuat dari BOM

4. Material Issue [Request Approval]
   → status: pending_approval
   → items: pending_approval
   
5. Material Issue [Approve]
   → status: approved
   → Stok bahan baku di-RESERVE

6. Material Issue [Selesai]
   → status: completed
   → Stok bahan baku DIKONSUMSI
   → StockMovement: manufacture_out per item
   → Jurnal: Dr WIP / Cr Bahan Baku
   → Manufacturing Order otomatis dibuat
   → ProductionCostEntry dibuat

7. Manufacturing Order [Produksi]
   → status: in_progress
   → Production record otomatis dibuat
   → ProductionPlan: in_progress

8. Production [Finished]
   → status: finished
   → MO: completed → ProductionPlan: completed
   → QualityControl otomatis dibuat

9. QualityControl [Process QC]
   → status: processed
   → Stok barang jadi masuk ke gudang
   → Jurnal: Dr Barang Jadi / Cr WIP
```

---

## 7. Permissions

| Permission | Fungsi |
|---|---|
| `view any manufacturing order` | Lihat daftar MO |
| `create manufacturing order` | Buat MO |
| `update manufacturing order` | Edit MO |
| `request manufacturing order` | Mulai produksi dari MO |
| `response manufacturing order` | Approve/respond MO |
| `approve warehouse` | Approve Material Issue |
