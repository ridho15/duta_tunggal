# Dokumentasi Modul Inventory / Gudang

**Versi Dokumen:** 1.0  
**Tanggal:** 30 Maret 2026  
**Aplikasi:** Duta Tunggal ERP

---

## 1. Gambaran Umum

Modul **Inventory / Gudang** mengelola seluruh pergerakan dan status stok barang di gudang — mulai dari master data gudang, stock record per produk per gudang/rak, transfer stok antar gudang, opname fisik, penyesuaian stok, warehouse confirmation, hingga retur barang. Modul ini adalah inti dari semua gerakan fisik barang dalam ERP.

### Alur Bisnis Utama

```
Warehouse Master Data (Gudang & Rak)
          ↓
Inventory Stock (Stok per produk + gudang + rak)
          ↓
Stock Movements (Semua pergerakan: purchase_in, sales, transfer, etc.)
          ↓
Stock Adjustment / Stock Transfer / Stock Opname
```

---

## 2. Sub-Modul & Fitur

### 2.1 Warehouse Management (Manajemen Gudang)

**File:** `app/Filament/Resources/WarehouseResource.php`  
**Navigasi:** Grup `Master Data`, Sort 5

#### Tujuan
Master data gudang fisik beserta propertinya. Setiap gudang dapat memiliki banyak rak (Rak).

#### Field Formulir

| Field | Keterangan |
|---|---|
| `kode` | Kode gudang unik, auto-generate (`WarehouseService::generateKodeGudang()` → `GD-YYYYMMDD-XXXX`) |
| `name` | Nama gudang |
| `cabang_id` | Cabang (hanya visible untuk `manage_type = all`) |
| `tipe` | `Kecil` / `Besar` |
| `location` | Alamat fisik |
| `telepon` | Nomor telepon (validasi regex) |
| `status` | Aktif/Nonaktif (checkbox) |
| `warna_background` | Warna latar untuk identifikasi visual di UI |

**Relation Manager:** `RakRelationManager` — kelola rak-rak dalam gudang ini.

---

### 2.2 Inventory Stock (Stok Persediaan)

**File:** `app/Filament/Resources/InventoryStockResource.php`  
**Navigasi:** Grup `Gudang`, Sort 4

#### Tujuan
Record master stok per kombinasi produk + gudang + rak. Ini adalah "buku besar" stok yang selalu diperbarui oleh setiap transaksi yang menyentuh persediaan.

#### Field Formulir

| Field | Keterangan |
|---|---|
| `product_id` | Produk (pencarian via SKU/nama) |
| `warehouse_id` | Gudang (difilter per cabang user) |
| `qty_available` | Stok tersedia (bebas) |
| `qty_reserved` | Stok yang direservasi (committed, belum dikirim) |
| `qty_min` | Threshold minimum untuk alert reorder |
| `rak_id` | Rak dalam gudang (opsional) |

**Validasi:** Kombinasi `product_id + warehouse_id + rak_id` harus unik.

**Relation Manager:** `StockMovementRelationManager` — tampilkan semua gerakan stok untuk record ini.

**Scoping:** Pengguna non-admin hanya melihat gudang dalam `cabang_id` mereka.

---

### 2.3 Stock Movement (Pergerakan Stok)

**File:** `app/Filament/Resources/StockMovementResource.php`  
**Navigasi:** Grup `Gudang`, Sort 5

#### Tujuan
Ledger/log dari semua perubahan qty stok dari semua sumber. Bersifat read-mostly (audit trail persediaan).

#### Field Formulir

| Field | Keterangan |
|---|---|
| `product_id` | Produk yang bergerak |
| `warehouse_id` | Gudang |
| `rak_id` | Rak |
| `quantity` | Jumlah pergerakan (positif) |
| `type` | Tipe gerakan (lihat tabel di bawah) |
| `reference_id` | Nomor referensi bebas |
| `from_model_type` / `from_model_id` | Model sumber (polymorphic) |
| `date` | Tanggal dan jam gerakan |
| `notes` | Catatan |

