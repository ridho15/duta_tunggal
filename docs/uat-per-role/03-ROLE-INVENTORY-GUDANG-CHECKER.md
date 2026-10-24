# PANDUAN PENGUJIAN UAT (NO HAPPY PATH) — ROLE 3: INVENTORY MANAGER, GUDANG & CHECKER
## SISTEM ERP DUTA TUNGGAL

- **Peran yang Diuji:** `Inventory Manager`, `Admin Inventory`, `Warehouse Staff`, & `Checker`
- **Modul Terkait:** Penerimaan Barang / Purchase Receipt (`/gudang/penerimaan`), Quality Control / QC Inspeksi (`/manufaktur/qc`), Monitoring & Kartu Stok (`/gudang/stok`), Stock Opname (`/gudang/opname`), Transfer Stok Antar Gudang (`/gudang/transfer`)
- **Tujuan Pengujian:** Memverifikasi ketatnya alokasi barang masuk dari PO, pencegahan barang reject masuk ke stok aktif, pencegahan mutasi stok minus, penolakan transfer sirkular/invalid, integritas audit trail kartu stok (*append-only*), dan pemisahan stok yang dicadangkan (*reserved stock*).

---

## 1. MATRIKS HAK AKSES & PEMISAHAN TUGAS (SEGREGATION OF DUTIES)

| Fitur / Aksi | Role `Checker` | Role `Warehouse Staff` | Role `Inventory Manager` | Aturan Bisnis & Pengamanan |
| :--- | :---: | :---: | :---: | :--- |
| **Catat Penerimaan Barang Fisik (Receipt)** | ✔ | ✔ | ✔ | Mengacu pada PO aktif. |
| **Input Hasil QC Inspeksi (Passed / Reject)** | ✔ | ❌ | ✔ | Checker memegang otoritas kelayakan fisik. |
| **Alokasi Rak & Gudang Simpan** | ❌ | ✔ | ✔ | Barang lolos QC dialokasikan ke rak komersial. |
| **Pengajuan Transfer Stok Antar Gudang** | ❌ | ✔ | ✔ | Memeriksa stok bebas (*unreserved stock*). |
| **Setujui (Approve) Transfer Stok Antar Gudang** | ❌ | ❌ | ✔ | Otoritas mutasi antar cabang/gudang. |
| **Eksekusi & Posting Stock Opname** | ❌ | ❌ | ✔ | Penyesuaian saldo buku inventaris. |
| **Hapus Riwayat Mutasi Stok** | ❌ | ❌ | ❌ (DILARANG) | Kartu stok bersifat immutable (*append-only*). |

---

## 2. SKENARIO PENGUJIAN NEGATIF (NO HAPPY PATH)

### Kasus UAT-INV-NEG-01: Checker Menerima Barang Masuk Melebihi Kuantitas Sisa PO
- **Tipe Pengujian:** Validasi Batas Toleransi Barang Masuk (*Over-Receipt Prevention*)
- **Prasyarat:** Purchase Order `PO-UAT-200` memesan 50 unit bahan baku X (tanpa klausul over-delivery).
- **Langkah-langkah Pengujian:**
  1. Login sebagai **Checker**.
  2. Buka menu **Gudang > Penerimaan Barang** (`/gudang/penerimaan/create`).
  3. Pilih referensi nomor `PO-UAT-200`.
  4. Pada kolom kuantitas barang yang diterima, masukkan angka `80` unit.
  5. Klik tombol Simpan Penerimaan.
- **Hasil yang Diharapkan (Expected Result):**
  - Form menolak penerimaan dengan pesan error: *"Kuantitas barang yang diterima (80) melebihi kuantitas sisa pesanan pada PO (50). Hubungi bagian Purchasing jika terdapat kelebihan kirim dari vendor."*
  - Barang tidak tercatat masuk dan tidak bertambah di kartu stok.
- **Verifikasi Codebase:** `PurchaseReceiptItemValidator::validateOverReceipt()`.

---

