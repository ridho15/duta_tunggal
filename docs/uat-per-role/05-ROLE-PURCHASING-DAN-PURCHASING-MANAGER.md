# PANDUAN PENGUJIAN UAT (NO HAPPY PATH) — ROLE 5: PURCHASING & PURCHASING MANAGER
## SISTEM ERP DUTA TUNGGAL

- **Peran yang Diuji:** `Purchasing` (Staf Pembelian) & `Purchasing Manager` (Manajer Pembelian)
- **Modul Terkait:** Master Supplier / Vendor (`/master/supplier`), Order Request / Pengajuan Pembelian (`/pembelian/order-request`), Purchase Order / Surat Pesanan (`/pembelian/po`), Retur Pembelian ke Supplier (`/pembelian/retur`)
- **Tujuan Pengujian:** Memverifikasi pemisahan wewenang pengadaan (*segregation of procurement duties*), validasi alur bertingkat dari Order Request ke PO, pencegahan biaya tambahan negatif, penguncian dokumen PO setelah terjadi penerimaan parsial, dan validasi limit retur barang ke vendor.

---

## 1. MATRIKS HAK AKSES & PEMISAHAN TUGAS (SEGREGATION OF DUTIES)

| Fitur / Aksi | Role `Purchasing` (Staf) | Role `Purchasing Manager` | Aturan Bisnis & Pengamanan |
| :--- | :---: | :---: | :--- |
| **Kelola Data Master Supplier** | ✔ (Lihat & Tambah) | ✔ (Penuh) | Supplier harus memiliki NPWP & status aktif. |
| **Buat / Edit Draft Order Request (OR)** | ✔ | ✔ | Permintaan pengadaan dari user/gudang. |
| **Setujui (Approve) Order Request (OR)** | ❌ (DILARANG) | ✔ | OR harus diapprove sebelum dibuatkan PO. |
| **Buat Draft Purchase Order (PO)** | ✔ | ✔ | Mengacu pada OR yang disetujui atau PO langsung. |
| **Setujui (Approve) Purchase Order (PO)** | ❌ (DILARANG) | ✔ | Staf dilarang approve PO buatannya sendiri. |
| **Edit PO yang Sudah Partially Received** | ❌ (DILARANG) | ❌ (DILARANG) | Kuantitas dan harga terkunci demi audit stok. |
| **Proses Retur Pembelian ke Supplier** | ✔ | ✔ | Kuantitas retur maksimal sebesar barang diterima. |

---

## 2. SKENARIO PENGUJIAN NEGATIF (NO HAPPY PATH)

### Kasus UAT-PURCH-NEG-01: Staf Purchasing Mencoba Menyetujui (Approve) PO Sendiri
- **Tipe Pengujian:** Otorisasi & Hak Akses Pengadaan (*Procurement Segregation of Duties*)
- **Prasyarat:** Login dengan akun role **Purchasing**, terdapat PO draft `PO-UAT-DRAFT-01`.
- **Langkah-langkah Pengujian:**
  1. Buka menu **Pembelian > Purchase Order** (`/pembelian/po`).
  2. Buka detail `PO-UAT-DRAFT-01`.
  3. Periksa apakah tombol **Setujui / Approve PO** muncul.
  4. Coba tembak request HTTP POST langsung:
     `POST /pembelian/po/{id}/approve` via browser devtools / cURL.
- **Hasil yang Diharapkan (Expected Result):**
  - Tombol Approve tidak ditampilkan untuk role `Purchasing`.
  - Jika ditembak via API, server mengembalikan **HTTP 403 Forbidden**: *"Tindakan tidak diizinkan. Staf Purchasing tidak memiliki wewenang menyetujui Purchase Order."*
  - Status PO tetap `draft` atau `submitted`.
- **Verifikasi Codebase:** `PurchaseOrderPolicy::approve()` & `RoleSeeder.php`.

---

### Kasus UAT-PURCH-NEG-02: Pembuatan PO dari Order Request (OR) yang Belum Disetujui
- **Tipe Pengujian:** Penegakan Prasyarat Alur Dokumen (*Pre-requisite Workflow Enforcement*)
- **Prasyarat:** Order Request `OR-PENDING-001` dengan status masih `Submitted` (Menunggu Approval).
- **Langkah-langkah Pengujian:**
  1. Buka menu Purchase Order `/pembelian/po/create`.
  2. Coba pilih referensi Order Request `OR-PENDING-001`.
  3. Jika tidak ada di dropdown, tembak parameter URL langsung:
     `/pembelian/po/create?order_request_id={id_pending}`.
  4. Submit form pembuatan PO.
