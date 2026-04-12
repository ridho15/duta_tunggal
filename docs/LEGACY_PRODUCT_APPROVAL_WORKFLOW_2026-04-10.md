# Legacy Product Approval Workflow 2026-04-10

Dokumen ini menjelaskan workflow approval untuk blocked staging-only product buckets yang tersisa setelah import workbook dan konsolidasi otomatis tahap sebelumnya. Status live terakhir menunjukkan bucket aktif terbesar adalah `category,cost,biaya`.

## File yang Sudah Dibuat

Summary lintas reason:

- `docs/legacy-product-approval-summary-20260411.md`

File approval utama untuk bucket terbesar yang aktif saat ini `category,cost,biaya`:

- `docs/legacy-product-approval-category-cost-biaya-selected25-20260411.csv`
- `docs/legacy-product-approval-category-cost-biaya-details-20260411.csv`
- `docs/LEGACY_PRODUCT_APPROVAL_CATEGORY_COST_BIAYA_SHORTLIST_2026-04-11.md`

Selain itu, file approval dan detail juga sudah dibuat untuk seluruh reason bucket lain dengan pola nama:

- `docs/legacy-product-approval-<reason>-20260410.csv`
- `docs/legacy-product-approval-<reason>-details-20260410.csv`

## Command Baru

Export approval CSV per reason:

```bash
php artisan legacy:export-product-approval-files
```

Export hanya satu reason tertentu:

```bash
php artisan legacy:export-product-approval-files --reason=category,cost,biaya
```

Dry-run konsolidasi approval khusus `category,cost,biaya`:

```bash
php artisan legacy:consolidate-approved-product-bucket docs/legacy-product-approval-category-cost-biaya-selected25-20260411.csv --reason=category,cost,biaya
```

Eksekusi konsolidasi approval khusus `category,cost,biaya`:

```bash
php artisan legacy:consolidate-approved-product-bucket docs/legacy-product-approval-category-cost-biaya-selected25-20260411.csv --reason=category,cost,biaya --execute
```

## Struktur Approval CSV

Kolom penting pada file approval:

- `target_sku`
- `difference_reason`
- `candidate_skus`
- `candidate_category_ids`
- `candidate_biaya_values`
- `recommended_canonical_sku`
- `recommended_category_id`
- `recommended_biaya`
- `approval_status`
- `approved_canonical_sku`
- `approved_category_id`
- `approved_biaya`
- `notes`

## Aturan Approval yang Dipakai Command

Rule yang dipakai command `legacy:consolidate-approved-product-bucket` sengaja konservatif:

1. Command hanya memproses grup yang pada state live saat ini masih benar-benar berada di bucket yang persis sama dengan `--reason`.
2. `approval_status=APPROVE` berarti rekomendasi default diterima bila kolom `approved_*` dibiarkan kosong.
3. Jika reviewer ingin override, isi `approved_canonical_sku`, `approved_category_id`, dan/atau `approved_biaya`.
4. Canonical row yang dipromosikan ke main akan:
   - diubah SKU-nya menjadi `target_sku`
   - dipindahkan ke `cabang_id = 2`
   - dipakai sebagai penampung total stok grup di `warehouse_id = 2`
5. Row staging duplikat lain dalam grup akan dinonaktifkan dan di-soft-delete; stock duplikat lain di-nolkan lalu di-soft-delete.

## Rekomendasi Default yang Diprefill

Rekomendasi default di file approval mengikuti rule yang konsisten dengan command konsolidasi sebelumnya:

1. Pilih canonical SKU `CAB-<target_sku>` bila row itu ada.
2. Jika tidak ada, pilih row dengan `id` paling kecil.
3. `recommended_category_id` dan `recommended_biaya` diambil dari canonical row tersebut.

Artinya, file approval sudah siap dipakai hanya dengan mengisi `approval_status` untuk grup yang disetujui, tanpa wajib mengisi override.

## Status Validasi Saat Ini

Dry-run command approval terhadap file `category,cost,biaya` sudah berhasil:

- approval_rows: `31`
- approved_rows: `25`
- valid_operations: `25`
- missing_groups: `0`
- invalid_approvals: `0`

Ini menunjukkan format approval CSV dan command konsolidasi sudah sinkron. Langkah berikutnya tinggal mengisi keputusan approval.

## Saran Operasional

1. Mulai review dari `docs/legacy-product-approval-category-cost-biaya-selected25-20260411.csv` karena ini shortlist aktif yang sudah diprefill untuk `25` grup teratas pada bucket `category,cost,biaya`.
2. Gunakan file `details` untuk membandingkan kandidat row per target SKU saat keputusan kategori dan biaya belum jelas.
3. Gunakan shortlist 25 grup pertama di [docs/LEGACY_PRODUCT_APPROVAL_CATEGORY_COST_BIAYA_SHORTLIST_2026-04-11.md](docs/LEGACY_PRODUCT_APPROVAL_CATEGORY_COST_BIAYA_SHORTLIST_2026-04-11.md) sebagai urutan review awal.
4. Setelah beberapa grup disetujui, jalankan dry-run lagi untuk memastikan semua row approval valid sebelum `--execute`.