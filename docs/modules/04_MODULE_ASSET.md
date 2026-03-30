# Dokumentasi Modul Asset (Aset Tetap)

**Versi Dokumen:** 1.0  
**Tanggal:** 30 Maret 2026  
**Aplikasi:** Duta Tunggal ERP

---

## 1. Gambaran Umum

Modul **Asset Management** mengelola seluruh siklus hidup aset tetap perusahaan — dari pencatatan perolehan, perhitungan dan posting penyusutan bulanan, transfer antar cabang, hingga pelepasan (disposal) aset. Modul ini terintegrasi penuh dengan modul Akuntansi melalui jurnal entri otomatis.

### Alur Bisnis Utama

```
Aset diperoleh (manual atau dari PO Aset)
    ↓ [Post Jurnal Akuisisi]
Aset aktif (status: active)
    ↓ [Post Penyusutan Bulanan] — setiap bulan
Aset terdepresiasi penuh (status: fully_depreciated)
         atau
    ↓ [Transfer Antar Cabang]
Aset berpindah lokasi (status: completed)
         atau
    ↓ [Disposal / Pelepasan]
Aset dilepas (status: disposed)
```

---

## 2. Sub-Modul & Fitur

### 2.1 Asset (Aset Tetap)

**File:** `app/Filament/Resources/AssetResource.php`  
**Navigasi:** Grup `Asset Management`, Sort 1  
**Nomor Dokumen:** Format `AST-NNNN`

#### Tujuan
CRUD utama aset tetap — pencatatan, penghitungan penyusutan, dan integrasi akuntansi untuk setiap aset milik perusahaan.

#### Field Formulir — Informasi Aset

| Field | Keterangan |
|---|---|
| `code` | Kode aset unik, auto-generate (`AST-NNNN`) |
| `name` | Nama/deskripsi aset |
| `cabang_id` | Cabang (hidden untuk pengguna single-branch) |
| `purchase_date` | Tanggal pembelian (default hari ini) |
| `usage_date` | Tanggal mulai pakai (referensi awal penyusutan) |
| `product_id` | Produk terkait; auto-isi dari PO item terbaru |
| `depreciation_method` | Metode penyusutan: `straight_line` / `declining_balance` / `sum_of_years_digits` / `units_of_production` |
| `purchase_cost` | Biaya perolehan total |
| `useful_life_years` | Masa manfaat (tahun, min 1, default 5) |
| `salvage_value` | Nilai sisa (read-only; otomatis = 5% dari purchase_cost) |

#### Field Formulir — Chart of Accounts

| Field | Keterangan |
|---|---|
| `asset_coa_id` | COA Aset Tetap (1210.01–1210.04); auto-isi dua COA lainnya |
| `accumulated_depreciation_coa_id` | COA Akumulasi Penyusutan (1220.01–1220.04) |
| `depreciation_expense_coa_id` | COA Beban Penyusutan (6311–6314) |

**Pemetaan COA Otomatis:**
| Asset COA | Akum. Penyusutan | Beban Penyusutan |
|---|---|---|
| 1210.01 | 1220.01 | 6311 |
| 1210.02 | 1220.02 | 6312 |
| 1210.03 | 1220.03 | 6313 |
| 1210.04 | 1220.04 | 6314 |

#### Field Formulir — Perhitungan Penyusutan (read-only)
- Jumlah yang dapat disusutkan
- Penjelasan metode
- Penyusutan tahunan
- Penyusutan bulanan

#### Field Formulir — Status & Catatan

| Field | Opsi |
|---|---|
| `status` | `active` / `disposed` / `fully_depreciated` |
| `notes` | Catatan bebas |

#### Aksi / Actions (Per Baris)

| Aksi | Syarat | Efek |
|---|---|---|
| **View** | Kapan saja | Lihat detail aset |
| **Edit** | Kapan saja | Edit form |
| **Hitung Penyusutan** | Kapan saja | `record->calculateDepreciation()` — hitung ulang nilai penyusutan |
| **Post Jurnal Akuisisi** | Belum ada jurnal | `AssetService::postAssetAcquisitionJournal()` |
| **Post Jurnal Penyusutan** | Bukan `fully_depreciated`; penyusutan bulanan > 0 | Cek duplikasi bulan ini; `AssetService::postAssetDepreciationJournal()` |
| **Lihat Jurnal** | Kapan saja | Buka halaman view aset |
| **Delete** | Kapan saja | Soft delete |

#### Status Aset

```
active → fully_depreciated (otomatis, via updateAccumulatedDepreciation())
active → disposed (via disposal approval)
```

#### Metode Perhitungan Penyusutan

| Metode | Keterangan |
|---|---|
| `straight_line` | (Harga - Nilai Sisa) / Masa Manfaat |
| `declining_balance` | % tetap × Book Value tersisa |
| `sum_of_years_digits` | Bobot sisa tahun / total digit tahun |
| `units_of_production` | Per unit produksi |

