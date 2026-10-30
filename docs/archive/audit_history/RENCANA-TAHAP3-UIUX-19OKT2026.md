# RENCANA TEKNIS TAHAP 3 – SPESIFIKASI UI/UX v1.1

Tanggal: 19/10/2026 · Acuan: `docs/baru/UIUX_Spec_ERP_Duta_Tunggal.md` Bagian 9 (Tahap 3), 6.5, 6.8, 6.12 · `Alur_Bisnis_Ko_Handy.md`
Kriteria selesai (spec): **Checker bisa mengirim DO dari HP tanpa membuka desktop.**

## 0. Ringkasan

| # | Pekerjaan | Mockup | Bergantung pada | Perkiraan |
|---|---|---|---|---|
| 3.0 | Kerangka HP: navigasi bawah, header HP, kartu (6.8) | F1 | – | 2 hari |
| 3.1 | Daftar kiriman checker + layar Siapkan & Kirim HP (6.5) | F1, E5 | 3.0 | 3 hari |
| 3.2 | "Ada kurang / rusak" → Retur DO dari HP | F2 | 3.1 | 2 hari |
| 3.3 | Picking list per gudang (layar + PDF) | F3 | – | 1,5 hari |
| 3.4 | Stock Opname HP, blind count (6.12) | D3 | 3.0 | 3 hari |
| 3.5 | Cek Stok HP + scan barcode (6.12) | D2, F4 | 3.0 | 2,5 hari |
| 3.6 | Scan barcode di checker & opname | F4 | 3.1, 3.4, 3.5 | 1 hari |
| 3.7 | Video tutorial per peran (halaman Bantuan) | – | 3.0 | 1 hari (+ pembuatan video) |
| 3.8 | Mode latihan | – | keputusan D2 | 2–3 hari |

Total ±18–20 hari kerja developer. Urutan kerja: 3.0 → 3.1 → 3.2 → 3.3 → 3.4 → 3.5/3.6 → 3.7 → 3.8.

## 1. Yang sudah ada di kode (dipakai ulang, tidak diubah logikanya)

- DO: transisi status satu pintu `App\Services\DeliveryOrderTransitions` (`to($do, 'sent')`), validasi stok `DeliveryShipments`, aksi `DeliveryOrderActions::dispatch()`, "Checker Edit Qty" (`checker_edit_quantity`).
- Retur DO: `ReturnProductService`, `DeliveryOrderReturnProcessor` (restock, gudang karantina, DO pengganti setelah approve).
- Opname: `StockOpnameService::startPhysicalCount()` (isi item, `physical_qty = null`), `completePhysicalCount()`, `approveStockOpname()` (jurnal penyesuaian). Model `StockOpnameItem` sudah memisahkan `system_qty` / `physical_qty`.
- Barcode: label produk sudah mencetak **SKU** sebagai Code128 (`resources/views/pdf/product-*-barcode.blade.php`, paket `milon/barcode`). Hasil scan = SKU, jadi **tidak perlu kolom barcode baru**.
- Stok bebas: `InventoryStock::qty_available − qty_reserved`.
- Hak akses: `config/menu_access.php` + trait `HasMenuAccess` (Tahap 1).

## 2. Rincian pekerjaan

### 3.0 Kerangka HP (prasyarat, dari 6.8)
- Render hook `PanelsRenderHook::BODY_END` → `filament.hooks.mobile-bottom-nav`: tinggi 70px, 5 item **Beranda · (menu utama peran) · [+] Buat · Stok · Menu**, hanya tampil < 768px. Item kedua per peran: Checker/Gudang = "Kirim", Kasir = "Approve", Sales = "Penjualan".
- Tombol [+] membuka sheet "Buat baru" sesuai peran (SO, Terima Pembayaran, Permintaan Pembelian, Pindah Stok).
- CSS di `theme.css`: < 768px sidebar disembunyikan, tombol min 44px, tombol utama lebar penuh 54–56px.
- Halaman HP baru dibuat sebagai Filament Page biasa (tetap lewat login, peran, `HasMenuAccess`), view Blade HP-first, tanpa paket baru.
- **File:** `resources/views/filament/hooks/mobile-bottom-nav.blade.php`, `app/Support/MobileNav.php`, `theme.css`, `AdminPanelProvider`.
- **Uji:** tes render per peran; uji manual di Chrome DevTools 390×844 dan HP asli.

