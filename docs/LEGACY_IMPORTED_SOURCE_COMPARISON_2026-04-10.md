# Legacy Imported Source Comparison 2026-04-10

Ringkasan ini membandingkan hasil import source `inventory` sebagai main (`cabang_id = 2`, `warehouse_id = 2`) dengan source `inventory_cab` sebagai staging (`cabang_id = 3`, `warehouse_id = 3`, prefix `CAB-`) pada database ERP aktif `duta_tunggal` setelah workbook import dieksekusi.

## Snapshot Scope

- Main cabang: 2
- Staging cabang: 3
- Main warehouse: 2
- Staging warehouse: 3
- Prefix staging: `CAB-`

## Entity Comparison

| Entity | Main Rows | Staging Rows | Overlap Base Code | Staging Only | Name Differences |
| --- | ---: | ---: | ---: | ---: | ---: |
| Customers | 9101 | 9083 | 9083 | 0 | 56 |
| Suppliers | 429 | 427 | 427 | 0 | 13 |
| Products | 10571 | 19447 | 18767 | 680 | 2593 |

Interpretasi cepat:

- Customer dan supplier staging sudah tidak menyisakan row `staging_only`; seluruh row staging sekarang punya base code pasangan di main.
- Perbedaan yang tersisa pada customer dan supplier murni isu nama/format penulisan, bukan gap coverage kode.
- Produk masih menyisakan `680` row staging-only yang belum punya pasangan SKU main.

## Stock Comparison

| Metric | Value |
| --- | ---: |
| Main stock rows | 10571 |
| Staging stock rows | 19447 |
| Mergeable to main SKU | 18767 |
| Staging-only SKU | 680 |
| Mergeable qty available | 403667 |
| Staging-only qty available | 210522 |

Implikasi:

- Mayoritas stok staging sudah mengarah ke SKU main yang ekuivalen secara base code.
- Nilai stok yang benar-benar masih tertahan di staging-only cukup besar: `210522` unit tersedia.

## Customer Name Differences

Contoh perbedaan yang tersisa cenderung berupa normalisasi legal suffix atau formatting:

- `A075`: `PT AIR SURYA RADIATOR` vs `AIR SURYA RADIATOR PT`
- `A079`: `PT ANGKA WIJAYASENTOSA` vs `ANGKA WIJAYASENTOSA PT`
- `G010`: `PT. GRAND KARTECH, Tbk` vs `PT.Grand Kartech,Tbk`
- `G016`: `PT GENTRACO BUANA UTAMA` vs `GENTRACO BUANA UTAMA PT`

Kesimpulan praktis: customer conflict sisa lebih dominan formatting dan candidate review-nya relatif aman.

## Supplier Name Differences

Contoh perbedaan supplier yang tersisa terdiri dari dua tipe: formatting ringan dan alias/singkatan yang lebih material.

- Formatting ringan:
  `C019`: `PT CENTRAL AGUNG ADIRAJA` vs `PT.CENTRAL AGUNG ADIRAJA`
  `C020`: `CV. CITRA SUKSES ABADI` vs `CV CITRA SUKSES ABADI`
  `V005`: `PT VALCONINDO INTI PERKASA` vs `VALCONINDO INTI PERKASA`
- Perlu review manual:
  `I001`: `PT IDJENINTI PRAKARSA` vs `IDJEN`
  `S018`: `PT SARIMAS BAHTERA SUKSES` vs `SBS`
  `W001`: `PT WILLICH ISOLASI PRATAMA` vs `Willich`

Kesimpulan praktis: supplier conflict sisa jumlahnya kecil (`13`), tetapi sebagian bukan sekadar beda format.

## Product Differences

Produk overlap dengan nama berbeda mencapai `2593` row. Pola yang terlihat di sample sangat dominan berupa penambahan brand atau variasi formatting nama, misalnya:

- `ACBB060915`: main `PIPA SET 1/4 T0.45mm + 3/8 T0.56mm x 15m`, staging `BRASSCO PIPA SET 1/4 T0.45mm + 3/8 T0.56mm x 15m`
- `ACBB060930`: main `PIPA SET 1/4 T0.45mm + 3/8 T0.56mm X 30m`, staging `BRASSCO PIPA SET 1/4 T0.45mm + 3/8 T0.56mm X 30m`
- `ACBB061630`: main `PIPA SET 1/4 T0.56MM + 5/8 T0.71MM X 30M`, staging `BRASSCO PIPA SET 1/4 T0.56MM + 5/8 T0.71MM X 30M`

Ini menunjukkan konflik overlap product yang tersisa banyak berupa enrichment nama, bukan selalu perbedaan SKU dasar.

## Remaining Staging-Only Product Buckets

Sisa produk staging-only yang belum punya pasangan main berada pada `340` grup target SKU, total `680` row.

Top reason bucket yang masih tersisa:

| Reason | Groups | Rows |
| --- | ---: | ---: |
| category,biaya | 153 | 306 |
| category,bulk_sell_qty | 32 | 64 |
| category,cost,biaya | 31 | 62 |
| category,qty_min | 29 | 58 |
| category,bulk_sell_qty,biaya | 25 | 50 |
| category,cost,bulk_sell_qty,biaya | 21 | 42 |
| category | 11 | 22 |
| category,bulk_capacity | 10 | 20 |
| category,bulk_sell_qty,biaya,qty_min | 6 | 12 |
| category,biaya,qty_min | 5 | 10 |

Temuan kunci:

- Seluruh bucket besar yang tersisa selalu mengandung perbedaan `category`.
- Artinya, keputusan kategori sekarang menjadi blocker utama sebelum konsolidasi lanjutan bisa dilakukan aman.

## Operational Summary

- Workbook import ke ERP aktif sudah dieksekusi untuk kedua source.
- CSV per tabel sudah digenerate di folder `docs` untuk review atau pertukaran data lanjutan.
- Gap utama yang tersisa sekarang bukan customer/supplier coverage, melainkan konflik product staging-only berbasis kategori dan atribut bisnis terkait.

## Recommended Next Actions

1. Review bucket product `staging_only` mulai dari reason `category,biaya` karena itu bucket terbesar.
2. Tentukan canonical `product_category_id` per grup target SKU sebelum merge lanjutan.
3. Setelah kategori diputuskan, evaluasi apakah konflik `biaya`, `cost`, `bulk_sell_qty`, dan `qty_min` bisa dinormalisasi otomatis per bucket.

## Approval Workflow Files

Workflow approval lanjutan sudah disiapkan di dokumen berikut:

- `docs/legacy-product-approval-summary-20260410.md`
- `docs/legacy-product-approval-category-biaya-20260410.csv`
- `docs/legacy-product-approval-category-biaya-details-20260410.csv`
- `docs/LEGACY_PRODUCT_APPROVAL_CATEGORY_BIAYA_SHORTLIST_2026-04-10.md`
- `docs/LEGACY_PRODUCT_APPROVAL_WORKFLOW_2026-04-10.md`