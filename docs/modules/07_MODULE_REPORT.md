# Dokumentasi Modul Report (Laporan Keuangan & Operasional)

**Versi Dokumen:** 1.0  
**Tanggal:** 30 Maret 2026  
**Aplikasi:** Duta Tunggal ERP

---

## 1. Gambaran Umum

Modul **Report** menyediakan laporan keuangan dan operasional yang komprehensif. Laporan diimplementasikan sebagai halaman Filament khusus (Custom Pages) maupun Shell Resources. Semua laporan mendukung filter periode, cabang, dan ekspor ke PDF/Excel.

---

## 2. Laporan Keuangan

### 2.1 Balance Sheet / Neraca Keuangan

**File:** `app/Filament/Resources/Reports/BalanceSheetResource.php`  
**Page:** `app/Filament/Pages/BalanceSheetPage.php`  
**Navigasi:** Grup `Laporan`, Sort 4

#### Fitur Utama
- **Multi-period comparison**: bandingkan neraca dua periode berbeda (bulan/tahun)
- **Drill-down**: klik baris → lihat jurnal detail per COA
- **Summary vs Detail**: toggle tampilan ringkas/rinci
- **Export PDF & Excel**: tombol unduh langsung dari halaman
- **Retained Earnings auto-calculated**: laba ditahan = total Revenue − total Expense

#### Filter
| Filter | Keterangan |
|---|---|
| `as_of_date` | Tanggal neraca (default hari ini) |
| `comparison_date` | Tanggal pembanding (opsional) |
| `cabang_id` | Filter per cabang |
| `show_zero_balance` | Toggle tampilkan akun saldo nol |

#### Struktur Laporan
```
ASET
  Aset Lancar
    Kas dan Bank
    Piutang Dagang
    Persediaan
    PPN Masukan
    ...
  Aset Tidak Lancar
    Aset Tetap (bruto)
    Akumulasi Penyusutan
  TOTAL ASET

LIABILITAS
  Liabilitas Jangka Pendek
    Utang Dagang
    PPN Keluaran
    ...
  Liabilitas Jangka Panjang
  TOTAL LIABILITAS

EKUITAS
  Modal Disetor
  Laba Ditahan (Retained Earnings)
  Laba/Rugi Tahun Berjalan
  TOTAL EKUITAS

TOTAL LIABILITAS + EKUITAS
```

#### Service (`BalanceSheetService`)

| Method | Fungsi |
|---|---|
| `generate(filters)` | Hitung neraca dari agregasi JournalEntry per COA |
| `comparePeriods(date1, date2)` | Bandingkan dua neraca, hitung perubahan (`change`, `change_pct`) |
| `calculateRetainedEarnings(date)` | Revenue − Expense dari GL sampai tanggal tersebut |

---

### 2.2 Income Statement / Laporan Laba Rugi (P&L)

**File:** `app/Filament/Resources/Reports/ProfitAndLossResource.php`  
**Page:** `app/Filament/Pages/IncomeStatementPage.php`  
**Navigasi:** Grup `Laporan`, Sort 3

#### Fitur Utama
- **Periode**: pilih rentang tanggal (dari–sampai)
- **Comparison**: bandingkan dengan periode sebelumnya
- **Styled rows**: rendering berbeda per `row_type` (header/data/subtotal/total/grand_total)
- **Export PDF & Excel**

#### Filter
| Filter | Keterangan |
|---|---|
| `start_date` / `end_date` | Periode laporan |
| `comparison_start` / `comparison_end` | Periode pembanding (opsional) |
| `cabang_id` | Filter per cabang |

#### Struktur Laporan
```
PENDAPATAN
  Penjualan Bersih
  Pendapatan Lain-lain
  TOTAL PENDAPATAN

BEBAN POKOK PENDAPATAN
  HPP / COGS
  GROSS PROFIT

BEBAN OPERASIONAL
  Beban Penjualan
  Beban Umum & Administrasi
  Beban Penyusutan
  TOTAL BEBAN OPERASIONAL

LABA/RUGI OPERASIONAL

PENDAPATAN/BEBAN LAIN-LAIN
  Pendapatan Bunga
  Beban Bunga/Keuangan
  NET OTHER INCOME

LABA/RUGI SEBELUM PAJAK
PAJAK PENGHASILAN (PPh)
LABA/RUGI BERSIH
```

