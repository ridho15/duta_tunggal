# Legacy Category Approval Workflow 2026-04-10

## Purpose

Dokumen ini menjelaskan workflow approval kategori untuk 340 grup duplicate product staging yang masih tertahan setelah konsolidasi fase dua.

## Current State

- Grup duplicate product yang masih butuh keputusan kategori: 340
- Row product aktif yang masih terlibat: 680
- Semua grup tersisa punya perbedaan `product_category_id`
- Approval kategori yang aman sudah diterapkan ke staging untuk 193 row, sehingga simulasi terbaru kini menyisakan 147 grup manual / 294 row.

## Simulation Result

Simulasi dilakukan dengan rule kanonik berikut:

- gunakan category dari row kanonik non-DUP jika tersedia
- row kanonik diprioritaskan dari SKU dasar `CAB-<target_sku>`
- setelah category diseragamkan, hitung ulang difference field per target SKU

Hasil simulasi:

- `exact`: 11 grup / 22 row
- `biaya`: 153 grup / 306 row
- `qty-min`: 29 grup / 58 row
- `manual`: 147 grup / 294 row

Artinya, sebelum approval diterapkan total 193 grup diperkirakan bisa masuk jalur merge otomatis lanjutan. Setelah approval diterapkan, state live memang sudah turun menjadi 147 grup manual / 294 row.

## Priority Categories

Kategori saran terbesar saat ini:

- `635` `SAMBUNGAN TEMBAGA STD`: 166 grup
- `640` `PIPA TEMBAGA`: 79 grup
- `517` `PIPA NONE`: 40 grup
- `514` `PIPA BRASSCO`: 25 grup
- `345` `FITTING`: 14 grup

## Commands

Simulasi dampak approval kategori:

```bash
php artisan legacy:simulate-category-approval
```

Ekspor file approval kategori:

```bash
php artisan legacy:export-category-approval
```

Apply hasil review kategori dari CSV:

```bash
php artisan legacy:apply-category-approval storage/app/private/legacy-review/<file>.csv
php artisan legacy:apply-category-approval storage/app/private/legacy-review/<file>.csv --execute
```

Catatan apply:

- hanya row dengan `decision_status=approved` atau `apply` yang akan diproses
- `approved_category_id` harus terisi
- tanpa `--execute`, command hanya dry-run

## Generated Files

Artefak terbaru yang sudah dibuat:

- [products_category_approval.csv](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/storage/app/private/legacy-review/20260410_213043_products_category_approval.csv)
- [products_category_approval_details.csv](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/storage/app/private/legacy-review/20260410_213043_products_category_approval_details.csv)
- [products_category_priority.csv](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/storage/app/private/legacy-review/20260410_213043_products_category_priority.csv)
- [products_category_approval_summary.md](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/storage/app/private/legacy-review/20260410_213043_products_category_approval_summary.md)
- [products_category_approval_prepared.csv](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/storage/app/private/legacy-review/20260410_213043_products_category_approval_prepared.csv)
- [refreshed products_category_approval.csv](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/storage/app/private/legacy-review/20260410_214621_products_category_approval.csv)
- [refreshed products_category_priority.csv](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/storage/app/private/legacy-review/20260410_214621_products_category_priority.csv)

## Recommended Review Order

1. Review kategori `635` dan `640` lebih dulu karena volume grup paling besar.
2. Approve semua grup yang setelah normalisasi kategori jatuh ke mode `exact`.
3. Lanjutkan ke grup mode `biaya`, lalu `qty-min`.
4. Sisakan bucket `manual` terakhir karena masih punya kombinasi perbedaan payload lain.

## Post-Apply State

Approval yang sudah diterapkan ke staging berhasil menurunkan bucket otomatis menjadi tinggal manual-only. Export terbaru menegaskan state ini, sehingga langkah berikutnya adalah menyelesaikan 147 grup manual yang masih punya perbedaan category.