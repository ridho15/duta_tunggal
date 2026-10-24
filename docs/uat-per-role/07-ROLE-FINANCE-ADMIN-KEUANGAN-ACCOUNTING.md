# PANDUAN PENGUJIAN UAT (NO HAPPY PATH) — ROLE 7: FINANCE, ADMIN KEUANGAN & ACCOUNTING
## SISTEM ERP DUTA TUNGGAL

- **Peran yang Diuji:** `Finance Manager`, `Admin Keuangan`, & `Accounting`
- **Modul Terkait:** Faktur Penjualan / AR (`/penjualan/invoices`, `/penjualan/receipts`), Faktur Pembelian / AP (`/pembelian/invoice`, `/pembelian/payment`), Kas & Bank (`/keuangan/cashbank`), Jurnal Umum & Buku Besar (`/keuangan/journal`, `/keuangan/journal/ledger`), Master Aset Tetap & Depresiasi (`/keuangan/asset`, `/keuangan/asset/depreciation`, `/keuangan/asset/disposal`)
- **Tujuan Pengujian:** Memverifikasi integritas pembukuan ganda (*double-entry balancing*), pencegahan kelebihan pembayaran (*overpayment*), perlindungan saldo kas/bank dari penarikan minus, idempotensi depresiasi bulanan aset, penguncian periode akuntansi tertutup (*closed period lock*), dan validasi mutasi internal.

---

## 1. MATRIKS HAK AKSES & PEMISAHAN TUGAS (SEGREGATION OF DUTIES)

| Fitur / Aksi | Role `Admin Keuangan` | Role `Accounting` | Role `Finance Manager` | Aturan Bisnis & Pengamanan |
| :--- | :---: | :---: | :---: | :--- |
| **Terbitkan Faktur Tagihan (AR/AP)** | ✔ | ✔ | ✔ | Mengacu pada DO terkirim atau PO diterima. |
| **Catat Pembayaran Vendor (Payment)** | ✔ | ❌ | ✔ | Wajib memeriksa saldo riil rekening kas/bank. |
| **Input Jurnal Penyesuaian Manual** | ❌ | ✔ | ✔ | Total Debit wajib sama persis dengan Kredit. |
| **Eksekusi Depresiasi Bulanan Massal** | ❌ | ✔ | ✔ | Dijalankan sekali per periode buku. |
| **Pelepasan / Disposal Aset Tetap** | ❌ | ❌ | ✔ | Menghitung laba/rugi pelepasan ke Buku Besar. |
| **Buku Besar & Laporan Keuangan** | ✔ (Lihat) | ✔ (Penuh) | ✔ (Penuh) | Analisis mutasi rekening dan neraca saldo. |
| **Hapus Jurnal Periode Tertutup** | ❌ (DILARANG) | ❌ (DILARANG) | ❌ (DILARANG) | Periode yang telah ditutup terkunci permanen. |

---

## 2. SKENARIO PENGUJIAN NEGATIF (NO HAPPY PATH)

### Kasus UAT-FIN-NEG-01: Pelunasan Piutang Pelanggan Melebihi Sisa Tagihan Faktur (Overpayment)
- **Tipe Pengujian:** Validasi Batas Maksimal Pelunasan Piutang (*Overpayment Prevention*)
- **Prasyarat:** Faktur Penjualan `INV-2026-050` memiliki sisa piutang sebesar **Rp 4.000.000**.
- **Langkah-langkah Pengujian:**
  1. Login sebagai **Admin Keuangan** atau **Finance Manager**.
  2. Buka menu **Penjualan > Penerimaan Piutang** (`/penjualan/receipts/create`).
  3. Pilih faktur `INV-2026-050`.
  4. Pada kolom Jumlah Bayar, masukkan angka: `Rp 7.000.000` (Kelebihan Rp 3.000.000 tanpa alokasi deposit).
  5. Klik tombol Simpan Penerimaan.
- **Hasil yang Diharapkan (Expected Result):**
  - Form menolak pembayaran dengan pesan error: *"Nominal pembayaran (Rp 7.000.000) melebihi sisa tagihan faktur (Rp 4.000.000). Gunakan fitur Deposit Pelanggan jika customer menyetorkan kelebihan dana."*
  - Saldo piutang tidak menjadi negatif (-Rp 3.000.000).
- **Verifikasi Codebase:** `CustomerReceiptService::validateOverpayment()`.

---

