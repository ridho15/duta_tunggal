# Dokumentasi Modul Delivery Order (Pengiriman Barang)

**Versi Dokumen:** 1.0  
**Tanggal:** 30 Maret 2026  
**Aplikasi:** Duta Tunggal ERP

---

## 1. Gambaran Umum

Modul **Delivery Order (DO)** mengelola seluruh proses pengiriman barang dari gudang ke pelanggan. Modul ini mencakup pembuatan Delivery Order dari Sales Order, penjadwalan pengiriman ke driver/ekspedisi, penerbitan Surat Jalan (SJ), pencatatan konfirmasi penerimaan, serta pencatatan jurnal otomatis untuk posting kartu stok dan General Ledger.

### Alur Utama
```
Sale Order (Confirmed)
  ↓
Delivery Order (draft → request_stock → approved → sent → received → completed)
  ↓
Delivery Schedule (pending → on_the_way → delivered / partial_delivered / failed)
  ↓
Surat Jalan (draft → sent)
```

---

## 2. Sub-Modul & Fitur

### 2.1 Delivery Order

**File:** `app/Filament/Resources/DeliveryOrderResource.php`  
**Service:** `app/Services/DeliveryOrderService.php`  
**Observer:** `app/Observers/DeliveryOrderObserver.php`  
**Navigasi:** Grup `Logistik`

#### Tujuan
Dokumen utama pengiriman. Satu Delivery Order dapat menampung item dari beberapa Sales Order sekaligus, dan satu item dapat diambil dari beberapa gudang (multi-source).

#### Nomor Dokumen
Format: `DO-YYYYMMDD-0001` (auto-generate via `DeliveryOrderService::generateDoNumber()`)

#### Field Formulir

| Field | Keterangan |
|---|---|
| `do_number` | Nomor DO, auto-generate |
| `date` | Tanggal DO (default hari ini) |
| `planned_date` | Tanggal rencana pengiriman |
| `cabang_id` | Cabang |
| `customer_id` | Pelanggan; auto-isi dari SO |
| `delivery_address` | Alamat pengiriman (dropdown dari alamat customer atau ketik manual) |
| `notes` | Catatan |
| **Repeater Items (sumber SO):** | |
| ↳ `sale_order_id` | Pilih Sales Order yang sudah Confirmed |
| ↳ `sale_order_item_id` | Item dari SO yang dipilih |
| ↳ `qty_to_deliver` | Jumlah yang dikirim (≤ qty outstanding di SO) |
| ↳ `uom_id` | Satuan |
| **Repeater Stock Sources (per item):** | |
| ↳ `warehouse_id` | Gudang sumber |
| ↳ `qty` | Jumlah dari gudang tersebut |

#### Aksi / Actions

| Aksi | Syarat | Efek |
|---|---|---|
| **Request Stock** | Status `draft` | Status → `request_stock`; notifikasi ke gudang |
| **Approve** | Status `request_stock` | `DeliveryOrderObserver::approved()` → `createStockReservation()`; status → `approved` |
| **Mark as Sent** | Status `approved` | `DeliveryOrderObserver::sent()` → post jurnal + release reservasi + update `delivered_qty` SO; status → `sent` |
| **Confirm Received** | Status `sent` | Status → `received`; customer konfirmasi penerimaan |
| **Complete** | Status `received` | `DeliveryOrderObserver::completed()` → buat StockMovement (sales); cek jika SO selesai sepenuhnya → status SO → `completed` |
| **Download PDF** | Semua status | Unduh format Surat Jalan dari DO ini |
| **Cancel** | Sebelum `sent` | Batalkan; hapus reservasi stok |

#### Status Workflow
```
draft → request_stock → approved → sent → received → completed
                                              ↓
                                          (cancelled)
```

#### Validasi
- `validateStockAvailability()`: pastikan total qty sumber ≥ qty_to_deliver; cek `InventoryStock.qty_available` per gudang dengan `lockForUpdate()`.
- qty_to_deliver tidak boleh melebihi sisa outstanding dari SO.

---

### 2.2 Delivery Schedule (Jadwal Pengiriman)

**File:** `app/Filament/Resources/DeliveryScheduleResource.php`  
**Service:** `DeliveryScheduleService`  
**Observer:** `app/Observers/DeliveryScheduleObserver.php`  
**Navigasi:** Grup `Logistik`

#### Tujuan
Mengelompokkan beberapa Delivery Order ke dalam satu jadwal pengiriman per driver dan kendaraan.

#### Field Formulir

