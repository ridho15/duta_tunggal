# MASTER DOKUMEN UAT END-TO-END PER ROLE (NEGATIVE TESTING & NO HAPPY PATH)
## SISTEM ERP DUTA TUNGGAL

- **Versi Dokumen:** 2.0 (Verified Against Production-Ready Codebase)
- **Tanggal:** 24 Oktober 2026
- **Karakter Pengujian:** **100% NO HAPPY PATH** (Uji Validasi Form, Pelanggaran State Machine, Akses Terlarang/RBAC 403, Stock Insufficiency, Overpayment, Jurnal Unbalanced, Constraint Database & Data Corruption Prevention).
- **Target Lingkungan:** Staging / Local Testing Environment (`http://localhost:8009` atau `http://localhost:8000`).

---

## DAFTAR ISI & STRUKTUR PENGUJIAN PER ROLE

1. [Panduan Eksekusi & Prinsip Pengujian "No Happy Path"](#1-panduan-eksekusi--prinsip-pengujian-no-happy-path)
2. [ROLE 1: Sales & Sales Manager](#2-role-1-sales--sales-manager)
3. [ROLE 2: Admin Pengiriman, Logistik & Driver](#3-role-2-admin-pengiriman-logistik--driver)
4. [ROLE 3: Inventory Manager, Warehouse Staff & Checker](#4-role-3-inventory-manager-warehouse-staff--checker)
5. [ROLE 4: PPIC, Produksi & Manufaktur](#5-role-4-ppic-produksi--manufaktur)
6. [ROLE 5: Purchasing & Purchasing Manager](#6-role-5-purchasing--purchasing-manager)
7. [ROLE 6: Kasir Ritel (POS) & Customer Service](#7-role-6-kasir-ritel-pos--customer-service)
8. [ROLE 7: Finance Manager, Admin Keuangan & Accounting](#8-role-7-finance-manager-admin-keuangan--accounting)
9. [ROLE 8: Super Admin, Owner & Auditor](#9-role-8-super-admin-owner--auditor)
10. [Matriks Checklist Verifikasi & Pelaporan Temuan Bug](#10-matriks-checklist-verifikasi--pelaporan-temuan-bug)

---

## 1. PANDUAN EKSEKUSI & PRINSIP PENGUJIAN "NO HAPPY PATH"

### Definisi "No Happy Path"
Pengujian **No Happy Path (Negative Testing)** bertujuan menguji ketahanan sistem saat menghadapi kesalahan input pengguna (*human error*), upaya manipulasi data, bypassing alur operasional melalui direct URL manipulation, benturan kuota/kapasitas (*boundary limits*), hingga kondisi inkonsistensi data akuntansi dan persediaan.

### Kategori Kegagalan yang Wajib Dicegah:
1. **Security & Authorization Bypass:** Akun staf memanggil aksi approval tanpa hak akses (harus ditolak HTTP 403 Forbidden).
2. **Cross-Branch Data Leakage:** Akun cabang A mencoba mengakses atau mengubah transaksi cabang B (harus dicegah oleh `canAccessCabang()`).
3. **Workflow State-Machine Violation:** Dokumen yang sudah disetujui atau dikirim diedit atau dihapus secara sepihak (harus dicegah oleh `DocumentLock` / Policy).
4. **Inventory Leakage & Phantom Stock:** Menerbitkan Surat Jalan / DO melebihi stok fisik gudang yang tersedia, atau alokasi bahan baku tanpa ketersediaan batch (harus ditolak validasi).
5. **Financial Imbalance & Overpayment:** Pembayaran piutang atau hutang vendor melebihi sisa tagihan, atau jurnal manual yang debit != kredit (harus ditolak sebelum commit transaksi).

---

## 2. ROLE 1: SALES & SALES MANAGER

### Cakupan Modul:
- Master Customer (`/master/customer`)
- Penawaran Harga / Quotation (`/penjualan/penawaran`)
- Pesanan Penjualan / Sales Order (`/penjualan/so`)

### Matriks Pengujian Negatif Role 1:

| ID Kasus | Deskripsi Skenario Negatif | Langkah Pengujian Presisi | Payload / Data Uji Ekstrem | Hasil yang Diharapkan (Expected Result) | Verifikasi Codebase |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **UAT-SALES-NEG-01** | Sales Staff mencoba menyetujui (Approve) Quotation sendiri | 1. Login sebagai akun **Sales**.<br>2. Buka daftar Quotation.<br>3. Buka detail Quotation draft.<br>4. Coba akses POST langsung ke endpoint `/penjualan/penawaran/{id}/approve` atau cek tombol di UI. | Akun role: `Sales`<br>ID Quotation: `QO-XXXX` | Tombol Approve **tidak muncul** di UI. Jika direct request via API/URL, return **HTTP 403 Forbidden** (*Sales staff excluded from approve quotation*). | `RoleSeeder.php` (privileged action exclusions) & `QuotationPolicy::approve()` |
| **UAT-SALES-NEG-02** | Pengajuan Quotation tanpa item barang (Keranjang kosong) | 1. Buka form Buat Quotation `/penjualan/penawaran/create`.<br>2. Pilih Customer valid.<br>3. Hapus seluruh baris produk sehingga tabel item 0.<br>4. Klik tombol **Simpan Penawaran**. | Customer: `PT Maju`<br>Items: `[]` (Kosong) | Form menolak pengiriman, alert validasi muncul: *"Minimal harus ada 1 item produk yang dimasukkan."* Transaksi tidak tersimpan di database. | `QuotationRequest` validation rule: `'items' => 'required\|array\|min:1'` |
| **UAT-SALES-NEG-03** | Input Item Quotation dengan Qty 0 atau Negatif | 1. Di form Quotation, masukkan produk A.<br>2. Isi kolom Qty dengan angka `0` atau `-10`.<br>3. Klik Simpan. | Produk: `BRG-001`<br>Qty: `0` / `-10`<br>Harga: `50000` | Input form ditolak dengan pesan error validasi: *"Jumlah kuantitas item harus lebih besar dari 0."* Nilai input diblokir. | Form validation rule: `'items.*.qty' => 'required\|numeric\|min:0.01'` |
| **UAT-SALES-NEG-04** | Diskon Item melebihi 100% atau Diskon Total Negatif | 1. Masukkan item produk.<br>2. Di kolom Diskon Item (%), masukkan angka `150`.<br>3. Di kolom Diskon Global (Rp), masukkan angka `-50000`.<br>4. Klik Simpan. | Diskon item: `150%`<br>Diskon global: `-50000` | Form menampilkan error: *"Diskon persentase tidak boleh melebihi 100% dan diskon nilai tidak boleh negatif."* Kalkulasi total tidak menghasilkan nilai negatif. | `QuotationCalculationService` & Request Validator |
| **UAT-SALES-NEG-05** | Mengubah (Edit) Quotation yang sudah Approved / Expired | 1. Cari Quotation yang statusnya telah `Approved` (`STATUS_APPROVE`).<br>2. Coba akses langsung URL `/penjualan/penawaran/{id}/edit`.<br>3. Ubah salah satu harga item lalu klik Simpan. | Status: `Approved`<br>Aksi: `GET /penawaran/{id}/edit` | Sistem menolak dengan **HTTP 403 Forbidden** atau me-redirect kembali ke halaman detail dengan peringatan: *"Dokumen yang telah disetujui tidak dapat diedit. Gunakan fitur Buat Revisi (Versi Baru)."* | `QuotationPolicy::update()`: `$quotation->isEditable()` |
| **UAT-SALES-NEG-06** | Pembuatan SO dari Quotation yang Ditolak (Rejected) | 1. Cari Quotation berstatus `Rejected` (`Ditolak`).<br>2. Coba kirim request POST pembuatan SO mengacu pada ID Quotation tersebut `/penjualan/so/create?quotation_id={id}`. | `quotation_status = 'rejected'` | Sistem memblokir alur dengan notifikasi: *"Hanya penawaran berstatus Disetujui yang dapat diproses menjadi Pesanan Penjualan."* | `SaleOrderController::create` state validation |
| **UAT-SALES-NEG-07** | Bypass Limit Plafon Kredit Customer pada Sales Order | 1. Pilih Customer yang memiliki sisa plafon kredit `Rp 5.000.000`.<br>2. Buat Sales Order tempo kredit senilai `Rp 25.000.000`.<br>3. Sales mencoba submit SO tanpa persetujuan Finance/Manager. | Limit: `5.000.000`<br>Total SO: `25.000.000`<br>Syarat Bayar: `Kredit 30 Hari` | Sistem menghentikan alur langsung dan mengunci status ke **Menunggu Persetujuan Khusus Kredit (Overlimit Hold)**. SO tidak dapat langsung terbit (*cannot bypass credit control*). | `App\Services\CreditLimitCheckService` |
| **UAT-SALES-NEG-08** | Penghapusan (Delete) Sales Order yang sudah masuk alur Delivery / Invoiced | 1. Login sebagai Sales Manager.<br>2. Pilih Sales Order yang sudah memiliki minimal 1 Delivery Order aktif atau Faktur.<br>3. Coba lakukan aksi Hapus (Delete). | Status SO: `In Delivery` atau `Invoiced` | Tombol Hapus tidak tersedia / Action mengembalikan error: *"Pesanan penjualan tidak dapat dihapus karena sudah memiliki dokumen turunan pengiriman/penagihan."* | `SaleOrderPolicy::delete()`: `$saleOrder->status === 'draft'` |

---

## 3. ROLE 2: ADMIN PENGIRIMAN, LOGISTIK & DRIVER

### Cakupan Modul:
- Delivery Order / DO (`/penjualan/do`)
- Surat Jalan / SJ (`/penjualan/sj`)
- Jadwal Pengiriman & Armada (`/penjualan/schedules`)

### Matriks Pengujian Negatif Role 2:

| ID Kasus | Deskripsi Skenario Negatif | Langkah Pengujian Presisi | Payload / Data Uji Ekstrem | Hasil yang Diharapkan (Expected Result) | Verifikasi Codebase |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **UAT-LOG-NEG-01** | Penerbitan DO Melebihi Kuantitas Sisa Pesanan SO | 1. Buka Sales Order dengan sisa barang yang belum dikirim: 5 unit.<br>2. Buat Delivery Order baru.<br>3. Isi kolom Qty Kirim sebesar `10` unit.<br>4. Klik Buat DO. | Sisa SO: `5 PCS`<br>Input Qty DO: `10 PCS` | Validasi error muncul: *"Kuantitas pengiriman (10) tidak boleh melebihi sisa pesanan yang belum terkirim (5)."* Transaksi DO digagalkan. | `DeliveryOrderService::validateRemainingQty()` |
| **UAT-LOG-NEG-02** | Penerbitan DO pada Gudang dengan Stok Fisik Kosong / Kurang | 1. Buat DO dengan produk B sejumlah 50 unit.<br>2. Pilih gudang asal yang hanya memiliki stok fisik 10 unit.<br>3. Klik Simpan & Konfirmasi DO. | Stok Gudang: `10 PCS`<br>Permintaan DO: `50 PCS` | Sistem menolak pengeluaran stok dengan notifikasi: *"Stok di gudang tidak mencukupi untuk memenuhi pengiriman ini (Tersedia: 10, Diminta: 50)."* Tidak terjadi stok minus. | `DeliveryOrder::validateStockAvailability()` |
| **UAT-LOG-NEG-03** | Penerbitan Surat Jalan dari DO yang Belum Disetujui (Draft DO) | 1. Buat DO status masih `Draft` (belum diapprove/dikonfirmasi gudang).<br>2. Buka menu Surat Jalan `/penjualan/sj/create`.<br>3. Paksa pilih ID DO draft tersebut. | Status DO: `draft` | DO tidak muncul di pilihan dropdown konsolidasi. Jika dipaksa via direct request, controller menolak: *"Surat Jalan hanya dapat diterbitkan dari Delivery Order yang telah disetujui."* | `Tahap4SuratJalanTest.php` & `SuratJalanPolicy` |
| **UAT-LOG-NEG-04** | Konsolidasi Surat Jalan dari Customer yang Berbeda | 1. Buka form Surat Jalan konsolidasi.<br>2. Pilih DO-1 (Customer A).<br>3. Coba tambahkan DO-2 (Customer B).<br>4. Klik Terbitkan Surat Jalan. | DO-1: `Customer A`<br>DO-2: `Customer B` | Sistem membatalkan proses dengan pesan error: *"Surat Jalan konsolidasi harus berasal dari pelanggan (customer) yang sama."* | `SuratJalanService::validateConsolidationSameCustomer()` |
| **UAT-LOG-NEG-05** | Duplikasi Pemakaian DO yang Sudah Melekat di Surat Jalan Aktif | 1. Ambil DO yang sudah diterbitkan Surat Jalan-nya (SJ aktif).<br>2. Buka form pembuatan Surat Jalan kedua.<br>3. Masukkan kembali DO yang sama. | DO: `DO-001` (Sudah terdaftar di `SJ-001`) | Sistem menolak dengan alert: *"Delivery order DO-001 sudah terhubung dengan Surat Jalan aktif SJ-001."* | `SuratJalanPolicy` & `DeliveryOrder::isAttachedToActiveSJ()` |
| **UAT-LOG-NEG-06** | Admin Pengiriman Mencoba Menghapus (Delete) DO yang Sedang Dikirim | 1. Login sebagai Admin Pengiriman.<br>2. Buka DO yang berstatus `In Transit` / `Dikirim`.<br>3. Coba kirim request DELETE ke endpoint DO. | Status DO: `in_transit` | Akses ditolak (**HTTP 403 / 422**): *"P0-1: DO yang sedang dikirim atau telah selesai tidak boleh dihapus demi menjaga integritas data stok dan audit log."* | `DeliveryOrderPolicy::delete()` rule P0-1 |
| **UAT-LOG-NEG-07** | Delivery Driver Mengonfirmasi Penerimaan Tanpa Bukti / Penerima Kosong | 1. Login sebagai Driver di portal pengiriman.<br>2. Buka Surat Jalan yang sedang diantar.<br>3. Klik tombol "Konfirmasi Terkirim / Selesai".<br>4. Kosongkan nama penerima barang dan foto bukti serah terima (POD). | Nama Penerima: `""` (Kosong)<br>Foto POD: `null` | Form menolak submit: *"Nama penerima barang dan bukti tanda terima fisik wajib diisi untuk menyelesaikan pengiriman."* Status tetap `in_transit`. | `DeliveryScheduleController::completeDelivery()` validation |

---

## 4. ROLE 3: INVENTORY MANAGER, WAREHOUSE STAFF & CHECKER

### Cakupan Modul:
- Penerimaan Barang / Purchase Receipt (`/gudang/penerimaan`)
- Quality Control / QC Inspeksi (`/manufaktur/qc`)
- Monitoring & Kartu Stok (`/gudang/stok`)
- Stock Opname (`/gudang/opname`)
- Transfer Stok Antar Gudang (`/gudang/transfer`)

### Matriks Pengujian Negatif Role 3:

| ID Kasus | Deskripsi Skenario Negatif | Langkah Pengujian Presisi | Payload / Data Uji Ekstrem | Hasil yang Diharapkan (Expected Result) | Verifikasi Codebase |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **UAT-INV-NEG-01** | Checker Menerima Barang Masuk Melebihi PO Tanpa Toleransi | 1. Buka PO dengan pesanan barang: 100 unit (tanpa klausul over-delivery).<br>2. Buka Penerimaan Barang `/gudang/penerimaan/create`.<br>3. Masukkan Qty Diterima sebesar `150` unit. | PO Qty: `100 PCS`<br>Input Terima: `150 PCS` | Sistem menolak penerimaan: *"Kuantitas barang yang diterima (150) melebihi kuantitas sisa pesanan pada PO (100)."* Barang tidak masuk stok. | `PurchaseReceiptItemValidator` |
| **UAT-INV-NEG-02** | Bypass Tahap QC: Memasukkan Barang Reject Langsung ke Rak Penyimpanan | 1. Pada form inspeksi QC Penerimaan, catat 20 unit berstatus `Reject / Rusak`.<br>2. Coba paksa konfirmasi alokasi rak untuk 20 unit reject tersebut ke Rak Stok Siap Jual. | Status QC: `Reject`<br>Target Rak: `Rak A-01 (Stok Aktif)` | Sistem memblokir alokasi: *"Barang reject tidak dapat dialokasikan ke rak stok komersial. Barang harus ditempatkan di Karantina / Area Retur Supplier."* | `QualityControlService` & `WarehouseConfirmation` |
| **UAT-INV-NEG-03** | Transfer Stok Antar Gudang Melebihi Stok Gudang Asal | 1. Buka Transfer Stok `/gudang/transfer/create`.<br>2. Pilih Gudang Asal: Gudang Pusat (Stok: 5 unit).<br>3. Pilih Gudang Tujuan: Gudang Cabang B.<br>4. Masukkan Qty Transfer: `20` unit. | Gudang Asal: `Stok 5`<br>Qty Transfer: `20` | Form validasi menolak: *"Jumlah barang yang ditransfer melebihi stok yang tersedia di gudang asal (Tersedia: 5, Diminta: 20)."* | `StockTransferRequest` validation |
| **UAT-INV-NEG-04** | Transfer Stok Antar Gudang dengan Gudang Asal = Gudang Tujuan | 1. Buka form Transfer Stok.<br>2. Pilih Gudang Asal: `Gudang Utama Jakarta`.<br>3. Pilih Gudang Tujuan: `Gudang Utama Jakarta` (sama).<br>4. Klik Kirim Transfer. | Gudang Asal: `GD-01`<br>Gudang Tujuan: `GD-01` | Validasi error muncul: *"Gudang asal dan gudang tujuan transfer tidak boleh sama."* | Rule: `'to_warehouse_id' => 'different:from_warehouse_id'` |
| **UAT-INV-NEG-05** | Eksekusi Stock Opname dengan Angka Fisik Negatif | 1. Buka form Stock Opname `/gudang/opname/create`.<br>2. Pada baris produk tertentu, masukkan jumlah fisik dihitung: `-5`.<br>3. Klik Simpan & Post Opname. | Qty Fisik Opname: `-5` | Input ditolak: *"Jumlah fisik hasil opname tidak boleh bernilai negatif."* Transaksi tidak diproses. | `StockOpnameRequest` validation |
| **UAT-INV-NEG-06** | Warehouse Staff Mencoba Menghapus Kartu Riwayat Mutasi Stok | 1. Buka Riwayat Mutasi Stok `/gudang/stok`.<br>2. Pilih salah satu transaksi mutasi stok yang telah tercatat.<br>3. Coba kirim request DELETE ke endpoint mutasi. | Role: `Warehouse Staff`<br>Aksi: `DELETE /stock-movements/{id}` | Akses ditolak (**HTTP 403 Forbidden**). Riwayat mutasi stok bersifat immutable (*append-only ledger*) dan dilarang dihapus. | `StockMovementPolicy::delete() === false` |

---

## 5. ROLE 4: PPIC, PRODUKSI & MANUFAKTUR

### Cakupan Modul:
- Bill of Materials / BOM Resep (`/manufaktur/bom`)
- SPK Produksi / Manufacturing Order (`/manufaktur/spk`)
- Pengeluaran Bahan Baku / Material Issue (`/manufaktur/material_issue`)
- Hasil Produksi & QC Output (`/manufaktur/production`)

### Matriks Pengujian Negatif Role 4:

| ID Kasus | Deskripsi Skenario Negatif | Langkah Pengujian Presisi | Payload / Data Uji Ekstrem | Hasil yang Diharapkan (Expected Result) | Verifikasi Codebase |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **UAT-MFG-NEG-01** | Pembuatan BOM Resep Sirkular (Produk Menjadi Bahan Baku Dirinya Sendiri) | 1. Buat BOM baru untuk Produk Jadi: `Meja Kayu Tipe A`.<br>2. Pada tabel bahan baku, pilih produk yang sama: `Meja Kayu Tipe A`.<br>3. Klik Simpan BOM. | Finished Good: `PROD-01`<br>Component: `PROD-01` | Sistem menolak dengan error: *"BOM tidak valid: Produk jadi tidak boleh dijadikan sebagai bahan baku untuk dirinya sendiri."* | `BillOfMaterialService::validateCircularReference()` |
| **UAT-MFG-NEG-02** | Pengeluaran Bahan Baku (Material Issue) pada SPK yang Sudah Selesai / Dibatalkan | 1. Cari SPK Produksi yang statusnya telah `Completed` atau `Cancelled`.<br>2. Buka Pengeluaran Bahan Baku `/manufaktur/material_issue/create`.<br>3. Paksa pilih nomor SPK tersebut. | SPK Status: `completed` / `cancelled` | Form memblokir pemilihan SPK: *"Bahan baku tidak dapat dikeluarkan untuk SPK yang telah selesai atau dibatalkan."* | `MaterialIssueController::create` validation |
| **UAT-MFG-NEG-03** | Pencatatan Hasil Jadi Produksi Melebihi Toleransi Target SPK | 1. Buka SPK dengan target kuantitas produksi: 100 unit (toleransi maksimal 5%).<br>2. Buka form Hasil Produksi `/manufaktur/production/create`.<br>3. Masukkan hasil jadi: `200` unit. | Target SPK: `100 unit`<br>Hasil Jadi Input: `200 unit` | Sistem memunculkan alert peringatan validasi: *"Hasil produksi melebihi batas toleransi target SPK (Maksimal 105 unit). Harap buat SPK tambahan jika ada over-production."* | `ProductionService::validateOutputThreshold()` |
| **UAT-MFG-NEG-04** | Penghapusan BOM Resep yang Sedang Dipakai oleh SPK Berjalan | 1. Ambil BOM yang saat ini aktif digunakan oleh SPK berstatus `In Progress`.<br>2. Buka daftar BOM `/manufaktur/bom`.<br>3. Klik Hapus BOM. | BOM: `BOM-001` (Active in `SPK-102`) | Sistem menolak penghapusan: *"BOM tidak dapat dihapus karena masih digunakan dalam proses produksi aktif SPK-102. Nonaktifkan status BOM jika sudah tidak dipakai."* | `BillOfMaterialPolicy::delete()` constraint |

---

## 6. ROLE 5: PURCHASING & PURCHASING MANAGER

### Cakupan Modul:
- Supplier / Vendor (`/master/supplier`)
- Order Request / Pengajuan Pembelian (`/pembelian/order-request`)
- Purchase Order / PO (`/pembelian/po`)
- Retur Pembelian (`/pembelian/retur`)

### Matriks Pengujian Negatif Role 5:

| ID Kasus | Deskripsi Skenario Negatif | Langkah Pengujian Presisi | Payload / Data Uji Ekstrem | Hasil yang Diharapkan (Expected Result) | Verifikasi Codebase |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **UAT-PURCH-NEG-01** | Staff Purchasing Mencoba Menyetujui (Approve) PO Sendiri | 1. Login sebagai akun **Purchasing** (staf biasa).<br>2. Buat PO status Draft.<br>3. Buka detail PO.<br>4. Cari tombol Approve atau tembak endpoint `/pembelian/po/{id}/approve`. | Role: `Purchasing`<br>Aksi: `POST /pembelian/po/{id}/approve` | Tombol Approve **tidak tersedia** di UI. Jika direct request, return **HTTP 403 Forbidden**. Hanya Purchasing Manager atau Owner yang memiliki izin approval. | `PurchaseOrderPolicy::approve()` & `RoleSeeder.php` |
| **UAT-PURCH-NEG-02** | Pembuatan PO dari Order Request (OR) yang Belum Disetujui | 1. Buat Order Request status masih `Draft` atau `Submitted` (belum Approved).<br>2. Buka menu PO `/pembelian/po/create?order_request_id={id}`.<br>3. Klik Submit PO. | Status OR: `submitted` (Pending) | Sistem menolak pembuatan PO: *"Purchase Order hanya dapat diterbitkan dari Order Request yang telah disetujui oleh atasan berwenang."* | `PurchaseOrderController::create` OR state validation |
| **UAT-PURCH-NEG-03** | Input Biaya Tambahan (Landed Cost) dengan Nilai Negatif pada PO | 1. Di form pembuatan PO, buka tab/bagian Biaya Tambahan (Ongkos Kirim/Asuransi).<br>2. Masukkan nilai: `-150000`.<br>3. Klik Simpan. | Biaya Tambahan: `-150.000` | Input ditolak validasi: *"Nilai biaya tambahan tidak boleh bernilai negatif."* Kalkulasi total PO tidak boleh berkurang secara tidak sah. | `PurchaseOrderBiayaRequest` validation |
| **UAT-PURCH-NEG-04** | Mengedit Dokumen PO yang Telah Masuk Penerimaan Barang Parsial | 1. Buka PO yang statusnya `Partially Received` (sebagian barang sudah diterima gudang).<br>2. Coba akses `/pembelian/po/{id}/edit` dan ubah harga satuan atau kurangi kuantitas di bawah barang yang sudah diterima fisik. | Status PO: `partially_received`<br>Qty Ubah: `< Qty Received` | Sistem memblokir edit: *"PO yang telah memiliki riwayat penerimaan barang tidak dapat diubah kuantitas atau harganya secara sepihak."* | `PurchaseOrderPolicy::update()` & `DocumentLock` |
| **UAT-PURCH-NEG-05** | Retur Pembelian Melebihi Kuantitas Barang yang Pernah Diterima | 1. Buka form Retur Pembelian `/pembelian/retur/create`.<br>2. Pilih PO dengan barang yang diterima gudang: 10 unit.<br>3. Masukkan kuantitas yang ingin diretur: `25` unit. | Qty Diterima: `10 PCS`<br>Qty Retur Input: `25 PCS` | Validasi gagal: *"Jumlah barang yang diretur (25) tidak boleh melebihi jumlah total barang yang telah diterima dari supplier (10)."* | `PurchaseReturnService::validateReturnLimit()` |

---

## 7. ROLE 6: KASIR RITEL (POS) & CUSTOMER SERVICE

### Cakupan Modul:
- Kasir Cepat POS (`/kasir/pos`)
- Riwayat Transaksi POS (`/kasir/pos/history`)
- Kwitansi Pembayaran Customer Receipt (`/penjualan/receipts`)

### Matriks Pengujian Negatif Role 6:

| ID Kasus | Deskripsi Skenario Negatif | Langkah Pengujian Presisi | Payload / Data Uji Ekstrem | Hasil yang Diharapkan (Expected Result) | Verifikasi Codebase |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **UAT-POS-NEG-01** | Transaksi Kasir POS Tanpa Buka Shift (Modal Awal Belum Dimasukkan) | 1. Login sebagai Kasir.<br>2. Pastikan belum melakukan Buka Shift.<br>3. Buka halaman terminal kasir `/kasir/pos`.<br>4. Scan barcode barang dan klik Bayar / Checkout. | Shift Status: `Closed`<br>Modal Awal: `0` | Sistem mengunci layar transaksi dan memunculkan modal: *"Anda belum membuka shift kasir. Silakan masukkan uang modal awal kasir untuk memulai transaksi."* Transaksi tidak bisa diselesaikan. | `PosSessionMiddleware` & `PosController::checkout` |
| **UAT-POS-NEG-02** | Pembayaran Tunai POS dengan Uang Diterima Kurang dari Total Belanja | 1. Total transaksi keranjang kasir: `Rp 150.000`.<br>2. Pilih metode bayar: Tunai (Cash).<br>3. Di kolom Uang Diterima, masukkan: `Rp 100.000`.<br>4. Klik tombol Cetak Struk / Selesai. | Total: `150.000`<br>Uang Bayar: `100.000` | Tombol submit diblokir, muncul pesan error: *"Nominal uang yang diterima kurang dari total belanja (Kurang: Rp 50.000)."* Struk tidak tercetak dan transaksi tidak tersimpan. | POS Frontend & Backend Payment Validation |
| **UAT-POS-NEG-03** | Tutup Shift Kasir (Z-Report) dengan Kas Fisik Kosong | 1. Klik Tutup Shift Kasir pada POS.<br>2. Pada form rekonsiliasi kas akhir, kosongkan jumlah uang kas fisik di laci.<br>3. Klik tombol Konfirmasi Tutup Shift. | Kas Akhir: `null` / Kosong | Form menolak submit: *"Jumlah uang kas fisik di laci wajib dihitung dan diisi untuk rekonsiliasi Z-Report."* | `PosShiftController::closeShift` validation |
| **UAT-POS-NEG-04** | Customer Service Mencoba Menyetujui (Approve) Penawaran Harga | 1. Login sebagai akun **Customer Service**.<br>2. Buka Penawaran Harga `/penjualan/penawaran`.<br>3. Coba lakukan approval pada penawaran draft. | Role: `Customer Service`<br>Action: `Approve` | Tombol Approve tidak tersedia (**HTTP 403 Forbidden**). CS hanya bertugas menangani input data awal customer dan draft pesanan. | `RoleSeeder.php` & `QuotationPolicy` |

---

## 8. ROLE 7: FINANCE MANAGER, ADMIN KEUANGAN & ACCOUNTING

### Cakupan Modul:
- Faktur Penjualan & Piutang (`/penjualan/invoices`, `/penjualan/receipts`)
- Faktur Pembelian & Hutang Vendor (`/pembelian/invoice`, `/pembelian/payment`)
- Kas & Bank (`/keuangan/cashbank`)
- Jurnal Umum & Buku Besar (`/keuangan/journal`, `/keuangan/journal/ledger`)
- Master Aset Tetap & Depresiasi (`/keuangan/asset`, `/keuangan/asset/depreciation`)

### Matriks Pengujian Negatif Role 7:

| ID Kasus | Deskripsi Skenario Negatif | Langkah Pengujian Presisi | Payload / Data Uji Ekstrem | Hasil yang Diharapkan (Expected Result) | Verifikasi Codebase |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **UAT-FIN-NEG-01** | Pelunasan Piutang Pelanggan Melebihi Sisa Tagihan Faktur (Overpayment) | 1. Buka Faktur Penjualan dengan sisa piutang: `Rp 5.000.000`.<br>2. Buka Penerimaan Pembayaran Customer Receipt `/penjualan/receipts/create`.<br>3. Masukkan jumlah bayar: `Rp 8.000.000` (tanpa alokasi deposit).<br>4. Klik Simpan Pelunasan. | Sisa Tagihan: `5.000.000`<br>Jumlah Bayar: `8.000.000` | Sistem menolak pembayaran: *"Nominal pembayaran (Rp 8.000.000) melebihi sisa tagihan faktur (Rp 5.000.000). Gunakan fitur Deposit jika pelanggan membayar lebih."* | `CustomerReceiptService::validateOverpayment()` |
| **UAT-FIN-NEG-02** | Pembayaran Tagihan Vendor (AP) Menggunakan Akun Kas dengan Saldo Tidak Cukup | 1. Buat Vendor Payment untuk faktur pembelian senilai `Rp 50.000.000`.<br>2. Pilih akun sumber dana: `Kas Kecil Operasional` (Saldo berjalan: `Rp 2.000.000`).<br>3. Klik Proses Pembayaran. | Total Bayar: `50.000.000`<br>Saldo Akun: `2.000.000` | Transaksi dibatalkan dengan notifikasi: *"Saldo akun kas/bank yang dipilih tidak mencukupi untuk melakukan pembayaran ini (Saldo saat ini: Rp 2.000.000)."* | `VendorPaymentService::validateAccountBalance()` |
| **UAT-FIN-NEG-03** | Posting Jurnal Manual Tidak Seimbang (Unbalanced: Debit != Kredit) | 1. Buka form Jurnal Penyesuaian Manual `/keuangan/journal/create`.<br>2. Masukkan baris 1: Akun Beban Listrik (Debit `Rp 1.000.000`).<br>3. Masukkan baris 2: Akun Kas (Kredit `Rp 800.000`).<br>4. Klik Simpan & Posting Jurnal. | Total Debit: `1.000.000`<br>Total Kredit: `800.000`<br>Selisih: `200.000` | Form menolak posting dengan error merah: *"Jurnal tidak seimbang! Total Debit (Rp 1.000.000) harus sama persis dengan Total Kredit (Rp 800.000). Selisih: Rp 200.000."* Transaksi tidak tersimpan di Buku Besar. | `JournalEntryService::validateBalanceIntegrity()` |
| **UAT-FIN-NEG-04** | Transfer Antar Rekening Kas/Bank dengan Akun Sumber = Akun Tujuan | 1. Buka Transfer Antar Kas/Bank `/keuangan/cashbank/transfer/create`.<br>2. Pilih Dari Rekening: `Bank BCA Operasional`.<br>3. Pilih Ke Rekening: `Bank BCA Operasional` (sama).<br>4. Klik Kirim Transfer. | Akun Asal: `BCA`<br>Akun Tujuan: `BCA` | Form menolak submit: *"Rekening sumber dana dan rekening tujuan transfer tidak boleh sama."* | `CashBankTransferRequest` validation |
| **UAT-FIN-NEG-05** | Eksekusi Depresiasi Bulanan Aset Ganda (Double Depreciation pada Periode Sama) | 1. Jalankan proses depresiasi massal untuk periode `Oktober 2026`.<br>2. Tunggu hingga selesai dan jurnal terposting.<br>3. Coba jalankan kembali depresiasi massal untuk periode `Oktober 2026` dengan aset yang sama. | Periode: `10 / 2026`<br>Eksekusi: `2x berturut-turut` | Sistem memblokir eksekusi kedua: *"Aset ini telah disusutkan untuk periode Oktober 2026. Tidak ada entri penyusutan baru yang diproses (Pencegahan beban ganda)."* | `AssetDepreciationService::processMonthly()` idempotency check |
| **UAT-FIN-NEG-06** | Pelepasan Aset (Disposal) pada Aset yang Statusnya Sudah Dihapus / Dilepas | 1. Buka Aset Tetap yang statusnya telah `Disposed` (sudah dijual/scrap).<br>2. Buka Pelepasan Aset `/keuangan/asset/disposal/create`.<br>3. Coba pilih aset tersebut untuk dilepas kembali. | Status Aset: `disposed` | Aset tidak muncul di daftar pilihan aset aktif. Jika dipaksa via ID: *"Aset tidak dapat dilepas karena status aset saat ini bukan aktif."* | `AssetDisposalPolicy::create` asset state validation |

---

## 9. ROLE 8: SUPER ADMIN, OWNER & AUDITOR

### Cakupan Modul:
- Manajemen User & Role RBAC (`/pengaturan/users`)
- Master Cabang & Multi-Tenancy Boundary (`/admin/cabangs`)
- Audit Trail & Hak Akses Global

### Matriks Pengujian Negatif Role 8:

| ID Kasus | Deskripsi Skenario Negatif | Langkah Pengujian Presisi | Payload / Data Uji Ekstrem | Hasil yang Diharapkan (Expected Result) | Verifikasi Codebase |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **UAT-SEC-NEG-01** | Pengguna Non-Admin Mencoba Mengakses Halaman Manajemen Pengguna | 1. Login sebagai akun **Sales** atau **Warehouse Staff**.<br>2. Ketik langsung URL `/pengaturan/users` pada browser.<br>3. Tekan Enter. | URL: `/pengaturan/users`<br>Role: `Sales` | Sistem langsung me-redirect atau menampilkan halaman **HTTP 403 Forbidden** (*Unauthorized action: User does not have permission `view any user`*). | `UserPolicy::viewAny()` & Spatie Role Middleware |
| **UAT-SEC-NEG-02** | Pengguna Mengakses Transaksi Cabang Lain yang Tidak Diberi Wewenang | 1. Akun pengguna ditugaskan di **Cabang Surabaya** (tidak memiliki akses `canAccessCabang` untuk Pusat).<br>2. Ketik URL detail transaksi milik Cabang Pusat: `/penjualan/so/{id_so_pusat}`.<br>3. Tekan Enter. | User Cabang: `Surabaya`<br>Dokumen Cabang: `Pusat (CBG-001)` | Akses ditolak dengan **HTTP 403 Forbidden** (*You are not authorized to view or modify transactions from another branch*). | `canAccessCabang()` helper pada semua Controller & Policy |
| **UAT-SEC-NEG-03** | Auditor Mencoba Melakukan Operasi Modifikasi Data (Create, Update, Delete) | 1. Login sebagai akun **Auditor**.<br>2. Buka modul apa saja (misal: Jurnal Umum, Faktur, atau Stok).<br>3. Coba cari tombol "Tambah", "Ubah", atau "Hapus".<br>4. Coba tembak endpoint POST/PUT/DELETE via API. | Role: `Auditor`<br>Aksi: `POST` / `PUT` / `DELETE` | Seluruh tombol aksi mutasi data **tersembunyi**. Seluruh request selain GET mengembalikan **HTTP 403 Forbidden**. Auditor adalah role read-only murni (*view any only*). | `RoleSeeder.php` (Auditor special block) |
| **UAT-SEC-NEG-04** | Penghapusan Akun Pengguna yang Sedang Login Sendiri (Self-Deletion) | 1. Login sebagai Admin/Super Admin.<br>2. Buka `/pengaturan/users`.<br>3. Cari akun milik sendiri yang sedang aktif digunakan saat ini.<br>4. Klik tombol Hapus Akun. | User ID: `Auth::id()`<br>Aksi: `DELETE /users/{own_id}` | Sistem menolak aksi: *"Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif."* Transaksi dibatalkan. | `UserController::destroy` self-deletion check |

---

## 10. MATRIKS CHECKLIST VERIFIKASI & PELAPORAN TEMUAN BUG

Untuk setiap skenario pengujian di atas, tim penguji UAT wajib mencatat hasil aktual menggunakan format laporan berikut:

```markdown
### Lembar Uji Skenario Negatif UAT

- **ID Kasus Uji:** [Contoh: UAT-SALES-NEG-01]
- **Role Penguji:** [Sales / Inventory / Finance / dll]
- **Tanggal & Waktu Uji:** [DD/MM/YYYY HH:mm WIB]
- **Lingkungan Uji:** [Local / Staging]
- **Akun yang Digunakan:** [email_penguji@domain.com]

**Hasil Pengujian:**
- [ ] **LULUS (PASS):** Sistem berhasil memblokir input/aksi tidak sah dengan pesan validasi yang jelas dan tepat, data tidak mengalami korupsi.
- [ ] **GAGAL (FAIL):** Sistem mengizinkan aksi tidak sah terjadi (terjadi overpayment, stok minus, bypass role, error 500 unhandled, dll).

**Catatan / Log Tambahan:**
[Tuliskan pesan error yang tampil di layar atau sertakan screenshot jika ditemukan ketidaksesuaian]
```

---
*Dokumen ini merupakan standar resmi Acceptance Testing No-Happy-Path untuk seluruh rilis dan patch Duta Tunggal ERP.*