---

### 2.3 Cash Flow / Arus Kas

**File:** `app/Filament/Resources/Reports/CashFlowResource.php`  
**Page:** `app/Filament/Pages/CashFlowPage.php`  
**Navigasi:** Grup `Laporan`, Sort 10

#### Fitur Utama
- **Dua metode**: Direct Method dan Indirect Method (toggle)
- **3 seksi utama**: Operasi / Investasi / Pendanaan
- **Saldo awal dan saldo akhir kas** auto-hitung

#### Filter
| Filter | Keterangan |
|---|---|
| `start_date` / `end_date` | Periode laporan |
| `method` | `direct` / `indirect` |
| `cabang_id` | Filter per cabang |

#### Struktur (Direct Method)
```
ARUS KAS DARI KEGIATAN OPERASI
  Penerimaan dari Pelanggan         (+)
  Pembayaran kepada Pemasok         (−)
  Pembayaran Kas untuk Operasional  (−)
  ARUS KAS BERSIH DARI OPERASI

ARUS KAS DARI KEGIATAN INVESTASI
  Pembelian Aset Tetap              (−)
  Penjualan Aset Tetap              (+)
  ARUS KAS BERSIH DARI INVESTASI

ARUS KAS DARI KEGIATAN PENDANAAN
  Penerimaan Pinjaman               (+)
  Pembayaran Pinjaman               (−)
  ARUS KAS BERSIH DARI PENDANAAN

KENAIKAN/PENURUNAN KAS BERSIH
SALDO KAS AWAL PERIODE
SALDO KAS AKHIR PERIODE
```

---

### 2.4 Cost of Goods Manufacturing (COGM)

**File:** `app/Filament/Pages/CostOfGoodsManufacturingPage.php`  
**Navigasi:** Grup `Laporan – Produksi`

#### Fitur Utama
- Laporan biaya produksi per periode
- Breakdown bahan baku, tenaga kerja, overhead
- Filter per MO / Product / Periode

#### Formula COGM
```
Saldo WIP Awal Periode
+ Bahan Baku terpakai (Material Issue)
+ Biaya Tenaga Kerja Langsung
+ Overhead Pabrik (alokasi)
− Saldo WIP Akhir Periode
= HARGA POKOK PRODUKSI (HPP Produksi)
```

---

## 3. Laporan Operasional Penjualan & Pembelian

### 3.1 Sales Report

**File:** `app/Filament/Pages/SalesReportPage.php`  
**Navigasi:** Grup `Laporan – Penjualan`

#### Filter
| Filter | Keterangan |
|---|---|
| `start_date` / `end_date` | Periode |
| `customer_id` | Per pelanggan |
| `product_id` | Per produk |
| `cabang_id` | Per cabang |
| `status` | Draft / Confirmed / Completed / All |

#### Kolom Laporan
| Kolom | Keterangan |
|---|---|
| No. Invoice/SO | Link ke dokumen sumber |
| Tanggal | Tanggal transaksi |
| Pelanggan | Nama customer |
| Produk | Item yang dijual |
| Qty | Jumlah |
| Harga | Harga satuan |
| Subtotal | Qty × Harga |
| Total | Total dengan pajak |
| Status | Status dokumen |

**Agregasi:** Total penjualan, Total qty, Average harga jual per produk.

---

### 3.2 Purchase Report

**File:** `app/Filament/Pages/PurchaseReportPage.php`  
**Navigasi:** Grup `Laporan – Pembelian`

#### Filter
| Filter | Keterangan |
|---|---|
| `start_date` / `end_date` | Periode |
| `vendor_id` | Per vendor |
| `product_id` | Per produk |
| `cabang_id` | Per cabang |

