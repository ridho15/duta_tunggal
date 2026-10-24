# PANDUAN PENGUJIAN UAT (NO HAPPY PATH) — ROLE 6: KASIR POS & CUSTOMER SERVICE
## SISTEM ERP DUTA TUNGGAL

- **Peran yang Diuji:** `Kasir` & `Customer Service`
- **Modul Terkait:** Terminal Kasir POS (`/kasir/pos`), Manajemen Sesi & Shift Kasir, Riwayat Transaksi POS (`/kasir/pos/history`), Kwitansi Pelanggan Walk-in (`/penjualan/receipts`), Customer Order Portal
- **Tujuan Pengujian:** Memverifikasi penegakan sesi kerja shift kasir (*cashier shift enforcement*), rekonsiliasi uang laci kasir (Z-Report), pencegahan pembayaran kurang (*underpayment*), penguncian stok ritel saat checkout, dan pembatasan hak akses Customer Service dari proses persetujuan manajerial.

---

## 1. MATRIKS HAK AKSES & PEMISAHAN TUGAS (SEGREGATION OF DUTIES)

| Fitur / Aksi | Role `Kasir` | Role `Customer Service` | Aturan Bisnis & Pengamanan |
| :--- | :---: | :---: | :--- |
| **Buka Shift & Masukkan Modal Awal** | ✔ | ❌ | Wajib sebelum transaksi pertama hari itu. |
| **Transaksi Kasir Ritel (POS)** | ✔ | ❌ | Mengurangi stok toko dan menerbitkan struk. |
| **Tutup Shift Kasir (Z-Report)** | ✔ | ❌ | Menghitung uang fisik laci vs saldo sistem. |
| **Input Data Customer & Alamat Kirim** | ✔ | ✔ | Master data pelanggan ritel & reguler. |
| **Input Draft Quotation / Pesanan CS** | ❌ | ✔ | Membantu customer membuat permintaan order. |
| **Approve Penawaran / Sales Order** | ❌ (DILARANG) | ❌ (DILARANG) | Otoritas penuh berada di Sales Manager. |
| **Hapus Riwayat Transaksi Penjualan POS** | ❌ (DILARANG) | ❌ (DILARANG) | Transaksi ritel yang terbit bersifat permanen. |

---

## 2. SKENARIO PENGUJIAN NEGATIF (NO HAPPY PATH)

### Kasus UAT-POS-NEG-01: Transaksi Kasir POS Tanpa Buka Shift (Modal Awal Belum Dimasukkan)
- **Tipe Pengujian:** Penegakan Sesi Kasir (*Cashier Session Enforcement*)
- **Prasyarat:** Login dengan akun role **Kasir**, status shift kasir saat ini adalah `Closed` (Belum Buka Shift).
- **Langkah-langkah Pengujian:**
  1. Buka halaman terminal kasir `/kasir/pos`.
  2. Scan barcode produk atau klik item produk ke keranjang.
  3. Coba klik tombol **Bayar / Selesaikan Transaksi**.
- **Hasil yang Diharapkan (Expected Result):**
  - Layar transaksi terkunci atau sistem memunculkan modal peringatan: *"Shift kasir belum dibuka. Anda wajib memasukkan uang modal awal laci kasir sebelum melakukan transaksi penjualan."*
  - Tombol checkout diblokir dan transaksi tidak dapat disimpan.
- **Verifikasi Codebase:** `PosSessionMiddleware` & `PosController::checkout`.

---

### Kasus UAT-POS-NEG-02: Pembayaran Tunai POS dengan Uang Diterima Kurang dari Total Belanja (Underpayment)
- **Tipe Pengujian:** Validasi Kelayakan Pembayaran Kasir (*Underpayment Prevention*)
- **Prasyarat:** Shift kasir aktif, keranjang belanja berisi item dengan total tagihan `Rp 250.000`.
- **Langkah-langkah Pengujian:**
  1. Pada modal pembayaran POS, pilih metode: `Tunai (Cash)`.
  2. Pada kolom **Uang Diterima (Cash Tendered)**, masukkan nominal: `Rp 200.000` (Kurang Rp 50.000).
  3. Klik tombol **Cetak Struk & Selesai**.
