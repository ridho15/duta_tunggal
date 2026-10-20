# SPESIFIKASI UI/UX & NAVIGASI – ERP DUTA TUNGGAL

Versi 1.1 · 13 Oktober 2026 · Disusun oleh tim UAT (Reyner / Hassee)
Sistem: https://dutatunggal.playgo.id/admin · Stack: Laravel 12, PHP 8.3, Filament v3, Livewire 3

> **Cara pakai dokumen ini.** Dokumen ini ditulis agar bisa langsung diberikan ke developer **atau ditempel ke AI coding assistant** (Claude, ChatGPT, Cursor, Copilot) sebagai konteks. Bagian 0 adalah prompt pembuka. Bagian 1–9 adalah spesifikasi. File `menu_mapping.json` berisi data yang sama dalam format mesin. Referensi visual: folder `mockups/` (15 layar, HTML + PNG, indeks di 6.13) dan gaya HRIS HR Group (hrd.playgo.id). Aturan bisnis ada di `Alur_Bisnis_Ko_Handy.md`, daftar bug & fitur belum ada di `List_Pengerjaan_Developer.md`.

---

## 0. PROMPT PEMBUKA UNTUK AI

Salin blok di bawah ini ke AI, lalu lampirkan: dokumen ini, `menu_mapping.json`, `Alur_Bisnis_Ko_Handy.md`, `List_Pengerjaan_Developer.md`, dan gambar PNG dari folder `mockups/` untuk layar yang sedang dikerjakan.

```text
Anda adalah senior Laravel + Filament v3 developer dan UI/UX engineer.
Proyek: ERP distributor "Duta Tunggal" (Laravel 12, PHP 8.3, Filament v3, Livewire 3).
Pengguna akhir: staf toko, kasir, admin gudang, checker yang SANGAT AWAM teknologi.

Tugas Anda: terapkan spesifikasi UI/UX dan navigasi pada dokumen terlampir.

Aturan kerja:
1. Jangan mengubah logika bisnis, perhitungan stok, jurnal, atau skema database
   kecuali disebut eksplisit di spesifikasi. Fokus: navigasi, tampilan, label, UX form.
2. Ikuti pemetaan menu di menu_mapping.json PERSIS: grup baru, label baru, urutan,
   dan peran yang boleh melihat. Menu dengan aksi SEMBUNYIKAN tidak dihapus,
   hanya tidak didaftarkan di navigasi (dan dikendalikan oleh setting Modul Aktif).
3. Semua teks UI dalam Bahasa Indonesia, mengikuti glosarium Bagian 7.
4. Gunakan design token Bagian 2. Tema lewat Filament custom theme (Vite),
   bukan inline style di Blade.
5. Hak akses: navigasi DAN akses halaman dibatasi per peran (Policy / canViewAny),
   bukan hanya disembunyikan dari sidebar.
6. Kerjakan per tahap sesuai Bagian 9. Setiap tahap: daftar file yang diubah,
   ringkasan perubahan, dan cara uji manual.
7. Jika ada hal ambigu atau bertentangan dengan kode yang ada, tanyakan dulu.
   Jangan menebak.
8. Mockup PNG/HTML adalah acuan tata letak dan perilaku, bukan kode final.
   Bangun dengan komponen Filament/Blade; angka di mockup hanya contoh.
9. Aturan bisnis di Alur_Bisnis_Ko_Handy.md lebih tinggi prioritasnya
   daripada mockup jika keduanya berbeda.
```

---

## 1. TUJUAN & PRINSIP

**Masalah saat ini (hasil UAT 13/10/2026):**

1. Sidebar hanya 11 ikon tanpa tulisan. Setiap ikon membuka halaman "Hub", baru pengguna memilih menu. Minimal 3 klik untuk membuka satu transaksi.
2. ±80 submenu terlihat oleh semua orang, termasuk menu akuntansi dan manufaktur.
3. Banyak menu dobel atau salah tempat (contoh: Kas & Bank di hub Pembelian, Konfirmasi Gudang di Inventory, 5 menu laporan stok, 5 menu piutang/hutang).
4. Tidak ada petunjuk langkah berikutnya dalam alur SO → DO → SJ → Invoice.
5. Tombol muncul/hilang sesuai status tanpa penjelasan. Pesan error teknis (SQL, stack trace) tampil ke pengguna.
6. Bahasa campur Inggris–Indonesia.

**Prinsip desain (wajib diikuti semua layar):**

| # | Prinsip | Penerapan |
|---|---|---|
| P1 | Satu layar, satu tugas | Kolom jarang dipakai disimpan di bagian "Lainnya" (collapsible). |
| P2 | Sistem yang mengingat | Data dokumen sebelumnya terisi otomatis. Tidak ada input produk ulang (SO → DO → SJ → Invoice). |
| P3 | Selalu ada langkah berikutnya | Setiap dokumen punya 1 tombol utama (primary) sesuai status. |
| P4 | Cegah salah, jangan hanya menolak | Stok, sisa qty, batas harga, limit kredit tampil di samping input sebelum simpan. |
| P5 | Tidak ada tombol hilang misterius | Aksi yang belum bisa dijalankan tampil **disabled + alasan**. |
| P6 | Bahasa sehari-hari | Bahasa Indonesia sederhana, istilah konsisten di menu, tombol, status, cetakan. |
| P7 | Maksimal 2 klik | Setiap transaksi harian bisa dibuka ≤ 2 klik dari Beranda. |

---

## 2. DESIGN TOKENS

Gaya visual mengikuti HRIS HR Group: putih bersih, aksen hijau, kartu rounded, garis tipis.

### 2.1 Warna

| Token | Hex | Pemakaian |
|---|---|---|
| `bg.page` | `#F6F7F8` | Latar halaman |
| `bg.surface` | `#FFFFFF` | Sidebar, header, kartu |
| `border.default` | `#E5E7EB` | Border kartu, header, sidebar |
| `border.subtle` | `#EEF0F2` | Garis pemisah di dalam kartu |
| `border.dashed` | `#D1D5DB` | Empty state, kotak info sekunder (dashed) |
| `text.primary` | `#111827` | Judul, isi utama |
| `text.body` | `#374151` | Item menu, label |
| `text.muted` | `#6B7280` | Deskripsi, keterangan, label grup |
| `text.disabled` | `#9CA3AF` | Tombol/teks nonaktif |
| `primary.600` | `#15803D` | Tombol utama, ikon aktif, link |
| `primary.900` | `#14532D` | Teks menu aktif |
| `primary.50` | `#ECFDF3` / `#F0FDF4` | Latar menu aktif, latar sukses |
| `primary.200` | `#BBF7D0` / `#86EFAC` | Border menu aktif, pill tanggal |
| `warning.bg / border / text` | `#FFFBEB` / `#FCD34D` / `#92400E` | Peringatan (stok kurang, perlu atasan) |
| `danger.bg / border / text` | `#FEF2F2` / `#FCA5A5` / `#B91C1C` | Error (harga di bawah batas) |
| `info.bg / border / text` | `#EFF6FF` / `#BFDBFE` / `#1D4ED8` | Status netral/proses |

Filament: `->colors(['primary' => Color::Green, 'warning' => Color::Amber, 'danger' => Color::Red, 'info' => Color::Blue, 'gray' => Color::Gray])`.

### 2.2 Tipografi

- Font: **Plus Jakarta Sans** (Google Fonts), fallback `sans-serif`. Filament: `->font('Plus Jakarta Sans')`.
- Skala: Judul halaman 28px/800 · Judul kartu 16px/700 · Body 14px/400–500 · Keterangan 12–13px · Label grup sidebar 10.5px/600 kapital, letter-spacing 1px · Angka besar (KPI/jumlah tugas) 22–24px/800.

### 2.3 Spasi, radius, ukuran

| Token | Nilai |
|---|---|
| Lebar sidebar | 240px (selalu terbuka di desktop, **tidak** collapsed ke ikon) |
| Tinggi header | 50px |
| Lebar konten maks | 1080px, center |
| Padding konten | 26px 28px |
| Jarak antar kartu | 20px |
| Radius kartu | 16px |
| Radius tombol/input | 10px |
| Radius pill/badge | 6px (pill) / 10px (badge angka) |
| Tinggi minimum tombol | 44px (HP: 52–56px) |
| Ikon | Heroicons outline 18px, stroke 1.8 |
| Bayangan | Tidak ada / sangat tipis. Pemisahan pakai border. |