| Field | Keterangan |
|---|---|
| `schedule_number` | Nomor jadwal, auto-generate |
| `schedule_date` | Tanggal pengiriman |
| `delivery_method` | `internal` / `kurir_internal` / `ekspedisi` |
| `driver_name` | Nama driver/kurir |
| `driver_phone` | Kontak driver |
| `vehicle_id` | Kendaraan yang digunakan |
| `ekspedisi_name` | Nama ekspedisi (visible hanya jika metode `ekspedisi`) |
| `awb_number` | Nomor resi (ekspedisi) |
| `notes` | Catatan |
| **Repeater DOs:** | |
| ↳ `delivery_order_id` | Pilih DO yang sudah `approved` |

#### Aksi / Actions

| Aksi | Syarat | Efek |
|---|---|---|
| **Depart / Start** | Status `pending` | Status → `on_the_way`; catat waktu berangkat |
| **Mark as Delivered** | Status `on_the_way` | Status → `delivered`; update seluruh DO dalam jadwal ke `received` |
| **Partial Delivered** | Status `on_the_way` | Status → `partial_delivered`; semua DO yang dikonfirmasi → `received`, sisanya tetap `approved` |
| **Failed** | Status `on_the_way` | Status → `failed`; reset DO ke `approved` |
| **Cancel** | Status `pending` | Status → `cancelled` |

#### Status Workflow
```
pending → on_the_way → delivered
                    → partial_delivered
                    → failed
                    → cancelled
```

---

### 2.3 Surat Jalan (SJ)

**File:** `app/Filament/Resources/SuratJalanResource.php`  
**Service:** `app/Services/SuratJalanService.php`  
**Navigasi:** Grup `Logistik`

#### Tujuan
Dokumen resmi surat jalan yang menemani pengiriman fisik. SJ dapat dibuat dari satu atau beberapa DO dalam satu jadwal.

#### Nomor Dokumen
Format: `SJ-YYYYMMDD-XXXX` (auto-generate via `SuratJalanService::generateCode()`)

#### Field Formulir

| Field | Keterangan |
|---|---|
| `sj_number` | Nomor SJ, auto-generate |
| `date` | Tanggal SJ |
| `delivery_order_ids` | Link ke satu atau beberapa DO (multi-select, status `approved` atau lebih) |
| `delivery_schedule_id` | Jadwal pengiriman terkait (opsional) |
| `customer_id` | Pelanggan; auto-isi dari DO |
| `driver_id` / `driver_name` | Driver pengantar |
| `vehicle_id` | Kendaraan |
| `notes` | Catatan |
| **Items (auto-populate dari DO):** | |
| ↳ Nama produk | |
| ↳ Qty dikirim | |
| ↳ Satuan | |

#### Aksi / Actions

| Aksi | Syarat | Efek |
|---|---|---|
| **Mark as Sent** | Status `draft` | Status → `sent`; DO terkait di-trigger ke status `sent` jika belum |
| **Download PDF** | Semua status | Unduh Surat Jalan sebagai PDF (template resmi) |
| **Print** | Semua status | Render cetak langsung |

#### Status Workflow
```
draft → sent
```

---

## 3. Observer & Side Effects

### 3.1 DeliveryOrderObserver

| Event | Kondisi | Aksi |
|---|---|---|
| `updated` → status = `approved` | — | `createStockReservation()`: LOOP setiap DO item → `$stock->lockForUpdate()` per (product+warehouse) → tambah `qty_reserved`; kurangi `qty_available` |
| `updated` → status = `sent` | — | (1) `postDeliveryOrder()` → buat JournalEntry; (2) `releaseStockReservations()` → kurangi `qty_reserved`; (3) tambah `delivered_quantity` pada SO item |
| `updated` → status = `completed` | — | `createStockMovement()`: StockMovement type `sales`; kurangi `qty_on_hand`; cek SO → jika semua item delivered → status SO → `completed` |
| `deleting` | Status `sent`/`completed` | Throw exception; DO tidak dapat dihapus |

### 3.2 DeliveryOrderItemObserver

| Event | Aksi |
|---|---|
| `creating` | Validasi `qty_to_deliver ≤ outstanding_qty` dari SO item |
| `deleting` | Hapus stock reservasi terkait item ini |

### 3.3 DeliveryScheduleObserver

| Event | Aksi |
|---|---|
| `updated` → status = `delivered` | Update semua DO dalam schedule → status `received` |
| `updated` → status = `partial_delivered` | Update DO yang dikonfirmasi → `received` |
| `updated` → status = `failed` | Reset DO → status `approved` |

---

## 4. Services

### 4.1 DeliveryOrderService

