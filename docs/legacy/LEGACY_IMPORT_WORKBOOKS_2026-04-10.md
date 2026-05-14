# Legacy Import Workbooks 2026-04-10

Workbook Excel import-ready untuk ERP aktif `duta_tunggal` sudah digenerate dari source legacy `inventory` dan `inventory_cab`.

## File Hasil

- `docs/legacy-import-inventory-20260410.xlsx`
- `docs/legacy-import-inventory-cab-20260410.xlsx`

Varian terpisah per tabel juga sudah digenerate:

- `docs/legacy-import-inventory-categories-20260410.xlsx`
- `docs/legacy-import-inventory-uoms-20260410.xlsx`
- `docs/legacy-import-inventory-customers-20260410.xlsx`
- `docs/legacy-import-inventory-suppliers-20260410.xlsx`
- `docs/legacy-import-inventory-products-20260410.xlsx`
- `docs/legacy-import-inventory-stocks-20260410.xlsx`
- `docs/legacy-import-inventory-cab-categories-20260410.xlsx`
- `docs/legacy-import-inventory-cab-uoms-20260410.xlsx`
- `docs/legacy-import-inventory-cab-customers-20260410.xlsx`
- `docs/legacy-import-inventory-cab-suppliers-20260410.xlsx`
- `docs/legacy-import-inventory-cab-products-20260410.xlsx`
- `docs/legacy-import-inventory-cab-stocks-20260410.xlsx`

Varian CSV per tabel juga sudah digenerate:

- `docs/legacy-import-inventory-categories-20260410.csv`
- `docs/legacy-import-inventory-uoms-20260410.csv`
- `docs/legacy-import-inventory-customers-20260410.csv`
- `docs/legacy-import-inventory-suppliers-20260410.csv`
- `docs/legacy-import-inventory-products-20260410.csv`
- `docs/legacy-import-inventory-stocks-20260410.csv`
- `docs/legacy-import-inventory-cab-categories-20260410.csv`
- `docs/legacy-import-inventory-cab-uoms-20260410.csv`
- `docs/legacy-import-inventory-cab-customers-20260410.csv`
- `docs/legacy-import-inventory-cab-suppliers-20260410.csv`
- `docs/legacy-import-inventory-cab-products-20260410.csv`
- `docs/legacy-import-inventory-cab-stocks-20260410.csv`

## Struktur Workbook

Setiap workbook berisi sheet berikut:

1. `meta`
2. `summary`
3. `categories`
4. `uoms`
5. `customers`
6. `suppliers`
7. `products`
8. `stocks`

Format workbook memakai business key agar aman di-upsert ke ERP tanpa bergantung pada `id` auto increment.

## Default Target Saat Ini

### Source `inventory`

- `cabang_id = 2`
- `warehouse_id = 2`
- tanpa prefix key tambahan

### Source `inventory_cab`

- `cabang_id = 3`
- `warehouse_id = 3`
- prefix key `CAB-`

Prefix `CAB-` dipakai supaya SKU/code dari `inventory_cab` tidak menimpa data source `inventory` saat di-upsert ke ERP aktif.

## Command Generator

Generate ulang workbook:

```bash
php artisan legacy:export-import-workbooks
```

Generate juga file `.xlsx` terpisah per tabel:

```bash
php artisan legacy:export-import-workbooks --split-files
```

Generate juga file `.csv` terpisah per tabel:

```bash
php artisan legacy:export-import-workbooks --split-csv
```

Generate `.xlsx` dan `.csv` split sekaligus:

```bash
php artisan legacy:export-import-workbooks --split-files --split-csv
```

Generate satu source saja:

```bash
php artisan legacy:export-import-workbooks --source=inventory
php artisan legacy:export-import-workbooks --source=inventory_cab
```

## Command Import Workbook

Dry-run validasi workbook:

```bash
php artisan legacy:import-workbook docs/legacy-import-inventory-20260410.xlsx
php artisan legacy:import-workbook docs/legacy-import-inventory-cab-20260410.xlsx
```

Eksekusi import ke ERP:

```bash
php artisan legacy:import-workbook docs/legacy-import-inventory-20260410.xlsx --execute
php artisan legacy:import-workbook docs/legacy-import-inventory-cab-20260410.xlsx --execute
```

Import berjalan dalam urutan:

1. `categories`
2. `uoms`
3. `customers`
4. `suppliers`
5. `products`
6. `stocks`

Untuk file split per tabel, importer tetap bisa membaca file tersebut. Jika hanya ingin memproses satu entitas tertentu, lebih rapi memakai `--only`, misalnya:

```bash
php artisan legacy:import-workbook docs/legacy-import-inventory-products-20260410.xlsx --only=products --execute
```

## Hasil Validasi Dry-Run

### `legacy-import-inventory-20260410.xlsx`

- categories: 311 row
- uoms: 36 row
- customers: 8835 row
- suppliers: 406 row
- products: 10092 row
- stocks: 10092 row

### `legacy-import-inventory-cab-20260410.xlsx`

- categories: 925 row
- uoms: 36 row
- customers: 9083 row
- suppliers: 427 row
- products: 19447 row
- stocks: 19447 row

Dry-run berhasil membaca kedua workbook tanpa row yang gagal dipetakan (`skipped = 0`).

Satu contoh validasi file split juga berhasil:

- `legacy-import-inventory-products-20260410.xlsx` -> `products: unchanged 10092, skipped 0`

Catatan: importer bawaan yang ditambahkan saat ini membaca format workbook `.xlsx`. File `.csv` disediakan sebagai artefak review, pertukaran data, atau transformasi lanjutan per tabel.