- **Hasil yang Diharapkan (Expected Result):**
  - Tombol submit diblokir dengan teks peringatan merah: *"Nominal uang diterima (Rp 200.000) kurang dari total tagihan (Rp 250.000). Kurang: Rp 50.000."*
  - Struk belanja tidak tercetak dan status transaksi tidak disimpan sebagai lunas.
- **Verifikasi Codebase:** POS checkout client-side validator & backend payment calculation.

---

### Kasus UAT-POS-NEG-03: Tutup Shift Kasir (Z-Report) dengan Kolom Uang Fisik Laci Dikosongkan
- **Tipe Pengujian:** Validasi Kelengkapan Rekonsiliasi Kas (*Cash Balancing Enforcement*)
- **Prasyarat:** Shift kasir sedang aktif dan telah terjadi minimal 1 transaksi penjualan tunai.
- **Langkah-langkah Pengujian:**
  1. Klik tombol **Tutup Shift (Z-Report)** pada header kasir POS.
  2. Pada modal penghitungan kas fisik laci, kosongkan kolom **Uang Tunai Dihitung** atau isi dengan spasi.
  3. Klik tombol **Konfirmasi Tutup Shift**.
- **Hasil yang Diharapkan (Expected Result):**
  - Form menolak konfirmasi tutup shift: *"Jumlah fisik uang tunai di laci kasir wajib dihitung dan diisi untuk pelaporan rekonsiliasi Z-Report."*
  - Shift kasir tetap berstatus aktif (*open*).
- **Verifikasi Codebase:** `PosShiftController::closeShift` validation rules.

---

### Kasus UAT-POS-NEG-04: Transaksi Kasir POS dengan Produk yang Stok Fisiknya Habis di Gudang Toko
- **Tipe Pengujian:** Perlindungan Persediaan Toko Ritel (*Zero-Stock Checkout Block*)
- **Prasyarat:** Produk Snack B di Gudang Toko Ritel memiliki saldo stok **0 PCS**.
- **Langkah-langkah Pengujian:**
  1. Pada antarmuka kasir POS, cari produk Snack B.
  2. Masukkan ke keranjang belanja (Qty: 2 PCS).
  3. Klik Bayar dengan uang pas.
  4. Submit transaksi.
- **Hasil yang Diharapkan (Expected Result):**
  - Produk yang stoknya habis bertanda badge merah "Habis" dan tidak dapat diklik.
  - Jika dipaksa via barcode scanner, sistem memunculkan pop-up error: *"Stok produk 'Snack B' di Gudang Toko tidak mencukupi (Tersedia: 0). Transaksi tidak dapat dilanjutkan."*
  - Stok gudang toko tidak menjadi minus (-2).
- **Verifikasi Codebase:** `PosController::validateStockAvailability()`.

---

### Kasus UAT-POS-NEG-05: Customer Service Mencoba Menyetujui (Approve) Penawaran Harga
- **Tipe Pengujian:** Otorisasi & Pemisahan Wewenang (*Role Boundary Enforcement*)
- **Prasyarat:** Login dengan akun role **Customer Service**, terdapat draft penawaran harga.
- **Langkah-langkah Pengujian:**
  1. Buka menu **Penjualan > Penawaran Harga** (`/penjualan/penawaran`).
  2. Buka salah satu penawaran draft.
  3. Coba cari tombol Approve.
  4. Kirim request POST manual: `POST /penjualan/penawaran/{id}/approve`.
- **Hasil yang Diharapkan (Expected Result):**
  - Tombol Approve tidak tersedia di antarmuka role Customer Service.
  - Server mengembalikan respon **HTTP 403 Forbidden**: *"Unauthorized action: Customer Service staff cannot approve quotations."*
- **Verifikasi Codebase:** `RoleSeeder.php` (privileged action exclusions) & `QuotationPolicy::approve()`.

---

