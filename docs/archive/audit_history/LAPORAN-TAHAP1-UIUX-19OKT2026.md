# LAPORAN PENYELESAIAN TAHAP 1 – SPESIFIKASI UI/UX v1.1

Tanggal: 19/10/2026 · Acuan: `docs/baru/UIUX_Spec_ERP_Duta_Tunggal.md` Bagian 9 (Tahap 1), `docs/baru/menu_mapping.json`

Dokumen ini melanjutkan pekerjaan Tahap 1 yang sudah ada di laptop (sidebar teks, grup & label baru, hub disembunyikan, redirect hub). Isi di bawah adalah bagian yang **ditambahkan** untuk menyelesaikan kriteria Tahap 1.

## 1. Status kriteria selesai Tahap 1

| Kriteria (Bagian 9) | Status | Bukti |
|---|---|---|
| Setiap peran login hanya melihat menunya (5.2) | ✅ | `tests/Feature/Tahap1/MenuAksesPerPeranTest.php`; tabel di Bagian 3 laporan ini |
| Tidak ada halaman Hub | ✅ | Hub tidak terdaftar; route lama 301 (`NavigasiTanpaHubTest`) |
| Tidak ada teks Inggris di menu/tombol/status | ✅ (menu, tombol, label kolom/field, status SO/DO, legenda warna) | `lang/id.json` + `NavigasiTanpaHubTest` |
| Tidak ada stack trace | ✅ | `tests/Feature/Tahap1/PesanErrorTest.php` |
| Peran Kasir di Aturan Persetujuan | ✅ (sudah ada sebelumnya, migrasi 2026_10_13_230100) | – |
| Tema warna & font (Bagian 2) | ✅ | `resources/css/filament/admin/theme.css` |
| Sembunyikan modul tidak dipakai | ✅ | PENGATURAN → Modul Aktif |

## 2. Perubahan

### 2.1 Filter menu & URL per peran
- `config/menu_access.php` – peta kode peran (OWN, SM, SLS, KSR, ADP, GDG, PUR, FIN) ke role Spatie, dan peta class Resource/Page → kode peran (sesuai matriks 5.2 + `pemetaan_menu_lama`).
- `app/Support/MenuAccess.php` – pengecekan akses + status Modul Aktif.
- `app/Filament/Concerns/HasMenuAccess.php` – trait yang dipasang di semua Resource/Page yang ada di peta. Mengunci **menu dan URL** sekaligus (URL di luar peran → 403), tetap ditambah pengecekan Policy/permission yang sudah ada.
- User yang perannya tidak ada di peta (mis. peran baru buatan admin) tidak dibatasi peta ini; aksesnya tetap lewat permission.
- Peran baru **Admin Pengiriman** (ADP): migrasi `2026_10_19_090000_create_admin_pengiriman_role.php`, `RoleSeeder`, `UserSeeder` (akun contoh `admin_pengiriman@example.com`). Tanpa izin hapus dan tanpa konfirmasi DO.
- Policy Kas & Bank, Transfer Kas, Rekonsiliasi Bank: Owner/Admin ditambahkan (sebelumnya Owner tidak bisa membuka). Kasir boleh **melihat** Kas & Bank.
- Aturan Persetujuan: hanya Owner / Super Admin / Admin (sesuai 5.2; sebelumnya Super Admin + Finance Manager).

### 2.2 Modul Aktif
- `app/Filament/Pages/ModulAktifPage.php` + view – PENGATURAN → Modul Aktif: Produksi/Manufaktur, Jadwal Kirim, Penjualan Lainnya (default nonaktif). Disimpan di `app_settings` (`modules.*`).
- Menu Produksi pindah ke grup **PRODUKSI** (hanya tampil bila modul aktif). Jadwal Kirim → PENJUALAN, Penjualan Lainnya → KEUANGAN.

### 2.3 Pesan error
- `bootstrap/app.php` – bila `APP_DEBUG=false`, error server tampil sebagai "Terjadi kesalahan sistem… (kode: SJ-500-7KQ2)". Kode = modul + status + referensi; referensi yang sama tercatat di log (`error_ref`).
- `app/Support/ErrorCode.php` – singkatan modul dari URL (SO, DO, SJ, INV, PO, …).
- `resources/views/errors/*.blade.php` – halaman 403, 404, 419, 429, 500, 503 Bahasa Indonesia.
- `resources/views/filament/hooks/livewire-error-handler.blade.php` – kegagalan request Livewire (500/403/419) tampil sebagai notifikasi, bukan modal berisi halaman error.

### 2.4 Bahasa Indonesia
- `lang/id.json` (±430 istilah) + `FilamentServiceProvider`: semua label tombol, kolom, field, filter, dan entri diterjemahkan otomatis (termasuk label bawaan dari nama field seperti "Created at" → "Dibuat pada").
- Label status SO & DO mengikuti 7.2 (mis. "Menunggu approve kasir", "Siap dibuatkan DO", "Menunggu konfirmasi gudang", "Siap dicek & dikirim", "Dalam pengiriman", "Diterima customer", "Gagal kirim").
- Legenda warna di daftar SO/DO/PO/Quotation/SJ tanpa istilah Inggris.

