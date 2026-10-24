# PANDUAN PENGUJIAN UAT (NO HAPPY PATH) — ROLE 4: PPIC, PRODUKSI & MANUFAKTUR
## SISTEM ERP DUTA TUNGGAL

- **Peran yang Diuji:** `Inventory Manager` (PPIC / Perencana Produksi), `Production Staff`, & `Checker QC Produksi`
- **Modul Terkait:** Bill of Materials / BOM Resep (`/manufaktur/bom`), SPK Produksi / Manufacturing Order (`/manufaktur/spk`), Pengeluaran Bahan Baku / Material Issue (`/manufaktur/material_issue`), Hasil Produksi & QC Output (`/manufaktur/production`, `/manufaktur/qc`)
- **Tujuan Pengujian:** Memverifikasi integritas struktur resep BOM (anti-sirkular), kontrol pengeluaran bahan baku (*material issue control*), pencegahan hasil produksi liar di luar target SPK, penguncian BOM aktif dalam proses manufaktur, dan pelaporan cacat produksi (*scrap/wastage*).

---

## 1. MATRIKS HAK AKSES & PEMISAHAN TUGAS (SEGREGATION OF DUTIES)

| Fitur / Aksi | Role `Production Staff` | Role `Checker QC` | Role `Inventory Manager (PPIC)` | Aturan Bisnis & Pengamanan |
| :--- | :---: | :---: | :---: | :--- |
| **Kelola Master BOM Resep Formula** | ❌ | ❌ | ✔ | Menentukan standar konsumsi bahan baku. |
| **Buat & Rencanakan SPK Produksi** | ❌ | ❌ | ✔ | Mengacu pada target SO atau minimum stock. |
| **Ajukan Pengeluaran Bahan Baku (Issue)** | ✔ | ❌ | ✔ | Menarik bahan dari gudang bahan baku. |
| **Catat Output Hasil Kerja Fisik** | ✔ | ❌ | ✔ | Mencatat kuantitas hasil rakitan/produksi. |
| **Inspeksi QC Produk Jadi** | ❌ | ✔ | ✔ | Memisahkan produk lolos vs reject/rework. |
| **Selesaikan / Tutup SPK (Closing)** | ❌ | ❌ | ✔ | Menghitung efisiensi dan posting HPP. |
| **Hapus BOM yang Sedang Berjalan** | ❌ | ❌ | ❌ (DILARANG) | BOM terkunci selama ada SPK aktif. |

---

## 2. SKENARIO PENGUJIAN NEGATIF (NO HAPPY PATH)

### Kasus UAT-MFG-NEG-01: Pembuatan BOM Resep Sirkular (Produk Jadi Menjadi Bahan Baku Dirinya Sendiri)
- **Tipe Pengujian:** Integritas Struktur Formula (*Circular Reference Prevention*)
- **Prasyarat:** Form Buat BOM Baru dibuka (`/manufaktur/bom/create`).
- **Langkah-langkah Pengujian:**
  1. Pilih Produk Jadi (Finished Good): `Pintu Kayu Jati Tipe A` (`PROD-PINTU-01`).
  2. Pada tabel rincian bahan baku (components), pilih item yang sama: `Pintu Kayu Jati Tipe A` (`PROD-PINTU-01`).
  3. Masukkan kuantitas: `1 PCS`.
  4. Klik tombol **Simpan Formula BOM**.
- **Hasil yang Diharapkan (Expected Result):**
  - Form menolak penyimpanan: *"Formula BOM tidak valid: Produk jadi tidak boleh dimasukkan sebagai komponen bahan baku bagi dirinya sendiri (Referensi Sirkular)."*
  - Record BOM tidak tersimpan di database.
- **Verifikasi Codebase:** `BillOfMaterialService::validateCircularReference()`.

---

### Kasus UAT-MFG-NEG-02: Input Komponen BOM Ganda (Duplikasi Bahan Baku pada Baris Berbeda)
- **Tipe Pengujian:** Konsistensi Normalisasi Data BOM
- **Prasyarat:** Form pembuatan BOM.
- **Langkah-langkah Pengujian:**
  1. Pilih Produk Jadi: `Meja Belajar Kayu`.
  2. Pada baris bahan baku 1, masukkan `Paku 2 Inch`, Qty: 20 PCS.
  3. Tambahkan baris bahan baku 2, masukkan kembali `Paku 2 Inch`, Qty: 10 PCS (produk sama dimasukkan 2 kali).
  4. Klik Simpan BOM.
