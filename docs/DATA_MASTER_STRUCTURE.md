# Dokumentasi Struktur Data Master

## Overview

Dokumen ini berisi dokumentasi lengkap struktur data master dalam sistem ERP Duta Tunggal. Data master merupakan data referensi utama yang digunakan di seluruh sistem untuk mendukung operasi bisnis.

## Daftar Data Master

### 1. Cabang (Branches)
Tabel: `cabangs`

**Deskripsi**: Menyimpan informasi cabang perusahaan yang digunakan untuk segmentasi operasional.

**Struktur Tabel**:
```sql
CREATE TABLE `cabangs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `kode` varchar(20) NOT NULL,                    -- Kode unik cabang
  `nama` varchar(100) NOT NULL,                   -- Nama cabang
  `alamat` text NOT NULL,                         -- Alamat lengkap cabang
  `telepon` varchar(20) DEFAULT NULL,             -- Nomor telepon cabang
  `kenaikan_harga` decimal(5,2) NOT NULL DEFAULT '0.00', -- Persentase kenaikan harga default
  `status` tinyint(1) NOT NULL DEFAULT '1',       -- Status aktif/non-aktif
  `warna_background` varchar(20) DEFAULT NULL,    -- Warna background untuk UI
  `tipe_penjualan` enum('Semua','Pajak','Non Pajak') NOT NULL DEFAULT 'Semua',
  `kode_invoice_pajak` varchar(50) DEFAULT NULL,  -- Kode invoice untuk pajak
  `kode_invoice_non_pajak` varchar(50) DEFAULT NULL, -- Kode invoice non-pajak
  `kode_invoice_pajak_walkin` varchar(50) DEFAULT NULL, -- Kode invoice walk-in pajak
  `nama_kwitansi` varchar(100) DEFAULT NULL,      -- Nama kwitansi
  `label_invoice_pajak` varchar(100) DEFAULT NULL,-- Label invoice pajak
  `label_invoice_non_pajak` varchar(100) DEFAULT NULL, -- Label invoice non-pajak
  `logo_invoice_non_pajak` varchar(255) DEFAULT NULL, -- Logo invoice non-pajak
  `lihat_stok_cabang_lain` tinyint(1) NOT NULL DEFAULT '0', -- Izin melihat stok cabang lain
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cabangs_kode_unique` (`kode`)
)
```

**Relasi**:
- Parent untuk: customers, suppliers, products, product_categories, warehouses
- Foreign key dari: customers.cabang_id, suppliers.cabang_id, dll.

### 2. Pelanggan (Customers)
Tabel: `customers`

**Deskripsi**: Menyimpan data pelanggan yang melakukan transaksi dengan perusahaan.

