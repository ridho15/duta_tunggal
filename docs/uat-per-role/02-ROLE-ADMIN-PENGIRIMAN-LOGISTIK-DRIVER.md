# PANDUAN PENGUJIAN UAT (NO HAPPY PATH) — ROLE 2: ADMIN PENGIRIMAN, LOGISTIK & DRIVER
## SISTEM ERP DUTA TUNGGAL

- **Peran yang Diuji:** `Admin Pengiriman` & `Delivery Driver`
- **Modul Terkait:** Delivery Order / DO (`/penjualan/do`), Surat Jalan / SJ (`/penjualan/sj`), Jadwal Pengiriman & Armada (`/penjualan/schedules`), Master Kendaraan & Driver
- **Tujuan Pengujian:** Memverifikasi pencegahan over-delivery kuantitas pesanan, pencegahan stok minus di gudang pengiriman, penguncian integritas konsolidasi Surat Jalan multi-DO, kepatuhan bukti serah terima (POD), serta pencegahan penghapusan dokumen logistik aktif (Aturan P0-1).

---

## 1. MATRIKS HAK AKSES & PEMISAHAN TUGAS (SEGREGATION OF DUTIES)

| Fitur / Aksi | Role `Admin Pengiriman` | Role `Delivery Driver` | Aturan Bisnis & Pengamanan |
| :--- | :---: | :---: | :--- |
| **Buat Delivery Order (DO) dari SO** | ✔ | ❌ (DILARANG) | Harus mengacu pada Sales Order yang telah disetujui. |
| **Konfirmasi Fisik & Cetak DO** | ✔ | ❌ | Mengurangi stok tercadangkan dan memvalidasi ketersediaan fisik. |
| **Hapus Delivery Order (DO)** | ✔ (Draft saja) | ❌ | **P0-1:** DO yang sedang dikirim / selesai dilarang keras dihapus. |
| **Buat Surat Jalan (SJ) Konsolidasi** | ✔ | ❌ | Hanya boleh menggabungkan DO dari **Customer yang sama**. |
| **Tugaskan Armada & Driver** | ✔ | ❌ | Memeriksa kapasitas kendaraan dan masa aktif SIM/STNK. |
| **Konfirmasi Pengiriman Selesai (POD)** | ✔ | ✔ | Wajib mengunggah bukti fisik tanda terima / foto POD. |

---

## 2. SKENARIO PENGUJIAN NEGATIF (NO HAPPY PATH)

### Kasus UAT-LOG-NEG-01: Penerbitan DO Melebihi Kuantitas Sisa Pesanan SO
- **Tipe Pengujian:** Validasi Batas Kuantitas (*Over-Delivery Block*)
- **Prasyarat:** Sales Order `SO-UAT-100` dengan pesanan produk X sebanyak 10 unit. Sebelumnya telah dikirim 6 unit (Sisa kuantitas belum terkirim: **4 unit**).
- **Langkah-langkah Pengujian:**
  1. Login sebagai **Admin Pengiriman**.
  2. Buka menu **Penjualan > Delivery Order** (`/penjualan/do`).
  3. Klik **Buat DO Baru**, pilih referensi `SO-UAT-100`.
  4. Pada kolom kuantitas kirim produk X, ubah angka menjadi `8` unit.
  5. Klik tombol **Simpan & Terbitkan DO**.
- **Hasil yang Diharapkan (Expected Result):**
  - Sistem menolak input dengan pesan validasi error: *"Kuantitas pengiriman (8) tidak boleh melebihi sisa pesanan yang belum terkirim pada SO (4)."*
  - Dokumen DO tidak diterbitkan dan sisa pesanan SO tidak terpotong.
- **Verifikasi Codebase:** `DeliveryOrderService::validateRemainingQty()`.

---

### Kasus UAT-LOG-NEG-02: Penerbitan DO pada Gudang dengan Stok Fisik Tidak Cukup (Anti-Stok Minus)
- **Tipe Pengujian:** Perlindungan Saldo Persediaan (*Inventory Depletion Prevention*)
- **Prasyarat:** Produk Y memiliki stok fisik di Gudang Cabang A sebanyak **5 unit**. Sales Order meminta 20 unit.
- **Langkah-langkah Pengujian:**
  1. Buka form pembuatan DO.
  2. Pilih Gudang Asal: `Gudang Cabang A`.
  3. Masukkan kuantitas produk Y sebesar `15` unit.
  4. Klik tombol **Konfirmasi Pengeluaran Barang**.
