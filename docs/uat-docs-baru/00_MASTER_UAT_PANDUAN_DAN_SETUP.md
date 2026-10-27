# MASTER PANDUAN PENGUJIAN UAT (USER ACCEPTANCE TESTING)
## SISTEM ERP DISTRIBUSI DUTA TUNGGAL
**Dokumen Standar QA & Verifikasi Lapangan (No Happy Path)**
**Versi:** 2.0 (Acuan: `docs/baru/Alur_Bisnis_Ko_Handy.md`, `List_Pengerjaan_Developer.md`, `UIUX_Spec_ERP_Duta_Tunggal.md`)
**Tanggal Terbit:** 26 Oktober 2026

---

### 1. Tujuan & Filosofi Pengujian QA

Dokumen ini disusun sebagai panduan pengujian penerimaan pengguna (*User Acceptance Testing* - UAT) untuk memastikan bahwa implementasi aplikasi ERP Duta Tunggal memenuhi seluruh kaidah bisnis yang ditetapkan oleh manajemen (Ko Handy) dan spesifikasi UI/UX untuk staf operasional yang awam teknologi.

#### Prinsip Kerja Pengujian:
1. **Fokus "No Happy Path" (70% Uji Negatif & Kondisi Ekstrem):**
   Pengujian tidak hanya membuktikan sistem berjalan saat data benar, melainkan memastikan sistem memiliki pertahanan kokoh (*robust defense*) saat menghadapi:
   - Manipulasi harga di bawah batas harga master produk (`harga_batas` / HPP).
   - Penjualan kredit melebihi batas limit kredit customer.
   - Penjualan COD tanpa ketersediaan saldo titip customer.
   - Perebutan stok fisik pada detik yang sama (*race condition*).
   - Pengiriman barang kurang atau rusak saat dicek oleh checker di gudang.
   - Penggabungan dokumen yang tidak sah (beda Sales Order atau beda cabang).
   - Manipulasi atau perubahan data pada dokumen finansial yang sudah terbit / diposting.
   - Pemotongan hutang ganda akibat barang reject pada inspeksi QC pembelian.
2. **Anti-Denial Autentikasi (Pengujian Terautentikasi Penuh):**
   Pengujian **DILARANG KERAS** dilakukan hanya dengan mengakses URL tanpa login lalu membanggakan respon penolakan (401/403/redirect login). Setiap skenario wajib dijalankan oleh aktor peran resmi yang terautentikasi dengan hak akses semestinya.
3. **Verifikasi Integritas 4 Lapis Data (*Four-Tier Verification*):**
   Setiap proses bisnis yang diuji wajib diverifikasi dampaknya secara konsisten pada:
   - **Lapis 1 (Dokumen):** Status transisi dokumen (Draft, Request Approve, Approved, Sent, Received, Completed, Cancelled).
   - **Lapis 2 (Inventori):** Mutasi kartu stok fisik (`inventory_stocks`), stok terkunci (`qty_reserved`), dan stok bebas (`qty_available - qty_reserved`).
   - **Lapis 3 (Buku Besar & Akuntansi):** Entri jurnal otomatis debit-kredit berpasangan dan seimbang (*balanced*) pada bagan akun (`chart_of_accounts`).
   - **Lapis 4 (Kartu Piutang / Hutang):** Buku pembantu piutang customer atau hutang vendor (`invoices`, `customer_receipts`, `vendor_payments`).