---

### 2.2 Asset Disposal (Pelepasan Aset)

**File:** `app/Filament/Resources/AssetDisposalResource.php`  
**Navigasi:** Grup `Asset Management`, Sort 3

#### Tujuan
Mencatat pelepasan aset (jual, scrap, donasi, pencurian, lainnya) beserta pengakuan keuntungan/kerugian.

#### Field Formulir

| Field | Keterangan |
|---|---|
| `asset_id` | Aset aktif (difilter ke status `active`; tolak jika ada disposal aktif) |
| `disposal_date` | Tanggal pelepasan (≥ hari ini) |
| `disposal_type` | `sale` / `scrap` / `donation` / `theft` / `other` |
| `sale_price` | Hasil penjualan (visible + required hanya untuk tipe `sale`) |
| `notes` | Catatan (min 10, max 1000 karakter) |
| `disposal_document` | Dokumen pendukung (PDF/JPEG/PNG/GIF, max 5MB) |

#### Aksi / Actions

| Aksi | Syarat | Efek |
|---|---|---|
| **Approve** | Status `pending` | Status → `completed`; set `approved_by/at`; aset → `disposed`; `AssetDisposalService::postDisposalJournalEntries()` |

#### Status Workflow

```
pending → completed (via Approve)
pending → cancelled (manual)
```

#### Integrasi Akuntansi (saat Approve)

| Entri Jurnal | Debit | Kredit |
|---|---|---|
| Hapus Akumulasi Penyusutan | Akum. Penyusutan | — |
| Hapus Nilai Aset | — | COA Aset Tetap |
| Catat Kas (jika jual) | Kas (1101) | — |
| Gain/Loss Recognition | Beban Kerugian (52xx) atau — | — atau Pendapatan Gain (41xx) |

---

### 2.3 Asset Transfer (Transfer Aset Antar Cabang)

**File:** `app/Filament/Resources/AssetTransferResource.php`  
**Navigasi:** Grup `Asset Management`, Sort 2

#### Tujuan
Mengelola perpindahan aset dari satu cabang ke cabang lain dengan workflow persetujuan.

#### Field Formulir

| Field | Keterangan |
|---|---|
| `asset_id` | Aset aktif (blok jika ada transfer pending/approved); auto-isi `from_cabang_id` |
| `from_cabang_id` | Cabang asal (hidden, auto-set dari aset) |
| `to_cabang_id` | Cabang tujuan (harus berbeda dari cabang asal) |
| `transfer_date` | Tanggal transfer (≥ hari ini) |
| `reason` | Alasan transfer (min 10, max 1000 karakter) |
| `transfer_document` | Dokumen pendukung (PDF/JPEG/PNG/GIF, max 5MB) |

#### Aksi / Actions

| Aksi | Syarat | Efek |
|---|---|---|
| **Approve** | Status `pending` | `AssetTransferService::approveTransfer()` → status `approved` |
| **Complete Transfer** | Status `approved` | `AssetTransferService::completeTransfer()` → update `asset.cabang_id`; status `completed` |
| **Cancel** | Status `pending` atau `approved` | Form alasan (min 10 karakter); `AssetTransferService::cancelTransfer()` |

#### Status Workflow

```
pending → approved → completed
pending/approved → cancelled
```

**Catatan:** Tidak ada jurnal akuntansi. Ini hanya perpindahan lokasi/kepemilikan cabang.

---

## 3. Models

### `Asset`

**Atribut penting:**

| Atribut | Keterangan |
|---|---|
| `code` | Kode unik `AST-NNNN` (auto-generate saat creating) |
| `purchase_cost` | Biaya perolehan |
| `salvage_value` | Nilai sisa (5% dari purchase_cost) |
| `useful_life_years` | Masa manfaat (tahun) |
| `depreciation_method` | Metode penyusutan |
| `annual_depreciation` | Penyusutan per tahun (auto-hitung) |
| `monthly_depreciation` | Penyusutan per bulan (auto-hitung) |
| `accumulated_depreciation` | Akumulasi penyusutan yang sudah diposting |
| `book_value` | Nilai buku = `purchase_cost - accumulated_depreciation` |
| `status` | `active` / `disposed` / `fully_depreciated` |

**Metode penting:**

| Method | Keterangan |
|---|---|
| `calculateDepreciation()` | Hitung annual, monthly, book_value; simpan |
| `updateAccumulatedDepreciation()` | Jumlah semua `AssetDepreciation` yang recorded; set `fully_depreciated` jika `book_value ≤ salvage_value` |
| `hasPostedJournals()` | Cek apakah jurnal akuisisi sudah ada |
| `generateAssetCode()` | Static; generate kode `AST-NNNN` berikutnya |

**Relasi:**