---

## 3. LAYOUT & KOMPONEN

### 3.1 Kerangka halaman

```
┌──────────────┬──────────────────────────────────────────────────┐
│ DUTA TUNGGAL │ Duta Tunggal / <Halaman>          Nama · Cabang (●)│ ← header 50px
│ [ERP]        ├──────────────────────────────────────────────────┤
│──────────────│        ┌ pill tanggal / status ┐                   │
│ GRUP         │        Judul Halaman (28/800)                     │
│ ▣ Menu aktif │        Deskripsi 1 kalimat (muted)                │
│ □ Menu       │        ───────────────────────────────            │
│ □ Menu       │        ┌ Kartu ───────────────────────┐            │
│ GRUP         │        │ (ikon) Judul kartu   [badge] │            │
│ □ Menu       │        │ ─────────────────────────────│            │
│              │        │ isi                          │            │
│              │        └──────────────────────────────┘            │
└──────────────┴──────────────────────────────────────────────────┘
  240px                     konten maks 1080px, center
```

### 3.2 Komponen

| Komponen | Spesifikasi |
|---|---|
| **Logo sidebar** | Teks "DUTA TUNGGAL" 15/800 + badge "ERP" (9px/700, hijau, border `primary.200`). |
| **Label grup** | Kapital 10.5px, `text.muted`, padding 14px 10px 6px. |
| **Item menu** | Ikon 18px + label 14px/500 `text.body`, padding 9px 10px, radius 10px. **Aktif:** bg `primary.50`, border 1px `primary.200`, teks `primary.900` 600. **Badge** jumlah tugas: bg `primary.600`, teks putih 11px. |
| **Header** | Breadcrumb "Duta Tunggal / Halaman" (bagian akhir bold). Kanan: nama user · cabang + avatar inisial. |
| **Pill tanggal/status** | 11px/700 kapital, ikon kalender 13px, bg `primary.50`, border `primary.200`, teks `primary.600`. Warna mengikuti status (info/warning). |
| **Kartu** | bg putih, border `border.default`, radius 16, padding 20/22. Header kartu: ikon dalam kotak 34px bg `primary.50` + judul + deskripsi 12px + badge kanan; garis bawah `border.subtle`. |
| **Baris tugas** | Angka besar (22/800) + judul 15/600 + deskripsi 13 muted + tombol aksi kanan. Antar baris dipisah garis tipis. |
| **Tombol primary** | bg `primary.600`, putih, 14/600, radius 10, min-h 44. Hanya 1 per area. |
| **Tombol secondary** | Putih, border `#D1D5DB`, teks `text.body`. |
| **Tombol disabled + alasan** | Border dashed, bg `#F9FAFB`, teks `text.disabled`, diikuti teks alasan 13px: "Belum bisa: menunggu …". |
| **Stepper progres** | 6 kolom: garis atas 3px. Selesai = hijau + "✓", aktif = hitam, belum = abu. Sub-teks tanggal/pelaku/qty. |
| **Kotak langkah berikutnya** | bg `primary.50`, border `primary.200`, label "LANGKAH BERIKUTNYA", judul 16/700, deskripsi, tombol primary di kanan. |
| **Input** | Label 13/600 di atas, input radius 10, border `#D1D5DB`. Keterangan bantu di bawah (12px muted). Error: border merah + teks merah 700. |
| **Chip info** | Contoh "Stok bebas 9 pcs": 11px/600, hijau (cukup) / kuning (kurang) / merah (habis). |
| **Empty state** | Kotak dashed, ikon abu, 1 kalimat apa yang terjadi + 1 kalimat kapan akan terisi. |
| **Ringkasan (aside)** | Kartu kanan: baris label–nilai, total 18/800, alert, lalu tombol primary + secondary. |

---

## 4. STRUKTUR NAVIGASI BARU

### 4.1 Aturan

1. **Hapus semua halaman Hub** (`dashboard-hub`, `sales-hub`, `purchase-hub`, `delivery-hub`, `accounting-hub`, `inventory-hub`, `master-data-hub`, `user-roles-management-hub`, `manufacturing-hub`). Sidebar langsung menuju halaman tujuan.
2. Sidebar **selalu menampilkan ikon + teks** di desktop (matikan collapse ke ikon).
3. Urutan grup tetap: BERANDA → PENJUALAN → GUDANG → PEMBELIAN → KEUANGAN → LAPORAN → DATA MASTER → PENGATURAN.
4. Menu yang digabung (aksi **GABUNG**) menjadi **satu halaman bertab** (Filament Cluster atau Page dengan Tabs). Contoh: "Laporan Keuangan" berisi tab Neraca, Laba Rugi, Neraca Saldo, Buku Besar, Arus Kas, HPP, dan sub-bagian "Lanjutan".
5. Grup yang semua menunya tidak boleh dilihat suatu peran **tidak tampil** untuk peran tersebut.
6. Route lama tetap berfungsi (redirect 301 ke route/tab baru) agar bookmark tidak rusak.

### 4.2 Sidebar final

**BERANDA**

- Tugas Saya — `heroicon-o-home` — peran: SEMUA (isi berbeda per peran)

**PENJUALAN**

- Penawaran Harga — `heroicon-o-tag` — peran: OWN,SM,SLS
- Pesanan Penjualan (SO) — `heroicon-o-document-text` — peran: OWN,SM,SLS,KSR,ADP
- Perintah Kirim (DO) — `heroicon-o-truck` — peran: OWN,ADP,SLS,KSR
- Surat Jalan — `heroicon-o-clipboard-document-check` — peran: OWN,ADP
- Invoice Penjualan — `heroicon-o-receipt-percent` — peran: OWN,KSR,ADP,FIN
- Terima Pembayaran — `heroicon-o-credit-card` — peran: OWN,KSR,FIN
- Retur — `heroicon-o-arrow-uturn-left` — peran: OWN,SM,SLS,GDG,ADP,FIN

**GUDANG**

- Siapkan & Kirim — `heroicon-o-clipboard-document-list` — peran: OWN,GDG
- Terima Barang — `heroicon-o-inbox-arrow-down` — peran: OWN,GDG,PUR
- Cek Stok — `heroicon-o-cube` — peran: SEMUA
- Pindah Stok — `heroicon-o-arrows-right-left` — peran: OWN,GDG
- Stock Opname — `heroicon-o-clipboard-document` — peran: OWN,GDG
- Penyesuaian Stok — `heroicon-o-adjustments-horizontal` — peran: OWN,GDG,FIN

**PEMBELIAN**

- Permintaan Pembelian — `heroicon-o-document-plus` — peran: OWN,PUR,GDG,SLS
- Pesanan Pembelian (PO) — `heroicon-o-shopping-bag` — peran: OWN,PUR
- Invoice Pembelian — `heroicon-o-receipt-refund` — peran: OWN,PUR,FIN
- Bayar Supplier — `heroicon-o-banknotes` — peran: OWN,FIN
- Retur Pembelian — `heroicon-o-arrow-uturn-right` — peran: OWN,PUR,GDG

**KEUANGAN**

- Kas & Bank — `heroicon-o-building-library` — peran: OWN,FIN,KSR
- Piutang & Hutang — `heroicon-o-scale` — peran: OWN,FIN,KSR
- Pengajuan Dana — `heroicon-o-document-currency-dollar` — peran: OWN,FIN,PUR
- Jurnal — `heroicon-o-book-open` — peran: OWN,FIN
- Rekonsiliasi Bank — `heroicon-o-check-badge` — peran: OWN,FIN
- Aset Tetap — `heroicon-o-building-office` — peran: OWN,FIN
- Tutup Buku — `heroicon-o-lock-closed` — peran: OWN,FIN

**LAPORAN**

