# PANDUAN PENGUJIAN UAT (NO HAPPY PATH) — ROLE 1: SALES & SALES MANAGER
## SISTEM ERP DUTA TUNGGAL

- **Peran yang Diuji:** `Sales` (Staf Penjualan) & `Sales Manager` (Kepala Penjualan)
- **Modul Terkait:** Master Customer (`/master/customer`), Penawaran Harga / Quotation (`/penjualan/penawaran`), Pesanan Penjualan / Sales Order (`/penjualan/so`)
- **Tujuan Pengujian:** Memverifikasi ketahanan sistem penjualan terhadap kesalahan input kuantitas/harga, pencegahan approval ilegal oleh staf, penegakan plafon kredit (*credit limit*), penguncian dokumen yang sudah disetujui, dan isolasi data antar cabang.

---

## 1. MATRIKS HAK AKSES & PEMISAHAN TUGAS (SEGREGATION OF DUTIES)

| Fitur / Aksi | Role `Sales` | Role `Sales Manager` | Aturan Bisnis & Pengamanan |
| :--- | :---: | :---: | :--- |
| **Lihat Daftar Customer & Quotation** | ✔ (Cabang sendiri) | ✔ (Cabang sendiri / Semua) | Isolasi data cabang via `canAccessCabang()`. |
| **Buat / Edit Draft Quotation** | ✔ | ✔ | Draft dapat diedit sebelum diajukan persetujuan. |
| **Approve / Reject Quotation** | ❌ (DILARANG) | ✔ | Sales staff tidak boleh menyetujui penawarannya sendiri. |
| **Buat Revisi Quotation (Versi Baru)** | ✔ | ✔ | Dokumen lama berstatus Approved tidak boleh ditimpa. |
| **Buat Sales Order (SO)** | ✔ | ✔ | Memeriksa ketersediaan limit kredit customer. |
| **Approve Sales Order (SO)** | ❌ (DILARANG) | ✔ | Approval SO berada di level manajerial. |
| **Hapus Sales Order (SO)** | ✔ (Draft saja) | ✔ (Draft saja) | SO yang sudah masuk alur DO/Invoice dilarang dihapus. |

---

## 2. SKENARIO PENGUJIAN NEGATIF (NO HAPPY PATH)

### Kasus UAT-SALES-NEG-01: Sales Staff Mencoba Menyetujui (Approve) Quotation Sendiri
- **Tipe Pengujian:** Otorisasi & Hak Akses (RBAC Violation)
- **Prasyarat:** Akun role `Sales`, terdapat Quotation draft `QO-UAT-001`.
- **Langkah-langkah Pengujian:**
  1. Login ke aplikasi menggunakan akun role **Sales**.
  2. Buka menu **Penjualan > Penawaran Harga** (`/penjualan/penawaran`).
  3. Buka detail penawaran `QO-UAT-001`.
  4. Periksa apakah tombol **Setujui / Approve** muncul di antarmuka.
  5. Coba lakukan bypass dengan menembak request HTTP POST langsung:
     `POST /penjualan/penawaran/{id}/approve` menggunakan inspect network / Postman / cURL.
- **Hasil yang Diharapkan (Expected Result):**
  - Tombol **Setujui / Approve** tidak boleh ditampilkan di antarmuka role `Sales`.
  - Jika request POST dikirim langsung, server wajib mengembalikan **HTTP 403 Forbidden** dengan pesan: *"Tindakan tidak diizinkan. Staf penjualan tidak memiliki kewenangan menyetujui penawaran harga."*
  - Status penawaran di database tetap `draft` atau `request_approve`.
- **Verifikasi Codebase:** `database/seeders/RoleSeeder.php` (privileged action exclusions) & `app/Policies/QuotationPolicy.php:111`.

---

### Kasus UAT-SALES-NEG-02: Pengajuan Quotation Tanpa Item Barang (Keranjang Kosong)
- **Tipe Pengujian:** Validasi Struktur Data Form
- **Prasyarat:** Akun role `Sales`, form Buat Penawaran terbuka.
- **Langkah-langkah Pengujian:**
  1. Buka form **Tambah Penawaran Baru** (`/penjualan/penawaran/create`).
  2. Pilih Customer valid (misal: `PT UAT Customer Jaya`).
  3. Isi tanggal penawaran dan masa berlaku.
  4. Jangan tambahkan item produk apapun, atau hapus seluruh baris produk sehingga tabel item berukuran `0`.
  5. Klik tombol **Simpan Penawaran**.