#### Tipe Gerakan Stok

| Tipe | Keterangan | Efek pada qty_available |
|---|---|---|
| `purchase_in` | Penerimaan dari pembelian | ↑ Naik |
| `sales` | Pengiriman ke pelanggan | ↓ Turun |
| `transfer_in` | Diterima dari transfer gudang | ↑ Naik |
| `transfer_out` | Dikirim ke transfer gudang | ↓ Turun |
| `manufacture_in` | Retur material ke gudang | ↑ Naik |
| `manufacture_out` | Pengambilan material untuk produksi | ↓ Turun |
| `adjustment_in` | Penyesuaian penambahan stok | ↑ Naik |
| `adjustment_out` | Penyesuaian pengurangan stok | ↓ Turun |
| `customer_return` | Retur dari pelanggan | ↑ Naik |

**Catatan:** Tidak ada aksi yang mengubah status. Ini murni tabel audit/ledger.

---

### 2.4 Stock Transfer (Transfer Stok Antar Gudang)

**File:** `app/Filament/Resources/StockTransferResource.php`  
**Navigasi:** Grup `Gudang`, Sort 2  
**Nomor Dokumen:** Format `TN-YYYYMMDD-XXXX`

#### Tujuan
Mengelola pemindahan stok antar gudang dengan workflow persetujuan tiga tahap.

#### Field Formulir

| Field | Keterangan |
|---|---|
| `transfer_number` | Nomor transfer, auto-generate |
| `transfer_date` | Tanggal dan jam transfer |
| `from_warehouse_id` | Gudang asal (semua cabang visible) |
| `to_warehouse_id` | Gudang tujuan (exclude gudang asal) |
| **Repeater: stockTransferItem** | Satu baris per produk |
| ↳ `product_id` | Produk |
| ↳ `quantity` | Jumlah yang ditransfer |
| ↳ `from_rak_id` | Rak asal |
| ↳ `to_warehouse_id` | Gudang tujuan per item |
| ↳ `to_rak_id` | Rak tujuan |

#### Aksi / Actions

| Aksi | Syarat | Efek |
|---|---|---|
| **Request Transfer** | Status `Draft` + permission `request stock transfer` | `StockTransferService::requestTransfer()` → status `Request` |
| **Approve** | Status `Request` + permission `response stock transfer` | `StockTransferService::approveStockTransfer()` → buat StockMovement; status `Approved` |
| **Reject** | Status `Request` + permission | Status → `Reject` |

#### Status Workflow

```
Draft → Request → Approved
               ↘ Reject
```

#### Observer (`StockTransferItemObserver`)

| Event | Aksi |
|---|---|
| `created` | Buat 2 StockMovement (`transfer_out` dari asal, `transfer_in` ke tujuan); sesuaikan `qty_available` langsung |
| `updated` → qty berubah | Balikkan penyesuaian lama; hapus + buat ulang StockMovements |
| `deleted` | Hapus StockMovements terkait; balikkan penyesuaian inventory |

---

### 2.5 Stock Adjustment (Penyesuaian Stok)

**File:** `app/Filament/Resources/StockAdjustmentResource.php`  
**Navigasi:** Grup `Gudang`, Sort 3  
**Nomor Dokumen:** Auto-generate via `generateAdjustmentNumber()`

#### Tujuan
Koreksi manual qty stok (penambahan atau pengurangan) — misalnya untuk barang rusak, hilang, atau temuan fisik.

#### Field Formulir

| Field | Keterangan |
|---|---|
| `adjustment_number` | Nomor penyesuaian, auto-generate |
| `adjustment_date` | Tanggal efektif |
| `warehouse_id` | Gudang target (difilter per cabang) |
| `adjustment_type` | `increase` (+) / `decrease` (-) |
| `reason` | Alasan penyesuaian (wajib) |
| `notes` | Catatan tambahan |
| `status` | `draft` / `approved` / `rejected` |