- **Hasil yang Diharapkan (Expected Result):**
  - OR yang belum disetujui tidak ditampilkan di daftar pilihan.
  - Jika dipaksa via URL/API, sistem membatalkan proses dengan pesan error: *"Purchase Order hanya dapat diterbitkan dari Order Request yang telah disetujui oleh atasan berwenang."*
- **Verifikasi Codebase:** `PurchaseOrderController::create` parameter OR state check.

---

### Kasus UAT-PURCH-NEG-03: Input Biaya Tambahan (Landed Cost) Bernilai Negatif pada PO
- **Tipe Pengujian:** Validasi Batas Nilai Biaya Finansial (*Negative Cost Prevention*)
- **Prasyarat:** Form pembuatan PO terbuka.
- **Langkah-langkah Pengujian:**
  1. Pada form PO, buka bagian Biaya Tambahan (Biaya Ekspedisi / Asuransi Pengiriman).
  2. Masukkan jenis biaya: `Ongkos Kirim Darat`.
  3. Masukkan nominal biaya: `-250000` (angka negatif).
  4. Klik Simpan PO.
- **Hasil yang Diharapkan (Expected Result):**
  - Form validasi menolak dengan pesan error: *"Nominal biaya tambahan tidak boleh bernilai negatif."*
  - Total kalkulasi PO tidak boleh berkurang secara tidak sah.
- **Verifikasi Codebase:** `PurchaseOrderBiayaRequest` validation rule `'biaya.*.nominal' => 'required|numeric|min:0'`.

---

### Kasus UAT-PURCH-NEG-04: Mengedit Dokumen PO yang Telah Masuk Penerimaan Barang Parsial
- **Tipe Pengujian:** Penguncian Dokumen Transaksi Berjalan (*Transaction Document Locking*)
- **Prasyarat:** PO `PO-RCV-01` memesan 20 unit barang. Sebanyak 10 unit telah diterima fisik di gudang melalui Purchase Receipt (Status PO: `Partially Received`).
- **Langkah-langkah Pengujian:**
  1. Buka halaman detail `PO-RCV-01`.
  2. Coba akses halaman edit `/pembelian/po/{id}/edit`.
  3. Coba ubah harga satuan barang, atau kurangi kuantitas pesanan menjadi `5 unit` (di bawah 10 unit yang sudah diterima gudang).
  4. Klik Simpan Perubahan.
- **Hasil yang Diharapkan (Expected Result):**
  - Halaman edit diblokir atau form mengembalikan error: *"Purchase Order terkunci dan tidak dapat diubah karena sebagian barang telah diterima di gudang. Hubungi bagian gudang/akuntansi untuk penyesuaian khusus."*
  - Data pesanan tidak mengalami inkonsistensi dengan dokumen penerimaan gudang.
- **Verifikasi Codebase:** `PurchaseOrderPolicy::update()` & `DocumentLock::blocks($purchaseOrder, 'update')`.

---

### Kasus UAT-PURCH-NEG-05: Retur Pembelian Melebihi Kuantitas Barang yang Pernah Diterima
- **Tipe Pengujian:** Validasi Batas Maksimal Retur (*Return Ceiling Limit*)
- **Prasyarat:** Purchase Receipt untuk produk M tercatat sebanyak **15 unit**.
- **Langkah-langkah Pengujian:**
  1. Buka menu **Pembelian > Retur Pembelian** (`/pembelian/retur/create`).
  2. Pilih referensi PO/Penerimaan terkait.
  3. Pada kolom kuantitas retur produk M, masukkan angka `30 unit`.
  4. Klik Kirim Pengajuan Retur.
- **Hasil yang Diharapkan (Expected Result):**
  - Form menolak submit: *"Jumlah barang yang diretur (30) tidak boleh melebihi total barang yang telah diterima fisik dari supplier (15 unit)."*
  - Transaksi retur tidak diterbitkan dan saldo stok gudang tidak terpotong minus.
- **Verifikasi Codebase:** `PurchaseReturnService::validateReturnLimit()`.

---

