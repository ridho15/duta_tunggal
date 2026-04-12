# Legacy Inventory Migration 2026-04-10

## Ringkasan

Analisis pada database legacy `inventory` dan `inventory_cab` menunjukkan bahwa keduanya berasal dari keluarga aplikasi yang sama, tetapi bukan dataset yang identik. Skema ERP aktif `duta_tunggal` berbeda jauh, jadi strategi yang aman adalah migrasi bertahap dengan mapping dan upsert, bukan restore SQL mentah.

Temuan kunci:

- `inventory` memakai prefix tabel `knr_` dan memiliki 47 tabel.
- `inventory_cab` memakai prefix tabel `dtm_` dan memiliki 49 tabel.
- Setelah prefix diabaikan, ada 46 tabel yang sama di kedua sumber legacy.
- `inventory_cab` memiliki kualitas data produk yang lebih berisiko karena duplikasi internal `product_code` sangat tinggi.
- ERP aktif `duta_tunggal` memiliki 124 tabel dan memecah domain transaksi menjadi modul yang lebih rinci.

## Mapping Master Data

| Legacy Source | Legacy Table | Key | ERP Table | ERP Key | Mapping |
| --- | --- | --- | --- | --- | --- |
| inventory / inventory_cab | `*_customers` | `customer_code` | `customers` | `code` | Upsert berdasarkan code. `customer_name -> name`, `customer_type -> tipe`, `customer_credit -> kredit_limit`, `customer_paytype -> tipe_pembayaran`, sisanya dipetakan ke field identitas customer ERP. |
| inventory / inventory_cab | `*_suppliers` | `supplier_code` | `suppliers` | `code` | Upsert berdasarkan code. `supplier_company/supplier_name -> perusahaan`, `supplier_tempo -> tempo_hutang`, field kontak dipetakan langsung. |
| inventory / inventory_cab | `*_product_categories` | `category_code` | `product_categories` | `kode` | Upsert berdasarkan `kode`, fallback ke `name` jika code kosong. |
| inventory / inventory_cab | `*_products.satuan` | text | `unit_of_measures` | `name` | Distinct `satuan` diubah menjadi UOM ERP. Jika tidak ada, fallback ke `PCS`. |
| inventory / inventory_cab | `*_products` | `product_code` | `products` | `sku` | Upsert berdasarkan `sku`. `product_name -> name`, `limit_price -> sell_price` dan `harga_batas`, `real_cost/cost -> cost_price`, `item_value -> item_value`, `tax_type/tax_value -> tipe_pajak/pajak`. |
| inventory / inventory_cab | `*_inventories` + `*_product_stocks` | `product_id` | `inventory_stocks` | `(product_id, warehouse_id, rak_id)` logical key | Saldo awal diagregasi per SKU lalu diimpor ke satu warehouse ERP yang dipilih. `qty -> qty_available`, `qty_booking_* -> qty_reserved`, `min_qty -> qty_min`. |
| inventory / inventory_cab | `*_stores` | `store_code` | `cabangs` / `warehouses` | manual mapping | Belum diimpor otomatis. Store legacy perlu dipetakan manual ke cabang dan warehouse ERP. |

## Perbedaan Konsep yang Wajib Diperhatikan

- Legacy memakai tabel transaksi yang lebih monolitik seperti `sales`, `purchases`, `stockflows`.
- ERP baru memecah alur menjadi `sale_orders`, `delivery_orders`, `purchase_orders`, `purchase_receipts`, `inventory_stocks`, `journal_entries`, dan modul akuntansi/manufaktur lain.
- Karena itu, transaksi historis lama tidak boleh di-restore mentah ke ERP baru.
- Tahap pertama yang aman adalah customer, supplier, kategori, UOM, product, dan opening stock agregat.

## Audit Merge `inventory` vs `inventory_cab`

Ringkasan kuantitatif yang sudah diverifikasi:

- Customer overlap code: 8819
- Product overlap code: 17823
- Supplier overlap code: 404
- Duplicate customer code di `inventory`: 1
- Duplicate customer code di `inventory_cab`: 2
- Duplicate product code di `inventory`: 0
- Duplicate product code di `inventory_cab`: 9358
- Duplicate supplier code di `inventory`: 0
- Duplicate supplier code di `inventory_cab`: 1

Interpretasi:

- Customer dan supplier relatif aman digabung berbasis code, tetapi tetap perlu review untuk variasi nama.
- Product tidak aman digabung hanya berdasarkan nama. Gunakan `product_code` sebagai kunci utama.
- `inventory_cab` memerlukan audit tambahan sebelum merge produk karena banyak `product_code` yang berulang di dalam database itu sendiri.

## Store Legacy yang Terdeteksi

Contoh `inventory`:

- `K01.1` -> KENARI 1
- `E-DT` -> E-COMMERCE
- `DTM` -> DAYA TEKNIK MEDIKA
- `C-B19` -> Daya Gas Kencana

Contoh `inventory_cab`:

- `DTM` -> DAYA TEKNIK MEDIKA
- `DUTA` -> PT DUTA TUNGGAL
- `DTU` -> PT DAYA TEKNIK UNGGUL

Rekomendasi praktis:

- Jika dua sumber legacy akan dibawa bersamaan, impor ke cabang atau warehouse terpisah lebih dulu.
- Setelah opening stock diverifikasi, baru lakukan konsolidasi product/customer yang benar-benar identik.

## Command Baru

Audit konflik merge:

```bash
php artisan legacy:audit-merge
```

Reset ERP agar hanya menyisakan tabel akses dan menyiapkan target import minimal:

```bash
php artisan legacy:reset-erp-data --force --prepare-import
```

Dry-run import master data dari `inventory`:

```bash
php artisan legacy:import-master-data inventory --cabang_id=1 --warehouse_id=1
```

Eksekusi import master data dari `inventory_cab`:

```bash
php artisan legacy:import-master-data inventory_cab --cabang_id=1 --warehouse_id=1 --execute
```

Eksekusi staging aman dari `inventory_cab` tanpa menimpa source lain:

```bash
php artisan legacy:import-master-data inventory_cab --cabang_id=3 --warehouse_id=3 --key-prefix=CAB- --execute
```

Validasi cepat dengan limit per langkah:

```bash
php artisan legacy:import-master-data inventory --limit=100
```

## Batasan Implementasi Saat Ini

- Command import hanya mencakup master data dan saldo awal stok agregat.
- Store legacy belum otomatis menjadi `cabangs` atau `warehouses` ERP.
- Transaksi historis penjualan, pembelian, kas, dan stockflow lama belum dimigrasikan.
- Import products memakai `limit_price` sebagai `sell_price` dan `harga_batas` karena legacy tidak menyimpan struktur harga jual ERP yang lebih lengkap.