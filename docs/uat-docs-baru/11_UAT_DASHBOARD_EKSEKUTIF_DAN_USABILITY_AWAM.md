# MODUL UAT 11: DASHBOARD EKSEKUTIF & KERAMAHAN PENGGUNA AWAM (UI/UX)
## PENGUJIAN RINGKASAN BISNIS, NAVIGASI 2-KLIK & BEBAS ERROR TEKNIS
**Acuan Bisnis:** `UIUX_Spec_ERP_Duta_Tunggal.md` Bagian 1, 2, 6.9 & Mockup A1/A2, E1; `List_Pengerjaan_Developer.md` Bagian C (Poin C4).
**Peran Penguji Terautentikasi:**
- `Super Admin` (`superadmin@gmail.com`)
- `Owner` (`owner@example.com`)
- `Finance Manager` (`finance_manager@example.com`)
- `Kasir` (`kasir@example.com`)
- `Warehouse Staff` (`warehouse_staff@example.com`)

---

### Kasus Uji UAT-UI-01: Pemisahan Otomatis Dashboard Eksekutif vs Beranda Tugas Operasional
- **Peran Penguji Terautentikasi**: `Owner` vs `Kasir`
- **Tujuan Pengujian**: Memastikan pembagian tampilan dashboard:
  - Role Eksekutif (`OWN`, `FIN`, `admin`, `superadmin`): melihat Dashboard Analitik Bisnis Eksekutif (Mockup A1).
  - Role Operasional (`Kasir`, `Gudang`, `Sales`): melihat Beranda Tugas Saya harian (Mockup E1).
- **Kategori Skenario**: Role-Based Dashboard Segregation.
- **Testing yang Dilakukan**:
  1. Login sebagai `owner@example.com` $\rightarrow$ buka URL `/dashboard` (atau klik menu Dashboard).
  2. Amati tampilan yang disajikan.
  3. Logout, kemudian login sebagai `kasir@example.com` $\rightarrow$ buka URL `/dashboard` (atau klik menu Beranda).
  4. Amati tampilan yang disajikan.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*:
  - Pada Owner: Tampil Dashboard Ringkasan Bisnis dengan 4 KPI Cards (Penjualan, Kas & Bank, Piutang, Hutang Tempo), Pipeline Alur Pesanan, Grafik Bar 7 Hari, dan Seksi *"Perlu Keputusan Anda"*.
  - Pada Kasir: Tampil Beranda Tugas Saya dengan judul *"Halo, Kasir"* disertai kartu tugas harian (SO perlu disetujui, kasir ritel, dll.). Tidak dibebani angka finansial perusahaan.
- **Solusi**:
  Router kondisional di [`DashboardController.php`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Http/Controllers/DashboardController.php) memisahkan tampilan secara elegan.
- **Bukti Pengujian Nyata**:
  - Blade Router: [`resources/views/dashboard.blade.php`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/resources/views/dashboard.blade.php).

---

### Kasus Uji UAT-UI-02: Verifikasi 4 KPI Cards & Pipeline Alur Pesanan 5 Tahap
- **Peran Penguji Terautentikasi**: `Super Admin` (`superadmin@gmail.com`) / `Owner` (`owner@example.com`)
- **Tujuan Pengujian**: Memverifikasi akurasi 4 kartu metrik utama dan kelancaran 5 tahap alur pesanan di desktop maupun mobile.
- **Kategori Skenario**: Executive Metric & Pipeline Accuracy.
- **Testing yang Dilakukan**:
  1. Login sebagai `owner@example.com`.
  2. Periksa 4 KPI Cards:
     - Penjualan: Nominal Rupiah, % perbandingan dengan periode lalu, jumlah SO.
     - Kas & Bank: Total saldo kas/bank, rincian uang masuk & uang keluar.
     - Piutang: Total piutang, kartu highlight kuning/amber jika ada piutang jatuh tempo.
     - Hutang: Total hutang supplier jatuh tempo $\le 7$ hari, nama supplier & tanggal terdekat.
  3. Periksa Pipeline Alur Pesanan:
     - 5 Tahap: Belum Konfirmasi $\rightarrow$ Siap Kirim $\rightarrow$ Sedang Dikirim $\rightarrow$ Siap Tagih $\rightarrow$ Belum Lunas.
     - Indikator peringatan jika ada pesanan tertahan $> 1$ hari.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*: Seluruh angka dan kartu tersaji rapi dalam 1 baris grid di desktop (1440px) dan scrollable mulus di mobile HP (390px). Angka diformat ringkas dengan helper `formatShortCurrency` (contoh: `Rp24,3 jt`).
- **Solusi**:
  Sesuai dengan Mockup A1 & A2.
