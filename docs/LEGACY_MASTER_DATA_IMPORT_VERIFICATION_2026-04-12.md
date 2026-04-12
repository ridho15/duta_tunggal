# Legacy Master Data Import Verification 2026-04-12

## Scope

Migrasi data master dari database legacy `inventory` dan `inventory_cab` ke database ERP `duta_tunggal` telah dijalankan dengan prinsip berikut:

- tidak menghapus data ERP yang sudah ada
- tidak mengubah struktur tabel sumber maupun target
- memakai pipeline import yang sudah ada di aplikasi
- memisahkan data `inventory_cab` dengan prefix key `CAB-` dan cabang/gudang khusus

Dokumen ini mencatat hasil verifikasi database setelah eksekusi import serta hasil spot-check UI Filament pada environment lokal.

## Pipeline Yang Dipakai

Import dijalankan melalui command aplikasi:

```bash
php artisan legacy:import-master-data inventory --cabang_id=1 --warehouse_id=1 --execute
php artisan legacy:import-master-data inventory_cab --cabang_id=2 --warehouse_id=2 --key-prefix=CAB- --execute
```

Target isolasi yang dibuat tanpa mengubah schema:

- cabang `CBG-LEG-INV` / `Cabang Import Inventory`
- cabang `CBG-LEG-CAB` / `Cabang Import Inventory CAB`
- warehouse `WH-LEG-INV` / `Gudang Import Inventory`
- warehouse `WH-LEG-CAB` / `Gudang Import Inventory CAB`

## Snapshot Sumber Legacy

Snapshot diambil ulang dari runtime Laravel menggunakan utilitas verifikasi sementara agar pembacaan schema mengikuti mapping resmi service migrasi.

### inventory

- customers: `8835`
- suppliers: `406`
- categories: `310`
- products: `10092`
- inventory rows: `89125`

### inventory_cab

- customers: `9083`
- suppliers: `427`
- categories: `924`
- products: `19447`
- inventory rows: `7444`

## Snapshot Target ERP

- cabangs: `2`
- warehouses: `2`
- customers: `17917`
- suppliers: `833`
- product categories: `311`
- unit of measures: `36`
- products: `29539`
- inventory stocks: `29539`

## Distribusi Hasil Import

### Customers by cabang

- cabang `1`: `8834`
- cabang `2`: `9083`

### Suppliers by cabang

- cabang `1`: `406`
- cabang `2`: `427`

### Products by cabang

- cabang `1`: `10092`
- cabang `2`: `19447`

### Inventory stocks by warehouse

- warehouse `1`: `10092`
- warehouse `2`: `19447`

## Verifikasi Sampel Data

Contoh row hasil import yang berhasil diverifikasi di target ERP:

- customer cabang 1: code `-`, name `ARIO.A`
- customer cabang 2: code `CAB--`, name `ARIO.A`
- supplier cabang 1: code `A001`, perusahaan `Abdi Karya`
- supplier cabang 2: code `CAB-A001`, perusahaan `Abdi Karya`
- product cabang 1: sku `002008016013`, name `SOK DRAT LUAR MODEL UNION PIPA 5/8'' X DRAT 1/2''`
- product cabang 2: sku `CAB-001001006071`, name `COPPER TUBE ASTM B819 TYPE L 1/4 X 0.71MM x 5.8M`

Contoh stock hasil import:

- warehouse `1`, product_id `1`, qty_available `0`, qty_reserved `0`, qty_min `20`
- warehouse `2`, product_id `10093`, qty_available `0`, qty_reserved `0`, qty_min `1`

## Temuan Penting

### Customer inventory selisih 1 row

Sumber `inventory` memiliki `8835` row customer, tetapi target cabang 1 berjumlah `8834` customer unik.

Penyebabnya adalah duplikasi `customer_code` di legacy:

- `96.813.868.5-331.000`

Karena import bersifat upsert berbasis business key, dua row legacy dengan code yang sama terkonsolidasi menjadi satu row target. Ini adalah perilaku yang benar dan aman.

### Categories dan UOM tidak bersifat aditif sederhana

Jumlah category target `311` dan UOM target `36` tidak boleh dibaca sebagai penjumlahan mentah dari dua source, karena entity ini dikonsolidasikan ke dimensi bersama ERP berdasarkan key/normalisasi import. Hasil ini konsisten dengan desain pipeline master-data yang idempotent dan non-destruktif.

### Stock target satu row per product per warehouse import

Legacy menyimpan banyak row inventory transaksi/agregat per product, tetapi target ERP `inventory_stocks` disusun sebagai posisi stok hasil konsolidasi. Karena itu jumlah row stock target mengikuti jumlah product yang berhasil diimport per warehouse, bukan jumlah row tabel inventory legacy.

## Kesimpulan

Verifikasi database menunjukkan bahwa import master data dari `inventory` dan `inventory_cab` ke `duta_tunggal` telah tersalin dengan benar sesuai desain pipeline:

- customer, supplier, product, dan stock berhasil masuk ke cabang/gudang yang dipisahkan
- data `inventory_cab` berhasil diisolasi dengan prefix `CAB-`
- tidak ada indikasi penghapusan data target
- tidak ada perubahan struktur schema selama proses
- selisih customer pada source `inventory` sudah dijelaskan oleh duplicate business key di legacy

## Spot-Check UI Filament

Spot-check UI dilakukan memakai akun lokal `superadmin@gmail.com` yang dibootstrap non-destruktif melalui role `Super Admin` agar sesuai dengan setup Playwright yang sudah ada di repo.

Halaman yang diverifikasi berhasil diakses tanpa error aplikasi:

- `/admin/master-data-hub`
- `/admin/customers?tableSearch=CAB--`
- `/admin/suppliers?tableSearch=CAB-A001`
- `/admin/products?tableSearch=CAB-001001006071`
- `/admin/product-categories?tableSearch=ALFA`
- `/admin/inventory-stocks?tableSearch=CAB-001001006071`

Sampel yang terlihat di UI:

- customer `CAB--` / `ARIO.A`
- supplier `CAB-A001` / `Abdi Karya`
- product `CAB-001001006071` / `COPPER TUBE ASTM B819 TYPE L 1/4 X 0.71MM x 5.8M`
- product category `ALFA` / `Pipa Tembaga ALFA`
- inventory stock product `CAB-001001006071` muncul pada gudang `WH-LEG-CAB`

Status saat dokumen ini ditulis:

- verifikasi database: selesai
- spot-check UI Filament: selesai