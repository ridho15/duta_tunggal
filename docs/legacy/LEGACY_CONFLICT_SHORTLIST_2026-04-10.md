# Legacy Conflict Shortlist 2026-04-10

## Purpose

Dokumen ini merangkum keputusan review yang paling masuk akal berdasarkan conflict report hasil import `inventory` sebagai source utama dan `inventory_cab` sebagai staging.

Referensi file detail:

- [summary](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/storage/app/private/legacy-review/20260410_114342_summary.md)
- [customer conflicts](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/storage/app/private/legacy-review/20260410_114342_customers_name_conflicts.csv)
- [supplier conflicts](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/storage/app/private/legacy-review/20260410_114342_suppliers_name_conflicts.csv)
- [product conflicts](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/storage/app/private/legacy-review/20260410_114342_products_name_conflicts.csv)

## Current Consolidation State

Konsolidasi staging-only yang aman sudah dijalankan sebagian:

- Customer promoted ke main: 266
- Supplier promoted ke main: 23
- Product promoted ke main: 14 direct staging-only + 465 exact duplicate groups
- Stock promoted ke warehouse main: 14 direct staging-only + 465 merged exact duplicate groups
- Product duplicate rows yang sudah dinonaktifkan setelah merge exact: 465
- Approval kategori untuk 193 row duplicate product staging telah diterapkan ke staging, dan simulasi terbaru kini menyisakan 147 grup manual / 294 row.

Yang masih tertinggal di staging setelah promosi aman:

- Customers cabang 3: 8817
- Suppliers cabang 3: 404
- Products cabang 3 aktif: 18503
- Stocks warehouse 3 aktif: 18503

Catatan penting:

- Product staging-only yang tersisa masih punya 680 row benturan internal target SKU, setara 340 grup manual review.
- Overlap records antara main dan staging memang sengaja tidak disentuh oleh konsolidasi otomatis.

## Phase Two Exact Duplicate Consolidation

Fase dua sudah dijalankan untuk grup product staging-only yang seluruh payload import-nya identik dan hanya berbeda pada row legacy duplikat internal.

Command yang dipakai:

- `php artisan legacy:consolidate-staging --entities=products,stocks --product-duplicate-mode=exact --execute --force`

Heuristik exact duplicate yang dipakai:

- Target SKU sama setelah prefix `CAB-` dan suffix `-DUPn-Rid` dibersihkan
- Nama sama setelah normalisasi huruf besar-kecil, spasi, dan tanda baca
- Field import product berikut identik: category, uom, cost, sell, item_value, tax_type, tax, bulk_capacity, bulk_sell_qty, brand, biaya, qty_min

Perilaku eksekusi:

- Satu row kanonik dipromosikan ke cabang main dengan SKU target final
- Stock semua row dalam grup dijumlahkan ke row kanonik di warehouse main
- Row product duplikat lain ditandai nonaktif dan soft-deleted
- Row stock duplikat lain di-nolkan lalu soft-deleted

Hasil fase dua:

- Auto-resolved exact duplicate groups: 465
- Product rows yang terlibat: 930
- Sisa blocked duplicate rows: 680
- Sisa blocked duplicate groups: 340

Status verifikasi lanjutan setelah percobaan fase tiga konservatif:

- Mode `biaya` dan `qty-min` sudah ditambahkan ke command konsolidasi untuk pengujian bucket parsial.
- Export terbaru berhasil dibuat dan sekarang menyertakan file khusus `products_blocked_duplicate_summary.csv` dan `products_blocked_duplicate_details.csv`.
- Pada state database saat ini, seluruh 340 grup yang tersisa ternyata juga memiliki perbedaan `category`, jadi bucket `biaya` saja atau `qty_min` saja tidak lagi tersedia sebagai auto-merge aman.
- Workflow approval kategori dan simulasi dampaknya sekarang didokumentasikan di [docs/LEGACY_CATEGORY_APPROVAL_WORKFLOW_2026-04-10.md](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/docs/LEGACY_CATEGORY_APPROVAL_WORKFLOW_2026-04-10.md).
- Setelah approval kategori diterapkan, export terbaru menunjukkan hanya 147 grup manual / 294 row yang masih tersisa untuk review category.

## Remaining Manual Product Buckets

State terbaru setelah approval kategori diterapkan hanya menyisakan bucket manual kategori. Tidak ada lagi jalur otomatis yang aman sampai keputusan category dibuat untuk 147 grup ini.

Ringkasan teratas saat ini:

- `category + biaya`: 63 grup / 126 row
- `category + qty_min`: 35 grup / 70 row
- `category + bulk_sell_qty`: 22 grup / 44 row
- `category + bulk_sell_qty + biaya`: 17 grup / 34 row
- `category + bulk_sell_qty + biaya + qty_min`: 4 grup / 8 row
- `category + cost + biaya`: 3 grup / 6 row
- `category + cost`: 2 grup / 4 row