### 3.1 Siapkan & Kirim di HP (6.5, mockup F1 + E5)
- Page `App\Filament\Pages\Gudang\KirimanPage` (`/admin/kiriman`): daftar DO gudang user dengan tab **Siap dicek · Menunggu SJ · Terkirim hari ini**, cari no. DO/customer, tombol scan, penanda "Tertahan > 1 hari".
- Page `KirimanDetailPage` (`/admin/kiriman/{do}`): header "‹ Kiriman (n)" + badge peran, pill gudang, no. DO, customer; daftar barang sebagai checkbox 26px dengan qty 24/800; teks "x dari n barang sudah dicek".
- Status centang disimpan per baris (kolom baru `delivery_order_items.checked_at`, `checked_by`) supaya tidak hilang bila HP terkunci/berpindah, dan tercatat siapa yang mengecek.
- Tombol **"Semua sesuai – KIRIM"** (min-h 56) disabled sampai semua dicentang, dengan alasan. Aksi memanggil `DeliveryOrderTransitions::to($do, 'sent')` (validasi stok, log, jurnal tetap sama). Bila ditolak (mis. belum ada SJ / stok fisik kurang) → tombol disabled + alasan dari exception.
- Gudang user: dari kolom `users.warehouse_id` (sudah ada di database); bila kosong, user memilih gudang sekali dan diingat di sesi.
- **File:** 2 page + 2 view, migrasi `add_checked_columns_to_delivery_order_items`, `config/menu_access.php` (GDG, OWN).
- **Uji otomatis:** daftar hanya DO gudang user; KIRIM disabled sebelum semua dicentang; KIRIM → status `sent`, stok berkurang, log ada; peran non-GDG 403.

### 3.2 Ada kurang / rusak → Retur DO dari HP (mockup F2)
- Tombol kuning di layar 3.1 membuka `ReturDoHpPage`: per barang input **Kurang** dan **Rusak/reject** dengan −/+ (44px); "Diterima" dihitung otomatis; foto opsional (upload ke disk privat yang sama dengan retur sekarang).
- Validasi: kurang + rusak ≤ qty DO. Ringkasan dampak sebelum simpan (stok gudang asal bertambah, reject ke karantina, DO pengganti `…C`).
- Simpan memakai `ReturnProductService` (nomor `DO.000xR`), lalu masuk antrian approve seperti sekarang. Tidak ada logika stok baru.
- **Uji:** retur tersimpan dengan qty benar; validasi melebihi qty; nomor turunan DO.

### 3.3 Picking list per gudang (mockup F3)
- Aksi "Cetak picking list" di daftar kiriman dan di tabel DO desktop (bulk). PDF `resources/views/pdf/picking-list.blade.php` (DomPDF seperti dokumen lain): gabungan DO "Siap dicek & dikirim" satu gudang, urut **Rak → Produk**, kolom Total, rincian per DO, kotak centang, barcode SKU, tanda tangan pengecek.
- Route `GET /pdf/picking-list?warehouse=..&do[]=..` dengan Policy gudang.
- **Uji:** isi PDF (total per produk = jumlah item DO), hanya DO gudang tersebut.

### 3.4 Stock Opname di HP, blind count (6.12, mockup D3)
- Page `OpnameHpPage` (`/admin/opname-hp/{opname}`): satu barang per layar, progress "12 / 30 dihitung" + bar, pill gudang/rak, nama/kode/satuan, input besar dengan −/+, catatan "Jumlah di sistem disembunyikan…".
- **`system_qty` tidak dikirim ke browser** selama status `in_progress` (bukan sekadar disembunyikan di tampilan).
- Tombol **Lewati** dan **Simpan & berikutnya**; daftar "Sudah dihitung" dengan tombol ubah; tombol "Jeda" kembali ke daftar opname.
- Urutan barang: rak → nama produk. Tombol "Selesai menghitung" → `completePhysicalCount()`; setelah itu selisih tampil di desktop untuk direview lalu disetujui (alur lama, menjadi Penyesuaian Stok).
- Daftar opname untuk HP: opname berstatus Draft/Sedang berlangsung di gudang user, tombol "Mulai/lanjutkan hitung" (`startPhysicalCount()` bila masih draft).
- **Uji:** respons Livewire tidak memuat `system_qty`; simpan per item; selesai ditolak bila ada item belum dihitung; selisih muncul setelah selesai.