### Kasus UAT-INV-NEG-02: Bypass Tahap QC: Memasukkan Barang Reject Langsung ke Rak Stok Komersial
- **Tipe Pengujian:** Penegakan Karantina Kualitas Barang (*Quality Isolation*)
- **Prasyarat:** Penerimaan barang 10 unit dengan hasil inspeksi QC: 6 unit Lolos (Passed), 4 unit Rusak/Cacat (Reject).
- **Langkah-langkah Pengujian:**
  1. Pada form konfirmasi alokasi rak penyimpanan gudang.
  2. Coba pilih 4 unit barang yang bertanda `Reject`.
  3. Masukkan ke rak penyimpanan utama: `Rak A-01 (Stok Siap Jual)`.
  4. Klik Konfirmasi Penempatan.
- **Hasil yang Diharapkan (Expected Result):**
  - Sistem menolak penempatan: *"Barang berstatus Reject tidak boleh dialokasikan ke rak stok komersial siap jual. Barang harus dialokasikan ke Area Karantina / Gudang Retur Supplier."*
  - Saldo stok komersial produk yang siap dijual hanya bertambah 6 unit (bukan 10 unit).
- **Verifikasi Codebase:** `QualityControlService` & `WarehouseConfirmationPolicy`.

---

### Kasus UAT-INV-NEG-03: Transfer Stok Antar Gudang Melebihi Stok Gudang Asal
- **Tipe Pengujian:** Pencegahan Stok Minus pada Mutasi Fisik (*Negative Balance Block*)
- **Prasyarat:** Gudang Utama memiliki saldo stok produk Z sebanyak **8 unit**.
- **Langkah-langkah Pengujian:**
  1. Buka menu **Gudang > Transfer Antar Gudang** (`/gudang/transfer/create`).
  2. Pilih Gudang Asal: `Gudang Utama`.
  3. Pilih Gudang Tujuan: `Gudang Cabang Barat`.
  4. Masukkan kuantitas transfer produk Z: `25` unit.
  5. Klik Ajukan Transfer Stok.
- **Hasil yang Diharapkan (Expected Result):**
  - Form validasi menolak dengan pesan error: *"Kuantitas yang ditransfer (25) melebihi stok yang tersedia di gudang asal (8 unit)."*
  - Dokumen transfer tidak diterbitkan dan saldo gudang asal tidak berkurang menjadi negatif (-17).
- **Verifikasi Codebase:** `StockTransferRequest` validation rules.

---

### Kasus UAT-INV-NEG-04: Transfer Stok Antar Gudang dengan Gudang Asal = Gudang Tujuan
- **Tipe Pengujian:** Integritas Logika Rute Pemindahan (*Circular Transfer Block*)
- **Prasyarat:** Form pembuatan Transfer Stok terbuka.
- **Langkah-langkah Pengujian:**
  1. Pada kolom **Gudang Asal**, pilih `Gudang Pusat Jakarta`.
  2. Pada kolom **Gudang Tujuan**, pilih kembali `Gudang Pusat Jakarta` (gudang yang sama).
  3. Masukkan produk dan kuantitas valid.
  4. Klik tombol Simpan & Kirim.
- **Hasil yang Diharapkan (Expected Result):**
  - Form memunculkan validasi: *"Gudang asal dan gudang tujuan transfer tidak boleh sama."*
  - Transaksi tidak diproses.
- **Verifikasi Codebase:** Rule `'to_warehouse_id' => 'required|different:from_warehouse_id'`.

---

### Kasus UAT-INV-NEG-05: Eksekusi Stock Opname dengan Input Fisik Negatif
- **Tipe Pengujian:** Validasi Logika Penghitungan Fisik Inventaris
- **Prasyarat:** Form Stock Opname terbuka (`/gudang/opname/create`).
- **Langkah-langkah Pengujian:**
  1. Pilih gudang yang akan di-opname.
  2. Pada baris produk tertentu, masukkan angka fisik dihitung: `-10`.
  3. Coba klik tombol Simpan & Post Selisih Opname.
- **Hasil yang Diharapkan (Expected Result):**
  - Form menolak submit: *"Jumlah fisik hasil opname tidak boleh bernilai negatif."*
  - Tidak ada jurnal penyesuaian selisih persediaan yang terbit dengan angka tidak masuk akal.
- **Verifikasi Codebase:** `StockOpnameRequest` validation `'items.*.physical_qty' => 'required|numeric|min:0'`.

---