### Kasus UAT-FIN-NEG-02: Pembayaran Tagihan Vendor (AP) Menggunakan Akun Kas dengan Saldo Tidak Cukup
- **Tipe Pengujian:** Perlindungan Likuiditas Rekening Kas/Bank (*Insufficient Cash Balance Prevention*)
- **Prasyarat:** Faktur Vendor bernilai **Rp 35.000.000**. Rekening `Kas Kecil Kantor` memiliki saldo berjalan saat ini **Rp 5.000.000**.
- **Langkah-langkah Pengujian:**
  1. Buka menu **Pembelian > Pembayaran Tagihan Vendor** (`/pembelian/payment/create`).
  2. Pilih faktur vendor terkait.
  3. Pilih akun sumber dana: `Kas Kecil Kantor`.
  4. Masukkan jumlah bayar: `Rp 35.000.000`.
  5. Klik tombol Proses Pembayaran.
- **Hasil yang Diharapkan (Expected Result):**
  - Sistem membatalkan transaksi: *"Saldo akun 'Kas Kecil Kantor' tidak mencukupi untuk melakukan pembayaran ini (Saldo saat ini: Rp 5.000.000, Diminta: Rp 35.000.000). Pilih rekening bank lain yang memiliki saldo likuiditas mencukupi."*
  - Saldo kas tidak menjadi minus (-Rp 30.000.000).
- **Verifikasi Codebase:** `VendorPaymentService::validateAccountBalance()`.

---

### Kasus UAT-FIN-NEG-03: Posting Jurnal Penyesuaian Manual Tidak Seimbang (Debit != Kredit)
- **Tipe Pengujian:** Penegakan Integritas Pembukuan Berpasangan (*Double-Entry Balancing Rule*)
- **Prasyarat:** Form Jurnal Penyesuaian Manual dibuka (`/keuangan/journal/create`).
- **Langkah-langkah Pengujian:**
  1. Login sebagai **Accounting**.
  2. Masukkan nomor referensi dan tanggal jurnal.
  3. Baris 1: Pilih Akun `Beban Pemeliharaan Gedung`, isi kolom Debit: `Rp 2.500.000`, Kredit: `0`.
  4. Baris 2: Pilih Akun `Kas Operasional`, isi kolom Debit: `0`, Kredit: `Rp 2.000.000` (Selisih Rp 500.000).
  5. Klik tombol **Simpan & Posting Jurnal**.
- **Hasil yang Diharapkan (Expected Result):**
  - Form menolak posting dengan notifikasi merah: *"Jurnal tidak seimbang (Unbalanced)! Total Debit (Rp 2.500.000) harus sama persis dengan Total Kredit (Rp 2.000.000). Selisih: Rp 500.000."*
  - Transaksi tidak tersimpan di tabel `journal_entries` dan Neraca Saldo tetap seimbang 100%.
- **Verifikasi Codebase:** `JournalEntryService::validateBalanceIntegrity()`.

---

### Kasus UAT-FIN-NEG-04: Transfer Antar Rekening Kas/Bank dengan Akun Sumber = Akun Tujuan
- **Tipe Pengujian:** Validasi Logika Perpindahan Dana (*Self-Transfer Block*)
- **Prasyarat:** Form Transfer Kas & Bank dibuka (`/keuangan/cashbank/transfer/create`).
- **Langkah-langkah Pengujian:**
  1. Pada pilihan **Dari Rekening (Sumber)**: pilih `Bank Mandiri Giro`.
  2. Pada pilihan **Ke Rekening (Tujuan)**: pilih kembali `Bank Mandiri Giro`.
  3. Masukkan nominal transfer: `Rp 10.000.000`.
  4. Klik tombol Kirim Transfer Dana.
- **Hasil yang Diharapkan (Expected Result):**
  - Form memunculkan validasi error: *"Rekening sumber dana dan rekening tujuan transfer tidak boleh sama."*
  - Transaksi tidak diproses dan tidak ada biaya admin yang terpotong.
- **Verifikasi Codebase:** `CashBankTransferRequest` rule `'to_coa_id' => 'required|different:from_coa_id'`.

---

### Kasus UAT-FIN-NEG-05: Eksekusi Depresiasi Bulanan Aset Ganda pada Periode yang Sama
- **Tipe Pengujian:** Idempotensi Proses Finansial (*Idempotency Enforcement*)
- **Prasyarat:** Depresiasi massal aset untuk periode `Oktober 2026` telah sukses dieksekusi dan jurnal telah terposting.
- **Langkah-langkah Pengujian:**
  1. Buka menu **Keuangan > Pusat Depresiasi Bulanan** (`/keuangan/asset/depreciation`).
  2. Pada panel Batch Processing, pilih kembali Bulan: `Oktober`, Tahun: `2026`.
  3. Klik tombol **Proses Sekarang**.
  4. Konfirmasi modal eksekusi.
- **Hasil yang Diharapkan (Expected Result):**
  - Sistem mendeteksi bahwa periode tersebut telah diproses: *"Seluruh aset aktif telah disusutkan untuk periode Oktober 2026. Tidak ada entri depresiasi baru yang dibuat (Pencegahan beban ganda)."*
  - Nilai buku aset dan akun akumulasi penyusutan tidak terpotong dua kali.