Implikasi review:

- Semua grup yang tersisa wajib direview dengan keputusan kategori terlebih dahulu.
- Setelah keputusan kategori ditetapkan, baru bucket `biaya`, `cost`, `qty_min`, atau `bulk_sell_qty` bisa dinormalisasi lanjutan bila masih diperlukan.

## Customer Decisions

### Auto-approve merge candidates

Pola berikut umumnya aman dianggap entitas yang sama dan main name bisa dipertahankan sebagai nama kanonik:

- Perpindahan posisi `PT`, `CV`, atau `Tbk`
- Perbedaan titik, koma, spasi, dan huruf besar-kecil
- Penulisan legal suffix yang setara seperti `PT GENTRACO BUANA UTAMA` vs `GENTRACO BUANA UTAMA PT`

Contoh:

- `A075`: `PT AIR SURYA RADIATOR` vs `AIR SURYA RADIATOR PT`
- `H024`: `CV. HARMONINDO ANUGRAH SENTOSA` vs `HARMONINDO ANUGRAH SENTOSA CV`
- `G010`: `PT. GRAND KARTECH, Tbk` vs `PT.Grand Kartech,Tbk`

### Manual review required

Pola berikut jangan digabung otomatis:

- Nama staging tampak sebagai singkatan, nama dagang, atau nama dipotong berat
- Nama staging tampak entitas berbeda walau code sama

Contoh:

- `K014`: `PT. KARYAGRAHA UNGGULAGUNG` vs `KARYA GRAHA UNGGUL`
- `N004`: `PT. NUCIFERA MULTI MANDIRI` vs `NUCIFERA`

## Supplier Decisions

### Auto-approve merge candidates

Pola yang aman:

- Perbedaan `PT` atau `CV` saja
- Perbedaan tanda baca dan kapitalisasi

Contoh:

- `C019`: `PT CENTRAL AGUNG ADIRAJA` vs `PT.CENTRAL AGUNG ADIRAJA`
- `C020`: `CV. CITRA SUKSES ABADI` vs `CV CITRA SUKSES ABADI`
- `V005`: `PT VALCONINDO INTI PERKASA` vs `VALCONINDO INTI PERKASA`

### Manual review required

Pola yang harus dicek:

- Singkatan besar yang kehilangan nama perusahaan penuh
- Nama staging tampak distributor atau alias yang berbeda total

Contoh:

- `I001`: `PT IDJENINTI PRAKARSA` vs `IDJEN`
- `S018`: `PT SARIMAS BAHTERA SUKSES` vs `SBS`
- `P020`: `PT PRIMA PROSPEK INDONESIA` vs `Pusat Pneumatic`
- `D021`: `DANYANG AIRTECH` vs `DISTRIBUTOR ALAM SIRAM`

## Product Decisions

### Keep main record, possible name enrichment later

Banyak conflict product tampak sebagai SKU yang sama tetapi staging menambahkan brand atau sedikit normalisasi teks. Untuk pola ini, jangan membuat product baru. Pertahankan main SKU, lalu bila perlu lakukan enrichment nama setelah approval.

Pola umum:

- Tambahan brand prefix seperti `BRASSCO`
- Normalisasi `x` vs `X`, `mm` vs `MM`, dan variasi spasi
- Perbedaan penulisan ukuran tanpa perubahan SKU dasar

Contoh:

- `ACBB060915`: main `PIPA SET ...`, staging `BRASSCO PIPA SET ...`
- `ACBB061630`: main `PIPA SET ...`, staging `BRASSCO PIPA SET ...`
- `ACBB131930`: perbedaan hanya brand dan formatting ukuran

### Manual review required

Pola yang wajib dicek sebelum merge:

- Nama staging menambahkan brand yang mungkin memang penting secara komersial
- Nama berbeda cukup material meski SKU sama
- Product staging-only dengan target SKU ganda internal

Catatan eksekusi saat ini:

- 14 product staging-only yang benar-benar unik sudah berhasil dipromosikan ke main.
- 465 exact duplicate groups juga sudah berhasil dipromosikan via fase dua.
- 680 row product staging-only masih diblokir karena payload bisnis mereka belum identik.

## Suggested Next Review Order

1. Approve semua customer conflict yang hanya beda posisi `PT/CV/Tbk`, tanda baca, atau kapitalisasi.
2. Approve semua supplier conflict yang hanya beda format legal prefix atau tanda baca.
3. Untuk products overlap, jadikan SKU main sebagai record kanonik dan putuskan apakah nama main perlu diperkaya dengan brand dari staging.
4. Untuk 147 blocked staging-only product groups yang tersisa, review category tetap prioritas utama karena tidak ada lagi jalur auto-merge aman sebelum keputusan category dibuat.