- **Hasil yang Diharapkan (Expected Result):**
  - Sistem memunculkan alert validasi: *"Produk bahan baku 'Paku 2 Inch' telah ditambahkan pada baris lain. Gabungkan kuantitas menjadi satu baris (30 PCS) untuk mencegah duplikasi data formula."*
- **Verifikasi Codebase:** `BillOfMaterialItemRequest` duplicate component validation rule.

---

### Kasus UAT-MFG-NEG-03: Pengeluaran Bahan Baku (Material Issue) pada SPK yang Sudah Selesai / Dibatalkan
- **Tipe Pengujian:** Penegakan Status Siklus Manufaktur (*State-Machine Violation*)
- **Prasyarat:** Terdapat Surat Perintah Kerja `SPK-CLOSED-001` dengan status telah `Completed` (Selesai).
- **Langkah-langkah Pengujian:**
  1. Login sebagai **Production Staff**.
  2. Buka menu **Manufaktur > Pengeluaran Bahan Baku** (`/manufaktur/material_issue/create`).
  3. Buka dropdown pilihan SPK Produksi.
  4. Cari dan pilih `SPK-CLOSED-001`.
  5. Jika tidak ada di dropdown, coba tembak request POST manual ke `/manufaktur/material_issue` dengan payload SPK tersebut.
- **Hasil yang Diharapkan (Expected Result):**
  - SPK yang berstatus Selesai atau Dibatalkan tidak muncul pada pilihan pengeluaran bahan.
  - Jika ditembak via API, request ditolak: *"Bahan baku tidak dapat dikeluarkan untuk SPK yang telah selesai atau dibatalkan."*
- **Verifikasi Codebase:** `MaterialIssueController::create` parameter validation.

---

### Kasus UAT-MFG-NEG-04: Pengeluaran Bahan Baku Melebihi Kuantitas Formula Standar Tanpa Alasan Scrap
- **Tipe Pengujian:** Pengendalian Pemborosan Bahan Baku (*Wastage Control*)
- **Prasyarat:** SPK `SPK-100` membutuhkan bahan baku Plat Besi sebanyak 10 lembar sesuai formula BOM.
- **Langkah-langkah Pengujian:**
  1. Buka form Pengeluaran Bahan Baku untuk `SPK-100`.
  2. Masukkan pengeluaran Plat Besi sebanyak `25 lembar` (Kelebihan 15 lembar dari standar BOM).
  3. Kosongkan kolom Keterangan / Alasan Tambahan Bahan Baku.
  4. Klik Konfirmasi Pengeluaran.
- **Hasil yang Diharapkan (Expected Result):**
  - Sistem mendeteksi over-issue dan meminta otorisasi: *"Kuantitas bahan baku yang dikeluarkan (25) melebihi standar formula BOM (10). Kolom 'Alasan Scrap / Kerusakan Bahan' wajib diisi dan memerlukan persetujuan Manajer Produksi."*
- **Verifikasi Codebase:** `MaterialIssueService::validateStandardVariance()`.

---

### Kasus UAT-MFG-NEG-05: Pengeluaran Bahan Baku saat Stok Gudang Bahan Baku Kosong / Kurang
- **Tipe Pengujian:** Perlindungan Persediaan Gudang Produksi (*Zero-Stock Issue Block*)
- **Prasyarat:** Bahan baku Lem Kayu di Gudang Bahan Baku hanya tersisa **2 Botol**. Kebutuhan SPK adalah 10 Botol.
- **Langkah-langkah Pengujian:**
  1. Pada form Material Issue, masukkan Lem Kayu sejumlah `10 Botol`.
  2. Pilih gudang asal: Gudang Bahan Baku.
  3. Klik Simpan Pengeluaran Bahan.
- **Hasil yang Diharapkan (Expected Result):**
  - Transaksi ditolak dengan pesan error: *"Stok bahan baku 'Lem Kayu' di gudang tidak mencukupi (Tersedia: 2, Diminta: 10). Harap lakukan pengadaan atau transfer bahan terlebih dahulu."*
  - Stok bahan baku tidak menjadi minus (-8).
- **Verifikasi Codebase:** `MaterialIssue::checkStockAvailability()`.

---