**Relation Manager:** `StockAdjustmentItemsRelationManager` — item produk + qty per rak.

#### Status Workflow

```
draft → approved
     ↘ rejected
```

**Saat approved:** Item menghasilkan `StockMovement` tipe `adjustment_in` atau `adjustment_out` sesuai `adjustment_type`.

---

### 2.6 Stock Opname (Inventarisasi Fisik)

**File:** `app/Filament/Resources/StockOpnameResource.php`  
**Navigasi:** Grup `Gudang`, Sort 6

#### Tujuan
Proses penghitungan stok fisik dan perbandingannya dengan stok sistem per gudang.

#### Field Formulir

| Field | Keterangan |
|---|---|
| `opname_number` | Nomor opname (manual) |
| `opname_date` | Tanggal penghitungan, default hari ini |
| `warehouse_id` | Gudang (difilter per cabang) |
| `status` | `draft` / `in_progress` / `completed` / `approved` |
| `notes` | Catatan |
| `approved_by` / `approved_at` | Read-only, visible hanya jika sudah approved |

**Relation Manager:** `StockOpnameItemsRelationManager` — per produk: qty sistem, qty fisik, selisih, biaya.

#### Aksi / Actions

| Aksi | Syarat | Efek |
|---|---|---|
| **Approve** | Status `completed` | Status → `approved`; catat `approved_by/at`; dapat trigger StockAdjustment |

#### Status Workflow

```
draft → in_progress → completed → approved
```

---

### 2.7 Warehouse Confirmation (Konfirmasi Gudang)

**File:** `app/Filament/Resources/WarehouseConfirmationResource.php`  
**Navigasi:** Grup `Gudang`, Sort 1

#### Tujuan
Konfirmasi dari tim gudang atas ketersediaan stok untuk memenuhi Sales Order, Manufacturing Order, atau Delivery Order sebelum proses pengiriman/produksi dimulai.

#### Field Formulir

| Field | Keterangan |
|---|---|
| `confirmation_type` | Radio: `sales_order` / `manufacturing_order` / `delivery_order` |
| `so_id_virtual` | SO referensi (approved, tipe pengiriman: direct/self-collect) |
| `mo_id_virtual` | MO referensi |
| `do_id_virtual` | DO referensi |
| **Repeater: confirmation_items** | Per item SO/MO/DO |
| ↳ `product_name` | Nama produk (disabled) |
| ↳ `requested_qty` | Qty yang diminta (disabled) |
| ↳ `confirmed_qty` | Qty yang dikonfirmasi |
| ↳ `warehouse_id` | Gudang asal |
| ↳ `rak_id` | Rak |
| ↳ `status` | Auto-hitung berdasarkan qty |
| `note` | Catatan |

**Aturan Status Item Otomatis:**
- `confirmed_qty == requested_qty` → `confirmed`
- `0 < confirmed_qty < requested_qty` → `partial_confirmed`
- `confirmed_qty == 0` → `rejected`

#### Aksi / Actions

| Aksi | Syarat | Efek |
|---|---|---|
| **Approve** | Status `request` | Status → `confirmed`; catat `confirmed_by/at`; trigger update DO terkait |
| **Reject** | Status `request` | Form `rejection_reason`; status → `rejected` |

#### Status Workflow

```
request → confirmed / partial_confirmed / rejected
```

---

## 3. Observers & Events (Gudang)