#### Kolom Laporan
| Kolom | Keterangan |
|---|---|
| No. Purchase Order | Link ke PO |
| Tanggal | Tanggal transaksi |
| Vendor | Nama supplier |
| Item | Produk yang dibeli |
| Qty | Jumlah |
| Harga | Harga satuan |
| Total | Nilai total |
| Status PO | Status Purchase Order |

---

## 4. Laporan Persediaan

### 4.1 Stock Report

**File:** `app/Filament/Resources/Reports/StockReportResource.php`  
**Navigasi:** Grup `Laporan – Gudang`, Sort 28

#### Filter
| Filter | Keterangan |
|---|---|
| `warehouse_id` | Filter per gudang |
| `product_id` | Filter per produk |
| `cabang_id` | Per cabang |
| `as_of_date` | Per tanggal (snapshot) |

#### Kolom Laporan
| Kolom | Keterangan |
|---|---|
| Produk | Nama produk |
| Gudang | Lokasi stok |
| Stok Tersedia | `qty_available` |
| Stok Reservasi | `qty_reserved` |
| Stok dalam Pengiriman | `qty_transit` |
| Nilai Stok | `qty × unit_cost` |

---

### 4.2 Inventory Card / Kartu Persediaan

**File:** `app/Filament/Resources/Reports/InventoryCardResource.php`  
**Page:** `app/Filament/Pages/InventoryCardPage.php`  
**Navigasi:** Grup `Laporan – Gudang`, Sort 11

#### Tujuan
Menampilkan historis masuk/keluar per item per gudang (kartu stok tradisional).

#### Filter
| Filter | Keterangan |
|---|---|
| `product_id` | **Required** — pilih produk |
| `warehouse_id` | Filter per gudang |
| `start_date` / `end_date` | Rentang tanggal |

#### Kolom
| Kolom | Keterangan |
|---|---|
| Tanggal | Tanggal pergerakan |
| Referensi | Nomor dokumen sumber |
| Tipe | `masuk` / `keluar` / `transfer` / `adj` |
| Qty Masuk | Qty jika tipe masuk |
| Qty Keluar | Qty jika tipe keluar |
| Saldo | Running balance saldo |
| Harga | Unit cost saat transaksi |
| Nilai Masuk/Keluar | Nilai uang |

---

### 4.3 HPP / COGS Report

**File:** `app/Filament/Resources/Reports/HppResource.php`  
**Navigasi:** Grup `Laporan – Inventori`, Sort 9

#### Tujuan
Laporan Harga Pokok Penjualan per produk / periode.

#### Filter
| Filter | Keterangan |
|---|---|
| `start_date` / `end_date` | Periode |
| `product_id` | Per produk (opsional) |
| `cabang_id` | Per cabang |

#### Kolom
| Kolom | Keterangan |
|---|---|
| Produk | Nama produk |
| Qty Terjual | Jumlah unit terjual |
| Harga Pokok Rata-rata | HPP per unit |
| Total HPP | Qty × HPP Rata-rata |
| Nilai Penjualan | Revenue dari produk |
| Margin Kotor | Revenue − Total HPP |
| Margin % | (Margin/Revenue) × 100 |

---

## 5. Laporan Keuangan Lanjutan

### 5.1 Buku Besar (General Ledger)

**File:** `app/Filament/Pages/BukuBesarPage.php`  
**Navigasi:** Grup `Laporan – Akuntansi`

#### Fitur Utama
- Multi-COA filter (pilih satu atau banyak akun)
- **3 Tampilan**:
  - `summary` — hanya saldo per COA per periode
  - `detail` — setiap entri jurnal per COA
  - `running_balance` — saldo berjalan per baris

#### Filter
| Filter | Keterangan |
|---|---|
| `coa_ids` | **Multi-select** akun COA |
| `start_date` / `end_date` | Periode |
| `cabang_id` | Per cabang |
| `view_mode` | summary / detail / running_balance |