**Struktur Tabel**:
```sql
CREATE TABLE `customers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,                   -- Nama pelanggan
  `address` varchar(255) NOT NULL,                -- Alamat pelanggan
  `phone` varchar(255) NOT NULL,                  -- Nomor telepon
  `email` varchar(255) NOT NULL,                  -- Email pelanggan
  `code` varchar(255) NOT NULL,                   -- Kode unik pelanggan
  `perusahaan` varchar(255) NOT NULL,             -- Nama perusahaan
  `tipe` enum('PKP','PRI') NOT NULL,              -- Tipe: PKP (Pengusaha Kena Pajak) atau PRI (Pribadi)
  `fax` varchar(255) NOT NULL,                    -- Nomor fax
  `isSpecial` tinyint(1) NOT NULL DEFAULT '0',    -- Status pelanggan special
  `tempo_kredit` int NOT NULL DEFAULT '0',        -- Tempo kredit dalam hari
  `kredit_limit` bigint NOT NULL DEFAULT '0',     -- Limit kredit
  `tipe_pembayaran` enum('Bebas','COD (Bayar Lunas)','Kredit') NOT NULL DEFAULT 'Bebas',
  `nik_npwp` varchar(255) NOT NULL,               -- NIK atau NPWP
  `keterangan` text,                              -- Keterangan tambahan
  `telephone` varchar(255) NOT NULL,              -- Nomor telephone
  `cabang_id` bigint unsigned NOT NULL,           -- Foreign key ke cabangs
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `customers_code_unique` (`code`),
  KEY `customers_cabang_id_foreign` (`cabang_id`),
  CONSTRAINT `customers_cabang_id_foreign` FOREIGN KEY (`cabang_id`) REFERENCES `cabangs` (`id`) ON DELETE CASCADE
)
```

**Relasi**:
- Child dari: cabangs
- Parent untuk: sale_orders, invoices, customer_receipts, dll.

### 3. Supplier (Suppliers)
Tabel: `suppliers`

**Deskripsi**: Menyimpan data supplier/pemasok yang menyediakan barang/produk.

**Struktur Tabel**:
```sql
CREATE TABLE `suppliers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,                   -- Nama supplier
  `address` varchar(255) NOT NULL,                -- Alamat supplier
  `phone` varchar(255) NOT NULL,                  -- Nomor telepon
  `email` varchar(255) NOT NULL,                  -- Email supplier
  `code` varchar(255) NOT NULL,                   -- Kode unik supplier
  `perusahaan` varchar(255) NOT NULL,             -- Nama perusahaan
  `handphone` varchar(255) NOT NULL,              -- Nomor handphone
  `fax` varchar(255) NOT NULL,                    -- Nomor fax
  `npwp` varchar(255) NOT NULL,                   -- NPWP supplier
  `tempo_hutang` int NOT NULL DEFAULT '0',        -- Tempo hutang dalam hari
  `kontak_person` varchar(255) DEFAULT NULL,      -- Contact person
  `keterangan` text,                              -- Keterangan tambahan
  `cabang_id` bigint unsigned NOT NULL,           -- Foreign key ke cabangs
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `suppliers_code_unique` (`code`),
  KEY `suppliers_cabang_id_foreign` (`cabang_id`),
  CONSTRAINT `suppliers_cabang_id_foreign` FOREIGN KEY (`cabang_id`) REFERENCES `cabangs` (`id`) ON DELETE CASCADE
)
```

**Relasi**:
- Child dari: cabangs
- Parent untuk: purchase_orders, products, dll.

### 4. Produk (Products)
Tabel: `products`

**Deskripsi**: Menyimpan data produk/barang yang diperdagangkan.

**Struktur Tabel**:
```sql
CREATE TABLE `products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,                   -- Nama produk
  `sku` varchar(255) NOT NULL,                    -- Stock Keeping Unit (kode unik)
  `product_category_id` int NOT NULL,             -- Foreign key ke product_categories
  `cost_price` decimal(18,2) NOT NULL DEFAULT '0.00', -- Harga pokok
  `sell_price` decimal(18,2) NOT NULL DEFAULT '0.00', -- Harga jual
  `description` text,                             -- Deskripsi produk
  `uom_id` int NOT NULL,                          -- Unit of Measure ID
  `cabang_id` int NOT NULL,                       -- Foreign key ke cabangs
  `supplier_id` bigint unsigned DEFAULT NULL,     -- Foreign key ke suppliers
  `harga_batas` int NOT NULL DEFAULT '0',         -- Batas harga dalam persen
  `item_value` decimal(18,2) NOT NULL DEFAULT '0.00', -- Nilai item
  `tipe_pajak` enum('Non Pajak','Inklusif','Eksklusif') NOT NULL DEFAULT 'Non Pajak',
  `pajak` decimal(5,2) NOT NULL DEFAULT '0.00',   -- Persentase pajak
  `jumlah_kelipatan_gudang_besar` int NOT NULL DEFAULT '0',
  `jumlah_jual_kategori_banyak` int NOT NULL DEFAULT '0',
  `kode_merk` varchar(50) NOT NULL,               -- Kode merk
  `biaya` decimal(18,2) NOT NULL DEFAULT '0.00',  -- Biaya tambahan
  `is_manufacture` tinyint(1) NOT NULL DEFAULT '0', -- Apakah produk manufaktur
  `is_raw_material` tinyint(1) NOT NULL DEFAULT '0', -- Apakah bahan baku
  `inventory_coa_id` bigint unsigned DEFAULT NULL,-- COA untuk inventory
  `sales_coa_id` bigint unsigned DEFAULT NULL,    -- COA untuk penjualan
  `sales_return_coa_id` bigint unsigned DEFAULT NULL, -- COA untuk retur penjualan
  `sales_discount_coa_id` bigint unsigned DEFAULT NULL, -- COA untuk diskon penjualan
  `goods_delivery_coa_id` bigint unsigned DEFAULT NULL, -- COA untuk pengiriman barang
  `cogs_coa_id` bigint unsigned DEFAULT NULL,     -- COA untuk Cost of Goods Sold
  `purchase_return_coa_id` bigint unsigned DEFAULT NULL, -- COA untuk retur pembelian
  `unbilled_purchase_coa_id` bigint unsigned DEFAULT NULL, -- COA untuk pembelian belum ditagih
  `temporary_procurement_coa_id` bigint unsigned DEFAULT NULL, -- COA untuk procurement sementara
  `is_active` tinyint(1) NOT NULL DEFAULT '1',    -- Status aktif
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_sku_unique` (`sku`),
  -- Foreign key constraints ke chart_of_accounts untuk berbagai COA
)
```