- Dashboard Owner — `heroicon-o-presentation-chart-line` — peran: OWN,FIN
- Penjualan — `heroicon-o-chart-bar` — peran: OWN,SM,FIN
- Pembelian — `heroicon-o-chart-bar-square` — peran: OWN,PUR,FIN
- Stok — `heroicon-o-chart-pie` — peran: OWN,GDG,PUR,FIN
- Laporan Keuangan — `heroicon-o-document-chart-bar` — peran: OWN,FIN

**DATA MASTER**

- Produk — `heroicon-o-squares-2x2` — peran: OWN,PUR
- Customer — `heroicon-o-users` — peran: OWN,SM,SLS,KSR,FIN
- Supplier — `heroicon-o-building-storefront` — peran: OWN,PUR,FIN
- Gudang & Rak — `heroicon-o-home-modern` — peran: OWN
- Cabang — `heroicon-o-map-pin` — peran: OWN
- Kendaraan & Driver — `heroicon-o-truck` — peran: OWN,ADP
- Akun & Pajak — `heroicon-o-calculator` — peran: OWN,FIN

**PENGATURAN**

- Pengguna & Hak Akses — `heroicon-o-user-group` — peran: OWN
- Aturan Persetujuan — `heroicon-o-shield-check` — peran: OWN
- Modul Aktif — `heroicon-o-puzzle-piece` — peran: OWN


Ringkasan: **±80 submenu di 11 hub → 41 menu di 8 grup** (Owner/Admin). Staf hanya melihat menu sesuai perannya: Sales Manager 7, Sales 8, Admin Pengiriman 8, Kasir 9, Gudang 11, Purchasing 12, Finance 23 (lihat 5.2).

### 4.3 Pemetaan lengkap menu lama → menu baru

Kolom **Aksi**: `TETAP` (posisi & nama sama) · `GANTI NAMA` · `PINDAH` (pindah grup) · `GABUNG` (jadi tab di menu lain) · `SEMBUNYIKAN` (tidak didaftarkan di navigasi, aktif lewat Modul Aktif).