### Kasus UAT-MFG-NEG-06: Pencatatan Hasil Jadi Produksi Melebihi Batas Toleransi Target SPK
- **Tipe Pengujian:** Batasan Output Produksi (*Over-Production Ceiling*)
- **Prasyarat:** `SPK-202` menargetkan pembuatan 50 unit Kursi (Toleransi kelebihan produksi maksimal 5% = 52 unit).
- **Langkah-langkah Pengujian:**
  1. Buka menu **Manufaktur > Pencatatan Hasil Jadi** (`/manufaktur/production/create`).
  2. Pilih `SPK-202`.
  3. Masukkan kuantitas hasil produksi: `120 unit`.
  4. Klik Simpan Hasil Produksi.
- **Hasil yang Diharapkan (Expected Result):**
  - Sistem menolak pencatatan: *"Hasil produksi (120 unit) melebihi batas target SPK (50 unit) di luar toleransi yang diizinkan (Maksimal 52 unit). Harap terbitkan SPK baru untuk pesanan tambahan."*
- **Verifikasi Codebase:** `ProductionService::validateOutputThreshold()`.

---

### Kasus UAT-MFG-NEG-07: Penghapusan BOM Resep yang Sedang Dipakai oleh SPK Berjalan
- **Tipe Pengujian:** Perlindungan Integritas Relasi Proses Manufaktur
- **Prasyarat:** Terdapat `BOM-MEJA-01` yang sedang aktif digunakan oleh `SPK-ACTIVE-01` (status SPK: `In Progress`).
- **Langkah-langkah Pengujian:**
  1. Buka daftar BOM `/manufaktur/bom`.
  2. Cari `BOM-MEJA-01`.
  3. Klik tombol Hapus (Delete).
- **Hasil yang Diharapkan (Expected Result):**
  - Sistem memblokir aksi hapus dengan peringatan: *"BOM tidak dapat dihapus karena sedang digunakan dalam SPK produksi aktif (SPK-ACTIVE-01). Selesaikan atau batalkan SPK terkait sebelum menghapus atau mengubah formula."*
  - Formula BOM tetap utuh.
- **Verifikasi Codebase:** `BillOfMaterialPolicy::delete()` relational integrity check.

---

### Kasus UAT-MFG-NEG-08: Pembuatan SPK Produksi Tanpa Adanya Dokumen BOM yang Aktif
- **Tipe Pengujian:** Kelengkapan Prasyarat Produksi (*Prerequisite Check*)
- **Prasyarat:** Produk `Lemari Khusus Tipe B` belum pernah dibuatkan dokumen BOM, atau seluruh BOM produk tersebut berstatus Nonaktif.
- **Langkah-langkah Pengujian:**
  1. Buka form Buat SPK Produksi baru (`/manufaktur/spk/create`).
  2. Pilih target produk jadi: `Lemari Khusus Tipe B`.
  3. Periksa apakah sistem dapat melanjutkan ke proses penjadwalan produksi.
  4. Klik Simpan SPK.
- **Hasil yang Diharapkan (Expected Result):**
  - Sistem menolak dengan alert: *"Produk yang dipilih tidak memiliki Formula BOM aktif. Buat dan aktifkan Bill of Materials (BOM) terlebih dahulu sebelum menerbitkan SPK Produksi."*
- **Verifikasi Codebase:** `ManufacturingOrderService::validateActiveBomExists()`.

---

## 3. LEMBAR HASIL PENGUJIAN ROLE 4

| Kode Uji | Skenario | Hasil Aktual Pengujian | Status (PASS/FAIL) | Catatan / Bug Log |
| :--- | :--- | :--- | :---: | :--- |
| UAT-MFG-NEG-01 | Pembuatan BOM sirkular (produk jadi = bahan baku) | | | |
| UAT-MFG-NEG-02 | Input duplikasi komponen pada BOM | | | |
| UAT-MFG-NEG-03 | Pengeluaran bahan baku pada SPK selesai/batal | | | |
| UAT-MFG-NEG-04 | Pengeluaran bahan baku over-standard tanpa alasan | | | |
| UAT-MFG-NEG-05 | Pengeluaran bahan baku stok gudang kosong | | | |
| UAT-MFG-NEG-06 | Hasil jadi produksi melebihi batas toleransi SPK | | | |
| UAT-MFG-NEG-07 | Hapus BOM yang sedang dipakai SPK berjalan | | | |
| UAT-MFG-NEG-08 | Buat SPK tanpa adanya dokumen BOM aktif | | | |

- **Tanggal Pengujian:** ____________________
- **Nama Penguji (Tester):** ____________________
- **Tanda Tangan:** ____________________