#### Kolom Tampilan Detail
| Kolom | Keterangan |
|---|---|
| Tanggal | Tanggal entri |
| Referensi | Nomor jurnal |
| Narasi | Deskripsi entri |
| Debit | Nilai debit |
| Kredit | Nilai kredit |
| Saldo | Running balance |

---

### 5.2 AR/AP Management Report

**File:** `app/Filament/Pages/ArApManagementPage.php`  
**Navigasi:** Grup `Laporan – Keuangan`

#### Fitur Utama
- Ageing schedule AR/AP: current / 1–30 hari / 31–60 hari / 61–90 hari / >90 hari
- Toggle AR atau AP
- Drill-down ke invoice sumber

#### Filter
| Filter | Keterangan |
|---|---|
| `type` | `ar` / `ap` |
| `as_of_date` | Tanggal ageing (default hari ini) |
| `cabang_id` | Per cabang |
| `status` | unpaid / partial / all |

#### Kolom
| Kolom | Keterangan |
|---|---|
| Supplier/Pelanggan | Nama counterparty |
| Invoice | Nomor invoice |
| Tanggal | Tanggal invoice |
| Jatuh Tempo | Due date |
| Total | Nilai invoice |
| Dibayar | Nominal terbayar |
| Sisa | Outstanding |
| 0–30 hari | Kolom ageing |
| 31–60 hari | Kolom ageing |
| >60 hari | Kolom ageing |

---

### 5.3 Vendor & Customer Summary

**File:** `app/Filament/Pages/VendorCustomerSummaryPage.php`  
**Navigasi:** Grup `Laporan – Keuangan`

#### Tujuan
Ringkasan pembelian per vendor atau penjualan per pelanggan pada satu periode.

#### Filter
| Filter | Keterangan |
|---|---|
| `type` | `vendor` / `customer` |
| `start_date` / `end_date` | Periode |
| `cabang_id` | Per cabang |

---

### 5.4 Deposit Summary

**File:** `app/Filament/Pages/DepositSummaryPage.php`  
**Navigasi:** Grup `Laporan – Keuangan`

#### Tujuan
Ringkasan saldo deposit supplier dan customer — sudah terpakai, sisa, dan riwayat drawdown FIFO.

---

### 5.5 Journal Consolidation

**File:** `app/Filament/Pages/JournalConsolidationPage.php`  
**Navigasi:** Grup `Laporan – Akuntansi`

#### Tujuan
Konsolidasi jurnal lintas cabang ke satu laporan. Mendukung eliminasi antar-cabang.

---

### 5.6 Financial Statement (Laporan Keuangan Komprehensif)

**File:** `app/Filament/Pages/FinancialStatementPage.php`  
**Navigasi:** Grup `Laporan – Akuntansi`

#### Tujuan
Tampilan satu halaman yang menggabungkan Balance Sheet, Income Statement, dan Cash Flow dalam format publish-ready.

---

### 5.7 Drill-Down Financial Report

**File:** `app/Filament/Pages/DrillDownFinancialReportPage.php`  
**Navigasi:** Grup `Laporan – Akuntansi`

#### Tujuan
Eksplorasi interaktif: klik baris di Balance Sheet / Income Statement → drill down ke level COA lebih dalam → sampai ke transaksi jurnal individual.

---

## 6. Ekspor Laporan

Semua laporan mendukung ekspor:

| Format | Keterangan |
|---|---|
| **PDF** | Cetak atau simpan sebagai PDF; menggunakan template Blade |
| **Excel / XLSX** | Export via `maatwebsite/excel`; menggunakan Filament `ExportAction` |

---

## 7. Permissions

| Permission | Fungsi |
|---|---|
| `view balance sheet` | Lihat neraca |
| `view income statement` | Lihat P&L |
| `view cash flow` | Lihat laporan arus kas |
| `view general ledger` | Lihat buku besar |
| `view stock report` | Lihat laporan stok |
| `view inventory card` | Lihat kartu persediaan |
| `view hpp report` | Lihat laporan HPP |
| `view ar ap management` | Lihat manajemen AR/AP |
| `export report` | Ekspor laporan (PDF/Excel) |