| No | Hub lama | Menu lama | Route lama | Grup baru | Menu baru | Tab | Aksi | Peran | Catatan |
|---|---|---|---|---|---|---|---|---|---|
| 1 | Dashboard Hub | Finance Dashboard | `/admin (dashboard)` | LAPORAN | Dashboard Owner | – | **PINDAH** | OWN,FIN | Beranda default untuk Owner. Staf mendapat Beranda Tugas Saya. |
| 2 | Dashboard Hub | Laporan Penjualan | `/sales-report-page` | LAPORAN | Penjualan | – | **GABUNG** | OWN,SM,FIN | Digabung dengan Sales Report (Sales Hub). |
| 3 | Dashboard Hub | Laporan Pembelian | `/purchase-report-page` | LAPORAN | Pembelian | – | **PINDAH** | OWN,PUR,FIN |  |
| 4 | (ikon sidebar) | Stock Report | `/reports/stock-report` | LAPORAN | Stok | Posisi Stok | **GABUNG** | OWN,GDG,PUR,FIN | Ikon sidebar sendiri dihapus. |
| 5 | (ikon sidebar) | Aturan Persetujuan | `/approval-rules` | PENGATURAN | Aturan Persetujuan | – | **PINDAH** | OWN | Tambahkan peran Kasir pada aturan approve Sales Order. |
| 6 | Sales Hub | Quotations | `/quotations` | PENJUALAN | Penawaran Harga | – | **GANTI NAMA** | OWN,SM,SLS |  |
| 7 | Sales Hub | Sale Orders | `/sale-orders` | PENJUALAN | Pesanan Penjualan (SO) | – | **GANTI NAMA** | OWN,SM,SLS,KSR,ADP | ADP hanya lihat. |
| 8 | Sales Hub | Retur Pelanggan | `/customer-returns` | PENJUALAN | Retur | Retur Penjualan (setelah invoice) | **GABUNG** | OWN,SM,SLS,GDG,FIN | Satu menu Retur dengan 2 tab. |
| 9 | Sales Hub | Piutang Usaha | `/account-receivables` | KEUANGAN | Piutang & Hutang | Piutang | **GABUNG** | OWN,FIN,KSR | KSR hanya lihat. |
| 10 | Sales Hub | Invoice Penjualan | `/sales-invoices` | PENJUALAN | Invoice Penjualan | – | **TETAP** | OWN,KSR,ADP,FIN |  |
| 11 | Sales Hub | Penerimaan Pelanggan | `/customer-receipts` | PENJUALAN | Terima Pembayaran | – | **GANTI NAMA** | OWN,KSR,FIN |  |
| 12 | Sales Hub | Penjualan Lainnya | `/other-sales` | KEUANGAN | Penjualan Lainnya | – | **SEMBUNYIKAN** | OWN,FIN | Tampil hanya jika modul diaktifkan / memang dipakai. |
| 13 | Sales Hub | Sales Report | `/sales-report-page` | LAPORAN | Penjualan | – | **GABUNG** | OWN,SM,FIN | Duplikat Laporan Penjualan. |
| 14 | Purchase Hub | Permintaan Pembelian | `/order-requests` | PEMBELIAN | Permintaan Pembelian | – | **TETAP** | OWN,PUR,GDG,SLS |  |
| 15 | Purchase Hub | Pesanan Pembelian | `/purchase-orders` | PEMBELIAN | Pesanan Pembelian (PO) | – | **TETAP** | OWN,PUR |  |
| 16 | Purchase Hub | Kontrol Kualitas Pembelian | `/quality-control-purchases` | GUDANG | Terima Barang | Langkah QC di form yang sama | **GABUNG** | OWN,GDG,PUR | Qty bagus / qty reject diisi di form Terima Barang. |
| 17 | Purchase Hub | Penerimaan Pembelian | `/purchase-receipts` | GUDANG | Terima Barang | – | **PINDAH** | OWN,GDG,PUR |  |
| 18 | Purchase Hub | Retur Pembelian | `/purchase-returns` | PEMBELIAN | Retur Pembelian | – | **TETAP** | OWN,PUR,GDG |  |
| 19 | Purchase Hub | Utang Usaha | `/account-payables` | KEUANGAN | Piutang & Hutang | Hutang | **GABUNG** | OWN,FIN |  |
| 20 | Purchase Hub | Invoice Pembelian | `/purchase-invoices` | PEMBELIAN | Invoice Pembelian | – | **TETAP** | OWN,PUR,FIN |  |
| 21 | Purchase Hub | Permintaan Pembayaran | `/payment-requests` | KEUANGAN | Pengajuan Dana | Permintaan Pembayaran | **GABUNG** | OWN,FIN,PUR | Perlu konfirmasi beda fungsi dengan Pengajuan Voucher. |
| 22 | Purchase Hub | Pembayaran Vendor | `/vendor-payments` | PEMBELIAN | Bayar Supplier | – | **GANTI NAMA** | OWN,FIN |  |
| 23 | Purchase Hub | Transaksi Kas & Bank | `/cash-bank-transactions` | KEUANGAN | Kas & Bank | Transaksi | **PINDAH** | OWN,FIN,KSR | Salah tempat (sebelumnya di Pembelian). |
| 24 | Purchase Hub | Deposit | `/deposits` | KEUANGAN | Kas & Bank | Deposit / Uang Muka | **PINDAH** | OWN,FIN,KSR |  |
| 25 | Purchase Hub | Transfer Kas & Bank | `/cash-bank-transfers` | KEUANGAN | Kas & Bank | Transfer | **PINDAH** | OWN,FIN |  |
| 26 | Delivery Hub | Perintah Pengiriman | `/delivery-orders` | PENJUALAN | Perintah Kirim (DO) | – | **GANTI NAMA** | OWN,ADP,SLS,KSR | SLS & KSR hanya lihat. |
| 27 | Delivery Hub | Surat Jalan | `/surat-jalans` | PENJUALAN | Surat Jalan | – | **TETAP** | OWN,ADP |  |
| 28 | Delivery Hub | Penjadwalan Pengiriman | `/delivery-schedules` | PENJUALAN | Jadwal Kirim | – | **SEMBUNYIKAN** | OWN,ADP | Aktifkan bila Duta Tunggal memakai jadwal/rute. |
| 29 | Accounting Hub | Journal Entries | `/journal-entries` | KEUANGAN | Jurnal | Per baris | **GABUNG** | OWN,FIN |  |
| 30 | Accounting Hub | Journal Entries (Grouped) | `/journal-entries/grouped` | KEUANGAN | Jurnal | Per transaksi | **GABUNG** | OWN,FIN | Jadi toggle tampilan, bukan menu terpisah. |
| 31 | Accounting Hub | Tutup Buku & Periode | `/accounting-periods` | KEUANGAN | Tutup Buku | – | **TETAP** | OWN,FIN |  |
| 32 | Accounting Hub | AR & AP Management | `/ar-ap-management` | KEUANGAN | Piutang & Hutang | Ringkasan | **GABUNG** | OWN,FIN |  |
| 33 | Accounting Hub | Rekonsiliasi Bank | `/bank-reconciliations` | KEUANGAN | Rekonsiliasi Bank | – | **TETAP** | OWN,FIN |  |
| 34 | Accounting Hub | Ageing Schedule | `/ageing-schedules` | KEUANGAN | Piutang & Hutang | Umur Piutang & Hutang | **GABUNG** | OWN,FIN |  |
| 35 | Accounting Hub | Pengajuan Voucher | `/voucher-requests` | KEUANGAN | Pengajuan Dana | Voucher Kas/Biaya | **GABUNG** | OWN,FIN |  |
| 36 | Accounting Hub | Neraca (Balance Sheet) | `/reports/balance-sheets` | LAPORAN | Laporan Keuangan | Neraca | **GABUNG** | OWN,FIN |  |
| 37 | Accounting Hub | Laporan Laba Rugi (P&L) | `/reports/profit-and-losses` | LAPORAN | Laporan Keuangan | Laba Rugi | **GABUNG** | OWN,FIN |  |
| 38 | Accounting Hub | Neraca Saldo (Trial Balance) | `/trial-balance` | LAPORAN | Laporan Keuangan | Neraca Saldo | **GABUNG** | OWN,FIN |  |
| 39 | Accounting Hub | Buku Besar | `/buku-besar-page` | LAPORAN | Laporan Keuangan | Buku Besar | **GABUNG** | OWN,FIN |  |
| 40 | Accounting Hub | Laporan Arus Kas | `/reports/cash-flow` | LAPORAN | Laporan Keuangan | Arus Kas | **GABUNG** | OWN,FIN |  |
| 41 | Accounting Hub | HPP / Cost of Goods Sold | `/reports/hpp` | LAPORAN | Laporan Keuangan | HPP | **GABUNG** | OWN,FIN |  |
| 42 | Accounting Hub | Cost of Goods Manufacturing | `/cost-of-goods-manufacturing` | LAPORAN | Laporan Keuangan | Lanjutan: HPP Produksi | **SEMBUNYIKAN** | OWN,FIN | Ikut modul Manufaktur. |
| 43 | Accounting Hub | Aging Report (AR/AP) | `/reports/ageing-reports` | KEUANGAN | Piutang & Hutang | Umur Piutang & Hutang | **GABUNG** | OWN,FIN | Duplikat Ageing Schedule. |
| 44 | Accounting Hub | Profit per Divisi | `/profit-loss-multi-division` | LAPORAN | Laporan Keuangan | Lanjutan: Laba per Divisi | **GABUNG** | OWN,FIN |  |
| 45 | Accounting Hub | Drill Down Financial Report | `/drill-down-financial-report` | LAPORAN | Laporan Keuangan | Lanjutan: Drill Down | **GABUNG** | OWN,FIN |  |
| 46 | Accounting Hub | Financial Statement | `/financial-statement` | LAPORAN | Laporan Keuangan | Lanjutan: Laporan Gabungan | **GABUNG** | OWN,FIN |  |
| 47 | Accounting Hub | ALK Grafik | `/alk-grafik` | LAPORAN | Laporan Keuangan | Lanjutan: Grafik Analisis | **GABUNG** | OWN,FIN |  |
| 48 | Accounting Hub | Journal Consolidation | `/journal-consolidation` | LAPORAN | Laporan Keuangan | Lanjutan: Konsolidasi | **GABUNG** | OWN,FIN |  |
| 49 | Accounting Hub | Aset Tetap | `/assets` | KEUANGAN | Aset Tetap | Daftar Aset | **TETAP** | OWN,FIN |  |
| 50 | Accounting Hub | Transfer Aset | `/asset-transfers` | KEUANGAN | Aset Tetap | Transfer | **GABUNG** | OWN,FIN |  |
| 51 | Accounting Hub | Disposal Aset | `/asset-disposals` | KEUANGAN | Aset Tetap | Penghapusan | **GABUNG** | OWN,FIN |  |
| 52 | Inventory Hub | Stock Transfer | `/stock-transfers` | GUDANG | Pindah Stok | – | **GANTI NAMA** | OWN,GDG |  |
| 53 | Inventory Hub | Stock Adjustment | `/stock-adjustments` | GUDANG | Penyesuaian Stok | – | **GANTI NAMA** | OWN,GDG,FIN | Retur DO yang disetujui juga tercatat di sini. |
| 54 | Inventory Hub | Stock Opname | `/stock-opnames` | GUDANG | Stock Opname | – | **TETAP** | OWN,GDG |  |
| 55 | Inventory Hub | Return Product | `/return-products` | PENJUALAN | Retur | Retur DO (sebelum Surat Jalan) | **GABUNG** | OWN,SLS,GDG,ADP | Penomoran DO.000xR / DO.000xC. |
| 56 | Inventory Hub | Inventory Stock | `/inventory-stocks` | GUDANG | Cek Stok | – | **GANTI NAMA** | OWN,SM,SLS,KSR,ADP,GDG,PUR | Semua peran boleh lihat. |
| 57 | Inventory Hub | Stock Movement | `/stock-movements` | LAPORAN | Stok | Riwayat Keluar-Masuk | **GABUNG** | OWN,GDG,FIN |  |
| 58 | Inventory Hub | Konfirmasi Gudang | `/warehouse-confirmations` | GUDANG | Siapkan & Kirim | – | **PINDAH** | OWN,GDG | Satu daftar: DO yang perlu dikonfirmasi, dicek checker, dan dikirim. |
| 59 | Inventory Hub | Kartu Persediaan | `/reports/inventory-card` | LAPORAN | Stok | Kartu Stok | **GABUNG** | OWN,GDG,FIN |  |
| 60 | Inventory Hub | Laporan Stok | `/inventory-report-page` | LAPORAN | Stok | Laporan & Umur Stok | **GABUNG** | OWN,GDG,PUR,FIN |  |
| 61 | Master Data Hub | Produk | `/products` | DATA MASTER | Produk | Produk | **TETAP** | OWN,PUR | Label 'Harga Batas (%)' diperbaiki sesuai isi (nominal). |
| 62 | Master Data Hub | Kategori Produk | `/product-categories` | DATA MASTER | Produk | Kategori | **GABUNG** | OWN |  |
| 63 | Master Data Hub | Satuan | `/unit-of-measures` | DATA MASTER | Produk | Satuan | **GABUNG** | OWN |  |
| 64 | Master Data Hub | Rak | `/raks` | DATA MASTER | Gudang & Rak | Rak | **GABUNG** | OWN |  |
| 65 | Master Data Hub | Gudang | `/warehouses` | DATA MASTER | Gudang & Rak | Gudang | **GABUNG** | OWN |  |
| 66 | Master Data Hub | Cabang | `/cabangs` | DATA MASTER | Cabang | – | **TETAP** | OWN |  |
| 67 | Master Data Hub | Customer | `/customers` | DATA MASTER | Customer | – | **TETAP** | OWN,SM,SLS,KSR,FIN | Peringatan nama mirip saat input (cegah dobel). |
| 68 | Master Data Hub | Supplier | `/suppliers` | DATA MASTER | Supplier | – | **TETAP** | OWN,PUR,FIN |  |
| 69 | Master Data Hub | Chart of Account | `/chart-of-accounts` | DATA MASTER | Akun & Pajak | Akun (COA) | **GABUNG** | OWN,FIN |  |
| 70 | Master Data Hub | Mata Uang | `/currencies` | DATA MASTER | Akun & Pajak | Mata Uang | **GABUNG** | OWN,FIN |  |
| 71 | Master Data Hub | Setting Pajak | `/tax-settings` | DATA MASTER | Akun & Pajak | Pajak | **GABUNG** | OWN,FIN |  |
| 72 | Master Data Hub | Kendaraan | `/vehicles` | DATA MASTER | Kendaraan & Driver | Kendaraan | **GABUNG** | OWN,ADP |  |
| 73 | Master Data Hub | Driver | `/drivers` | DATA MASTER | Kendaraan & Driver | Driver | **GABUNG** | OWN,ADP |  |
| 74 | User & Roles Hub | User | `/users` | PENGATURAN | Pengguna & Hak Akses | Pengguna | **GABUNG** | OWN |  |
| 75 | User & Roles Hub | Role | `/roles` | PENGATURAN | Pengguna & Hak Akses | Peran | **GABUNG** | OWN |  |
| 76 | User & Roles Hub | Permission | `/permissions` | PENGATURAN | Pengguna & Hak Akses | Hak Akses | **GABUNG** | OWN |  |
| 77 | Manufacturing Hub | Bill of Material | `/bill-of-materials` | PRODUKSI (nonaktif) | Bill of Material | – | **SEMBUNYIKAN** | OWN | Seluruh modul Manufaktur disembunyikan via PENGATURAN > Modul Aktif. |
| 78 | Manufacturing Hub | Production Plan | `/production-plans` | PRODUKSI (nonaktif) | Rencana Produksi | – | **SEMBUNYIKAN** | OWN |  |
| 79 | Manufacturing Hub | Manufacturing Order | `/manufacturing-orders` | PRODUKSI (nonaktif) | Perintah Produksi | – | **SEMBUNYIKAN** | OWN |  |
| 80 | Manufacturing Hub | Material Issue | `/material-issues` | PRODUKSI (nonaktif) | Pengeluaran Bahan | – | **SEMBUNYIKAN** | OWN |  |
| 81 | Manufacturing Hub | Production | `/productions` | PRODUKSI (nonaktif) | Produksi | – | **SEMBUNYIKAN** | OWN |  |
| 82 | Manufacturing Hub | QC Manufacture | `/quality-control-manufactures` | PRODUKSI (nonaktif) | QC Produksi | – | **SEMBUNYIKAN** | OWN |  |