| Observer | Event | Aksi yang Dipicu |
|---|---|---|
| `StockMovementObserver` | `created` (tipe in) | `InventoryStock.qty_available` ↑ |
| `StockMovementObserver` | `created` (tipe out) | `InventoryStock.qty_available` ↓ |
| `StockMovementObserver` | `updated` → qty berubah | Sesuaikan delta ke `qty_available` |
| `StockMovementObserver` | `deleted` | Balikkan efek (in → ↓, out → ↑) |
| `StockReservationObserver` | `created` | `qty_reserved` ↑; `qty_available` ↓ |
| `StockReservationObserver` | `updated` → qty naik | Sesuaikan reserved/available |
| `StockReservationObserver` | `deleted` | `qty_reserved` ↓; `qty_available` ↑ (rilis reservasi) |
| `StockTransferItemObserver` | `created` | Buat 2 StockMovements; sesuaikan qty_available asal & tujuan |
| `StockTransferItemObserver` | `updated` → qty berubah | Balikkan + buat ulang StockMovements |
| `StockTransferItemObserver` | `deleted` | Hapus StockMovements; balikkan qty_available |
| `InventoryStockObserver` | `deleted` | Hapus semua JournalEntry tipe `opening_balance` untuk record ini |

**Catatan Penting `StockMovementObserver`:**
- Semua penyesuaian berjalan dalam `DB::transaction` dengan `lockForUpdate()` untuk menghindari race condition
- Auto-buat `InventoryStock` jika belum ada
- Flag `skip_stock_update = true` di meta mencegah dual-deduction

---

## 4. Services

| Service | Method | Fungsi |
|---|---|---|
| `StockTransferService` | `generateTransferNumber()` | Generate `TN-YYYYMMDD-XXXX` |
| `StockTransferService` | `requestTransfer(stockTransfer)` | Set status = Request |
| `StockTransferService` | `approveStockTransfer(stockTransfer)` | Buat StockMovements per item; set Approved |
| `StockReservationService` | `reserveStockForMaterialIssue(mi)` | Lock qty untuk Material Issue |
| `StockReservationService` | `consumeReservedStockForMaterialIssue(mi)` | Konsumsi reservasi (deduct actual) |
| `WarehouseService` | `generateKodeGudang()` | Generate `GD-YYYYMMDD-XXXX` |

---

## 5. Alur Pergerakan Stok Lengkap

```
PENERIMAAN BARANG (Purchase)
    PurchaseReceipt → QC lulus
    → StockMovement (purchase_in)
    → StockMovementObserver → qty_available ↑

PENJUALAN (Sales)
    DeliveryOrder approved
    → StockReservation dibuat
    → qty_available ↓, qty_reserved ↑
    
    DeliveryOrder sent
    → StockReservation dihapus
    → qty_reserved ↓, qty_available ↑
    → JournalEntry (Dr Barang Dalam Pengiriman / Cr Persediaan)
    
    DeliveryOrder completed
    → StockMovement (sales)
    → qty_available ↓

PRODUKSI (Manufacturing)
    MaterialIssue completed
    → StockMovement (manufacture_out) per item
    → qty_available ↓
    
    QC Manufacture diproses
    → Barang jadi masuk stok
    → qty_available ↑

TRANSFER STOK
    StockTransfer approved
    → StockMovement (transfer_out dari asal)
    → StockMovement (transfer_in ke tujuan)
    → qty_available asal ↓; qty_available tujuan ↑

PENYESUAIAN
    StockAdjustment approved
    → StockMovement (adjustment_in / adjustment_out)
    → qty_available ± delta

RETUR PELANGGAN
    CustomerReturn completed (decision=replace)
    → StockMovement (customer_return)
    → qty_available ↑
```

---

## 6. Permissions

| Permission | Fungsi |
|---|---|
| `view any inventory stock` | Lihat stok persediaan |
| `create stock adjustment` | Buat penyesuaian stok |
| `approve stock adjustment` | Setujui penyesuaian stok |
| `request stock transfer` | Ajukan transfer stok |
| `response stock transfer` | Approve/Reject transfer stok |
| `approve warehouse` | Approve Material Issue / Warehouse Confirmation |
| `approve stock opname` | Setujui stock opname |
| `view stock movement` | Lihat pergerakan stok |