**Relasi**:
- Child dari: cabangs, suppliers, product_categories, unit_of_measures, chart_of_accounts
- Parent untuk: sale_order_items, purchase_order_items, inventory_stocks, dll.

### 5. Kategori Produk (Product Categories)
Tabel: `product_categories`

**Deskripsi**: Menyimpan kategori produk untuk pengelompokan.

**Struktur Tabel**:
```sql
CREATE TABLE `product_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,                   -- Nama kategori
  `kode` varchar(50) NOT NULL,                    -- Kode kategori
  `cabang_id` int NOT NULL,                       -- Foreign key ke cabangs
  `kenaikan_harga` decimal(5,2) NOT NULL DEFAULT '0.00', -- Kenaikan harga default
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
)
```

**Relasi**:
- Child dari: cabangs
- Parent untuk: products

### 6. Gudang (Warehouses)
Tabel: `warehouses`

**Deskripsi**: Menyimpan data gudang untuk penyimpanan inventory.

**Struktur Tabel**:
```sql
CREATE TABLE `warehouses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,                   -- Nama gudang
  `location` varchar(255) NOT NULL,               -- Lokasi gudang
  `kode` varchar(255) NOT NULL,                   -- Kode gudang
  `cabang_id` int NOT NULL,                       -- Foreign key ke cabangs
  `tipe` enum('Kecil','Besar') NOT NULL DEFAULT 'Kecil', -- Tipe gudang
  `telepon` varchar(20) DEFAULT NULL,             -- Nomor telepon gudang
  `status` tinyint(1) NOT NULL DEFAULT '0',       -- Status aktif
  `warna_background` varchar(20) DEFAULT NULL,    -- Warna background UI
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
)
```

**Relasi**:
- Child dari: cabangs
- Parent untuk: raks, inventory_stocks, stock_movements, dll.

### 7. Rak (Racks)
Tabel: `raks`

**Deskripsi**: Menyimpan data rak dalam gudang untuk penyimpanan yang lebih spesifik.

