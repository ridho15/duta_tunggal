# PR Summary - 13 April 2026

## Scope

Branch ini berisi pekerjaan legacy migration, verifikasi import master data, serta penyesuaian UI/admin untuk validasi hasil migrasi.

## Key Changes

- Menambahkan command, service, model, migration, dan resource Filament untuk alur legacy migration dan legacy transaction archive.
- Menambahkan utilitas import, konsolidasi, approval, dan rehidrasi data legacy ke ERP.
- Menambahkan dokumentasi audit dan verifikasi migrasi, termasuk workbook dan laporan approval produk.
- Menambahkan utilitas verifikasi master data di `tmp/` dan spec Playwright untuk spot-check UI Filament.
- Menyembunyikan menu `Legacy Transaction Archives` dari navigasi Filament tanpa menghapus resource-nya.
- Menambahkan proteksi `.gitignore` untuk dump SQL dan screenshot sementara agar tidak kembali ter-commit.

## Validation

- Playwright spot-check untuk UI master-data/Filament lulus.
- Push branch `13-april-2026` ke remote `dt` berhasil setelah pembersihan artefak besar.

## Cleanup Notes

- File dump SQL besar di `public/` sudah tidak lagi memblokir push.
- Screenshot sementara di `tmp/` layak dianggap artefak dan tidak perlu disimpan di git.
- File `docs/legacy-import-*` dan `docs/legacy-product-approval-*` tetap dipertahankan karena masih direferensikan oleh dokumentasi dan workflow command.