### Kasus UAT-INV-NEG-06: Warehouse Staff Mencoba Menghapus Riwayat Mutasi Kartu Stok
- **Tipe Pengujian:** Kepatuhan Audit Trail & Immutability Ledger Stok
- **Prasyarat:** Terdapat transaksi perpindahan stok di tabel `stock_movements`.
- **Langkah-langkah Pengujian:**
  1. Login sebagai **Warehouse Staff** atau **Checker**.
  2. Buka menu **Gudang > Riwayat Mutasi Stok** (`/gudang/stok`).
  3. Periksa apakah terdapat tombol Hapus (Delete).
  4. Kirim request DELETE manual: `DELETE /stock-movements/{id}` via API.
- **Hasil yang Diharapkan (Expected Result):**
  - Tombol Hapus tidak ada di UI.
  - Server mengembalikan **HTTP 403 Forbidden**. Kartu stok bersifat *append-only* (hanya boleh ditambah melalui transaksi sah, tidak boleh dihapus demi integritas akuntansi persediaan).
- **Verifikasi Codebase:** `StockMovementPolicy::delete() === false`.

---

### Kasus UAT-INV-NEG-07: Penempatan Barang ke Rak yang Berstatus Nonaktif (Inactive Rak)
- **Tipe Pengujian:** Penegakan Status Master Data Rak
- **Prasyarat:** Rak `RAK-B-RUSAK` berstatus Nonaktif (Inactive) karena dalam renovasi.
- **Langkah-langkah Pengujian:**
  1. Buka alokasi rak penerimaan barang atau pemindahan rak internal.
  2. Coba pilih `RAK-B-RUSAK`.
  3. Klik Simpan Alokasi.
- **Hasil yang Diharapkan (Expected Result):**
  - Rak nonaktif tidak muncul pada dropdown pemilihan rak.
  - Jika dipaksa via ID, sistem menolak: *"Rak yang dipilih saat ini berstatus tidak aktif dan tidak dapat menerima penempatan barang."*
- **Verifikasi Codebase:** Query scope active rak di `WarehouseConfirmationController`.

---

### Kasus UAT-INV-NEG-08: Transfer Stok yang Sedang Dicadangkan (Reserved) untuk Delivery Order Berjalan
- **Tipe Pengujian:** Perlindungan Komitmen Pesanan Pelanggan (*Allocated Stock Protection*)
- **Prasyarat:** Total stok fisik produk W di gudang: **20 unit**. Sebanyak **15 unit** telah dicadangkan (*reserved*) untuk Delivery Order `DO-SALES-55` yang sedang disiapkan logistik. Stok bebas tersedia: **5 unit**.
- **Langkah-langkah Pengujian:**
  1. Buat Transfer Stok Antar Gudang untuk produk W.
  2. Masukkan kuantitas transfer: `10` unit (melebihi stok bebas 5 unit, meskipun stok fisik total 20).
  3. Klik Simpan & Setujui Transfer.
- **Hasil yang Diharapkan (Expected Result):**
  - Sistem menolak transfer: *"Stok bebas tidak mencukupi (Stok fisik: 20, Dicadangkan untuk DO: 15, Bebas: 5). Kuantitas transfer diminta: 10."*
  - Barang yang sudah dijanjikan untuk customer tidak terambil.
- **Verifikasi Codebase:** `InventoryStockService::getAvailableUnreservedStock()`.

---

## 3. LEMBAR HASIL PENGUJIAN ROLE 3

| Kode Uji | Skenario | Hasil Aktual Pengujian | Status (PASS/FAIL) | Catatan / Bug Log |
| :--- | :--- | :--- | :---: | :--- |
| UAT-INV-NEG-01 | Checker terima barang melebihi PO | | | |
| UAT-INV-NEG-02 | Bypass QC: Masukkan reject ke rak komersial | | | |
| UAT-INV-NEG-03 | Transfer stok melebihi stok gudang asal | | | |
| UAT-INV-NEG-04 | Transfer stok gudang asal = gudang tujuan | | | |
| UAT-INV-NEG-05 | Stock opname kuantitas fisik negatif | | | |
| UAT-INV-NEG-06 | Hapus riwayat kartu mutasi stok | | | |
| UAT-INV-NEG-07 | Alokasi barang ke rak nonaktif | | | |
| UAT-INV-NEG-08 | Transfer stok yang dicadangkan (reserved) DO | | | |

- **Tanggal Pengujian:** ____________________
- **Nama Penguji (Tester):** ____________________
- **Tanda Tangan:** ____________________