- **Bukti Pengujian Nyata**:
  - Service Backend: [`ExecutiveDashboardService.php`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Services/Dashboard/ExecutiveDashboardService.php).

---

### Kasus Uji UAT-UI-03: Modal Cepat Persetujuan (Quick Approval) pada Seksi "Perlu Keputusan Anda"
- **Peran Penguji Terautentikasi**: `Owner` (`owner@example.com`)
- **Tujuan Pengujian**: Memastikan Owner/Direksi dapat memberikan keputusan langsung pada SO yang melanggar batas harga atau limit kredit tanpa harus berpindah halaman.
- **Kategori Skenario**: Executive Quick Action & Usability.
- **Testing yang Dilakukan**:
  1. Terdapat SO yang melanggar batas harga atau limit kredit pada seksi *"Perlu Keputusan Anda"*.
  2. Owner mengklik tombol **"Setujui Sekarang"** pada kartu peringatan tersebut.
  3. Muncul pop-up modal Alpine.js dengan rincian SO, customer, dan alasan penolakan.
  4. Masukkan alasan persetujuan: `"Disetujui untuk ekspansi cabang customer baru"` $\rightarrow$ Klik Simpan.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*: Modal beroperasi cepat tanpa reload halaman penuh. SO seketika disetujui, dan kartu peringatan menghilang dari daftar tugas keputusan.
- **Solusi**:
  Integrasi komponen modal Alpine.js pada view executive dashboard.
- **Bukti Pengujian Nyata**:
  - Template Blade: [`resources/views/dashboard/executive.blade.php`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/resources/views/dashboard/executive.blade.php).

---

### Kasus Uji UAT-UI-04 (No Happy Path): Penanganan Error Server Tanpa Menampilkan Stack Trace (Penyelesaian Bug C4)
- **Peran Penguji Terautentikasi**: Sembarang User Terautentikasi
- **Tujuan Pengujian**: Memastikan bug lama C4 (*Stack trace Laravel tampil ke user*) **TERSELESAIKAN PENUH**. Di lingkungan produksi (`APP_DEBUG=false`), pengguna awam tidak pernah melihat kode teknis atau stack trace SQL yang membingungkan.
- **Kategori Skenario**: **No Happy Path (Production Error Masking & Reference Code)**.
- **Testing yang Dilakukan**:
  1. Pastikan aplikasi berjalan dengan `APP_DEBUG=false` (mode produksi).
  2. Picu sebuah kondisi error server terkontrol (misalnya request URL dengan parameter ID yang sengaja dirusak tipe datanya).
  3. Amati halaman respon yang ditampilkan kepada pengguna.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*:
  - Tidak ada halaman debug Ignition / Laravel stack trace berwarna merah-hitam.
  - Tampil halaman error kustom berdesain bersih dengan pesan ramah pengguna berbahasa Indonesia:
    *"Terjadi kesalahan sistem. Tim teknis telah diberi tahu. (Kode Error: ERR-500-XXXX)"*.
  - Kode referensi acak tersebut tercatat secara otomatis di log server `storage/logs/laravel.log` bersama detail error teknis untuk kemudahan debugging developer.
- **Solusi**:
  Handler error terpusat di `bootstrap/app.php` dan `app/Support/ErrorCode.php`.
- **Bukti Pengujian Nyata**:
  - Halaman View: `resources/views/errors/500.blade.php` dan `livewire-error-handler.blade.php`.

---

### Kasus Uji UAT-UI-05: Tombol Aksi Nonaktif (Disabled) Disertai Alasan Jelas (Prinsip P5)
- **Peran Penguji Terautentikasi**: `Checker Gudang` (`checker@example.com`) / `Admin Pengiriman`
- **Tujuan Pengujian**: Memastikan prinsip desain P5: *"Tidak ada tombol hilang misterius. Aksi yang belum bisa dijalankan tampil nonaktif (disabled) disertai keterangan/alasan yang jelas bagi orang awam."*
- **Kategori Skenario**: Laypeople Usability & Action Visibility.
- **Testing yang Dilakukan**:
  1. Buka halaman detail DO yang berstatus belum disetujui atau belum memiliki Surat Jalan terbit.
  2. Amati tombol **"Kirim"**.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*: Tombol "Kirim" tidak hilang atau lenyap tanpa kabar. Tombol tetap berada di posisinya dengan status abu-abu (*disabled*), dan di bawahnya/tooltip tertera keterangan jelas:
  *"Tombol nonaktif: Menunggu Surat Jalan diterbitkan terlebih dahulu."* Pengguna awam langsung paham apa langkah selanjutnya yang harus dilakukan.
- **Solusi**:
  Penerapan prinsip UI/UX Section 1 (Prinsip P5).
- **Bukti Pengujian Nyata**:
  - Form UI: Atribut `disabled` dan helper text informatif di samping tombol aksi.