- **Hasil yang Diharapkan (Expected Result):**
  - Form tidak boleh terkirim ke database.
  - Muncul pesan validasi error berwarna merah: *"Minimal harus ada 1 item produk dalam penawaran harga."*
  - Tidak ada record baru yang terbentuk di tabel `quotations` maupun `quotation_items`.
- **Verifikasi Codebase:** `QuotationRequest` rule `'items' => 'required|array|min:1'`.

---

### Kasus UAT-SALES-NEG-03: Input Item Quotation dengan Qty 0 atau Negatif
- **Tipe Pengujian:** Validasi Batas Nilai (*Boundary Limits*)
- **Prasyarat:** Form pembuatan Quotation.
- **Langkah-langkah Pengujian:**
  1. Pada form Quotation, masukkan produk `BRG-UAT-01`.
  2. Pada kolom **Kuantitas (Qty)**, masukkan angka `0`.
  3. Tambahkan baris kedua dengan produk `BRG-UAT-02`, masukkan Qty `-5`.
  4. Klik tombol **Simpan Penawaran**.
- **Hasil yang Diharapkan (Expected Result):**
  - Form menolak input dengan pesan validasi: *"Kuantitas item harus bernilai lebih dari 0."*
  - Nilai subtotal dan grand total tidak boleh bernilai negatif atau nol untuk barang fisik.
- **Verifikasi Codebase:** Form Request Validator `'items.*.qty' => 'required|numeric|min:0.01'`.

---

### Kasus UAT-SALES-NEG-04: Diskon Item Melebihi 100% atau Diskon Nilai Negatif
- **Tipe Pengujian:** Validasi Kalkulasi Finansial
- **Prasyarat:** Form pembuatan Quotation.
- **Langkah-langkah Pengujian:**
  1. Masukkan produk dengan harga satuan `Rp 100.000` dan Qty `2`. Total kotor: `Rp 200.000`.
  2. Pada kolom Diskon Item (%), masukkan angka `150`.
  3. Pada kolom Diskon Global (Rp), masukkan angka `-25000`.
  4. Klik tombol **Simpan Penawaran**.
- **Hasil yang Diharapkan (Expected Result):**
  - Form menolak dengan alert: *"Diskon persentase tidak boleh melebihi 100% dan diskon nilai tidak boleh bernilai negatif."*
  - Grand total akhir tidak boleh menghasilkan nilai negatif (tidak boleh menjadi piutang minus).
- **Verifikasi Codebase:** `QuotationCalculationService` & Controller validation logic.

---

### Kasus UAT-SALES-NEG-05: Mengubah (Edit) Quotation yang Sudah Berstatus Approved
- **Tipe Pengujian:** Integritas State-Machine Dokumen
- **Prasyarat:** Quotation `QO-APPROVED-001` dengan status `Approved`.
- **Langkah-langkah Pengujian:**
  1. Cari Quotation yang sudah berstatus `Approved`.
  2. Buka URL edit secara paksa: `/penjualan/penawaran/{id}/edit`.
  3. Coba ubah harga produk atau nama customer.
  4. Klik Simpan Perubahan.
- **Hasil yang Diharapkan (Expected Result):**
  - Sistem menolak akses halaman edit dengan **HTTP 403 Forbidden** atau me-redirect kembali ke halaman detail dengan flash error: *"Dokumen penawaran yang telah disetujui terkunci dan tidak dapat diedit. Gunakan fitur 'Buat Revisi' untuk membuat versi penawaran baru."*
  - Data di database tidak berubah.
- **Verifikasi Codebase:** `QuotationPolicy::update()`: `$quotation->isEditable()` (Hanya status `draft` dan `rejected` yang editable).

---

### Kasus UAT-SALES-NEG-06: Pembuatan Sales Order dari Quotation yang Ditolak (Rejected)
- **Tipe Pengujian:** Validasi Alur Transisi Dokumen
- **Prasyarat:** Dokumen Quotation berstatus `Rejected`.
- **Langkah-langkah Pengujian:**
  1. Buka Quotation yang statusnya telah `Rejected` (`Ditolak`).
  2. Periksa apakah tombol **Buat Sales Order** tersedia.
  3. Coba akses langsung endpoint pembuatan SO: `/penjualan/so/create?quotation_id={id_rejected}`.
  4. Submit form pembuatan SO.