| Relasi | Tipe | Target |
|---|---|---|
| `assetCoa` | BelongsTo | `ChartOfAccount` |
| `accumulatedDepreciationCoa` | BelongsTo | `ChartOfAccount` |
| `depreciationExpenseCoa` | BelongsTo | `ChartOfAccount` |
| `depreciationEntries` | HasMany | `AssetDepreciation` |
| `disposals` | HasMany | `AssetDisposal` |
| `transfers` | HasMany | `AssetTransfer` |
| `product` | BelongsTo | `Product` |
| `purchaseOrder` | BelongsTo | `PurchaseOrder` |

### `AssetDepreciation`
Setiap record = satu posting penyusutan bulanan.

| Atribut | Keterangan |
|---|---|
| `asset_id` | Aset induk |
| `depreciation_date` | Tanggal posting |
| `period_month` / `period_year` | Periode (untuk cegah duplikat) |
| `amount` | Nominal penyusutan |
| `accumulated_total` | Total akumulasi setelah entri ini |
| `book_value` | Nilai buku setelah entri ini |
| `journal_entry_id` | Jurnal debit terkait |
| `status` | `recorded` / `reversed` |

---

## 4. Services

| Service | Method | Fungsi |
|---|---|---|
| `AssetService` | `postAssetAcquisitionJournal(asset, creditCoaId?)` | Post jurnal akuisisi aset (Dr Aset Tetap / Cr AP atau Kas) |
| `AssetService` | `postAssetDepreciationJournal(asset, amount, period)` | Post jurnal penyusutan (Dr Beban Penyusutan / Cr Akum. Penyusutan) |
| `AssetService` | `hasPostedJournals(asset)` | Cek apakah jurnal sudah ada |
| `AssetDepreciationService` | `generateMonthlyDepreciation(asset, date)` | Buat record penyusutan bulanan + post jurnal |
| `AssetDepreciationService` | `generateAllMonthlyDepreciation(date)` | Iterasi semua aset aktif; generate penyusutan |
| `AssetDepreciationService` | `reverseDepreciation(depreciation)` | Set `reversed`; hapus jurnal; update book value |
| `AssetDisposalService` | `createDisposal(asset, data)` | Snapshot book value; hitung gain/loss; update aset; post jurnal |
| `AssetDisposalService` | `postDisposalJournalEntries(asset, disposal)` | Buat jurnal pelepasan (hapus akum. + aset; catat kas; gain/loss) |
| `AssetTransferService` | `createTransferRequest(asset, toCabangId, data)` | Buat request transfer |
| `AssetTransferService` | `approveTransfer(transfer)` | Setujui transfer |
| `AssetTransferService` | `completeTransfer(transfer)` | Selesaikan transfer; update `asset.cabang_id` |
| `AssetTransferService` | `cancelTransfer(transfer, reason?)` | Batalkan transfer |

---

## 5. Observers & Events

### `AssetObserver`

| Event | Aksi |
|---|---|
| `creating` | Hitung penyusutan sebelum insert |
| `updating` → `purchase_cost` / `salvage_value` / `useful_life_years` berubah | Hitung ulang penyusutan; update jurnal akuisisi |
| `deleting` | Soft-delete semua `JournalEntry` terkait |

---

## 6. Pola Jurnal Akuntansi

| Transaksi | Debit | Kredit | `journal_type` |
|---|---|---|---|
| Akuisisi Aset | COA Aset Tetap (1210.xx) | Utang Dagang (AP) atau Kas | `asset_acquisition` |
| Penyusutan Bulanan | Beban Penyusutan (63xx) | Akumulasi Penyusutan (1220.xx) | `asset_depreciation` |
| Pelepasan — Hapus Akum. | Akumulasi Penyusutan | — | — |
| Pelepasan — Hapus Aset | — | COA Aset Tetap | — |
| Pelepasan — Kas (jual) | Kas (1101) | — | — |
| Pelepasan — Keuntungan | — | Pendapatan Gain (41xx) | — |
| Pelepasan — Kerugian | Beban Kerugian (52xx) | — | — |

---

## 7. Integrasi Antar Modul

| Integrasi | Keterangan |
|---|---|
| **Purchase → Asset** | PO dengan `is_asset = true` → auto-buat Asset records saat PO disetujui |
| **CabangScope** | Global scope pada model `Asset` — query otomatis difilter per cabang user |
| **JournalBranchResolver** | Semua jurnal aset melalui resolver untuk set `cabang_id`, `department_id`, `project_id` |
| **Product linkage** | Pilih produk di Asset → auto-isi biaya dari PO item terbaru |

---

## 8. Permissions

| Permission | Fungsi |
|---|---|
| `view any asset` | Lihat daftar aset |
| `create asset` | Buat aset baru |
| `update asset` | Edit aset |
| `delete asset` | Hapus aset |
| `post asset acquisition journal` | Post jurnal akuisisi |
| `post asset depreciation journal` | Post jurnal penyusutan |
| `approve asset disposal` | Setujui disposal aset |
| `approve asset transfer` | Setujui transfer aset |
| `complete asset transfer` | Selesaikan transfer aset |