### 3.5 Cek Stok di HP + scan (6.12, mockup D2 + F4)
- Halaman Cek Stok yang sudah ada (`InventoryStockResource`) tetap untuk desktop. Page `CekStokHpPage` untuk HP: kolom cari besar + tombol scan; hasil teratas dibuka (stok bebas angka besar, rincian per gudang dengan bar, tombol Pindah stok / Minta pembelian), hasil lain kartu ringkas. Kalimat "Stok bebas = stok fisik − stok dikunci DO" di bawah judul.
- Menu "Stok" di navigasi bawah mengarah ke halaman ini.
- **Uji:** stok bebas = fisik − dikunci; cari berdasarkan SKU/nama.

### 3.6 Scan barcode
- Komponen Blade `<x-scan-barcode>` + JS Vite entry `resources/js/scan-barcode.js`: pakai **BarcodeDetector** bawaan browser (Chrome Android) dan cadangan **html5-qrcode** (npm, lisensi Apache-2.0, mendukung Code128) bila tidak tersedia. Butuh HTTPS (sudah, dutatunggal.playgo.id).
- Hasil scan (SKU) → event Livewire `barcode-scanned`:
  - Cek Stok: buka produk.
  - Siapkan & Kirim: centang barang yang cocok; bila produk tidak ada di DO → peringatan "Barang ini tidak ada di DO-xxxx".
  - Opname: lompat ke barang tersebut.
- Selalu ada input manual "Ketik kode produk" (kamera ditolak / label rusak).
- **Uji:** event dengan SKU mencentang baris yang benar; SKU asing memberi peringatan.

### 3.7 Video tutorial per peran
- Page "Bantuan" (ikon ? di header + menu PENGATURAN untuk Owner): daftar video per peran (Kasir, Checker, Admin Pengiriman, Sales, Purchasing, Finance), masing-masing 2–4 menit.
- Data video di tabel `help_videos` (judul, peran, URL, urutan) dikelola Owner; tampilan embed YouTube unlisted atau file MP4.
- Konten video dibuat setelah 3.1–3.5 selesai (rekaman layar HP + desktop).

### 3.8 Mode latihan
- Lihat keputusan D2. Usulan: **instans terpisah** `latihan.dutatunggal.playgo.id` (kode sama, database sendiri), banner kuning permanen "MODE LATIHAN – data tidak nyata", data contoh dari seeder, reset otomatis tiap malam (`php artisan latihan:reset` via scheduler). Tidak menyentuh data produksi sama sekali.

## 3. Keputusan yang dibutuhkan sebelum mulai

| # | Pertanyaan | Usulan |
|---|---|---|
| D1 | **Urutan KIRIM vs Surat Jalan.** `Alur_Bisnis_Ko_Handy.md` menulis DO → gudang kirim → Retur DO → SJ, tetapi `List_Pengerjaan_Developer.md` B22 dan setelan sekarang (`SALES_STOCK_REQUIRE_SURAT_JALAN=true`) mewajibkan SJ sebelum DO dikirim. | Ikuti Alur Bisnis (prioritas tertinggi): checker boleh KIRIM sebelum SJ; set `SALES_STOCK_REQUIRE_SURAT_JALAN=false`. Bila tetap wajib SJ, layar checker menampilkan tab "Menunggu SJ" (mockup F1). |
| D2 | Mode latihan: instans terpisah atau tanda `is_training` di data produksi? | Instans terpisah (lebih aman, tidak mencampur laporan). Butuh 1 subdomain + 1 database di server. |
| D3 | Video tutorial: siapa yang merekam dan di mana disimpan? | Tim UAT merekam; disimpan YouTube unlisted. |
| D4 | Library scan: boleh menambah paket npm `html5-qrcode`? | Ya (hanya dipakai bila BarcodeDetector tidak ada). |
| D5 | Gudang checker: apakah setiap checker terikat 1 gudang? | Pakai kolom `users.warehouse_id` yang sudah ada (isi di Pengguna & Hak Akses); bila kosong, checker memilih gudang sekali per sesi. |

## 4. Mockup baru

`docs/baru/mockups/`: **F1** Daftar kiriman checker (HP), **F2** Retur DO dari HP, **F3** Picking list per gudang (A4), **F4** Scan barcode (HP). Melengkapi E5, D2, D3 yang sudah ada.