### Kasus UAT-PURCH-NEG-06: Pembuatan PO kepada Supplier yang Berstatus Nonaktif (Inactive)
- **Tipe Pengujian:** Penegakan Validitas Master Data Supplier
- **Prasyarat:** Supplier `PT Supplier Macet` berstatus Nonaktif (karena blacklist atau tidak beroperasi).
- **Langkah-langkah Pengujian:**
  1. Buka form pembuatan PO baru (`/pembelian/po/create`).
  2. Buka dropdown pemilihan supplier.
  3. Cari nama `PT Supplier Macet`.
  4. Coba submit ID supplier nonaktif tersebut via API POST.
- **Hasil yang Diharapkan (Expected Result):**
  - Supplier nonaktif tidak muncul pada pilihan dropdown supplier.
  - Jika dikirim via request API, sistem membatalkan: *"Supplier yang dipilih berstatus tidak aktif dan tidak dapat menerima pesanan pembelian baru."*
- **Verifikasi Codebase:** Scope query active supplier di `PurchaseOrderController`.

---

### Kasus UAT-PURCH-NEG-07: PO Multi-Currency dengan Kurs Konversi Bernilai 0 atau Negatif
- **Tipe Pengujian:** Validasi Nilai Tukar Valuta Asing (*Currency Exchange Rate Validation*)
- **Prasyarat:** Form PO dengan mata uang asing (misal: USD atau SGD).
- **Langkah-langkah Pengujian:**
  1. Pada form PO, pilih mata uang selain Rupiah: `USD`.
  2. Pada kolom Kurs Konversi terhadap IDR, masukkan angka `0` atau `-15000`.
  3. Masukkan item produk dan harga dalam USD.
  4. Klik Simpan PO.
- **Hasil yang Diharapkan (Expected Result):**
  - Form menolak penyimpanan: *"Kurs nilai tukar mata uang harus bernilai positif lebih dari 0."*
  - Kalkulasi ekuivalen Rupiah tidak boleh bernilai nol atau negatif di Buku Besar.
- **Verifikasi Codebase:** `PurchaseOrderCurrencyRequest` validation `'rate' => 'required|numeric|gt:0'`.

---

### Kasus UAT-PURCH-NEG-08: Duplikasi Nomor Referensi Surat Penawaran Supplier pada PO Sama
- **Tipe Pengujian:** Pencegahan Double Entry Pesanan Vendor
- **Prasyarat:** Supplier B telah dibuatkan PO mengacu pada nomor penawaran supplier `QUO-SUPP-999`.
- **Langkah-langkah Pengujian:**
  1. Buat PO baru kepada Supplier B.
  2. Masukkan kembali nomor referensi penawaran supplier: `QUO-SUPP-999`.
  3. Klik Simpan PO.
- **Hasil yang Diharapkan (Expected Result):**
  - Sistem memberikan peringatan / konfirmasi duplikasi: *"Peringatan: Nomor referensi penawaran supplier 'QUO-SUPP-999' telah digunakan pada PO sebelumnya (PO-2026-055). Pastikan Anda tidak membuat pesanan ganda."*
- **Verifikasi Codebase:** `PurchaseOrderService::checkDuplicateSupplierRef()`.

---

## 3. LEMBAR HASIL PENGUJIAN ROLE 5

| Kode Uji | Skenario | Hasil Aktual Pengujian | Status (PASS/FAIL) | Catatan / Bug Log |
| :--- | :--- | :--- | :---: | :--- |
| UAT-PURCH-NEG-01 | Staf Purchasing approve PO sendiri | | | |
| UAT-PURCH-NEG-02 | Buat PO dari OR yang belum approved | | | |
| UAT-PURCH-NEG-03 | Biaya tambahan (landed cost) negatif | | | |
| UAT-PURCH-NEG-04 | Edit PO yang sudah partially received | | | |
| UAT-PURCH-NEG-05 | Retur beli melebihi kuantitas diterima | | | |
| UAT-PURCH-NEG-06 | Buat PO ke supplier nonaktif | | | |
| UAT-PURCH-NEG-07 | Kurs valuta asing 0 atau negatif | | | |
| UAT-PURCH-NEG-08 | Duplikasi nomor referensi supplier | | | |

- **Tanggal Pengujian:** ____________________
- **Nama Penguji (Tester):** ____________________
- **Tanda Tangan:** ____________________
