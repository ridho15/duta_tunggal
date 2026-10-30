# Laporan Tahap 3 — Tampilan HP Gudang, Bantuan & Mode Latihan

Tanggal: 19 Oktober 2026
Acuan: `docs/baru/UIUX_Spec_ERP_Duta_Tunggal.md` Bagian 9 (Tahap 3), rencana `docs/RENCANA-TAHAP3-UIUX-19OKT2026.md`, mockup F1–F4, D2, D3.

## 1. Ringkasan

| No | Pekerjaan | Halaman / file utama | Status |
|----|-----------|----------------------|--------|
| 3.1 | Navigasi bawah HP (6.8): Beranda · menu utama per peran · tombol + · Stok · Menu | hook `mobile-bottom-nav`, `App\Support\MobileNav` | Selesai |
| 3.2 | Siapkan & Kirim di HP (checker, mockup F1) | `/admin/kiriman`, `/admin/kiriman/{DO}` | Selesai |
| 3.3 | Retur DO dari HP (mockup F2) | `/admin/kiriman/{DO}/retur` | Selesai (tanpa upload foto, lihat 5) |
| 3.3b | Picking list per gudang (A4, mockup F3) | `/pdf/picking-list` | Selesai |
| 3.4 | Stock Opname HP, blind count (mockup D3) | `/admin/opname-hp` | Selesai |
| 3.5 | Cek Stok HP + scan barcode (mockup D2, F4) | `/admin/cek-stok-hp` | Selesai |
| 3.6 | Halaman Bantuan + kelola Video Tutorial | `/admin/bantuan`, PENGATURAN → Video Tutorial | Selesai (isi video menunggu tim) |
| 3.7 | Pemindai barcode | `hp-scripts` (BarcodeDetector → `html5-qrcode` → ketik manual) | Selesai |
| 3.8 | Mode latihan | `config/training.php`, banner, `php artisan latihan:reset` | Selesai (butuh instans terpisah) |

Pengujian: `tests/Feature/Tahap3` (4 berkas) + `tests/Feature/Tahap1` + `tests/Feature/Stock/DeliveryOrderTransitionsTest.php` → **99 lulus**. Uji regresi DO / stok / opname / navigasi tidak menambah kegagalan baru. Kegagalan di `DeliveryOrderFeatureTest` dan `ReportNavigationGroupingTest` sudah ada sejak sebelum Tahap 1 (dicek di commit dasar).

Tampilan HP sudah dicek pada lebar 390 px sebagai user Checker contoh: daftar kiriman, detail DO, retur, opname, cek stok, dan bantuan.

## 2. Keputusan yang dipakai (D1–D5 belum dijawab, jadi dipakai usulan rencana)

- **D1 Urutan KIRIM vs Surat Jalan.** Setelan sekarang **tidak diubah** (`SALES_STOCK_REQUIRE_SURAT_JALAN=true`).
  - DO yang belum punya SJ terbit tampil di tab **"Menunggu SJ"**.
  - Checker tetap bisa mencentang barangnya, tetapi tombol KIRIM nonaktif dengan alasan yang jelas.
  - Untuk mengikuti Alur Bisnis (kirim sebelum SJ), cukup set `SALES_STOCK_REQUIRE_SURAT_JALAN=false` di `.env`; tidak perlu ubah kode.
  - **Perlu keputusan Ko Handy.**
- **D2 Mode latihan:** instans terpisah. Kode yang sama dipakai, dengan database sendiri.
- **D3 Video:** direkam tim UAT dan disimpan di YouTube unlisted. Link ditempel di menu Video Tutorial; file `.mp4` juga didukung.
- **D4 `html5-qrcode@2.3.8`:** ditambahkan ke `package.json`. Hanya dimuat bila browser tidak punya BarcodeDetector.
- **D5 Gudang checker:** memakai `users.warehouse_id`. Bila kosong, user memilih gudang sekali per sesi. Owner/Super Admin boleh berganti gudang.

## 3. Catatan teknis penting

- **Centang checker** disimpan di tabel baru `delivery_order_item_checks`. Tabel item DO tidak diubah, karena hook-nya menyinkronkan stok dan jurnal.
- **KIRIM** tetap lewat satu jalur `DeliveryOrderTransitions::to($do, 'sent')` dengan sumber `hp_checker`. Aturan SJ, stok, dan jurnal tetap sama dengan desktop.
- **Retur DO dari HP** membuat retur **draft** bernomor `DO…R`, lalu disetujui di desktop.
  - Barang "Kurang" dicatat kondisi *baik*; barang "Rusak" dicatat kondisi *rusak* dan masuk gudang karantina.
  - Retur ditolak bila DO sudah punya SJ aktif atau sudah punya retur.
- **Opname blind count:** `system_qty` tidak pernah dikirim ke browser (diuji). Selisih baru terlihat di desktop setelah "Selesai menghitung".
- **Perbaikan bug lama `StockOpname`:**
  - Masalahnya: scope cabang memakai kolom `stock_opnames.cabang_id` yang tidak ada, sehingga user yang dibatasi cabang mendapat error SQL di menu Stock Opname.
  - Perbaikannya: scope sekarang menyaring lewat `warehouse.cabang_id`.
- **Hak akses** (`config/menu_access.php`):

  | Halaman | Peran |
  |---------|-------|
  | Kiriman | Owner, Gudang |
  | Retur DO | Owner, Gudang, Sales, Admin Pengiriman |
  | Opname HP | Owner, Gudang |
  | Cek Stok | semua peran |
  | Video Tutorial | Owner/SA/Admin |

  Halaman HP tidak muncul di sidebar desktop. Halaman itu dibuka dari navigasi bawah, atau dari tombol "Siapkan & Kirim di HP" / "Hitung di HP" di daftar desktop.
- Di HP, menu samping selalu tertutup saat halaman dibuka. Menu dibuka lewat tombol "Menu" di bawah.

## 4. Langkah deploy

1. `php artisan migrate`. Ada 2 tabel baru: `delivery_order_item_checks` dan `help_videos`.
2. `npm ci && npm run build`. Ada aset baru `resources/js/scan-fallback.js`.
3. `php artisan optimize:clear`.
4. Isi **Gudang** untuk setiap user Checker di Pengguna & Hak Akses (`users.warehouse_id`).
5. Pemindai kamera hanya jalan di **HTTPS** (aturan browser). Di HTTP, layar otomatis jatuh ke input ketik manual.
6. Mode latihan (opsional, instans kedua):
   - Buat subdomain, mis. `latihan.dutatunggal.playgo.id`, dengan database yang **namanya mengandung "latihan"**.
   - Set `APP_TRAINING_MODE=true`, lalu `TRAINING_RESET_DAILY=true` dan `TRAINING_RESET_AT=02:00` bila ingin reset tiap malam.
   - Jalankan scheduler (`php artisan schedule:run` via cron).
   - Perintah `latihan:reset` menolak jalan bila mode latihan mati atau nama database tidak mengandung "latihan", jadi aman dari database produksi.

## 5. Belum dikerjakan / perlu tindak lanjut

- **Foto bukti di Retur DO.** Belum ada kolom foto di tabel retur. Bila diperlukan, tambahkan kolom dan upload di tahap berikutnya.
- **Isi video tutorial.** Tim perlu merekam lalu memasukkan link lewat PENGATURAN → Video Tutorial.
- **Keputusan D1.** Lihat bagian 2.