- **Verifikasi Codebase:** `AssetDepreciationService::processMonthly()` idempotency check.

---

### Kasus UAT-FIN-NEG-06: Pelepasan Aset (Disposal) pada Aset yang Statusnya Sudah Dihapus / Dilepas
- **Tipe Pengujian:** Validasi Status Kepemilikan Aset Tetap
- **Prasyarat:** Aset `AST-KEND-01` telah berstatus `Disposed` (sudah dijual/scrap pada bulan lalu).
- **Langkah-langkah Pengujian:**
  1. Buka menu **Pelepasan & Disposal Aset** (`/keuangan/asset/disposal/create`).
  2. Buka dropdown pemilihan aset tetap.
  3. Cari aset `AST-KEND-01`.
  4. Jika tidak ada di dropdown, coba tembak request POST pembuatan disposal dengan parameter aset tersebut.
- **Hasil yang Diharapkan (Expected Result):**
  - Aset yang berstatus Disposed tidak muncul pada pilihan pelepasan.
  - Jika ditembak via API, request ditolak: *"Aset tidak dapat dilepas karena status aset saat ini bukan aktif (Status saat ini: Disposed)."*
- **Verifikasi Codebase:** `AssetDisposalPolicy::create` asset state validation.

---

### Kasus UAT-FIN-NEG-07: Penghapusan Voucher Jurnal pada Periode Buku yang Telah Ditutup (Closed Period)
- **Tipe Pengujian:** Kepatuhan Regulasi Akuntansi (*Accounting Period Lock Enforcement*)
- **Prasyarat:** Periode akuntansi `Bulan September 2026` telah dinyatakan Tutup Buku (*Closed Period*).
- **Langkah-langkah Pengujian:**
  1. Cari voucher jurnal umum dengan tanggal transaksi di bulan September 2026.
  2. Buka detail voucher jurnal tersebut.
  3. Coba klik tombol Hapus Jurnal Manual atau kirim request `DELETE /keuangan/journal/{reference}`.
- **Hasil yang Diharapkan (Expected Result):**
  - Tombol Hapus tidak tersedia.
  - Request DELETE ditolak (**HTTP 403 Forbidden**): *"Voucher jurnal berada pada periode akuntansi yang telah ditutup (Closed Period) dan terkunci permanen demi kepatuhan audit."*
- **Verifikasi Codebase:** `AccountingPeriodPolicy` & `JournalEntryPolicy::delete()`.

---

### Kasus UAT-FIN-NEG-08: Registrasi Aset Tetap Baru dengan Nilai Perolehan 0 atau Negatif
- **Tipe Pengujian:** Validasi Nilai Kapitalisasi Aset (*Asset Valuation Rule*)
- **Prasyarat:** Form Registrasi Aset Baru dibuka (`/keuangan/asset/create`).
- **Langkah-langkah Pengujian:**
  1. Masukkan nama aset: `Laptop Desain Kantor`.
  2. Pada kolom **Nilai Perolehan (Harga Beli)**, masukkan angka: `0` atau `-12000000`.
  3. Masukkan masa manfaat: `4 Tahun`.
  4. Klik Simpan Data Aset.
- **Hasil yang Diharapkan (Expected Result):**
  - Form menolak penyimpanan: *"Nilai perolehan aset tetap harus bernilai positif lebih dari 0."*
- **Verifikasi Codebase:** `AssetRequest` validation `'purchase_cost' => 'required|numeric|gt:0'`.

---

## 3. LEMBAR HASIL PENGUJIAN ROLE 7

| Kode Uji | Skenario | Hasil Aktual Pengujian | Status (PASS/FAIL) | Catatan / Bug Log |
| :--- | :--- | :--- | :---: | :--- |
| UAT-FIN-NEG-01 | Pelunasan piutang melebihi sisa tagihan (overpayment) | | | |
| UAT-FIN-NEG-02 | Pembayaran vendor saldo kas/bank tidak cukup | | | |
| UAT-FIN-NEG-03 | Posting jurnal manual tidak seimbang (Debit != Kredit) | | | |
| UAT-FIN-NEG-04 | Transfer kas/bank rekening asal = tujuan | | | |
| UAT-FIN-NEG-05 | Depresiasi bulanan massal ganda periode sama | | | |
| UAT-FIN-NEG-06 | Pelepasan aset (disposal) pada aset yang sudah dilepas | | | |
| UAT-FIN-NEG-07 | Hapus voucher jurnal periode akuntansi tertutup | | | |
| UAT-FIN-NEG-08 | Registrasi aset nilai perolehan 0 atau negatif | | | |

- **Tanggal Pengujian:** ____________________
- **Nama Penguji (Tester):** ____________________
- **Tanda Tangan:** ____________________