- **Hasil yang Diharapkan (Expected Result):**
  - Tombol **Buat Sales Order** tidak muncul pada penawaran berstatus Ditolak.
  - Jika dipaksa via URL/API, sistem membatalkan proses dengan pesan error: *"Pesanan penjualan hanya dapat dibuat dari penawaran harga yang telah disetujui (Approved)."*
- **Verifikasi Codebase:** `SaleOrderController::create` parameter validation.

---

### Kasus UAT-SALES-NEG-07: Bypass Limit Plafon Kredit Customer pada Sales Order
- **Tipe Pengujian:** Kontrol Risiko Kredit Piutang (*Credit Limit Risk Control*)
- **Prasyarat:** Customer `PT Mitra UAT` memiliki limit kredit `Rp 10.000.000` dengan piutang berjalan `Rp 8.000.000` (Sisa plafon: `Rp 2.000.000`).
- **Langkah-langkah Pengujian:**
  1. Login sebagai Sales.
  2. Buat Sales Order baru untuk Customer tersebut dengan syarat bayar `Tempo / Kredit 30 Hari`.
  3. Masukkan item pesanan dengan total nilai `Rp 15.000.000` (Melampaui sisa plafon sebesar Rp 13.000.000).
  4. Submit Sales Order.
- **Hasil yang Diharapkan (Expected Result):**
  - SO tidak boleh langsung terbit (*cannot auto-approve*).
  - Sistem mendeteksi overlimit dan otomatis mengunci SO dengan status **Menunggu Persetujuan Kredit / On Hold Overlimit**.
  - Muncul peringatan di layar: *"Total pesanan melebihi sisa limit kredit pelanggan (Sisa limit: Rp 2.000.000, Total SO: Rp 15.000.000). Dokumen membutuhkan persetujuan khusus Finance Manager."*
- **Verifikasi Codebase:** `App\Services\CreditLimitCheckService` & SO status handler.

---

### Kasus UAT-SALES-NEG-08: Penghapusan Sales Order yang Sudah Memiliki Delivery Order Aktif
- **Tipe Pengujian:** Perlindungan Integritas Relasi Data
- **Prasyarat:** Sales Order berstatus `In Progress` yang telah diterbitkan Delivery Order (DO).
- **Langkah-langkah Pengujian:**
  1. Login sebagai Sales Manager.
  2. Buka daftar Sales Order `/penjualan/so`.
  3. Buka SO yang sudah memiliki turunan Delivery Order aktif.
  4. Coba lakukan aksi Hapus (Delete) baik melalui modal konfirmasi UI maupun request `DELETE /penjualan/so/{id}`.
- **Hasil yang Diharapkan (Expected Result):**
  - Tombol Hapus tidak tersedia pada SO yang telah berproses.
  - Jika ditembak melalui request DELETE, sistem menolak dengan pesan error: *"Pesanan penjualan tidak dapat dihapus karena sudah memiliki dokumen turunan pengiriman (Delivery Order) atau faktur terkait."*
  - Data SO dan DO tetap utuh di database.
- **Verifikasi Codebase:** `SaleOrderPolicy::delete()`: `$saleOrder->status === 'draft'`.

---

## 3. LEMBAR HASIL PENGUJIAN ROLE 1

| Kode Uji | Skenario | Hasil Aktual Pengujian | Status (PASS/FAIL) | Catatan / Bug Log |
| :--- | :--- | :--- | :---: | :--- |
| UAT-SALES-NEG-01 | Sales staff approve Quotation sendiri | | | |
| UAT-SALES-NEG-02 | Quotation keranjang item kosong | | | |
| UAT-SALES-NEG-03 | Qty item 0 atau negatif | | | |
| UAT-SALES-NEG-04 | Diskon > 100% atau nilai negatif | | | |
| UAT-SALES-NEG-05 | Edit Quotation Approved / Expired | | | |
| UAT-SALES-NEG-06 | SO dari Quotation Rejected | | | |
| UAT-SALES-NEG-07 | Bypass limit plafon kredit SO | | | |
| UAT-SALES-NEG-08 | Hapus SO yang sudah memiliki DO | | | |

- **Tanggal Pengujian:** ____________________
- **Nama Penguji (Tester):** ____________________
- **Tanda Tangan:** ____________________