---

## 5. PERAN & HAK AKSES

### 5.1 Daftar peran

| Kode | Peran |
|---|---|
| OWN | Owner / Super Admin / Admin |
| SM | Sales Manager |
| SLS | Sales / Toko |
| KSR | Kasir (peran baru) |
| ADP | Admin Pengiriman |
| GDG | Admin Gudang / Checker |
| PUR | Purchasing |
| FIN | Finance / Akunting (Finance Manager) |

**Catatan penting:** Aturan Persetujuan saat ini mengizinkan approve Sales Order oleh Sales Manager, Finance Manager, Super Admin, Owner, dan Admin. **Peran Kasir harus dibuat dan ditambahkan** sebagai pemberi approve SO (sesuai alur Ko Handy: approve SO oleh kasir jika harga ≥ batas harga master produk dan tidak melewati limit kredit).

### 5.2 Matriks menu per peran

| Grup | Menu | OWN | SM | SLS | KSR | ADP | GDG | PUR | FIN |
|---|---|---|---|---|---|---|---|---|---|
| BERANDA | Tugas Saya | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| PENJUALAN | Penawaran Harga | ✓ | ✓ | ✓ |  |  |  |  |  |
| PENJUALAN | Pesanan Penjualan (SO) | ✓ | ✓ | ✓ | ✓ | ✓ |  |  |  |
| PENJUALAN | Perintah Kirim (DO) | ✓ |  | ✓ | ✓ | ✓ |  |  |  |
| PENJUALAN | Surat Jalan | ✓ |  |  |  | ✓ |  |  |  |
| PENJUALAN | Invoice Penjualan | ✓ |  |  | ✓ | ✓ |  |  | ✓ |
| PENJUALAN | Terima Pembayaran | ✓ |  |  | ✓ |  |  |  | ✓ |
| PENJUALAN | Retur | ✓ | ✓ | ✓ |  | ✓ | ✓ |  | ✓ |
| GUDANG | Siapkan & Kirim | ✓ |  |  |  |  | ✓ |  |  |
| GUDANG | Terima Barang | ✓ |  |  |  |  | ✓ | ✓ |  |
| GUDANG | Cek Stok | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| GUDANG | Pindah Stok | ✓ |  |  |  |  | ✓ |  |  |
| GUDANG | Stock Opname | ✓ |  |  |  |  | ✓ |  |  |
| GUDANG | Penyesuaian Stok | ✓ |  |  |  |  | ✓ |  | ✓ |
| PEMBELIAN | Permintaan Pembelian | ✓ |  | ✓ |  |  | ✓ | ✓ |  |
| PEMBELIAN | Pesanan Pembelian (PO) | ✓ |  |  |  |  |  | ✓ |  |
| PEMBELIAN | Invoice Pembelian | ✓ |  |  |  |  |  | ✓ | ✓ |
| PEMBELIAN | Bayar Supplier | ✓ |  |  |  |  |  |  | ✓ |
| PEMBELIAN | Retur Pembelian | ✓ |  |  |  |  | ✓ | ✓ |  |
| KEUANGAN | Kas & Bank | ✓ |  |  | ✓ |  |  |  | ✓ |
| KEUANGAN | Piutang & Hutang | ✓ |  |  | ✓ |  |  |  | ✓ |
| KEUANGAN | Pengajuan Dana | ✓ |  |  |  |  |  | ✓ | ✓ |
| KEUANGAN | Jurnal | ✓ |  |  |  |  |  |  | ✓ |
| KEUANGAN | Rekonsiliasi Bank | ✓ |  |  |  |  |  |  | ✓ |
| KEUANGAN | Aset Tetap | ✓ |  |  |  |  |  |  | ✓ |
| KEUANGAN | Tutup Buku | ✓ |  |  |  |  |  |  | ✓ |
| LAPORAN | Dashboard Owner | ✓ |  |  |  |  |  |  | ✓ |
| LAPORAN | Penjualan | ✓ | ✓ |  |  |  |  |  | ✓ |
| LAPORAN | Pembelian | ✓ |  |  |  |  |  | ✓ | ✓ |
| LAPORAN | Stok | ✓ |  |  |  |  | ✓ | ✓ | ✓ |
| LAPORAN | Laporan Keuangan | ✓ |  |  |  |  |  |  | ✓ |
| DATA MASTER | Produk | ✓ |  |  |  |  |  | ✓ |  |
| DATA MASTER | Customer | ✓ | ✓ | ✓ | ✓ |  |  |  | ✓ |
| DATA MASTER | Supplier | ✓ |  |  |  |  |  | ✓ | ✓ |
| DATA MASTER | Gudang & Rak | ✓ |  |  |  |  |  |  |  |
| DATA MASTER | Cabang | ✓ |  |  |  |  |  |  |  |
| DATA MASTER | Kendaraan & Driver | ✓ |  |  |  | ✓ |  |  |  |
| DATA MASTER | Akun & Pajak | ✓ |  |  |  |  |  |  | ✓ |
| PENGATURAN | Pengguna & Hak Akses | ✓ |  |  |  |  |  |  |  |
| PENGATURAN | Aturan Persetujuan | ✓ |  |  |  |  |  |  |  |
| PENGATURAN | Modul Aktif | ✓ |  |  |  |  |  |  |  |
| | **Jumlah menu** | **41** | **7** | **8** | **9** | **8** | **11** | **12** | **23** |

---

## 6. SPESIFIKASI HALAMAN KUNCI

### 6.1 Beranda "Tugas Saya" (route `/admin`, default untuk semua peran kecuali Owner)

