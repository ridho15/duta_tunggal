# Legacy Import Review 2026-04-10

## Current State

ERP saat ini sudah berada pada kondisi review hasil import:

- Cabang 1 / Warehouse 1: akses sistem
- Cabang 2 / Warehouse 2: hasil import `inventory`
- Cabang 3 / Warehouse 3: hasil staging `inventory_cab`

Count final saat dokumen ini dibuat:

- Customers: 17917
- Suppliers: 833
- Products: 29539
- Inventory Stocks: 29539

Split per source:

- Cabang 2: customers 8834, suppliers 406, products 10092, stocks 10092
- Cabang 3: customers 9083, suppliers 427, products 19447, stocks 19447

## UI Review Paths

User dengan `manage_type = all` seperti `super_admin`, `admin`, atau `owner` bisa review semua cabang hasil import.

Halaman yang relevan:

- `/admin/customers`
- `/admin/suppliers`
- `/admin/products`
- `/admin/inventory-stocks`

Panduan review cepat:

- Customers: gunakan filter `Cabang` pada halaman customer untuk memilih cabang 2 atau 3.
- Suppliers: review by search code atau nama perusahaan, staging source kedua memakai code prefixed `CAB-`.
- Products: source kedua memakai SKU prefix `CAB-`, jadi pencarian `CAB-` langsung memisahkan data staging.
- Inventory Stocks: gunakan filter `Gudang` dan pilih warehouse 2 untuk source `inventory`, warehouse 3 untuk source `inventory_cab`.

## Comparison Summary

Overlap hasil import yang sudah masuk ke ERP:

- Customer overlap ke source utama: 8817
- Supplier overlap ke source utama: 404
- Product overlap ke source utama: 17823

Artinya, sebagian besar data `inventory_cab` memang merujuk ke code dasar yang juga ada pada source `inventory`, tetapi saat ini aman karena disimpan sebagai staging dengan prefix `CAB-`.

## Helpful Commands

Bandingkan dua source yang sudah diimpor ke ERP:

```bash
php artisan legacy:compare-imported-sources
```

Rencana dry-run untuk konsolidasi staging ke source utama:

```bash
php artisan legacy:plan-consolidation
```

Konsolidasi staging-only ke source utama, default dry-run:

```bash
php artisan legacy:consolidate-staging
```

Ekspor conflict report ke markdown dan CSV untuk approval manual:

```bash
php artisan legacy:export-conflict-report
```