| Method | Fungsi |
|---|---|
| `generateDoNumber()` | Generate `DO-YYYYMMDD-0001` dengan sequence per hari |
| `validateStockAvailability(do)` | Cek ketersediaan stok per gudang; throw exception jika tidak cukup |
| `updateStatus(do, status)` | Gate keeper perubahan status; trigger observer side-effects |
| `createStockReservation(do)` | Kunci stok (lockForUpdate) per item per gudang; tambah `qty_reserved` |
| `releaseStockReservations(do)` | Lepaskan reservasi; kurangi `qty_reserved` |
| `postDeliveryOrder(do)` | Entry point posting DO ke GL |
| `createJournalEntriesForDelivery(do)` | Buat JournalEntry untuk setiap item; double-entry HPP ke GL |

### 4.2 SuratJalanService

| Method | Fungsi |
|---|---|
| `generateCode()` | Generate `SJ-YYYYMMDD-XXXX` dengan sequence per hari |
| `createFromDeliveryOrder(do)` | Buat SJ otomatis dari satu DO |
| `markAsSent(sj)` | Update status SJ → sent; sync DO terkait |

---

## 5. Pola Jurnal Akuntansi

Jurnal dibuat otomatis saat status DO berubah menjadi `sent` via `createJournalEntriesForDelivery()`.

### Jurnal HPP (Harga Pokok Penjualan)

```
Debit   Barang Dalam Pengiriman (COA 1140.20)   [qty × HPP per unit]
Credit  Persediaan Produk (COA 1140.10 atau COA produk) [qty × HPP per unit]
```

- `journal_type = sales`
- Tagged dengan `source_type = DeliveryOrder`, `source_id = id`
- `JournalBranchResolver` memastikan `cabang_id` dari DO dipakai

> Jurnal Revenue (piutang/pendapatan) dibuat di **Sales Invoice** pada saat invoice diterbitkan, bukan di DO.

---

## 6. Stock Reservation Flow

```
DO Approved
   ↓
Per (product_id + warehouse_id):
   InventoryStock.qty_reserved  += qty_to_deliver
   InventoryStock.qty_available -= qty_to_deliver

DO Sent (atau Cancelled)
   ↓
   InventoryStock.qty_reserved  -= qty_to_deliver

DO Completed
   ↓ (via StockMovement type=sales)
   InventoryStock.qty_on_hand   -= qty_to_deliver
```

---

## 7. Relasi Antar Model

| Model | Relasi |
|---|---|
| `DeliveryOrder` | `belongsTo` Customer, Cabang |
| `DeliveryOrder` | `hasMany` DeliveryOrderItem |
| `DeliveryOrder` | `belongsToMany` SaleOrder (through items) |
| `DeliveryOrder` | `morphMany` JournalEntry |
| `DeliveryOrder` | `morphMany` StockMovement |
| `DeliveryOrderItem` | `belongsTo` DeliveryOrder, SaleOrderItem, Product, UOM |
| `DeliveryOrderItem` | `hasMany` DeliveryOrderItemSource (per gudang) |
| `DeliverySchedule` | `hasMany` DeliveryOrder |
| `DeliverySchedule` | `belongsTo` Vehicle |
| `SuratJalan` | `belongsToMany` DeliveryOrder |
| `SuratJalan` | `belongsTo` DeliverySchedule |

---

## 8. Laporan Pengiriman

Modul Delivery Order menghasilkan data untuk laporan di modul Report:

| Laporan | Sumber Data |
|---|---|
| **Delivery Summary** | Count DO per periode, per customer, per cabang |
| **Outstanding DO** | DO dengan status `approved` atau `sent` belum `completed` |
| **On-Time Delivery Rate** | Pembanding `planned_date` vs `actual_delivery_date` |
| **Stock Movement Report** | StockMovement type `sales` dari DO completed |

---

## 9. PDF & Cetak

| Dokumen | Template | Trigger |
|---|---|---|
| Surat Jalan | `resources/views/pdf/surat-jalan.blade.php` | Action Download PDF di SuratJalanResource |
| Delivery Order | `resources/views/pdf/delivery-order.blade.php` | Action Download PDF di DeliveryOrderResource |

---

## 10. Permissions

| Permission | Fungsi |
|---|---|
| `create delivery order` | Buat DO baru |
| `approve delivery order` | Setujui DO (request stock → approved) |
| `send delivery order` | Ubah DO ke status sent |
| `complete delivery order` | Konfirmasi penerimaan → complete |
| `create delivery schedule` | Buat jadwal pengiriman |
| `create surat jalan` | Buat surat jalan |
| `view delivery report` | Lihat laporan pengiriman |
| `cancel delivery order` | Batalkan DO |