- Header halaman: pill tanggal hari ini (format "SELASA, 13 OKTOBER 2026"), judul "Halo, {nama}", deskripsi "Ada {n} pekerjaan yang menunggu Anda hari ini."
- Kartu **"Perlu Anda kerjakan"**: daftar baris tugas, urut prioritas. Baris dengan jumlah 0 disembunyikan. Jika semua 0, tampil empty state "Tidak ada pekerjaan tertunda. Bagus!".
- Kartu **"Selesai hari ini"**: 3 angka ringkas (dashed box).
- Badge angka di menu "Tugas Saya" = total tugas.

| Peran | Baris tugas (query) | Tombol |
|---|---|---|
| Kasir | SO status *menunggu approve* dalam limit harga & kredit | Periksa & Approve |
| Kasir | SO melewati limit kredit / harga di bawah batas (label PERLU ATASAN) | Lihat |
| Kasir | SO COD menunggu verifikasi titipan | Verifikasi |
| Kasir | Surat Jalan diterima belum ada invoice | Buat Invoice |
| Sales | SO draft milik saya · Penawaran disetujui belum jadi SO · SO ditolak/perlu revisi | Lanjutkan |
| Admin Pengiriman | SO disetujui dengan sisa qty belum DO · DO terkirim belum SJ | Buat DO / Buat SJ |
| Gudang/Checker | DO menunggu konfirmasi gudang saya · DO disetujui siap dicek & dikirim · PO dijadwalkan tiba · Produk di bawah stok minimum | Kerjakan / Kirim / Terima Barang / Lihat |
| Purchasing | Permintaan Pembelian baru · PO belum diterima lewat jatuh tempo | Proses |
| Finance | Invoice pembelian jatuh tempo ≤ 7 hari · Pengajuan dana menunggu · Rekonsiliasi bulan berjalan | Proses |
| Owner | Dashboard Owner (widget keuangan saat ini) + persetujuan yang menunggu Owner | – |

### 6.2 Detail Sales Order

1. Header: pill status (bahasa manusia, lihat 7.2), judul nomor SO, sub-judul "Customer · Tipe kirim · Tanggal". Tombol kanan: Cetak SO, Lainnya ▾.
2. Kartu progres: stepper **SO dibuat → Diapprove kasir → DO (x dari y pcs) → Dikirim gudang → Surat Jalan → Invoice**.
3. Kotak **Langkah berikutnya** dengan 1 tombol primary sesuai status:
   - Draft → "Ajukan Approve"
   - Menunggu approve (peran Kasir) → "Approve" + "Tolak"
   - Disetujui, sisa qty > 0 → "+ Buat DO dari SO ini"
   - Semua DO terkirim, belum SJ → "Buat Surat Jalan"
   - SJ diterima, belum invoice → "Buat Invoice"
4. Aksi yang belum bisa: tampil **disabled + alasan** (contoh: "Belum bisa: menunggu DO-0007-01 dikirim oleh gudang KENARI 11 LT 4.").
5. Tabel barang: Produk (nama + kode + harga), Qty SO, Sudah DO, Sisa (hijau "0 ✓" / kuning), Subtotal. Footer: Ongkos kirim, Total.
6. Kartu **Dokumen terkait**: DO, SJ, Invoice, Retur yang terhubung, masing-masing dengan status. Empty state jika belum ada.

### 6.3 Form Buat Sales Order

| Bagian | Isi | Catatan |
|---|---|---|
| Otomatis (tidak tampil sebagai input) | Nomor SO, Cabang (dari user), Tanggal (hari ini), Mata uang IDR | Ditampilkan di keterangan "terisi otomatis saat disimpan". |
| 1. Customer | Customer (searchable), "Dibuat dari penawaran?" Tidak / Ya, pilih | Ganti teks "SO Mandiri (None) / Refer Quotation". |
| Info customer | Limit kredit · Terpakai · Sisa limit (3 kotak) | Tempo & alamat kirim otomatis dari master customer, bisa diubah. |
| 2. Barang | Per baris: produk + kode + chip stok bebas; Qty; Harga satuan + "Min. Rp…" | Harga < batas → border merah + "Di bawah min. Rp…". Qty > stok → chip kuning "kurang n, sisa dikirim menyusul". |
| Ongkos kirim | Input nominal | **Field baru** (permintaan Ko Handy). |
| Lainnya (collapsible) | Diskon, pajak per barang, mata uang, catatan | Pajak default diambil dari setting customer. |
| Ringkasan (kanan) | Subtotal, Ongkos kirim, PPN, Total, alert batas harga & limit kredit | Tombol: "Simpan & Ajukan Approve" (primary), "Simpan sebagai draft". |

**Menunggu keputusan Ko Handy:** harga di bawah batas → (A) ditolak saat simpan seperti sekarang, atau (B) boleh disimpan dan approve naik ke atasan. Mockup memakai opsi B.

### 6.4 Buat Perintah Kirim (DO)

- Dibuka dari tombol di Detail SO. Header pill "DARI SO-xxxx · CUSTOMER".
- Per produk yang masih ada sisa: kartu berisi tabel **hanya gudang dengan stok bebas > 0**: Gudang · Stok bebas · Qty dikirim (input). Badge "Sisa SO n pcs". Indikator "Terisi x dari n pcs ✓".
- Produk yang sudah lengkap: kartu dashed "Sudah dibuat DO semua".
- Aside "Yang akan dibuat": pratinjau DO **dipisah otomatis per gudang** (DO-xxxx-01, -02, …). Keterangan "Stok langsung dikunci saat DO dibuat."
- Validasi: qty per gudang ≤ stok bebas gudang; total ≤ sisa SO.

### 6.5 Siapkan & Kirim – tampilan HP checker (lebar 390px)

- Header: "‹ Kiriman (n)" + badge peran. Pill nama gudang, judul nomor DO, nama customer.
- Daftar barang sebagai **checkbox besar** (26px) per baris: nama, kode, qty besar (24/800).
- Tombol "Semua sesuai – KIRIM" (min-h 56) **disabled sampai semua dicentang**, dengan keterangan.
- Tombol "Ada kurang / rusak" (warning) → membuka form Retur DO (qty diterima / kurang / reject).

### 6.6 Pesan error & notifikasi

| Situasi | Wajib |
|---|---|
| Validasi gagal | Pesan spesifik dalam Bahasa Indonesia, menyebut angka. Contoh: "Stok gudang KENARI hanya 2 pcs, Anda mengisi 5 pcs." |
| Error server | Pesan umum + kode: "Terjadi kesalahan sistem. Silakan hubungi admin (kode: SJ-500)." **APP_DEBUG=false** di produksi. Detail hanya di log. |
| Simpan gagal tanpa alasan | Dilarang. Setiap kegagalan wajib menampilkan notifikasi. |
| Sukses | Notifikasi "DO-0012 berhasil dibuat" + tombol lanjutan (Cetak, Kembali ke SO). Durasi ≥ 6 detik. |
| Aksi berisiko (posting, batal, hapus) | Modal konfirmasi dengan ringkasan: "Anda akan membuat 2 DO (KENARI 4 pcs, DUTA-K01.2 2 pcs). Lanjutkan?" |

### 6.7 Daftar (tabel) transaksi

- Tab filter cepat di atas tabel: **Semua · Perlu Tindakan Saya · Menunggu Approve · Siap DO · Selesai** (disesuaikan per dokumen).
- Kolom default maks 6: Nomor, Tanggal, Customer/Supplier, Total, Status, Tombol utama. Kolom lain via toggle kolom.
- Ganti tombol "Action" dengan **1 tombol utama sesuai status** + menu titik tiga untuk aksi lain.
- Urutan default: terbaru di atas. Pencarian: nomor dokumen, nama customer/supplier, nama produk.

---

### 6.8 Pola mobile (berlaku untuk semua layar HP)