### Kasus UAT-POS-NEG-06: Customer Service Mencoba Mengedit Sales Order yang Sudah Berstatus Approved
- **Tipe Pengujian:** Penguncian Dokumen yang Sudah Diproses
- **Prasyarat:** Sales Order `SO-2026-88` berstatus `Approved` dan sedang disiapkan gudang.
- **Langkah-langkah Pengujian:**
  1. Login sebagai Customer Service.
  2. Buka URL edit SO: `/penjualan/so/{id}/edit`.
  3. Ubah kuantitas produk atau kurangi total harga.
  4. Klik Simpan Perubahan.
- **Hasil yang Diharapkan (Expected Result):**
  - Halaman edit tidak dapat diakses (**HTTP 403 Forbidden**).
  - Data pesanan customer tidak dapat dimanipulasi setelah disetujui manajer.
- **Verifikasi Codebase:** `SaleOrderPolicy::update()`: hanya status `draft` dan `request_approve` yang boleh di-update.

---

### Kasus UAT-POS-NEG-07: Pembayaran Non-Tunai POS Tanpa Nomor Referensi Transfer / Trx ID
- **Tipe Pengujian:** Kepatuhan Jejak Rekonsiliasi Bank Non-Tunai (*Audit Trail Check*)
- **Prasyarat:** Total belanja keranjang kasir `Rp 500.000`.
- **Langkah-langkah Pengujian:**
  1. Pada modal pembayaran, pilih metode: `Transfer Bank / QRIS`.
  2. Kosongkan kolom **Nomor Referensi Transaksi / Approval Code**.
  3. Klik tombol Selesaikan Pembayaran.
- **Hasil yang Diharapkan (Expected Result):**
  - Form memunculkan validasi error: *"Nomor referensi / bukti transfer bank wajib diisi untuk transaksi pembayaran non-tunai."*
  - Transaksi tidak dapat disimpan tanpa nomor referensi audit.
- **Verifikasi Codebase:** `PosPaymentValidator` conditional rule on non-cash methods.

---

### Kasus UAT-POS-NEG-08: Kasir Membuka Dua Shift Bersamaan pada Mesin Kasir yang Sama
- **Tipe Pengujian:** Integritas Sesi Tunggal Kasir (*Single-Session Lock*)
- **Prasyarat:** Shift kasir A sudah berstatus Aktif (Open) di Kasir Toko 1.
- **Langkah-langkah Pengujian:**
  1. Buka tab browser baru dengan akun kasir lain atau akun yang sama.
  2. Coba kirim request Buka Shift baru untuk Kasir Toko 1 tanpa menutup shift sebelumnya.
- **Hasil yang Diharapkan (Expected Result):**
  - Sistem menolak pembukaan shift ganda: *"Mesin kasir ini telah memiliki shift aktif yang belum ditutup. Tutup shift sebelumnya terlebih dahulu sebelum membuka shift baru."*
- **Verifikasi Codebase:** `PosShiftController::openShift` unique active shift check.

---

## 3. LEMBAR HASIL PENGUJIAN ROLE 6

| Kode Uji | Skenario | Hasil Aktual Pengujian | Status (PASS/FAIL) | Catatan / Bug Log |
| :--- | :--- | :--- | :---: | :--- |
| UAT-POS-NEG-01 | Transaksi POS tanpa buka shift (modal awal kosong) | | | |
| UAT-POS-NEG-02 | Pembayaran tunai POS kurang bayar (underpayment) | | | |
| UAT-POS-NEG-03 | Tutup shift kasir tanpa input kas fisik laci | | | |
| UAT-POS-NEG-04 | Checkout produk stok 0 di gudang toko ritel | | | |
| UAT-POS-NEG-05 | CS mencoba approve penawaran harga | | | |
| UAT-POS-NEG-06 | CS edit Sales Order yang sudah Approved | | | |
| UAT-POS-NEG-07 | Bayar non-tunai tanpa nomor referensi transfer | | | |
| UAT-POS-NEG-08 | Buka shift ganda pada kasir yang sama | | | |

- **Tanggal Pengujian:** ____________________
- **Nama Penguji (Tester):** ____________________
- **Tanda Tangan:** ____________________