- **Hasil yang Diharapkan (Expected Result):**
  - Sistem memeriksa stok real-time gudang dan memblokir transaksi: *"Stok fisik di Gudang Cabang A tidak mencukupi untuk memenuhi pengiriman ini (Tersedia: 5, Diminta: 15). Harap lakukan transfer stok antar gudang terlebih dahulu."*
  - Saldo stok di gudang tidak menjadi minus (-10).
- **Verifikasi Codebase:** `DeliveryOrder::validateStockAvailability()`.

---

### Kasus UAT-LOG-NEG-03: Penerbitan Surat Jalan dari DO yang Belum Disetujui (Draft DO)
- **Tipe Pengujian:** Urutan Alur Dokumen (*Document Precedence Rule*)
- **Prasyarat:** Terdapat Delivery Order `DO-DRAFT-001` dengan status masih `Draft` (belum dikonfirmasi bagian gudang).
- **Langkah-langkah Pengujian:**
  1. Buka menu **Penjualan > Surat Jalan** (`/penjualan/sj`).
  2. Klik **Buat Surat Jalan Baru** (`/penjualan/sj/create`).
  3. Buka dropdown pemilihan Delivery Order.
  4. Cari nomor `DO-DRAFT-001`.
  5. Jika tidak ada di dropdown, coba kirim request POST manual:
     `POST /penjualan/sj` dengan payload `{"delivery_order_ids": ["DO-DRAFT-001"]}`.
- **Hasil yang Diharapkan (Expected Result):**
  - DO berstatus Draft tidak boleh ditampilkan pada daftar pilihan Surat Jalan.
  - Jika request dikirim paksa via API, controller menolak dengan error: *"Surat Jalan hanya dapat diterbitkan dari Delivery Order yang telah disetujui dan siap dikirim."*
- **Verifikasi Codebase:** `Tahap4SuratJalanTest.php` & `SuratJalanPolicy`.

---

### Kasus UAT-LOG-NEG-04: Konsolidasi Surat Jalan dari Customer yang Berbeda
- **Tipe Pengujian:** Integritas Kepemilikan Dokumen Pelanggan
- **Prasyarat:** Terdapat `DO-A` milik `Customer PT Maju` dan `DO-B` milik `Customer CV Berkah`. Keduanya berstatus Approved.
- **Langkah-langkah Pengujian:**
  1. Buka form pembuatan Surat Jalan konsolidasi.
  2. Pilih `DO-A` (PT Maju).
  3. Coba pilih `DO-B` (CV Berkah) pada baris konsolidasi yang sama.
  4. Klik tombol **Terbitkan Surat Jalan Konsolidasi**.
- **Hasil yang Diharapkan (Expected Result):**
  - Dropdown DO kedua otomatis menyaring hanya DO milik `PT Maju`.
  - Jika dikirim secara paksa, validasi backend membatalkan proses: *"Surat Jalan konsolidasi harus berasal dari pelanggan (customer) yang sama."*
  - Tidak ada Surat Jalan gabungan yang tercetak dengan nama pelanggan ganda.
- **Verifikasi Codebase:** `SuratJalanService::validateConsolidationSameCustomer()`.

---

### Kasus UAT-LOG-NEG-05: Duplikasi Pemakaian DO yang Sudah Melekat di Surat Jalan Aktif
- **Tipe Pengujian:** Pencegahan Multi-Allocation Transaksi Logistik
- **Prasyarat:** `DO-101` telah diterbitkan Surat Jalan `SJ-2026-001` (status Surat Jalan: Aktif / Dalam Pengiriman).
- **Langkah-langkah Pengujian:**
  1. Buka form pembuatan Surat Jalan baru.
  2. Masukkan nomor `DO-101` yang sama.
  3. Klik Simpan.
- **Hasil yang Diharapkan (Expected Result):**
  - Sistem menolak pemilihan DO tersebut: *"Delivery Order DO-101 sudah terikat dengan Surat Jalan aktif SJ-2026-001 dan tidak dapat digunakan kembali."*
- **Verifikasi Codebase:** `SuratJalanPolicy` & `DeliveryOrder::isAttachedToActiveSJ()`.

---

### Kasus UAT-LOG-NEG-06: Penghapusan DO yang Sedang Dikirim / Selesai (Aturan P0-1)
- **Tipe Pengujian:** Pencegahan Korupsi Data & Kehilangan Jejak Audit
- **Prasyarat:** Delivery Order `DO-SHIPPED-001` berstatus `In Transit` (Sedang dalam perjalanan).
- **Langkah-langkah Pengujian:**
  1. Login sebagai Admin Pengiriman.
  2. Buka detail `DO-SHIPPED-001`.
  3. Periksa apakah tombol Hapus tersedia.
  4. Coba tembak request `DELETE /penjualan/do/{id}`.