**Struktur Tabel**:
```sql
CREATE TABLE `raks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,                   -- Nama rak
  `code` varchar(255) NOT NULL,                   -- Kode rak
  `warehouse_id` int NOT NULL,                    -- Foreign key ke warehouses
  `description` varchar(255) DEFAULT NULL,        -- Deskripsi rak
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
)
```

**Relasi**:
- Child dari: warehouses
- Parent untuk: inventory_stocks, stock_movements

### 8. Bagan Akun (Chart of Accounts)
Tabel: `chart_of_accounts`

**Deskripsi**: Menyimpan struktur akun untuk keperluan akuntansi.

**Struktur Tabel**:
```sql
CREATE TABLE `chart_of_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) NOT NULL,                   -- Kode akun
  `name` varchar(255) NOT NULL,                   -- Nama akun
  `type` enum('Asset','Liability','Equity','Revenue','Expense','Contra Asset') NOT NULL,
  `parent_id` int DEFAULT NULL,                   -- Parent account untuk hierarchical structure
  `is_active` tinyint(1) NOT NULL DEFAULT '1',    -- Status aktif
  `is_current` tinyint(1) NOT NULL DEFAULT '0',   -- Apakah current asset/liability
  `description` text,                             -- Deskripsi akun
  `opening_balance` decimal(15,2) NOT NULL DEFAULT '0.00', -- Saldo awal
  `debit` decimal(15,2) NOT NULL DEFAULT '0.00',  -- Total debit
  `credit` decimal(15,2) NOT NULL DEFAULT '0.00', -- Total kredit
  `ending_balance` decimal(15,2) NOT NULL DEFAULT '0.00', -- Saldo akhir
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chart_of_accounts_code_unique` (`code`)
)
```

**Relasi**:
- Parent untuk: products (berbagai COA), journal_entries, dll.

### 9. Mata Uang (Currencies)
Tabel: `currencies`

**Deskripsi**: Menyimpan data mata uang untuk transaksi multi-currency.

**Struktur Tabel**:
```sql
CREATE TABLE `currencies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,                   -- Nama mata uang
  `symbol` varchar(255) DEFAULT NULL,             -- Simbol mata uang
  `to_rupiah` decimal(18,2) NOT NULL DEFAULT '0.00', -- Kurs ke Rupiah
  `code` varchar(255) NOT NULL,                   -- Kode mata uang (USD, EUR, dll)
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
)
```

**Relasi**:
- Parent untuk: purchase_orders, sale_orders, dll (untuk currency conversion)

### 10. Satuan (Unit of Measures)
Tabel: `unit_of_measures`

**Deskripsi**: Menyimpan satuan ukuran untuk produk.

**Struktur Tabel**:
```sql
CREATE TABLE `unit_of_measures` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,                   -- Nama satuan (Kg, Liter, Pcs, dll)
  `abbreviation` varchar(255) NOT NULL,           -- Singkatan (kg, ltr, pcs, dll)
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
)
```

**Relasi**:
- Parent untuk: products, product_unit_conversions

## Konversi Satuan Produk (Product Unit Conversions)
Tabel: `product_unit_conversions`

**Deskripsi**: Menyimpan konversi satuan untuk produk tertentu.

**Struktur Tabel**:
```sql
CREATE TABLE `product_unit_conversions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,                      -- Foreign key ke products
  `uom_id` int NOT NULL,                          -- Foreign key ke unit_of_measures
  `nilai_konversi` decimal(10,2) NOT NULL,        -- Nilai konversi
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
)
```

## Catatan Penting

1. **Soft Deletes**: Semua tabel data master menggunakan soft deletes (`deleted_at` field) untuk menjaga integritas data.

2. **Cabang-based**: Sebagian besar data master tersegmentasi berdasarkan cabang (`cabang_id`) untuk mendukung multi-branch operations.

3. **COA Integration**: Produk terintegrasi dengan Chart of Accounts untuk keperluan akuntansi otomatis.

4. **Multi-currency**: Sistem mendukung multi-currency melalui tabel currencies.

5. **Hierarchical COA**: Chart of Accounts memiliki struktur hierarki melalui `parent_id`.

6. **Inventory Management**: Gudang dan Rak menyediakan struktur penyimpanan inventory yang terorganisir.

## Dependencies Antar Tabel

```
cabangs (root)
├── customers
├── suppliers
├── product_categories
├── warehouses
│   └── raks
├── products (tergantung pada product_categories, suppliers, unit_of_measures, chart_of_accounts)
└── product_unit_conversions (tergantung pada products, unit_of_measures)

currencies (independent)
unit_of_measures (independent, digunakan oleh products)
chart_of_accounts (independent, digunakan oleh products)
```

---

**Dibuat pada**: 11 Desember 2025
**Versi Sistem**: ERP Duta Tunggal v1.0