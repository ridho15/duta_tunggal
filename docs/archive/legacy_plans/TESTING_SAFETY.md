# Laravel Testing Safety

Automated tests must never run against the local application database.

## Required test database

Create and use a dedicated MySQL database for tests:

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS duta_tunggal_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

The main local database is `duta_tunggal`. Do not use it for automated tests.

## Safe commands

Use the safe Composer scripts so Laravel config cache is cleared before PHPUnit starts:

```bash
composer test:safe -- tests\Feature\PurchaseOrderTotalCalculationTest.php
composer test:unit-safe -- tests\Unit\PurchaseOrderItemNavigatorTest.php
```

`tests/TestCase.php` contains a hard guard: when `APP_ENV=testing`, tests abort unless the active database name ends with `_test`.

## Browser/manual testing

Manual browser checks may use the local application database `duta_tunggal`.
Automated Feature tests that use `RefreshDatabase` must use `duta_tunggal_test`.

## Menjalankan seluruh suite: runner terpotong + baseline

Satu proses untuk ±350 berkas uji dapat kehabisan memori (kebocoran nyata di suite). Gunakan runner terpotong — tiap 30 berkas dijalankan
sebagai proses terpisah, hasilnya dibaca dari JUnit XML dan digabung:

```bash
composer test:chunked                                    # seluruh suite (± 20 menit) → storage/test-runs/<waktu>/
composer test:chunked -- --compare=tests/baseline-failures.txt   # + bandingkan dengan baseline (exit 1 bila ada kegagalan BARU)
composer test:chunked -- --dirs=tests/Feature --only=CustomerReceipt --size=10   # uji cepat sebagian
composer test:chunked -- --db=duta_tunggal_lain_test     # DB uji lain (WAJIB berakhiran _test)
composer test:compare -- tests/baseline-failures.txt storage/test-runs/<waktu>
```

- Keluaran: `failures.txt` (nama tes gagal, format `Kelas :: nama tes`), `summary.json`, dan log per potongan.
- Potongan yang crash (mis. kehabisan memori) diisolasi per berkas dan dicatat sebagai `CRASH :: <berkas>` — tidak pernah hilang diam-diam.
- **`tests/baseline-failures.txt`** = daftar kegagalan yang sudah ada sebelum pekerjaan baru. Setiap perubahan pekerjaan **tidak boleh menambah** baris di luar daftar ini.
  Memperbaruinya hanya lewat commit terpisah yang menjelaskan alasannya (mis. tes diperbaiki → baris dihapus). Analisis kelompoknya ada di `docs/BASELINE-TES.md`.
- **Jangan** menjalankan dua runner pada database uji yang sama (`RefreshDatabase` menjalankan `migrate:fresh`); gunakan `--db=` berbeda bila ingin paralel.
- Batas memori: `App\Support\MemoryLimit::raiseTo()` hanya *menaikkan* batas (tidak lagi menurunkan `-d memory_limit=-1` menjadi 512M).