| Elemen | Spesifikasi |
|---|---|
| Breakpoint | < 768px = tampilan HP. Sidebar disembunyikan, diganti **navigasi bawah**. |
| Navigasi bawah | Tinggi 70px, 5 item: **Beranda · Penjualan · [+] Buat · Stok · Menu**. Item aktif hijau `primary.600` 700, lainnya `text.muted`. Tombol tengah 48×48 radius 16 hijau, membuka sheet "Buat baru" (SO, Terima Pembayaran, Permintaan Pembelian, Pindah Stok — sesuai peran). "Menu" membuka seluruh menu sesuai peran (struktur 4.2). |
| Header HP | 56px: judul/tombol kembali "‹ …" di kiri, badge peran atau notifikasi di kanan. |
| Daftar | Tabel diganti **kartu**: nomor + status pill di baris atas, nama customer/supplier, total besar, 1 tombol aksi utama di kanan bawah. |
| Filter | Chip horizontal yang bisa di-scroll di bawah kolom cari. |
| Input angka | Tombol −/+ 44–56px di kiri-kanan input qty. |
| Tombol utama | Lebar penuh, min-h 54–56px, di bagian bawah layar. |
| Target sentuh | Minimal 44×44px untuk semua elemen yang bisa diklik. |
| Kamera | Tombol scan barcode di samping cari produk; tombol foto untuk barang rusak dan surat jalan supplier. |

### 6.9 Dashboard Owner (mockup A1 desktop, A2 HP)

Route `/admin` untuk peran Owner/Finance. Peran lain mendapat Beranda "Tugas Saya" (6.1).

| Blok | Isi & sumber data | Klik menuju |
|---|---|---|
| Filter | Periode: Hari ini / 7 hari / Bulan ini / Pilih tanggal. Cabang: Semua / per cabang. Berlaku untuk semua blok. | – |
| KPI 1 – Penjualan | Total invoice/SO disetujui periode ini, % vs periode sama minggu lalu, jumlah SO | Laporan Penjualan |
| KPI 2 – Kas & Bank | Saldo seluruh akun kas/bank saat ini, total masuk & keluar periode | Kas & Bank |
| KPI 3 – Piutang lewat jatuh tempo | Total piutang > jatuh tempo, jumlah customer. Kartu kuning jika > 0 | Piutang & Hutang (tab Umur, filter telat) |
| KPI 4 – Hutang jatuh tempo ≤ 7 hari | Total hutang jatuh tempo 7 hari ke depan, jumlah supplier, tanggal terdekat | Piutang & Hutang (tab Hutang) |
| Perlu keputusan Anda | SO melewati limit kredit, SO harga di bawah batas, pengajuan dana menunggu Owner. Tombol **Setujui** langsung (dengan modal konfirmasi) + Lihat | Dokumen terkait |
| Alur pesanan saat ini | Jumlah dokumen per tahap: Menunggu approve → Siap dibuatkan DO → Disiapkan gudang → Dalam pengiriman → Terkirim belum ditagih. Tahap dengan dokumen tertahan > 1 hari diberi warna kuning + jumlah telat | Daftar dokumen terfilter |
| Penjualan 7 hari | Grafik batang 1 seri (hijau muda, hari ini hijau tua), label nilai hanya pada nilai tertinggi & hari ini, total + rata-rata di judul | Laporan Penjualan |
| Produk terlaris | Top 5 produk per nilai penjualan periode, bar horizontal | Laporan Penjualan per produk |
| Stok perlu perhatian | Status ✕ Habis / ! Menipis (stok bebas < minimum) / ◷ Lambat (tidak terjual > 90 hari). Tombol "+ Buat Permintaan Pembelian dari stok menipis" (PR terisi otomatis) | Cek Stok terfilter |
| Pembelian | Permintaan baru, PO menunggu barang, barang tiba hari ini, PO terlambat | Daftar PO terfilter |

Aturan: angka besar disingkat (Rp24,3 jt; Rp1,24 M), tooltip menampilkan angka penuh. Data di-cache maks 5 menit. Status selalu teks + ikon, tidak hanya warna. Di HP, KPI menjadi grid 2×2, "Alur pesanan" menjadi kartu yang bisa di-scroll ke samping, dan "Perlu keputusan" hanya menampilkan 1 item teratas + "Lihat n lainnya".

### 6.10 Penjualan – Daftar SO & Approve kasir (mockup B1, B2, B3)

- **Daftar SO (B1/B2):** tab filter cepat dengan jumlah: **Perlu tindakan** (default) · Draft · Menunggu approve · Dalam proses · Selesai · Semua. "Perlu tindakan" = dokumen yang aksi berikutnya milik peran user yang login.
- Kolom: No. SO (+ tanggal & sales), Customer, Total, Status (pill bahasa manusia), Kirim (progress bar x/y pcs), Aksi (1 tombol utama + ⋯).
- Tombol utama per status: Menunggu approve → **Approve** · Draft → Lanjutkan · Disetujui sisa qty → **Buat DO** · Diterima customer → **Buat Invoice** · Ditolak → Revisi · Tertahan di gudang > 1 hari → Ingatkan gudang.
- **Approve kasir (B3):** halaman HP-first. Blok "Pengecekan otomatis" berisi 3 baris status (✓ hijau / ! kuning / ✕ merah):
  1. Harga vs batas minimum master produk (per barang).
  2. Limit kredit: sisa limit sekarang → sisa setelah SO.
  3. Ketersediaan stok bebas (sebagian = kuning, boleh lanjut).
- Jika ada ✕ (harga di bawah batas atau melewati limit kredit): tombol **Setujui disabled** + alasan; tersedia **Teruskan ke atasan** (primary) dan **Kembalikan ke sales**. Jika semua ✓/!: tombol Setujui aktif + Tolak (wajib isi alasan).

### 6.11 Pembelian – Detail PO & Terima Barang (mockup C1, C2)

- **Detail PO (C1):** pill status (contoh "SEBAGIAN DITERIMA · TERLAMBAT 2 HARI"), stepper **Permintaan → PO disetujui → Terima barang (x/y) → QC → Invoice → Dibayar**, kotak Langkah berikutnya.
- Tabel barang: Dipesan, Diterima, Reject (merah), Sisa (hijau 0 ✓ / kuning), Subtotal.
- Kartu Supplier menampilkan **rekening pembayaran** (bank, no. rekening, atas nama) dari master supplier.
- Dokumen terkait: PR asal, dokumen Terima Barang, Retur Pembelian. **Reject QC menghasilkan tepat 1 dokumen retur** dan tidak ikut ditagihkan/mengurangi hutang di luar retur tersebut.
- Tombol: "Kirim ke supplier (WA/PDF)", "Terima sisa n pcs", "+ Buat Invoice Pembelian" (hanya untuk qty diterima bagus).
- **Terima Barang (C2, HP-first):** pilih gudang tujuan; per barang yang masih ada sisa: input **Diterima bagus** dan **Rusak/reject** dengan tombol −/+; validasi bagus + reject ≤ sisa PO; foto barang rusak (opsional) dan foto surat jalan supplier.
- Sebelum simpan tampil ringkasan dampak: "stok {gudang} bertambah n pcs, x pcs reject otomatis dibuatkan 1 retur pembelian". QC menyatu di form ini (menu Kontrol Kualitas terpisah tidak diperlukan).

### 6.12 Inventory – Cek Stok & Stock Opname (mockup D1, D2, D3)

- **Definisi tampil:** **Stok bebas = stok fisik − stok dikunci DO.** Kalimat ini ditampilkan di bawah judul halaman Cek Stok.
- **Cek Stok desktop (D1):** cari besar + filter gudang, kategori, chip "Menipis & habis · n". Tabel: Produk, kolom per gudang (fisik), Dikunci DO, **Stok bebas** (tebal), Status (✓ Aman / ! Menipis / ✕ Habis, berdasarkan stok bebas vs minimum stok produk).
- Klik baris membuka panel kanan: Fisik / Dikunci / Bebas, 4–5 mutasi terakhir (dengan nomor dokumen dan +/−), tombol Pindah stok, Kartu stok, **+ Minta pembelian**.
- **Cek Stok HP (D2):** cari + tombol **scan barcode**. Hasil teratas dibuka: stok bebas sebagai angka besar, rincian per gudang dengan bar, tombol Pindah stok / Minta pembelian. Hasil lain sebagai kartu ringkas.
- **Stock Opname HP (D3):** satu barang per layar, progress "12 / 30 dihitung" + bar di atas, info rak. Input jumlah besar dengan −/+.
- Opname bersifat **blind count**: jumlah sistem disembunyikan saat menghitung; selisih ditampilkan setelah sesi selesai untuk direview sebelum diposting ke Penyesuaian Stok.
- Tombol: Lewati, **Simpan & berikutnya**; daftar "Sudah dihitung" dengan tombol ubah.