4. **Verifikasi Keramahan Pengguna Awam (*Usability for Laypeople*):**
   - Menguji apakah tampilan antarmuka bersih, rapi, dan mengikuti prinsip desain P1–P7.
   - Memastikan tidak ada tombol hilang misterius (tombol yang belum bisa diklik harus tampil nonaktif/*disabled* dengan alasan yang jelas).
   - Memastikan pesan kesalahan menggunakan Bahasa Indonesia lugas, tanpa istilah teknis SQL, kode exception Laravel, atau stack trace.

---

### 2. Matriks Akun Penguji Resmi Terautentikasi (Seeded Roles)

Seluruh pengujian dilakukan dengan kredensial uji berikut (password standar: `password`):

| Peran (Role) | Email Login | Cakupan Tanggung Jawab dalam UAT | Menu Utama yang Boleh Diakses |
|---|---|---|---|
| **Super Admin** | `superadmin@gmail.com` | Override darurat, audit trail, setup konfigurasi | Semua menu sistem |
| **Owner / Direksi** | `owner@example.com` | Approval tingkat akhir, dashboard eksekutif, override limit | Dashboard Eksekutif, Semua Modul, Aturan Persetujuan |
| **Kasir** | `kasir@example.com` | Approval SO $\le$ batas harga, verifikasi COD, kasir POS | Tugas Saya, SO, DO, Invoice, Terima Pembayaran, Cek Stok, POS |
| **Sales** | `sales@example.com` | Input penawaran, input SO, pantau status kiriman | Penawaran, Pesanan Penjualan (SO), Customer, Cek Stok |
| **Sales Manager** | `sales_manager@example.com` | Approval SO di bawah batas harga / limit kredit | Persetujuan Penjualan, Laporan Penjualan, Customer |
| **Checker Gudang** | `checker@example.com` | Cek fisik HP (`/admin/kiriman`), input kurang/rusak | Tugas Kiriman HP, Retur DO HP, Cek Stok HP, Opname HP |
| **Warehouse Staff** | `warehouse_staff@example.com` | Penyiapan barang, konfirmasi keluar/masuk barang | Daftar DO, Penerimaan Barang, Pindah Stok, Stock Opname |
| **Admin Pengiriman**| `admin_pengiriman@example.com` | Cetak Surat Jalan kolektif, kelola armada kirim | Perintah Kirim (DO), Surat Jalan (SJ), Armada Kendaraan |
| **Purchasing** | `purchasing@example.com` | Input PO, monitoring kedatangan barang supplier | Permintaan Pembelian, Pesanan Pembelian (PO), Supplier |
| **Purchasing Manager**| `purchasing_manager@example.com`| Approval PO nominal besar, negosiasi retur supplier | Persetujuan Pembelian, PO, Retur Pembelian, Supplier |
| **Finance Manager** | `finance_manager@example.com` | Approval faktur, pembayaran vendor, override kredit | Faktur, Pembayaran, Kas/Bank, Jurnal Umum, Laporan Keuangan |
| **Admin Keuangan** | `admin_keuangan@example.com` | Pencatatan pembayaran, pencocokan kas/bank | Terima Pembayaran, Bayar Supplier, Saldo Titip, Kas/Bank |

---

### 3. Penanda Data Uji Resmi (*Test Data Marker*)

Untuk mencegah bentrokan data dan mempermudah audit trail, seluruh transaksi uji menggunakan format penamaan standar:

- **Customer Uji:** `CUST-TEST-UAT-001` (Kredit), `CUST-TEST-UAT-002` (COD), `CUST-TEST-UAT-003` (Tunai).
- **Supplier Uji:** `SUPP-TEST-UAT-001` (Rekening BCA Lengkap), `SUPP-TEST-UAT-002` (Rekening Mandiri).
- **Produk Uji:** 
  - `PROD-TEST-A` (5/8 Inch, Harga Beli Rp 5.667, Harga Batas Rp 5.667, Harga Jual Rp 7.500, Stok Bebas: 100 pcs).
  - `PROD-TEST-B` (1/2 Inch, Harga Beli Rp 3.600, Harga Batas Rp 3.600, Harga Jual Rp 5.000, Stok Bebas: 50 pcs).
  - `PROD-TEST-C` (Stok Menipis / Kritis: 2 pcs).
- **Prefix Nomor Dokumen Uji:** `TEST-RL-HASSEE-<YYYYMMDD>-<SEQ>`

---

### 4. Struktur Modul Dokumen Pengujian UAT

Pengujian dipecah ke dalam 12 dokumen terpisah agar isi dokumen mendalam dan tidak terpotong oleh batasan berkas:

1. [`01_UAT_SALES_ORDER_DAN_APPROVE_KASIR.md`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/docs/uat-docs-baru/01_UAT_SALES_ORDER_DAN_APPROVE_KASIR.md)
2. [`02_UAT_DELIVERY_ORDER_MULTI_GUDANG_DAN_STOK_LOCK.md`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/docs/uat-docs-baru/02_UAT_DELIVERY_ORDER_MULTI_GUDANG_DAN_STOK_LOCK.md)
3. [`03_UAT_CHECKER_PENGIRIMAN_DAN_RETUR_BY_DO.md`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/docs/uat-docs-baru/03_UAT_CHECKER_PENGIRIMAN_DAN_RETUR_BY_DO.md)
4. [`04_UAT_SURAT_JALAN_KOLEKTIF_DAN_AUTO_RECEIVED.md`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/docs/uat-docs-baru/04_UAT_SURAT_JALAN_KOLEKTIF_DAN_AUTO_RECEIVED.md)
5. [`05_UAT_SALES_INVOICE_DAN_PIUTANG_TERKUNCI.md`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/docs/uat-docs-baru/05_UAT_SALES_INVOICE_DAN_PIUTANG_TERKUNCI.md)
6. [`06_UAT_PURCHASE_ORDER_DAN_APPROVAL_BERTINGKAT.md`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/docs/uat-docs-baru/06_UAT_PURCHASE_ORDER_DAN_APPROVAL_BERTINGKAT.md)
7. [`07_UAT_TERIMA_BARANG_QC_DAN_RETUR_BELI.md`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/docs/uat-docs-baru/07_UAT_TERIMA_BARANG_QC_DAN_RETUR_BELI.md)
8. [`08_UAT_PURCHASE_INVOICE_DAN_HUTANG_SUPPLIER.md`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/docs/uat-docs-baru/08_UAT_PURCHASE_INVOICE_DAN_HUTANG_SUPPLIER.md)
9. [`09_UAT_INVENTORY_STOK_BEBAS_DAN_OPNAME_HP.md`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/docs/uat-docs-baru/09_UAT_INVENTORY_STOK_BEBAS_DAN_OPNAME_HP.md)
10. [`10_UAT_KASIR_POS_KAS_BANK_DAN_JURNAL.md`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/docs/uat-docs-baru/10_UAT_KASIR_POS_KAS_BANK_DAN_JURNAL.md)
11. [`11_UAT_DASHBOARD_EKSEKUTIF_DAN_USABILITY_AWAM.md`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/docs/uat-docs-baru/11_UAT_DASHBOARD_EKSEKUTIF_DAN_USABILITY_AWAM.md)
12. [`LAPORAN_HASIL_TESTING_DAN_REKOMENDASI.md`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/docs/uat-docs-baru/LAPORAN_HASIL_TESTING_DAN_REKOMENDASI.md)

---

### 5. Format Standar Pelaporan Setiap Kasus Uji

Setiap kasus uji diuraikan dengan format ketat berikut:
```markdown
### Kasus Uji [KODE_UJI]: [Nama Skenario Pengujian]
- **Peran Penguji Terautentikasi**: [Nama Role dan Email Login Penguji]
- **Tujuan Pengujian**: [Sasaran bisnis & aturan yang divalidasi]
- **Kategori Skenario**: [No Happy Path / Boundary / Concurrent / Usability]
- **Testing yang Dilakukan**:
  1. Langkah demi langkah interaksi aktor pada sistem.
  2. Data masukan (payload) presisi.
  3. Aksi pemicu (klik tombol / submit formulir).
- **Permasalahan / Bug atau Ketidaksesuaian**:
  [Temuan objektif di lapangan jika ada bug/selisih; jika valid catat pesan proteksi yang muncul]
- **Solusi**:
  [Langkah perbaikan / rekomendasi tindakan definitif]
- **Bukti Pengujian Nyata**:
  - Respon Antarmuka (UI Notification / Modal): "..."
  - Respon Backend / HTTP Status: HTTP 422 / 200
  - Bukti Lapis Database / State Record: `table_name.column_name = value`
  - Bukti Jurnal Akuntansi / Kartu Stok: Debet/Kredit seimbang
```