### 2.5 Tema
- `resources/css/filament/admin/theme.css` + `tailwind.config.js` – token Bagian 2 & komponen 3.2 (sidebar 240px, item aktif hijau, header 50px, kartu radius 16 tanpa bayangan, tombol radius 10 min-h 44, judul 28/800).
- `AdminPanelProvider` – `viteTheme()` hanya dipasang bila sudah di-build (mencegah error "Vite manifest" bila deploy memakai `--quick`); bila belum di-build, CSS sidebar lama tetap dipakai.
- Logo "DUTA TUNGGAL" + badge ERP, nama · peran · cabang di kanan header.
- `Dockerfile` – stage `filament-vendor` agar build tema di Docker menemukan `vendor/filament`.
- Beranda Tugas Saya: judul "Halo, {nama}"; ringkasan aset hanya untuk Owner/Keuangan (widget tugas per peran = Tahap 2).

### 2.6 Lain-lain
- Redirect `/admin/finance-reports` → `/admin/financial-statement` (sebelumnya ke Laporan Penjualan).
- Sidebar: hanya grup BERANDA yang ditampilkan tanpa judul; grup berisi 1 menu tetap berjudul.

## 3. Hasil per peran (login di lingkungan uji)

Izin **lihat** (view any + view) untuk menu di matriks ditambahkan lewat migrasi `2026_10_19_100000_grant_menu_view_permissions.php` (daftar izin di `app/Support/MenuViewPermissions.php`, juga dipakai `RoleSeeder`). Hanya izin melihat: membuat/mengubah/menyetujui tetap seperti sebelumnya. Izin yang sudah ada tidak dicabut. Tambahan: Dashboard Owner terbuka untuk semua peran Keuangan, Admin Keuangan boleh melihat Rekonsiliasi Bank.

Hasil: semua peran melihat **tepat** menu di matriksnya — tidak ada menu di luar matriks dan tidak ada yang kurang.

| Peran | Menu tampil |
|---|---|
| Owner / Super Admin / Admin | 42 |
| Sales Manager | 7 |
| Sales, Customer Service | 9 |
| Kasir | 9 |
| Admin Pengiriman | 8 |
| Checker, Warehouse Staff, Admin Inventory, Inventory Manager | 12 |
| Purchasing, Purchasing Manager | 12 |
| Finance Manager, Accounting | 23 |
| Admin Keuangan | 23 |

Catatan: peran Auditor, Delivery Driver, dan IT Support tidak diubah izinnya.

## 4. Pengujian otomatis
- Baru: `tests/Feature/Tahap1/` (3 file, 26 tes) – lulus.
- Regresi: seluruh suite dijalankan sebelum & sesudah. Tes yang gagal karena perubahan ini sudah disesuaikan (label status/teks Indonesia, Aturan Persetujuan, contoh user Credit Note). Kegagalan lain yang tersisa sudah gagal sebelum perubahan (tidak terkait Tahap 1).
- Catatan lingkungan: server CI/uji perlu ekstensi PHP `bcmath` (dipakai `CurrencyConversionResolver`); tanpa itu ±700 tes gagal.

## 5. Cara deploy
1. `php artisan migrate --force` (membuat peran Admin Pengiriman dan menambah izin lihat sesuai matriks).
2. `npm ci && npm run build` (tema baru). Docker: `docker compose build app` sudah mencakup.
3. `php artisan optimize:clear && php artisan config:cache && php artisan view:cache`.
4. Pastikan `.env` produksi `APP_DEBUG=false`.
5. Owner: buka PENGATURAN → Modul Aktif bila modul Produksi / Jadwal Kirim / Penjualan Lainnya masih dipakai.

## 6. Cara uji manual
1. Login `kasir@example.com` (password `password`): sidebar hanya Tugas Saya, Pesanan Penjualan (SO), Perintah Kirim (DO), Invoice Penjualan, Terima Pembayaran, Cek Stok, Kas & Bank, Piutang & Hutang, Customer. Buka `/admin/journal-entries` → halaman "Anda tidak punya akses" (403).
2. Login `checker@example.com`: tidak ada menu penjualan/keuangan.
3. Login `owner@example.com`: semua grup tampil termasuk PENGATURAN › Modul Aktif. Aktifkan "Produksi / Manufaktur" → grup PRODUKSI muncul setelah muat ulang.
4. Buka `/admin/sales-hub` → dialihkan ke Pesanan Penjualan.
5. Dengan `APP_DEBUG=false`, picu error (mis. hentikan database sebentar) → pesan "Terjadi kesalahan sistem… (kode: …)", tidak ada stack trace; cari kode itu di `storage/logs`.
6. Periksa tombol Approve/Reject di daftar → tampil "Setujui"/"Tolak"; status DO "Dalam pengiriman".