### 6.13 Indeks mockup

File di folder `mockups/` (HTML statis + PNG). Angka, nama produk dalam [kurung siku], dan nama orang adalah **contoh**, bukan data asli.

| Kode | Layar | Ukuran |
|---|---|---|
| A1 / A2 | Dashboard Owner desktop / HP | 1440 / 390 |
| B1 / B2 | Daftar Pesanan Penjualan desktop / HP | 1440 / 390 |
| B3 | Approve SO oleh kasir (HP) | 390 |
| C1 | Detail Pesanan Pembelian | 1440 |
| C2 | Terima Barang + QC (HP) | 390 |
| D1 / D2 | Cek Stok desktop / HP | 1440 / 390 |
| D3 | Stock Opname (HP) | 390 |
| E1 | Beranda Kasir – Tugas Saya | 1440 |
| E2 | Detail SO – progres & langkah berikutnya | 1440 |
| E3 | Buat SO – form ringkas | 1440 |
| E4 | Buat DO – qty per gudang | 1440 |
| E5 | Checker gudang (HP) | 390 |

---


## 7. GLOSARIUM & STATUS

### 7.1 Istilah

| Sekarang | Ganti menjadi |
|---|---|
| Quotations | Penawaran Harga |
| Sale Orders / Sales Order | Pesanan Penjualan (SO) |
| Perintah Pengiriman / Delivery Order | Perintah Kirim (DO) |
| Penerimaan Pelanggan | Terima Pembayaran |
| Pembayaran Vendor | Bayar Supplier |
| Return Product | Retur DO |
| Retur Pelanggan | Retur Penjualan |
| Stock Transfer | Pindah Stok |
| Stock Adjustment | Penyesuaian Stok |
| Inventory Stock | Cek Stok |
| Stock Movement | Riwayat Keluar-Masuk Stok |
| Konfirmasi Gudang | Siapkan & Kirim |
| Penerimaan Pembelian + Kontrol Kualitas | Terima Barang |
| Journal Entries | Jurnal |
| AR & AP Management / Ageing / Aging Report | Piutang & Hutang / Umur Piutang & Hutang |
| Action | Aksi |
| So number / Order date / Shipped To | No. SO / Tanggal / Alamat Kirim |
| SO Mandiri (None) / Refer Quotation | Buat baru / Dari penawaran |
| Harga Batas (%) (isi nominal) | Harga Minimum (Rp) |
| Dashboard Hub, Sales Hub, … | (dihapus) |

### 7.2 Status dokumen (label tampil)

Nama status sistem di bawah adalah perkiraan; sesuaikan dengan enum/kolom status yang sebenarnya di kode. Yang wajib adalah label tampilnya.

| Dokumen | Status sistem → Label tampil |
|---|---|
| SO | draft → Draft · pending/request_approve → Menunggu approve kasir · approved → Siap dibuatkan DO · partial → Sebagian sudah dibuat DO · completed → Selesai · rejected → Ditolak · canceled → Dibatalkan |
| DO | draft → Draft · request_approve → Menunggu konfirmasi gudang · approved → Siap dicek & dikirim · sent → Dalam pengiriman · received → Diterima customer · completed → Selesai · failed → Gagal kirim |
| Invoice | draft → Draft · posted → Terbit (terkunci) · partial → Dibayar sebagian · paid → Lunas |

Warna status: Draft = abu · Menunggu = kuning · Proses = biru · Selesai/Lunas = hijau · Ditolak/Gagal = merah.

---

## 8. PANDUAN IMPLEMENTASI FILAMENT v3

1. **Panel provider** (`AdminPanelProvider`):
   - `->font('Plus Jakarta Sans')`, `->colors([...])` sesuai 2.1.
   - `->sidebarCollapsibleOnDesktop(false)` dan jangan memakai `->sidebarFullyCollapsibleOnDesktop()`.
   - `->navigationGroups([...])` dengan urutan grup Bagian 4.1 (pakai `NavigationGroup::make('PENJUALAN')`, dst.).
   - `->viteTheme('resources/css/filament/admin/theme.css')` untuk token radius/border/spacing.
2. **Hapus Hub pages** dari navigasi (`protected static bool $shouldRegisterNavigation = false;`) lalu hapus setelah redirect dipasang.
3. Setiap Resource/Page: set `$navigationGroup`, `$navigationLabel`, `$navigationSort`, `$navigationIcon` (`heroicon-o-*` sesuai 4.2), `$modelLabel` / `$pluralModelLabel` dalam Bahasa Indonesia.
4. **Menu GABUNG** → buat **Cluster** (`php artisan make:filament-cluster LaporanKeuangan`) dan pindahkan Page/Resource terkait ke cluster tersebut sehingga tampil sebagai tab/sub-navigasi. Cluster yang dibutuhkan: Retur, Kas & Bank, Piutang & Hutang, Pengajuan Dana, Jurnal (toggle), Aset Tetap, Laporan Stok, Laporan Keuangan, Produk, Gudang & Rak, Akun & Pajak, Kendaraan & Driver, Pengguna & Hak Akses.
5. **Peran**: gunakan Policy (`viewAny`) + `canAccess()` sehingga menu dan URL sama-sama terkunci. Tambahkan peran `kasir`, `admin_pengiriman`, `gudang`, `purchasing`, `sales` bila belum ada.
6. **Badge**: `getNavigationBadge()` pada "Tugas Saya" dan menu yang punya antrian (contoh Siapkan & Kirim = jumlah DO menunggu gudang user).
7. **Beranda Tugas Saya**: custom `Dashboard` page dengan widget per peran (`canView()` per widget).
8. **Modul Aktif**: setting (tabel `settings` atau config) `modules.manufacturing`, `modules.delivery_schedule`, `modules.other_sales`. `shouldRegisterNavigation()` mengecek setting ini.
9. **Redirect** route lama → baru di `routes/web.php` (301).
10. **Teks**: pakai file `lang/id/*.php` untuk label & status agar konsisten.

---

## 9. TAHAPAN & KRITERIA SELESAI

| Tahap | Pekerjaan | Kriteria selesai |
|---|---|---|
| **1 (±1–2 minggu)** | Sidebar teks + tanpa Hub · Grup & label baru (4.2) · Filter menu per peran + peran Kasir di Aturan Persetujuan · Sembunyikan modul tidak dipakai · Bahasa Indonesia (7.1, 7.2) · Pesan error jelas + APP_DEBUG=false · Tema warna & font (Bagian 2) | Setiap peran login hanya melihat menunya (5.2). Tidak ada halaman Hub. Tidak ada teks Inggris di menu/tombol/status. Tidak ada stack trace. |
| **2 (±2–4 minggu)** | Beranda Tugas Saya (6.1) · Stepper + Langkah berikutnya + tombol disabled beralasan (6.2) · Gabung menu jadi Cluster (4.3) · Form SO & DO baru (6.3, 6.4) · Tab filter & tombol utama di tabel (6.7) · Dashboard Owner (6.9) · Daftar SO & Approve kasir (6.10) · Detail PO & Terima Barang + QC (6.11) · Cek Stok (6.12) · Navigasi bawah HP (6.8) | Staf baru bisa membuat SO → DO tanpa bantuan setelah 1x pelatihan 30 menit. Semua transaksi harian ≤ 2 klik dari Beranda. |
| **3 (lanjutan)** | Tampilan HP checker (6.5) · Stock Opname blind count di HP (6.12) · Scan barcode di Cek Stok · Picking list per gudang · Scan barcode · Video tutorial per peran · Mode latihan | Checker bisa mengirim DO dari HP tanpa membuka desktop. |

**Di luar cakupan dokumen ini:** bug fungsional dan fitur alur (Buat DO tidak tersimpan, SJ error 500, Transfer Stok error, Retur by DO DO.000xR/C, dll.) ada di "List Pengerjaan Developer" 13/10/2026.