- **Hasil yang Diharapkan (Expected Result):**
  - Tombol Hapus tidak tersedia di UI.
  - Request DELETE ditolak (**HTTP 403 Forbidden** / **HTTP 422 Unprocessable Entity**) dengan pesan audit: *"P0-1: Dokumen pengiriman yang sedang dikirim atau telah selesai tidak boleh dihapus untuk mencegah stok dan jurnal hilang tanpa jejak. Gunakan prosedur Pembatalan Pengiriman / Retur jika kiriman gagal."*
- **Verifikasi Codebase:** `app/Policies/DeliveryOrderPolicy.php:100` (Rule P0-1).

---

### Kasus UAT-LOG-NEG-07: Driver Mengonfirmasi Pengiriman Selesai Tanpa Nama Penerima & Bukti Foto POD
- **Tipe Pengujian:** Validasi Kelengkapan Proof of Delivery (POD)
- **Prasyarat:** Akun role `Delivery Driver`, Surat Jalan `SJ-ONGOING-001` status sedang dikirim.
- **Langkah-langkah Pengujian:**
  1. Login sebagai Driver di antarmuka logistik.
  2. Buka detail surat jalan `SJ-ONGOING-001`.
  3. Klik tombol **Konfirmasi Terkirim / Selesai**.
  4. Pada modal konfirmasi yang muncul, kosongkan kolom **Nama Penerima**, kolom **Waktu Terima**, dan jangan unggah file foto tanda terima fisik (POD).
  5. Klik tombol Simpan.
- **Hasil yang Diharapkan (Expected Result):**
  - Form menolak konfirmasi dengan validasi: *"Nama penerima dan foto bukti tanda terima (POD) wajib diisi untuk menyelesaikan pengiriman."*
  - Status Surat Jalan dan DO tetap `in_transit` (tidak berubah menjadi `delivered`).
- **Verifikasi Codebase:** `DeliveryScheduleController::completeDelivery()` validation rules.

---

### Kasus UAT-LOG-NEG-08: Penugasan Muatan Melebihi Batas Tonase Armada Kendaraan
- **Tipe Pengujian:** Validasi Kapasitas Beban Angkut (*Over-Capacity Load*)
- **Prasyarat:** Kendaraan Pick-Up `B 1234 ABC` memiliki batas muatan maksimal **1.000 KG**.
- **Langkah-langkah Pengujian:**
  1. Buat Jadwal Pengiriman baru (`/penjualan/schedules/create`).
  2. Pilih armada kendaraan `B 1234 ABC`.
  3. Gabungkan 3 Delivery Order yang total bobot fisiknya mencapai **2.500 KG**.
  4. Klik tombol **Jadwalkan Pengiriman**.
- **Hasil yang Diharapkan (Expected Result):**
  - Sistem memunculkan alert peringatan kapasitas: *"Total bobot barang (2.500 KG) melampaui kapasitas maksimal kendaraan (1.000 KG). Harap bagi pengiriman menjadi beberapa trip atau gunakan armada yang lebih besar."*
- **Verifikasi Codebase:** `DeliveryScheduleService::checkVehicleCapacity()`.

---

## 3. LEMBAR HASIL PENGUJIAN ROLE 2

| Kode Uji | Skenario | Hasil Aktual Pengujian | Status (PASS/FAIL) | Catatan / Bug Log |
| :--- | :--- | :--- | :---: | :--- |
| UAT-LOG-NEG-01 | Penerbitan DO melebihi sisa pesanan SO | | | |
| UAT-LOG-NEG-02 | Penerbitan DO stok gudang tidak cukup | | | |
| UAT-LOG-NEG-03 | Surat Jalan dari DO Draft / Unapproved | | | |
| UAT-LOG-NEG-04 | Konsolidasi Surat Jalan beda customer | | | |
| UAT-LOG-NEG-05 | Duplikasi pemakaian DO di Surat Jalan aktif | | | |
| UAT-LOG-NEG-06 | Hapus DO in-transit / selesai (Rule P0-1) | | | |
| UAT-LOG-NEG-07 | Driver konfirmasi POD tanpa foto / penerima | | | |
| UAT-LOG-NEG-08 | Penugasan melebihi kapasitas beban armada | | | |

- **Tanggal Pengujian:** ____________________
- **Nama Penguji (Tester):** ____________________
- **Tanda Tangan:** ____________________
